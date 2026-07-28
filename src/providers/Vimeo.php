<?php

declare(strict_types=1);

namespace viesrood\videokit\providers;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use viesrood\videokit\base\Provider;
use viesrood\videokit\base\ProviderException;
use viesrood\videokit\helpers\EmbedOptions;
use viesrood\videokit\helpers\Thumbnails;
use viesrood\videokit\helpers\VideoUrl;
use viesrood\videokit\models\Collection;
use viesrood\videokit\models\Video;
use viesrood\videokit\models\VideoResults;

/**
 * Vimeo, through the API with a personal access token.
 *
 * The token covers looking up a video and listing the owner's videos, folders
 * and showcases. Site-wide keyword search (`/videos?query=`) additionally
 * requires an approved app, so an unapproved token gets a 403 there; that is
 * handled as a graceful degradation (the picker hides its search box) rather
 * than a hard failure.
 */
class Vimeo extends Provider
{
    private const BASE_URI = 'https://api.vimeo.com/';

    /** Keeps responses small and predictable. */
    private const VIDEO_FIELDS = 'uri,name,description,link,duration,width,height,created_time,privacy.view,'
        . 'pictures.sizes,stats.plays,user.name,user.link';

    public static function handle(): string
    {
        return VideoUrl::VIMEO;
    }

    public static function displayName(): string
    {
        return 'Vimeo';
    }

    public function isConfigured(): bool
    {
        return $this->getSettings()->getParsedVimeoAccessToken() !== '';
    }

    public function getCanonicalUrl(string $videoId, ?string $hash = null): string
    {
        $url = 'https://vimeo.com/' . $videoId;

        return $hash !== null ? $url . '/' . $hash : $url;
    }

    public function getEmbedUrl(string $videoId, ?string $hash = null, array $options = []): string
    {
        $params = EmbedOptions::vimeo($options, $this->getSettings()->vimeoDoNotTrack);

        // Unlisted videos only play when the privacy hash travels with them.
        if ($hash !== null) {
            $params['h'] = $hash;
        }

        $url = EmbedOptions::host(self::handle()) . '/video/' . $videoId;

        return $params === [] ? $url : UrlHelper::urlWithParams($url, $params);
    }

    public function getVideoById(string $videoId, ?string $hash = null): ?Video
    {
        if (!VideoUrl::isValidId(self::handle(), $videoId)) {
            return null;
        }

        $uri = 'videos/' . $videoId;
        $params = ['fields' => self::VIDEO_FIELDS];

        // The API wants the privacy hash appended to the id for unlisted videos.
        if ($hash !== null) {
            $uri .= ':' . $hash;
        }

        $response = $this->cachedGet($uri, $params);

        if ($response === null) {
            return null;
        }

        $video = $this->parseVideo($response);
        $video->hash = $hash;

        return $video;
    }

    public function search(string $query, ?string $page = null): VideoResults
    {
        try {
            return $this->fetchList('videos', [
                'query' => $query,
                'sort' => 'relevant',
            ], $page);
        } catch (ProviderException $e) {
            // Search needs an approved app; without one the picker simply
            // falls back to browsing.
            Craft::warning('VideoKit: Vimeo search is unavailable: ' . $e->getMessage(), __METHOD__);

            return VideoResults::create([]);
        }
    }

    public function getCollections(): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $owner = $this->getOwnerPath();

        $uploads = new Collection();
        $uploads->type = Collection::TYPE_UPLOADS;
        $uploads->name = Craft::t('videokit', 'Videos');

        $collections = [$uploads];

        foreach ([
            [Collection::TYPE_FOLDER, $owner . '/projects'],
            [Collection::TYPE_SHOWCASE, $owner . '/albums'],
        ] as [$type, $uri]) {
            $response = $this->cachedGet($uri, [
                'fields' => 'uri,name,metadata.connections.videos.total',
                'per_page' => 50,
            ]);

            foreach ($this->items($response) as $item) {
                $id = $this->lastUriSegment($item['uri'] ?? null);

                if ($id === null) {
                    continue;
                }

                $collection = new Collection();
                $collection->type = $type;
                $collection->id = $id;
                $collection->name = (string)($item['name'] ?? $id);
                $total = $item['metadata']['connections']['videos']['total'] ?? null;
                $collection->count = is_numeric($total) ? (int)$total : null;

                $collections[] = $collection;
            }
        }

