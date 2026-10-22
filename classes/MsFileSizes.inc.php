<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Sizes of the files of one submission for the back-end file lists (shown
 * under the file type, e.g. "Article Text"). Read from the disk, so nothing
 * is downloaded or logged. Nothing here touches OJS, so it can be tested on
 * its own.
 */
class MsFileSizes {

	/**
	 * Stored path of every file of a submission of the journal (OJS 3.3
	 * tables submission_files, files and submissions).
	 *
	 * @return array submission file id => path relative to the files directory
	 */
	public static function paths(PDO $pdo, $submissionId, $contextId) {
		$statement = $pdo->prepare(
			'SELECT sf.submission_file_id, f.path
			FROM submission_files sf
			JOIN files f ON f.file_id = sf.file_id
			JOIN submissions s ON s.submission_id = sf.submission_id
			WHERE sf.submission_id = ? AND s.context_id = ?'
		);
		$statement->execute(array((int) $submissionId, (int) $contextId));
		$paths = array();
		while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
			$paths[(int) $row[0]] = (string) $row[1];
		}
		return $paths;
	}

	/**
	 * Stored path of the files a reviewer sees on his or her review page: the
	 * files assigned to the review (review_files) and the reviewer's own
	 * attachments (file stage 5 attached to the review assignment, assoc type
	 * 517). Other files of the submission are left out.
	 *
	 * @return array submission file id => path relative to the files directory
	 */
	public static function reviewerPaths(PDO $pdo, $submissionId, $contextId, $reviewerId) {
		$statement = $pdo->prepare(
			'SELECT sf.submission_file_id, f.path
			FROM review_assignments ra
			JOIN submissions s ON s.submission_id = ra.submission_id
			JOIN submission_files sf ON sf.submission_id = ra.submission_id
			JOIN files f ON f.file_id = sf.file_id
			WHERE ra.submission_id = ? AND ra.reviewer_id = ? AND s.context_id = ?
			AND (sf.submission_file_id IN (SELECT rf.submission_file_id FROM review_files rf WHERE rf.review_id = ra.review_id)
				OR (sf.file_stage = 5 AND sf.assoc_type = 517 AND sf.assoc_id = ra.review_id))'
		);
		$statement->execute(array((int) $submissionId, (int) $reviewerId, (int) $contextId));
		$paths = array();
		while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
			$paths[(int) $row[0]] = (string) $row[1];
		}
		return $paths;
	}

	/**
	 * @param array $paths submission file id => relative path
	 * @param string $baseDir the files directory of OJS
	 * @return array submission file id => size in bytes (missing files left out)
	 */
	public static function sizes(array $paths, $baseDir) {
		$base = rtrim((string) $baseDir, '/\\');
		$sizes = array();
		foreach ($paths as $id => $path) {
			$path = ltrim((string) $path, '/\\');
			if ($path === '' || strpos($path, '..') !== false) {
				continue;
			}
			$size = @filesize($base . '/' . $path);
			if ($size !== false) {
				$sizes[(int) $id] = (int) $size;
			}
		}
		return $sizes;
	}

	/** JSON data block read by js/manuscriptScholarAdmin.js; '' when there are no sizes. */
	public static function dataTag(array $sizes) {
		if (!$sizes) {
			return '';
		}
		$json = json_encode((object) $sizes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		return $json === false ? '' : '<script type="application/json" id="ms-file-sizes">' . $json . '</script>';
	}
}
