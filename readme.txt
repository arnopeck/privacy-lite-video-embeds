=== Privacy Lite YouTube Embeds ===
Contributors: arnopeck
Donate link: https://ko-fi.com/luminescenza
Tags: youtube, embeds, privacy, performance, lazy load
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast YouTube embeds. Nothing loads until click.

== Description ==

Privacy Lite YouTube Embeds replaces YouTube embeds with lightweight local placeholders.

Fast YouTube embeds. Nothing loads until click.

Before the visitor clicks, the frontend loads only local resources from the site:

* local HTML markup;
* local CSS from the plugin;
* local JavaScript from the plugin;
* locally cached video thumbnail, when available.

The YouTube player is created only after user interaction and uses the privacy-enhanced `youtube-nocookie.com` embed domain.

This can help reduce external frontend requests, improve perceived loading performance, and provide a more privacy-friendly default behavior for embedded YouTube videos.

Important: this plugin is a technical privacy/performance aid. It does not constitute legal advice and does not by itself guarantee compliance with privacy laws. Site owners remain responsible for their own privacy notices, consent setup, and legal assessment.

= Main features =

* Replaces Gutenberg YouTube embed blocks.
* Optional replacement scope for all YouTube videos found in post content.
* Replaces classic oEmbed output when scope is set to "All".
* Replaces manually pasted YouTube iframes when scope is set to "All".
* Downloads YouTube thumbnails server-side into the WordPress uploads directory.
* Serves thumbnails locally from `/wp-content/uploads/privacy-lite-youtube-embeds/`.
* Falls back to a local CSS placeholder when no thumbnail can be downloaded.
* Loads YouTube only after click.
* Uses `youtube-nocookie.com` for the iframe player.
* Optional privacy/consent text in the placeholder.
* Optional autoplay after click.
* Accessible button markup with keyboard support.
* Admin tool to scan content and generate missing thumbnails.
* Admin tool to clear the local thumbnail cache.
* Privacy policy helper text for the WordPress privacy tools.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/privacy-lite-youtube-embeds/`, or install it from the WordPress plugin screen when available.
2. Activate **Privacy Lite YouTube Embeds**.
3. Go to **Settings > Privacy Lite YouTube**.
4. Choose the replacement scope and placeholder options.
5. For existing sites, run **Scan content and generate missing thumbnails** from the settings page.

== Frequently Asked Questions ==

= Does the visitor browser load YouTube before click? =

The plugin is designed so that the frontend placeholder uses local HTML, CSS, JavaScript and locally cached thumbnails. The YouTube iframe is created only after the visitor clicks the placeholder.

= Are YouTube thumbnails loaded from YouTube in the frontend? =

No. The plugin downloads thumbnails server-side and saves them locally in the WordPress uploads directory. The frontend uses the local copy.

= Does the server contact YouTube? =

Yes, the server may contact `img.youtube.com` to download and cache thumbnails locally. This is done server-side, not in the visitor browser.

= Does this guarantee GDPR compliance? =

No plugin can guarantee legal compliance on its own. This plugin avoids loading YouTube resources in the visitor browser before interaction, but site owners should still review their privacy notice, cookie/consent setup, and legal obligations.

= Which YouTube embed domain is used after click? =

After click, the plugin creates an iframe using `https://www.youtube-nocookie.com/embed/VIDEO_ID`.

= Can I replace only Gutenberg YouTube blocks? =

Yes. The settings page includes a replacement scope option:

* Only Gutenberg YouTube embed blocks
* All YouTube videos found in content

= What happens if a thumbnail cannot be downloaded? =

The plugin displays a local CSS fallback placeholder. Failed thumbnail download attempts are cached for 12 hours to avoid repeated server-side requests.

= How do I test the privacy behavior? =

Open a page containing a YouTube embed and use your browser DevTools.

1. Open DevTools > Network.
2. Reload the page.
3. Before clicking the placeholder, check that there are no requests to `youtube.com`, `youtube-nocookie.com`, `ytimg.com`, `googlevideo.com`, `google.com`, or `gstatic.com`.
4. Click the placeholder.
5. Confirm that the iframe is created and loads from `youtube-nocookie.com`.

== Screenshots ==

1. Plugin settings page with privacy behavior notes, replacement options and thumbnail tools.
2. Frontend YouTube placeholder before click, using a local thumbnail and play button.

== Changelog ==

= 1.0.0 =
* Initial public release candidate.
* Local YouTube placeholders.
* Local thumbnail caching.
* Gutenberg block replacement.
* Optional replacement of classic oEmbeds and manual iframes.
* Admin thumbnail tools.
* Privacy policy helper text.

== Upgrade Notice ==

= 1.0.0 =
Initial public release candidate.
