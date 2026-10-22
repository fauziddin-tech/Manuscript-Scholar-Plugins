<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Pure helpers for the "Issue Coverage" box (authors and countries of an
 * issue). Nothing here touches OJS, so it can be tested on its own.
 */
class IssueCoverage {

	/**
	 * Count unique authors and authors per country.
	 *
	 * @param array $records list of array('key' => string, 'country' => string)
	 * @return array array('authors' => int, 'countries' => array(CODE => count))
	 *   Countries are sorted by count (high to low), then by code. Authors
	 *   without a valid two-letter country are counted as authors only.
	 */
	public static function summarize(array $records) {
		$seen = array();
		$countries = array();
		foreach ($records as $record) {
			$key = isset($record['key']) ? (string) $record['key'] : '';
			if ($key === '' || isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$code = strtoupper(trim(isset($record['country']) ? (string) $record['country'] : ''));
			if (preg_match('/^[A-Z]{2}$/', $code)) {
				$countries[$code] = (isset($countries[$code]) ? $countries[$code] : 0) + 1;
			}
		}
		uksort($countries, function ($a, $b) use ($countries) {
			return $countries[$a] === $countries[$b] ? strcmp($a, $b) : $countries[$b] - $countries[$a];
		});
		return array('authors' => count($seen), 'countries' => $countries);
	}

	/**
	 * Authors from outside the journal's home country.
	 *
	 * @param array $summary result of summarize()
	 * @param string $homeCode ISO alpha-2 code of the home country; empty or invalid
	 *   gives null (the figure is not shown)
	 * @return array|null array('foreign' => int, 'known' => int, 'percent' => int) where
	 *   'known' counts the authors with a valid country; null when there are none
	 */
	public static function foreignShare(array $summary, $homeCode) {
		$home = strtoupper(trim((string) $homeCode));
		if (!preg_match('/^[A-Z]{2}$/', $home) || empty($summary['countries'])) {
			return null;
		}
		$known = 0;
		$foreign = 0;
		foreach ($summary['countries'] as $code => $count) {
			$known += (int) $count;
			if ($code !== $home) {
				$foreign += (int) $count;
			}
		}
		if ($known < 1) {
			return null;
		}
		$percent = (int) round(100 * $foreign / $known);
		if ($foreign > 0 && $percent < 1) {
			$percent = 1;
		}
		return array('foreign' => $foreign, 'known' => $known, 'percent' => $percent);
	}

	/**
	 * Number of author bylines (one per article) whose country is empty or not
	 * a two-letter code; these authors count as authors but add no flag.
	 */
	public static function countWithoutCountry(array $records) {
		$count = 0;
		foreach ($records as $record) {
			$code = strtoupper(trim(isset($record['country']) ? (string) $record['country'] : ''));
			if (!preg_match('/^[A-Z]{2}$/', $code)) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Number of pages of an article from its page field: "863-875" gives 13,
	 * "12" gives 1, "1-5, 9-10" gives 7. Letters (e12-e20) are ignored; a
	 * field without numbers, or whose range runs backwards, gives 0.
	 */
	public static function pageCount($pages) {
		$total = 0;
		foreach (preg_split('/[,;]/', (string) $pages) as $part) {
			if (!preg_match_all('/\d+/', $part, $numbers)) {
				continue;
			}
			$numbers = $numbers[0];
			if (count($numbers) === 1) {
				$total += 1;
			} else {
				$start = (int) $numbers[0];
				$end = (int) $numbers[1];
				$total += $end >= $start ? $end - $start + 1 : 0;
			}
		}
		return $total;
	}

	/**
	 * Sum of pageCount() over the page fields of the articles of an issue.
	 *
	 * @param array $pagesList list of page field strings
	 */
	public static function totalPages(array $pagesList) {
		$total = 0;
		foreach ($pagesList as $pages) {
			$total += self::pageCount($pages);
		}
		return $total;
	}

	/**
	 * Stable identity for one author across several articles: the same name
	 * with the same email. Different names that share an email (one address
	 * entered for several co-authors) stay separate authors, so each of them
	 * keeps his or her own country. $affiliation is accepted for older
	 * callers and not used.
	 */
	public static function authorKey($email, $name, $affiliation = '') {
		$email = mb_strtolower(trim((string) $email));
		$name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $name)));
		if ($name === '' && $email === '') {
			return '';
		}
		return 'n:' . $name . '|e:' . $email;
	}

