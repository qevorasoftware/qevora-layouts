# Qevora Layouts

Dynamic headers, footers and reusable layout templates for WordPress — with a real condition engine, a builder adapter layer and bundled PHP-rendered demos.

* **Version:** 1.0.0
* **Requires:** WordPress 6.4+, PHP 7.4+
* **Tested up to:** WordPress 7.1
* **License:** GPL-2.0-or-later (see [LICENSE](LICENSE))
* **Text domain:** `qevora-layouts`

No third-party page builder is required to activate or use this plugin. Everything works with the block editor out of the box; Elementor is supported when it is already installed.

---

## Table of contents

1. [What it does](#what-it-does)
2. [Installation](#installation)
3. [Quick start](#quick-start)
4. [Admin screens](#admin-screens)
5. [The condition engine](#the-condition-engine)
6. [Headers, footers and placements](#headers-footers-and-placements)
7. [Layout templates and the data model](#layout-templates-and-the-data-model)
8. [Builder support and compatibility matrix](#builder-support-and-compatibility-matrix)
9. [Template library and imports](#template-library-and-imports)
10. [Shortcode, block and template tags](#shortcode-block-and-template-tags)
11. [REST API](#rest-api)
12. [Settings](#settings)
13. [System Status](#system-status)
14. [Developer reference: hooks](#developer-reference-hooks)
15. [Security model](#security-model)
16. [Performance and caching](#performance-and-caching)
17. [Accessibility and translations](#accessibility-and-translations)
18. [Uninstall and your data](#uninstall-and-your-data)
19. [Testing](#testing)
20. [Known limitations](#known-limitations)
21. [Project layout](#project-layout)
22. [Changelog, attribution and license](#changelog-attribution-and-license)

---

## What it does

**Dynamic headers.** Create as many headers as you like, decide exactly where each one appears (whole site, front page, one post type, a single page, a taxonomy term, an author archive, search, 404, logged-in users, user roles…), give each one a priority, and switch it on or off without deleting it. Your theme's own header stays untouched unless you explicitly ask the plugin to hide it.

**Dynamic footers.** Same engine, same controls, independent slot. A header and a footer never interfere with each other, and neither renders twice — even if a theme fires its hooks more than once.

**Reusable layout templates.** Every layout is a normal WordPress post (`qevora_layout` custom post type) with prefixed meta. Layouts have real WordPress statuses (published, draft, pending, private), can be cloned in one click, and can be placed anywhere with a shortcode, a block, a hook or a PHP template tag. The data model is type-driven, so archive, single, search and 404 templates can be added later without a rewrite.

**Builder adapters.** Content is authored in the block editor (always available) or in Elementor (when installed). The plugin never edits another builder's internal data: it calls documented APIs only, and falls back to core rendering if a builder cannot render.

**Validated imports.** Seven bundled demos (two headers, two footers, three sections) are rendered by the plugin itself from PHP templates. Your own `.json` templates can still be uploaded or fetched from an optional https remote library: everything is validated against a schema before anything is created, imports never overwrite silently, and PHP is never executed from imported data.

## Installation

### From the release ZIP

1. Download `release/qevora-layouts-1.0.0.zip`.
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the ZIP and click **Install Now**.
3. Click **Activate**.

### By hand

1. Copy the `qevora-layouts/` folder into `wp-content/plugins/`.
2. Activate **Qevora Layouts** on the Plugins screen.

### From source

```bash
git clone https://github.com/qevorasoftware/qevora-layouts.git wp-content/plugins/qevora-layouts
```

The repository contains development files (`tests/`, `tools/`, `.distignore`). They are inert at runtime; use `tools/build-zip.sh` to produce a runtime-only ZIP.

Activation performs a capability check and a requirement check. If the site runs PHP older than 7.4 or WordPress older than 6.4, the plugin stays inactive and shows an admin notice explaining what to upgrade — it never fatals.

## Quick start

1. Go to **Qevora Layouts → Template Library**.
2. Import **Simple Header** and **Footer with Columns**. Both are block-editor templates and need nothing else installed.
3. Visit the front end. The header appears at the top of the body, the footer just before the theme's `wp_footer` output.
4. Want the theme's own header gone? **Qevora Layouts → Settings → Rendering → Hide the theme header**, enter your theme's selector (for example `.site-header`) and save. Nothing is hidden until you do this.
5. Create your own: **Qevora Layouts → Add New**, write the markup in the block editor, then use the **Display conditions** panel to choose where it applies and the **Placement** panel to choose where it renders.

## Admin screens

The plugin registers one top-level menu, **Qevora Layouts** (`dashicons-layout`), with these submenus:

| Menu | Slug | Capability | What it does |
| --- | --- | --- | --- |
| All Layouts | `edit.php?post_type=qevora_layout` | `edit_qevora_layouts` | List table with type, conditions, priority, placement, builder and source columns, plus Enable / Disable / Clone row actions. |
| Add New | `post-new.php?post_type=qevora_layout` | `publish_qevora_layouts` | Block editor with the layout meta boxes. |
| Template Library | `qevora-layouts-library` | `publish_qevora_layouts` | Bundled PHP demos, `.json` uploads and the optional remote library, with import feedback. |
| Settings | `qevora-layouts-settings` | `manage_options` | Every option below. |
| System Status | `qevora-layouts-status` | `manage_options` | Environment report, builder status, layout counts, cache state. |

The menu list is filterable (`qevora_layouts_admin_submenus`). Plugin CSS and JS load **only** on these screens and on the layout editor — never on other admin pages, never on the front end unless a layout actually renders.

Every state-changing screen action is a POST with a nonce: `qevora_layouts_meta_nonce`, `qevora_layouts_clone_{id}`, `qevora_layouts_enable_{id}`, `qevora_layouts_disable_{id}`, plus `qevora_layouts_admin` for AJAX and `wp_rest` for the REST API.

The three custom capabilities (`edit_qevora_layouts`, `publish_qevora_layouts`, `delete_qevora_layouts`) do not exist in a vanilla WordPress, so activation adds them to the **administrator** role and uninstall removes them from every role again. Add more roles (for example `editor`) with the `qevora_layouts_granted_roles` filter. An `admin_init` check re-grants them when they are missing - rebuilt roles or an interrupted activation can never lock administrators out. Settings and System Status stay behind the core `manage_options` capability.

## The condition engine

Conditions are resolved from **WordPress query and user APIs only** (`is_front_page()`, `is_singular()`, `get_queried_object()`, `is_user_logged_in()`, `wp_get_current_user()`…). There is no URL substring matching anywhere: a request for `/?p=12&preview=true` is understood as a post preview, not as a string.

### Structure

Every layout stores conditions as:

```json
{
  "include": [ { "type": "post_type", "value": "post" } ],
  "exclude": [ { "type": "user_role", "value": "administrator" } ]
}
```

* An **empty `include` list matches the entire site**.
* **Exclusions always win.** If any exclude rule matches, the layout does not apply, no matter what the include list says.
* If `include` is not empty, at least one include rule must match.

### Condition types

| Type | Value | Meaning | Specificity |
| --- | --- | --- | --- |
| `entire_site` | none | Every frontend request | 0 |
| `logged_in` | none | Any logged-in visitor | 1 |
| `logged_out` | none | Any logged-out visitor | 1 |
| `front_page` | none | The front page (static page or posts page) | 2 |
| `posts_page` | none | The blog posts index, including when it is the site root | 2 |
| `singular` | none | Any single post, page or attachment | 2 |
| `archive` | none | Any archive | 2 |
| `search` | none | Search results | 2 |
| `404` | none | Not found | 2 |
| `post_type` | post type slug | Any content of that post type | 3 |
| `post_type_archive` | post type slug | The archive of that post type | 3 |
| `taxonomy` | taxonomy slug | Any archive of that taxonomy | 3 |
| `term` | term IDs | Specific terms (includes children) | 4 |
| `author` | user IDs | Specific authors | 4 |
| `post` | post IDs | Specific posts | 5 |
| `page` | page IDs | Specific pages | 5 |
| `user_role` | role slugs | Visitors with one of these roles | 5 |

Types are extensible: `qevora_layouts_conditions` adds or changes definitions, `qevora_layouts_condition_matches` overrides a single evaluation.

### Precedence

When more than one published layout matches, the plugin picks exactly one per slot:

1. **Priority** — higher wins (0–999, default 10).
2. **Specificity** — on equal priority, the layout whose matching rules are more specific wins (`page` beats `post_type`, which beats `entire_site`).
3. **ID** — on equal priority and specificity, the older layout (lower ID) wins, so the result is deterministic.

`find_matching()` returns the whole ordered list; `get_active_layout()` returns the first entry. Both are filterable (`qevora_layouts_matching_layouts`).

### Worked example

Three headers are published:

| Layout | Priority | Conditions |
| --- | --- | --- |
| Announcement header | 30 | exclude: post type = post |
| Centered header | 20 | include: front page |
| Simple header | 10 | (none — entire site) |

* **Front page** → Announcement (30) wins; its exclusion does not apply because the front page is a page.
* **Single post** → Announcement excludes itself, Centered only wants the front page, so Simple renders.
* **404** → Announcement (30) again.

This exact scenario is asserted end-to-end in the demo import and render tests (`tests/Unit/Import/Demo_Importer_Test.php`, `tests/Unit/Frontend/Demo_Render_Test.php`).

## Headers, footers and placements

### Placement

| Type | Available placements | Default |
| --- | --- | --- |
| Header / Section | `wp_body_open`, `get_header`, `custom_hook`, `manual` | `wp_body_open` |
| Footer | `get_footer`, `wp_footer`, `wp_body_open`, `custom_hook`, `manual` | `get_footer` |

The admin UI never shows these internal slugs: every placement is offered with a plain-language label (for example *Above the site footer - best for most themes*), and the table above documents the stored values for developers.

* `custom_hook` prints through `add_action( $hook, …, $priority )`. Core lifecycle hooks (`init`, `plugins_loaded`, `wp_head`, `wp_footer`, `template_redirect`, `shutdown`, …) are on a block list, and hook names must match `^[a-zA-Z0-9_\-\/.]{3,80}$`.
* `manual` never renders automatically; use the shortcode, the block or a template tag.

### Block themes

Classic themes fire `get_header` and `get_footer`. Block themes render through `template-canvas.php`, where those actions never run but `wp_body_open()` and `wp_footer()` do. `Frontend::resolve_placement()` checks `wp_is_block_theme()` and maps a stored `get_header` placement to `wp_body_open` and a stored `get_footer` placement to `wp_footer` on block themes, so headers **and** footers appear on both theme families; classic themes keep the stored hook. Verified on a real WordPress 7.1.4 install with Twenty Twenty-Five.

### Duplicate guard

`Duplicate_Guard` allows **one render per slot per request**. A theme that calls `wp_body_open()` twice, a layout matching several hooks, or a plugin re-running the plan cannot produce two headers. Themes and other plugins can opt out of a slot with the `qevora_layouts_should_render` filter, and `qevora_layouts_slot_rendered` fires after each render.

### Hiding the theme header or footer

This is **always opt-in**. Settings → Rendering has two selector fields (`hide_theme_header_selectors`, `hide_theme_footer_selectors`) and an `inline_hide_css` switch. When enabled the plugin prints one `<style>` element in `wp_head`:

```html
<style id="qevora-layouts-hide-theme">.site-header{display:none !important;}</style>
```

Selectors are sanitized twice — once when saved (`Settings::sanitize_css_selectors()`: max 20 selectors, 200 characters each, no `{ } ; < / \ & @`, no `expression(`, `javascript:` or `behavior`) and again immediately before printing (`Frontend::sanitize_css_block()`: single declaration block, no `<`, no `url(`, no `@import`, no comments, max 4000 bytes). If anything looks wrong, nothing is printed. The theme's files and markup are never modified.

### Replacing the theme header and footer

An imported demo that stacks on top of the theme's own header or footer shows two of each. Every header/footer layout therefore carries a **Hide the theme header/footer while this layout is displayed** flag (`_qevora_layout_hide_theme_part`):

* Every demo card and the upload form offer the flag **checked by default** - importing a demo replaces the theme parts instead of doubling them. It is a visible, untickable-by-choice control: unchecking it (on the form or later in the layout editor) brings the theme header/footer straight back.
* Only the part a layout actually replaces is touched, only on requests where that layout renders, and only for `header`/`footer` types - `manual` layouts never suppress anything.
* **Block themes:** the matching `core/template-part` block (`slug` = `header`/`footer`) is dropped from the response through the core `render_block` filter, so the theme markup is genuinely gone, not just invisible.
* **Classic themes (and as a fallback):** the inline hide CSS above is printed with a conservative default selector list - `#masthead`, `#site-header`, `.site-header`, `header.wp-block-template-part` for the header; `#colophon`, `#site-footer`, `.site-footer`, `footer.wp-block-template-part` for the footer - merged with the optional site-wide selectors from Settings. The list is filterable (`qevora_layouts_theme_part_selectors`) and passes the same double sanitization as all hide CSS.
* The REST import route accepts `hide_theme_part` (boolean). Theme files and database entries of the theme are never modified.

Verified end-to-end on a real WordPress 7.1.4 install with Twenty Twenty-Five: with the flag on, the rendered blog home contains the plugin header/footer wrappers and zero theme template-part markup; with the flag off, the theme parts render untouched and no hide CSS is printed.

### Wrapper

Rendered layouts are wrapped in:

```html
<div class="qevora-layouts qevora-layouts--header qevora-layouts--id-42">…</div>
```

The wrapper can be turned off per site (`render_wrapper`) or per shortcode/block (`wrapper="false"`), classes and attributes are filterable (`qevora_layouts_wrapper_classes`, `qevora_layouts_wrapper_attributes`), and the markup itself can be replaced by copying `templates/frontend/layout-wrapper.php` into your theme at `<theme>/qevora-layouts/layout-wrapper.php`.

## Layout templates and the data model

Layouts live in the `qevora_layout` post type. It is `show_in_menu => false` (the plugin registers its own menu), `publicly_queryable => false` (layouts are not directly reachable URLs), `map_meta_cap => true`, exposed in REST as `qevora-layouts`, and supports `title`, `editor`, `revisions`, `custom-fields` and `elementor` (when Elementor is active).

### Meta keys

All sixteen keys are registered with `register_meta()` including sanitize callbacks, so they are cleaned even when written by other code:

| Meta key | Contents |
| --- | --- |
| `_qevora_layout_type` | `header`, `footer`, `section` (extensible) |
| `_qevora_layout_priority` | 0–999 |
| `_qevora_layout_conditions` | `{include:[],exclude:[]}` |
| `_qevora_layout_placement` | Placement slug |
| `_qevora_layout_hide_theme_part` | `1` while the layout replaces the theme's own header/footer |
| `_qevora_layout_demo` | Slug of the bundled PHP demo this layout renders |
| `_qevora_layout_menu_id` | Nav menu created for a demo layout |
| `_qevora_layout_demo_settings` | Customised settings of a demo layout (per-demo schema) |
| `_qevora_layout_hook` | Custom hook name |
| `_qevora_layout_hook_priority` | 0–999 |
| `_qevora_layout_builder` | `auto`, `block`, `elementor`, `demo` (extensible) |
| `_qevora_layout_content_source` | `self` or `reference` |
| `_qevora_layout_reference_id` | Referenced post ID |
| `_qevora_layout_template_id` | Import provenance |
| `_qevora_layout_import_source` | `bundled`, `upload`, `remote` |
| `_qevora_layout_imported_at` | ISO-8601 timestamp |

`qevora_layouts_types` adds new layout types; `qevora_layouts_meta_definitions` and `qevora_layouts_allowed_builders` extend the model without touching the plugin.

### Content source

* **self** — the layout's own block content is rendered.
* **reference** — the layout renders another post's content (`reference_id`). This is how Elementor-built content is used: build the design once in Elementor, then point a layout at it. Nothing is copied and no builder data is rewritten.

### Statuses and cloning

Published layouts are candidates; drafts, pending and private layouts are not. **Clone** copies content and every setting into a new *draft* — provenance (`template_id`, `import_source`, `imported_at`) is deliberately not copied, so a clone is a new layout rather than a second "import" of the same template.

## Builder support and compatibility matrix

The plugin ships an adapter interface (`Builder_Adapter_Interface`) and a manager that resolves which adapter renders a layout: the layout's `builder` hint first, then whichever adapter reports it can handle the content, with the block adapter as the final fallback. Adapters are registered through `qevora_layouts_builder_adapters`.

| Builder | Supported | How | Verified |
| --- | --- | --- | --- |
| **Block editor (Gutenberg)** | Yes, always | Content is stored as block markup in `post_content` and rendered through `apply_filters( 'the_content', … )` (which runs `do_blocks()`), with the global `$post` set up and restored so post-title/query blocks work. | Unit-tested against WordPress stubs; core API usage verified against the WordPress 7.1.4 source. |
| **Elementor** | Yes, when active and when "Elementor support" is on | `add_post_type_support( 'qevora_layout', 'elementor' )` so layouts can be edited in Elementor; rendering via `Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $id )`; detection via `did_action( 'elementor/loaded' )`, `class_exists( '\Elementor\Plugin' )` and `Document::is_built_with_elementor()` (the supported replacement for the deprecated `Elementor\DB::is_built_with_elementor()`). `Elementor\Frontend::enqueue_styles()` is called early when a layout will render. | Coded against documented APIs and checked against the Elementor 4.5.0 source. **Not** executed against a live Elementor install — see [Known limitations](#known-limitations). |
| **Other builders** | Not directly | Any builder that can render a post's content can be integrated by adding an adapter (a few dozen lines). Until then, use `content_source = reference` or the shortcode/block to place layouts inside that builder's own templates. | — |

Every Elementor call is defensive (`method_exists()`, `try/catch ( \Throwable )`) and the manager falls back to the block adapter when Elementor returns nothing, so a deactivated or upgraded Elementor cannot break the front end. The plugin never writes `_elementor_data` or any other builder meta.

## Template library and imports

### Registered JSON templates

The plugin no longer ships editor-based starter content: bundled demos are PHP-rendered (below). Sites that keep their own block templates can still register `.json` files through the `qevora_layouts_bundled_template_files` filter (absolute paths to readable JSON files); they appear in the library next to the remote library and go through the same validation, duplicate handling and display-scope flow as everything else.


### File format

Single template:

```json
{
  "$schema": "qevora-layouts/template/v1",
  "id": "my-agency-header",
  "name": "Agency header",
  "description": "Logo, navigation and a contact button.",
  "type": "header",
  "builder": "block",
  "content": "<!-- wp:group -->…<!-- /wp:group -->",
  "conditions": { "include": [ { "type": "front_page" } ], "exclude": [] },
  "priority": 20,
  "placement": "wp_body_open",
  "preview": "https://example.org/previews/agency-header.png",
  "tags": [ "header", "agency" ],
  "requires": { "wordpress": "6.4", "php": "7.4" },
  "version": "1.0.0",
  "author": "My Agency",
  "license": "GPL-2.0-or-later"
}
```

Collections wrap the same objects: `{ "templates": [ { … }, { … } ] }`, or a bare JSON array. Only `$schema`, `id`, `name`, `description`, `type`, `builder`, `content`, `conditions`, `priority`, `placement`, `preview`, `tags`, `requires`, `version`, `author`, `license` and `url` are read; anything else is ignored and reported as a warning.

### Validation before anything is created

`Template_Validator` runs on every template, whatever its origin:

* `$schema` must be `qevora-layouts/template/v1` (extend with `qevora_layouts_template_schemas`).
* `name` is required (≤120 characters), `type` must be a registered layout type.
* `builder: elementor` is a **hard error** with instructions: import Elementor JSON in Elementor's own importer, then reference that template from a layout. Elementor's export format is not this plugin's format, and guessing at it would mean writing builder internals.
* `content` must be a non-empty string, within the configured size limit, and free of PHP tags, `<script>`, `javascript:`, `data:text/html`, `<iframe>`, `<object>`/`<embed>` and inline event handlers. The block list is filterable (`qevora_layouts_forbidden_content_patterns`). Users without `unfiltered_html` additionally get `wp_kses_post()`.
* `preview` must be an https URL, or — for bundled templates only — a path that `realpath()` resolves **inside** the plugin directory (no traversal, no absolute paths).
* `url` (lazy download pointer) is accepted for remote templates only and must be https.
* `requires` accepts `wordpress`, `php` and a `plugins` list of `folder/file.php` references; invalid values are dropped with a warning.
* One file yields at most 50 templates; duplicate IDs inside a file are an error.
* Raw payloads are rejected before JSON parsing if they contain `<?php` or `<?=`; decoding is capped at depth 32 and at the configured byte limit.

**Imported data is never executed.** There is no `eval`, no `include` of uploaded content, no unserialize of template data, and no PHP evaluation of any string that came from a file, an upload or a network response.

### Uploads

Only `.json` files are accepted. The upload is verified with `is_uploaded_file()`, checked against `UPLOAD_ERR_*`, size-limited (4× the configured import limit), read from the temporary file and then discarded. WordPress' `wp_handle_upload()` is deliberately not used: `wp_get_mime_types()` has no `json` entry, so it would reject every template file. Uploads can be switched off entirely (`allow_uploaded_imports`).

### Remote library

Off by default, with no built-in or hard-coded endpoint. When an administrator enables it and enters an https URL:

* The index is fetched with `wp_remote_get()`: 15 second timeout, max 2 redirects, `Accept: application/json`, **no cookies**, custom user agent.
* Only 200 responses with a JSON or text content type are accepted; responses over 8 MB are refused.
* The index may be `{ "templates": [ … ] }` or a plain array. Entries may either contain `content` or point at their own `url`; those are fetched lazily **only from the same host as the configured endpoint** (`Remote_Source::url_is_allowed()`), so a hostile index cannot redirect the site to an attacker.
* Every fetched template goes through the same validation as an upload.
* The index is cached for 12 hours; "Refresh" clears it.

### One-click demo, display scope and previews

The library screen is built so a non-technical user can put a complete demo live in one click:

* **Per-demo settings metabox** - every demo layout is customised on its edit screen through a metabox built from that demo's own `settings.php` schema (texts, links, colours, toggles, selects). Demo layouts never open in the block editor: their markup lives in plugin PHP files, the edit screen says so and hides the empty content editor, and saved settings render on the front end immediately.
* **Display scope on every import** - each demo card offers a *Display on* select: *Where the demo says - recommended*, *Entire site*, *Front page only*, *Single posts and pages*, *Blog and archive listings*. The chosen scope becomes the layout's conditions at import time - for every import path (demo card, upload, admin AJAX and REST), so the default header/footer appears exactly where the user picked, without opening the editor. The import confirmation repeats it in plain words (*Displays on: Entire site*). Scopes map to condition sets through `qevora_layouts_display_scope_conditions`, and the list itself is filterable (`qevora_layouts_display_scopes`). The REST route accepts the same `display_scope` key, a full `conditions` object (`{include:[],exclude:[]}`) for programmatic imports, and - for the PHP demos below - `demo` (slug) plus `create_menu` (boolean).
* **Previews for every demo** - each card shows its preview image and a *Preview* button that opens an accessible dialog (focus moved in, Escape or *Close* moves it back) with the large image, the type, the builder requirement and the display rules the demo ships with. Cards without a preview image fall back to the bundled placeholder, so a preview always exists.
* **Enabled vs draft** - every import form keeps the *Enabled (display now)* / *Disabled (draft)* choice; "display now" simply means the layout is stored published with the chosen conditions.

### Bundled PHP demos (no editor needed)

Seven demos ship as **pure PHP templates** for sites that should not depend on the block editor or a page builder at all. Each demo lives in its own folder and keeps its own data, styles and behaviour:

| Demo | Type | Menu | Files |
| --- | --- | --- | --- |
| `header-classic` | header | yes | `template.php`, `style.css`, `script.js` |
| `header-centered` | header | yes | `template.php`, `style.css`, `script.js` |
| `footer-columns` | footer | yes | `template.php`, `style.css` |
| `footer-simple` | footer | no | `template.php`, `style.css`, `script.js` |
| `section-hero` | section | no | `template.php`, `style.css`, `settings.php` |
| `section-cta` | section | no | `template.php`, `style.css`, `settings.php` |
| `section-faq` | section | no | `template.php`, `style.css`, `script.js`, `settings.php` |

```
templates/demos/<slug>/
	demo.json      manifest: id, name, type, description, menu support
	template.php   the HTML, rendered server-side with escaped WP APIs
	settings.php   that demo's customisable fields (metabox + defaults)
	style.css      that demo's own responsive stylesheet
	script.js      that demo's own vanilla JavaScript (mobile menu, back to top)
```

* **Rendering:** layouts imported from a demo use the `demo` builder. `Demo_Adapter` includes the demo's `template.php` - resolved through a strict allow list, so only files shipped inside the plugin can ever be included, and imported JSON never becomes PHP. No post content is involved; editing happens in the demo files, not the editor.
* **Assets, demo by demo:** the stylesheet and script are enqueued only while that exact demo is planned or rendered (queued at plan time so the CSS reaches `wp_head`). All markup and CSS is scoped to the demo (`qdhc`, `qdhh`, `qdfc`, `qdfs` roots), responsive down to phone widths, and nothing is shared between demos.
* **Real menus:** demos with menu support can create a genuine WordPress nav menu at import - the home link plus up to five published pages, never invented content. Menu names stay unique, creating one needs `edit_theme_options` (otherwise the demo imports anyway, with a warning), and the layout stores its own `menu_id` with an *Edit the menu created for this demo* link in the editor.
* **Importing:** the library screen has a *Ready-made demos* section - each card offers *Display on* (default: entire site, so the demo shows immediately), *Create a sample menu*, *Hide the theme header/footer* (checked by default) and *Enabled/Disabled*. The REST route accepts `demo` + `create_menu`; duplicate handling works through the `qevora-demo-<slug>` provenance id.
* **Extending:** the demo list is filterable (`qevora_layouts_demos`) and the rendered markup per demo (`qevora_layouts_demo_markup`).

### Duplicate handling

Imports never overwrite silently. The policy is `skip` (default) or `copy`, per site and per import:

* **skip** — if a layout with the same `template_id` exists, nothing is created and the result points at the existing layout.
* **copy** — a second layout is created and the result says so.
* **replace** — only when the user explicitly picks a layout to overwrite (`replace_id`), and only if they may edit it.

Import results are per template: `created`, `copied`, `updated`, `skipped` or `failed`, each with messages, the affected layout IDs and edit links, so the UI can show progress and actionable errors.

## Shortcode, block and template tags

```text
[qevora_layout id="42"]
[qevora_layout type="section" wrapper="false"]
```

`type` resolves the active layout for the current request through the same condition engine. The block is `qevora/layout` (attributes `layoutId`, `wrapper`), registered with `api_version` 3 and a server-side `render_callback`, so block content is never trusted to contain rendered markup.

In a theme:

```php
qevora_layouts_the_header();           // Prints the active header.
qevora_layouts_the_footer();           // Prints the active footer.
echo qevora_layouts_get_layout( 42 );  // Returns one layout's markup.
if ( qevora_layouts_has_layout( 'header' ) ) { … }
```

All of them respect the duplicate guard, the type switches and the capability rules (an unpublished layout only renders for users who may edit layouts).

## REST API

Namespace `qevora-layouts/v1`. Read routes require `edit_qevora_layouts`; the import route requires `publish_qevora_layouts` plus a `wp_rest` nonce. All arguments are schema-validated and responses are escaped.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/layouts` | List layouts (`type`, `status`, `search`, `page`, `per_page`), with `X-WP-Total`. |
| GET | `/layouts/{id}` | One layout, including conditions and provenance. |
| GET | `/templates` | Library entries (bundled, remote or both) without content. |
| POST | `/templates/import` | Import by `template_id`, `origin`, `on_duplicate`, `replace_id`, `status`, plus `display_scope`, `demo` + `create_menu` or an explicit `conditions` object. |
| GET | `/search` | Search posts, terms or users for the condition pickers. |
| GET | `/system-status` | The same report the System Status screen shows (`manage_options` only). |

No secret, token, absolute server path or debug detail is returned to users without the matching capability, and the status route is unreachable for anyone who cannot manage options.

## Settings

| Key | Default | Notes |
| --- | --- | --- |
| `header_enabled`, `footer_enabled`, `section_enabled` | `true` | Master switches, checked before anything resolves. |
| `render_wrapper` | `true` | Print the wrapper `<div>`. |
| `default_header_placement` | `wp_body_open` | Used when a layout does not override it. |
| `default_footer_placement` | `get_footer` | Same for footers. |
| `hide_theme_header_selectors`, `hide_theme_footer_selectors` | `''` | Opt-in CSS selectors, hardened as described above. |
| `inline_hide_css` | `true` | Print the `<style>` element at all. |
| `builder_block`, `builder_elementor` | `true` | Enable adapters. |
| `elementor_editor_support` | `true` | Allow layouts to be edited in Elementor. |
| `remote_library_enabled`, `remote_library_url` | `false`, `''` | https only, no host baked in. |
| `allow_uploaded_imports` | `true` | Turn `.json` uploads off. |
| `max_import_size_kb` | `2048` | Clamped to 16–20480 KB. |
| `duplicate_import_policy` | `skip` | `skip` or `copy`. |
| `cache_candidates` | `true` | Cache candidate lookups. |
| `cache_ttl_seconds` | `43200` | Clamped to 60–86400 seconds. |
| `debug_mode` | `false` | Off by default; enables the internal log buffer. |
| `delete_data_on_uninstall` | `false` | Uninstall keeps your layouts unless you ask. |

Settings are stored in one option (`qevora_layouts_settings`) with a registered `sanitize_callback`, so even a direct `update_option()` write is cleaned. Defaults are filterable via `qevora_layouts_default_settings`.

## System Status

**Qevora Layouts → System Status** reports: WordPress, PHP and theme versions; whether the active theme fires `wp_body_open()`; whether the site is a block theme; which header/footer plugins are active (a real source of duplicate headers); builder availability with version and requirement checks; layout counts by type and status; cache version and request-cache size; and the plugin's own settings that affect rendering. Debug mode adds an in-memory log buffer with warnings and errors (for example "no adapter could render layout 42"). The report can be copied as text for a support request — it contains no secrets, no tokens and no filesystem paths beyond what an administrator already sees.

## Developer reference: hooks

### Filters

| Filter | Purpose |
| --- | --- |
| `qevora_layouts_types` | Register layout types beyond header/footer/section. |
| `qevora_layouts_allowed_builders` | Register builder identifiers. |
| `qevora_layouts_builder_adapters` | Register a `Builder_Adapter_Interface` implementation. |
| `qevora_layouts_conditions` | Register or change condition definitions. |
| `qevora_layouts_condition_matches` | Override one condition's evaluation. |
| `qevora_layouts_condition_post_types`, `qevora_layouts_condition_taxonomies` | Restrict the pickers. |
| `qevora_layouts_context` | Adjust the resolved request context. |
| `qevora_layouts_matching_layouts` | Reorder or filter matched layouts. |
| `qevora_layouts_plan`, `qevora_layouts_should_run`, `qevora_layouts_should_render` | Frontend render decisions. |
| `qevora_layouts_layout_placement` | Effective placement of a layout. |
| `qevora_layouts_pre_render_content`, `qevora_layouts_rendered_content`, `qevora_layouts_render_layout` | Content rendering. |
| `qevora_layouts_wrapper_classes`, `qevora_layouts_wrapper_attributes` | Wrapper markup. |
| `qevora_layouts_hide_theme_css` | The hide-theme CSS block (re-validated afterwards). |
| `qevora_layouts_theme_part_selectors` | Default selectors used when a layout replaces the theme header/footer. |
| `qevora_layouts_enqueue_frontend_assets` | Frontend asset loading. |
| `qevora_layouts_sanitize_content` | Stored layout content. |
| `qevora_layouts_post_type_args`, `qevora_layouts_meta_definitions` | CPT and meta registration. |
| `qevora_layouts_default_settings`, `qevora_layouts_settings_fields` | Settings. |
| `qevora_layouts_template_schemas`, `qevora_layouts_forbidden_content_patterns`, `qevora_layouts_import_data` | Imports. |
| `qevora_layouts_bundled_template_pattern`, `qevora_layouts_bundled_template_files`, `qevora_layouts_template_candidates` | Library sources. |
| `qevora_layouts_remote_request_args` | Remote request arguments. |
| `qevora_layouts_admin_submenus`, `qevora_layouts_list_columns`, `qevora_layouts_library_view_context`, `qevora_layouts_settings_view_context`, `qevora_layouts_status_report` | Admin. |
| `qevora_layouts_granted_roles` | Roles that receive the plugin capabilities on activation (default: `administrator`). |
| `qevora_layouts_display_scopes`, `qevora_layouts_display_scope_conditions` | The *Display on* choices and their condition sets. |
| `qevora_layouts_demos` | The bundled PHP demos. |
| `qevora_layouts_demo_markup` | Markup rendered by a bundled PHP demo. |
| `qevora_layouts_rest_layout`, `qevora_layouts_allow_disabled_layout`, `qevora_layouts_auto_render_types`, `qevora_layouts_languages_dir` | Misc. |

### Actions

`qevora_layouts_loaded`, `qevora_layouts_booted`, `qevora_layouts_activated`, `qevora_layouts_deactivated`, `qevora_layouts_admin_registered`, `qevora_layouts_admin_assets`, `qevora_layouts_admin_integration_notices`, `qevora_layouts_builders_registered`, `qevora_layouts_frontend_registered`, `qevora_layouts_layout_saved`, `qevora_layouts_layout_duplicated`, `qevora_layouts_meta_saved`, `qevora_layouts_list_column`, `qevora_layouts_slot_rendered`, `qevora_layouts_cache_flushed`, `qevora_layouts_upgraded`.

### Adding a layout type

```php
add_filter( 'qevora_layouts_types', function ( $types ) {
    $types['popup'] = array(
        'label'       => __( 'Popup', 'my-plugin' ),
        'description' => __( 'A modal overlay.', 'my-plugin' ),
        'placements'  => array( 'wp_footer', 'custom_hook', 'manual' ),
        'default_placement' => 'wp_footer',
    );
    return $types;
} );
```

### Adding a builder adapter

```php
add_filter( 'qevora_layouts_builder_adapters', function ( $adapters ) {
    $adapters[] = new My_Plugin_Adapter(); // implements Builder_Adapter_Interface
    return $adapters;
} );
```

Extend `Abstract_Builder_Adapter` to inherit the error guard, the availability checks and the settings plumbing; only `get_id()`, `get_name()`, `is_available()`, `can_edit()` and `render()` are required.

## Security model

* **Capabilities everywhere.** `edit_qevora_layouts`, `publish_qevora_layouts`, `delete_qevora_layouts` (mapped to the CPT) and `manage_options` for settings and status. Per-object checks (`current_user_can( 'edit_post', $id )`) run in the repository, so saving, toggling, cloning, deleting and replacing all fail closed with a 403.
* **Nonces on every state change** — meta boxes, row actions, settings, library imports, AJAX and REST.
* **Sanitize on input, escape on output.** All meta has registered sanitize callbacks; every view escapes with `esc_html()`, `esc_attr()`, `esc_url()` or `wp_kses_post()`; admin notices escape their details.
* **No raw SQL.** Every query goes through `WP_Query`, `get_posts()`, `get_post_meta()` and the post API. The only `$wpdb` touch points are two read-only calls in the System Status report (`$wpdb->db_version()` and `$wpdb->charset`); no SQL string is concatenated anywhere in the plugin.
* **Imports are treated as hostile input**: extension check, upload verification, size caps, JSON depth caps, schema validation, forbidden-pattern scanning, KSES for limited users, https-only and host-pinned remote fetching, preview paths confined to the plugin directory.
* **No code execution from data.** No `eval`, no `assert`, no `create_function`, no `unserialize` of remote data, no `include` of user files, and PHP tags are stripped from stored content even for users with `unfiltered_html`.
* **No secret exposure.** Nothing is printed publicly unless a layout renders; the System Status report and the debug log require `manage_options`; error messages never include absolute paths, tokens or stack traces.
* **Direct access blocked.** Every directory has an `index.php` guard and every PHP file starts with `if ( ! defined( 'ABSPATH' ) ) { exit; }`.

## Performance and caching

* The render plan (which header and which footer apply) is resolved **once** per request at `template_redirect` priority 5 and memoized.
* Candidate lists per type are cached in the object cache under a **versioned key** (`ql_{version}_candidates_{type}`), TTL `cache_ttl_seconds`, plus a per-request memo. Versioning means invalidation is a single option write: saving, deleting, duplicating, trashing, untrashing, changing status, editing layout meta or saving settings all call `Cache::bump_version()`, and the version is strictly increasing so two saves in the same second cannot collide.
* The frontend stylesheet and script load only when a layout actually renders; admin assets only on plugin screens.
* Queries are narrow: `fields => ids`, `no_found_rows`, no term cache, `posts_per_page` capped at 300, and meta queries restricted to type and template ID.
* Cached candidate rows intentionally exclude post content, so the object cache never holds large markup; content is read once, for the single layout that renders.

## Accessibility and translations

* The condition builder is keyboard operable: rules are added and removed with real `<button>` elements, the post/term/user picker is a `role="listbox"` with `aria-expanded`, `aria-activedescendant` and arrow-key navigation, and every state change is announced through an `aria-live="polite"` region.
* Form fields have `<label for>`, error and success messages are associated with `aria-describedby`, focus is moved to the feedback region after an import, and no information is conveyed by colour alone.
* All strings go through `__()/esc_html__()/esc_attr__()` with the `qevora-layouts` domain. `languages/qevora-layouts.pot` holds 529 strings, generated with `python3 tools/make-pot.py` (a dependency-free generator; `wp i18n make-pot` produces the same output if you prefer it). Drop `qevora-layouts-{locale}.mo` into `languages/` to translate.

## Uninstall and your data

Deleting the plugin from the Plugins screen runs `uninstall.php`, which:

1. Checks `WP_UNINSTALL_PLUGIN` and the `delete_data_on_uninstall` option — **by default it does nothing at all** beyond letting WordPress remove the files. Every layout, option and meta row stays in the database, so reinstalling brings them back.
2. Only when an administrator explicitly enabled "Delete all plugin data on uninstall" does it remove data: `qevora_layout` posts are permanently deleted with `wp_delete_post( $id, true )` in batches of 100 (capped at 5000 per site so a large site cannot time out), then the plugin's post meta keys are removed with `delete_post_meta_by_key()`, per-user admin notice transients are deleted, and finally the plugin's own options.
3. On multisite it iterates sites with `get_sites()` and `switch_to_blog()`, per site, never a global table sweep.
4. It never touches posts, options, meta or tables that do not belong to the plugin, and it never drops custom tables (there are none).

Deactivating the plugin deletes nothing: it invalidates the cache version, drops the in-memory settings cache and fires `qevora_layouts_deactivated`. The plugin registers no cron events, so there is nothing to unschedule.

## Testing

The suite has **no Composer, PHPUnit or database dependency** — it runs on any PHP 7.4+ install:

```bash
php tests/run.php                     # everything
php tests/run.php --filter=Conditions # one area
php tests/run.php --verbose           # per-test output
```

Current result: **191 tests, 1836 assertions, 0 failures** (PHP 8.5, `php tests/run.php`).

| Area | File | Covers |
| --- | --- | --- |
| Conditions | `tests/Unit/Conditions/Condition_Sanitizer_Test.php` | Rule normalization, value sanitizing, JSON string decoding, role validation, summaries |
| Conditions | `tests/Unit/Conditions/Condition_Registry_Test.php` | Definitions, labels, value types, specificity, extensibility |
| Conditions | `tests/Unit/Conditions/Condition_Matcher_Test.php` | Include/exclude semantics, all 17 types, specificity ordering |
| Conditions | `tests/Unit/Conditions/Context_Factory_Test.php` | Context built from query/user APIs, caching, filters |
| Core | `tests/Unit/Core/Cache_Test.php` | Versioned keys, invalidation, TTLs, memoization |
| Core | `tests/Unit/Core/Settings_Test.php` | Defaults, sanitizing, CSS selectors, library URLs |
| Core | `tests/Unit/Core/Layout_Post_Type_Test.php` | Every sanitizer and the meta key prefix |
| Core | `tests/Unit/Core/Layout_Repository_Test.php` | Save, capabilities, provenance, clone, delete, candidates, matching |
| Import | `tests/Unit/Import/Template_Validator_Test.php` | Schema, types, builders, forbidden patterns, previews, URLs, payloads |
| Import | `tests/Unit/Import/Template_Library_Test.php` | Bundled loading, filters, broken files, remote index, shadowing |
| Import | `tests/Unit/Import/Importer_Test.php` | JSON decoding, upload validation, duplicate policies, replacement, remote fetch |
| Frontend | `tests/Unit/Frontend/Duplicate_Guard_Test.php` | One render per slot, normalization, filters, actions |
| Frontend | `tests/Unit/Frontend/Frontend_Security_Test.php` | The inline CSS validator |
| Admin | `tests/Unit/Admin/Meta_Boxes_Test.php` | Demo layouts leave the block editor; per-demo settings metabox renders, saves and sanitises |
| Integration | `tests/Integration/Activation_Test.php` | Activation seeds options, grants the plugin capabilities, dies gracefully on old environments, migration self-heals, deactivation deletes nothing |
| Frontend | `tests/Unit/Frontend/Placement_Test.php` | Stored placements versus the hooks block and classic themes really fire |

### Real WordPress smoke test

For 1.0.0 the release archive was additionally exercised on a **real WordPress 7.1.4** install (SQLite database drop-in, PHP 8.5, no browser): activation through `activate_plugin()` exactly as `plugins.php` runs it; all six REST routes as an administrator and anonymously (401); demo import, duplicate skip and replace through `POST /templates/import`; the front page of the block theme rendering both the header and the footer wrapper with real block content; and the Template Library, Settings and System Status screens rendering their full admin pages. This caught two bugs the stub suite could not see (a namespace-relative class reference that fatalled activation, and plugin capabilities that were never granted to any role). The browser-level steps in [`tests/INTEGRATION.md`](tests/INTEGRATION.md) remain the last manual gate, and the Elementor adapter has still never run against Elementor.

`tests/stubs/wordpress.php` provides the WordPress functions the tested classes use (hooks, options, object cache, an in-memory post store, `WP_Query`, KSES, the HTTP API, capabilities, assets, REST/block/shortcode registration). Anything that needs MySQL, a real theme, the REST server or the block parser is **not** covered by this suite — those paths are listed as a manual checklist in [`tests/INTEGRATION.md`](tests/INTEGRATION.md), and they must be run on a real WordPress install before a release is considered verified.

### Verifying that no API was invented

`tools/verify-wp-functions.php` indexes the WordPress and Elementor sources, then scans the plugin and resolves every function call, class reference, `class_exists()` string and Elementor method call against them. It fails loudly on anything that does not exist:

```bash
# Download the sources once (any WordPress >= 6.4 checkout works).
curl -sL https://codeload.github.com/WordPress/WordPress/tar.gz/refs/tags/7.1.4 | tar xz -C /tmp/verify
curl -sL https://codeload.github.com/elementor/elementor/tar.gz/refs/heads/main | tar xz -C /tmp/verify

php tools/verify-wp-functions.php /tmp/verify/wordpress-7.1.4 /tmp/verify/elementor-main
```

Last run against WordPress 7.1.4 and Elementor `main` (4.5.0): 165 WordPress functions, 10 WordPress classes, `\Elementor\Plugin` and both Elementor methods (`Frontend::enqueue_styles()`, `Frontend::get_builder_content_for_display()`) resolve; **0 unresolved** references.

### Building the release ZIP

```bash
bash tools/build-zip.sh
```

It reads the version from the plugin header, stages every tracked file that `.distignore` does not exclude into a single top-level `qevora-layouts/` directory, writes `release/qevora-layouts-<version>.zip`, and then verifies the result: no `.git/`, `tests/`, `tools/`, `release/`, `*.sh`, `*.py`, `.distignore` or `node_modules`; the bootstrap, `uninstall.php`, `LICENSE`, `README.md`, `readme.txt`, `CHANGELOG.md`, `includes/`, `assets/`, `templates/demos/`, `languages/qevora-layouts.pot` and the `index.php` guards all present; exactly one top-level directory. The 1.0.0 archive holds 135 files (248 KB) and all 79 PHP files in it pass `php -l`.

## Known limitations

These are real, and stated deliberately rather than papered over:

1. **Partial live-site verification.** 1.0.0 was smoke-tested on a real WordPress 7.1.4 install (SQLite drop-in, PHP 8.5, CLI requests - no browser): activation, REST permissions, imports, front-end rendering on a block theme and the three admin screens all pass, as described under *Real WordPress smoke test*. What has **not** been exercised in any live environment yet: the browser-side admin JavaScript (condition pickers, library import progress), the Elementor adapter against a real Elementor install, multisite activation/uninstall and the browser-level steps in `tests/INTEGRATION.md`. The Elementor adapter in particular should be exercised on a real install before relying on it in production.
2. **Elementor rendering depends on Elementor's own asset loading.** `get_builder_content_for_display()` enqueues the document CSS and flags the page, but Elementor only enqueues its frontend stylesheet for singular documents built with Elementor. The adapter calls `Elementor\Frontend::enqueue_styles()` early to compensate; on unusual setups (caching plugins that defer CSS, Elementor's "Improved CSS Loading" experiment) a header may still need `elementor-frontend` styles added manually.
3. **No drag-and-drop builder of its own.** Layouts are authored in the block editor or in Elementor. The plugin places, conditions and renders them.
4. **Elementor template JSON files cannot be imported.** This is intentional: importing them would mean writing Elementor's internal meta. The validator returns instructions instead (import in Elementor, then use `content_source = reference`).
5. **Block theme template parts are not replaced.** On block themes the plugin renders through `wp_body_open`/`wp_footer`. It does not register or override `header`/`footer` template parts in the site editor; archive/single/search/404 *template* types are supported by the data model but not shipped as types in 1.0.0.
6. **A cached `null` is recomputed.** `Cache::remember()` treats `null` as a miss, so "no layout matched" is recalculated rather than cached. This keeps a stale empty result from sticking for the whole TTL at the cost of one extra query on requests with no matching layout.
7. **Sections are manual.** `section` layouts never render automatically; use the shortcode, block, a custom hook or a template tag.
8. **The remote library is single-host.** A remote index can only point at files on its own host. This is a deliberate SSRF defence, not an oversight.
9. **PHP 7.4 syntax floor.** No enums, no `readonly`, no constructor promotion, no `match`, no nullsafe operator, no union types — so the plugin also runs on current PHP 8.x but cannot use their conveniences.

## Project layout

```text
qevora-layouts/
├── qevora-layouts.php          Bootstrap: constants, requirements, single loader
├── uninstall.php               Explicit, opt-in data removal
├── includes/
│   ├── Support/                Requirements, environment report
│   ├── Core/                   Autoloader, Loader, Plugin, Settings, Cache, Logger,
│   │                           Capabilities, Layout_Post_Type, Layout, Layout_Repository,
│   │                           Activator, Deactivator, I18n
│   ├── Conditions/             Context, Context_Factory, Condition_Registry,
│   │                           Condition_Matcher, Condition_Sanitizer
│   ├── Integrations/           Builder_Adapter_Interface, Abstract_Builder_Adapter,
│   │                           Builder_Manager, Adapters/{Block,Elementor}_Adapter
│   ├── Frontend/               Frontend, Renderer, Duplicate_Guard, Shortcode,
│   │                           Layout_Block, Template_Tags
│   ├── Import/                 Template, Template_Validator, Importer, Remote_Source,
│   │                           Template_Library
│   ├── Admin/                  Admin, Notices, Layout_Columns, Meta_Boxes, Actions, Ajax,
│   │                           Import_Handler, Settings_Page, System_Status_Page,
│   │                           Template_Library_Page, Views/
│   └── REST/                   Rest_Controller
├── assets/                     css/, js/, images/previews/
├── templates/
│   ├── frontend/layout-wrapper.php   Overridable from the theme
│   └── demos/<slug>/                 Seven bundled PHP demos (manifest, template, CSS, JS, settings)
├── languages/qevora-layouts.pot
├── tests/                      run.php, TestCase.php, stubs/, Unit/, Integration/
└── tools/                      make-pot.py, build-zip.sh, verify-wp-functions.php
```

## Changelog, attribution and license

* [CHANGELOG.md](CHANGELOG.md) — release history.
* [readme.txt](readme.txt) — WordPress.org formatted readme.
* [LICENSE](LICENSE) — GPL-2.0-or-later, the same license as WordPress itself.
* Bundled demos are plain PHP, HTML, CSS and vanilla JavaScript written for this plugin; imported `.json` templates may use core WordPress blocks. Preview images in `assets/images/previews/` are original SVG artwork created for this plugin and licensed GPL-2.0-or-later.
* No third-party libraries are bundled. WordPress and Elementor are used through their public APIs; Elementor is a separate project by Elementor Ltd. and is not required.

© Qevora Software. Released under the GPL-2.0-or-later license.
