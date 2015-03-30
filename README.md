CDN Cache Invalidator
=====================
* Tags: cache, flush, expire, purge, invalidate, cloudfront, modified
* Requires at least: 3.5.1
* Tested up to: 4.1.1
* Stable tag: 4.1.1
* License: GPLv2
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

Flush recently modified posts from CloudFront cache.

## Description

A plugin to invalidate recently updated pages from CDNs. Currently
only Amazon Web Services CloudFront is supported. The posts that were modified
since the last flush will automatically be added to a list of candidate URLs,
and will be expanded to include their parents and any user hooked expansions.

## Installation

1. Upload `plugin-name.php` to the `/wp-content/plugins/cdn-cache-invalidator` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Sign in as the Administrator and go to Settings->CloudFront
4. Enter your AWS Key ID and it's secret.
5. Enter the CloudFront distribution ID. It's displayed in the ID column and starts with
   a capital letter E.
6. Click save settings. If everything worked, the distribution domain name should be now
   displayed underneath the distribution ID input box.

## Frequently asked questions

### How do I clear URLs from cache?

After plugin configuration a new menu item called "Clear Cache" with a globe icon
will appear. The URLs related to the recently changed pages and posts will be populated
in the text area. Alternatively, URLs can be added there manually as well. Clicking the
Submit button will create an invalidation request with CloudFront.

## Removal
1. Remove the plugin directory "/wp-content/plugins/cdn-cache-invalidator"
2. Clean the settings saved in the MySQL database:
   DELETE FROM wp_options WHERE option_name LIKE 'cdn_cache_invalidator_%';

## Screenshots

1. screenshot-1.png
2. screenshot-2.png

## Changelog
### Version 0.5 (29-03-2015)
* Initial release with AWS CloudFront support
