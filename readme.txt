=== Full-Text Search ===
Contributors: ishitaka
Tags: full-text,search,fulltext,mroonga
Requires at least: 4.9
Tested up to: 6.1
Requires PHP: 7.1
Stable tag: 2.10.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Replaces site search with full-text search.

== Description ==

Replaces site search with full-text search.

Replace the site search from LIKE search to Japanese full-text search (MySQL + Ngram parser or Mroonga engine + TokenMecab parser). This will significantly improve search performance.

Search for pure strings (plain text) without HTML tags. This will prevent HTML tags from being searched.

The data (index) for searching is stored in a dedicated table. It does not rewrite existing table structures or post data (posts table).

Searches for text in PDF, Word (doc, docx), Excel, and PowerPoint files. Secured PDF file are currently not supported.

It supports WordPress multisite.

= Search string options =

* ```OR``` (uppercase letter) - Combine searches. Example: foo OR bar
* ```-``` - Exclude words from the search. Example: foo -bar
* ```""``` - Search for an exact match. Example: "foo bar"
* ```*``` - Search by wildcard. Mroonga only. Example: foo*
* ```()``` - Grouping. Mroonga only. Example: (foo OR bar) baz

= Operating environment =

Requires MySQL 5.6 or later, or Mroonga engine.

Mroonga engine is strongly recommended. InnoDB engine performs significantly worse with large amounts of data.

== Installation ==

1. Upload the `full-text-search` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins menu in WordPress.

== Screenshots ==

1. Settings screen.
2. Infomation screen.
3. Maintenance screen.
4. Attachment details screen.

== Changelog ==

= 2.10.0 =

* Added option to search the contents of shortcodes.
* Added option to search the contents of reusable blocks.
* Added option to search HTML tags.
* Updated PDF Parser library to 2.3.0.

= 2.9.4 =

* Updated PDF Parser library to 2.2.2.
* Added full_text_search_pdf_text filter.

= 2.9.3 =

* Changed default setting values.
* Added escaping to multiple translate texts for enhanced security.
* Code refactoring.

= 2.9.2 =

* Changed full-text search to exclude_from_search posts only.
* Fixed a bug that the text of attachments were not extracted when regenerating indexes.

= 2.9.0 =

* Added the ability to highlight search keywords on the search results page.
* Optimized SQL.

= 2.8.1 =

* Added a function to delete the search text of attachments.

= 2.8.0 =

* Improved performance.
* Changed to remove control characters such as spaces and line breaks from automatically extracted text in PDF.

= 1.0.0 =

* Initial release.