        return $collections;
    }

    public function getCollectionVideos(Collection $collection, ?string $page = null): VideoResults
    {
        $owner = $this->getOwnerPath();

        $uri = match ($collection->type) {
            Collection::TYPE_UPLOADS => $owner . '/videos',
            Collection::TYPE_FOLDER => $collection->id !== '' ? $owner . '/projects/' . $collection->id . '/videos' : null,
            Collection::TYPE_SHOWCASE => $collection->id !== '' ? 'albums/' . $collection->id . '/videos' : null,
            default => null,
        };

        if ($uri === null) {
            return VideoResults::create([]);
        }

        return $this->fetchList($uri, [], $page);
    }

    protected function getBaseUri(): string
    {
        return self::BASE_URI;
    }

    protected function authorizeHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getSettings()->getParsedVimeoAccessToken(),
            // Pinning the API version keeps response shapes stable.
            'Accept' => 'application/vnd.vimeo.*+json;version=3.4',
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function fetchList(string $uri, array $params, ?string $page): VideoResults
    {
        $pageNumber = $this->pageNumber($page);

        $params['fields'] = self::VIDEO_FIELDS;
        $params['per_page'] = $this->getPerPage();
        $params['page'] = $pageNumber;

        $response = $this->cachedGet($uri, $params);

        $videos = [];

        foreach ($this->items($response) as $item) {
            $videos[] = $this->parseVideo($item);
        }

        $hasNext = is_string($response['paging']['next'] ?? null);
        $total = $response['total'] ?? null;

        return VideoResults::create(
            $videos,
            $hasNext ? (string)($pageNumber + 1) : null,
            is_numeric($total) ? (int)$total : null,
        );
    }

    private function pageNumber(?string $page): int
    {
        if ($page === null || preg_match('/^\d+$/', $page) !== 1) {
            return 1;
        }

        return max(1, (int)$page);
    }

    /** `me` unless a specific user id is configured. */
    private function getOwnerPath(): string
    {
        $userId = $this->getSettings()->getParsedVimeoUserId();

        return preg_match('/^\d+$/', $userId) === 1 ? 'users/' . $userId : 'me';
    }

    /**
     * @param array<string, mixed>|null $response
     * @return array<int, array<string, mixed>>
     */
    private function items(?array $response): array
    {
        $items = $response['data'] ?? null;

        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, 'is_array'));
    }

    private function lastUriSegment(mixed $uri): ?string
    {
        if (!is_string($uri)) {
            return null;
        }

        $segment = substr($uri, strrpos($uri, '/') + 1);

        return $segment === '' ? null : $segment;
    }

    protected function parseVideo(array $data): Video
    {
        $videoId = $this->lastUriSegment($data['uri'] ?? null) ?? '';

        $video = new Video();
        $video->provider = self::handle();
        $video->videoId = $videoId;
        $video->url = is_string($data['link'] ?? null) ? $data['link'] : $this->getCanonicalUrl($videoId);
        $video->title = $this->string($data['name'] ?? null);
        $video->description = $this->string($data['description'] ?? null);
        $video->authorName = $this->string($data['user']['name'] ?? null);
        $video->authorUrl = $this->string($data['user']['link'] ?? null);

        $created = $this->string($data['created_time'] ?? null);

        if ($created !== null) {
            $date = DateTimeHelper::toDateTime($created);
            $video->datePublished = $date === false ? null : $date;
        }

        $duration = $data['duration'] ?? null;
        $video->duration = is_numeric($duration) ? (int)$duration : null;

        $plays = $data['stats']['plays'] ?? null;
        $video->plays = is_numeric($plays) ? (int)$plays : null;

        $width = $data['width'] ?? null;
        $height = $data['height'] ?? null;
        $video->width = is_numeric($width) ? (int)$width : null;
        $video->height = is_numeric($height) ? (int)$height : null;

        $video->private = ($data['privacy']['view'] ?? 'anybody') === 'nobody';

        $sizes = $data['pictures']['sizes'] ?? null;
        $video->thumbnails = is_array($sizes) ? Thumbnails::normalize($sizes) : [];

        $video->raw = $data;

        return $video;
    }

    private function string(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
