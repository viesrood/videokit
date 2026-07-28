<?php

declare(strict_types=1);

namespace viesrood\videokit\base;

use Craft;
use craft\base\Component;
use craft\helpers\Json;
use Throwable;
use viesrood\videokit\helpers\VideoUrl;
use viesrood\videokit\models\Settings;
use viesrood\videokit\models\Video;
use viesrood\videokit\Plugin;
use yii\caching\TagDependency;

/**
 * Shared HTTP, caching and URL handling for providers.
 *
 * Two things are worth knowing about the cache here:
 * - the credential is added after the cache key is computed, so no secret ever
 *   ends up in a cache key (and therefore never in a log line);
 * - the TTL comes from the settings and 0 means "do not cache", not "cache
 *   forever". Provider metadata does go stale.
 */
abstract class Provider extends Component implements ProviderInterface
{
    /** The API root. Hard-coded per provider; never taken from user input. */
    abstract protected function getBaseUri(): string;

    /**
     * Turn one item of a provider response into a Video.
     *
     * @param array<string, mixed> $data
     */
    abstract protected function parseVideo(array $data): Video;

    public function supportsSearch(): bool
    {
        return $this->isConfigured();
    }

    public function supportsCollections(): bool
    {
        return $this->isConfigured();
    }

    public function matchUrl(string $url): bool
    {
        $parsed = VideoUrl::parse($url);

        return ($parsed['provider'] ?? null) === static::handle();
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        $parsed = VideoUrl::parse($url);

        if (($parsed['provider'] ?? null) !== static::handle()) {
            return null;
        }

        return $parsed['videoId'];
    }

    public function getHashFromUrl(string $url): ?string
    {
        $parsed = VideoUrl::parse($url);

        if (($parsed['provider'] ?? null) !== static::handle()) {
            return null;
        }

        return $parsed['hash'];
    }

    public function clearCache(): void
    {
        TagDependency::invalidate(Craft::$app->getCache(), $this->getCacheTag());
    }

    protected function getCacheTag(): string
    {
        return Plugin::CACHE_TAG . ':' . static::handle();
    }

    protected function getSettings(): Settings
    {
        $plugin = Plugin::getInstance();

        return $plugin !== null ? $plugin->getSettings() : new Settings();
    }

    protected function getPerPage(): int
    {
        return max(1, min(50, $this->getSettings()->videosPerPage));
    }

    /**
     * A cached GET. Returns null when the resource does not exist.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>|null
     * @throws ProviderException
     */
    protected function cachedGet(string $uri, array $query = []): ?array
    {
        $duration = max(0, $this->getSettings()->cacheDuration);

        if ($duration === 0) {
            return $this->get($uri, $query);
        }

        $cache = Craft::$app->getCache();
        $key = $this->cacheKey('GET', $uri, $query);
        $cached = $cache->get($key);

        if (is_string($cached)) {
            $decoded = Json::decodeIfJson($cached);

            return is_array($decoded) ? $decoded : null;
        }

        $response = $this->get($uri, $query);

        $cache->set(
            $key,
            Json::encode($response ?? []),
            $duration,
            new TagDependency(['tags' => [Plugin::CACHE_TAG, $this->getCacheTag()]]),
        );

        return $response;
    }

    /**
     * An uncached GET. Returns null when the resource does not exist.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>|null
     * @throws ProviderException
     */
    protected function get(string $uri, array $query = []): ?array
    {
        $client = Craft::createGuzzleClient([
            'base_uri' => $this->getBaseUri(),
            'timeout' => max(1, $this->getSettings()->timeout),
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);

        try {
            $response = $client->request('GET', ltrim($uri, '/'), [
                'query' => $this->authorizeQuery($query),
                'headers' => $this->authorizeHeaders() + ['Accept' => 'application/json'],
            ]);
        } catch (Throwable $e) {
            $this->apiError($e, $uri);

            throw new ProviderException(sprintf('%s is unreachable.', static::displayName()), 0, $e);
        }

        $status = $response->getStatusCode();

        if ($status === 404) {
            return null;
        }

        $body = (string)$response->getBody();

        if ($status >= 400) {
            Craft::error(sprintf(
                'VideoKit: %s returned HTTP %d for %s: %s',
                static::handle(),
                $status,
                $uri,
                mb_substr($body, 0, 500),
            ), __METHOD__);

            throw new ProviderException(sprintf('%s rejected the request.', static::displayName()));
        }

        $decoded = Json::decodeIfJson($body);

        if (!is_array($decoded)) {
            Craft::error(sprintf('VideoKit: %s returned a non-JSON body for %s.', static::handle(), $uri), __METHOD__);

            throw new ProviderException(sprintf('%s returned an unexpected response.', static::displayName()));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Query parameters with the credential added, if this provider uses one.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    protected function authorizeQuery(array $query): array
    {
        return $query;
    }

    /**
     * Request headers with the credential added, if this provider uses one.
     *
     * @return array<string, string>
     */
    protected function authorizeHeaders(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $query
     */
    protected function cacheKey(string $method, string $uri, array $query): string
    {
        ksort($query);

        return sprintf('%s:%s:%s', Plugin::CACHE_TAG, static::handle(), md5(Json::encode([$method, $uri, $query])));
    }

    protected function apiError(Throwable $e, string $context): void
    {
        Craft::error(sprintf(
            'VideoKit: %s request to %s failed: %s',
            static::handle(),
            $context,
            $e->getMessage(),
        ), __METHOD__);
    }
}
