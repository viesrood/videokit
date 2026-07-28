<?php

/**
 * VideoKit configuration.
 *
 * Copy to config/videokit.php. Anything set here overrides what is saved in the
 * control panel, per environment.
 */

return [
    '*' => [
        // Credentials. Keep these in .env; only the reference belongs in config.
        'youtubeApiKey' => '$YOUTUBE_API_KEY',
        'youtubeChannelId' => '$YOUTUBE_CHANNEL_ID',
        'vimeoAccessToken' => '$VIMEO_ACCESS_TOKEN',
        'vimeoUserId' => '$VIMEO_USER_ID',

        // Empty allows every configured provider.
        'enabledProviders' => [],

        'videosPerPage' => 24,

        // A YouTube search costs 100 of the 10,000 daily quota units, so keep
        // the response cache on.
        'cacheDuration' => 3600,

        // 0 means stored metadata is only refreshed from the VideoKit utility,
        // so no visitor ever waits on a provider API.
        'metadataTtl' => 0,

        'timeout' => 10,

        'youtubeNoCookie' => true,
        'vimeoDoNotTrack' => true,
        'facadeByDefault' => true,
        'registerFacadeAssets' => true,
        'facadeAspectRatio' => '16 / 9',
        'facadeThumbnailWidth' => 1280,
    ],

    'dev' => [
        // Short cache while you are working on templates.
        'cacheDuration' => 60,
    ],
];
