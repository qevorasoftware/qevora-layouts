# Manual integration checklist

`php tests/run.php` covers everything that can be tested without a database, a
theme, a browser or a REST server: the condition engine, the repository, the
cache, the settings, the validator, the library, the importer, the duplicate
guard and the inline CSS validator (181 tests, 1791 assertions).

Everything below needs a **real WordPress install**. Run it before treating a
release as verified, and record the result next to each item. Nothing in this
file is automated: if a box is unchecked, that behaviour has not been verified.

## Environment

Prepare two installs, both with `WP_DEBUG`, `WP_DEBUG_LOG` and
`WP_DEBUG_DISPLAY` enabled and an empty `wp-content/debug.log`:

| Install | Theme | Builders | Purpose |
| --- | --- | --- | --- |
| A | Twenty Twenty-Four (block theme) | none | Core-only behaviour, block themes |
| B | Twenty Twenty-One (classic theme) | Elementor (latest) | Classic hooks, builder adapter |

WordPress 6.4 minimum and the current release; PHP 7.4 and the current supported
PHP version. Check both PHP versions if you can.

## 1. Activation and requirements

- [ ] Activating on a supported site shows no PHP notice, warning or fatal in `debug.log`.
- [ ] **Qevora Layouts** appears in the admin menu with the `dashicons-layout` icon and five submenus: All Layouts, Add New, Template Library, Settings, System Status.
- [ ] Capabilities `edit_qevora_layouts`, `publish_qevora_layouts` and `delete_qevora_layouts` exist after activation (check with a role editor or `get_role( 'administrator' )->capabilities`).
- [ ] On a site with PHP < 7.4 the plugin stays inactive and prints an admin notice naming the required version, without a fatal error.
- [ ] On a site with WordPress < 6.4 the same happens for the WordPress version.
- [ ] Deactivating and reactivating keeps all layouts and settings.
- [ ] No plugin CSS or JS is enqueued on a non-plugin admin screen (inspect the source of Posts → All Posts) and none on the front end when no layout renders.

## 2. Creating and rendering a header (install A, block theme)

1. **Qevora Layouts → Template Library →** import *Simple Header*.
2. Visit the front page.

- [ ] The header markup appears directly after `<body>` (inside `wp_body_open`), wrapped in `<div class="qevora-layouts qevora-layouts--header qevora-layouts--id-N">`.
- [ ] It appears exactly **once** in the HTML source.
- [ ] The theme's own header still renders (nothing was hidden).
- [ ] `qevora-layouts/frontend.css` is loaded, and only on pages where a layout renders.
- [ ] No new entry in `debug.log`.

Then create a layout by hand (**Add New**, write a group block, placement `wp_body_open`, publish) and confirm it renders as well.

## 3. Creating and rendering a footer (install B, classic theme)

- [ ] A footer layout with placement `get_footer` renders before the theme footer markup.
- [ ] Placement `wp_footer` renders inside the `wp_footer` output.
- [ ] Placement `wp_body_open` renders at the top of the body on the same site.
- [ ] Placement `custom_hook` with a hook your theme fires (for example `twentytwentyone_footer_start` if available, otherwise add one in a child theme) renders there, at the configured priority.
- [ ] `custom_hook` refuses core lifecycle hooks: saving `init`, `wp_head`, `wp_footer`, `template_redirect` or `shutdown` stores an empty hook name.
- [ ] Placement `manual` renders nothing automatically but works through `[qevora_layout id="N"]`, the block and `qevora_layouts_the_layout( N )`.

## 4. Duplicate guard

- [ ] In a child theme, call `wp_body_open()` twice in one template: the header renders once.
- [ ] Two published headers that both match the front page: only one renders (the higher priority).
- [ ] `add_filter( 'qevora_layouts_should_render', '__return_false' )` stops the render completely.
- [ ] `qevora_layouts_slot_rendered` fires once per rendered slot (log it from a test plugin).

