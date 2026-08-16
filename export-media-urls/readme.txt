=== Export Media URLs ===
Contributors: Atlas_Gondal, waqasgondal
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=YWT3BFURG6SGS&source=url
Tags: export, media, urls, csv, json
Requires at least: 3.6
Tested up to: 7.1
Stable tag: 3.2
Requires PHP: 5.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Extract every Media Library URL with title, size, dimensions, alt text and more. Filter by media type, export to CSV/JSON, and audit your library.

== Description ==

The fastest way to pull a complete inventory of your WordPress Media Library. Export every attachment's details — pick exactly the columns you want from grouped, collapsible field sections with quick-select presets — then download a CSV or JSON file, or display the results in a paginated table right inside the dashboard. A separate **Media Tools** tab helps you audit and clean up the library. Invaluable for migrations, SEO and accessibility audits, and library cleanup.

The export is built to survive real-world content: every record stays on a single CSV line even when your captions contain line breaks, commas and quotes are escaped to the CSV standard, the delimiter is switchable for Excel in European locales, and text that was mangled by an old character set ("â€™" instead of "’") can be repaired on the way out.

= Media Tools =

Each tool lives on its own sub-tab and processes the library in pages (100, 250, 500, 1000 or All per page), so the scans stay fast and light even on libraries with thousands of items.

* **Storage summary** — attachment counts per media type, read straight from the database (instant)
* **Missing file detector** — find attachments whose file is gone from disk (e.g. after a bad migration)
* **Duplicate detection** — find files that are byte-for-byte identical (matched by size and content hash; choose "All" for a complete cross-library scan)
* **Heavy image flags** — spot oversized images (large file size or pixel dimensions) that slow your pages
* **Bulk alt-text editing** — review and update alt text image by image, page by page, with missing alt text highlighted

You can export each media item's:

