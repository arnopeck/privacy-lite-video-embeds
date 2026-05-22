# Changelog

## 0.1.1

- Added thumbnail pre-generation on post save.
- Added transient cache for failed thumbnail download attempts.
- Switched thumbnail requests to `wp_safe_remote_get()`.
- Added a Settings link in the WordPress plugins screen.
- Updated README documentation.

## 0.1.0

- Initial plugin implementation.
- Replaces Gutenberg YouTube embeds with local placeholders.
- Optional support for classic oEmbeds and manually pasted YouTube iframes.
- Server-side thumbnail download and local thumbnail serving.
- CSS fallback placeholder when no thumbnail is available.
- Frontend JS loads `youtube-nocookie.com` only after click.
- Settings page with replacement scope, consent text and autoplay options.