## 5. Hiding the theme header (consent)

- [ ] With `hide_theme_header_selectors` empty and no layout flagged to replace the theme part, no `<style id="qevora-layouts-hide-theme">` is printed at all.
- [ ] A header/footer layout with *Hide the theme header/footer* checked removes the theme's own part: template part block dropped on block themes, `<style id="qevora-layouts-hide-theme">` with the default selectors on classic themes.
- [ ] After entering `.site-header`, exactly that element gets `display:none !important` and nothing else changes.
- [ ] Entering `.site-header } body { display: block` saves as empty (the token is dropped) and prints nothing.
- [ ] Entering `</style><script>alert(1)</script>` prints nothing and stores nothing executable.
- [ ] Turning `inline_hide_css` off removes the `<style>` element while keeping the setting.

## 6. Conditions

Verify each row by loading a matching and a non-matching URL.

| Condition | Expected to render on | Expected NOT to render on |
| --- | --- | --- |
| entire site | every front-end page | admin screens, REST requests |
| front page | the front page | the blog page, a single post |
| posts page | the blog index | the front page (when different) |
| singular | one post, one page | archives, search, 404 |
| archive | category/tag/author/date archives | singular views |
| search | `?s=term` results | any other view |
| 404 | a URL that does not exist | every existing view |
| post type = `page` | any page | posts, archives |
| post type archive = `post` | the blog index | a single post |
| post = ID | that post only | every other post |
| page = ID | that page only | every other page |
| taxonomy = `category` | category archives | tag archives, singular |
| term = ID | that term archive, and its children | other terms |
| author = ID | that author's archive and their posts | other authors |
| logged in | a logged-in browser | a logged-out browser |
| logged out | a logged-out browser | a logged-in browser |
| user role | a user with that role | a user without it |

- [ ] Every row behaves as described.
- [ ] An include list with two rules renders when **either** matches.
- [ ] An exclude rule removes the layout even when an include rule matches.
- [ ] Precedence: with priorities 30 (exclude post type = post), 20 (include front page) and 10 (entire site), the front page shows the priority-30 layout, a single post shows the priority-10 layout and a 404 shows the priority-30 layout.
- [ ] Equal priority resolves by specificity: a `page` rule beats a `post_type` rule beats `entire_site`.
- [ ] Draft layouts never render; a logged-in editor still does not see them unless `qevora_layouts_allow_disabled_layout` allows it.
- [ ] The condition picker finds posts, terms and users by search, is reachable with the keyboard (Tab into the field, arrow keys through the listbox, Enter to select, Escape to close) and announces selections to screen readers.

## 7. Builders (install B)

- [ ] With Elementor active, a layout shows **Edit with Elementor** and can be built there.
- [ ] An Elementor-built header renders on the front end with Elementor's styles applied (inspect that `elementor-frontend.css` is loaded on a page that is not itself built with Elementor).
- [ ] With Elementor deactivated afterwards, the same layout falls back to core rendering and the front end still works, with a warning in the debug log when debug mode is on and no fatal error.
- [ ] A layout with `builder = elementor` but plain block content renders through the block adapter.
- [ ] Content source `reference` renders the referenced post's content, and changing the referenced post changes the output.
- [ ] System Status lists Elementor with its version and shows green requirement checks.
- [ ] No Elementor meta (`_elementor_data`, `_elementor_version`, `_elementor_edit_mode`) was written by the plugin on a layout that was never edited in Elementor.

## 8. Template library and imports

