<?php

namespace Roots\Sage\Titles;

/**
 * Page titles
 */
function title() {
  if (is_home()) {
    if (get_option('page_for_posts', true)) {
      return get_the_title(get_option('page_for_posts', true));
    } else {
      return __('Die letzten Beiträge', THEME_TEXT_DOMAIN);
    }
  } elseif (is_archive()) {
    return get_the_archive_title();
  } elseif (is_search()) {
    return sprintf(__('Suchresultate für %s', THEME_TEXT_DOMAIN), get_search_query());
  } elseif (is_404()) {
    return __('Nicht gefunden.', THEME_TEXT_DOMAIN);
  } else {
    return get_the_title();
  }
}
