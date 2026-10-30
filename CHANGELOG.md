# Changelog

All notable changes to **Qevora Layouts** are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Dark Bar Header settings now match real sites: the colour picker is replaced by a logo image field with a media-library picker (the site initial tile remains as the fallback), and a new menu chooser decides which WordPress menu the bar displays - any menu from Appearance → Menus, defaulting to the menu created at import and falling back to it if the chosen menu is deleted. The mobile bar was tightened with a smaller-phone breakpoint.
- Template Library demo card now spans the full page width with the demo previewed at its natural size (no scaled strip), and the import options sit in a bordered panel with one clean line per control - label, selects, checkboxes and the import button aligned row by row.
- Editor and library polish from site feedback: the metabox and dialog previews now render the demo at its natural size (no scaled-down whitespace), the library lists demos as a responsive grid of modern cards with a slim live stage so several demos fit side by side, the media-library picker prints its script inline in the metabox so it works even when admin JS is filtered, the mobile toggle moved next to the call-to-action at the end of the bar and its click handler ships inline with the markup (the demo no longer enqueues a script), and the placement select - which demo layouts never use - is removed from their edit screen.
- Theme-reset proofing for the dark bar: toggle, menu and button rules now use doubled selectors so a theme reset stylesheet (which often styles bare `button` or `[type=button]`, as on the demo site) can no longer reveal the mobile toggle on desktop or repaint it; on phones the menu opens in normal flow inside the wrapped bar instead of an absolute dropdown, so no ancestor `overflow` can clip it - the mobile menu now reliably opens and closes.
- Template Library page rebuilt as simple stacked blocks (stage on top, details and import form below) so the layout cannot collapse in any admin theme; the earlier flex/grid card CSS was removed.
- All Layouts list table: every demo layout row now carries a small live preview rendered from its PHP file with its stored settings, in a new Preview column next to the layout name; admin assets are versioned by file modification time so browsers never keep a stale stylesheet.
- Template Library redesign: the demo card is now a horizontal panel - the demo renders live, centred on a soft dotted stage on the left while the name, description and import form sit on the right - and every live preview (library card, preview dialog, edit-screen metabox) centres the demo on its canvas at its own scale instead of pinning it to a corner. The zoom control became a chip on the stage, the JSON empty state a compact strip, and the layout stacks cleanly under 900px.
- WordPress Playground: `blueprint.json` boots the plugin on playground.wordpress.net (install, login, land on the Template Library), and the plugin is verified on the same PHP-WASM + SQLite runtime locally.
- One focused demo, live in the editor: the seven earlier demos and all of their settings fields are removed, replaced by a single `header-dark-bar` demo (`templates/demos/header-dark-bar.php`) - a floating dark bar with a coloured logo tile, a centred uppercase dot-separated menu and a white call-to-action button. Its metabox exposes only the four fields it renders (`accent_color`, `button_label`, `button_url`, `show_button`) and now shows the demo **live**: the preview renders through the same closure as the front end with the saved settings, and the tile colour and button text update as you type. Library cards and the preview dialog keep their live previews; unused preview artwork was removed. Verified on WordPress 7.1.4: import, menu creation, front-end render, settings round-trip, metabox live preview and library previews.

### Added

- Single-file demos with live previews: every bundled demo is now exactly one self-contained PHP file (`templates/demos/<slug>.php`) returning its manifest, settings schema, scoped CSS, vanilla JS and render closure - seven demos in seven files, nothing shared. The Template Library renders each demo live inside its card and in the preview dialog through the same render closure the front end uses, so the demo a user selects is exactly the markup the site displays; demo assets print inline under per-demo handles from the same file.

### Added

