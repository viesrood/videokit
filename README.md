# VideoKit for Craft CMS

A video field for YouTube and Vimeo. Editors paste a URL or browse the accounts you
configured; templates get a player URL, a plain iframe, or a privacy-friendly
click-to-load poster that contacts nothing until the visitor clicks.

Three things shape the design:

1. **The element stores the provider reference**, not just a URL. Provider and video id
   sit in the content column, so a template can build a player URL with no database
   read and no API call at all.
2. **Metadata is a cache.** Titles, thumbnails and durations live in VideoKit's own
   table. Rendering a page never waits on YouTube or Vimeo, and the whole table can be
   thrown away and rebuilt.
3. **An API key, not OAuth.** One key per provider in your `.env`. No connect flow, no
   token storage, no per-source administration.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [The field](#the-field)
- [Twig helpers](#twig-helpers)
- [The facade (click to load)](#the-facade-click-to-load)
- [Caching and quota](#caching-and-quota)
- [Security](#security)
- [License](#license)

## Requirements

Craft CMS 5.0.0+ and PHP 8.2+.

## Installation

```bash
composer require viesrood/videokit
php craft plugin/install videokit
```

## Configuration

Add the credentials you have to `.env`:

```
YOUTUBE_API_KEY=
YOUTUBE_CHANNEL_ID=
VIMEO_ACCESS_TOKEN=
VIMEO_USER_ID=
```

Both providers are optional. Without a credential the field still accepts and stores
URLs from that provider, and embeds still work; only titles, thumbnails, durations and
the browse modal need the API.

- **YouTube** needs a [Data API v3](https://console.cloud.google.com/apis/library/youtube.googleapis.com)
  key. Add `YOUTUBE_CHANNEL_ID` (a `UC...` id) to let editors browse that channel's
  uploads and playlists; without it, search and pasting still work.
- **Vimeo** needs a [personal access token](https://developer.vimeo.com/apps) with the
  `public` and `private` scopes. Keyword search additionally requires an approved Vimeo
  app; on an unapproved token search returns nothing and browsing takes over.

Everything is also settable per environment in `config/videokit.php`; see
[`examples/config/videokit.php`](examples/config/videokit.php).

| Setting | Type | Default | Description |
|---|---|---|---|
| `youtubeApiKey` | string | `$YOUTUBE_API_KEY` | YouTube Data API v3 key. |
| `youtubeChannelId` | string | `$YOUTUBE_CHANNEL_ID` | Channel whose uploads and playlists are browsable. |
| `vimeoAccessToken` | string | `$VIMEO_ACCESS_TOKEN` | Vimeo personal access token. |
| `vimeoUserId` | string | `$VIMEO_USER_ID` | Vimeo user to browse; empty uses the token owner. |
| `enabledProviders` | string[] | `[]` | Allowed handles (`youtube`, `vimeo`). Empty allows all configured providers. |
| `videosPerPage` | int | `24` | Results per page in the browse modal. |
| `cacheDuration` | int | `3600` | Seconds to cache API responses. `0` disables caching. |
| `metadataTtl` | int | `0` | Seconds before stored metadata is refetched on read. `0` refreshes only from the utility. |
| `timeout` | int | `10` | HTTP timeout for provider calls. |
| `youtubeNoCookie` | bool | `true` | Embed YouTube through `youtube-nocookie.com`. |
| `vimeoDoNotTrack` | bool | `true` | Send `dnt=1` to the Vimeo player. |
| `facadeByDefault` | bool | `true` | Prefer the click-to-load facade over a bare iframe. |
| `registerFacadeAssets` | bool | `true` | Load the bundled facade script and stylesheet automatically. |
| `facadeAspectRatio` | string | `16 / 9` | Fallback ratio when the provider reports no dimensions. |
| `facadeThumbnailWidth` | int | `1280` | Thumbnail width requested for the poster. |

## The field

Add a field of type **Video** to any field layout. Per field you can choose which
providers are allowed, whether the browse button appears, and whether to show a preview.

The field value is a `Video` model, or `null`:

```twig
{% set video = entry.myVideo %}

{{ video.title }}
{{ video.authorName }}
{{ video.formattedDuration }}   {# "4:32", or "" for a live stream #}
{{ video.providerName }}        {# "YouTube" #}
{{ video.watchUrl }}
{{ video.thumbnail(1280) }}
{{ video.duration8601 }}        {# "PT4M32S", for schema.org #}
```

Because the provider reference lives on the element, you can query on the URL:

```twig
{% set entries = craft.entries().myVideo('*vimeo.com*').all() %}
```

## Twig helpers

```twig
{{ videokit_facade(entry.myVideo) }}            {# click-to-load poster, recommended #}
{{ videokit_embed(entry.myVideo) }}             {# iframe, loads immediately #}
{{ videokit_embed_url(entry.myVideo) }}         {# player URL #}
{{ videokit_thumbnail(entry.myVideo, 1280) }}   {# poster URL #}
{{ videokit_video('https://youtu.be/dQw4w9WgXcQ') }}   {# a Video from a bare URL #}
{% if videokit_configured('youtube') %}...{% endif %}
```

The same helpers are on `craft.videokit` (`craft.videokit.facade(...)`, and so on).

`videokit_embed` and `videokit_facade` are HTML-safe. `videokit_embed_url` and
`videokit_thumbnail` deliberately are not, so Twig keeps escaping them when they end up
in an attribute.

Player options are an allowlist; anything else is dropped:

```twig
{{ videokit_embed(entry.myVideo, {
    autoplay: true,
    muted: true,
    loop: true,
    controls: false,
    playsinline: true,
    start: 30,          {# YouTube only #}
    title: false,       {# Vimeo only #}
    byline: false,      {# Vimeo only #}
    color: '#ff8800',   {# Vimeo only #}
}) }}
```

## The facade (click to load)

`videokit_facade()` renders a poster image with a play button. Nothing is requested
from YouTube or Vimeo until the visitor clicks; only then is the iframe inserted. That
is the real privacy win, well beyond what `youtube-nocookie.com` alone gives you.

```twig
{{ videokit_facade(entry.myVideo, {
    ratio: '16 / 9',
    class: 'rounded shadow',
    showTitle: true,
    thumbnail: myOwnPosterUrl,
    player: { muted: true },
}) }}
```

There is a `<noscript>` fallback link, so the video stays reachable when the loader
script never runs.

One third-party request does remain: the poster image itself, from `i.ytimg.com` or
`i.vimeocdn.com`. Pass a `thumbnail` you host or proxy yourself to remove even that:

```twig
{{ videokit_facade(video, { thumbnail: imagekit(videokit_thumbnail(video, 1280), { width: 1280 }) }) }}
```

To bundle the loader yourself, set `registerFacadeAssets` to `false` and copy
`src/web/assets/facade/dist/videokit-facade.js`. The markup carries everything the
script needs in data attributes, so it works from any bundle.

## Caching and quota

There are two separate layers:

- **API responses** are cached in Craft's cache for `cacheDuration` seconds, tagged
  `videokit` and `videokit:<provider>`. They show up in Craft's own Caches utility.
- **Video metadata** is stored in `videokit_videos` and only refetched when you ask for
  it. With the default `metadataTtl` of `0`, no front-end request ever calls a provider.

The **VideoKit** utility shows what is stored per provider and can clear or refresh it.

Mind the YouTube quota: a key gets 10,000 units a day and `search.list` costs 100 of
them, so roughly 100 searches. `videos.list` and `playlistItems.list` cost 1. The picker
is built around that: it opens on collections rather than search, requires three
characters, debounces typing, caches responses, and rate-limits each control panel user
to 60 requests a minute.

## Security

- API keys are used server-side only. The browser only ever sees provider handles,
  video payloads and opaque paging cursors.
- A URL is only accepted when it parses as a YouTube or Vimeo video URL, with the host
  matched exactly against an allowlist. VideoKit never fetches a URL a user supplied: it
  extracts the video id and calls its own hard-coded API base. So there is no SSRF
  surface, and `https://evil.example.com/?x=youtube.com/watch?v=...` is rejected.
- Player parameters are an allowlist, so a template cannot inject arbitrary query
  parameters or iframe attributes.
- Every control panel endpoint requires a control panel session, a CSRF token and the
  `accessCp` permission; the cache endpoints additionally require `utility:videokit`.
- Provider errors are logged in full and reported to the browser as a generic message.

## License

[MIT](LICENSE.md).
