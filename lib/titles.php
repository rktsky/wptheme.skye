<?php

namespace Rocketsky\Skye\Titles;

/**
 * Page titles
 */
function title() {
	if (is_home()) {
		if (get_option('page_for_posts', true)) {
			return get_the_title(get_option('page_for_posts', true));
		} else {
			return __('Latest Posts', 'skye');
		}
	} elseif (is_archive()) {
		return get_the_archive_title();
	} elseif (is_search()) {
		return sprintf(__('Search Results for %s', 'skye'), get_search_query());
	} elseif (is_404()) {
		return __('Not Found', 'skye');
	} else {
		return get_the_title();
	}
}
