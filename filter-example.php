<?php
/**
 * A sample URL filter that would be defined within the theme's functions.php or similiar
 */

add_filter("cdn_cache_invalidator_expand_urls", "cdn_cache_invalidator_expand_urls_sesame", 10, 3);

function cdn_cache_invalidator_expand_urls_sesame($queue, $post) {
  global $wp_rewrite;
  $post_link = $wp_rewrite->get_extra_permastruct($post->post_type);
  $post_root_link = untrailingslashit(preg_replace('/(%[^%]+%)/i', '', $post_link));

  switch ($post->post_type) {
    case 'video':
      $queue[] = $post_root_link . '/?gid=' . $post->post_name;
      break;
    case 'audio':
      $queue[] = $post_root_link . '/?gid=' . $post->post_name;
      break;
    case 'games':
      $queue[] = $post_root_link . '/?gid=' . $post->post_name;
      break;
    case 'activities':
      $queue[] = $post_root_link . '/?gid=' . $post->post_name;
      break;
    default:
      break;
  }

  if ($post->post_type == 'games') {
    $template_dir = get_template_directory();
    $queue = cdn_cache_invalidator_expand_urls_sesame_add_all_files($queue, $template_dir . '/games/' . $post->post_name);
  }

  return $queue;
}

function cdn_cache_invalidator_expand_urls_sesame_add_all_files($queue, $path) {
  try {
    $home_path = get_home_path();
    $dir = new RecursiveDirectoryIterator($path);
    $itr = new RecursiveIteratorIterator($dir);
    // Ignore files that start with a period
    $ritr = new RegexIterator($itr, '/[^\.]$/i', RecursiveRegexIterator::GET_MATCH);
    foreach ($ritr as $fn => $obj) {
      // Ignore meta-files
      if (strpos($fn, '.DS_Store') !== false) {
        continue;
      }
      if (strpos($fn, '__MACOSX') !== false) {
        continue;
      }
      $url = '/' . str_replace($home_path, '', $fn);
      $queue[] = $url;
    }
  } catch (Exception $e) {
    // TODO: Display a warning
  }
  return $queue;
}
?>
