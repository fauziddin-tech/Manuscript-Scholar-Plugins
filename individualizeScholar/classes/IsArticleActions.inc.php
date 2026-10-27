<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Generated from classes/MsArticleActions.inc.php by scripts/sync-individualize-scholar.sh: edit that file, not this one.
/**
 * Pure helpers for the action line under the authors of each article in a
 * list (issue table of contents, home page, search results):
 * "Show Abstract | Download PDF | DOI: 10.xxxx/... | Check in Google Scholar". The article's own
 * PDF button is removed and its link becomes "Download PDF".
 * Nothing here touches OJS, so it can be tested on its own.
 */
class IsArticleActions {

	const SEGMENT = '<div class="obj_article_summary"';

	/**
	 * Submission ids of the article summaries in a rendered page, read from the
	 * title link (<a id="article-123" ...>).
	 *
	 * @return int[]
	 */
	public static function idsFromOutput($output) {
		$ids = array();
		if (preg_match_all('/<a\s[^>]*\bid="article-(\d+)"/', $output, $m)) {
			foreach ($m[1] as $id) {
				$ids[(int) $id] = (int) $id;
			}
		}
		return array_values($ids);
	}

	/**
	 * Google Scholar search for an exact title.
	 */
	public static function scholarUrl($title) {
		return 'https://scholar.google.com/scholar?q=' . rawurlencode('+intitle:"' . trim(preg_replace('/\s+/u', ' ', $title)) . '"');
	}

	/**
	 * Add the action line (and a hidden abstract) right after the authors of
	 * every article summary whose id is a key of $data.
	 *
	 * @param string $output rendered page
	 * @param array $data id => array('title' => string, 'abstract' => html, 'url' => string, 'download' => string)
	 * @param array $labels 'show', 'hide', 'download', 'scholar'
	 */
	public static function inject($output, array $data, array $labels) {
		if (!$data || strpos($output, self::SEGMENT) === false) {
			return $output;
		}
		$parts = preg_split('/(?=' . preg_quote(self::SEGMENT, '/') . ')/', $output);
		foreach ($parts as $i => $part) {
			if (strpos($part, self::SEGMENT) !== 0 || !preg_match('/\bid="article-(\d+)"/', $part, $idMatch)) {
				continue;
			}
			$id = (int) $idMatch[1];
			if (!isset($data[$id])) {
				continue;
			}
			$item = $data[$id];
			$item['panel'] = 'ms-abstract-' . $id . '-' . $i;
			list($part, $pdfHref) = self::takePdfButton($part);
			if ($pdfHref !== '') {
				$item['download'] = $pdfHref;
			} elseif (empty($item['isPdf'])) {
				$item['download'] = '';
			}
			$html = self::render($item, $labels);
			// Only the authors line that opens this summary's meta block
			$parts[$i] = preg_replace_callback('#<div class="meta">\s*<div class="authors">.*?</div>#s', function ($m) use ($html) {
				return $m[0] . $html;
			}, $part, 1);
		}
		return implode('', $parts);
	}

	/**
	 * Remove the PDF button (<li> with a link whose class contains "pdf") from
	 * the galley list of one article summary and return its link. Other
	 * galley buttons stay; an empty list is removed.
	 *
	 * @return array array(string $segment, string $pdfHref) ('' when none)
	 */
	public static function takePdfButton($segment) {
		$href = '';
		$segment = preg_replace_callback('#<ul class="galleys_links[^"]*">(.*?)</ul>#s', function ($ul) use (&$href) {
			$inner = preg_replace_callback('#<li\b[^>]*>.*?</li>#s', function ($li) use (&$href) {
				if ($href === '' && preg_match('/<a\b([^>]*)>/', $li[0], $a)
					&& preg_match('/\bclass="[^"]*\bpdf\b[^"]*"/', $a[1])
					&& preg_match('/\bhref="([^"]*)"/', $a[1], $h)) {
					$href = html_entity_decode($h[1], ENT_QUOTES, 'UTF-8');
					return '';
				}
				return $li[0];
			}, $ul[1]);
			return strpos($inner, '<li') === false ? '' : str_replace($ul[1], $inner, $ul[0]);
		}, $segment, 1);
		return array($segment, $href);
	}

	/**
	 * A DOI as "10.xxxx/suffix" (a doi.org URL or "doi:" prefix is removed),
	 * or '' when the text is not a DOI.
	 */
	public static function cleanDoi($value) {
		$doi = trim((string) $value);
		$doi = preg_replace('#^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)#i', '', $doi);
		return preg_match('#^10\.\d{4,9}/\S+$#', $doi) ? $doi : '';
	}

	public static function doiUrl($doi) {
		return 'https://doi.org/' . str_replace('%2F', '/', rawurlencode($doi));
	}

	public static function render(array $item, array $labels) {
		$e = function ($value) {
			return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
		};
		$links = array();
		$panel = isset($item['panel']) ? (string) $item['panel'] : '';
		$controls = $panel !== '' ? ' aria-controls="' . $e($panel) . '"' : '';
		$hasAbstract = trim(strip_tags((string) $item['abstract'])) !== '';
		if ($hasAbstract) {
			$links[] = '<a href="' . $e($item['url']) . '" class="ms-show-abstract" role="button" aria-expanded="false"' . $controls
				. ' data-show="' . $e($labels['show']) . '" data-hide="' . $e($labels['hide']) . '">' . $e($labels['show']) . '</a>';
		}
		if (!empty($item['download'])) {
			$links[] = '<a href="' . $e($item['download']) . '" class="ms-download">' . $e($labels['download']) . '</a>';
		}
		$doi = isset($item['doi']) ? self::cleanDoi($item['doi']) : '';
		if ($doi !== '') {
			$links[] = '<a href="' . $e(self::doiUrl($doi)) . '" class="ms-doi" target="_blank" rel="noopener">' . $e('DOI: ' . $doi) . '</a>';
		}
		if (trim((string) $item['title']) !== '') {
			$links[] = '<a href="' . $e(self::scholarUrl($item['title'])) . '" class="ms-scholar" target="_blank" rel="noopener">' . $e($labels['scholar']) . '</a>';
		}
		if (!$links) {
			return '';
		}
		$html = '<p class="ms-article-actions">' . implode(' <span class="ms-sep">|</span> ', $links) . '</p>';
		if ($hasAbstract) {
			// The abstract is already sanitized HTML (stripUnsafeHtml)
			$html .= '<div class="ms-abstract"' . ($panel !== '' ? ' id="' . $e($panel) . '"' : '') . ' hidden>' . $item['abstract'] . '</div>';
		}
		return $html;
	}
}