- Per-demo settings and a settings metabox: every bundled demo now keeps its customisable fields in its own `settings.php` schema (`templates/demos/<slug>/settings.php`), rendered as a metabox on the layout edit screen (texts, textareas, URLs, checkboxes, selects, colours, numbers) and saved schema-sanitised in `_qevora_layout_demo_settings`. Demo layouts no longer open in the block editor at all - their markup, styles and scripts live in plugin PHP files, the edit screen explains this and hides the empty content editor, and saved settings change the front end on the next request. Three new section demos join the four headers/footers (`section-hero`, `section-cta`, `section-faq`), so the bundled set covers headers, footers and reusable sections without any editor content. Verified on WordPress 7.1.4: metabox renders and saves sanitised settings, the rendered markup follows them, and section demos render through the same adapter.

### Removed

- The eight bundled block-editor starter templates and the one-click demo packs: bundled demos are now exclusively PHP-rendered (see Added). Sites can still register their own `.json` templates with the `qevora_layouts_bundled_template_files` filter, and uploads plus the optional remote library are unchanged; the `demo_pack` REST argument and the `qevora_layouts_demo_packs` filter are gone.

### Added

- Bundled PHP demos (`templates/demos/`): four responsive headers/footers rendered by the plugin itself through a new `demo` builder adapter - plain server-side HTML, a separate stylesheet and optional vanilla JavaScript per demo, all scoped and enqueued only while that demo renders. Demos with menu support create a real WordPress nav menu at import (home link plus up to five published pages, unique names, `edit_theme_options` guarded) and store it in `_qevora_layout_menu_id`; the library screen gets a *Ready-made demos* section (Display on, create-menu, hide-theme-part and status controls per card) and the REST import route accepts `demo` and `create_menu`. Demo files are resolved through a strict allow list, so only plugin-shipped templates can be included. New meta keys `_qevora_layout_demo` and `_qevora_layout_menu_id`; new filters `qevora_layouts_demos` and `qevora_layouts_demo_markup`. Verified on WordPress 7.1.4 with Twenty Twenty-Five: import created the menu with four real items, the front end rendered the demo markup with its own CSS/JS, and the theme header/footer stayed suppressed via the hide flag.
- The REST import route no longer requires `template_id`, so demo and pack imports work over REST too (they previously failed with 400); import `WP_Error`s now carry proper HTTP statuses (404 unknown demo, 400 for disabled/invalid requests).
- Header and footer layouts can replace the theme's own header/footer: a per-layout *Hide the theme header/footer* flag (`_qevora_layout_hide_theme_part` meta, editor checkbox, `hide_theme_part` REST/import parameter). The demo pack form, every header/footer template card and the upload form offer it checked by default, so an imported demo replaces the theme parts instead of showing two of each. Block themes drop the matching `core/template-part` block via the `render_block` filter; classic themes get sanitized inline CSS from a conservative default selector list (filterable with `qevora_layouts_theme_part_selectors`), merged with the existing site-wide settings selectors. Theme files are never modified and unchecking restores the theme part immediately. Verified on WordPress 7.1.4 with Twenty Twenty-Five.

### Fixed

- A display scope chosen on a single-template import (library card form, admin AJAX or REST) was silently ignored and the template's own conditions were kept - importing a footer with *Entire site* could still land on *Single posts and pages*. Scope resolution now happens in `Importer::import_template()`, so every import path honours it (regression-tested and verified on a real WordPress 7.1.4 install).
- The `posts_page` condition now also matches a blog shown at the site root (no static front page), so *Blog and archive listings* covers the most common blog setup.

### Changed

- Placement, condition and display-scope options use plain language for non-developers: hook names like `get_footer` or `wp_body_open` no longer appear in any label (*Above the site footer - best for most themes*, *At the top of the page, before the theme header - best for most themes*, …). Stored values are unchanged.
- *All singular content* is now *Single posts and pages* and spells out that listings are not included; *Posts page (blog)* is now *Blog listing (latest posts)*.
- Import confirmations state where each imported layout will display (*Displays on: Entire site*).

## [1.0.0] - 2026-10-30

First public release.

### Added

#### Layouts

