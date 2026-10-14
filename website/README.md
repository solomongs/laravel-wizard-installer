# Laravel Wizard documentation website

Static documentation and a safe interactive demonstration, developed by **Solomon Gbeta**. Serve this directory over HTTP(S); no build, Node.js on the host, CDN, tracking, or external font is needed.

The site is intended for `https://solomongs.github.io/laravel-wizard-installer/`. Publication is performed by the repository's GitHub Pages workflow. A source upload alone does not prove that Pages deployment succeeded; check the workflow and visit the published URL.

## Edit content

Open `editor.html` (linked as **Edit site content** in the footer). The local editor provides labeled fields for the core headings, introductions, action labels, and FAQ entries. It loads the current `content.json` and exports a replacement file. Nothing is published or sent to GitHub by the editor.

1. Edit the copy and download `content.json`.
2. Replace `website/content.json` in this repository through GitHub's file editor or a local checkout.
3. Commit to the publishing branch. The Pages deployment workflow publishes the update.

Publishing uses your existing GitHub account and repository permissions. The editor does not ask for login information or access tokens. The website applies JSON values as plain text, never as HTML. Headings can contain line breaks. Keep setup codes, database passwords, and private application details out of public copy.

For technical guide changes, edit `index.html`; for interactive behavior, edit `app.js`; for design changes, edit `styles.css`. If `content.json` is missing or invalid, the complete default documentation remains in the HTML. Direct `file://` preview can use the default copy; use a local HTTP server or the published Pages site for JSON content loading and the editor.

## Demo boundaries

The six-stage demonstration uses fictional settings. It does not accept setup tokens, passwords, or real database credentials, and it never runs a migration or alters a hosting account. It includes a failed-check/retry illustration, reset behavior, and simulated sequential migration progress. Use your own project's prepared `install.php` for an actual installation.

The production package requires PHP 8.1+, while each Laravel project's actual PHP requirements govern hosting compatibility. Version 1 uses fresh MySQL/MariaDB databases. See the root README and `docs/` for authoritative package integration and deployment instructions.
