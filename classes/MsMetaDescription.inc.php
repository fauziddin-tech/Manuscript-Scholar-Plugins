<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * <meta name="description"> for pages that have none (search engines show
 * it under the page title). Nothing here touches OJS, so it can be tested
 * on its own.
 */
class MsMetaDescription {

	const LIMIT = 160;

	/**
	 * Plain text from HTML: tags removed, entities decoded, white space
	 * collapsed, cut at a word boundary to at most $limit characters ("…"
	 * added when cut).
	 */
	public static function text($html, $limit = self::LIMIT) {
		$text = html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/i', ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
		if (mb_strlen($text, 'UTF-8') <= $limit) {
			return $text;
		}
		$cut = mb_substr($text, 0, $limit - 1, 'UTF-8');
		$space = mb_strrpos($cut, ' ', 0, 'UTF-8');
		if ($space !== false && $space > $limit / 2) {
			$cut = mb_substr($cut, 0, $space, 'UTF-8');
		}
		return rtrim($cut, " ,;:.-") . '…';
	}

	/**
	 * First non-empty text among $parts, each turned into plain text; parts
	 * are joined when the first one is short (a title), e.g. an issue title
	 * followed by the journal description.
	 *
	 * @param string[] $parts HTML or text, most specific first
	 */
	public static function compose(array $parts, $limit = self::LIMIT) {
		$texts = array();
		foreach ($parts as $part) {
			$text = self::text($part, 100000);
			if ($text !== '') {
				$texts[] = $text;
			}
		}
		return self::text(implode('. ', array_map(function ($t) { return rtrim($t, '. '); }, $texts)), $limit);
	}

	/**
	 * Add the tag before </head> when the page has no description yet.
	 */
	public static function inject($output, $content) {
		$content = (string) $content;
		if ($content === '' || stripos($output, '</head>') === false
			|| preg_match('/<meta\b[^>]*\bname\s*=\s*["\']description["\']/i', $output)) {
			return $output;
		}
		$tag = '<meta name="description" content="' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '">' . "\n";
		$pos = stripos($output, '</head>');
		return substr($output, 0, $pos) . $tag . substr($output, $pos);
	}
}