- [ ] The library lists all seven bundled PHP demos with name, type, description, tags and their import controls (Display on, create menu, hide theme parts, status).
- [ ] Importing *Hero Section* creates a `section` layout in `manual` placement that renders through the shortcode but not automatically.
- [ ] Importing the same demo again reports **"already exists"** with a link to the existing layout and creates nothing.
- [ ] With the duplicate policy set to *copy*, importing again creates a second layout and says so.
- [ ] Choosing an existing layout to replace overwrites exactly that layout and no other.
- [ ] Uploading a valid `.json` file (single template and `{ "templates": [ … ] }` collection) imports it.
- [ ] Uploading a `.txt`, `.php` or `.json.php` file is refused with a clear message.
- [ ] Uploading a file with `<?php` anywhere in it is refused before parsing.
- [ ] Uploading a file whose content contains `<script>`, `onclick=`, `javascript:` or an `<iframe>` is refused and names the reason.
- [ ] Uploading `"builder": "elementor"` is refused with the instruction to use Elementor's own importer plus reference mode.
- [ ] Uploading a file larger than the configured limit is refused with the limit in the message.
- [ ] Uploading a file with duplicate template IDs reports the duplicate.
- [ ] A file with unknown fields imports and lists those fields as warnings.
- [ ] With `allow_uploaded_imports` off, the upload control is gone and a forged upload request is refused.
- [ ] As a user without `publish_qevora_layouts` (Author or below), the library screen is inaccessible and a forged import request returns 403.

## 9. Remote library

Use a local https endpoint you control (for example a static file served by `wp-env`, a tunnel, or a small script on your own domain).

- [ ] With the remote library disabled, no request leaves the site (verify with a request log).
- [ ] After enabling it with an https endpoint returning `{ "templates": [ … ] }`, those templates appear in the library marked as remote.
- [ ] An endpoint returning invalid JSON shows an actionable error and the bundled library still works.
- [ ] An endpoint returning HTTP 500 shows the status code in the message.
- [ ] An entry with its own `url` and no content is fetched only when that single template is imported.
- [ ] An entry whose `url` points at a **different host** is refused and that host is never requested.
- [ ] An `http://` (non-TLS) endpoint cannot be saved: the setting stores an empty string.
- [ ] A response larger than 8 MB is refused.
- [ ] *Refresh* clears the cached index and re-fetches.
- [ ] No cookies are sent with the request (check the server log).

## 10. REST API

With an application password or a logged-in administrator (`X-WP-Nonce` from `wpApiSettings.nonce`):

- [ ] `GET /wp-json/qevora-layouts/v1/layouts` returns layouts and an `X-WP-Total` header.
- [ ] `GET /wp-json/qevora-layouts/v1/layouts/{id}` returns one layout with conditions and provenance.
- [ ] `GET /wp-json/qevora-layouts/v1/templates` lists library entries **without** content.
- [ ] `POST /wp-json/qevora-layouts/v1/templates/import` with `{ "template_id": "qevora-header-simple" }` creates a layout.
- [ ] `GET /wp-json/qevora-layouts/v1/search?kind=posts&search=hello` returns matching posts.
- [ ] `GET /wp-json/qevora-layouts/v1/system-status` returns the report for an administrator.
- [ ] Every route returns 401/403 for a logged-out request or a Subscriber.
- [ ] `system-status` returns 403 for an Editor (it requires `manage_options`).
- [ ] A POST without a valid nonce is rejected.
- [ ] No response contains an absolute server path, a token or a debug trace.

## 11. Capabilities

| Role | Expected |
| --- | --- |
| Administrator | Everything. |
| Editor | Create, edit, publish, clone, enable/disable, import. No Settings, no System Status. |
| Author | Sees the menu only if granted `edit_qevora_layouts`; by default no access. |
| Subscriber | No menu, no REST access, no layouts rendered that are not published. |

- [ ] Each row holds, including direct URL access to `edit.php?post_type=qevora_layout`, `admin.php?page=qevora-layouts-settings` and `admin.php?page=qevora-layouts-status`.
- [ ] Row actions (`enable`, `disable`, `clone`) reject a request with a missing or reused nonce.
- [ ] The meta box rejects a save with a tampered nonce and leaves the layout unchanged.

## 12. Content safety

