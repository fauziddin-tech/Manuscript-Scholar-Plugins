<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Pure helpers for the theme's two colours, chosen per journal under
 * Website > Appearance > Theme: the base colour (header band, buttons, links)
 * and the accent colour (the thin lines under headings, Manuscript's yellow by
 * default). They are checked, and turned into the LESS variables of the
 * Manuscript stylesheet and the CSS variables of manuscriptScholar.css.
 * Nothing here touches OJS, so it can be tested on its own.
 */
class ThemeColour {

	/** Manuscript's own header colour */
	const DEFAULT_BASE = '#4B7D92';

	/** Default of the Default Theme's "Base colour" option: read as "nothing chosen", as Manuscript does */
	const PARENT_DEFAULT_BASE = '#1E6292';

	/** Manuscript's accent colour */
	const DEFAULT_ACCENT = '#F7BC4A';

	/** Factor that gives the darker shade used for hover and borders (0 = black, 1 = unchanged) */
	const DARKEN = 0.77;

	/** Dark text Manuscript puts on a light base colour */
	const DARK_TEXT = 'rgba(0,0,0,0.84)';

	/**
	 * A colour as '#RRGGBB' (upper case). '#abc' is expanded; anything that
	 * is not a hex colour gives the default.
	 */
	public static function normalize($value, $default = self::DEFAULT_BASE) {
		$value = trim(is_scalar($value) ? (string) $value : '');
		if (preg_match('/^#([0-9a-fA-F]{3})$/', $value, $m)) {
			$value = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}
		return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtoupper($value) : $default;
	}

	/** The base colour of a saved option value; the Default Theme's default gives Manuscript's colour. */
	public static function base($value) {
		$hex = self::normalize($value);
		return $hex === self::PARENT_DEFAULT_BASE ? self::DEFAULT_BASE : $hex;
	}

	/** The accent colour of a saved option value. */
	public static function accent($value) {
		return self::normalize($value, self::DEFAULT_ACCENT);
	}

	/** @return int[] red, green, blue */
	public static function channels($hex) {
		$hex = self::normalize($hex);
		return array(hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
	}

	/** "75, 125, 146" for use inside rgba(...) */
	public static function rgb($hex) {
		return implode(', ', self::channels($hex));
	}

	/** A darker shade of the colour, '#RRGGBB'. */
	public static function darker($hex) {
		$out = '#';
		foreach (self::channels($hex) as $channel) {
			$out .= strtoupper(str_pad(dechex((int) round($channel * self::DARKEN)), 2, '0', STR_PAD_LEFT));
		}
		return $out;
	}

	/**
	 * Relative luminance (0 black, 1 white) as used for WCAG contrast.
	 */
	public static function luminance($hex) {
		$linear = array();
		foreach (self::channels($hex) as $channel) {
			$c = $channel / 255;
			$linear[] = $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
		}
		return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
	}

	/** Contrast ratio of white text on the colour (4.5 or more is comfortable to read). */
	public static function whiteContrast($hex) {
		return 1.05 / (self::luminance($hex) + 0.05);
	}

	/** Text colour on the base colour: white on a dark colour, Manuscript's dark text on a light one. */
	public static function textOn($hex) {
		return self::whiteContrast($hex) >= 3 ? '#ffffff' : self::DARK_TEXT;
	}

	/**
	 * Colour of links and outlined buttons on the white page: the base colour
	 * when it is dark enough to read on white, else Manuscript's own colour
	 * (a light yellow header must not give yellow links).
	 */
	public static function primary($base) {
		$base = self::normalize($base);
		return self::whiteContrast($base) >= 4.5 ? $base : self::DEFAULT_BASE;
	}

	/**
	 * LESS variables of the Manuscript stylesheet. They are always written, so
	 * whatever the parent theme added before is replaced by the same values.
	 */
	public static function lessVariables($base, $accent) {
		$base = self::normalize($base);
		$accent = self::normalize($accent, self::DEFAULT_ACCENT);
		return '@bg-base:' . $base . ';'
			. '@text-bg-base:' . self::textOn($base) . ';'
			. '@primary:' . self::primary($base) . ';'
			. '@accent:' . $accent . ';';
	}

	/**
	 * CSS that sets the colour variables of manuscriptScholar.css; '' for the
	 * default colours (the stylesheet already holds those values). `html:root`
	 * is more specific than the stylesheet's `:root`, so order does not matter.
	 */
	public static function rootCss($base, $accent) {
		$base = self::normalize($base);
		$accent = self::normalize($accent, self::DEFAULT_ACCENT);
		if ($base === self::DEFAULT_BASE && $accent === self::DEFAULT_ACCENT) {
			return '';
		}
		$primary = self::primary($base);
		return 'html:root{--ms-base:' . $base . ';--ms-on-base:' . self::textOn($base)
			. ';--ms-primary:' . $primary . ';--ms-primary-dark:' . self::darker($primary) . ';--ms-primary-rgb:' . self::rgb($primary)
			. ';--ms-accent:' . $accent . ';}';
	}
}
