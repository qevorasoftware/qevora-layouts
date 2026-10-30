=== Qevora Layouts ===
Contributors: qevorasoftware
Tags: header, footer, header builder, footer builder, dynamic header, template, conditions, block editor, elementor
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build dynamic headers, footers and reusable layout templates with a real condition engine, builder adapters and a validated starter template library.

== Description ==

Qevora Layouts lets you create any number of headers, footers and reusable sections, decide exactly where each one appears, and render them without touching your theme.

= Headers and footers that follow your content =

* Choose where a layout applies in plain words: entire site, front page, blog listing, single posts and pages, archives, search, 404, a post type, a post type archive, a taxonomy, specific terms, specific posts or pages, an author, logged-in visitors, logged-out visitors or user roles.
* Include and exclude rules combine, and exclusions always win.
* Priority ordering decides which layout renders when several match; on equal priority the more specific one wins, then the older one, so the result is always predictable.
* Enable or disable a layout with one click from the list table - nothing is deleted.
* A duplicate guard makes sure a slot renders once per request, even if a theme fires its hooks twice.
* Your theme's own header and footer stay untouched. If you want them hidden, you enter the CSS selectors yourself in Settings, and the plugin prints one validated `<style>` element. No theme file is ever modified.

= Built on WordPress, not around it =

* Layouts are a normal custom post type with prefixed meta, so revisions, the block editor, capabilities and backups work as usual.
* Content is authored in the block editor and rendered through the standard `the_content` pipeline.
* Conditions are resolved from WordPress query and user APIs - never from URL strings.
* No third-party page builder is needed to activate or use the plugin.

= Builder support =

* Block editor (Gutenberg): always available.
* Elementor: supported when installed. Layouts can be edited with Elementor, and Elementor-built content renders through Elementor's own public API. The plugin never writes Elementor's internal data.
* Any other builder: use "reference existing content" to point a layout at content you built elsewhere, or add an adapter with the `qevora_layouts_builder_adapters` filter.

= Template library =

* Eight starter templates ship with the plugin: three headers, two footers and three reusable sections. All of them use core blocks only.
* Import from the library, upload a `.json` file, or connect your own https endpoint.
* Every template is validated against a schema before anything is created: type, builder, size, conditions, preview, requirements and a block list that rejects PHP tags, scripts, iframes, event handlers and `javascript:` URLs.
* Imports never overwrite silently. The default policy is to skip a template that was imported before; you can ask for a copy, or pick one layout to replace explicitly.
* Remote libraries are off by default, https only, and can only point at files on their own host.

= For developers =

* 40+ filters and actions, including custom layout types, custom condition types, custom builder adapters and a replaceable wrapper template.
* A REST namespace `qevora-layouts/v1` for layouts, templates, imports, search and system status.
* A shortcode `[qevora_layout]`, a `qevora/layout` block and PHP template tags.
* Cached candidate lookups with versioned keys and automatic invalidation.

= Privacy and data =

* No data leaves your site unless you enable the remote template library and enter an endpoint yourself.
* No tracking, no external requests on activation, no third-party services.
* Uninstalling deletes nothing by default. Data removal is an explicit opt-in in Settings.

