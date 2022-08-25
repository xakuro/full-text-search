=== Full-Text Search ===
Contributors: ishitaka
Tags: full-text,search,fulltext,mroonga
Requires at least: 4.9
Tested up to: 6.0
Requires PHP: 7.1
Stable tag: 2.8.0
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

= 2.8.0 =

* Improved performance.
* Changed to remove control characters such as spaces and line breaks from automatically extracted text in PDF.

= 2.7.2 =

* Fixed a bug in search other than the search page.

= 2.7.1 =

* Fixed a bug where database indexes were not regenerated.

= 2.7.0 =

* Improved performance.

= 2.6.1 =

* Changed not to remove the full_text_search_search_text custom field on uninstall.

= 2.6.0 =

* Added full_text_search_index_post filter hook.

= 2.5.1 =

* Fixed a bug that an error message was displayed when searching for an empty space.

= 2.5.0 =

* Added search string options.

= 2.4.1 =

* Fixed a bug in the search order.

= 2.4.0 =

* Added an option to display the search score on the search page.

= 2.3.0 =

* Added sort order option.

= 2.2.10 =

* Updated the PDF Parser library to 2.2.1.

= 2.2.9 =

* Updated the PDF Parser library to 2.2.0.

= 2.2.8 =

* Updated the PDF Parser library to 2.1.0.

= 2.2.7 =

* Fixed a bug in searching only attachments.

= 2.2.6 =

* Updated the PDF Parser library to 2.0.1.

= 2.2.5 =

* Fixed an empty search results screen warning.

= 2.2.4 =

* Updated the PDF Parser library to 1.1.0.

= 2.2.3 =

* Optimized SQL.

= 2.2.1 =

* Fixed omissions in the translated text.

= 2.2.0 =

* The setting page has been renewed.

= 2.1.0 =

* Added support for searching Excel and PowerPoint files.

= 2.0.0 =

* Added support for searching Word (doc, docx) files.

= 1.9.0 =

* Added the option to search for attachments only pdf.

= 1.8.0 =

* Added a search text (PDF text) item to the media file.
* Added a custom field (full_text_search_search_text) for full-text search.

= 1.7.0 =

* Added the function to display the number of characters of PDF text in the media list.

= 1.6.0 =

* Added support for searching PDF files.

= 1.4.0 =

* Added support for attachments.

= 1.3.0 =

* Added an option to enable / disable full-text search.

= 1.2.0 =

* Fixed a bug that InnoDB could not be searched correctly.

= 1.1.0 =

* Fixed a bug when uninstalling WordPress multisite.
* Added some filters. 

= 1.0.0 =

* Initial release.
