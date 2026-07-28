<?php

declare(strict_types=1);

namespace viesrood\videokit\providers;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use viesrood\videokit\base\Provider;
use viesrood\videokit\helpers\Duration;
use viesrood\videokit\helpers\EmbedOptions;
use viesrood\videokit\helpers\Thumbnails;
use viesrood\videokit\helpers\VideoUrl;
use viesrood\videokit\models\Collection;
use viesrood\videokit\models\Video;
use viesrood\videokit\models\VideoResults;

/**
 * YouTube, through the Data API v3 with a plain API key.
 *
 * A key gives read access to public data only, which covers everything the
 * picker needs: look up a video, search, and list a channel's uploads and
 * playlists. Anything scoped to "me" (likes, subscriptions) would require
 * OAuth and is deliberately out of scope.
 *
 * Quota note: a key gets 10,000 units a day and `search.list` costs 100 of
 * them, so roughly 100 searches. `videos.list` and `playlistItems.list` cost 1.
 * That is why search results are cached and the modal opens on collections.
 */
class YouTube extends Provider
{
    private const BASE_URI = 'https://www.googleapis.com/youtube/v3/';

    public static function handle(): string
    {
        return VideoUrl::YOUTUBE;
    }

    public static function displayName(): string
    {
        return 'YouTube';
    }

    public function isConfigured(): bool
    {
        return $this->getSettings()->getParsedYoutubeApiKey() !== '';
    }

    public function supportsCollections(): bool
    {
        return $this->isConfigured() && $this->getChannelId() !== '';
    }

    public function getCanonicalUrl(string $videoId, ?string $hash = null): string
    {
        return 'https://www.youtube.com/watch?v=' . $videoId;
    }

    public function getEmbedUrl(string $videoId, ?string $hash = null, array $options = []): string
    {
        $params = EmbedOptions::youtube($options);

        // YouTube only loops a single video when it is also given as a
        // one-item playlist.
        if (($params['loop'] ?? '0') === '1') {
            $params['playlist'] = $videoId;
        }

        $host = EmbedOptions::host(self::handle(), $this->getSettings()->youtubeNoCookie);
        $url = $host . '/embed/' . $videoId;

        return $params === [] ? $url : UrlHelper::urlWithParams($url, $params);
    }

    public function getVideoById(string $videoId, ?string $hash = null): ?Video
    {
        if (!VideoUrl::isValidId(self::handle(), $videoId)) {
            return null;
        }

        $videos = $this->fetchVideosByIds([$videoId]);

        return $videos[0] ?? null;
    }

    public function search(string $query, ?string $page = null): VideoResults
    {
        $params = [
            'part' => 'id',
            'type' => 'video',
            'q' => $query,
            'maxResults' => $this->getPerPage(),
        ];

        $channelId = $this->getChannelId();

        if ($channelId !== '') {
            $params['channelId'] = $channelId;
        }

        if ($page !== null && $page !== '') {
            $params['pageToken'] = $page;
        }

        $response = $this->cachedGet('search', $params);

        return $this->hydrateIdList($response, static fn(array $item): mixed => $item['id']['videoId'] ?? null);
    }

    public function getCollections(): array
    {
        if (!$this->supportsCollections()) {
            return [];
        }

        $collections = [];
        $uploadsPlaylistId = $this->getUploadsPlaylistId();

        if ($uploadsPlaylistId !== null) {
            $uploads = new Collection();
            $uploads->type = Collection::TYPE_UPLOADS;
            $uploads->id = $uploadsPlaylistId;
            $uploads->name = Craft::t('videokit', 'Uploads');
            $collections[] = $uploads;
        }

        $response = $this->cachedGet('playlists', [
            'part' => 'snippet,contentDetails',
            'channelId' => $this->getChannelId(),
            'maxResults' => 50,
        ]);

        foreach ($this->items($response) as $item) {
            $id = $item['id'] ?? null;

            if (!is_string($id) || $id === '') {
                continue;
            }

            $playlist = new Collection();
            $playlist->type = Collection::TYPE_PLAYLIST;
            $playlist->id = $id;
            $playlist->name = (string)($item['snippet']['title'] ?? $id);
            $count = $item['contentDetails']['itemCount'] ?? null;
            $playlist->count = is_numeric($count) ? (int)$count : null;

            $collections[] = $playlist;
        }

        return $collections;
    }

