<?php

declare(strict_types=1);

namespace viesrood\videokit\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use craft\helpers\Json;
use DateTime;
use Throwable;
use viesrood\videokit\base\ProviderException;
use viesrood\videokit\helpers\VideoUrl;
use viesrood\videokit\models\Video;
use viesrood\videokit\Plugin;
use viesrood\videokit\records\VideoRecord;

/**
 * Reads and writes the video metadata cache.
 *
 * The important rule here: reading a stored video never touches a provider
 * API. Fetching only happens when something explicitly asks for it (the
 * picker, the utility, or an element save), so rendering a page or opening an
 * element index can never stall on YouTube or burn quota.
 */
class VideosService extends Component
{
    /**
     * Request-level identity map: "provider:videoId" => Video|false.
     *
     * @var array<string, Video|false>
     */
    private array $_stored = [];

    /**
     * The stored metadata for a video, without ever contacting the provider.
     */
    public function getStoredVideo(string $provider, string $videoId): ?Video
    {
        $key = $provider . ':' . $videoId;

        if (!array_key_exists($key, $this->_stored)) {
            $record = $this->getRecord($provider, $videoId);
            $this->_stored[$key] = $record !== null ? $this->toVideo($record) : false;
        }

        $video = $this->_stored[$key];

        return $video === false ? null : $video;
    }

    /**
     * A video by provider reference, fetching it when it is missing, stale or
     * explicitly refreshed.
     */
    public function getVideo(string $provider, string $videoId, ?string $hash = null, bool $refresh = false): ?Video
    {
        if (!$refresh) {
            $stored = $this->getStoredVideo($provider, $videoId);

            if ($stored !== null && !$this->isStale($provider, $videoId)) {
                $stored->hash = $stored->hash ?? $hash;

                return $stored;
            }
        }

        $fetched = $this->fetch($provider, $videoId, $hash);

        if ($fetched !== null) {
            return $fetched;
        }

        // The lookup failed (no credential, rate limit, deleted video). Falling
        // back to whatever is stored keeps the field usable.
        return $this->getStoredVideo($provider, $videoId);
    }

    /**
     * A video by URL. Returns null when the URL is not a recognised video URL.
     */
    public function getVideoByUrl(string $url, bool $refresh = false): ?Video
    {
        $parsed = VideoUrl::parse($url);

        if ($parsed === null) {
            return null;
        }

        return $this->getVideo($parsed['provider'], $parsed['videoId'], $parsed['hash'], $refresh);
    }

    /**
     * Make sure metadata exists for a video, without refetching what is
     * already there. Used as a safety net on element save.
     */
    public function ensureVideo(Video $video): bool
    {
        if ($video->provider === null || $video->videoId === null) {
            return false;
        }

        if ($this->getStoredVideo($video->provider, $video->videoId) !== null) {
            return true;
        }

        if ($video->hasMetadata()) {
            return $this->saveVideo($video);
        }

        return $this->fetch($video->provider, $video->videoId, $video->hash) !== null;
    }

    public function saveVideo(Video $video): bool
    {
        if ($video->provider === null || $video->videoId === null || $video->videoId === '') {
            return false;
        }

        $record = $this->getRecord($video->provider, $video->videoId) ?? new VideoRecord();
        $record->provider = $video->provider;
        $record->videoId = $video->videoId;
        $record->url = (string)($video->url ?? '');
        $record->data = $video->serializeData();

        try {
            $saved = $record->save(false);
        } catch (Throwable $e) {
            Craft::error('VideoKit: could not store video metadata: ' . $e->getMessage(), __METHOD__);

            return false;
        }

        unset($this->_stored[$video->provider . ':' . $video->videoId]);

        return $saved;
    }

    /**
     * Drop stored metadata, for one provider or for all of them.
     */
    public function delete(?string $provider = null): int
    {
        $condition = $provider !== null ? ['provider' => $provider] : '1=1';
        $deleted = Db::delete(VideoRecord::TABLE, $condition);

        $this->_stored = [];

        return $deleted;
    }

    /**
     * Refetch stored videos from their provider.
     *
     * @return array{refreshed: int, failed: int, skipped: int}
     */
    public function refresh(?string $provider = null, int $limit = 200): array
    {
        $query = VideoRecord::find()
            ->select(['provider', 'videoId', 'data'])
            ->orderBy(['dateUpdated' => SORT_ASC])
            ->limit(max(1, $limit))
            ->asArray();

        if ($provider !== null) {
            $query->where(['provider' => $provider]);
        }

        $refreshed = 0;
        $failed = 0;
        $skipped = 0;

        /** @var array<string, mixed> $row */
        foreach ($query->all() as $row) {
            $handle = (string)$row['provider'];
            $videoId = (string)$row['videoId'];

            if (!Plugin::getInstance()?->getProviders()->isConfigured($handle)) {
                $skipped++;

                continue;
            }

            $hash = null;
            $data = Json::decodeIfJson((string)($row['data'] ?? ''));

            if (is_array($data) && is_string($data['hash'] ?? null)) {
                $hash = $data['hash'];
            }

            if ($this->fetch($handle, $videoId, $hash) !== null) {
                $refreshed++;
            } else {
                $failed++;
            }
        }

        return ['refreshed' => $refreshed, 'failed' => $failed, 'skipped' => $skipped];
    }

    /**
     * Stored rows per provider handle.
     *
     * @return array<string, int>
     */
    public function getCounts(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = VideoRecord::find()
            ->select(['provider', 'total' => 'COUNT(*)'])
            ->groupBy(['provider'])
            ->asArray()
            ->all();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string)$row['provider']] = (int)$row['total'];
        }

        return $counts;
    }

    /**
     * Fetch fresh metadata from the provider and store it.
     */
    private function fetch(string $provider, string $videoId, ?string $hash = null): ?Video
    {
        $source = Plugin::getInstance()?->getProviders()->getEnabledProviderByHandle($provider);

        if ($source === null) {
            return null;
        }

        try {
            $video = $source->getVideoById($videoId, $hash);
        } catch (ProviderException $e) {
            Craft::warning('VideoKit: could not fetch video metadata: ' . $e->getMessage(), __METHOD__);

            return null;
        }

        if ($video === null) {
            return null;
        }

        $video->hash = $video->hash ?? $hash;
        $this->saveVideo($video);

        return $video;
    }

    private function isStale(string $provider, string $videoId): bool
    {
        $ttl = Plugin::getInstance()?->getSettings()->metadataTtl ?? 0;

        if ($ttl <= 0) {
            return false;
        }

        $dateUpdated = VideoRecord::find()
            ->select(['dateUpdated'])
            ->where(['provider' => $provider, 'videoId' => $videoId])
            ->scalar();

        if (!is_string($dateUpdated)) {
            return true;
        }

        $updated = DateTime::createFromFormat('Y-m-d H:i:s', $dateUpdated, new \DateTimeZone('UTC'));

        if ($updated === false) {
            return true;
        }

        return (time() - $updated->getTimestamp()) > $ttl;
    }

    private function getRecord(string $provider, string $videoId): ?VideoRecord
    {
        /** @var VideoRecord|null $record */
        $record = VideoRecord::findOne(['provider' => $provider, 'videoId' => $videoId]);

        return $record;
    }

    private function toVideo(VideoRecord $record): Video
    {
        $data = Json::decodeIfJson((string)($record->data ?? ''));

        $video = Video::fromArray(is_array($data) ? $data : []);
        $video->provider = $record->provider;
        $video->videoId = $record->videoId;
        $video->url = $video->url ?? $record->url;

        return $video;
    }
}