- `qevora_layout` custom post type with twelve prefixed, `register_meta()`-sanitized meta keys: type, priority, conditions, placement, hook, hook priority, builder, content source, reference ID, template ID, import source and imported-at timestamp.
- Three layout types out of the box — `header`, `footer` and `section` — with `qevora_layouts_types` for adding more (archive, single, search or 404 templates need no rewrite).
- WordPress-native statuses: only published layouts are render candidates; drafts, pending and private layouts are visible to editors but never printed.
- One-click **Clone** row action that copies content and every setting into a new draft, deliberately without import provenance.
- **Enable / Disable** row actions that switch the post status instead of deleting anything.
- List table columns for type, condition summary, priority, placement, builder and source, sortable by type and priority, with `display_post_states` markers.

#### Condition engine

- Seventeen condition types resolved exclusively from WordPress query and user APIs: `entire_site`, `front_page`, `posts_page`, `singular`, `archive`, `search`, `404`, `post_type`, `post_type_archive`, `post`, `page`, `taxonomy`, `term`, `author`, `logged_in`, `logged_out`, `user_role`.
- Include/exclude semantics: an empty include list matches the whole site and any matching exclusion removes the layout.
- Documented precedence — priority (0–999) descending, then condition specificity (0–5) descending, then layout ID ascending — implemented in `Layout_Repository::find_matching()`.
- Per-type value handling (post type slugs, post/page/author IDs, taxonomies, term IDs with children, role slugs) with sanitizing on save and on read.
- `qevora_layouts_conditions`, `qevora_layouts_condition_matches`, `qevora_layouts_context` and `qevora_layouts_matching_layouts` filters.

#### Rendering

- Placements: `wp_body_open`, `get_header`, `get_footer`, `wp_footer`, `custom_hook` and `manual`, with a block list that refuses core lifecycle hooks and a strict hook-name pattern.
- Block theme detection (`wp_is_block_theme()`) so placements still work when `get_header`/`get_footer` never fire.
- `Duplicate_Guard`: one render per slot per request, with `qevora_layouts_should_render` and `qevora_layouts_slot_rendered`.
- A render plan resolved once per request at `template_redirect` priority 5 and memoized.
- Overridable wrapper template at `<theme>/qevora-layouts/layout-wrapper.php`, plus `qevora_layouts_wrapper_classes` and `qevora_layouts_wrapper_attributes`.
- Opt-in hiding of the theme header/footer through administrator-supplied CSS selectors, printed as one `<style>` element in `wp_head` after two independent sanitization passes.
- Frontend stylesheet and script loaded only when a layout actually renders.

#### Builders

- `Builder_Adapter_Interface`, `Abstract_Builder_Adapter` and `Builder_Manager` with resolution by hint, capability and fallback, and `qevora_layouts_builder_adapters`.
- Block editor adapter rendering `post_content` through `apply_filters( 'the_content', … )` with the global `$post` set up and restored, so dynamic blocks work.
- Elementor adapter using documented APIs only: `add_post_type_support()`, `Plugin::$instance->documents->get()->is_built_with_elementor()`, `Plugin::$instance->frontend->get_builder_content_for_display()` and `Frontend::enqueue_styles()`; every call is guarded and falls back to core rendering.
- Reference mode (`content_source = reference`) that renders another post's content without copying or rewriting builder data.
- Builder status matrix in System Status, plus an admin notice when a requested builder is missing.

#### Template library and imports

- Eight bundled starter templates (three headers, two footers, three sections) using core blocks only, each with description, tags, priority, placement, conditions, an original SVG preview, `requires`, author and license.
- `qevora_layouts_bundled_template_files` so a site can add its own starter files without touching the plugin directory.
- `.json` upload support with `is_uploaded_file()` verification, `UPLOAD_ERR_*` messages, extension and size checks — deliberately not `wp_handle_upload()`, because WordPress has no JSON MIME type.
- Optional https remote library: 15 second timeout, two redirects, no cookies, 8 MB response cap, 12 hour index cache, lazy per-template fetch restricted to the configured host.
- `Template_Validator`: schema check, name/type/builder validation, content size caps, forbidden-pattern scanning (PHP tags, scripts, `javascript:`, `data:text/html`, iframes, object/embed, inline event handlers), KSES for users without `unfiltered_html`, preview path confinement, tag and requirement cleaning, unknown-field warnings, 50 templates per file and duplicate-ID rejection.
- Duplicate policies `skip` (default) and `copy`, plus explicit `replace_id` overwriting behind a capability check.
- Per-template results (`created`, `copied`, `updated`, `skipped`, `failed`) with messages, affected IDs and edit links.

