# Privacy Lite YouTube Embeds

Privacy Lite YouTube Embeds replaces YouTube embeds with lightweight local placeholders.

**Fast YouTube embeds. Nothing loads until click.**

The goal is simple: before the visitor clicks, the page should not load YouTube/Google resources in the browser.

Before click, the frontend loads only local resources from the site:

- local HTML markup;
- local CSS from the plugin;
- local JavaScript from the plugin;
- locally cached video thumbnail, when available.

The YouTube player is created only after user interaction and uses `https://www.youtube-nocookie.com/embed/`.

> Important: this plugin is a technical privacy/performance aid. It does not constitute legal advice and does not by itself guarantee compliance with privacy laws. Site owners remain responsible for their own privacy notices, consent setup, and legal assessment.

## Requirements

- **WordPress:** 6.0 or later. In the WordPress.org `readme.txt` format this is expressed as `Requires at least: 6.0`.
- **PHP:** 7.4 or later.

## Features in v1.0.0

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
- Admin tool to scan content and generate missing thumbnails.
- Admin tool to clear the local thumbnail cache.
- Privacy verification notes in the settings page.
- WordPress privacy policy helper text.
- Translation source files in `/languages/`.
- WordPress.org-compatible `readme.txt`.
- GPL license file.
- Uninstall cleanup for options, failed-download transients and local thumbnail files.

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

## Thumbnail tools

The settings page includes two tools.

### Scan content and generate missing thumbnails

Scans up to 50 published public posts/pages per run and downloads missing local thumbnails.

This is useful for existing sites with old YouTube embeds already published before installing the plugin.

### Clear local thumbnail cache

Deletes locally cached thumbnail files from:

`wp-content/uploads/privacy-lite-youtube-embeds/`

It also clears failed-download retry markers.

After clearing, thumbnails are regenerated when content is scanned, saved, or viewed.

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

## Privacy behavior

This plugin is designed to avoid frontend requests to YouTube before user interaction.

The server may contact `img.youtube.com` to download and cache the thumbnail locally. This happens server-side, not in the visitor's browser.

After click, the plugin creates an iframe using:

`https://www.youtube-nocookie.com/embed/VIDEO_ID`

The plugin also adds suggested wording to WordPress' privacy policy guide so site owners can adapt their policy text.

## How to test privacy behavior

Open a page containing a YouTube embed and use your browser DevTools.

1. Open DevTools > Network.
2. Enable “Preserve log” if useful.
3. Reload the page.
4. Before clicking the placeholder, check that there are no requests to:
   - `youtube.com`
   - `youtube-nocookie.com`
   - `ytimg.com`
   - `googlevideo.com`
   - `google.com`
   - `gstatic.com`
5. Click the placeholder.
6. Confirm that the iframe is created and loads from `youtube-nocookie.com`.

## Installation

1. Upload the plugin folder to `wp-content/plugins/privacy-lite-youtube-embeds/`.
2. Activate **Privacy Lite YouTube Embeds** in WordPress.
3. Configure the options under `Settings > Privacy Lite YouTube`.
4. For existing sites, run the thumbnail scanner once or more from the settings page.

## Development notes

The implementation intentionally avoids build tools and dependencies.

File structure:

```text
privacy-lite-youtube-embeds.php
readme.txt
README.md
LICENSE.txt
uninstall.php
assets/
  privacy-lite-youtube-embeds.css
  privacy-lite-youtube-embeds.js
languages/
  privacy-lite-youtube-embeds.pot
  privacy-lite-youtube-embeds-it_IT.po
```

## Translations

A `.pot` template and translation source files are included in `/languages/`.

To use local translations before wordpress.org language packs are available, compile the relevant `.po` file into a `.mo` file with Poedit or a similar gettext tool and place it in the `languages/` directory.
