# Privacy Lite YouTube Embeds

Privacy Lite YouTube Embeds replaces YouTube embeds with fast, privacy-friendly local placeholders.

Before the visitor clicks, the page loads only local resources from the site:

- local HTML markup;
- local CSS from the plugin;
- local JavaScript from the plugin;
- locally cached video thumbnail, when available.

The YouTube player is created only after user interaction and uses `https://www.youtube-nocookie.com/embed/`.

## Features in v0.1.1

- Replaces Gutenberg YouTube embed blocks.
- Optional replacement scope for all YouTube videos found in post content.
- Replaces classic oEmbed output when scope is set to “All”.
- Replaces manually pasted YouTube iframes when scope is set to “All”.
- Downloads YouTube thumbnails server-side into the WordPress uploads directory.
- Pre-generates local thumbnails when a post is saved, where possible.
- Avoids repeated failed thumbnail downloads for 12 hours.
- Serves thumbnails locally from `/wp-content/uploads/privacy-lite-youtube-embeds/`.
- Falls back to a local CSS placeholder when no thumbnail can be downloaded.
- Loads YouTube only after click.
- Uses the privacy-enhanced `youtube-nocookie.com` embed domain.
- Optional consent/privacy text in the placeholder.
- Optional autoplay after click.
- Accessible button markup with keyboard support.
- Settings link from the WordPress plugins screen.

## Settings

Go to:

`Settings > Privacy Lite YouTube`

Available options:

- **Replacement scope**
  - Only Gutenberg YouTube embed blocks
  - All YouTube videos found in content
- **Consent text**
  - Enable/disable the short message shown in the placeholder
- **Consent message**
  - Custom editable message
- **Autoplay after click**
  - Start playback immediately after the visitor clicks the placeholder

## Thumbnail handling

The plugin does not print remote YouTube thumbnails in the frontend.

When a YouTube video is found, the plugin attempts to download a thumbnail server-side from YouTube and saves it locally in:

`wp-content/uploads/privacy-lite-youtube-embeds/`

It tries, in order:

1. `maxresdefault.jpg`
2. `sddefault.jpg`
3. `hqdefault.jpg`

The downloaded image is validated before being saved.

If no valid thumbnail can be downloaded, the frontend uses a CSS-only local placeholder. Failed thumbnail download attempts are cached for 12 hours to avoid repeated server-side requests.

When a post is saved, the plugin also scans the content and tries to pre-generate thumbnails for detected YouTube videos. This keeps the frontend lighter for newly edited content.

## Privacy note

This plugin is designed to avoid frontend requests to YouTube before user interaction.

The server may contact `img.youtube.com` to download and cache the thumbnail locally. This happens server-side, not in the visitor's browser.

After click, the plugin creates an iframe using:

`https://www.youtube-nocookie.com/embed/VIDEO_ID`

## Installation

1. Upload the plugin folder to `wp-content/plugins/privacy-lite-youtube-embeds/`.
2. Activate **Privacy Lite YouTube Embeds** in WordPress.
3. Configure the options under `Settings > Privacy Lite YouTube`.

## Development notes

The v0.1.1 implementation intentionally avoids build tools and dependencies.

File structure:

```text
privacy-lite-youtube-embeds.php
assets/
  privacy-lite-youtube-embeds.css
  privacy-lite-youtube-embeds.js
```

## Planned improvements

Possible next steps:

- Admin tool to scan posts and pre-generate thumbnails in bulk.
- Admin list of detected YouTube videos and thumbnail status.
- Button to clear cached thumbnails.
- More granular control for iframe parameters.
- Optional integration hooks for consent platforms.
- Translation template file.
