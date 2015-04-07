<?php
/*
Plugin Name: CDN Cache Invalidator
Plugin URI: https://github.com/ordenull/wordpress-plugin-cdn-cache-invalidator
Description: A plugin to invalidate recently updated pages from CDNs. Currently
only Amazon Web Services CloudFront is supported. The posts that were modified
since the last flush will automatically be added to a list of candidate URLs,
and will be expanded to include their parents and any user hooked expansions.
Version: 0.5
Author: Stan Borbat (stan@borbat.com)
Author URI: http://stan.borbat.com
License: GPLv2 License
*/

/**
 * Include the Amazon Web Serviced SDK that was downloaded from:
 * @link https://github.com/aws/aws-sdk-php/releases/tag/2.7.25
 * and stripped for only CloudFront related components.
 */
require_once plugin_dir_path(__FILE__) . 'aws/aws-autoloader.php';
use Aws\CloudFront\CloudFrontClient;

add_action( 'admin_menu', 'cdn_cache_invalidator_menu' );

/**
 * The hook to add menu items to the WordPress CMS menu.
 */
function cdn_cache_invalidator_menu() {
  add_menu_page( 'Clear Cache', 'Clear Cache', 'edit_posts', 'cdn_cache_invalidator_clear', 'cdn_cache_invalidator_clear', 'dashicons-admin-site', 21);
  add_options_page( 'CloudFront Settings', 'CloudFront', 'manage_options', 'cdn_cache_invalidator_options', 'cdn_cache_invalidator_options' );
}

/**
 * Will add to a list of URLs that could be relevant to a $post that's
 * been passed. These URLs are to be scheduled for invalidation from
 * CDN cache.
 * @param array $queue <p>
 * The array of site relative URLs to be invalidated. They should all start with a slash /
 * </p>
 * @param WP_Post $post <p>
 * The post that needs to be invalidated from cache
 * </p>
 * @return the $queue with new URLs added as appropriate.
 */
function cdn_cache_invalidator_enqueue($queue, $post) {
  $full_link = get_permalink($post);
  $relative_link = parse_url($full_link, PHP_URL_PATH);
  $query = parse_url($full_link, PHP_URL_QUERY);
  if ($query) $relative_link += $query;

  $queue[] = $relative_link;
  if ($relative_link != '/')
    $queue[] = untrailingslashit($relative_link);

  if ( $post->post_parent != 0 ) {
    // Recurse to the post's parents
    $parent = get_post($post->post_parent);
    $queue[] = cdn_cache_invalidator_enqueue($queue, $parent);
  }

  // This filter allows custom code to add URLs to the queue. For example this could
  // be used to parse a post and add any of it's dependencies to the invalidation queue.
  $queue = apply_filters("cdn_cache_invalidator_expand_urls", $queue, $post);

  return $queue;
}

/**
 * The callback for the admin menu to configure the CloudFront access
 * keys and distribution ID.
 */
function cdn_cache_invalidator_options() {
  if ( !current_user_can( 'manage_options' ) ) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
  }
  // Get our options from the persistent store
  $access_key = get_option('cdn_cache_invalidator_access_key');
  if (!$access_key) $access_key = '';

  $access_secret = get_option('cdn_cache_invalidator_access_secret');
  if (!$access_secret) $access_secret = '';

  $distribution = get_option('cdn_cache_invalidator_distribution');
  if (!$distribution) $distribution = '';

  $domain = get_option('cdn_cache_invalidator_domain');
  if (!$domain) $domain = '';

  $last_flush = get_option('cdn_cache_invalidator_last_flush');
  if (!$last_flush) {
    $last_flush = time();
    update_option('cdn_cache_invalidator_last_flush', $last_flush);
  }

  if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['save-btn']) {
    $access_key = $_POST['access-key'];
    $access_secret = $_POST['access-secret'];
    $distribution = $_POST['distribution'];

    try {
      $client = CloudFrontClient::factory(array(
        'key' => $access_key,
        'secret' => $access_secret
      ));
      $result = $client->getDistribution(array(
        'Id' => $distribution,
      ));

      $domain = $result->get('DomainName');
      update_option('cdn_cache_invalidator_access_key', $access_key);
      update_option('cdn_cache_invalidator_access_secret', $access_secret);
      update_option('cdn_cache_invalidator_distribution', $distribution);
      update_option('cdn_cache_invalidator_domain', $domain);

      ?><div class="updated"><p><strong><?php _e('Settings Saved', 'menu-test'); ?></strong></p></div><?php

    } catch (Exception $e) {
      ?><div class="error"><p><strong><?php echo $e->getMessage(); ?></strong></p></div><?php
    }
  }

  ?>
  <div class="wrap">
    <h1>CloudFront Settings</h1>
    <form name="settings" method="post" action="">
      <table>
        <tr><td><?php _e("Access Key ID:", 'cdn-settings' ); ?></td><td><input type="text" name="access-key" value="<?php echo $access_key; ?>" size="40"></td></tr>
        <tr><td><?php _e("Secret Key:", 'cdn-settings' ); ?></td><td><input type="text" name="access-secret" value="<?php echo $access_secret; ?>" size="40"></td></tr>
        <tr><td><?php _e("Distribution:", 'cdn-settings' ); ?></td><td><input type="text" name="distribution" value="<?php echo $distribution; ?>" size="40"></td></tr>
        <?php if ($domain) { ?>
          <tr><td><?php _e("Domain name:", 'cdn-settings' ); ?></td><td><?php echo $domain; ?></td></tr>
        <?php } ?>
        <tr><td colspan="2" align="right"><input type="submit" name="save-btn" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" /></td><td></td></tr>
      </table>
    </form>
  </div>