	/**
	 * Names of the author bylines without a valid country (for diagnostics).
	 *
	 * @return string[] at most $limit distinct names
	 */
	public static function namesWithoutCountry(array $records, $limit = 5) {
		$names = array();
		foreach ($records as $record) {
			$code = strtoupper(trim(isset($record['country']) ? (string) $record['country'] : ''));
			$name = trim(isset($record['name']) ? (string) $record['name'] : '');
			if (!preg_match('/^[A-Z]{2}$/', $code) && $name !== '' && !in_array($name, $names, true)) {
				$names[] = $name;
				if (count($names) >= $limit) {
					break;
				}
			}
		}
		return $names;
	}

	/**
	 * Build the coverage box.
	 *
	 * @param int $articleCount
	 * @param array $summary result of summarize()
	 * @param array $names CODE => localized country name
	 * @param callable $flagUrl CODE => URL string, or '' when no flag exists
	 * @param array $labels 'total' => string, 'coverage' => string (already translated), optional
	 *   'pages' => string (shown beside the total; leave out or empty to hide),
	 *   'foreign' => string (share of authors from abroad, shown under the coverage line; optional)
	 * @return string HTML, or '' when there is nothing to show
	 */
	public static function render($articleCount, array $summary, array $names, $flagUrl, array $labels) {
		if ($articleCount < 1) {
			return '';
		}
		$e = function ($value) {
			return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
		};

		$html = '<div class="ms-issue-coverage">';
		$html .= '<p class="ms-issue-coverage__total">' . $e($labels['total'])
			. (!empty($labels['pages']) ? ' <span class="ms-issue-coverage__sep" aria-hidden="true">|</span> ' . $e($labels['pages']) : '')
			. '</p>';
		if (!empty($summary['countries'])) {
			$html .= '<p class="ms-issue-coverage__summary">' . $e($labels['coverage']) . '</p>';
			if (!empty($labels['foreign'])) {
				$html .= '<p class="ms-issue-coverage__foreign">' . $e($labels['foreign']) . '</p>';
			}
			$html .= '<ul class="ms-issue-coverage__countries">';
			foreach ($summary['countries'] as $code => $count) {
				$name = isset($names[$code]) ? $names[$code] : $code;
				$url = call_user_func($flagUrl, $code);
				$html .= '<li>';
				if ($url !== '') {
					$html .= '<img class="ms-flag" src="' . $e($url) . '" alt="" width="28" height="21" loading="lazy"> ';
				}
				$html .= '<span class="ms-country">' . $e($name) . '</span> ';
				$html .= '<span class="ms-count">(' . (int) $count . ')</span></li>';
			}
			$html .= '</ul>';
		}
		return $html . '</div>';
	}

	/**
	 * Insert the box into the issue heading, right after the cover image, so
	 * it sits beside the cover (the theme's CSS lays the heading out as two
	 * columns). Falls back to just before the article list, then to the top
	 * of the table of contents. Returns the output unchanged when it holds no
	 * table of contents.
	 */
	public static function inject($output, $html) {
		if ($html === '' || strpos($output, 'obj_issue_toc') === false) {
			return $output;
		}

		// 1. Inside the heading, before its description, DOI or published date
		if (preg_match('/<div\s+class="heading"/', $output, $heading, PREG_OFFSET_CAPTURE)) {
			$start = $heading[0][1];
			$limit = strlen($output);
			if (preg_match('/<div\s+class="sections"/', $output, $sections, PREG_OFFSET_CAPTURE, $start)) {
				$limit = $sections[0][1];
			}
			if (preg_match('/<div\s+class="(?:description|pub_id|published)\b/', $output, $anchor, PREG_OFFSET_CAPTURE, $start)
				&& $anchor[0][1] < $limit) {
				return substr($output, 0, $anchor[0][1]) . $html . substr($output, $anchor[0][1]);
			}
		}

		// 2. Before the article list
		$count = 0;
		$result = preg_replace_callback('/<div\s+class="sections"/', function ($m) use ($html) {
			return $html . $m[0];
		}, $output, 1, $count);
		if ($count === 1) {
			return $result;
		}

		// 3. Top of the table of contents
		return preg_replace_callback('/<div\s+class="obj_issue_toc"[^>]*>/', function ($m) use ($html) {
			return $m[0] . $html;
		}, $output, 1);
	}
}