- [ ] As an Editor (no `unfiltered_html`), saving `<script>alert(1)</script>` inside a layout strips the script.
- [ ] As an Administrator, saving `<?php echo 1; ?>` stores content **without** the PHP tags (they are stripped for every role) and the front end never executes them.
- [ ] Block comments (`<!-- wp:group -->`) survive saving and rendering intact.
- [ ] A layout title containing `<img src=x onerror=alert(1)>` is escaped in the list table, the editor and the REST response.

## 13. Caching

- [ ] With a persistent object cache (Redis or Memcached) enabled, the second front-end request issues no `WP_Query` for candidates (verify with Query Monitor).
- [ ] Saving, trashing, untrashing, duplicating, enabling, disabling a layout or changing a layout's meta invalidates the cache: the next request reflects the change immediately.
- [ ] Importing several demos in one request shows all of them on the next request (the version must increase per save, even within the same second).
- [ ] Changing a setting invalidates the cache.
- [ ] With `cache_candidates` off, every request queries and the results are still correct.
- [ ] "Clear caches" in System Status bumps the version and reports the new value.

## 14. Internationalization and accessibility

- [ ] Switching the site language to one with a translation file (add a small `qevora-layouts-de_DE.mo` for testing) translates the menu, buttons and messages.
- [ ] `load_plugin_textdomain()` runs and no "missing translation domain" notice appears.
- [ ] Every control on the Settings, Library and meta box screens is reachable and operable with the keyboard alone.
- [ ] Focus order on the import flow ends on the result region, and results are announced (`aria-live`).
- [ ] Colour is not the only indicator of status (badges carry text).
- [ ] The condition picker listbox exposes `aria-expanded`, `aria-activedescendant` and `role="option"`.

## 15. Uninstall

- [ ] With `delete_data_on_uninstall` **off**: deleting the plugin leaves every `qevora_layout` post, the settings option and all meta in place; reinstalling shows them again.
- [ ] With `delete_data_on_uninstall` **on**: deleting the plugin removes all layouts (including revisions), all twelve meta keys, the settings and cache-version options, and the per-user notice transients.
- [ ] It leaves posts, options, meta and tables of other plugins and of WordPress untouched (compare row counts before and after).
- [ ] On multisite, the same holds per site, and a network delete runs across all sites.
- [ ] `uninstall.php` cannot be requested directly (it exits without `WP_UNINSTALL_PLUGIN`).

## 16. Performance and reliability

- [ ] A front-end request with one header and one footer adds no more than one extra cached lookup plus one content render (Query Monitor).
- [ ] No PHP notice, warning or deprecated message appears in `debug.log` on any screen, on PHP 7.4 and on the current PHP 8.x.
- [ ] The plugin works with a page cache (object cache + full-page cache) without showing stale headers after a change.
- [ ] Loading 300+ layouts does not slow the list table or the front end beyond the candidate cap (`MAX_CANDIDATES = 300`).

## Already verified against a real WordPress for 1.0.0

These steps were executed on a real WordPress 7.1.4 install (SQLite database drop-in, PHP 8.5, CLI requests) and pass:

- Activation through `activate_plugin()`, exactly as `wp-admin/plugins.php` runs it.
- All six REST routes: 200 for an administrator, 401 anonymously, 400 with a helpful message for a missing `template_id`.
- Starter import (`created`), a repeat import (`skipped` by the duplicate policy) and `on_duplicate=replace`.
- Front page of a block theme: header and footer wrappers render with real block content.
- Template Library, Settings and System Status screens render their complete admin pages.

Everything below that needs a browser, a classic theme with Elementor, multisite or a page cache is still manual.

## Reporting

Attach to any bug report:

1. The **System Status** report copied as text.
2. The WordPress, PHP and theme versions, plus active builder versions.
3. The relevant lines of `wp-content/debug.log`.
4. The exact steps and the layout's conditions, priority and placement.
