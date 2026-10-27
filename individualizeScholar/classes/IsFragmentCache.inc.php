<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Generated from classes/MsFragmentCache.inc.php by scripts/sync-individualize-scholar.sh: edit that file, not this one.
/**
 * Tiny file cache for the page fragments the theme builds from the database
 * (home page cards, issue coverage, article actions). One file per key, a
 * time to live in seconds, written atomically. When the directory cannot be
 * created or written the cache silently does nothing, so a page is always
 * built from the database in that case.
 * Optionally (serveStale) an expired value is still returned at once while a
 * fresh one is built after the response has been sent, so no visitor waits
 * for the rebuild.
 * Nothing here touches OJS, so it can be tested on its own.
 */
class IsFragmentCache {

	/** @var string */
	protected $dir;

	/** @var int */
	protected $ttl;

	/** @var int seconds after which an expired value is no longer served */
	protected $staleTtl = 0;

	/** @var callable|null receives a callable to run after the response is sent */
	protected $defer = null;

	/** Seconds after which a rebuild lock is considered abandoned */
	const LOCK_TTL = 300;

	/**
	 * @param string $dir directory for the cache files (created when missing)
	 * @param int $ttl seconds a stored value stays valid; 0 turns the cache off
	 */
	public function __construct($dir, $ttl) {
		$this->dir = rtrim((string) $dir, '/\\');
		$this->ttl = max(0, (int) $ttl);
	}

	/**
	 * Serve expired values (up to $maxAge seconds old) while $defer runs the
	 * rebuild later. $defer gets a callable and must run it after the
	 * response; null turns this off (an expired value is rebuilt at once).
	 *
	 * @return IsFragmentCache
	 */
	public function serveStale($maxAge, $defer) {
		$this->staleTtl = max(0, (int) $maxAge);
		$this->defer = is_callable($defer) ? $defer : null;
		return $this;
	}

	/**
	 * Return the stored value of $key, or build it with $build and store it.
	 * A $build that throws stores nothing and the exception goes to the caller.
	 *
	 * @param string $key
	 * @param callable $build returns a string or an array
	 * @param bool $refresh skip the stored value and store a fresh one
	 */
	public function remember($key, $build, $refresh = false) {
		if ($this->ttl === 0 || $this->dir === '') {
			return call_user_func($build);
		}
		$file = $this->file($key);
		if (!$refresh) {
			$stored = $this->read($file);
			if ($stored !== null && $stored['age'] < $this->ttl) {
				return $stored['value'];
			}
			if ($stored !== null && $this->defer !== null) {
				// Expired: hand out the stored copy, rebuild once after the response
				if ($this->claim($file)) {
					call_user_func($this->defer, function () use ($file, $build) {
						try {
							$this->write($file, call_user_func($build));
						} finally {
							$this->release($file);
						}
					});
				}
				return $stored['value'];
			}
		}
		$value = call_user_func($build);
		$this->write($file, $value);
		return $value;
	}

	/** Take the rebuild lock of a cache file; false when another request holds it. */
	protected function claim($file) {
		$lock = $file . '.lock';
		clearstatcache(true, $lock);
		if (is_file($lock) && (time() - (int) @filemtime($lock)) >= self::LOCK_TTL) {
			@unlink($lock);
		}
		$handle = @fopen($lock, 'x');
		if ($handle === false) {
			return false;
		}
		fclose($handle);
		return true;
	}

	protected function release($file) {
		@unlink($file . '.lock');
	}

	/** Remove every stored value. */
	public function flush() {
		foreach (array_merge((array) glob($this->dir . '/ms-*.cache'), (array) glob($this->dir . '/ms-*.cache.lock')) as $file) {
			@unlink($file);
		}
	}

	protected function file($key) {
		return $this->dir . '/ms-' . substr(md5((string) $key), 0, 16) . '-' . preg_replace('/[^A-Za-z0-9_-]/', '_', substr((string) $key, 0, 40)) . '.cache';
	}

	/**
	 * @return array|null array('value' => string|array, 'age' => seconds) while it
	 *   may still be used (within the time to live, or the stale age when stale
	 *   values are served)
	 */
	protected function read($file) {
		clearstatcache(true, $file);
		if (!is_file($file)) {
			return null;
		}
		$age = time() - (int) @filemtime($file);
		$limit = $this->defer !== null ? max($this->ttl, $this->staleTtl) : $this->ttl;
		if ($age >= $limit) {
			return null;
		}
		$raw = @file_get_contents($file);
		if ($raw === false || $raw === '') {
			return null;
		}
		$data = @unserialize($raw, array('allowed_classes' => false));
		if (!is_array($data) || !array_key_exists('value', $data)) {
			return null;
		}
		return array('value' => $data['value'], 'age' => $age);
	}

	protected function write($file, $value) {
		if (!is_dir($this->dir) && !@mkdir($this->dir, 0755, true) && !is_dir($this->dir)) {
			return;
		}
		$tmp = $file . '.' . getmypid() . '.tmp';
		if (@file_put_contents($tmp, serialize(array('value' => $value)), LOCK_EX) === false) {
			@unlink($tmp);
			return;
		}
		if (!@rename($tmp, $file)) {
			@unlink($tmp);
		}
	}
}
