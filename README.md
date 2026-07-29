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
- [Getting your credentials](#getting-your-credentials)
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

## Getting your credentials

Both providers are optional, and so is every credential below. Without them the field
still accepts and stores URLs, and embeds still render, because the provider and video
id come from the URL itself. What you lose is titles, thumbnails, durations and the
browse modal.

### YouTube API key

1. Go to the [Google Cloud Console](https://console.cloud.google.com/) and sign in.
2. Create a project (any name) if you do not have one yet. **No billing account is
   needed**; the YouTube Data API has a free daily quota.
3. Open **APIs & Services → Library**, search for **YouTube Data API v3** and click
   **Enable**.

   Do not pick one of the lookalikes: *YouTube oEmbed API* needs no key at all,
   *Reporting* and *Analytics* are about your own channel statistics and require OAuth,
   and *Embedded Player API* is a JavaScript player.
4. Open **APIs & Services → Credentials → + Create credentials → API key**.
5. Click **Edit API key** and restrict it:
   - **API restrictions**: choose *Restrict key* and tick **YouTube Data API v3** only.
     Do this. An unrestricted Google key works against every API in the project.
   - **Application restrictions**: leave on **None** while developing, and see the
     warning below.
6. Copy the key into `.env` as `YOUTUBE_API_KEY`.

> **Do not use an HTTP referrer restriction.** VideoKit calls the API server-side from
> PHP, so there is no referrer to match and every request is rejected.
>
> **Be careful with an IP restriction too.** It works in production, where the server
> has a fixed outbound address, but locally your requests leave through your router,
> often over an IPv6 address with privacy extensions that rotates every few days. The
> symptom is a 403 with `The provided API key has an IP address restriction`. Use a
> separate, unrestricted key for development.

### YouTube channel ID

Only needed to let editors browse a channel's uploads and playlists. Pasting a URL and
searching work without it.

Open **YouTube Studio → Settings → Channel → Advanced settings**; the channel ID is the
`UC...` string there. Copy it into `.env` as `YOUTUBE_CHANNEL_ID`.

### Vimeo access token

Vimeo has no API keys; you generate a personal access token inside your own app.

1. Go to [developer.vimeo.com/apps](https://developer.vimeo.com/apps) and sign in.
2. **Create an app**, with a name and a short description of what you are building.
3. Open the app and go to the **Authentication** tab.
4. Under **Personal Access Tokens**, click **Generate an access token**, authenticated
   as yourself.
5. Tick only the **public** and **private** scopes. VideoKit never writes to Vimeo, so
   leave `edit`, `upload`, `delete` and `video_files` off.
6. Copy the token immediately (Vimeo shows it once) into `.env` as
   `VIMEO_ACCESS_TOKEN`.

Two things to expect:

- **Keyword search needs an approved app.** Searching all of Vimeo (`/videos?query=`)
  returns 403 for an unapproved app. VideoKit treats that as a graceful degradation:
  the search simply returns nothing and browsing takes over. Request app review on the
  same app page if you want search. Browsing your own videos, folders and showcases
  works straight away.
- **Folders and showcases need a paid Vimeo plan.** They map to `/me/projects` and
  `/me/albums`, which do not exist on a free account, so you will only see "Videos".
  That is not a connection problem.

### Vimeo user ID

Leave empty. VideoKit then browses `/me`, the account the token belongs to. Only set
`VIMEO_USER_ID` if you want to browse a different account's videos.

## Configuration

```
YOUTUBE_API_KEY=
YOUTUBE_CHANNEL_ID=
VIMEO_ACCESS_TOKEN=
VIMEO_USER_ID=
```

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

The field value is a `Video` model, or `null`.

### Rendering a video

The shortest useful version. `videokit_facade()` handles the poster, the play button and
the player.

```twig
{% if entry.video %}
    {{ videokit_facade(entry.video) }}
{% endif %}
```

With a caption and a wrapper:

```twig
{% set video = entry.video %}

{% if video %}
    <figure class="video">
        {{ videokit_facade(video, { class: 'rounded overflow-hidden' }) }}

        {% if video.title %}
            <figcaption>{{ video.title }}</figcaption>
        {% endif %}
    </figure>
{% endif %}
```

### The Video model

```twig
{% set video = entry.video %}

{{ video.title }}               {# "Never Gonna Give You Up" #}
{{ video.description }}
{{ video.authorName }}          {# channel or uploader #}
{{ video.authorUrl }}
{{ video.provider }}            {# "youtube" | "vimeo" #}
{{ video.providerName }}        {# "YouTube" | "Vimeo" #}
{{ video.videoId }}
{{ video.url }}
{{ video.watchUrl }}            {# canonical public page #}
{{ video.formattedDuration }}   {# "4:32", or "" for a live stream #}
{{ video.duration }}            {# 272, or null #}
{{ video.duration8601 }}        {# "PT4M32S" #}
{{ video.aspectRatio }}         {# "1920 / 1080", or the configured fallback #}
{{ video.datePublished|date('j F Y') }}
{{ video.plays }}               {# view count, or null #}
{{ video.thumbnail(1280) }}     {# closest available poster URL #}
{{ video.raw.statistics.likeCount ?? null }}   {# untouched provider payload #}
```

Every metadata property can be empty: a live stream has no duration, a video may have
no stored metadata yet, and Vimeo reports things YouTube does not. Guard accordingly.

```twig
{% if video.formattedDuration %}
    <span class="duration">{{ video.formattedDuration }}</span>
{% endif %}
```

### Structured data

```twig
{% if video and video.title %}
    {% set schema = {
        '@context': 'https://schema.org',
        '@type': 'VideoObject',
        name: video.title,
        description: video.description,
        thumbnailUrl: video.thumbnail(1280),
        uploadDate: video.datePublished ? video.datePublished|atom : null,
        duration: video.duration8601,
        embedUrl: videokit_embed_url(video),
        contentUrl: video.watchUrl,
    }|filter(v => v is not null) %}

    {{ tag('script', { type: 'application/ld+json', html: schema|json_encode }) }}
{% endif %}
```

### Querying

Because the URL is stored in its own content key, you can filter on it:

```twig
{% set vimeoEntries = craft.entries()
    .section('articles')
    .video('*vimeo.com*')
    .all() %}

{% set withVideo = craft.entries().section('articles').video(':notempty:').all() %}
```

## Twig helpers

```twig
{{ videokit_facade(video) }}             {# click-to-load poster, recommended #}
{{ videokit_embed(video) }}              {# iframe, loads immediately #}
{{ videokit_embed_url(video) }}          {# player URL #}
{{ videokit_thumbnail(video, 1280) }}    {# poster URL #}
{{ videokit_video(url) }}                {# a Video model from a bare URL #}
{{ videokit_configured(provider) }}      {# is a provider set up? #}
```

Every helper accepts a field value **or** a plain URL string, so you can embed a video
that is not in a field at all:

```twig
{{ videokit_facade('https://youtu.be/dQw4w9WgXcQ') }}

{% set video = videokit_video('https://vimeo.com/76979871') %}
{{ video.videoId }}   {# 76979871 #}
```

An unrecognised URL yields `null` rather than an error, so this is safe on user input:

```twig
{% set video = videokit_video(entry.someUrlField) %}

{% if video %}
    {{ videokit_facade(video) }}
{% else %}
    <p>That is not a YouTube or Vimeo link.</p>
{% endif %}
```

The same helpers live on `craft.videokit`, if you prefer that style:

```twig
{{ craft.videokit.facade(video) }}
{{ craft.videokit.embed(video) }}
{{ craft.videokit.embedUrl(video) }}
{{ craft.videokit.thumbnail(video, 640) }}
{{ craft.videokit.video('https://youtu.be/dQw4w9WgXcQ') }}
{{ craft.videokit.isConfigured('youtube') }}
{{ craft.videokit.providers()|join(', ') }}   {# "YouTube, Vimeo" #}
```

`videokit_embed` and `videokit_facade` are HTML-safe. `videokit_embed_url` and
`videokit_thumbnail` deliberately are not, so Twig keeps escaping them when they end up
in an attribute:

```twig
<a href="{{ videokit_embed_url(video) }}">Open the player</a>
<img src="{{ videokit_thumbnail(video, 640) }}" alt="">
```

### Player options

Options are an allowlist. Anything not listed here is dropped, so a template cannot
inject arbitrary query parameters or iframe attributes.

| Option | Type | YouTube | Vimeo |
|---|---|---|---|
| `autoplay` | bool | yes | yes |
| `muted` | bool | yes | yes |
| `loop` | bool | yes | yes |
| `controls` | bool | yes | yes |
| `playsinline` | bool | yes | yes |
| `captions` | bool | yes | - |
| `related` | bool | yes | - |
| `start` | int (seconds) | yes | - |
| `end` | int (seconds) | yes | - |
| `language` | string (`nl`, `en-GB`) | yes | yes (subtitles) |
| `title` | bool | - | yes |
| `byline` | bool | - | yes |
| `portrait` | bool | - | yes |
| `color` | string (`#ff8800`) | - | yes |

```twig
{{ videokit_embed(video, {
    autoplay: true,
    muted: true,
    controls: false,
    start: 30,
}) }}
```

A background video, muted and looping, with no chrome:

```twig
<div class="hero">
    {{ videokit_embed(video, {
        autoplay: true,
        muted: true,
        loop: true,
        controls: false,
        playsinline: true,
        title: false,
        byline: false,
    }) }}
</div>
```

Options for the player behind a facade go in a `player` key, because the facade's own
options sit at the top level:

```twig
{{ videokit_facade(video, {
    ratio: '21 / 9',
    class: 'my-8',
    showTitle: true,
    player: { muted: true, controls: false },
}) }}
```

## The facade (click to load)

`videokit_facade()` renders a poster image with a play button. Nothing is requested
from YouTube or Vimeo until the visitor clicks; only then is the iframe inserted. That
is the real privacy win, well beyond what `youtube-nocookie.com` alone gives you.

| Option | Type | Default | Description |
|---|---|---|---|
| `ratio` | string | the video's ratio, else `facadeAspectRatio` | CSS `width / height`. |
| `class` | string | - | Extra classes on the wrapper. |
| `thumbnail` | string | the provider's poster | Your own poster URL. |
| `showTitle` | bool | `false` | Overlay the title on the poster. |
| `player` | hash | `{ autoplay: true }` | Player options, see above. |

There is a `<noscript>` fallback link, so the video stays reachable when the loader
script never runs.

One third-party request does remain: the poster image itself, from `i.ytimg.com` or
`i.vimeocdn.com`. Pass a `thumbnail` you host or proxy yourself to remove even that.
With [ImageKit Toolkit](https://github.com/viesrood/craft-imagekit), for example:

```twig
{% set poster = videokit_thumbnail(video, 1280) %}

{{ videokit_facade(video, {
    thumbnail: poster ? imagekit(poster, { width: 1280 }) : null,
}) }}
```

To bundle the loader yourself, set `registerFacadeAssets` to `false` and copy
`src/web/assets/facade/dist/videokit-facade.js`. The markup carries everything the
script needs in data attributes, so it works from any bundle.

### Styling

The facade renders these classes, all unstyled beyond a responsive box and a centred
play badge:

```
.vk-facade              wrapper, carries --vk-ratio
.vk-facade__button      the click target
.vk-facade__thumbnail   poster <img>
.vk-facade__play        play badge
.vk-facade__title       optional overlaid title
.vk-facade__iframe      the player, after the click
.vk-facade__fallback    the <noscript> link
```

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
