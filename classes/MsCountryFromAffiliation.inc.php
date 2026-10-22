<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Guess an author's country from the last comma-separated part of the
 * affiliation ("Universitas Indonesia, Depok, Indonesia"), for authors whose
 * Country field is empty. Nothing here touches OJS, so it can be tested on
 * its own.
 */
class MsCountryFromAffiliation {

	/** Short forms that are not the official country name */
	const ALIASES = array(
		'ID' => array('republic of indonesia', 'republik indonesia', 'indonesian'),
		'MY' => array('malaysian'),
		'US' => array('usa', 'u.s.a', 'u.s.a.', 'us', 'u.s.', 'united states of america', 'amerika serikat'),
		'GB' => array('uk', 'u.k.', 'united kingdom', 'england', 'scotland', 'wales', 'great britain', 'inggris'),
		'AE' => array('uae', 'u.a.e.'),
		'KR' => array('korea', 'south korea', 'republic of korea', 'korea selatan'),
		'TR' => array('turkey', 'turkiye', 'türkiye'),
		'RU' => array('russia', 'russian federation'),
		'VN' => array('vietnam', 'viet nam'),
		'IR' => array('iran'),
		'SA' => array('saudi arabia', 'ksa', 'arab saudi'),
		'CN' => array('china', "people's republic of china", 'prc', 'tiongkok'),
		'TW' => array('taiwan'),
		'PH' => array('philippines', 'filipina'),
		'SG' => array('singapura'),
		'TH' => array('thailand'),
		'UZ' => array('uzbekistan', 'o\'zbekiston'),
	);

	/** Lower-case, trim spaces and trailing punctuation, collapse inner spaces. */
	public static function normalize($text) {
		$text = mb_strtolower(trim((string) $text), 'UTF-8');
		$text = preg_replace('/\s+/u', ' ', $text);
		return trim($text, " \t\n\r\0\x0B.;,)(");
	}

	/**
	 * Lookup table from normalized country names to codes.
	 *
	 * @param array[] $nameLists lists of CODE => name (for example the names in the
	 *   interface language and in English); a name listed first wins
	 * @return array normalized name => CODE
	 */
	public static function index(array $nameLists) {
		$index = array();
		foreach ($nameLists as $names) {
			foreach ($names as $code => $name) {
				$code = strtoupper((string) $code);
				$key = self::normalize($name);
				if ($key !== '' && preg_match('/^[A-Z]{2}$/', $code) && !isset($index[$key])) {
					$index[$key] = $code;
				}
			}
		}
		foreach (self::ALIASES as $code => $aliases) {
			foreach ($aliases as $alias) {
				$key = self::normalize($alias);
				if ($key !== '' && !isset($index[$key])) {
					$index[$key] = $code;
				}
			}
		}
		return $index;
	}

	/**
	 * The code of the country that closes the affiliation, or '' when the last
	 * part is not a known country name (a city or an institution).
	 *
	 * @param string $affiliation
	 * @param array $index result of index()
	 */
	public static function detect($affiliation, array $index) {
		$parts = preg_split('/[,;\x{2013}\x{2014}]|\s-\s/u', (string) $affiliation);
		$last = '';
		for ($i = count($parts) - 1; $i >= 0; $i--) {
			$last = self::normalize($parts[$i]);
			if ($last !== '') {
				break;
			}
		}
		return $last !== '' && isset($index[$last]) ? $index[$last] : '';
	}
}
