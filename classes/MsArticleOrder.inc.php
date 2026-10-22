<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Pure helper that puts the article summaries of a rendered page in order of
 * their first page number. Articles are grouped by list (neighbouring items
 * of the same <ul>), so sections keep their own place; an article without a
 * page number goes to the end of its list, in its original order.
 * Nothing here touches OJS, so it can be tested on its own.
 */
require_once(dirname(__FILE__) . '/MsHomeIssueTrim.inc.php');

class MsArticleOrder {

	/**
	 * @param string $output rendered page
	 * @return string the page with each article list ordered by start page
	 */
	public static function sortByPages($output) {
		$items = MsHomeIssueTrim::articleItems($output);
		if (count($items) < 2) {
			return $output;
		}

		// Items that follow each other with only whitespace between them form one list
		$groups = array();
		$current = array();
		foreach ($items as $item) {
			if ($current) {
				$last = end($current);
				if (trim(substr($output, $last[1], $item[0] - $last[1])) !== '') {
					$groups[] = $current;
					$current = array();
				}
			}
			$current[] = $item;
		}
		$groups[] = $current;

		for ($g = count($groups) - 1; $g >= 0; $g--) {
			$group = $groups[$g];
			if (count($group) < 2) {
				continue;
			}
			$entries = array();
			foreach ($group as $index => $item) {
				$html = substr($output, $item[0], $item[1] - $item[0]);
				$entries[] = array('page' => self::startPage($html), 'index' => $index, 'html' => $html);
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
			$sorted = '';
			foreach ($entries as $entry) {
				$sorted .= $entry['html'];
			}
			$start = $group[0][0];
			$end = $group[count($group) - 1][1];
			$output = substr($output, 0, $start) . $sorted . substr($output, $end);
		}
		return $output;
	}

	/**
	 * First number of the article's page range ("1029-1038" gives 1029,
	 * "e12" gives 12), or null when it has none.
	 */
	public static function startPage($articleHtml) {
		if (preg_match('#<div class="pages">\s*([^<]*)#', $articleHtml, $m) && preg_match('/\d+/', $m[1], $n)) {
			return (int) $n[0];
		}
		return null;
	}
}
