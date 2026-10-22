<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Pure helper that adds the Dimensions citation badge (https://badge.dimensions.ai)
 * above the DOI line of an article page. The DOI is read from the rendered
 * page, so nothing here touches OJS and it can be tested on its own.
 * The badge is drawn in the visitor's browser by Dimensions' own script; it
 * is also shown with 0 citations (nothing is drawn when Dimensions does not
 * know the DOI at all).
 */
class DimensionsBadge {

	const SCRIPT = 'https://badge.dimensions.ai/badge.js';

	/** The DOI line of the article page (a div or section with the classes "item doi") */
	const DOI_BLOCK = '#<(div|section)\b[^>]*class="[^"]*\bitem\b[^"]*\bdoi\b[^"]*"[^>]*>.*?</\1>#s';

	/**
	 * DOI (10.xxxx/...) of the article, from the DOI line of the page.
	 *
	 * @return string '' when the page has no usable DOI line
	 */
	public static function doiFromOutput($output) {
		if (!preg_match(self::DOI_BLOCK, $output, $block)) {
			return '';
		}
		if (!preg_match('#href="[^"]*?doi\.org/([^"]+)"#i', $block[0], $m)) {
			return '';
		}
		$doi = rawurldecode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
		return preg_match('#^10\.\d{4,9}/\S+$#', $doi) ? $doi : '';
	}

	public static function render($doi) {
		$e = htmlspecialchars($doi, ENT_QUOTES, 'UTF-8');
		return '<div class="item ms-dimensions"><span class="__dimensions_badge_embed__" data-doi="' . $e . '"'
			. ' data-style="small_rectangle" data-legend="hover" data-hide-zero-citations="false"></span>'
			. '<script async src="' . self::SCRIPT . '" charset="utf-8"></script></div>';
	}

	/**
	 * Insert the badge right before the DOI line. Returns the page unchanged
	 * when there is no DOI line or a badge is already there.
	 */
	public static function inject($output) {
		if (strpos($output, '__dimensions_badge_embed__') !== false) {
			return $output;
		}
		$doi = self::doiFromOutput($output);
		if ($doi === '') {
			return $output;
		}
		$html = self::render($doi);
		return preg_replace_callback(self::DOI_BLOCK, function ($m) use ($html) {
			return $html . $m[0];
		}, $output, 1);
	}
}
