=== Export Media URLs ===
Contributors: Atlas_Gondal, waqasgondal
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=YWT3BFURG6SGS&source=url
Tags: export, media, urls, csv, json
Requires at least: 3.6
Tested up to: 7.0
Stable tag: 3.1
Requires PHP: 5.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Extract every Media Library URL with title, size, dimensions, alt text and more. Filter by media type, export to CSV/JSON, and audit your library.

== Description ==

The fastest way to pull a complete inventory of your WordPress Media Library. Export every attachment's details — pick exactly the columns you want from grouped, collapsible field sections with quick-select presets — then download a CSV or JSON file, or display the results in a paginated table right inside the dashboard. A separate **Media Tools** tab helps you audit and clean up the library. Invaluable for migrations, SEO and accessibility audits, and library cleanup.

= Media Tools =

Each tool lives on its own sub-tab and processes the library in pages (100, 250, 500, 1000 or All per page), so the scans stay fast and light even on libraries with thousands of items.

* **Storage summary** — attachment counts per media type
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
* Used In (count), Used In (posts) and Used In (URLs) — every post that actually references the item
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

For every media item you can export the ID, Title, File Name, URL, File Size, MIME Type, Dimensions, sized-image URLs (thumbnail/medium/large), Caption, Alt Text, an "Alt Missing" flag, Description, Parent Post and Parent URL, and the upload date. Choose exactly the columns you want using the grouped checkboxes and quick-select presets.

= Can I export to JSON as well as CSV? =

Yes. You can download the data as a CSV file, download it as JSON, or display it in a paginated table right inside the dashboard.

= What is the difference between "Uploaded To (Parent)" and "Used In"? =

**Uploaded To (Parent)** is WordPress's own `post_parent` value, it records the single post that was open in the editor when the file was *uploaded* (or nothing, for files added from Dashboard → Media). It never changes when you later insert the image elsewhere. **Used In** is computed fresh by scanning your posts and pages for every place the item is actually referenced, featured images, editor content, galleries and pasted URLs, so one item can list several posts, exactly matching where it is used today.

= Can I find media that isn't used anywhere? =

Two ways, and they mean different things. The **"Attachment Status → Unattached"** filter lists media whose `post_parent` is 0 (typically anything uploaded from Dashboard → Media) but such a file may still be used in a post. For real usage, add the **"Used In (count)"** and **"Unused"** fields (or the **Where Used** / **Cleanup** preset): "Unused" is flagged only when the item is not referenced by any scanned post. Please treat it as a strong hint, not proof, references stored by page builders, custom fields, widgets, sliders or CSS are not scanned, so always confirm before deleting anything.

= Can I find images missing alt text? =

Yes. Add the "Alt Missing" field (or use the SEO / Accessibility preset). Any image with no alt text is flagged, so you can fix accessibility and SEO gaps quickly.

= Will it work on a large site without timing out? =

The export reads attachments in batches and streams results out as it goes, so memory stays bounded no matter how many items you have. You can also export a specific item range to keep each request small.

= Is the exported CSV safe to open in Excel? =

Yes. Cell values are neutralized, so a malicious value cannot run as a spreadsheet formula when the file is opened.

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

= 3.1 =
Adds "Used In" columns and an "Unused" flag that show where each media item is actually referenced across your posts, not just where it was uploaded. Also includes a Where Used preset and developer filters for page builders.

* New - "Used In" columns and "Unused" flag showing where each media item is referenced
* New - "Where Used" preset for quick usage reports
* New - "Parent Post" / "Parent URL" renamed "Uploaded To (Parent)" / "Uploaded To URL"
* New - notice warning that the usage scan can be slow on large sites
* New - developer filters emu_usage_extra_ids and emu_usage_post_types
* Improvement - the usage scan runs only when a usage column is selected
* Improvement - updated German, Spanish, French and Simplified Chinese translations