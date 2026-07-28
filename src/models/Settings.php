<?php

declare(strict_types=1);

namespace viesrood\videokit\models;

use craft\base\Model;
use craft\helpers\App;
use viesrood\videokit\helpers\VideoUrl;

/**
 * Plugin settings.
 *
 * The credential fields reference env vars by default and are resolved through
 * craft\helpers\App::parseEnv() by the getParsed*() getters. Every setting can
 * also be overridden per environment via config/videokit.php.
 */
class Settings extends Model
{
    /** YouTube Data API v3 key. Server-side only; never sent to the browser. */
    public string $youtubeApiKey = '$YOUTUBE_API_KEY';

    /** Vimeo personal access token. Server-side only; never sent to the browser. */
    public string $vimeoAccessToken = '$VIMEO_ACCESS_TOKEN';

    /** Channel id (UC...) whose uploads and playlists the browse modal lists. */
    public string $youtubeChannelId = '$YOUTUBE_CHANNEL_ID';

    /** Vimeo user id to browse; empty falls back to the token owner. */
    public string $vimeoUserId = '$VIMEO_USER_ID';

    /**
     * Provider handles that may be used. Empty means every configured provider.
     *
     * @var string[]
     */
    private array $_enabledProviders = [];

    /** Number of results per page in the browse modal. */
    public int $videosPerPage = 24;

    /** Seconds to cache provider API responses. 0 disables the response cache. */
    public int $cacheDuration = 3600;

    /**
     * Seconds before stored video metadata is fetched again on read. 0 keeps
     * metadata until the utility refreshes it, so no front-end request ever
     * waits on a provider API.
     */
    public int $metadataTtl = 0;

    /** HTTP timeout in seconds for provider calls. */
    public int $timeout = 10;

    /** Embed YouTube through youtube-nocookie.com. */
    public bool $youtubeNoCookie = true;

    /** Send dnt=1 to the Vimeo player. */
    public bool $vimeoDoNotTrack = true;

    /** Render a click-to-load facade instead of a bare iframe by default. */
    public bool $facadeByDefault = true;

    /** Register the bundled facade script and stylesheet automatically. */
    public bool $registerFacadeAssets = true;

    /** Thumbnail width requested for the facade poster image. */
    public int $facadeThumbnailWidth = 1280;

    /** Default aspect ratio for the facade, as a CSS `width / height` value. */
    public string $facadeAspectRatio = '16 / 9';

    /**
     * Craft saves plugin settings with setAttributes($values, false), which
     * only assigns names that attributes() reports. Without this, the getter
     * and setter pair below would be skipped and the setting would silently
     * never save.
     *
     * @return string[]
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['enabledProviders']);
    }

    /**
     * @return string[]
     */
    public function getEnabledProviders(): array
    {
        return $this->_enabledProviders;
    }

    /**
     * Accepts an array of handles (config file) or a comma-separated string
     * (control panel field). The input is untrusted form data, so anything
     * that is not a known handle is dropped, including the empty value a
     * checkbox group always posts.
     *
     * @param array<int|string, mixed>|string|null $providers
     */
    public function setEnabledProviders(array|string|null $providers): void
    {
        if ($providers === null) {
            $this->_enabledProviders = [];

            return;
        }

        if (is_string($providers)) {
            $providers = preg_split('/[\s,]+/', $providers, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $known = [VideoUrl::YOUTUBE, VideoUrl::VIMEO];
        $normalized = [];

        foreach ($providers as $provider) {
            if (!is_string($provider)) {
                continue;
            }

            $provider = strtolower(trim($provider));

            if (in_array($provider, $known, true) && !in_array($provider, $normalized, true)) {
                $normalized[] = $provider;
            }
        }

        $this->_enabledProviders = $normalized;
    }

    /** The resolved YouTube API key ('' when unset). */
    public function getParsedYoutubeApiKey(): string
    {
        return (string)(App::parseEnv($this->youtubeApiKey) ?? '');
    }

    /** The resolved Vimeo access token ('' when unset). */
    public function getParsedVimeoAccessToken(): string
    {
        return (string)(App::parseEnv($this->vimeoAccessToken) ?? '');
    }

    /** The resolved YouTube channel id ('' when unset). */
    public function getParsedYoutubeChannelId(): string
    {
        return (string)(App::parseEnv($this->youtubeChannelId) ?? '');
    }

    /** The resolved Vimeo user id ('' when unset). */
    public function getParsedVimeoUserId(): string
    {
        return (string)(App::parseEnv($this->vimeoUserId) ?? '');
    }

    public function rules(): array
    {
        return [
            [['youtubeApiKey', 'vimeoAccessToken', 'youtubeChannelId', 'vimeoUserId'], 'string'],
            [['facadeAspectRatio'], 'string'],
            [['facadeAspectRatio'], 'match', 'pattern' => '#^\d{1,5}(\.\d{1,3})?(\s*/\s*\d{1,5}(\.\d{1,3})?)?$#'],
            [['videosPerPage'], 'integer', 'min' => 1, 'max' => 50],
            [['facadeThumbnailWidth'], 'integer', 'min' => 120, 'max' => 3840],
            [['timeout'], 'integer', 'min' => 1, 'max' => 120],
            [['cacheDuration', 'metadataTtl'], 'integer', 'min' => 0],
            [
                ['youtubeNoCookie', 'vimeoDoNotTrack', 'facadeByDefault', 'registerFacadeAssets'],
                'boolean',
            ],
        ];
    }
}
