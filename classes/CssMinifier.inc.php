<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Conservative CSS minifier used when the theme package is built: drops
 * comments and collapses white space, leaving quoted strings untouched.
 * Spaces are removed only next to { } ; and after a colon inside a
 * declaration, never inside selectors, so the meaning of the stylesheet does
 * not change. Nothing here touches OJS, so it can be tested on its own.
 */
class CssMinifier {

	public static function minify($css) {
		$css = (string) $css;
		$out = '';
		$length = strlen($css);
		$quote = '';
		for ($i = 0; $i < $length; $i++) {
			$char = $css[$i];
			if ($quote !== '') {
				$out .= $char;
				if ($char === '\\' && $i + 1 < $length) {
					$out .= $css[++$i];
				} elseif ($char === $quote) {
					$quote = '';
				}
				continue;
			}
			if ($char === '"' || $char === "'") {
				$quote = $char;
				$out .= $char;
				continue;
			}
			if ($char === '/' && $i + 1 < $length && $css[$i + 1] === '*') {
				$end = strpos($css, '*/', $i + 2);
				$i = $end === false ? $length : $end + 1;
				// A comment between two words must still separate them
				$out .= ' ';
				continue;
			}
			if (ctype_space($char)) {
				if ($out !== '' && substr($out, -1) !== ' ') {
					$out .= ' ';
				}
				continue;
			}
			if ($char === '{' || $char === '}' || $char === ';') {
				$out = rtrim($out, ' ');
				if ($char === '}') {
					$out = rtrim($out, ';');
				}
				$out .= $char;
				// skip the white space that follows
				while ($i + 1 < $length && ctype_space($css[$i + 1])) {
					$i++;
				}
				continue;
			}
			$out .= $char;
		}
		return trim($out) . "\n";
	}
}
