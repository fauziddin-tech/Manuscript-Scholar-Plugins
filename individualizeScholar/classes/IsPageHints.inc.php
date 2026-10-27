<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Loading hints for the images of a page rendered by the Individualize
 * theme: the header logo is fetched first, the issue covers of the Archives
 * page below the first one and the images and frames of the footer are
 * loaded only when scrolled near (visitor counters excepted). Nothing here
 * touches OJS, so it can be tested on its own.
 */
class IsPageHints {

	/** Sources that count visitors when loaded; they keep loading at once. */
	const COUNTER_PATTERN = '~counter|histats|clustrmaps|revolvermaps|amung\\.us|mapmyvisitors|analytics|pixel|track|/stats?\\b~i';

	/**
	 * fetchpriority="high" on the first desktop header logo, unless it has a
	 * fetchpriority or loading attribute of its own.
	 */
	public static function prioritizeLogo($output) {
		return preg_replace_callback('/<img\b([^>]*\bclass="[^"]*\bheader-desktop-logo\b[^"]*"[^>]*)>/i', function ($m) {
			if (preg_match('/\s(?:fetchpriority|loading)\s*=/i', $m[1])) {
				return $m[0];
			}
			return '<img fetchpriority="high"' . $m[1] . '>';
		}, $output, 1);
	}

	/**
	 * loading="lazy" on the issue covers of the Archives page (list and
	 * cover gallery), except the first one, which is on screen at once.
	 */
	public static function lazyCovers($output) {
		$seen = 0;
		return preg_replace_callback('/<img\b([^>]*\bclass="[^"]*\b(?:issue-summary-cover|issue-cover-summary-image)\b[^"]*"[^>]*)>/i', function ($m) use (&$seen) {
			$seen++;
			if ($seen === 1 || preg_match('/\sloading\s*=/i', $m[1])) {
				return $m[0];
			}
			return '<img loading="lazy" decoding="async"' . $m[1] . '>';
		}, $output);
	}

	/**
	 * loading="lazy" (and decoding="async" for images) on the <img> and
	 * <iframe> tags from the footer to the end of the page. Tags with their
	 * own loading or fetchpriority attribute and visitor counters (their
	 * count would drop) are kept. Unchanged when the page has no footer.
	 */
	public static function lazyFooter($output) {
		if (!preg_match('/<div\s+class="footer"/', $output, $m, PREG_OFFSET_CAPTURE)) {
			return $output;
		}
		$start = $m[0][1];
		$tail = preg_replace_callback('/<(img|iframe)\b([^>]*)>/i', function ($m) {
			$attrs = $m[2];
			if (preg_match('/\s(?:loading|fetchpriority)\s*=/i', $attrs)) {
				return $m[0];
			}
			if (preg_match('/\ssrc\s*=\s*["\']([^"\']*)/i', $attrs, $src) && preg_match(self::COUNTER_PATTERN, $src[1])) {
				return $m[0];
			}
			$hint = strtolower($m[1]) === 'img' ? ' loading="lazy" decoding="async"' : ' loading="lazy"';
			return '<' . $m[1] . $hint . $attrs . '>';
		}, substr($output, $start));
		return $tail === null ? $output : substr($output, 0, $start) . $tail;
	}
}
