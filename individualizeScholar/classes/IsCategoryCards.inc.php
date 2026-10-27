<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Generated from classes/MsCategoryCards.inc.php by scripts/sync-individualize-scholar.sh: edit that file, not this one.
/**
 * Category cards for the home page: one card per OJS category that has
 * published articles in the current issue, each opening a list of those
 * articles.
 * Reading the database and building the HTML are kept apart from OJS, so
 * both can be tested on their own.
 */
class IsCategoryCards {

	/**
	 * Id of the journal's current issue (OJS 3.3 `issues` table), or 0 when
	 * there is none.
	 */
	public static function currentIssueId(PDO $pdo, $contextId) {
		$statement = $pdo->prepare('SELECT issue_id FROM issues WHERE journal_id = ? AND current = 1 ORDER BY issue_id DESC LIMIT 1');
		$statement->execute(array((int) $contextId));
		return (int) $statement->fetchColumn();
	}

	/**
	 * Categories of a journal with their published articles (OJS 3.3 tables:
	 * categories, category_settings, publication_categories, publications,
	 * publication_settings, submissions).
	 *
	 * @param PDO $pdo
	 * @param int $contextId
	 * @param string[] $locales preferred locales, best first
	 * @param int $limit articles listed per category
	 * @param int|null $issueId only articles of this issue (the publication's `issueId` setting); null for all issues
	 * @return array list of array('id' => int, 'title' => string, 'count' => int, 'submissionIds' => int[]);
	 *   only categories that have at least one published article, in the journal's order
	 */
	public static function load(PDO $pdo, $contextId, array $locales, $limit, $issueId = null) {
		$titles = array();
		$statement = $pdo->prepare(
			'SELECT c.category_id, c.seq, cs.locale, cs.setting_value
			FROM categories c
			LEFT JOIN category_settings cs ON cs.category_id = c.category_id AND cs.setting_name = ?
			WHERE c.context_id = ?
			ORDER BY c.seq, c.category_id'
		);
		$statement->execute(array('title', (int) $contextId));
		$order = array();
		foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$id = (int) $row['category_id'];
			$order[$id] = true;
			if ($row['setting_value'] !== null && $row['setting_value'] !== '') {
				$titles[$id][(string) $row['locale']] = (string) $row['setting_value'];
			}
		}

		$issueJoin = '';
		$issueArgs = array();
		if ($issueId !== null) {
			$issueJoin = ' JOIN publication_settings ps ON ps.publication_id = p.publication_id AND ps.setting_name = ? AND ps.setting_value = ?';
			$issueArgs = array('issueId', (string) (int) $issueId);
		}

		$count = $pdo->prepare(
			'SELECT COUNT(DISTINCT s.submission_id)
			FROM publication_categories pc
			JOIN publications p ON p.publication_id = pc.publication_id
			JOIN submissions s ON s.submission_id = p.submission_id AND s.current_publication_id = p.publication_id'
			. $issueJoin . '
			WHERE s.context_id = ? AND s.status = 3 AND pc.category_id = ?'
		);
		$latest = $pdo->prepare(
			'SELECT DISTINCT s.submission_id, p.date_published
			FROM publication_categories pc
			JOIN publications p ON p.publication_id = pc.publication_id
			JOIN submissions s ON s.submission_id = p.submission_id AND s.current_publication_id = p.publication_id'
			. $issueJoin . '
			WHERE s.context_id = ? AND s.status = 3 AND pc.category_id = ?
			ORDER BY p.date_published DESC, s.submission_id DESC
			LIMIT ' . max(1, (int) $limit)
		);

		$result = array();
		foreach (array_keys($order) as $id) {
			$count->execute(array_merge($issueArgs, array((int) $contextId, $id)));
			$total = (int) $count->fetchColumn();
			if ($total < 1 || empty($titles[$id])) {
				continue;
			}
			$latest->execute(array_merge($issueArgs, array((int) $contextId, $id)));
			$ids = array();
			foreach ($latest->fetchAll(PDO::FETCH_NUM) as $row) {
				$ids[] = (int) $row[0];
			}
			$result[] = array('id' => $id, 'title' => self::pickTitle($titles[$id], $locales), 'count' => $total, 'submissionIds' => $ids);
		}
		return $result;
	}

	public static function pickTitle(array $byLocale, array $locales) {
		foreach ($locales as $locale) {
			if (isset($byLocale[$locale]) && $byLocale[$locale] !== '') {
				return $byLocale[$locale];
			}
		}
		return (string) reset($byLocale);
	}

	/**
	 * @param array $cards list of array('title', 'count', 'articles' => array of array('title','url'), 'searchUrl')
	 * @param array $labels 'heading', 'articles' (e.g. "Articles"), 'more'
	 * @return string HTML, '' when there are no cards
	 */
	public static function render(array $cards, array $labels) {
		if (!$cards) {
			return '';
		}
		$e = function ($value) {
			return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
		};
		$html = '<section class="ms-categories"><h2>' . $e($labels['heading']) . '</h2><div class="ms-categories__grid">';
		foreach ($cards as $card) {
			$html .= '<details class="ms-category"><summary><span class="ms-category__name">' . $e($card['title']) . '</span>'
				. '<span class="ms-category__count">' . (int) $card['count'] . ' ' . $e($labels['articles']) . '</span></summary><ul>';
			foreach ($card['articles'] as $article) {
				$html .= '<li><a href="' . $e($article['url']) . '">' . $e($article['title']) . '</a></li>';
			}
			$html .= '</ul>';
			if ($card['count'] > count($card['articles']) && !empty($card['searchUrl'])) {
				$html .= '<a class="ms-category__more" href="' . $e($card['searchUrl']) . '">' . $e($labels['more']) . '</a>';
			}
			$html .= '</details>';
		}
		return $html . '</div></section>';
	}

	/**
	 * Insert the cards before the current-issue block of the home page (right
	 * below the journal description). Returns the page unchanged when that
	 * block is not found.
	 */
	public static function inject($output, $html) {
		if ($html === '') {
			return $output;
		}
		return preg_replace_callback('#<section class="current_issue"#', function ($m) use ($html) {
			return $html . $m[0];
		}, $output, 1);
	}
}
