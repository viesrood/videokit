<?php

declare(strict_types=1);

namespace viesrood\videokit\base;

use viesrood\videokit\models\Collection;
use viesrood\videokit\models\Video;
use viesrood\videokit\models\VideoResults;

/**
 * The contract every video source implements.
 *
 * Providers are code-defined and authenticate with a single API key or token
 * from the plugin settings, so there is no per-source CRUD, no OAuth handshake
 * and no token storage.
 */
interface ProviderInterface
{
    /** Stable handle, used in settings, requests and the metadata table. */
    public static function handle(): string;

    /** Human-readable name for the control panel. */
    public static function displayName(): string;

    /** Whether the credential this provider needs is present. */
    public function isConfigured(): bool;

    /** Whether keyword search is available. */
    public function supportsSearch(): bool;

    /** Whether the provider can list browsable collections. */
    public function supportsCollections(): bool;

    /** Whether this provider recognises the given URL. */
    public function matchUrl(string $url): bool;

    /** The video id in the given URL, or null when it is not ours. */
    public function getVideoIdFromUrl(string $url): ?string;

    /** The privacy hash in the given URL, for providers that use one. */
    public function getHashFromUrl(string $url): ?string;

    /** The canonical public URL for a video. */
    public function getCanonicalUrl(string $videoId, ?string $hash = null): string;

    /** Fetch a single video's metadata, or null when it does not exist. */
    public function getVideoById(string $videoId, ?string $hash = null): ?Video;

    /** Search the provider by keyword. */
    public function search(string $query, ?string $page = null): VideoResults;

    /**
     * The collections shown in the picker sidebar.
     *
     * @return Collection[]
     */
    public function getCollections(): array;

    /** One page of videos from a collection. */
    public function getCollectionVideos(Collection $collection, ?string $page = null): VideoResults;

    /**
     * The player URL. Must work without any credential, because it is built
     * from the provider and id stored on the element itself.
     *
     * @param array<string, mixed> $options
     */
    public function getEmbedUrl(string $videoId, ?string $hash = null, array $options = []): string;

    /** Invalidate every cached API response for this provider. */
    public function clearCache(): void;
}
