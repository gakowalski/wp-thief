# wp-thief

## About

This is a simple, **proof-of-concept** script to download Wordpress plugins from directory listings and put them in clear hierarchy of folders.

## Disclaimer

Author of this software does not endorse any form of unethical, immoral activity. Pay people for their hard work and obey the law. This software just shows how easy it is to legally amass large number of otherwise paid plugins *without paying for them*.

## Description

 You want to be a Wordpress plugin *thief*? ;-) Wordpress plugins adhere to the same licence that Wordpress does: GPL. That means that anyone can request a fee for distributing the plugin's code but if you found that code anywhere then it is legal for you to posses this code, distribute it, modify and even request payment for making a copy of it and distributing it. When you buy plugin from its developer, you pay for making a copy of the plugin and probably you buy some **time-limited support** and **access to new versions** (often via native Wordpress update mechanisms). You're **not** paying for the plugin license.

So where you can legally find pro / premium versions of plugins? Just try this in Google:

[intitle:"wp-content" "zip" intitle:index.of -intitle:uploads "pro"](https://www.google.com/search?q=intitle:%22wp-content%22+%22zip%22+intitle:index.of+-intitle:uploads+%22pro%22)

You'll find a lot of directory listings from many random sites containing ZIP files with free and "full" versions of popular plugins. People put these files via FTP to install them by one of WP alternative mechanisms. Why they don't remove them after that? Who knows. ZIP files are in the open. You just have to download it.

What this script does is that it takes URL of directory listing, scrapes all links to ZIP files, downloads those files and scans their contents for plugin name and plugin version. Then it puts them in a folder organized in specific hierarchy:

1. Download folder contains subfolder with plugin name identifier
2. Each plugin-name folder contains subfolders with different downloaded versions of plugin
3. Each version folder contains ZIP file downloaded from the directory listing

You can scan many directory listings and all ZIP files will be placed orderly in the download folder.

## Requirements

* Developed with PHP 8.0 but should work even with PHP 7.4
* Installed ZLIB extension to use gzinflate function

## Usage

Create subfolders:
```
mkdir downloads
mkdir not-recognized
mkdir not-recognized/bundles
```

Execute script from the command line:
```
php archive.php
```

You will be asked about URL to directory listing. Just paste one. Files will be downloaded, many messages will be put on the screen. You can also paste path to local directory or path to single file. In case of local directory script will search recursively for all ZIP files and process them.

Another method is to use URL as an argument to archive.php:
```
php archive.php https://www.example.com/wp-content/plugins/
php archive.php c:\mylocal_directory\
php archive.php /tmp/my_plugin.zip
```

Theoretically it is possible to call `archive.php` from the browser with `url` parameter like that:

```
https://www.example.com/archive.php?url=https://www.example.com/wp-content/plugins/
```

...but I have never tested it. I implemented it "just in case" but never needed to check it.

## Statistics for... "collectors"

There is also a small utility to get top ten popular plugins from your download folder. Popularity is measured by number of different version you managed to "collect".

```
$ php stats.php

Top 10 folders:
        elementor-pro: 27
        wordpress-seo-premium: 18
        rocket: 17
        digits: 13
        really-simple-ssl-pro: 13
        wordpress-seo: 12
        WPBakery Page Builder: 12
        WPBakeryVisualComposer: 12
        acf: 11
        gravityforms: 10
```

## Plugins saved but not processed

ZIP files that do not contain properly described WP plugins or that contain MORE than 1 plugin are being put into special folder for not recognized plugins and its subfolder for plugin bundles. At this point this script does not deal with plugin bundles.

## Ideas for script improvement

1. Unpack plugin bundles.
1. Processing unpacked plugins.
1. Support for TAR.GZ, GZIP and TAR archives as some people put plugins (especially plugin bundles) in those file formats.
1. Scan for malware.
1. Scan /wp-content/uploads/ subfolders for 'pro' and 'premium' ZIPs