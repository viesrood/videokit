# Release Notes for VideoKit

## 1.1.1 - 2026-08-06

### Fixed

- Vimeo `pictures.sizes` thumbnails are normalized from their `link` property,
  so posters and responsive image candidates are stored in the metadata cache.

## 1.1.0 - 2026-08-06

### Added

- Vimeo `autopause` and `pictureInPicture` player options.
- An allowlisted `attributes` option for safe iframe overrides such as classes,
  titles, loading behavior, sandbox permissions and referrer policy.

## 1.0.0 - 2026-07-29

### Added

- A **Video** field for YouTube and Vimeo. Editors paste a URL or pick a video from a
  search and browse modal covering the configured channel, playlists, folders and
  showcases.
- The element stores the provider reference (url, provider, video id, and the Vimeo
  privacy hash for unlisted videos), so a player URL can be built with no database read
  and no API call.
- Video metadata is cached in the `videokit_videos` table and never fetched while a page
  renders, which keeps element indexes and front-end requests off the provider APIs.
- Twig helpers `videokit_facade()`, `videokit_embed()`, `videokit_embed_url()`,
  `videokit_thumbnail()`, `videokit_video()` and `videokit_configured()`, plus the same
  set on `craft.videokit`.
- A click-to-load facade that requests nothing from YouTube or Vimeo until the visitor
  clicks, with a `<noscript>` fallback and a bundled dependency-free loader script.
- A **VideoKit** control panel utility to inspect, clear and refresh the metadata cache,
  and a "VideoKit API responses" entry in Craft's own Caches utility.
- Dutch and English control panel translations.

### Security

- URLs are matched against an exact host allowlist and parsed into a provider and video
  id. VideoKit never fetches a user-supplied URL; it calls its own hard-coded API base
  with the extracted id, so there is no SSRF surface.
- Player parameters are an allowlist, so a template cannot inject arbitrary query
  parameters or iframe attributes.
- API keys stay server-side; control panel endpoints require a session, a CSRF token and
  the `accessCp` permission, and are rate-limited per user.
