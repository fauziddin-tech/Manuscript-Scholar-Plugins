<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Pure helpers for the small statistics card under each issue of the
 * Archives page: number of authors, number of countries (with a few flags)
 * and number of articles (with the total number of pages under it). Nothing here touches OJS, so it can be tested on
 * its own.
 */
class MsArchiveStats {

	const MARKER = '</div><!-- .obj_issue_summary -->';

	/** Flags shown in the card; the rest is summarised as "+N" */
	const FLAG_LIMIT = 6;

	/**
	 * @param int $articleCount
	 * @param array $summary result of MsIssueCoverage::summarize()
	 * @param array $names CODE => localized country name
	 * @param callable $flagUrl CODE => URL, or '' when there is no flag
	 * @param array $labels 'authors', 'countries', 'articles', 'summary' (already translated),
	 *   optional 'pages' (the word shown after the total number of pages)
	 * @param int $totalPages pages of all articles; shown under the article count
	 *   when greater than zero and a 'pages' label is given
	 * @param array|null $foreign result of MsIssueCoverage::foreignShare(); shown under the
	 *   author count as "12% foreign" when a 'foreign' label (the word) is given
	 * @return string HTML, or '' when the issue has no articles
	 */
	public static function render($articleCount, array $summary, array $names, $flagUrl, array $labels, $totalPages = 0, $foreign = null) {
		if ($articleCount < 1) {
			return '';
		}
		$e = function ($value) {
			return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
		};
		$icon = function ($path) {
			return '<span class="ms-archive-stats__icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="' . $path . '"/></svg></span>';
		};
		$stat = function ($icon, $number, $label, $extra = '') use ($e) {
			return '<div class="ms-archive-stats__item">' . $icon . '<span class="ms-archive-stats__copy"><span class="ms-archive-stats__line">'
				. '<span class="ms-archive-stats__num">' . (int) $number . '</span> <span class="ms-archive-stats__label">' . $e($label) . '</span></span>' . $extra . '</span></div>';
		};

		$pagesLine = '';
		if ((int) $totalPages > 0 && !empty($labels['pages'])) {
			$pagesLine = '<span class="ms-archive-stats__line ms-archive-stats__pages"><span class="ms-archive-stats__num">' . (int) $totalPages
				. '</span> <span class="ms-archive-stats__label">' . $e($labels['pages']) . '</span></span>';
		}

		$foreignLine = '';
		if (is_array($foreign) && !empty($labels['foreign'])) {
			$foreignLine = '<span class="ms-archive-stats__line ms-archive-stats__foreign"><span class="ms-archive-stats__num">' . (int) $foreign['percent']
				. '%</span> <span class="ms-archive-stats__label">' . $e($labels['foreign']) . '</span></span>';
		}

		$countries = isset($summary['countries']) ? $summary['countries'] : array();
		$flags = '';
		if ($countries) {
			$flags = '<span class="ms-archive-stats__flags" aria-hidden="true">';
			$shown = 0;
			foreach ($countries as $code => $count) {
				if ($shown >= self::FLAG_LIMIT) {
					break;
				}
				$shown++;
				$name = isset($names[$code]) && $names[$code] !== '' ? $names[$code] : $code;
				$url = call_user_func($flagUrl, $code);
				$title = $e($name . ' (' . (int) $count . ')');
				$flags .= $url !== ''
					? '<img class="ms-flag" src="' . $e($url) . '" alt="" title="' . $title . '" width="22" height="16" loading="lazy">'
					: '<span class="ms-flag ms-flag--text" title="' . $title . '">' . $e($code) . '</span>';
			}
			if (count($countries) > $shown) {
				$flags .= '<span class="ms-archive-stats__more">+' . (count($countries) - $shown) . '</span>';
			}
			$flags .= '</span>';
		}

		return '<div class="ms-archive-stats" role="group" aria-label="' . $e($labels['summary']) . '">'
			. $stat($icon('M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'), $summary['authors'], $labels['authors'], $foreignLine)
			. $stat($icon('M3 12a9 9 0 1 0 18 0 9 9 0 0 0-18 0ZM3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18'), count($countries), $labels['countries'], $flags)
			. $stat($icon('M6 3h9l4 4v14H6zM14 3v5h5M9 12h7M9 16h7'), $articleCount, $labels['articles'], $pagesLine)
			. '</div>';
	}

	/**
	 * Put each card at the end of its issue block (right before the closing
	 * tag of .obj_issue_summary), where it sits beside the cover next to the
	 * title.
	 *
	 * @param string $output rendered Archives page
	 * @param array $cards list of HTML strings in the order of the issues on the page ('' = no card)
	 * @return string the page; unchanged when the number of issue blocks does not
	 *   match the number of issues (a custom template), or a card is already there
	 */
	public static function inject($output, array $cards) {
		$cards = array_values($cards);
		if (!$cards || strpos($output, 'class="ao-badge"') !== false) {
			return $output;
		}
		$positions = array();
		$from = 0;
		while (($pos = strpos($output, self::MARKER, $from)) !== false) {
			$positions[] = $pos;
			$from = $pos + strlen(self::MARKER);
		}
		if (count($positions) !== count($cards)) {
			return $output;
		}
		for ($i = count($positions) - 1; $i >= 0; $i--) {
			if ($cards[$i] !== '') {
				$output = substr($output, 0, $positions[$i]) . $cards[$i] . substr($output, $positions[$i]);
			}
		}
		return $output;
	}
}
