<?php

declare(strict_types=1);

/**
 * Source strings. This file is the identity map that keeps the message keys
 * discoverable; the actual translations live in the other language folders.
 */

return [
    // Field
    'Video' => 'Video',
    'Paste a YouTube or Vimeo URL' => 'Paste a YouTube or Vimeo URL',
    'Browse' => 'Browse',
    'Enter a YouTube or Vimeo video URL.' => 'Enter a YouTube or Vimeo video URL.',
    'Videos from {provider} are not allowed here.' => 'Videos from {provider} are not allowed here.',
    'No video provider is configured yet. You can still paste a video URL, but titles and thumbnails will stay empty.' => 'No video provider is configured yet. You can still paste a video URL, but titles and thumbnails will stay empty.',

    // Field settings
    'Allowed providers' => 'Allowed providers',
    'Leave every option unchecked to allow all configured providers.' => 'Leave every option unchecked to allow all configured providers.',
    'Show the browse button' => 'Show the browse button',
    'Lets editors search and browse the configured accounts instead of only pasting a URL.' => 'Lets editors search and browse the configured accounts instead of only pasting a URL.',
    'Show a preview' => 'Show a preview',
    'Shows the thumbnail, title and duration of the selected video.' => 'Shows the thumbnail, title and duration of the selected video.',

    // Picker (JavaScript)
    'Cancel' => 'Cancel',
    'Choose a video' => 'Choose a video',
    'Clear' => 'Clear',
    'Could not reach the video service.' => 'Could not reach the video service.',
    'Load more' => 'Load more',
    'Loading...' => 'Loading...',
    'No videos found.' => 'No videos found.',
    'Nothing to show yet.' => 'Nothing to show yet.',
    'Refresh' => 'Refresh',
    'Remove video' => 'Remove video',
    'Search' => 'Search',
    'Search videos' => 'Search videos',
    'Select' => 'Select',
    'This video has no stored details yet.' => 'This video has no stored details yet.',

    // Collections
    'Uploads' => 'Uploads',
    'Videos' => 'Videos',

    // Controller responses
    'Enter at least three characters.' => 'Enter at least three characters.',
    'That is not a YouTube or Vimeo video URL.' => 'That is not a YouTube or Vimeo video URL.',
    'That video provider is not available.' => 'That video provider is not available.',
    'The request failed. Check the Craft logs for details.' => 'The request failed. Check the Craft logs for details.',
    'The video service did not respond. Try again later.' => 'The video service did not respond. Try again later.',
    'This provider does not support search.' => 'This provider does not support search.',
    'Too many requests. Wait a moment and try again.' => 'Too many requests. Wait a moment and try again.',
    'Unknown collection.' => 'Unknown collection.',
    'VideoKit is not available.' => 'VideoKit is not available.',

    // Front end
    'Play video' => 'Play video',
    'Play video: {title}' => 'Play video: {title}',
    'Watch the video on {provider}' => 'Watch the video on {provider}',

    // Settings
    'Every setting below can also be set per environment in config/videokit.php. Credentials are only ever used server-side and never reach the browser.' => 'Every setting below can also be set per environment in config/videokit.php. Credentials are only ever used server-side and never reach the browser.',
    'Credentials' => 'Credentials',
    'YouTube API key' => 'YouTube API key',
    'A YouTube Data API v3 key. Without it, YouTube URLs still work but titles and thumbnails stay empty.' => 'A YouTube Data API v3 key. Without it, YouTube URLs still work but titles and thumbnails stay empty.',
    'YouTube channel ID' => 'YouTube channel ID',
    'The channel (UC...) whose uploads and playlists editors can browse. Leave empty to allow search only.' => 'The channel (UC...) whose uploads and playlists editors can browse. Leave empty to allow search only.',
    'Vimeo access token' => 'Vimeo access token',
    'A Vimeo personal access token. Keyword search additionally requires an approved Vimeo app; without one, browsing still works.' => 'A Vimeo personal access token. Keyword search additionally requires an approved Vimeo app; without one, browsing still works.',
    'Vimeo user ID' => 'Vimeo user ID',
    'Whose videos editors can browse. Leave empty to use the account the token belongs to.' => 'Whose videos editors can browse. Leave empty to use the account the token belongs to.',
    'Browsing' => 'Browsing',
    'Enabled providers' => 'Enabled providers',
    'Videos per page' => 'Videos per page',
    'How many results the browse modal loads at a time.' => 'How many results the browse modal loads at a time.',
    'Caching' => 'Caching',
    'API response cache' => 'API response cache',
    'Seconds to cache provider API responses. Keep this above zero: a YouTube search costs 100 of the 10,000 daily quota units. Set to 0 to disable caching.' => 'Seconds to cache provider API responses. Keep this above zero: a YouTube search costs 100 of the 10,000 daily quota units. Set to 0 to disable caching.',
    'Metadata lifetime' => 'Metadata lifetime',
    'Seconds before stored titles and thumbnails are fetched again while a page renders. 0 (recommended) keeps metadata until you refresh it from the VideoKit utility, so no visitor ever waits on a provider API.' => 'Seconds before stored titles and thumbnails are fetched again while a page renders. 0 (recommended) keeps metadata until you refresh it from the VideoKit utility, so no visitor ever waits on a provider API.',
    'Request timeout' => 'Request timeout',
    'Seconds to wait for a provider API.' => 'Seconds to wait for a provider API.',
    'Privacy and embedding' => 'Privacy and embedding',
    'Use youtube-nocookie.com' => 'Use youtube-nocookie.com',
    'Embeds YouTube through its no-cookie host.' => 'Embeds YouTube through its no-cookie host.',
    'Ask Vimeo not to track' => 'Ask Vimeo not to track',
    'Adds dnt=1 to the Vimeo player URL.' => 'Adds dnt=1 to the Vimeo player URL.',
    'Register the facade assets' => 'Register the facade assets',
    'Loads the bundled click-to-load script and stylesheet when videokit_facade() is used. Turn this off if you ship your own copy.' => 'Loads the bundled click-to-load script and stylesheet when videokit_facade() is used. Turn this off if you ship your own copy.',
    'Facade aspect ratio' => 'Facade aspect ratio',
    'Fallback ratio for the poster when the provider does not report the video dimensions.' => 'Fallback ratio for the poster when the provider does not report the video dimensions.',
    'Facade poster width' => 'Facade poster width',
    'Thumbnail width requested for the poster image.' => 'Thumbnail width requested for the poster image.',

    // Utility
    'VideoKit stores video titles, thumbnails and durations in its own table so pages never wait on a provider API. Everything here is a cache: clearing it is always safe.' => 'VideoKit stores video titles, thumbnails and durations in its own table so pages never wait on a provider API. Everything here is a cache: clearing it is always safe.',
    'Provider' => 'Provider',
    'Status' => 'Status',
    'Stored videos' => 'Stored videos',
    'Configured' => 'Configured',
    'No credential set' => 'No credential set',
    'All providers' => 'All providers',
    'Limit the action below to one provider.' => 'Limit the action below to one provider.',
    'Clear cache' => 'Clear cache',
    'Refresh metadata' => 'Refresh metadata',
    '{count, plural, =1{1 video} other{# videos}} cleared.' => '{count, plural, =1{1 video} other{# videos}} cleared.',
    'Refreshed {refreshed}, failed {failed}, skipped {skipped}.' => 'Refreshed {refreshed}, failed {failed}, skipped {skipped}.',
    'VideoKit API responses' => 'VideoKit API responses',
];
