=== PropertyHive Demo Data ===
Contributors: PropertyHive,BIOSTALL
Tags: propertyhive, property hive, property, real estate, software, estate agents, estate agent, demo data
Requires at least: 3.8
Tested up to: 6.6.2
Stable tag: trunk
Version: 2.0.1
Homepage: https://wp-property-hive.com/addons/demo-data/

This add on for Property Hive adds the ability to create and remove a set of demo data

== Description ==

This add on for Property Hive adds the ability to create and remove a set of demo data

== Installation ==

= Manual installation =

The manual installation method involves downloading the Property Hive Demo Data Add-on plugin and uploading it to your webserver via your favourite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Updating should work like a charm; as always though, ensure you backup your site just in case.

== Changelog ==

= 2.0.1 =
* WP CLI integration so demo data can be generated on the command line using: create-demo-data
* Run instructional text through __() so 'Property Hive' is translated by White Label add on
* Declared support for WordPress 6.6.2

= 2.0.0 =
* Overhaul UI
* Added filters allowing customisation of number of records, property features and photos
* Optimisation to creationg script only querying negs and offices once
* Remove dependency on lipsum API and build own
* Prevent delete button being clicked twice
* Better AJAX request error handling
* Declared support for WordPress 6.5.3

= 1.0.3 =
* Ensure property availabilities set are relevant to department of the property in question
* Added new 'noshow' viewing status
* Declared support for WordPress 5.8.3

= 1.0.2 =
* Ensure a rent frequency is set on tenancy records
* Declared support for WordPress 5.7.2

= 1.0.1 =
* Ensure _price_actual meta key set when inserting properties. This resulted in issues with properties not appearing on frontend
* Declared support for WordPress 5.7.1

= 1.0.0 =
* First working release of the add on