#### Admin, REST and front-end API

- Top-level **Qevora Layouts** menu with All Layouts, Add New, Template Library, Settings and System Status, filtered by `qevora_layouts_admin_submenus`.
- Meta boxes for layout settings, display conditions (with an accessible post/term/user picker) and placement, saved behind nonces and capabilities.
- AJAX endpoints for search, import and cache flushing; REST namespace `qevora-layouts/v1` with `/layouts`, `/layouts/{id}`, `/templates`, `/templates/import`, `/search` and `/system-status`.
- `[qevora_layout]` shortcode, `qevora/layout` block (API version 3, server-side render callback) and template tags `qevora_layouts_the_header()`, `qevora_layouts_the_footer()`, `qevora_layouts_get_layout()`, `qevora_layouts_the_layout()` and `qevora_layouts_has_layout()`.
- Admin notices that are stored per user, expire after five minutes and escape their details.

#### Settings, status, security and performance

- One option (`qevora_layouts_settings`) with a registered sanitize callback covering rendering, builder, import, cache, debug and uninstall behaviour.
- System Status screen with environment, theme, builder, layout-count and cache reporting, copyable as text, restricted to `manage_options`.
- Debug mode (off by default) with an in-memory log buffer.
- Capabilities (`edit_qevora_layouts`, `publish_qevora_layouts`, `delete_qevora_layouts`, `manage_options`) plus per-object checks in the repository.
- Nonces on every state change, sanitizing on input, escaping on output, no raw SQL, direct-access guards in every file and an `index.php` in every directory.
- Versioned candidate cache with strictly increasing versions and invalidation on every write, plus per-request memoization.
- Requirement checks that keep the plugin inactive with an explanatory notice on PHP < 7.4 or WordPress < 6.4 instead of fataling.
- `uninstall.php` that does nothing unless "Delete all plugin data on uninstall" was enabled, then removes only plugin-owned posts, meta, transients and options, in batches and per site on multisite.

#### Internationalization, accessibility, documentation and tests

- 529 translatable strings collected in `languages/qevora-layouts.pot`, generated by `tools/make-pot.py`.
- Keyboard-operable condition builder with `role="listbox"`, `aria-activedescendant`, arrow-key navigation and `aria-live` announcements; labels, described-by associations and focus management on the import screen.
- `README.md` (features, compatibility matrix, hook reference, security model, limitations), `readme.txt` (WordPress.org format) and this changelog.
- A dependency-free test runner (`php tests/run.php`) with 181 tests and 1791 assertions across conditions, core, imports, frontend and an end-to-end starter template suite, plus a manual integration checklist in `tests/INTEGRATION.md`.

### Known limitations at release

- Built and verified without a live WordPress/MySQL runtime: syntax checked, unit and integration tested against WordPress stubs, and every WordPress and Elementor API confirmed against the WordPress 7.1.4 and Elementor 4.5.0 sources. The `tests/INTEGRATION.md` checklist still has to be run on a real install, and the Elementor adapter has not been executed against a live Elementor.
- Elementor template JSON exports cannot be imported by design; the validator returns the supported workaround instead.
- Block theme template parts are not replaced or registered; layouts render through `wp_body_open`/`wp_footer`.
- `Cache::remember()` treats `null` as a miss, so a "no layout matched" result is recomputed rather than cached.

[Unreleased]: https://github.com/qevorasoftware/qevora-layouts/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/qevorasoftware/qevora-layouts/releases/tag/v1.0.0
