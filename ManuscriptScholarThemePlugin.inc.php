<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Manuscript Scholar: the boxed, paper-like Manuscript look (PKP's "Default
 * Manuscript" child theme) with the scholarly features of Lite Scholar, as one
 * child theme of the OJS 3.3 Default Theme.
 *
 * The Manuscript stylesheet is compiled together with the Default Theme's
 * (LESS), then one small stylesheet styles the features: the author's country
 * below the affiliation, the Dimensions badge, the "Issue Coverage" box, the
 * Archives cards, the action line of listed articles, the home page cards and
 * the back-end helpers. No core or parent-theme template is copied, so OJS
 * 3.3.x updates of the Default Theme keep working.
 */
import('lib.pkp.classes.plugins.ThemePlugin');
require_once(dirname(__FILE__) . '/classes/MsIssueCoverage.inc.php');
require_once(dirname(__FILE__) . '/classes/MsHomeHighlights.inc.php');
require_once(dirname(__FILE__) . '/classes/MsArticleActions.inc.php');
require_once(dirname(__FILE__) . '/classes/MsHomeIssueTrim.inc.php');
require_once(dirname(__FILE__) . '/classes/MsArticleOrder.inc.php');
require_once(dirname(__FILE__) . '/classes/MsDimensionsBadge.inc.php');
require_once(dirname(__FILE__) . '/classes/MsCategoryCards.inc.php');
require_once(dirname(__FILE__) . '/classes/MsFragmentCache.inc.php');
require_once(dirname(__FILE__) . '/classes/MsArchiveStats.inc.php');
require_once(dirname(__FILE__) . '/classes/MsCountryFromAffiliation.inc.php');
require_once(dirname(__FILE__) . '/classes/MsCountryOptions.inc.php');
require_once(dirname(__FILE__) . '/classes/MsImageHints.inc.php');
require_once(dirname(__FILE__) . '/classes/MsMetaDescription.inc.php');
require_once(dirname(__FILE__) . '/classes/MsFileSizes.inc.php');
require_once(dirname(__FILE__) . '/classes/MsContentSignature.inc.php');
require_once(dirname(__FILE__) . '/classes/MsThemeColour.inc.php');

class ManuscriptScholarThemePlugin extends ThemePlugin {

	/** @var array|null ISO 3166-1 alpha-2 => localized country name */
	protected $countryNames = null;

	/** @var array|null */
	protected $countryNamesEnglish = array();

	/** @var array|null */
	protected $affiliationIndex = null;

	/**
	 * Manuscript's set-up (its LESS on top of the Default Theme, its fonts and
	 * its accent colour), then the features. The base colour is the Default
	 * Theme's own "Base colour" option, as in Manuscript; it also colours the
	 * links and buttons when it is dark enough to read on white.
	 */
	public function init() {
		$this->setParent('defaultthemeplugin');
		$this->modifyStyle('stylesheet', array('addLess' => array('styles/index.less')));
		try {
			// Manuscript brings its own fonts and has no homepage-image header
			if (method_exists($this, 'removeOption')) {
				$this->removeOption('typography');
				$this->removeOption('useHomepageImageAsHeader');
			}
		} catch (\Throwable $e) {
		}
		$this->addOption('accentColour', 'FieldColor', array(
			'label' => __('plugins.themes.manuscriptScholar.option.accentColour.label'),
			'description' => __('plugins.themes.manuscriptScholar.option.accentColour.description'),
			'default' => MsThemeColour::DEFAULT_ACCENT,
		));
		$countryOptions = $this->homeCountryChoices();
		$homeCountry = array(
			'label' => __('plugins.themes.manuscriptScholar.option.homeCountry.label'),
			'description' => __('plugins.themes.manuscriptScholar.option.homeCountry.description'),
			'default' => '',
		);
		if ($countryOptions) {
			$homeCountry['options'] = $countryOptions;
			$this->addOption('manuscriptScholarHomeCountry', 'FieldSelect', $homeCountry);
		} else {
			// No country list available: a two-letter code typed by hand still works
			$this->addOption('manuscriptScholarHomeCountry', 'FieldText', $homeCountry);
		}
		if (method_exists($this, 'removeStyle')) {
			// Fonts queued by the parent theme (one style per font file)
			for ($i = 0; $i < 11; $i++) {
				$this->removeStyle('font');
			}
		}
		$base = MsThemeColour::base($this->getOption('baseColour'));
		$accent = MsThemeColour::accent($this->getOption('accentColour'));
		$this->modifyStyle('stylesheet', array('addLessVariables' => MsThemeColour::lessVariables($base, $accent)));
		$this->addStyle('manuscriptScholar', 'styles/manuscriptScholar.css', array('priority' => STYLE_SEQUENCE_LAST));
		$rootCss = MsThemeColour::rootCss($base, $accent);
		if ($rootCss !== '') {
			$this->addStyle('manuscriptScholarColour', $rootCss, array('inline' => true, 'priority' => STYLE_SEQUENCE_LAST));
		}

		HookRegistry::register('TemplateManager::display', array($this, 'handleDisplay'));
	}

	/**
	 * Only hex colours are stored for the two colour options, so nothing else
	 * can reach the compiled stylesheet (pkp/pkp-lib#11974).
	 */
	public function saveOption($name, $value, $contextId = null) {
		if (($name === 'baseColour' || $name === 'accentColour') && !preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', (string) $value)) {
			$value = null;
		}
		parent::saveOption($name, $value, $contextId);
	}

	/** Articles of the current issue visible on the home page before "Read more" */
	const HOME_ARTICLE_LIMIT = 5;

	/** Articles of the current issue listed inside one category card */
	const CATEGORY_ARTICLE_LIMIT = 20;

	/** Seconds the home page cards, issue coverage and article actions are kept; 0 turns the cache off */
	const CACHE_TTL = 1800;

	/**
	 * Seconds an expired fragment is still shown while a fresh one is built
	 * after the response (only where PHP can end the response early)
	 */
	const STALE_TTL = 604800;

	/** @var MsFragmentCache|null */
	protected $fragmentCache = null;

	const VERSION = '1.0.2';

	/**
	 * Choices of the "journal home country" option: every country by its full
	 * name in the interface language. The list is kept for a day per language,
	 * because the theme options are defined on every request.
	 */
	protected function homeCountryChoices() {
		try {
			$locale = class_exists('AppLocale') ? AppLocale::getLocale() : '';
			$cache = new MsFragmentCache($this->cacheDir(), 86400);
			$choices = $cache->remember('v' . self::VERSION . '-country-options-' . $locale, function () {
				$this->loadCountryNames();
				return MsCountryOptions::build($this->countryNames, __('plugins.themes.manuscriptScholar.option.homeCountry.none'));
			});
			return is_array($choices) ? $choices : array();
		} catch (\Throwable $e) {
			error_log('manuscriptScholar: unable to list the countries: ' . $e->getMessage());
			return array();
		}
	}

	/**
	 * ISO alpha-2 code of the journal's home country, from the theme option;
	 * '' (nothing saved or not a two-letter code) turns the share of foreign
	 * authors off.
	 */
	protected function homeCountry() {
		$code = strtoupper(trim((string) $this->getOption('manuscriptScholarHomeCountry')));
		return preg_match('/^[A-Z]{2}$/', $code) ? $code : '';
	}

	/** @var array list of array('name' => string, 'fn' => callable) applied to the rendered page */
	protected $injections = array();

	/** @var array status lines for the HTML comment at the end of the page */
	protected $notes = array();

	/**
	 * Route the display hook to the features for the page being rendered.
	 * All page insertions run in one output filter, so they cannot replace
	 * each other, and a status comment is added to the page source.
	 */
	public function handleDisplay($hookName, $args) {
		$templateMgr = $args[0];
		$template = $args[1];
		if (is_string($template) && strpos($template, 'frontend/') !== 0) {
			$this->addBackendTools($templateMgr, $template);
			return false;
		}
		if (!is_string($template)) {
			return false;
		}
		$this->injections = array();
		$this->notes = array();

		try {
			$this->inject('header image priority', function ($output) {
				return MsImageHints::prioritizeHeader($output);
			});
			$this->inject('sidebar and footer lazy loading', function ($output) {
				return MsImageHints::lazySidebarAndFooter($output);
			});
			$description = $this->pageDescription($templateMgr, $template);
			$this->inject('meta description', function ($output) use ($description) {
				return MsMetaDescription::inject($output, $description);
			});
			// First, so the home page shortening keeps the first articles by page
			if ($template === 'frontend/pages/issue.tpl' || $template === 'frontend/pages/indexJournal.tpl') {
				$this->inject('article order by pages', function ($output) {
					return MsArticleOrder::sortByPages($output);
				});
			}
			if ($template === 'frontend/pages/article.tpl') {
				$this->inject('dimensions badge', function ($output) {
					return MsDimensionsBadge::inject($output);
				});
				$this->addAuthorCountries($hookName, $args);
			}
			if ($template === 'frontend/pages/indexJournal.tpl') {
				$this->addHomeHighlights($templateMgr);
				$this->addCategoryCards();
			}
			if ($template === 'frontend/pages/issue.tpl' || $template === 'frontend/pages/indexJournal.tpl') {
				$this->addIssueCoverage($templateMgr);
			}
			if ($template === 'frontend/pages/issueArchive.tpl') {
				$this->inject('archive cover lazy loading', function ($output) {
					return MsImageHints::lazyCovers($output);
				});
				$this->addArchiveStats($templateMgr);
			}
			if ($template === 'frontend/pages/indexJournal.tpl') {
				$more = __('plugins.themes.manuscriptScholar.home.readMore');
				$less = __('plugins.themes.manuscriptScholar.home.readLess');
				$this->inject('home issue fold', function ($output) use ($more, $less) {
					return MsHomeIssueTrim::trim($output, ManuscriptScholarThemePlugin::HOME_ARTICLE_LIMIT, $more, $less);
				});
			}
			if (in_array($template, array('frontend/pages/issue.tpl', 'frontend/pages/indexJournal.tpl', 'frontend/pages/search.tpl'), true)) {
				$this->addArticleActions();
			}
		} catch (\Throwable $e) {
			$this->notes[] = 'error: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 160);
		}

		$injections = $this->injections;
		$notes = $this->notes;
		$detail = $this->canSeeDebug();
		$scriptUrl = $this->getAssetUrl('js/manuscriptScholar.js');
		$templateMgr->registerFilter('output', function ($output) use ($injections, $notes, $detail, $template, $scriptUrl) {
			// Sidebar blocks and other partial fetches also pass through this filter
			if (stripos($output, '</html>') === false) {
				return $output;
			}
			$status = array();
			foreach ($injections as $injection) {
				$changed = call_user_func($injection['fn'], $output);
				$status[] = $injection['name'] . ': ' . ($changed !== $output ? 'inserted' : 'no anchor found');
				$output = $changed;
			}
			$output = ManuscriptScholarThemePlugin::addCacheBuster($output, ManuscriptScholarThemePlugin::VERSION);
			$text = 'manuscriptScholar ' . ManuscriptScholarThemePlugin::VERSION;
			if ($detail) {
				$text .= ' | ' . $template . ' | ' . implode(' | ', array_merge($notes, $status));
			}
			$comment = ($scriptUrl !== '' ? ManuscriptScholarThemePlugin::scriptTag($scriptUrl, ManuscriptScholarThemePlugin::VERSION) : '')
				. '<!-- ' . str_replace('--', '- -', $text) . ' -->';
			return preg_match('/<\/body>/i', $output)
				? preg_replace_callback('/<\/body>(?!.*<\/body>)/is', function ($m) use ($comment) { return $comment . $m[0]; }, $output, 1)
				: $output . $comment;
		}, 'manuscriptScholar');
		return false;
	}

	/**
	 * Back-end pages (not part of the public site): add the "Select all"
	 * buttons script for signed-in users. Only complete pages get it; the
	 * script itself does nothing on pages without the lists it serves.
	 */
	protected function addBackendTools($templateMgr, $template = '') {
		try {
			if (!class_exists('Validation') || !Validation::isLoggedIn()) {
				return;
			}
			$tag = MsFileSizes::dataTag($this->workflowFileSizes($template))
				. self::adminScriptTag($this->getAssetUrl('js/manuscriptScholarAdmin.js'), self::VERSION, array(
				'all' => __('plugins.themes.manuscriptScholar.admin.selectAll'),
				'none' => __('plugins.themes.manuscriptScholar.admin.clearAll'),
			));
			$templateMgr->registerFilter('output', function ($output) use ($tag) {
				return ManuscriptScholarThemePlugin::injectAdminScript($output, $tag);
			});
		} catch (\Throwable $e) {
			error_log('manuscriptScholar: unable to add the back-end script: ' . $e->getMessage());
		}
	}

	/** Back-end pages that list the files of one submission => word their template contains */
	const FILE_SIZE_PAGES = array('workflow' => 'workflow', 'authorDashboard' => 'authorDashboard', 'reviewer' => 'review');

	/**
	 * Sizes of the files of the submission shown on a workflow, author
	 * dashboard or reviewer page (OJS has already checked that the user may
	 * open it). The submission must belong to the current journal; a reviewer
	 * gets only the files of his or her own review. Empty on other pages.
	 *
	 * @return array submission file id => bytes
	 */
	protected function workflowFileSizes($template) {
		if (!is_string($template)) {
			return array();
		}
		try {
			$request = Application::get()->getRequest();
			$page = $request->getRequestedPage();
			if (!isset(self::FILE_SIZE_PAGES[$page]) || stripos($template, self::FILE_SIZE_PAGES[$page]) === false) {
				return array();
			}
			$args = $request->getRequestedArgs();
			$submissionId = isset($args[0]) ? (int) $args[0] : 0;
			$context = $request->getContext();
			$user = $request->getUser();
			if ($submissionId < 1 || !$context || !$user) {
				return array();
			}
			$pdo = \Illuminate\Database\Capsule\Manager::connection()->getPdo();
			$paths = $page === 'reviewer'
				? MsFileSizes::reviewerPaths($pdo, $submissionId, $context->getId(), $user->getId())
				: MsFileSizes::paths($pdo, $submissionId, $context->getId());
			return MsFileSizes::sizes($paths, Config::getVar('files', 'files_dir'));
		} catch (\Throwable $e) {
			error_log('manuscriptScholar: unable to read the file sizes: ' . $e->getMessage());
			return array();
		}
	}

	/**
	 * <script> tag of the back-end helper script (js/manuscriptScholarAdmin.js) with
	 * the button labels as data attributes.
	 */
	public static function adminScriptTag($url, $version, array $labels) {
		$e = function ($value) {
			return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
		};
		return '<script src="' . $e($url) . '?v=' . rawurlencode($version) . '" data-select-all="' . $e(isset($labels['all']) ? $labels['all'] : '')
			. '" data-clear-all="' . $e(isset($labels['none']) ? $labels['none'] : '') . '" defer></script>';
	}

	/**
	 * Put the tag before the last </body> of a complete page; fragments (no
	 * </html>) and pages that already carry the script are returned unchanged.
	 */
	public static function injectAdminScript($output, $tag) {
		if (stripos($output, '</html>') === false || strpos($output, 'manuscriptScholarAdmin.js') !== false) {
			return $output;
		}
		$result = preg_replace_callback('/<\/body>(?!.*<\/body>)/is', function ($m) use ($tag) {
			return $tag . $m[0];
		}, $output, 1, $count);
		return $count === 1 ? $result : $output;
	}

	/**
	 * Text for <meta name="description"> (used only when OJS printed none):
	 * the abstract on an article page, the issue title and description on an
	 * issue page, else the journal name and description.
	 */
	protected function pageDescription($templateMgr, $template) {
		try {
			$parts = array();
			if ($template === 'frontend/pages/article.tpl') {
				$publication = $templateMgr->getTemplateVars('publication');
				if ($publication && method_exists($publication, 'getLocalizedData')) {
					$parts[] = (string) $publication->getLocalizedData('abstract');
				}
			}
			if ($template === 'frontend/pages/issue.tpl') {
				$issue = $templateMgr->getTemplateVars('issue');
				if ($issue && method_exists($issue, 'getIssueIdentification')) {
					$parts[] = (string) $issue->getIssueIdentification();
					$parts[] = (string) $issue->getLocalizedDescription();
				}
			}
			$context = $templateMgr->getTemplateVars('currentContext');
			if ($context && method_exists($context, 'getLocalizedName')) {
				$parts[] = (string) $context->getLocalizedName();
				$parts[] = (string) $context->getLocalizedDescription();
			}
			return MsMetaDescription::compose($parts);
		} catch (\Throwable $e) {
			return '';
		}
	}

	/**
	 * <script> tag of the theme's helper script (js/manuscriptScholar.js), with the
	 * version in the URL so browsers and CDNs fetch the new file after every
	 * release. "defer" runs it once the page is parsed.
	 */
	public static function scriptTag($url, $version) {
		return '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '?v=' . rawurlencode($version) . '" defer></script>';
	}

	/**
	 * Add ?v=VERSION to the theme stylesheet URL so browsers, Cloudflare and
	 * server caches fetch a fresh copy after every release.
	 */
	public static function addCacheBuster($output, $version) {
		return preg_replace(
			'~(href=["\'][^"\']*/manuscriptScholar\.css)(?:\?[^"\']*)?(["\'])~',
			'${1}?v=' . rawurlencode($version) . '${2}',
			$output
		);
	}

	/**
	 * Status details in the page source are shown to site administrators only.
	 */
	protected function canSeeDebug() {
		if ($this->isSiteAdmin === null) {
			try {
				$this->isSiteAdmin = class_exists('Validation') && Validation::isSiteAdmin();
			} catch (\Throwable $e) {
				$this->isSiteAdmin = false;
			}
		}
		return $this->isSiteAdmin;
	}

	/** @var bool|null site administrator check, done once per request */
	protected $isSiteAdmin = null;

	/** @var array fingerprints read in this request ('<journal>' and '<journal>-issues') */
	protected $signatures = array();

	/** @var callable[] cache rebuilds to run after the response */
	protected $deferredJobs = array();

	/**
	 * Runs a cache rebuild after the page has been sent to the visitor, when
	 * PHP can end the response early (PHP-FPM or LiteSpeed); null otherwise,
	 * and an expired fragment is then rebuilt at once as before.
	 *
	 * @return callable|null
	 */
	protected function afterResponse() {
		$finish = null;
		foreach (array('fastcgi_finish_request', 'litespeed_finish_request') as $function) {
			if (function_exists($function)) {
				$finish = $function;
				break;
			}
		}
		if ($finish === null) {
			return null;
		}
		return function ($job) use ($finish) {
			if (!$this->deferredJobs) {
				register_shutdown_function(array($this, 'runDeferredJobs'), $finish);
			}
			$this->deferredJobs[] = $job;
		};
	}

	/**
	 * Shutdown function: send the response, then rebuild the expired fragments.
	 */
	public function runDeferredJobs($finish) {
		$jobs = $this->deferredJobs;
		$this->deferredJobs = array();
		if (!$jobs) {
			return;
		}
		try {
			call_user_func($finish);
		} catch (\Throwable $e) {
		}
		@ignore_user_abort(true);
		if (function_exists('set_time_limit')) {
			@set_time_limit(120);
		}
		foreach ($jobs as $job) {
			try {
				call_user_func($job);
			} catch (\Throwable $e) {
				error_log('manuscriptScholar: unable to refresh a cached fragment: ' . $e->getMessage());
			}
		}
	}

	protected function cacheTtl() {
		return self::CACHE_TTL;
	}

	/**
	 * Directory of the fragment cache: OJS's own cache folder when it can be
	 * found, else a folder in the system temp directory.
	 */
	protected function cacheDir() {
		try {
			if (class_exists('CacheManager') && method_exists('CacheManager', 'getFileCachePath')) {
				return rtrim(CacheManager::getFileCachePath(), '/\\') . '/manuscriptScholar';
			}
		} catch (\Throwable $e) {
		}
		return sys_get_temp_dir() . '/manuscriptScholar-' . substr(md5(dirname(__FILE__)), 0, 8);
	}

	/**
	 * Value of a page fragment built from the database, kept for CACHE_TTL
	 * seconds per journal, language, version and $kind. Site administrators
	 * always get a fresh build, which also refreshes the stored copy, so
	 * viewing the page as an administrator shows new content straight away.
	 * A $build that throws stores nothing.
	 */
	protected function cached($kind, $contextId, $build, $signature = '') {
		if ($this->fragmentCache === null) {
			$this->fragmentCache = new MsFragmentCache($this->cacheDir(), $this->cacheTtl());
			$this->fragmentCache->serveStale(self::STALE_TTL, $this->afterResponse());
		}
		$locale = class_exists('AppLocale') ? AppLocale::getLocale() : '';
		$key = 'v' . self::VERSION . '-c' . (int) $contextId . '-' . $locale . '-' . $kind . ($signature !== '' ? '-' . $signature : '');
		return $this->fragmentCache->remember($key, $build, $this->canSeeDebug());
	}

	/**
	 * Fingerprint of the published articles of the journal (or of one issue),
	 * part of the cache key: publishing, withdrawing or editing an article
	 * changes it, so the cached fragments are rebuilt at once. '' when it
	 * cannot be read (then only the cache time applies). Read at most twice
	 * per request: once for the journal and once for all its issues together
	 * (before, the Archives page asked once for every issue it listed).
	 */
	protected function contentSignature($contextId, $issueId = null) {
		$contextId = (int) $contextId;
		$key = $contextId . ($issueId === null ? '' : '-issues');
		if (!array_key_exists($key, $this->signatures)) {
			try {
				$pdo = \Illuminate\Database\Capsule\Manager::connection()->getPdo();
				$this->signatures[$key] = $issueId === null
					? MsContentSignature::forContext($pdo, $contextId)
					: MsContentSignature::forIssues($pdo, $contextId);
			} catch (\Throwable $e) {
				$this->signatures[$key] = $issueId === null ? '' : null;
			}
		}
		if ($issueId === null) {
			return $this->signatures[$key];
		}
		$map = $this->signatures[$key];
		if (!is_array($map)) {
			return '';
		}
		return isset($map[(int) $issueId]) ? $map[(int) $issueId] : MsContentSignature::emptyIssue();
	}

	/**
	 * Published articles of a journal by id, in the order of $ids, loaded with
	 * one query (a single lookup for each id that query did not return).
	 */
	protected function loadSubmissions(array $ids, $contextId) {
		$ids = array_values(array_unique(array_map('intval', $ids)));
		if (!$ids) {
			return array();
		}
		$service = Services::get('submission');
		$byId = array();
		try {
			foreach ($service->getMany(array(
				'contextId' => (int) $contextId,
				'submissionIds' => $ids,
				'status' => STATUS_PUBLISHED,
				'count' => count($ids),
			)) as $submission) {
				$byId[(int) $submission->getId()] = $submission;
			}
		} catch (\Throwable $e) {
			error_log('manuscriptScholar: batch article lookup failed, using single lookups: ' . $e->getMessage());
		}
		foreach ($ids as $id) {
			if (isset($byId[$id])) {
				continue;
			}
			$submission = $service->get($id);
			if ($submission && (int) $submission->getData('contextId') === (int) $contextId
				&& (int) $submission->getData('status') === STATUS_PUBLISHED) {
				$byId[$id] = $submission;
			}
		}
		$ordered = array();
		foreach ($ids as $id) {
			if (isset($byId[$id])) {
				$ordered[] = $byId[$id];
			}
		}
		return $ordered;
	}

	protected function note($line) {
		$this->notes[] = $line;
	}

	protected function inject($name, $fn) {
		$this->injections[] = array('name' => $name, 'fn' => $fn);
	}

	public function getDisplayName() {
		return __('plugins.themes.manuscriptScholar.name');
	}

	public function getDescription() {
		return __('plugins.themes.manuscriptScholar.description');
	}

	/**
	 * Show each author's country on its own line below the affiliation on
	 * the article page. The rendered page is edited (not the author objects),
	 * so the Default Theme's template stays untouched and the text it prints
	 * is not altered. Authors are matched by the name the template prints.
	 */
	public function addAuthorCountries($hookName, $args) {
		$templateMgr = $args[0];
		$template = $args[1];
		if ($template !== 'frontend/pages/article.tpl') {
			return false;
		}
		$publication = $templateMgr->getTemplateVars('publication');
		if (!$publication) {
			$this->note('author country: no publication on this page');
			return false;
		}
		$authors = $publication->getData('authors');
		if (!is_array($authors) && !($authors instanceof Traversable)) {
			$this->note('author country: no authors list');
			return false;
		}

		$countries = array();
		$total = 0;
		foreach ($authors as $author) {
			$total++;
			$country = $this->getCountryName($author->getData('country'));
			if ($country !== '') {
				$countries[self::normalizeName($author->getFullName())] = $country;
			}
		}
		$this->note('author country: ' . count($countries) . " of $total authors have a valid country");
		if ($countries) {
			$this->inject('author country', function ($output) use ($countries) {
				return ManuscriptScholarThemePlugin::insertAuthorCountries($output, $countries);
			});
		}
		return false;
	}

	public static function normalizeName($name) {
		return mb_strtolower(trim(preg_replace('/\s+/u', ' ', html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8'))));
	}

	/**
	 * For every <span class="name"> whose text is a key of $countries
	 * (normalized name => country name), put the country at the end of the
	 * affiliation text ("Universitas Indonesia, Indonesia"); when no
	 * affiliation follows, add <span class="ms-country-line">Country</span>
	 * right after the name. The country is always added, even when the
	 * affiliation already names it (e.g. "Universitas Pendidikan Indonesia"),
	 * so every author looks alike.
	 */
	public static function insertAuthorCountries($output, array $countries) {
		return preg_replace_callback(
			'#(<span class="name">(.*?)</span>)(\s*<span class="affiliation">(.*?)</span>)?#s',
			function ($m) use ($countries) {
				$key = ManuscriptScholarThemePlugin::normalizeName(strip_tags($m[2]));
				if (!isset($countries[$key])) {
					return $m[0];
				}
				$country = htmlspecialchars($countries[$key], ENT_QUOTES, 'UTF-8');
				if (isset($m[4]) && trim(strip_tags($m[4])) !== '') {
					// "Affiliation, Country" on the affiliation line itself
					$affiliation = rtrim($m[4]);
					$affiliation = preg_replace('/[\s,]+$/', '', $affiliation);
					return $m[1] . preg_replace_callback('#<span class="affiliation">.*?</span>#s', function () use ($affiliation, $country) {
						return '<span class="affiliation">' . $affiliation . ', ' . $country . '</span>';
					}, $m[3], 1);
				}
				return $m[0] . '<span class="ms-country-line">' . $country . '</span>';
			},
			$output
		);
	}

	/**
	 * Under the authors of each article in a list: "Show Abstract | Download
	 * PDF | Check in Google Scholar" (the article's own PDF button is removed
	 * and its link is used for "Download PDF"). The ids of the articles on the page
	 * are read from the rendered page, so this works for any list that uses
	 * the Default Theme's article summary.
	 */
	public function addArticleActions() {
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		if (!$context) {
			return false;
		}
		$labels = array(
			'show' => __('plugins.themes.manuscriptScholar.article.showAbstract'),
			'hide' => __('plugins.themes.manuscriptScholar.article.hideAbstract'),
			'download' => __('plugins.themes.manuscriptScholar.article.download'),
			'scholar' => __('plugins.themes.manuscriptScholar.article.scholar'),
		);
		$this->inject('article actions', function ($output) use ($request, $context, $labels) {
			try {
				$ids = MsArticleActions::idsFromOutput($output);
				$data = $ids ? $this->articleActionData($ids, $request, $context) : array();
				return MsArticleActions::inject($output, $data, $labels);
			} catch (\Throwable $e) {
				error_log('manuscriptScholar: unable to build article actions: ' . $e->getMessage());
				return $output;
			}
		});
		return false;
	}

	/**
	 * DOI of the publication ("10.xxxx/..."), or '' when it has none.
	 */
	protected function articleDoi($publication) {
		$doi = '';
		if (method_exists($publication, 'getStoredPubId')) {
			$doi = (string) $publication->getStoredPubId('doi');
		}
		if ($doi === '') {
			$stored = $publication->getData('pub-id::doi');
			$doi = is_scalar($stored) ? (string) $stored : '';
		}
		return MsArticleActions::cleanDoi($doi);
	}

	/**
	 * Title, abstract, article URL and download URL of each published article.
	 *
	 * @return array id => array('title', 'abstract', 'url', 'download', 'isPdf', 'doi')
	 */
	protected function articleActionData(array $ids, $request, $context) {
		sort($ids);
		return $this->cached('actions-' . md5(implode(',', $ids)), $context->getId(), function () use ($ids, $request, $context) {
			return $this->buildArticleActionData($ids, $request, $context);
		}, $this->contentSignature($context->getId()));
	}

	protected function buildArticleActionData(array $ids, $request, $context) {
		$dispatcher = $request->getDispatcher();
		$data = array();
		foreach ($this->loadSubmissions($ids, $context->getId()) as $submission) {
			$id = (int) $submission->getId();
			$publication = $submission->getCurrentPublication();
			if (!$publication) {
				continue;
			}
			$title = (string) $publication->getLocalizedFullTitle();
			if ($title === '') {
				$title = (string) $publication->getLocalizedTitle();
			}
			$abstract = (string) $publication->getLocalizedData('abstract');
			if ($abstract !== '' && class_exists('PKPString')) {
				$abstract = PKPString::stripUnsafeHtml($abstract);
			} else {
				$abstract = strip_tags($abstract, '<p><br><b><i><em><strong><sub><sup><ul><ol><li>');
			}

			$download = '';
			$isPdf = false;
			$galleys = $publication->getData('galleys');
			if (is_array($galleys) || $galleys instanceof Traversable) {
				$chosen = null;
				foreach ($galleys as $galley) {
					if ($chosen === null) {
						$chosen = $galley;
					}
					if (method_exists($galley, 'isPdfGalley') && $galley->isPdfGalley()) {
						$chosen = $galley;
						break;
					}
				}
				if ($chosen !== null) {
					$isPdf = method_exists($chosen, 'isPdfGalley') && $chosen->isPdfGalley();
					$download = $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'article', 'download', array($submission->getBestId(), $chosen->getBestGalleyId()));
				}
			}
			$data[(int) $id] = array(
				'title' => $title,
				'abstract' => $abstract,
				'url' => $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'article', 'view', array($submission->getBestId())),
				'download' => $download,
				'isPdf' => $isPdf,
				'doi' => $this->articleDoi($publication),
			);
		}
		return $data;
	}

	/**
	 * Add three cards (latest, most read, most downloaded articles) before the
	 * footer of the journal home page, by filtering the rendered page. Any
	 * failure leaves the page as OJS rendered it, and a card without data is
	 * left out.
	 */
	public function addHomeHighlights($templateMgr) {
		$html = $this->buildHomeHighlights();
		if ($html === '') {
			$this->note('home highlights: no cards built');
			return false;
		}
		$this->inject('home highlights', function ($output) use ($html) {
			return MsHomeHighlights::inject($output, $html);
		});
		return false;
	}

	protected function buildHomeHighlights() {
		try {
			$request = Application::get()->getRequest();
			$context = $request->getContext();
			if (!$context) {
				return '';
			}
			return $this->cached('highlights', $context->getId(), function () use ($request, $context) {
				return $this->buildHomeHighlightsHtml($request, $context);
			}, $this->contentSignature($context->getId()));
		} catch (\Throwable $e) {
			error_log('manuscriptScholar: unable to build home highlights: ' . $e->getMessage());
			$this->note('home highlights error: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 160));
			return '';
		}
	}

	protected function buildHomeHighlightsHtml($request, $context) {
		$contextId = (int) $context->getId();
		$service = Services::get('submission');

		$latest = $service->getMany(array(
			'contextId' => $contextId,
			'status' => STATUS_PUBLISHED,
			'orderBy' => 'datePublished',
			'orderDirection' => 'DESC',
			'count' => 5,
		));
		$cards = array(
			array(
				'title' => __('plugins.themes.manuscriptScholar.home.latest'),
				'items' => $this->itemsFromSubmissions($latest, $request, $context, function ($submission, $publication) {
					return $this->formatDate($publication->getData('datePublished'));
				}),
			),
			array(
				'title' => __('plugins.themes.manuscriptScholar.home.mostRead'),
				'items' => $this->itemsFromStats('read', $contextId, $request, $context),
			),
			array(
				'title' => __('plugins.themes.manuscriptScholar.home.mostDownloaded'),
				'items' => $this->itemsFromStats('downloaded', $contextId, $request, $context),
			),
		);
		return MsHomeHighlights::render($cards);
	}

	/**
	 * Add one card per OJS category with articles in the current issue below the
	 * journal description. Clicking a card opens the list of its articles.
	 * A journal without categories simply gets no cards.
	 */
	public function addCategoryCards() {
		$html = $this->buildCategoryCards();
		if ($html === '') {
			$this->note('category cards: nothing to show (no current issue, or no category has articles in it)');
			return false;
		}
		$this->inject('category cards', function ($output) use ($html) {
			return MsCategoryCards::inject($output, $html);
		});
		return false;
	}

	protected function buildCategoryCards() {
		try {
			$request = Application::get()->getRequest();
			$context = $request->getContext();
			if (!$context) {
				return '';
			}
			return $this->cached('categories', $context->getId(), function () use ($request, $context) {
				return $this->buildCategoryCardsHtml($request, $context);
			}, $this->contentSignature($context->getId()));
		} catch (\Throwable $e) {
			error_log('manuscriptScholar: unable to build category cards: ' . $e->getMessage());
			$this->note('category cards error: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 160));
			return '';
		}
	}

	protected function buildCategoryCardsHtml($request, $context) {
		$contextId = (int) $context->getId();
		$locales = array_values(array_unique(array_filter(array(
			AppLocale::getLocale(),
			$context->getPrimaryLocale(),
		))));
		$pdo = \Illuminate\Database\Capsule\Manager::connection()->getPdo();
		$issueId = MsCategoryCards::currentIssueId($pdo, $contextId);
		if (!$issueId) {
			$this->note('category cards: the journal has no current issue');
			return '';
		}
		$categories = MsCategoryCards::load($pdo, $contextId, $locales, self::CATEGORY_ARTICLE_LIMIT, $issueId);
		$this->note('categories with articles in current issue ' . $issueId . ': ' . count($categories));

		// One lookup for the articles of all cards
		$allIds = array();
		foreach ($categories as $category) {
			$allIds = array_merge($allIds, $category['submissionIds']);
		}
		$byId = array();
		foreach ($this->loadSubmissions($allIds, $contextId) as $submission) {
			$byId[(int) $submission->getId()] = $submission;
		}
		$cards = array();
		foreach ($categories as $category) {
			$submissions = array();
			foreach ($category['submissionIds'] as $id) {
				if (isset($byId[(int) $id])) {
					$submissions[] = $byId[(int) $id];
				}
			}
			$articles = $this->itemsFromSubmissions($submissions, $request, $context);
			if (!$articles) {
				continue;
			}
			$cards[] = array('title' => $category['title'], 'count' => $category['count'], 'articles' => $articles);
		}
		return MsCategoryCards::render($cards, array(
			'heading' => __('plugins.themes.manuscriptScholar.categories.heading'),
			'articles' => __('plugins.themes.manuscriptScholar.categories.articles'),
			'more' => '',
		));
	}

	/**
	 * Top five articles by usage: 'read' counts abstract page views and
	 * 'downloaded' counts file downloads. OJS 3.3 keeps usage in the `metrics`
	 * table. Returns no items when statistics are empty or unavailable.
	 */
	protected function itemsFromStats($kind, $contextId, $request, $context) {
		try {
			$rows = $this->queryMetrics($contextId, $kind);
			$ids = MsHomeHighlights::idsFromStats($rows, 10);
			$this->note('statistics (' . $kind . '): ' . count($ids) . ' articles with data');
			$totals = MsHomeHighlights::totalsFromStats($rows);
			$key = $kind === 'read' ? 'plugins.themes.manuscriptScholar.home.views' : 'plugins.themes.manuscriptScholar.home.downloads';
			$submissions = $this->loadSubmissions($ids, $contextId);
			return $this->itemsFromSubmissions(array_slice($submissions, 0, 5), $request, $context, function ($submission) use ($totals, $key) {
				$id = (int) $submission->getId();
				return isset($totals[$id]) ? __($key, array('count' => $this->formatCount($totals[$id]))) : '';
			});
		} catch (\Throwable $e) {
			error_log('manuscriptScholar: statistics unavailable for home highlights: ' . $e->getMessage());
			$this->note('statistics (' . $kind . ') error: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 160));
			return array();
		}
	}

	/**
	 * Submission ids with their usage total, highest first, from the OJS 3.3
	 * `metrics` table (all time).
	 *
	 * @return array list of array('submission_id' => int, 'total' => int)
	 */
	protected function queryMetrics($contextId, $kind) {
		$abstractType = defined('ASSOC_TYPE_SUBMISSION') ? ASSOC_TYPE_SUBMISSION : 0x0100009;
		$fileType = defined('ASSOC_TYPE_SUBMISSION_FILE') ? ASSOC_TYPE_SUBMISSION_FILE : 0x0000203;
		$types = $kind === 'read' ? array($abstractType) : array($fileType);

		$placeholders = implode(',', array_fill(0, count($types), '?'));
		$pdo = \Illuminate\Database\Capsule\Manager::connection()->getPdo();
		$statement = $pdo->prepare(
			'SELECT submission_id, SUM(metric) AS total FROM metrics
			WHERE context_id = ? AND submission_id IS NOT NULL AND assoc_type IN (' . $placeholders . ')
			GROUP BY submission_id ORDER BY total DESC LIMIT 10'
		);
		$statement->execute(array_merge(array((int) $contextId), array_map('intval', $types)));
		return $statement->fetchAll(\PDO::FETCH_ASSOC);
	}

	/** Number with thousands separators of the interface language (1.234 or 1,234) */
	protected function formatCount($number) {
		$locale = class_exists('AppLocale') ? (string) AppLocale::getLocale() : '';
		return strpos($locale, 'id') === 0 ? number_format((int) $number, 0, ',', '.') : number_format((int) $number);
	}

	/** Publication date in the journal's short date format (config date_format_short), else d-m-Y */
	protected function formatDate($date) {
		$time = strtotime((string) $date);
		if (!$time) {
			return '';
		}
		$format = class_exists('Config') ? (string) Config::getVar('general', 'date_format_short') : '';
		if ($format !== '' && function_exists('strftime')) {
			$text = @strftime($format, $time);
			if (is_string($text) && $text !== '') {
				return $text;
			}
		}
		return date('d-m-Y', $time);
	}

	/**
	 * @param callable|null $meta (submission, publication) => short text shown under the title
	 */
	protected function itemsFromSubmissions($submissions, $request, $context, $meta = null) {
		$items = array();
		foreach ($submissions as $submission) {
			$publication = $submission->getCurrentPublication();
			if (!$publication) {
				continue;
			}
			$title = (string) $publication->getLocalizedFullTitle();
			if ($title === '') {
				$title = (string) $publication->getLocalizedTitle();
			}
			if ($title === '') {
				continue;
			}
			$items[] = array(
				'title' => $title,
				'url' => $request->getDispatcher()->url($request, ROUTE_PAGE, $context->getPath(), 'article', 'view', array($submission->getBestId())),
				'meta' => $meta ? (string) call_user_func($meta, $submission, $publication) : '',
			);
		}
		return $items;
	}

	/**
	 * Add the "Issue Coverage" box to the issue table of contents by
	 * filtering the rendered page, so the parent theme's template stays
	 * untouched. Any failure leaves the page as OJS rendered it.
	 */
	public function addIssueCoverage($templateMgr) {
		$issue = $templateMgr->getTemplateVars('issue');
		if (!$issue) {
			$this->note('issue coverage: no issue on this page');
			return false;
		}
		$html = $this->buildIssueCoverage($issue);
		if ($html === '') {
			$this->note('issue coverage: nothing to show');
			return false;
		}
		$this->inject('issue coverage', function ($output) use ($html) {
			return MsIssueCoverage::inject($output, $html);
		});
		return false;
	}

	protected function buildIssueCoverage($issue) {
		try {
			return $this->cached('coverage-' . (int) $issue->getId() . '-h' . $this->homeCountry(), $issue->getJournalId(), function () use ($issue) {
				return $this->buildIssueCoverageHtml($issue);
			}, $this->contentSignature($issue->getJournalId(), (int) $issue->getId()));
		} catch (\Throwable $e) {
			error_log('manuscriptScholar: unable to build issue coverage: ' . $e->getMessage());
			$this->note('issue coverage error: ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 160));
			return '';
		}
	}

	/**
	 * Published articles of an issue and one record per author byline.
	 *
	 * @return array array('articles' => int, 'records' => array of array('key', 'country', 'name'), 'pages' => int,
	 *   'derived' => int bylines whose country was taken from the affiliation)
	 */
	protected function collectIssueAuthors($issue) {
		$submissions = Services::get('submission')->getMany(array(
			'contextId' => $issue->getJournalId(),
			'issueIds' => array((int) $issue->getId()),
			'status' => STATUS_PUBLISHED,
		));
		$articleCount = 0;
		$records = array();
		$pagesList = array();
		$index = $this->affiliationIndex();
		$derived = 0;
		foreach ($submissions as $submission) {
			$publication = $submission->getCurrentPublication();
			if (!$publication) {
				continue;
			}
			$articleCount++;
			$pages = $publication->getData('pages');
			$pagesList[] = is_scalar($pages) ? (string) $pages : '';
			$authors = $publication->getData('authors');
			if (!is_array($authors) && !($authors instanceof Traversable)) {
				continue;
			}
			foreach ($authors as $author) {
				$country = strtoupper(trim((string) $author->getData('country')));
				if (!preg_match('/^[A-Z]{2}$/', $country)) {
					// The Country field is empty: use the country that closes the affiliation, if any
					$country = MsCountryFromAffiliation::detect((string) $author->getLocalizedData('affiliation'), $index);
					if ($country !== '') {
						$derived++;
					}
				}
				$records[] = array(
					'key' => MsIssueCoverage::authorKey(
						$author->getData('email'),
						$author->getFullName(),
						$author->getLocalizedData('affiliation')
					),
					'country' => $country,
					'name' => (string) $author->getFullName(),
				);
			}
		}
		return array('articles' => $articleCount, 'records' => $records, 'pages' => MsIssueCoverage::totalPages($pagesList), 'derived' => $derived);
	}

	protected function buildIssueCoverageHtml($issue) {
		$collected = $this->collectIssueAuthors($issue);
		$articleCount = $collected['articles'];
		$summary = MsIssueCoverage::summarize($collected['records']);
		$this->note('issue coverage: issue ' . (int) $issue->getId() . ', ' . $articleCount . ' published articles, ' . $summary['authors'] . ' authors, '
			. count($summary['countries']) . ' countries (' . implode(', ', array_keys($summary['countries'])) . '), '
			. MsIssueCoverage::countWithoutCountry($collected['records']) . ' author bylines without a valid country'
			. (MsIssueCoverage::countWithoutCountry($collected['records']) ? ' (' . implode('; ', MsIssueCoverage::namesWithoutCountry($collected['records'])) . ')' : '')
			. ', ' . count($collected['records']) . ' bylines in total, ' . $collected['pages'] . ' pages in total, '
			. $collected['derived'] . ' countries taken from the affiliation');
		$foreign = MsIssueCoverage::foreignShare($summary, $this->homeCountry());
		$names = array();
		foreach (array_keys($summary['countries']) as $code) {
			$names[$code] = $this->getCountryName($code);
		}
		$flagUrl = function ($code) {
			return $this->getFlagUrl($code);
		};
		return MsIssueCoverage::render($articleCount, $summary, $names, $flagUrl, array(
			'total' => __('plugins.themes.manuscriptScholar.issue.totalArticles', array('count' => $articleCount)),
			'pages' => $collected['pages'] > 0 ? __('plugins.themes.manuscriptScholar.issue.totalPages', array('count' => $collected['pages'])) : '',
			'coverage' => __('plugins.themes.manuscriptScholar.issue.coverage', array(
				'authors' => $summary['authors'],
				'countries' => count($summary['countries']),
			)),
			'foreign' => $foreign ? __('plugins.themes.manuscriptScholar.issue.foreign', array(
				'count' => $foreign['foreign'],
				'percent' => $foreign['percent'],
			)) : '',
		));
	}

	/**
	 * Add a small card (authors, countries with flags, articles) to each issue
	 * of the Archives page. Needs the standard issue blocks of the Default
	 * Theme; when the number of blocks does not match the issues, nothing is
	 * added.
	 */
	public function addArchiveStats($templateMgr) {
		$issues = $templateMgr->getTemplateVars('issues');
		if (empty($issues)) {
			$this->note('archive stats: no issues on this page');
			return false;
		}
		if (function_exists('set_time_limit')) {
			// a long Archives list on a cold cache can take a while
			@set_time_limit(120);
		}
		$cards = array();
		$failed = 0;
		foreach ($issues as $issue) {
			try {
				$cards[] = $this->buildArchiveCard($issue);
			} catch (\Throwable $e) {
				$failed++;
				$cards[] = '';
				error_log('manuscriptScholar: unable to build the card of issue ' . $issue->getId() . ': ' . $e->getMessage());
			}
		}
		$this->note('archive stats: ' . count($cards) . ' issues, ' . $failed . ' failed');
		$this->inject('archive stats', function ($output) use ($cards) {
			return MsArchiveStats::inject($output, $cards);
		});
		return false;
	}

	protected function buildArchiveCard($issue) {
		return $this->cached('archive-' . (int) $issue->getId() . '-h' . $this->homeCountry(), $issue->getJournalId(), function () use ($issue) {
			$collected = $this->collectIssueAuthors($issue);
			$summary = MsIssueCoverage::summarize($collected['records']);
			$names = array();
			foreach (array_keys($summary['countries']) as $code) {
				$names[$code] = $this->getCountryName($code);
			}
			return MsArchiveStats::render($collected['articles'], $summary, $names, function ($code) {
				return $this->getFlagUrl($code);
			}, array(
				'authors' => __('plugins.themes.manuscriptScholar.archive.authors'),
				'countries' => __('plugins.themes.manuscriptScholar.archive.countries'),
				'articles' => __('plugins.themes.manuscriptScholar.archive.articles'),
				'summary' => __('plugins.themes.manuscriptScholar.archive.summary'),
				'pages' => __('plugins.themes.manuscriptScholar.archive.pages'),
				'foreign' => __('plugins.themes.manuscriptScholar.archive.foreign'),
			), $collected['pages'], MsIssueCoverage::foreignShare($summary, $this->homeCountry()));
		}, $this->contentSignature($issue->getJournalId(), (int) $issue->getId()));
	}

	/**
	 * URL of the bundled flag for an ISO alpha-2 code, or '' when absent.
	 */
	protected function getFlagUrl($code) {
		$file = strtolower($code) . '.svg';
		if (!preg_match('/^[a-z]{2}\.svg$/', $file) || !is_file(dirname(__FILE__) . '/flags/' . $file)) {
			return '';
		}
		return $this->getAssetUrl('flags/' . $file);
	}

	/**
	 * URL of a file in the theme folder, or '' when it does not exist.
	 */
	protected function getAssetUrl($relativePath) {
		if (!is_file(dirname(__FILE__) . '/' . $relativePath)) {
			return '';
		}
		$request = Application::get()->getRequest();
		return rtrim($request->getBaseUrl(), '/') . '/' . $this->getPluginPath() . '/' . $relativePath;
	}

	/**
	 * Localized country name for an ISO alpha-2 code, or '' when unknown.
	 */
	protected function getCountryName($code) {
		$code = strtoupper(trim((string) $code));
		if (!preg_match('/^[A-Z]{2}$/', $code)) {
			return '';
		}
		$this->loadCountryNames();
		return isset($this->countryNames[$code]) ? $this->countryNames[$code] : '';
	}

	protected function loadCountryNames() {
		if ($this->countryNames !== null) {
			return;
		}
		$this->countryNames = array();
		$this->countryNamesEnglish = array();
		try {
			$factory = new \Sokil\IsoCodes\IsoCodesFactory();
			foreach ($factory->getCountries() as $country) {
				$code = strtoupper($country->getAlpha2());
				$this->countryNames[$code] = $country->getLocalName();
				$this->countryNamesEnglish[$code] = $country->getName();
			}
		} catch (\Throwable $e) {
			error_log('manuscriptScholar: unable to load country names: ' . $e->getMessage());
		}
	}

	/**
	 * Lookup table (country name in the interface language and in English, plus
	 * common short forms) used to read the country from an affiliation.
	 */
	protected function affiliationIndex() {
		if ($this->affiliationIndex === null) {
			$this->loadCountryNames();
			$this->affiliationIndex = MsCountryFromAffiliation::index(array($this->countryNames, $this->countryNamesEnglish));
		}
		return $this->affiliationIndex;
	}
}
