# Release Notes for VideoKit

## 2.0.0-beta.2 - 2026-11-11

### Fixed

- **The `videokit_*()` functions no longer disappear after a queue job on Craft 6.**
  Craft 6 binds Twig as a scoped service, and Laravel's queue worker flushes scoped instances
  after every job. The Twig extension was registered once, on the first instance, so a template
  rendered after a job in the same process could no longer call them. The extension is now
  registered on every Twig instance that is resolved.

## 2.0.0-beta.1 - 2026-11-11

### Changed

- **Requires Craft CMS 6.0.0-beta.1 or later and PHP 8.5.** The plugin runs as a legacy plugin
  through `craftcms/yii2-adapter`, which it now requires. It is not yet rewritten for Craft 6's
  Laravel base classes.
- `VideoField::getThumbHtml()` takes Craft 6's element interface and an optional
  `ImageTransformMode`, as `ThumbableFieldInterface` now requires.

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