    public function getCollectionVideos(Collection $collection, ?string $page = null): VideoResults
    {
        $playlistId = match ($collection->type) {
            Collection::TYPE_UPLOADS => $collection->id !== '' ? $collection->id : $this->getUploadsPlaylistId(),
            Collection::TYPE_PLAYLIST => $collection->id,
            default => null,
        };

        if ($playlistId === null || $playlistId === '') {
            return VideoResults::create([]);
        }

        $params = [
            'part' => 'contentDetails',
            'playlistId' => $playlistId,
            'maxResults' => $this->getPerPage(),
        ];

        if ($page !== null && $page !== '') {
            $params['pageToken'] = $page;
        }

        $response = $this->cachedGet('playlistItems', $params);

        return $this->hydrateIdList(
            $response,
            static fn(array $item): mixed => $item['contentDetails']['videoId'] ?? null,
        );
    }

    protected function getBaseUri(): string
    {
        return self::BASE_URI;
    }

    protected function authorizeQuery(array $query): array
    {
        $query['key'] = $this->getSettings()->getParsedYoutubeApiKey();

        return $query;
    }

    /**
     * Search and playlist endpoints only return ids, so the actual metadata
     * needs a second, cheap `videos.list` call.
     *
     * @param array<string, mixed>|null $response
     * @param callable(array<string, mixed>): mixed $extractId
     */
    private function hydrateIdList(?array $response, callable $extractId): VideoResults
    {
        $ids = [];

        foreach ($this->items($response) as $item) {
            $id = $extractId($item);

            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        $nextPage = $response['nextPageToken'] ?? null;
        $total = $response['pageInfo']['totalResults'] ?? null;

        return VideoResults::create(
            $ids === [] ? [] : $this->fetchVideosByIds($ids),
            is_string($nextPage) && $nextPage !== '' ? $nextPage : null,
            is_numeric($total) ? (int)$total : null,
        );
    }

    /**
     * @param string[] $ids
     * @return Video[]
     */
    private function fetchVideosByIds(array $ids): array
    {
        $response = $this->cachedGet('videos', [
            'part' => 'snippet,contentDetails,statistics,status',
            'id' => implode(',', $ids),
            'maxResults' => 50,
        ]);

        $videos = [];

        foreach ($this->items($response) as $item) {
            $videos[] = $this->parseVideo($item);
        }

        return $videos;
    }

    private function getUploadsPlaylistId(): ?string
    {
        $channelId = $this->getChannelId();

        if ($channelId === '') {
            return null;
        }

        $response = $this->cachedGet('channels', [
            'part' => 'contentDetails',
            'id' => $channelId,
        ]);

        foreach ($this->items($response) as $item) {
            $playlistId = $item['contentDetails']['relatedPlaylists']['uploads'] ?? null;

            if (is_string($playlistId) && $playlistId !== '') {
                return $playlistId;
            }
        }

        return null;
    }

    private function getChannelId(): string
    {
        return $this->getSettings()->getParsedYoutubeChannelId();
    }

    /**
     * @param array<string, mixed>|null $response
     * @return array<int, array<string, mixed>>
     */
    private function items(?array $response): array
    {
        $items = $response['items'] ?? null;

        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, 'is_array'));
    }

    protected function parseVideo(array $data): Video
    {
        $snippet = is_array($data['snippet'] ?? null) ? $data['snippet'] : [];
        $videoId = (string)($data['id'] ?? '');

        $video = new Video();
        $video->provider = self::handle();
        $video->videoId = $videoId;
        $video->url = $this->getCanonicalUrl($videoId);
        $video->title = $this->string($snippet['title'] ?? null);
        $video->description = $this->string($snippet['description'] ?? null);
        $video->authorName = $this->string($snippet['channelTitle'] ?? null);

        $channelId = $this->string($snippet['channelId'] ?? null);

        if ($channelId !== null) {
            $video->authorUrl = 'https://www.youtube.com/channel/' . $channelId;
        }

        $publishedAt = $this->string($snippet['publishedAt'] ?? null);

        if ($publishedAt !== null) {
            $date = DateTimeHelper::toDateTime($publishedAt);
            $video->datePublished = $date === false ? null : $date;
        }

        $duration = $data['contentDetails']['duration'] ?? null;
        $video->duration = Duration::fromIso8601(is_string($duration) ? $duration : null);

        $plays = $data['statistics']['viewCount'] ?? null;
        $video->plays = is_numeric($plays) ? (int)$plays : null;

        $video->private = ($data['status']['privacyStatus'] ?? 'public') === 'private';

        $thumbnails = $snippet['thumbnails'] ?? null;
        $video->thumbnails = is_array($thumbnails) ? Thumbnails::normalize(array_values($thumbnails)) : [];

        // The Data API does not report the video's own dimensions, and the
        // thumbnails are 16:9 even for a vertical short, so guessing from them
        // would be wrong. Leave it unset and let the caller pick a ratio.
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
