<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Loading hints for images in the rendered page: the header image is
 * fetched first, and the issue covers of the Archives page below the first
 * one are loaded only when scrolled near, like the images and frames of the
 * sidebar and the footer (visitor counters excepted). Nothing here touches OJS, so it
 * can be tested on its own.
 */
class ImageHints {

	/**
	 * Ask the browser to fetch the header (site name) image early. Only the
	 * first image inside a link marked "is_img" is changed, and only when it
	 * has no fetchpriority or loading attribute of its own.
	 */
	public static function prioritizeHeader($output) {
		return preg_replace_callback('/(<a\b[^>]*\bclass="[^"]*\bis_img\b[^"]*"[^>]*>\s*)<img\b([^>]*)>/i', function ($m) {
			if (preg_match('/\s(?:fetchpriority|loading)\s*=/i', $m[2])) {
				return $m[0];
			}
			return $m[1] . '<img fetchpriority="high"' . $m[2] . '>';
		}, $output, 1);
	}

	/**
	 * Add loading="lazy" to the issue covers of the Archives page, except
	 * the first one (it is on screen when the page opens).
	 */
	public static function lazyCovers($output) {
		$seen = 0;
		return preg_replace_callback('/(<a\b[^>]*\bclass="[^"]*\bcover\b[^"]*"[^>]*>\s*)<img\b([^>]*)>/i', function ($m) use (&$seen) {
			$seen++;
			if ($seen === 1 || preg_match('/\sloading\s*=/i', $m[2])) {
				return $m[0];
			}
			return $m[1] . '<img loading="lazy" decoding="async"' . $m[2] . '>';
		}, $output);
	}

	/** Sources that count visitors when loaded; they keep loading at once. */
	const COUNTER_PATTERN = '~counter|histats|clustrmaps|revolvermaps|amung\\.us|mapmyvisitors|analytics|pixel|track|/stats?\\b~i';

	/**
	 * Add loading="lazy" (and decoding="async" for images) to the <img> and
	 * <iframe> tags from the start of the sidebar to the end of the page
	 * (sidebar blocks, footer). Tags with their own loading or fetchpriority
	 * attribute, and visitor counters (their count would drop), are kept.
	 * Returns the page unchanged when it has no sidebar or footer marker.
	 */
	public static function lazySidebarAndFooter($output) {
		$start = false;
		foreach (array('class="pkp_structure_sidebar', 'class="pkp_structure_footer') as $marker) {
			$pos = strpos($output, $marker);
			if ($pos !== false && ($start === false || $pos < $start)) {
				$start = $pos;
			}
		}
		if ($start === false) {
			return $output;
		}
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
