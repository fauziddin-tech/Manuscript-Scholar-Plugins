<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Pure helpers for the three homepage cards (latest, most read, most
 * downloaded). Nothing here touches OJS, so it can be tested on its own.
 */
class MsHomeHighlights {

	/**
	 * Read submission ids from a statistics result. Accepts rows as arrays or
	 * objects, using submission_id, submissionId or id, in the given order.
	 *
	 * @return int[]
	 */
	public static function idsFromStats($rows, $limit = 5) {
		$ids = array();
		if (!is_array($rows) && !($rows instanceof Traversable)) {
			return $ids;
		}
		foreach ($rows as $row) {
			$row = is_object($row) ? get_object_vars($row) : (array) $row;
			foreach (array('submission_id', 'submissionId', 'id') as $key) {
				if (isset($row[$key]) && (int) $row[$key] > 0) {
					$id = (int) $row[$key];
					if (!in_array($id, $ids, true)) {
						$ids[] = $id;
					}
					break;
				}
			}
			if (count($ids) >= $limit) {
				break;
			}
		}
		return $ids;
	}

	/**
	 * Usage total of each submission from a statistics result (rows with
	 * submission_id/submissionId/id and total/metric).
	 *
	 * @return array submission id => int total
	 */
	public static function totalsFromStats($rows) {
		$totals = array();
		if (!is_array($rows) && !($rows instanceof Traversable)) {
			return $totals;
		}
		foreach ($rows as $row) {
			$row = is_object($row) ? get_object_vars($row) : (array) $row;
			$id = 0;
			foreach (array('submission_id', 'submissionId', 'id') as $key) {
				if (isset($row[$key]) && (int) $row[$key] > 0) {
					$id = (int) $row[$key];
					break;
				}
			}
			foreach (array('total', 'metric') as $key) {
				if ($id && isset($row[$key]) && is_numeric($row[$key]) && !isset($totals[$id])) {
					$totals[$id] = (int) $row[$key];
				}
			}
		}
		return $totals;
	}

	/**
	 * @param array $cards list of array('title' => string, 'items' => array of array('title' => string, 'url' => string, optional 'meta' => string))
	 * @return string HTML, or '' when no card has items
	 */
	public static function render(array $cards) {
		$e = function ($value) {
			return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
		};
		$html = '';
		foreach ($cards as $card) {
			if (empty($card['items'])) {
				continue;
			}
			$html .= '<section class="ms-highlight"><h2>' . $e($card['title']) . '</h2><ul>';
			foreach ($card['items'] as $item) {
				$html .= '<li><a href="' . $e($item['url']) . '">' . $e($item['title']) . '</a>'
					. (isset($item['meta']) && $item['meta'] !== '' ? '<span class="ms-highlight__meta">' . $e($item['meta']) . '</span>' : '')
					. '</li>';
			}
			$html .= '</ul></section>';
		}
		return $html === '' ? '' : '<div class="ms-highlights"><div class="ms-highlights__grid">' . $html . '</div></div>';
	}

	/**
	 * Insert the cards before the footer. Returns the output unchanged when
	 * no footer marker is found.
	 */
	public static function inject($output, $html) {
		if ($html === '') {
			return $output;
		}
		$count = 0;
		$result = preg_replace_callback('/<div\s+class="pkp_structure_footer_wrapper"/', function ($m) use ($html) {
			return $html . $m[0];
		}, $output, 1, $count);
		if ($count === 1) {
			return $result;
		}
		return preg_replace_callback('/<footer\b/', function ($m) use ($html) {
			return $html . $m[0];
		}, $output, 1);
	}
}
