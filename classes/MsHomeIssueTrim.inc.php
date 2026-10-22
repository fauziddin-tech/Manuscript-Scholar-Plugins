<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Pure helper that folds the "current issue" block of the home page: only the
 * first N articles stay visible and the others are marked "hidden" in the
 * page itself, together with a "Read more" button that unfolds them in place
 * (js/manuscriptScholar.js switches the button to "Show less"; without JavaScript
 * the button is a link to the issue page).
 * Nothing here touches OJS, so it can be tested on its own.
 */
class MsHomeIssueTrim {

	const SECTIONS_ID = 'ms-issue-sections';

	/**
	 * @param string $output rendered home page
	 * @param int $limit articles that stay visible
	 * @param string $labelMore text of the button while folded
	 * @param string $labelLess text of the button while unfolded
	 * @return string the page; unchanged when there is no current-issue block or
	 *   it holds no more than $limit articles
	 */
	public static function trim($output, $limit, $labelMore, $labelLess = 'Show less') {
		return preg_replace_callback('#<section class="current_issue"[^>]*>.*?</section>#s', function ($m) use ($limit, $labelMore, $labelLess) {
			return MsHomeIssueTrim::trimBlock($m[0], $limit, $labelMore, $labelLess);
		}, $output, 1);
	}

	/** Opening tag with an extra class and optional extra attributes */
	protected static function addToTag($tag, $class, $attributes = '') {
		if (preg_match('/\bclass="([^"]*)"/', $tag)) {
			$tag = preg_replace('/\bclass="([^"]*)"/', 'class="$1 ' . $class . '"', $tag, 1);
		} else {
			$tag = preg_replace('/^<(\w+)/', '<$1 class="' . $class . '"', $tag, 1);
		}
		return $attributes === '' ? $tag : preg_replace('/\s*>$/', ' ' . $attributes . '>', $tag, 1);
	}

	public static function trimBlock($block, $limit, $labelMore, $labelLess = 'Show less') {
		$items = self::articleItems($block);
		if ($limit < 1 || count($items) <= $limit) {
			return $block;
		}

		// Section boundaries: each section runs up to the start of the next one
		$starts = array();
		if (preg_match_all('#<div class="section"[^>]*>#', $block, $found, PREG_OFFSET_CAPTURE)) {
			foreach ($found[0] as $f) {
				$starts[] = $f[1];
			}
		}
		$edits = array(); // array(offset, old tag length, new tag)
		$edge = $limit - 1;
		// Articles after the limit are hidden; the last visible one is the edge
		foreach ($items as $index => $item) {
			if ($index < $edge) {
				continue;
			}
			preg_match('/^<li\b[^>]*>/', substr($block, $item[0], 200), $tag);
			$edits[] = array($item[0], strlen($tag[0]), $index === $edge
				? self::addToTag($tag[0], 'ms-edge')
				: self::addToTag($tag[0], 'ms-more', 'hidden'));
		}

		foreach ($starts as $k => $start) {
			$end = isset($starts[$k + 1]) ? $starts[$k + 1] : strlen($block);
			$inside = array();
			foreach ($items as $index => $item) {
				if ($item[0] > $start && $item[0] < $end) {
					$inside[] = $index;
				}
			}
			if (!$inside) {
				continue;
			}
			preg_match('/^<div class="section"[^>]*>/', substr($block, $start, 200), $tag);
			if (min($inside) >= $limit) {
				$edits[] = array($start, strlen($tag[0]), self::addToTag($tag[0], 'ms-more', 'hidden'));
			} elseif (in_array($edge, $inside, true)) {
				$edits[] = array($start, strlen($tag[0]), self::addToTag($tag[0], 'ms-edge-section'));
			}
		}

		// The block itself starts folded; the sections wrapper gets an id for aria-controls
		preg_match('/^<section\b[^>]*>/', $block, $sectionTag);
		$edits[] = array(0, strlen($sectionTag[0]), self::addToTag($sectionTag[0], 'ms-collapsed'));
		if (preg_match('/<div class="sections"([^>]*)>/', $block, $wrapper, PREG_OFFSET_CAPTURE) && strpos($wrapper[1][0], 'id=') === false) {
			$edits[] = array($wrapper[0][1], strlen($wrapper[0][0]), '<div class="sections" id="' . self::SECTIONS_ID . '"' . $wrapper[1][0] . '>');
		}

		usort($edits, function ($a, $b) {
			return $b[0] - $a[0];
		});
		$done = array();
		foreach ($edits as $edit) {
			if (isset($done[$edit[0]])) {
				continue;
			}
			$done[$edit[0]] = true;
			$block = substr($block, 0, $edit[0]) . $edit[2] . substr($block, $edit[0] + $edit[1]);
		}

		$e = function ($value) {
			return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
		};
		$href = preg_match('#href="([^"]*/issue/view/[^"]+)"#', $block, $url) ? $url[1] : '#' . self::SECTIONS_ID;
		$button = '<div class="ms-readmore"><a class="ms-readmore__button" href="' . $href . '" role="button" aria-expanded="false"'
			. ' aria-controls="' . self::SECTIONS_ID . '" data-more="' . $e($labelMore) . '" data-less="' . $e($labelLess) . '">'
			. $e($labelMore) . '</a></div>';
		// Before the "View All Issues" link when there is one, else at the end of the block
		$count = 0;
		$result = preg_replace_callback('#<a\s[^>]*class="read_more"#', function ($m) use ($button) {
			return $button . $m[0];
		}, $block, 1, $count);
		if ($count === 1) {
			return $result;
		}
		return preg_replace_callback('#</section>\s*$#', function ($m) use ($button) {
			return $button . $m[0];
		}, $block, 1);
	}

	/**
	 * Start and end offsets of every outer <li> that holds an article summary.
	 * Nested <li> (galley buttons) are matched by depth.
	 *
	 * @return array list of array(start, end)
	 */
	public static function articleItems($html) {
		$items = array();
		if (!preg_match_all('#<li\b|</li>#', $html, $tokens, PREG_OFFSET_CAPTURE)) {
			return $items;
		}
		$depth = 0;
		$start = 0;
		foreach ($tokens[0] as $token) {
			if ($token[0] === '</li>') {
				if ($depth > 0 && --$depth === 0) {
					$end = $token[1] + 5;
					if (strpos(substr($html, $start, $end - $start), 'obj_article_summary') !== false) {
						$items[] = array($start, $end);
					}
				}
			} else {
				if ($depth++ === 0) {
					$start = $token[1];
				}
			}
		}
		return $items;
	}
}