<?php
}

/**
 * The callback for the editor's menu to clear the CDN cache after a
 * round of updates haves been completed.
 */
function cdn_cache_invalidator_clear() {
  if ( !current_user_can( 'edit_posts' ) ) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
  }

  $access_key = get_option('cdn_cache_invalidator_access_key');
  if (!$access_key) wp_die(__('This plugin is not configured yet'));
  $access_secret = get_option('cdn_cache_invalidator_access_secret');
  if (!$access_secret) wp_die(__('This plugin is not configured yet'));
  $distribution = get_option('cdn_cache_invalidator_distribution');
  if (!$distribution) wp_die(__('This plugin is not configured yet'));
  $domain = get_option('cdn_cache_invalidator_domain');
  if (!$domain) wp_die(__('This plugin is not configured yet'));
  $last_flush = get_option('cdn_cache_invalidator_last_flush');
  if (!$last_flush) wp_die(__('This plugin is not configured yet'));

  // Add all posts that have been modified since the last flush
  $update_queue = array();
  $update_queue = apply_filters("cdn_cache_invalidator_add_urls", $update_queue);
  $post_types = get_post_types();
  foreach ($post_types as $post_type_name) {
    $posts = get_posts( array(
      'post_type' => $post_type_name,
    ));
    foreach ($posts as $post) {
      $modified_date = strtotime($post->post_modified);
      if ($modified_date > $last_flush) {
        // Enqueue all of the post URLs and call any user filters
        $update_queue = cdn_cache_invalidator_enqueue($update_queue, $post);
      }
    }
  }

  // Sort and remove duplicate URLs from this list
  $update_queue = array_unique ($update_queue, SORT_STRING);

  if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['clear-btn']) {
    // The Clear Cache button has been clicked
    try {
      $update_queue = explode("\r\n", trim($_POST['invalidations']));
      $client = CloudFrontClient::factory(array(
        'key' => $access_key,
        'secret' => $access_secret
      ));

      $result = $client->createInvalidation(array(
        'DistributionId' => $distribution,
        'Paths' => array(
          'Quantity' => count($update_queue),
          'Items' => $update_queue,
        ),
        'CallerReference' => 'WordPress-'.$last_flush,
      ));
      update_option('cdn_cache_invalidator_last_flush', time());
      $update_queue = array();
      ?><div class="updated"><p><strong><?php _e('Cache cleared, please allow 20 minutes for processing.', 'menu-test'); ?></strong></p></div><?php
    } catch (Exception $e) {
      ?><div class="error"><p><strong><?php echo $e->getMessage(); ?></strong></p></div><?php
    }
  }
  ?>
  <div class="wrap">
    <h1>Clear CDN Cache</h1>
    <h2>Recently changed URLs</h2>
    <form name="clear" method="post" action="">
      <table>
        <tr><td><textarea rows="10" cols="100" name="invalidations"><?php
              foreach ($update_queue as $url) {
                echo $url . "\n";
              }
              ?></textarea></td></tr>
        <tr><td align="right"><input type="submit" name="clear-btn" class="button-primary" value="<?php esc_attr_e('Submit') ?>" /></td></tr>
      </table>
    </form>
  </div>
<?php
}
?>