Full documentation, the compatibility matrix, the hook reference and the testing instructions are in the [plugin README](https://github.com/qevorasoftware/qevora-layouts#readme).

== Installation ==

1. In WordPress go to Plugins -> Add New -> Upload Plugin.
2. Choose `qevora-layouts.zip` and click Install Now.
3. Click Activate.
4. Open Qevora Layouts -> Template Library and import a starter, or open Qevora Layouts -> Add New to build your own.

= Manual installation =

1. Upload the `qevora-layouts` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.

= Requirements =

* WordPress 6.4 or newer (tested up to 7.1)
* PHP 7.4 or newer
* A theme that calls `wp_body_open()` for the default header placement (every default theme since Twenty Twenty does; block themes are handled automatically)

If a requirement is missing the plugin stays inactive and explains what to upgrade. It never produces a fatal error.

== Frequently Asked Questions ==

= Do I need Elementor or another page builder? =

No. The block editor is enough for everything. Elementor support switches on automatically when Elementor is active, and can be turned off in Settings.

= Will this remove my theme's header? =

Not unless you ask it to. Settings -> Rendering has explicit fields for the CSS selectors of your theme header and footer. Until you fill them in, the theme keeps rendering exactly as before and your layout is added on top.

= Can two headers appear on one page? =

No. Each slot renders once per request. If several layouts match, the condition engine picks one by priority, then by specificity, then by age. You can see every match through the `qevora_layouts_matching_layouts` filter.

= What happens on a block theme? =

Block themes do not fire `get_header` or `get_footer`. The plugin detects `wp_is_block_theme()` and uses `wp_body_open` and `wp_footer` instead, so placements keep working.

= Can I import Elementor template files? =

Not directly, and that is deliberate: importing them would mean writing Elementor's internal data. Import the file in Elementor -> Templates -> Saved Templates, then create a layout and set Content source to "Reference existing content". The validator says exactly this when it sees an Elementor file.

= Where do my layouts live if I deactivate the plugin? =

In your database, as `qevora_layout` posts. Deactivating changes nothing. Uninstalling also changes nothing unless you enable "Delete all plugin data on uninstall" in Settings first.

= Can I add my own layout types, for example popups or 404 templates? =

Yes. The data model is type driven: add a definition through the `qevora_layouts_types` filter and the list table, meta boxes, REST API and condition engine pick it up.

= Does the plugin make any external requests? =

Only if you enable the remote template library and enter an https endpoint. Then it requests that endpoint (and files on the same host) with a 15 second timeout, no cookies and an 8 MB response cap.

= How do I place a section inside my content? =

Use the `[qevora_layout id="42"]` shortcode, the "Qevora Layout" block, a custom hook, or `qevora_layouts_the_layout( 42 )` in a template.

= Where can I report a problem? =

Open an issue at [the GitHub repository](https://github.com/qevorasoftware/qevora-layouts/issues). Include the output of Qevora Layouts -> System Status; it is written to be safe to share.

== Changelog ==

= 1.0.0 =
* First release.
* Dynamic headers, footers and reusable sections stored as a WordPress custom post type.
* Condition engine with 17 condition types, include/exclude semantics and documented precedence (priority, then specificity, then ID).
* Placements: `wp_body_open`, `get_header`, `get_footer`, `wp_footer`, a custom hook or manual, with block theme detection.
* Duplicate render guard, enable/disable switching, clone action and a list table with condition summaries.
* Builder adapter layer: block editor through core APIs, Elementor through its documented public APIs, reference mode for any other builder.
* Template library with eight bundled starter templates, `.json` uploads and an optional https remote source.
* One-click demo packs: import a complete header and footer demo (or the full demo site) and display it right away.
* Ready-made PHP demos: headers and footers rendered by the plugin itself with plain HTML, responsive CSS and a little JavaScript - every demo keeps its own stylesheet and data, menu demos can create a real WordPress menu (Home plus your published pages) at import, and no page builder or block editor is needed.
* Imported demos can replace the theme's own header and footer instead of stacking on top of it: the option is offered checked by default on every import form, block themes drop the theme template part outright, classic themes hide it with sanitized inline CSS, theme files are never modified and unticking the box restores them instantly.
* Choose where each imported demo displays (entire site, front page, posts and pages, archives) and the conditions are applied on import; refine them per layout later.
* Every demo shows a preview image on its card and a large preview dialog with its display rules and builder requirement.
* Schema validation, forbidden-pattern scanning, duplicate-safe import policies and per-template import results.
* Admin screens: All Layouts, Add New, Template Library, Settings and System Status.
* REST namespace `qevora-layouts/v1`, shortcode, block and template tags.
* Versioned candidate cache with invalidation on every write, lazy asset loading, and an off-by-default debug mode.
* Capabilities, nonces, sanitization and escaping throughout; explicit opt-in data removal on uninstall.
* 529 translatable strings in `languages/qevora-layouts.pot`; 181 automated tests with a dependency-free runner.

== Upgrade Notice ==

= 1.0.0 =
First release. Nothing to upgrade.