* ID
* Title
* File Name
* URL
* File Size
* MIME Type
* Dimensions (width x height)
* Thumbnail / Medium / Large image URLs
* Caption
* Alt Text
* Alt Missing (flag — quickly find images with no alt text)
* Description
* Uploaded To (Parent) and Uploaded To URL — where the file was first uploaded
* Used In (count), Used In (posts), Used In (URLs), Used In (post ID) and Used In (post type) — every post that actually references the item
* Unused (flag — items not found in any post's content)
* Date Uploaded

The data can be filtered by **media type** (images, video, audio, documents, archives), by **attachment status** (attached vs. unattached/orphaned), by author, and between a date range before extraction. You can also export a specific item range to keep each request small on very large libraries.

== When do we need this plugin? ==

* To check all Media URLs of your website
* During a migration (and to confirm everything moved across)
* During an SEO or accessibility audit (find images missing alt text)
* To clean up orphaned media imported by a theme demo
* During a security audit

= Customizable Features =

* Choose exactly which columns to export, grouped with quick-select presets (Migration, SEO/Accessibility, Cleanup, Full)
* Download as CSV or JSON, or display the results in a paginated table
* Filter by media type, attachment status, author and date range
* Export a specific item range (helpful on very large libraries)
* Custom or randomly generated download file name
* Choose the CSV delimiter (comma, semicolon or tab) and keep every record on a single line
* Repair mis-encoded characters and decode HTML entities on the way out, optionally folding typographic punctuation to plain ASCII
* Report usage as one row per media item or one row per usage, and optionally scan custom fields and page-builder data
* Remembers your last field selection
* CSV and JSON downloads stream straight to the browser, leaving nothing behind in wp-content/uploads

= System requirements =

* PHP version 5.4 or higher
* WordPress version 3.6 or higher

= Feedback =

If you like this plugin, then please consider leaving us a good [rating](https://wordpress.org/support/plugin/export-media-urls/reviews/).

= Contact =

For further information please send me an [email](https://AtlasGondal.com/contact-me/?utm_source=self&utm_medium=wp&utm_campaign=export-media-urls&utm_term=plugin-description).

== Installation ==

= From your WordPress dashboard =

1. Visit 'Plugins > Add New'
2. Search for 'Export Media URLs'
3. Activate Export Media URLs from your Plugins page.

= From WordPress.org =

1. Download Export Media URLs.
2. Unzip the plugin.
3. Upload the 'export-media-urls' directory to your '/wp-content/plugins/' directory, using your favorite method (ftp, sftp, scp, etc...)
4. Activate Export Media URLs from your Plugins page.

= Usage =

1. Go to Tools > Export Media URLs.
2. Tick the fields you want (or use a preset such as "SEO / Accessibility").
3. Choose a media type, and optionally filter by attachment status, author or date range.
4. Click "Download" to get a CSV or JSON file, or "Display Here" to view the results in a paginated table.

= Uninstalling: =

1. In the Admin Panel, go to "Plugins" and deactivate the plugin.
2. Click "Delete" on the Plugins page. This removes the plugin files and its per-user preference automatically.

== Frequently Asked Questions ==

= What data can I export? =

For every media item you can export the ID, Title, File Name, URL, File Size, MIME Type, Dimensions, sized-image URLs (thumbnail/medium/large), Caption, Alt Text, an "Alt Missing" flag, Description, Parent Post and Parent URL, the upload date, and the full "Used In" set — count, post titles, post URLs, post IDs and post types — plus an "Unused" flag. Choose exactly the columns you want using the grouped checkboxes and quick-select presets.

= Can I export to JSON as well as CSV? =

Yes. You can download the data as a CSV file, download it as JSON, or display it in a paginated table right inside the dashboard.

= What is the difference between "Uploaded To (Parent)" and "Used In"? =

They answer two different questions. **Uploaded To (Parent)** is WordPress's own `post_parent` value — it records the single post that was open in the editor when the file was *uploaded* (or nothing, for files added from Dashboard → Media). It never changes when you later insert the image elsewhere. **Used In** is computed fresh by scanning your posts and pages for every place the item is actually referenced — featured images, editor content, galleries and pasted URLs — so one item can list several posts, exactly matching where it is used today.

= Can I find media that isn't used anywhere? =

Two ways, and they mean different things. The **"Attachment Status → Unattached"** filter lists media whose `post_parent` is 0 (typically anything uploaded from Dashboard → Media) — but such a file may still be used in a post. For real usage, add the **"Used In (count)"** and **"Unused"** fields (or the **Where Used** / **Cleanup** preset): "Unused" is flagged only when the item is not referenced by any scanned post. Please treat it as a strong hint, not proof — references stored by page builders, custom fields, widgets, sliders or CSS are not scanned, so always confirm before deleting anything.

= Can I find images missing alt text? =

Yes. Add the "Alt Missing" field (or use the SEO / Accessibility preset). Any image with no alt text is flagged, so you can fix accessibility and SEO gaps quickly.

= Will it work on a large site without timing out? =

The export reads attachments in batches and streams results out as it goes, so memory stays bounded no matter how many items you have. You can also export a specific item range to keep each request small.

= Is the exported CSV safe to open in Excel? =

Yes. Cell values that begin with =, +, - or @ are neutralized, so a malicious value cannot run as a spreadsheet formula when the file is opened.

= My captions contain commas and quotes. Will that break the CSV? =

No. Every field is quoted and any quote inside a value is doubled, exactly as the CSV standard requires, so commas and quotes in your text are always safe.

What genuinely does cause trouble is a **line break** stored inside a caption or description. That is still valid CSV, but the record then spans several physical lines and plenty of importers read it as several broken records. "Keep every record on one line" under Show Advanced Options replaces those line breaks with a space, so one record is always one line.

If instead every column lands in a single cell when you double-click the file, that is Excel using your locale's list separator. Switch the CSV field delimiter to semicolon.

= My export shows "â€™" where an apostrophe should be. Can that be fixed? =

Yes — tick "Repair mis-encoded characters" under Show Advanced Options (it is on by default). Text that was saved as UTF-8 but later read back through an older character set turns "’" into "â€™", "–" into "â€“" and "é" into "Ã©". The repair puts them back.

It is deliberately cautious: it only rewrites text whose damage is provable, so accented, Cyrillic, Greek, Hebrew, Arabic, CJK and emoji characters are never touched. The damage lives in your database, so the repair applies to the export only — your posts are not modified.

= Why does one image with a "Used In (count)" of 3 produce only one line? =

Because the export lists media items, and each item gets one row with all three posts listed inside the "Used In (posts)" cell. Tick "One row per usage" and that item exports as three rows instead, one per referencing post, which is much easier to sort, filter and pivot. "Used In (count)" still shows the total on every row.

= Why does an image that is clearly used report a count of 0? =

Because the standard scan reads post content, and some references are not stored there. WooCommerce product galleries, ACF fields and page builders such as Elementor, Divi and WPBakery keep their references in custom fields instead.

Tick "Also scan custom fields and page-builder data" to include those. It reads every custom field on the site, so it takes noticeably longer — but it is the usual reason a visible image reports 0. Even then, treat "Unused" as a hint rather than proof: references in widgets, theme options or CSS are still not scanned.

= Does Export Media URLs make changes to the database? =

No. It only stores your last field selection as a per-user preference; it does not create tables or modify your content.

= Which PHP version do I need? =

This plugin works with PHP version 5.4 and greater. WordPress itself [recommends using PHP version 7.4 or greater](https://wordpress.org/about/requirements/).

== Screenshots ==

1. The Export screen — pick fields from collapsible groups with quick-select presets, and filter by media type
2. Results shown right in the dashboard, in a paginated table
3. The Summary dashboard — library stats plus one-click access to every maintenance tool
4. Duplicate Files — byte-for-byte identical files grouped for easy cleanup
5. Bulk Alt-Text editing — fix missing alt text across the library, with missing rows highlighted

== Changelog ==

= 3.2 =
* New - "Repair mis-encoded characters" fixes text stored as UTF-8 but written back through an older character set, so "â€™" exports as "’" again
* New - "Decode HTML entities" writes the real character behind &#8217;, &amp;, &nbsp; and friends
* New - optional "Replace typographic characters with plain ASCII" for systems that only accept straight quotes and hyphens
* New - CSV field delimiter is selectable: comma, semicolon (Excel in most European locales) or tab
* New - "Keep every record on one line" collapses line breaks stored inside captions and descriptions, so one record is always one CSV line
* New - "One row per usage": a media item used in 3 posts can now export as 3 rows instead of one combined row
* New - "Only include items that are used somewhere" leaves out items whose Used In count is 0
* New - optional deep scan of custom fields and page-builder data, finding references stored by WooCommerce galleries, ACF, Elementor, Divi and WPBakery
* New - "Used In (post ID)" and "Used In (post type)" columns
* New - developer filters emu_usage_skip_meta_keys and emu_usage_gallery_meta_keys
* Fix - a single invalid byte anywhere in the data no longer breaks the entire JSON download; every value is now guaranteed to be valid UTF-8
* Fix - control characters stored in meta fields no longer leak into the CSV

= 3.1 =
* New - "Used In" columns and "Unused" flag showing where each media item is referenced
* New - "Where Used" preset for quick usage reports
* New - "Parent Post" / "Parent URL" renamed "Uploaded To (Parent)" / "Uploaded To URL"
* New - notice warning that the usage scan can be slow on large sites
* New - developer filters emu_usage_extra_ids and emu_usage_post_types
* Improvement - the usage scan runs only when a usage column is selected
* Improvement - updated German, Spanish, French and Simplified Chinese translations

= 3.0 =
* New - Media Tools tab: storage summary, missing-file detector, duplicate detection, heavy-image flags, and bulk alt-text editing
* New - export MIME Type, Dimensions, Thumbnail/Medium/Large URLs, an "Alt Missing" flag, and Parent Post / Parent URL
* New - filter by media type (images, video, audio, documents, archives)
* New - filter by attachment status (attached vs. unattached/orphaned)
* New - JSON download format, alongside CSV and on-screen display
* New - field picker grouped into collapsible sections with quick-select presets; remembers your last selection
* New - on-screen results table now paginates with a "Results per page" selector
* Improvement - rewritten on a modular architecture, still PHP 5.4 compatible
* Improvement - exports run in batches and stream straight to the browser, leaving nothing behind in wp-content/uploads
* Improvement - removed the bundled select2 dependency; the interface now uses plain, dependency-free JavaScript
* Improvement - the whole interface is now translatable (POT included)
* Security - CSV formula-injection protection
* Security - hardened output escaping, nonce verification and input sanitization across all screens
* Fix - corrected the version mismatch and restored genuine PHP 5.4 compatibility

= 2.3.1 =
* Improvement - strengthened csv file name to prevent unauthorized discovery
* Compatibility - tested with WordPress 6.9.1

= 2.3 =
* Fixed - patched a security vulnerability

= 2.2 =
* Added - additional file size data field
* Improvement - preserves the previously selected values
* Compatibility - tested with wordpress 6.7.1

= 2.1 =
* Improvement - author filtering is simplified
* Compatibility - tested with wordpress 6.4.3

= 2.0 =
* Added - additional data fields (file name, caption, alt-text, description)
* Added - enables user to delete the file once downloaded
* Added - support for the translation
* Fixed - patched a security vulnerability
* Improvement - a few code refinements and validation checks
* Compatibility - tested with wordpress 6.4.1 & PHP 8.2.0

= 1.0 =
* initial release

== Upgrade Notice ==

= 3.2 =
Repairs mis-encoded characters ("â€™" back to "’"), keeps every CSV record on one line even when captions contain line breaks, adds a semicolon/tab delimiter for European Excel, and can export one row per usage. Also fixes a JSON download that could be corrupted by a single invalid byte.

= 3.1 =
Adds "Used In" columns and an "Unused" flag that show where each media item is actually referenced across your posts — not just where it was uploaded. Includes a Where Used preset and developer filters for page builders.

= 3.0 =
A major rewrite: new Media Tools tab (storage summary, missing-file detector, duplicate detection, heavy-image flags, bulk alt-text editing), media-type filtering, new export fields, JSON export, and streamed downloads. Still compatible back to PHP 5.4.
