<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Generated from classes/MsContentSignature.inc.php by scripts/sync-individualize-scholar.sh: edit that file, not this one.
/**
 * A short fingerprint of the published articles of a journal (or of one
 * issue): how many there are and when their current (reader-visible)
 * publication was last changed; draft and older versions do not count. The theme adds
 * it to the key of its cached page fragments, so a fragment is rebuilt as
 * soon as an article is published, withdrawn or edited, instead of after the
 * cache time. One cheap query (forIssues(): every issue at once); reads OJS 3.3 tables (submissions,
 * publications, publication_settings).
 * Nothing here touches OJS, so it can be tested on its own.
 */
class IsContentSignature {

	/**
	 * @param PDO $pdo
	 * @param int $contextId
	 * @param int|null $issueId only the published articles of this issue (the current
	 *   publication's `issueId` setting); null for the whole journal
	 * @return string 8 hex characters; '' when the fingerprint cannot be read
	 */
	public static function forContext(PDO $pdo, $contextId, $issueId = null) {
		try {
			$join = '';
			$args = array();
			if ($issueId !== null) {
				$join = ' JOIN publication_settings ps ON ps.publication_id = s.current_publication_id AND ps.setting_name = ? AND ps.setting_value = ?';
				$args = array('issueId', (string) (int) $issueId);
			}
			$statement = $pdo->prepare(
				'SELECT COUNT(DISTINCT s.submission_id), MAX(p.last_modified), MAX(s.last_modified)
				FROM submissions s
				JOIN publications p ON p.publication_id = s.current_publication_id' . $join . '
				WHERE s.context_id = ? AND s.status = 3'
			);
			$statement->execute(array_merge($args, array((int) $contextId)));
			$row = $statement->fetch(PDO::FETCH_NUM);
			return $row === false ? '' : self::digest($row);
		} catch (\Throwable $e) {
			return '';
		}
	}

	/**
	 * The fingerprints of every issue of the journal in one query, each equal
	 * to forContext($pdo, $contextId, $issueId).
	 *
	 * @return array|null issue id => fingerprint (issues without published
	 *   articles are left out, see emptyIssue()); null when it cannot be read
	 */
	public static function forIssues(PDO $pdo, $contextId) {
		try {
			$statement = $pdo->prepare(
				'SELECT ps.setting_value, COUNT(DISTINCT s.submission_id), MAX(p.last_modified), MAX(s.last_modified)
				FROM submissions s
				JOIN publications p ON p.publication_id = s.current_publication_id
				JOIN publication_settings ps ON ps.publication_id = s.current_publication_id AND ps.setting_name = ?
				WHERE s.context_id = ? AND s.status = 3
				GROUP BY ps.setting_value'
			);
			$statement->execute(array('issueId', (int) $contextId));
			$map = array();
			while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
				$issueId = (int) array_shift($row);
				if ($issueId > 0) {
					$map[$issueId] = self::digest($row);
				}
			}
			return $map;
		} catch (\Throwable $e) {
			return null;
		}
	}

	/** Fingerprint of an issue without published articles (as forContext() gives it). */
	public static function emptyIssue() {
		return self::digest(array(0, null, null));
	}

	protected static function digest(array $row) {
		return substr(md5(implode('|', $row)), 0, 8);
	}
}
