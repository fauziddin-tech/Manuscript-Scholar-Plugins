<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Puts the articles of each section of an issue in order of their first page
 * number, before the table of contents is rendered. An article without a
 * page number goes to the end of its section, in its original order.
 * Nothing here touches OJS, so it can be tested on its own.
 */
class IsArticleOrder {

	/**
	 * First number of a page range ("1029-1038" gives 1029, "e12" gives 12),
	 * or null when there is none.
	 */
	public static function startPage($pages) {
		return is_scalar($pages) && preg_match('/\d+/', (string) $pages, $m) ? (int) $m[0] : null;
	}

	/**
	 * @param array $sections the `publishedSubmissions` template variable of
	 *   OJS 3.3: section id => array('title' => ..., 'articles' => Submission[])
	 * @param callable $pagesOf article => its page field
	 * @return array the same sections, each with its articles ordered by page
	 */
	public static function sortSections(array $sections, $pagesOf) {
		foreach ($sections as $key => $section) {
			if (!is_array($section) || empty($section['articles']) || !is_array($section['articles']) || count($section['articles']) < 2) {
				continue;
			}
			$entries = array();
			foreach (array_values($section['articles']) as $index => $article) {
				$entries[] = array('page' => self::startPage(call_user_func($pagesOf, $article)), 'index' => $index, 'article' => $article);
			}
			usort($entries, function ($a, $b) {
				if ($a['page'] === $b['page']) {
					return $a['index'] - $b['index'];
				}
				if ($a['page'] === null) {
					return 1;
				}
				if ($b['page'] === null) {
					return -1;
				}
				return $a['page'] < $b['page'] ? -1 : 1;
			});
			$sections[$key]['articles'] = array_map(function ($entry) {
				return $entry['article'];
			}, $entries);
		}
		return $sections;
	}
}
