<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Choices of a "country" drop-down for the theme options: the full country
 * names (as on the author form), sorted, behind an empty choice. Nothing
 * here touches OJS, so it can be tested on its own.
 */
class MsCountryOptions {

	/**
	 * @param array $names CODE => country name (in the interface language)
	 * @param string $emptyLabel text of the first choice, which has the value ''
	 * @return array list of array('value' => CODE, 'label' => name); invalid codes
	 *   and blank names are left out; empty when there is no usable country
	 */
	public static function build(array $names, $emptyLabel) {
		$options = array();
		foreach ($names as $code => $name) {
			$code = strtoupper(trim((string) $code));
			$name = trim((string) $name);
			if (preg_match('/^[A-Z]{2}$/', $code) && $name !== '') {
				$options[$code] = $name;
			}
		}
		if (!$options) {
			return array();
		}
		$collator = class_exists('Collator') ? new \Collator('en') : null;
		uasort($options, function ($a, $b) use ($collator) {
			return $collator ? $collator->compare($a, $b) : strcmp(mb_strtolower($a, 'UTF-8'), mb_strtolower($b, 'UTF-8'));
		});
		$list = array(array('value' => '', 'label' => (string) $emptyLabel));
		foreach ($options as $code => $name) {
			$list[] = array('value' => $code, 'label' => $name);
		}
		return $list;
	}
}
