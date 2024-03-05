=== Full-Text Search ===
Contributors: ishitaka
Tags: full-text search,full-text,search,fulltext,mroonga
Requires at least: 5.5
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 2.14.4
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

= 2.14.4 =

* Fixed compatibility issues with other plugins.

= 2.14.3 =

* Supported WordPress version 6.5 and MySQL version 8.3.
* Updated PDF Parser library to 2.9.0.

= 2.14.2 =

* Updated PDF Parser library to 2.8.0.

= 2.14.1 =

* Fixed an error when extracting text from PDF files.

= 2.14.0 =

* Added option to use mark.js to highlight search keywords.
* Adhered WordPress coding standards 3.0.1.

= 2.13.0 =

* Supported WordPress version 6.4.
* Updated WordPress version requirements to 5.5.

= 2.12.4 =

* Fixed a bug in highlighting search keywords.
* Changed the name of "Reusable Block" to "Synced Pattern".

= 2.12.3 =

* Fixed a bug that characters on the management screen were sometimes garbled.

= 2.12.2 =

* Updated PDF Parser library to 2.7.0.

= 2.12.1 =

* Replaced composer autoloader with Jetpack autoloader.

= 2.12.0 =

* Updated PDF Parser library to 2.4.0.
* Code refactoring to meet WordPress PHP Coding Standards.

--------

[See the previous changelogs here](https://xakuro.com/wordpress/full-text-search/#changelog)
