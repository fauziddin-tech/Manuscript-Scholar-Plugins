<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Standalone check of the author-country hook; no OJS installation needed.
function import($class) {}
class ThemePlugin {
	public $calls = array();
	public function getOption($name) { return null; }
	public function setParent($name) { $this->calls[] = array('setParent', $name); }
	public function modifyStyle($name, $args) { $this->calls[] = array('modifyStyle', $name, $args); }
	public function addStyle($name, $path, $args = array()) { $this->calls[] = array('addStyle', $name); }
	public function addOption($name, $type, $args) { $this->calls[] = array('addOption', $name, $type, $args); }
	public function removeOption($name) { $this->calls[] = array('removeOption', $name); }
	public function removeStyle($name) { $this->calls[] = array('removeStyle', $name); }
	public function saveOption($name, $value, $contextId = null) { $this->calls[] = array('saveOption', $name, $value); }
}
class HookRegistry { public static function register() {} }
class AppLocale { public static function getLocale() { return 'en_US'; } }
define('STYLE_SEQUENCE_LAST', 9);
define('STATUS_PUBLISHED', 3);
function __($key, $params = array()) {
	$map = array(
		'plugins.themes.manuscriptScholar.issue.totalArticles' => 'Total Articles: {$count}',
		'plugins.themes.manuscriptScholar.issue.totalPages' => 'Total Pages: {$count}',
		'plugins.themes.manuscriptScholar.issue.coverage' => 'Issue Coverage: {$authors} Author(s) from {$countries} Country(s)',
		'plugins.themes.manuscriptScholar.article.showAbstract' => 'Show Abstract',
		'plugins.themes.manuscriptScholar.article.hideAbstract' => 'Hide Abstract',
		'plugins.themes.manuscriptScholar.article.download' => 'Download PDF',
		'plugins.themes.manuscriptScholar.article.scholar' => 'Check in Google Scholar',
		'plugins.themes.manuscriptScholar.home.latest' => 'Latest articles',
		'plugins.themes.manuscriptScholar.home.mostRead' => 'Most read',
		'plugins.themes.manuscriptScholar.home.mostDownloaded' => 'Most downloaded',
		'plugins.themes.manuscriptScholar.home.views' => '{$count} views',
		'plugins.themes.manuscriptScholar.home.downloads' => '{$count} downloads',
	);
	$map['plugins.themes.manuscriptScholar.issue.foreign'] = 'Foreign authors: {$count} ({$percent}%)';
	$map['plugins.themes.manuscriptScholar.archive.foreign'] = 'foreign';
	$text = isset($map[$key]) ? $map[$key] : $key;
	foreach ($params as $k => $v) { $text = str_replace('{$' . $k . '}', (string) $v, $text); }
	return $text;
}
class FakeSubmissionService {
	public $subs;
	public function getMany($params) { return new ArrayIterator($this->subs); }
}
class Services {
	public static $service;
	public static $services = array();
	public static function get($name) { return isset(self::$services[$name]) ? self::$services[$name] : self::$service; }
}
define('ROUTE_PAGE', 1);
function fp($s) { return '<html><body>' . $s . '</body></html>'; }
class Application {
	public static function get() { return new self(); }
	public function getRequest() { return new self(); }
	public function getBaseUrl() { return 'https://example.org/'; }
	public function getContext() { return new FakeContext(); }
	public function getDispatcher() { return new FakeDispatcher(); }
}
class FakeContext { public function getId() { return 1; } public function getPath() { return 'aulad'; } }
class FakeDispatcher {
	public function url($request, $route, $path, $page, $op, $args) { return "https://example.org/index.php/$path/$page/$op/" . implode('/', $args); }
}
require __DIR__ . '/../ManuscriptScholarThemePlugin.inc.php';
require_once __DIR__ . '/../classes/CssMinifier.inc.php';

class FakeAuthor {
	public $data;
	public function __construct($data) { $this->data = $data; }
	public function getData($k) { return $this->data[$k] ?? null; }
	public function setData($k, $v, $locale = null) { $this->data[$k] = $v; }
	public function getLocalizedData($k) { return $this->data[$k] ?? ''; }
	public function getFullName() { return $this->data['name'] ?? ''; }
}
class FakePublication {
	public $authors; public $pages = '';
	public function __construct($a) { $this->authors = $a; }
	public function getData($k) { return $k === 'pages' ? $this->pages : $this->authors; }
}
class FakeTemplateMgr {
	public $pub;
	public function __construct($p) { $this->pub = $p; }
	public function getTemplateVars($k) { return $this->pub; }
}
class TestTheme extends ManuscriptScholarThemePlugin {
	public $enabled = true;
	public $debug = false;
	public $ttl = 0;
	public static $cacheDir = '';
	protected function cacheTtl() { return $this->ttl; }
	protected function cacheDir() { return self::$cacheDir; }
	public function cachedFor($kind, $contextId, $build, $signature = '') { return $this->cached($kind, $contextId, $build, $signature); }
	public function loadFor(array $ids, $contextId) { return $this->loadSubmissions($ids, $contextId); }
	public static $metricRows = array();
	protected function queryMetrics($contextId, $kind) { return self::$metricRows[$kind]; }
	protected function canSeeDebug() { return $this->debug; }
	public function getPluginPath() { return 'plugins/themes/manuscriptScholar'; }
	protected function getCountryName($code) {
		$names = array('ID' => 'Indonesia', 'MY' => 'Malaysia');
		return $names[strtoupper(trim((string) $code))] ?? '';
	}
}

$fail = 0;
function check($label, $actual, $expected) {
	global $fail;
	if ($actual !== $expected) {
		$fail++;
		echo "FAIL $label: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	}
}

// Author countries are added to the rendered page
$html = '<ul class="item authors"><li>
	<span class="name">Muneeza Arshad</span>
	<span class="affiliation">
		Universitas Pendidikan Indonesia
	</span></li><li><span class="name">Asep Deni Gustiana</span></li><li><span class="name">O&#039;Neil &amp; Co</span><span class="affiliation">Univ D, Malaysia</span></li><li><span class="name">Unknown Person</span><span class="affiliation">X</span></li></ul>';
$map = array(
	ManuscriptScholarThemePlugin::normalizeName('Muneeza  Arshad') => 'Indonesia',
	ManuscriptScholarThemePlugin::normalizeName('Asep Deni Gustiana') => 'Indonesia',
	ManuscriptScholarThemePlugin::normalizeName("O'Neil & Co") => 'Malaysia',
);
$out = ManuscriptScholarThemePlugin::insertAuthorCountries($html, $map);
check('country joined to the affiliation with a comma', strpos($out, "Universitas Pendidikan Indonesia, Indonesia</span>") !== false && strpos($out, "Indonesia\n\t</span><span class=\"ms-country-line\"") === false, true);
check('country after name when no affiliation', strpos($out, '<span class="name">Asep Deni Gustiana</span><span class="ms-country-line">Indonesia</span>') !== false, true);
check('country added even when the affiliation names it', strpos($out, 'Univ D, Malaysia, Malaysia</span>') !== false, true);
check('unknown author untouched', strpos($out, '<span class="name">Unknown Person</span><span class="affiliation">X</span></li>') !== false, true);
check('only the author without affiliation gets a country line', substr_count($out, 'ms-country-line'), 1);
check('trailing comma and space in the affiliation do not double up', strpos(ManuscriptScholarThemePlugin::insertAuthorCountries('<span class="name">A</span><span class="affiliation">Univ X, </span>', array('a' => 'Peru')), '<span class="affiliation">Univ X, Peru</span>') !== false, true);
check('country name escaped', ManuscriptScholarThemePlugin::insertAuthorCountries('<span class="name">A</span>', array('a' => 'C<x>')), '<span class="name">A</span><span class="ms-country-line">C&lt;x&gt;</span>');

$authors = array(
	new FakeAuthor(array('name' => 'Ann One', 'affiliation' => 'Univ A', 'country' => 'ID')),
	new FakeAuthor(array('name' => 'Bob Two', 'affiliation' => '', 'country' => 'MY')),
	new FakeAuthor(array('name' => 'Cy Three', 'affiliation' => 'Univ C', 'country' => '')),
	new FakeAuthor(array('name' => 'Di Four', 'affiliation' => 'Univ D', 'country' => 'XX')),
);
class ArticleMgr {
	public $filters = array(); public $pub;
	public function __construct($pub) { $this->pub = $pub; }
	public function getTemplateVars($k) { return $this->pub; }
	public function registerFilter($type, $cb) { $this->filters[] = $cb; }
	public function apply($html) { foreach ($this->filters as $f) { $html = call_user_func($f, $html); } return $html; }
}
$theme = new TestTheme();
$theme->debug = true;
$mgr = new ArticleMgr(new FakePublication($authors));
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/article.tpl'));
$page = fp('<ul class="item authors"><li><span class="name">Ann One</span><span class="affiliation">Univ A</span></li><li><span class="name">Bob Two</span></li><li><span class="name">Cy Three</span><span class="affiliation">Univ C</span></li><li><span class="name">Di Four</span><span class="affiliation">Univ D</span></li></ul>');
$out = $mgr->apply($page);
check('page: author 1 country', strpos($out, 'Univ A, Indonesia</span>') !== false, true);
check('page: author 2 country', strpos($out, '<span class="name">Bob Two</span><span class="ms-country-line">Malaysia</span>') !== false, true);
check('page: no country, no span', strpos($out, 'Univ C</span></li>') !== false && strpos($out, 'Univ D</span></li>') !== false, true);
check('page: note and status', strpos($out, 'author country: 2 of 4 authors have a valid country') !== false && strpos($out, 'author country: inserted') !== false, true);
check('authors data untouched', $authors[0]->data['affiliation'], 'Univ A');

$mgr = new ArticleMgr(new FakePublication($authors));
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/search.tpl'));
check('other template: no author injection', strpos($mgr->apply($page), 'ms-country-line'), false);

// Manuscript's stylesheet and the colours reach the parent theme's LESS, after setParent
$theme = new TestTheme();
$theme->init();
$names = array_map(function ($c) { return $c[0]; }, $theme->calls);
check('init order: parent first, then the Manuscript LESS', array_slice($names, 0, 2), array('setParent', 'modifyStyle'));
check('init: Manuscript LESS added to the parent stylesheet', array($theme->calls[1][1], $theme->calls[1][2]), array('stylesheet', array('addLess' => array('styles/index.less'))));
check('init: typography and homepage-image options removed', array_values(array_map(function ($c) { return $c[1]; }, array_filter($theme->calls, function ($c) { return $c[0] === 'removeOption'; }))), array('typography', 'useHomepageImageAsHeader'));
check('init: parent fonts dequeued', count(array_filter($theme->calls, function ($c) { return $c[0] === 'removeStyle' && $c[1] === 'font'; })), 11);
$lessCalls = array_values(array_filter($theme->calls, function ($c) { return $c[0] === 'modifyStyle' && isset($c[2]['addLessVariables']); }));
check('init: one set of LESS variables, after the options', count($lessCalls) === 1 && array_search($lessCalls[0], $theme->calls, true) > array_search('addOption', $names, true), true);
$less = $lessCalls[0];
check('less target', $less[1], 'stylesheet');
foreach (array('@bg-base:#4B7D92;', '@primary:#4B7D92;', '@text-bg-base:#ffffff;', '@accent:#F7BC4A;') as $var) {
	check("less $var", strpos($less[2]['addLessVariables'], $var) !== false, true);
}
check('less: no stray characters', (bool) preg_match('/^(@[a-z-]+:[^;{}]+;)+$/', $less[2]['addLessVariables']), true);
$styles = array_values(array_filter($theme->calls, function ($c) { return $c[0] === 'addStyle'; }));
check('init: feature stylesheet last, no inline colours for the defaults', array_map(function ($c) { return $c[1]; }, $styles), array('manuscriptScholar'));
$theme->saveOption('baseColour', 'red;}body{display:none');
$theme->saveOption('accentColour', '#abc');
$theme->saveOption('manuscriptScholarHomeCountry', 'ID');
check('saveOption: only hex colours are stored', array_slice($theme->calls, -3), array(array('saveOption', 'baseColour', null), array('saveOption', 'accentColour', '#abc'), array('saveOption', 'manuscriptScholarHomeCountry', 'ID')));

// Issue coverage
$summary = IssueCoverage::summarize(array(
	array('key' => 'e:a', 'country' => 'id'),
	array('key' => 'e:a', 'country' => 'ID'),
	array('key' => 'e:b', 'country' => 'ID'),
	array('key' => 'e:c', 'country' => 'ph'),
	array('key' => 'e:d', 'country' => ''),
	array('key' => '', 'country' => 'ID'),
));
check('summary authors', $summary['authors'], 4);
check('summary countries', $summary['countries'], array('ID' => 2, 'PH' => 1));
check('author key: same name and email', IssueCoverage::authorKey(' A@X.org ', ' Efi  Marija '), IssueCoverage::authorKey('a@x.org', 'efi marija'));
check('author key: different names sharing one email stay different', IssueCoverage::authorKey('a@x.org', 'Ahmad') !== IssueCoverage::authorKey('a@x.org', 'Usarov Jabbor'), true);
check('author key: affiliation does not matter', IssueCoverage::authorKey('', 'Efi Marija', 'Univ A'), IssueCoverage::authorKey('', 'Efi Marija', 'Univ B'));
check('author key: name only and email only', array(IssueCoverage::authorKey('', 'Efi Marija'), IssueCoverage::authorKey('a@x.org', ''), IssueCoverage::authorKey('', '')), array('n:efi marija|e:', 'n:|e:a@x.org', ''));
$shared = IssueCoverage::summarize(array(array('key' => IssueCoverage::authorKey('shared@x.org', 'Ahmad'), 'country' => 'ID'), array('key' => IssueCoverage::authorKey('shared@x.org', 'Usarov Jabbor'), 'country' => 'UZ'), array('key' => IssueCoverage::authorKey('shared@x.org', 'Ahmad'), 'country' => 'ID')));
check('authors who share an email are all counted, each with his country', $shared, array('authors' => 2, 'countries' => array('ID' => 1, 'UZ' => 1)));
check('names without a valid country', IssueCoverage::namesWithoutCountry(array(array('name' => 'A', 'country' => 'ID'), array('name' => 'B', 'country' => ''), array('name' => 'B', 'country' => 'xx1'), array('name' => 'C'), array('name' => '', 'country' => ''), array('name' => 'D', 'country' => 'UZ')), 5), array('B', 'C'));
check('names without a valid country, limited', count(IssueCoverage::namesWithoutCountry(array(array('name' => 'A'), array('name' => 'B'), array('name' => 'C')), 2)), 2);

$html = IssueCoverage::render(12, $summary, array('ID' => 'Indonesia', 'PH' => 'Philippines <x>'), function ($c) {
	return $c === 'ID' ? 'https://e.org/flags/id.svg' : '';
}, array('total' => 'Total Articles: 12', 'coverage' => 'Issue Coverage: 4 Author(s) from 2 Country(s)'));
check('render has flag', strpos($html, 'src="https://e.org/flags/id.svg"') !== false, true);
check('render no flag for PH', substr_count($html, '<img'), 1);
check('render escapes', strpos($html, 'Philippines &lt;x&gt;') !== false, true);
check('render counts', strpos($html, '(2)') !== false && strpos($html, '(1)') !== false, true);
check('render zero articles', IssueCoverage::render(0, $summary, array(), function () { return ''; }, array('total' => 'x', 'coverage' => 'y')), '');

$page = '<div class="obj_issue_toc"><div class="heading">h</div><div class="sections"><p>s $1 \\0</p></div></div>';
$out = IssueCoverage::inject($page, '<b>BOX $1</b>');
check('inject before sections', strpos($out, '<b>BOX $1</b><div class="sections"') !== false, true);
check('inject keeps content', strpos($out, 's $1 \\0') !== false, true);
$heading = '<div class="obj_issue_toc"><div class="heading"><a class="cover" href="#"><img src="c.jpg"></a><div class="description">d</div><div class="published">p</div></div><div class="sections"><div class="description">x</div></div></div>';
$out = IssueCoverage::inject($heading, '<b>BOX</b>');
check('inject beside cover', strpos($out, '</a><b>BOX</b><div class="description">d</div>') !== false, true);
check('inject once', substr_count($out, '<b>BOX</b>'), 1);
$noDesc = '<div class="obj_issue_toc"><div class="heading"><a class="cover" href="#"></a><div class="published">p</div></div><div class="sections"></div></div>';
check('inject before published', strpos(IssueCoverage::inject($noDesc, '<b>BOX</b>'), '</a><b>BOX</b><div class="published">') !== false, true);
$noAnchor = '<div class="obj_issue_toc"><div class="heading"><a class="cover" href="#"></a></div><div class="sections"></div></div>';
check('no anchor in heading falls back to sections', strpos(IssueCoverage::inject($noAnchor, '<b>BOX</b>'), '<b>BOX</b><div class="sections"') !== false, true);
$out = IssueCoverage::inject('<div class="obj_issue_toc"><p>x</p></div>', '<b>B</b>');
check('inject fallback', $out, '<div class="obj_issue_toc"><b>B</b><p>x</p></div>');
check('inject no toc', IssueCoverage::inject('<p>no</p>', '<b>B</b>'), '<p>no</p>');

// End to end through the theme, with fake OJS services
class FakeIssue { public function getJournalId() { return 1; } public function getId() { return 7; } }
class FakeSubmission {
	public $pub;
	public function __construct($authors) { $this->pub = new FakePublication($authors); }
	public function getCurrentPublication() { return $this->pub; }
}
class FilterMgr {
	public $filter;
	public function getTemplateVars($k) { return new FakeIssue(); }
	public function registerFilter($type, $cb) { $this->filter = $cb; }
}
Services::$service = new FakeSubmissionService();
Services::$service->subs = array(
	new FakeSubmission(array(
		new FakeAuthor(array('email' => 'a@x.org', 'name' => 'A', 'country' => 'ID')),
		new FakeAuthor(array('email' => 'b@x.org', 'name' => 'B', 'country' => 'MY')),
	)),
	new FakeSubmission(array(new FakeAuthor(array('email' => 'a@x.org', 'name' => 'A', 'country' => 'ID')))),
);
$theme = new TestTheme();
$mgr = new FilterMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/issue.tpl'));
check('filter registered', is_callable($mgr->filter), true);
$out = call_user_func($mgr->filter, fp('<div class="obj_issue_toc"><div class="sections"></div></div>'));
check('e2e totals', strpos($out, 'Total Articles: 2') !== false, true);
check('e2e coverage', strpos($out, 'Issue Coverage: 2 Author(s) from 2 Country(s)') !== false, true);
check('e2e names', strpos($out, 'Indonesia') !== false && strpos($out, 'Malaysia') !== false, true);
check('e2e flag', strpos($out, 'https://example.org/plugins/themes/manuscriptScholar/flags/id.svg') !== false, true);
check('e2e flag exists on disk', is_file(__DIR__ . '/../flags/id.svg') && is_file(__DIR__ . '/../flags/my.svg'), true);

// Home highlights
check('ids from stats', HomeHighlights::idsFromStats(array(array('submission_id' => 5), (object) array('submissionId' => 7), array('id' => 5), array('x' => 1), array('id' => 9)), 3), array(5, 7, 9));
check('ids from junk', HomeHighlights::idsFromStats(null), array());
$cardsHtml = HomeHighlights::render(array(
	array('title' => 'Latest articles', 'items' => array(array('title' => 'A <b>', 'url' => 'https://e.org/a?x=1&y=2'))),
	array('title' => 'Empty', 'items' => array()),
));
check('cards render', substr_count($cardsHtml, '<section'), 1);
check('cards escape', strpos($cardsHtml, 'A &lt;b&gt;') !== false && strpos($cardsHtml, 'x=1&amp;y=2') !== false, true);
check('totals from stats', HomeHighlights::totalsFromStats(array(array('submission_id' => 5, 'total' => '12'), (object) array('submissionId' => 7, 'metric' => 3), array('id' => 5, 'total' => 99), array('x' => 1, 'total' => 4), array('id' => 9))), array(5 => 12, 7 => 3));
check('totals from junk', HomeHighlights::totalsFromStats(null), array());
check('card item with a meta line, escaped', strpos(HomeHighlights::render(array(array('title' => 'T', 'items' => array(array('title' => 'A', 'url' => 'u', 'meta' => '1 <b>'))))), '<a href="u">A</a><span class="ms-highlight__meta">1 &lt;b&gt;</span></li>') !== false, true);
check('card item without meta has no meta element', strpos(HomeHighlights::render(array(array('title' => 'T', 'items' => array(array('title' => 'A', 'url' => 'u'))))), 'ms-highlight__meta'), false);
check('cards empty', HomeHighlights::render(array(array('title' => 'x', 'items' => array()))), '');
check('inject footer', HomeHighlights::inject('<p>main</p><div class="pkp_structure_footer_wrapper" role="contentinfo">f</div>', '<i>C</i>'), '<p>main</p><i>C</i><div class="pkp_structure_footer_wrapper" role="contentinfo">f</div>');
check('inject no footer', HomeHighlights::inject('<p>x</p>', '<i>C</i>'), '<p>x</p>');

class HSub {
	public $id; public $status; public $title;
	public function __construct($id, $title, $status = 3) { $this->id = $id; $this->title = $title; $this->status = $status; }
	public function getData($k) { return $k === 'contextId' ? 1 : $this->status; }
	public function getBestId() { return $this->id; }
	public function getId() { return $this->id; }
	public function getCurrentPublication() { return new HPub($this->title); }
}
class HPub { public $t; public function getData($k) { return $k === 'datePublished' ? '2026-09-01' : array(); } public function __construct($t) { $this->t = $t; } public function getLocalizedFullTitle() { return $this->t; } public function getLocalizedTitle() { return $this->t; } }
class HomeMgr {
	public $filters = array();
	public function getTemplateVars($k) { return new FakeIssue(); }
	public function registerFilter($type, $cb) { $this->filters[] = $cb; }
	public function apply($html) { foreach ($this->filters as $f) { $html = call_user_func($f, $html); } return $html; }
}
class HSubService {
	public $all = array();
	public function getMany($p) { return new ArrayIterator(array($this->all[1], $this->all[2])); }
	public function get($id) { return isset($this->all[$id]) ? $this->all[$id] : null; }
}
class HStats {
	public $rows;
	public function getTotals($args) { if ($args['orderBy'] === 'boom') throw new Exception('x'); return $this->rows[$args['orderBy']]; }
}
$subs = new HSubService();
$subs->all = array(1 => new HSub(1, 'Latest One'), 2 => new HSub(2, 'Latest Two'), 3 => new HSub(3, 'Read Three'), 4 => new HSub(4, 'Draft Four', 1));
$stats = new HStats();
TestTheme::$metricRows = array(
	'read' => array(array('submission_id' => 3, 'total' => 50), array('submission_id' => 4, 'total' => 40), array('submission_id' => 99, 'total' => 1)),
	'downloaded' => array(),
);
Services::$services = array('submission' => $subs);
$theme = new TestTheme();
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/indexJournal.tpl'));
check('home filter registered', count($mgr->filters) >= 1, true);
$out = $mgr->apply(fp('<main>m</main><div class="pkp_structure_footer_wrapper">f</div>'));
check('home latest card', strpos($out, 'Latest articles') !== false && strpos($out, 'Latest One') !== false, true);
check('home latest url', strpos($out, 'https://example.org/index.php/aulad/article/view/1') !== false, true);
check('home latest shows the publication date', strpos($out, '<span class="ms-highlight__meta">01-09-2026</span>') !== false, true);
check('home most read shows the number of views', strpos($out, 'Read Three</a><span class="ms-highlight__meta">50 views</span>') !== false, true);
check('home most read skips drafts and unknown', strpos($out, 'Read Three') !== false && strpos($out, 'Draft Four') === false, true);
check('home empty card hidden', strpos($out, 'Most downloaded') === false, true);
check('home before footer', strpos($out, 'ms-highlights') < strpos($out, 'pkp_structure_footer_wrapper'), true);
TestTheme::$metricRows['read'] = array();
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/indexJournal.tpl'));
$out = $mgr->apply(fp('<div class="pkp_structure_footer_wrapper">f</div>'));
check('home still shows latest without stats', strpos($out, 'Latest One') !== false && strpos($out, 'Most read') === false, true);
// Article action line (Show Abstract | Download | Google Scholar)
$list = '<div class="obj_article_summary"><h3 class="title"><a id="article-12" href="x/article/view/12">One</a></h3><div class="meta"><div class="authors">Ann, Bob</div><div class="pages">1-5</div></div><ul class="galleys_links"><li><a class="obj_galley_link pdf" href="https://e.org/article/view/12/5?x=1&amp;y=2" aria-labelledby="article-12">PDF</a></li><li><a class="obj_galley_link file" href="https://e.org/article/view/12/6">HTML</a></li></ul></div>'
	. '<div class="obj_article_summary"><h3 class="title"><a id="article-13" href="x/article/view/13">Two</a></h3><div class="meta"><div class="authors">Cy</div></div></div>'
	. '<div class="obj_article_summary"><h3 class="title"><a id="article-14" href="x/article/view/14">Three</a></h3><div class="meta"><div class="pages">9</div></div></div>'
	. '<div class="sidebar"><div class="authors">not an article</div></div>';
check('ids from output', ArticleActions::idsFromOutput($list), array(12, 13, 14));
$labels = array('show' => 'Show Abstract', 'hide' => 'Hide Abstract', 'download' => 'Download PDF', 'scholar' => 'Check in Google Scholar');
$data = array(
	12 => array('title' => 'One & "Two"', 'abstract' => '<p>An abstract.</p>', 'url' => 'https://e.org/article/view/12', 'download' => 'https://e.org/article/download/12/5'),
	13 => array('title' => 'Two', 'abstract' => '', 'url' => 'https://e.org/article/view/13', 'download' => ''),
	14 => array('title' => 'Three', 'abstract' => '<p>x</p>', 'url' => 'https://e.org/article/view/14', 'download' => ''),
);
$out = ArticleActions::inject($list, $data, $labels);
check('actions after authors', strpos($out, '<div class="authors">Ann, Bob</div><p class="ms-article-actions"><a href="https://e.org/article/view/12" class="ms-show-abstract"') !== false, true);
check('three links with separators', substr_count(substr($out, 0, strpos($out, '</p>')), '<span class="ms-sep">|</span>'), 2);
check('abstract hidden after the line, linked by id', preg_match('#</p><div class="ms-abstract" id="(ms-abstract-12-\d+)" hidden><p>An abstract.</p></div>#', $out, $mm) === 1 && strpos($out, 'aria-controls="' . $mm[1] . '"') !== false, true);
check('abstract link is an accessible toggle', strpos($out, 'role="button" aria-expanded="false" aria-controls="ms-abstract-12-') !== false, true);
check('same article twice gets different panel ids', (function () use ($list, $data, $labels) { preg_match_all('/id="(ms-abstract-[^"]+)"/', ArticleActions::inject($list . $list, $data, $labels), $m); return count($m[1]) === count(array_unique($m[1])) && count($m[1]) > 1; })(), true);
check('download link is the old PDF button link', strpos($out, 'href="https://e.org/article/view/12/5?x=1&amp;y=2" class="ms-download">Download PDF') !== false, true);
check('pdf button removed', strpos($out, 'obj_galley_link pdf'), false);
check('other galley buttons kept', strpos($out, '<ul class="galleys_links"><li><a class="obj_galley_link file" href="https://e.org/article/view/12/6">HTML</a></li></ul>') !== false, true);
check('scholar link encoded', strpos($out, 'https://scholar.google.com/scholar?q=%2Bintitle%3A%22One%20%26%20%22Two%22%22') !== false, true);
check('no abstract, no download: only scholar', substr_count(substr($out, strpos($out, 'Cy')), 'ms-show-abstract'), 0);
check('article without authors untouched', strpos($out, '<div class="meta"><div class="pages">9</div></div>') !== false, true);
check('sidebar authors untouched', strpos($out, '<div class="authors">not an article</div></div>') !== false, true);
list($seg, $href) = ArticleActions::takePdfButton('<ul class="galleys_links"><li><a href="/v/1/2" class="obj_galley_link pdf">PDF</a></li></ul><p>x</p>');
check('only pdf button: list removed', $seg, '<p>x</p>');
check('only pdf button: href', $href, '/v/1/2');
list($seg, $href) = ArticleActions::takePdfButton('<ul class="galleys_links"><li><a class="obj_galley_link file" href="/h">HTML</a></li></ul>');
check('no pdf button: list kept', strpos($seg, 'HTML') !== false && $href === '', true);
check('inject no data', ArticleActions::inject($list, array(), $labels), $list);
check('scholar url spaces', ArticleActions::scholarUrl("A   B\nC"), 'https://scholar.google.com/scholar?q=%2Bintitle%3A%22A%20B%20C%22');

class AGalley { public $id; public $pdf; public function __construct($id, $pdf) { $this->id = $id; $this->pdf = $pdf; } public function isPdfGalley() { return $this->pdf; } public function getBestGalleyId() { return $this->id; } }
class APub { public function getLocalizedFullTitle() { return 'Full Title'; } public function getLocalizedTitle() { return 'T'; } public function getLocalizedData($k) { return '<p>Abs <b>x</b><script>alert(1)</script></p>'; } public function getData($k) { return array(new AGalley(7, false), new AGalley(8, true)); } public function getStoredPubId($type) { return $type === 'doi' ? '10.31004/aulad.v9i3.1779' : ''; } }
class ASub { public $id = 12; public function getId() { return $this->id; } public function getData($k) { return $k === 'contextId' ? 1 : 3; } public function getCurrentPublication() { return new APub(); } public function getBestId() { return $this->id; } }
class ASubService {
	public function get($id) { return $id === 12 ? new ASub() : null; }
	public function getMany($params) {
		$found = array();
		foreach ((isset($params['submissionIds']) ? $params['submissionIds'] : array()) as $id) { if ($id === 12) { $found[] = new ASub(); } }
		return new ArrayIterator($found);
	}
}
$prevServices = Services::$services;
Services::$services = array('submission' => new ASubService());
$theme = new TestTheme();
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/issue.tpl'));
$out = $mgr->apply(fp($list));
check('e2e actions inserted', strpos($out, 'class="ms-show-abstract"') !== false && strpos($out, 'Show Abstract') !== false, true);
check('e2e doi pill from the stored DOI', strpos($out, 'class="ms-doi" target="_blank" rel="noopener">DOI: 10.31004/aulad.v9i3.1779</a>') !== false, true);
check('e2e uses the page pdf button link', strpos($out, 'class="ms-download"') !== false && strpos($out, 'article/view/12/5?x=1&amp;y=2') !== false, true);
$noBtn = '<div class="obj_article_summary"><h3 class="title"><a id="article-12" href="x">One</a></h3><div class="meta"><div class="authors">Ann</div></div></div>';
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/issue.tpl'));
check('e2e fallback to the pdf galley download url', strpos($mgr->apply(fp($noBtn)), 'article/download/12/8') !== false, true);
check('e2e abstract tags limited', strpos($out, '<script>alert(1)</script>') === false && strpos($out, '<b>x</b>') !== false, true);
check('e2e unknown article ignored', substr_count($out, 'ms-article-actions'), 1);
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/article.tpl'));
check('article page: no list actions', strpos($mgr->apply(fp($list)), 'ms-article-actions'), false);
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/search.tpl'));
check('search page: actions inserted', strpos($mgr->apply(fp($list)), 'ms-article-actions') !== false, true);
Services::$services = $prevServices;

// Home page: shorten the current issue to N articles with a Read more button
function sampleArticle($n) { return '<li><div class="obj_article_summary"><h3 class="title"><a id="article-' . $n . '" href="https://e.org/article/view/' . $n . '">T' . $n . '</a></h3><div class="meta"><div class="authors">A' . $n . '</div></div><ul class="galleys_links"><li><a class="obj_galley_link pdf" href="https://e.org/article/view/' . $n . '/1">PDF</a></li></ul></div></li>'; }
function sampleHome($first, $second) {
	$a = ''; for ($i = 1; $i <= $first; $i++) { $a .= sampleArticle($i); }
	$b = ''; for ($i = 101; $i < 101 + $second; $i++) { $b .= sampleArticle($i); }
	return '<div class="page_index_journal"><section class="current_issue"><header><h2>Current Issue</h2></header><div class="obj_issue_toc"><div class="heading"><a class="cover" href="https://e.org/issue/view/29">c</a></div><div class="sections">'
		. '<div class="section"><h2>Research Articles</h2><ul class="cmp_article_list articles">' . $a . '</ul></div>'
		. ($second ? '<div class="section"><h2>Book Reviews</h2><ul class="cmp_article_list articles">' . $b . '</ul></div>' : '')
		. '</div></div><a href="https://e.org/issue/archive" class="read_more">View All Issues</a></section></div><div class="pkp_structure_footer_wrapper">f</div>';
}
function hiddenIds($html) { preg_match_all('/<li class="ms-more" hidden>.*?id="article-(\\d+)"/s', $html, $m); return array_map('intval', $m[1]); }
$out = HomeIssueTrim::trim(sampleHome(4, 3), 5, 'Read more', 'Show less');
check('fold: every article stays in the page', substr_count($out, '<div class="obj_article_summary"'), 7);
check('fold: the ones after the fifth are hidden', hiddenIds($out), array(102, 103));
check('fold: the fifth is the edge', strpos($out, '<li class="ms-edge"><div class="obj_article_summary"><h3 class="title"><a id="article-101"') !== false, true);
check('fold: block starts collapsed', strpos($out, '<section class="current_issue ms-collapsed">') !== false, true);
check('fold: the section with the edge is marked', strpos($out, '<div class="section ms-edge-section"><h2>Book Reviews</h2>') !== false, true);
check('fold: first section untouched', strpos($out, '<div class="section"><h2>Research Articles</h2>') !== false, true);
check('fold: sections wrapper gets an id', strpos($out, '<div class="sections" id="ms-issue-sections">') !== false, true);
check('fold button before View All Issues, with ARIA and labels', strpos($out, '<div class="ms-readmore"><a class="ms-readmore__button" href="https://e.org/issue/view/29" role="button" aria-expanded="false" aria-controls="ms-issue-sections" data-more="Read more" data-less="Show less">Read more</a></div><a href="https://e.org/issue/archive" class="read_more">') !== false, true);
check('galley list markup still balanced', substr_count($out, '<li') === substr_count($out, '</li>'), true);
$out = HomeIssueTrim::trim(sampleHome(4, 3), 4, 'Read more', 'Show less');
check('a section with only hidden articles is hidden with its heading', strpos($out, '<div class="section ms-more" hidden><h2>Book Reviews</h2>') !== false && hiddenIds($out) === array(101, 102, 103), true);
check('edge is the fourth article', strpos($out, '<li class="ms-edge"><div class="obj_article_summary"><h3 class="title"><a id="article-4"') !== false && strpos($out, 'ms-edge-section') !== false, true);
check('no fold at or below the limit', HomeIssueTrim::trim(sampleHome(3, 2), 5, 'Read more', 'Show less'), sampleHome(3, 2));
check('no current issue block', HomeIssueTrim::trim('<p>x</p>', 5, 'Read more', 'Show less'), '<p>x</p>');
$noLink = str_replace(' class="read_more">View All Issues</a>', '>x</a>', sampleHome(6, 0));
check('button at the end when there is no View All Issues link', strpos(HomeIssueTrim::trim($noLink, 5, 'Read more', 'Show less'), 'Read more</a></div></section>') !== false, true);
$noCover = str_replace('href="https://e.org/issue/view/29"', 'href="https://e.org/x"', sampleHome(6, 0));
check('without an issue link the button points at the sections', strpos(HomeIssueTrim::trim($noCover, 5, 'Read more', 'Show less'), 'href="#ms-issue-sections" role="button"') !== false, true);
check('labels escaped', strpos(HomeIssueTrim::trim(sampleHome(6, 0), 5, 'A&B', '"x"'), 'data-more="A&amp;B" data-less="&quot;x&quot;"') !== false, true);
check('classes added to an li that already has one', strpos(HomeIssueTrim::trim(str_replace('<li><div class="obj_article_summary"', '<li class="foo"><div class="obj_article_summary"', sampleHome(6, 0)), 5, 'm', 'l'), 'class="foo ms-more" hidden') !== false, true);

$prevServices2 = Services::$services;
Services::$services = array('submission' => new ASubService());
$theme = new TestTheme();
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/indexJournal.tpl'));
$out = $mgr->apply('<html><body>' . sampleHome(4, 3) . '</body></html>');
check('e2e home: folded after 5 with a button', substr_count($out, '<li class="ms-more" hidden>') === 2 && strpos($out, 'ms-readmore__button') !== false && strpos($out, 'data-less="plugins.themes.manuscriptScholar.home.readLess"') !== false, true);
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/issue.tpl'));
check('issue page is not shortened', substr_count($mgr->apply('<html><body>' . sampleHome(4, 3) . '</body></html>'), '<div class="obj_article_summary"'), 7);
Services::$services = $prevServices2;

// Cache buster on the theme stylesheet
check('buster adds version', ManuscriptScholarThemePlugin::addCacheBuster('<link rel="stylesheet" href="https://e.org/plugins/themes/manuscriptScholar/styles/manuscriptScholar.css" type="text/css" />', '1.5.2'), '<link rel="stylesheet" href="https://e.org/plugins/themes/manuscriptScholar/styles/manuscriptScholar.css?v=1.5.2" type="text/css" />');
check('buster replaces old query', ManuscriptScholarThemePlugin::addCacheBuster('<link href="/x/manuscriptScholar.css?v=1.0&amp;z=1">', '1.5.2'), '<link href="/x/manuscriptScholar.css?v=1.5.2">');
check('buster ignores other files', ManuscriptScholarThemePlugin::addCacheBuster('<link href="/x/other.css">', '1.5.2'), '<link href="/x/other.css">');

// Partial fetches (sidebar blocks) are left alone
$theme = new TestTheme();
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/indexJournal.tpl'));
check('partial output untouched', $mgr->apply('<div class="pkp_block">block</div>'), '<div class="pkp_block">block</div>');

// Font bump script is added to complete pages only
$theme = new TestTheme();
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/indexJournal.tpl'));
$out = $mgr->apply('<html><body><main>m</main></body></html>');
$tag = '<script src="https://example.org/plugins/themes/manuscriptScholar/js/manuscriptScholar.js?v=' . ManuscriptScholarThemePlugin::VERSION . '" defer></script>';
check('helper script tag with the version', strpos($out, $tag) !== false, true);
check('no inline script on the page', strpos($out, '<script>'), false);
check('script before body end', strpos($out, $tag) < strpos($out, '</body>'), true);
check('script absent on partials', strpos($mgr->apply('<div class="pkp_block">b</div>'), '<script'), false);

// Status comment in the page source
$theme = new TestTheme();
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/indexJournal.tpl'));
$out = $mgr->apply('<html><body><main>m</main><div class="pkp_structure_footer_wrapper">f</div></body></html>');
check('comment short for visitors', (bool) preg_match('/<!-- manuscriptScholar ' . preg_quote(ManuscriptScholarThemePlugin::VERSION, '/') . ' -->\s*<\/body>/', $out), true);
check('comment hides details from visitors', strpos($out, 'inserted'), false);
$theme->debug = true;
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/indexJournal.tpl'));
$out = $mgr->apply('<html><body><main>m</main><div class="pkp_structure_footer_wrapper">f</div></body></html>');
check('comment details for admins', strpos($out, 'home highlights: inserted') !== false && strpos($out, 'frontend/pages/indexJournal.tpl') !== false, true);
$out = $mgr->apply('<html><body><main>m</main></body></html>');
check('comment reports missing anchor', strpos($out, 'home highlights: no anchor found') !== false, true);
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'backend/dashboard.tpl'));
check('backend pages untouched', count($mgr->filters), 0);

// Category cards
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE categories (category_id INTEGER, context_id INTEGER, seq INTEGER)');
$pdo->exec('CREATE TABLE category_settings (category_id INTEGER, locale TEXT, setting_name TEXT, setting_value TEXT)');
$pdo->exec('CREATE TABLE publication_categories (publication_id INTEGER, category_id INTEGER)');
$pdo->exec('CREATE TABLE publications (publication_id INTEGER, submission_id INTEGER, date_published TEXT)');
$pdo->exec('CREATE TABLE submissions (submission_id INTEGER, context_id INTEGER, status INTEGER, current_publication_id INTEGER)');
$pdo->exec("INSERT INTO categories VALUES (1,1,2),(2,1,1),(3,1,3),(4,1,4),(5,2,1)");
$pdo->exec("INSERT INTO category_settings VALUES (1,'en_US','title','Education'),(1,'id_ID','title','Pendidikan'),(2,'en_US','title','Science'),(3,'en_US','title','Empty'),(4,'en_US','title','Unpublished'),(5,'en_US','title','Other journal')");
$pdo->exec("INSERT INTO submissions VALUES (10,1,3,100),(11,1,3,111),(12,1,3,120),(13,1,1,130),(14,2,3,140)");
$pdo->exec("INSERT INTO publications VALUES (100,10,'2024-01-01'),(110,11,'2023-01-01'),(111,11,'2025-01-01'),(120,12,'2022-01-01'),(130,13,'2025-06-01'),(140,14,'2025-01-01')");
// 110 is an old version of submission 11: must not count
$pdo->exec("INSERT INTO publication_categories VALUES (100,1),(110,1),(111,1),(120,2),(130,4),(140,5),(100,2)");
$cats = CategoryCards::load($pdo, 1, array('id_ID', 'en_US'), 20);
check('categories without published articles are left out', array_map(function ($c) { return $c['id']; }, $cats), array(2, 1));
check('category order follows seq', $cats[0]['title'], 'Science');
check('title in the preferred locale', $cats[1]['title'], 'Pendidikan');
check('count uses current publications only', array($cats[0]['count'], $cats[1]['count']), array(2, 2));
check('latest articles first', $cats[1]['submissionIds'], array(11, 10));
$cats = CategoryCards::load($pdo, 1, array('fr_FR'), 1);
check('title falls back to any locale', $cats[1]['title'] === 'Education' || $cats[1]['title'] === 'Pendidikan', true);
check('article list limited, count kept', array(count($cats[1]['submissionIds']), $cats[1]['count']), array(1, 2));
$pdo->exec('CREATE TABLE publication_settings (publication_id INTEGER, locale TEXT, setting_name TEXT, setting_value TEXT)');
$pdo->exec("INSERT INTO publication_settings VALUES (100,'','issueId','5'),(110,'','issueId','5'),(111,'','issueId','6'),(120,'','issueId','5'),(100,'','title','x'),(130,'','issueId','5'),(140,'','issueId','5')");
$pdo->exec('CREATE TABLE issues (issue_id INTEGER, journal_id INTEGER, current INTEGER)');
$pdo->exec("INSERT INTO issues VALUES (4,1,0),(5,1,1),(6,1,0),(7,2,1)");
check('current issue id', array(CategoryCards::currentIssueId($pdo, 1), CategoryCards::currentIssueId($pdo, 2), CategoryCards::currentIssueId($pdo, 3)), array(5, 7, 0));
$cats = CategoryCards::load($pdo, 1, array('en_US'), 20, 5);
check('current issue: only its categories, empty ones hidden', array_map(function ($c) { return $c['id']; }, $cats), array(2, 1));
check('current issue: counts and articles', array($cats[0]['count'], $cats[0]['submissionIds'], $cats[1]['count'], $cats[1]['submissionIds']), array(2, array(10, 12), 1, array(10)));
$cats = CategoryCards::load($pdo, 1, array('en_US'), 20, 6);
check('other issue: only its articles (current publication, not old version)', array_map(function ($c) { return array($c['id'], $c['count'], $c['submissionIds']); }, $cats), array(array(1, 1, array(11))));
check('no articles in issue: no cards', CategoryCards::load($pdo, 1, array('en_US'), 20, 4), array());
check('other journals ignored', CategoryCards::load($pdo, 2, array('en_US'), 5)[0]['title'], 'Other journal');

$cards = array(array('title' => 'Edu <b>', 'count' => 3, 'articles' => array(array('title' => 'A & B', 'url' => 'http://x/1?a=1&b=2'))));
$html = CategoryCards::render($cards, array('heading' => 'Categories', 'articles' => 'articles', 'more' => ''));
check('card html: details/summary', strpos($html, '<details class="ms-category"><summary>') !== false && strpos($html, '3 articles') !== false, true);
check('card html escaped', strpos($html, 'Edu &lt;b&gt;') !== false && strpos($html, 'A &amp; B') !== false && strpos($html, 'a=1&amp;b=2') !== false, true);
check('no cards, no html', CategoryCards::render(array(), array('heading' => 'x', 'articles' => 'y', 'more' => '')), '');
$page = '<div>about</div><section class="current_issue"><h2>x</h2></section>';
$out = CategoryCards::inject($page, $html);
check('cards inserted before current issue', strpos($out, 'ms-categories') < strpos($out, 'current_issue') && strpos($out, 'about') < strpos($out, 'ms-categories'), true);
check('inject without anchor leaves page', CategoryCards::inject('<p>x</p>', $html), '<p>x</p>');
check('inject with empty html leaves page', CategoryCards::inject($page, ''), $page);

// Article order by start page
function pagedArticle($n, $pages) { return str_replace('</div></li>', ($pages === null ? '' : '<div class="pages">' . $pages . '</div>') . '</div></li>', sampleArticle($n)); }
function ids($html) { preg_match_all('/id="article-(\\d+)"/', $html, $m); return array_map('intval', $m[1]); }
$list = '<ul class="cmp_article_list articles">' . pagedArticle(1, '977-988') . "\n" . pagedArticle(2, '1029-1038') . pagedArticle(3, null) . pagedArticle(4, '1028-1038') . pagedArticle(5, '989-1001') . '</ul>';
$out = ArticleOrder::sortByPages('<p>x</p>' . $list . '<p>y</p>');
check('articles ordered by start page, no page last', ids($out), array(1, 5, 4, 2, 3));
check('order keeps the markup balanced', substr_count($out, '<li') === substr_count($out, '</li>') && substr_count($out, '<ul') === substr_count($out, '</ul>'), true);
check('text around the list untouched', strpos($out, '<p>x</p><ul') === 0 && substr($out, -strlen('</ul><p>y</p>')) === '</ul><p>y</p>', true);
check('numeric, not alphabetical', ids(ArticleOrder::sortByPages('<ul>' . pagedArticle(1, '1000-1005') . pagedArticle(2, '99-101') . '</ul>')), array(2, 1));
check('letters in pages ignored', ids(ArticleOrder::sortByPages('<ul>' . pagedArticle(1, 'e30') . pagedArticle(2, 'e5') . '</ul>')), array(2, 1));
check('missing pages keep their order', ids(ArticleOrder::sortByPages('<ul>' . pagedArticle(1, null) . pagedArticle(2, null) . pagedArticle(3, '5') . '</ul>')), array(3, 1, 2));
check('same page keeps order', ids(ArticleOrder::sortByPages('<ul>' . pagedArticle(1, '7') . pagedArticle(2, '7') . '</ul>')), array(1, 2));
$twoSections = '<div class="section"><ul class="cmp_article_list">' . pagedArticle(1, '50') . pagedArticle(2, '10') . '</ul></div><div class="section"><ul class="cmp_article_list">' . pagedArticle(3, '20') . pagedArticle(4, '5') . '</ul></div>';
check('each section sorted on its own', ids(ArticleOrder::sortByPages($twoSections)), array(2, 1, 4, 3));
check('single article and no articles unchanged', ArticleOrder::sortByPages('<ul>' . pagedArticle(1, '5') . '</ul>') === '<ul>' . pagedArticle(1, '5') . '</ul>' && ArticleOrder::sortByPages('<p>x</p>') === '<p>x</p>', true);
check('sorted page unchanged', ArticleOrder::sortByPages('<ul>' . pagedArticle(1, '1') . pagedArticle(2, '2') . '</ul>'), '<ul>' . pagedArticle(1, '1') . pagedArticle(2, '2') . '</ul>');
// Order first, then the home page keeps the first five by page
$home = '<html><body>' . '<div class="page_index_journal"><section class="current_issue"><div class="sections"><div class="section"><h2>S</h2><ul class="cmp_article_list articles">'
	. pagedArticle(1, '60') . pagedArticle(2, '50') . pagedArticle(3, '40') . pagedArticle(4, '30') . pagedArticle(5, '20') . pagedArticle(6, '10') . pagedArticle(7, '5')
	. '</ul></div></div><a href="https://e.org/issue/view/1">x</a></section></div></body></html>';
$sorted = ArticleOrder::sortByPages($home);
check('home: sorted before folding shows the lowest pages first', array_slice(ids(HomeIssueTrim::trim($sorted, 5, 'Read more', 'Show less')), 0, 5), array(7, 6, 5, 4, 3));
$theme = new TestTheme();
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/indexJournal.tpl'));
$out = $mgr->apply($home . '<div class="pkp_structure_footer_wrapper">f</div>');
check('plugin: home sorted and folded', array(array_slice(ids($out), 0, 5), substr_count($out, 'ms-more')), array(array(7, 6, 5, 4, 3), 2));
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/issue.tpl'));
$out = $mgr->apply('<html><body><ul>' . pagedArticle(1, '9') . pagedArticle(2, '3') . '</ul></body></html>');
check('plugin: issue page sorted', ids($out), array(2, 1));
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/search.tpl'));
$out = $mgr->apply('<html><body><ul>' . pagedArticle(1, '9') . pagedArticle(2, '3') . '</ul></body></html>');
check('plugin: search results keep their own order', ids($out), array(1, 2));

// Dimensions badge
$doiPage = '<div class="item authors">a</div><section class="item doi"><span class="label">DOI:</span> <span class="value"><a href="https://doi.org/10.31004/aulad.v9i3.1759">https://doi.org/10.31004/aulad.v9i3.1759</a></span></section><div class="item keywords">k</div>';
check('doi from the DOI line', DimensionsBadge::doiFromOutput($doiPage), '10.31004/aulad.v9i3.1759');
check('doi from a div line, encoded', DimensionsBadge::doiFromOutput('<div class="item doi"><a href="https://doi.org/10.1234/ab%28c%29&amp;d">x</a></div>'), '10.1234/ab(c)&d');
check('no DOI line', DimensionsBadge::doiFromOutput('<div class="item keywords">k</div>'), '');
check('link that is not a DOI', DimensionsBadge::doiFromOutput('<div class="item doi"><a href="https://doi.org/hello">x</a></div>'), '');
$out = DimensionsBadge::inject($doiPage);
check('badge right before the DOI line', strpos($out, '<div class="item authors">a</div><div class="item ms-dimensions"><span class="__dimensions_badge_embed__" data-doi="10.31004/aulad.v9i3.1759"') !== false && strpos($out, '</script></div><section class="item doi">') !== false, true);
check('badge options', strpos($out, 'data-style="small_rectangle"') !== false && strpos($out, 'data-hide-zero-citations="false"') !== false && substr_count($out, 'badge.dimensions.ai/badge.js') === 1, true);
check('badge only once', DimensionsBadge::inject($out), $out);
check('no DOI, page unchanged', DimensionsBadge::inject('<p>x</p>'), '<p>x</p>');
check('doi attribute escaped', strpos(DimensionsBadge::render('10.1/a"b'), 'data-doi="10.1/a&quot;b"') !== false, true);
$theme = new TestTheme();
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/article.tpl'));
check('plugin: badge on the article page', strpos($mgr->apply('<html><body>' . $doiPage . '</body></html>'), '__dimensions_badge_embed__') !== false, true);
$mgr = new HomeMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/issue.tpl'));
check('plugin: no badge on other pages', strpos($mgr->apply('<html><body>' . $doiPage . '</body></html>'), '__dimensions_badge_embed__'), false);

// Fragment cache
$cacheDir = sys_get_temp_dir() . '/ms-test-cache-' . getmypid();
$cache = new FragmentCache($cacheDir, 60);
$built = 0;
$make = function () use (&$built) { $built++; return '<p>card ' . $built . '</p>'; };
check('cache: first call builds', $cache->remember('k1', $make), '<p>card 1</p>');
check('cache: second call is served from the file', $cache->remember('k1', $make), '<p>card 1</p>');
check('cache: built once', $built, 1);
check('cache: another key builds again', $cache->remember('k2', $make), '<p>card 2</p>');
check('cache: refresh rebuilds and stores', array($cache->remember('k1', $make, true), $cache->remember('k1', $make)), array('<p>card 3</p>', '<p>card 3</p>'));
check('cache: arrays are stored', $cache->remember('arr', function () { return array(1 => array('t' => 'x')); }) === array(1 => array('t' => 'x')) && $cache->remember('arr', function () { return 'other'; }) === array(1 => array('t' => 'x')), true);
check('cache: empty string is a valid stored value', array($cache->remember('empty', function () { return ''; }), $cache->remember('empty', function () { return 'x'; })), array('', ''));
$threw = false;
try { $cache->remember('boom', function () { throw new RuntimeException('db'); }); } catch (RuntimeException $e) { $threw = true; }
check('cache: a failing build reaches the caller and stores nothing', array($threw, $cache->remember('boom', function () { return 'ok'; })), array(true, 'ok'));
$files = glob($cacheDir . '/ms-*.cache');
foreach ($files as $f) { touch($f, time() - 120); }
check('cache: expired values are rebuilt', $cache->remember('k1', $make), '<p>card 4</p>');
check('cache: ttl 0 never stores', (new FragmentCache($cacheDir, 0))->remember('k1', function () { return 'fresh'; }), 'fresh');
check('cache: no temp files left behind', glob($cacheDir . '/*.tmp'), array());
$cache->flush();
check('cache: flush removes everything', glob($cacheDir . '/ms-*.cache'), array());
file_put_contents($cacheDir . '/ms-bad.cache', 'not serialized');
check('cache: damaged file is ignored', (new FragmentCache($cacheDir, 60))->remember('bad', function () { return 'rebuilt'; }), 'rebuilt');
check('cache: unwritable directory still builds', (new FragmentCache('/proc/nope/x', 60))->remember('k', function () { return 'v'; }), 'v');
$cache->flush();
@rmdir($cacheDir);

$theme = new TestTheme();
TestTheme::$cacheDir = $cacheDir;
$theme->ttl = 60;
$calls = 0;
$build = function () use (&$calls) { $calls++; return 'html'; };
check('plugin cache: visitors get the stored copy', array($theme->cachedFor('highlights', 1, $build), $theme->cachedFor('highlights', 1, $build), $calls), array('html', 'html', 1));
check('plugin cache: journal is part of the key', array($theme->cachedFor('highlights', 2, $build), $calls), array('html', 2));
$theme->debug = true;
check('plugin cache: administrators always rebuild', array($theme->cachedFor('highlights', 1, $build), $calls), array('html', 3));
(new FragmentCache($cacheDir, 60))->flush();
@rmdir($cacheDir);
TestTheme::$cacheDir = '';

// Batch lookup of articles
class CountingService extends ASubService {
	public $gets = 0; public $manys = 0; public $failMany = false; public $lastParams;
	public function get($id) { $this->gets++; return parent::get($id); }
	public function getMany($params) { $this->manys++; $this->lastParams = $params; if ($this->failMany) { throw new RuntimeException('no getMany'); } return parent::getMany($params); }
}
$svc = new CountingService();
$prev = Services::$services;
Services::$services = array('submission' => $svc);
$theme = new TestTheme();
$found = $theme->loadFor(array(12), 1);
check('batch: one query for known ids', array(count($found), $svc->manys, $svc->gets, $svc->lastParams['submissionIds'], $svc->lastParams['status']), array(1, 1, 0, array(12), 3));
$svc->gets = 0;
$found = $theme->loadFor(array(99, 12, 12), 1);
check('batch: ids deduplicated, unknown id tried once alone and dropped', array(count($found), $found[0]->getId(), $svc->gets), array(1, 12, 1));
$svc->failMany = true; $svc->gets = 0;
$errlog = ini_set('error_log', '/dev/null');
check('batch: falls back to single lookups when the query fails', array(count($theme->loadFor(array(12), 1)), $svc->gets), array(1, 1));
check('batch: no ids, no query', $theme->loadFor(array(), 1), array());
Services::$services = $prev;

// Archives page statistics
$sum = array('authors' => 5, 'countries' => array('ID' => 4, 'MY' => 1));
$names = array('ID' => 'Indonesia', 'MY' => 'Malaysia');
$flag = function ($c) { return $c === 'MY' ? '' : 'https://e.org/flags/' . strtolower($c) . '.svg'; };
$labels = array('authors' => 'authors', 'countries' => 'countries', 'articles' => 'articles', 'summary' => 'Issue "summary"');
$card = ArchiveStats::render(3, $sum, $names, $flag, $labels);
check('card: three counts', preg_match_all('#<span class="ms-archive-stats__num">(\d+)</span> <span class="ms-archive-stats__label">(\w+)</span>#', $card, $mm) === 3 && $mm[1] === array('5', '2', '3') && $mm[2] === array('authors', 'countries', 'articles'), true);
check('card: flag image with a title, text fallback without a flag file', strpos($card, '<img class="ms-flag" src="https://e.org/flags/id.svg" alt="" title="Indonesia (4)"') !== false && strpos($card, '<span class="ms-flag ms-flag--text" title="Malaysia (1)">MY</span>') !== false, true);
check('card: accessible group label, escaped', strpos($card, 'role="group" aria-label="Issue &quot;summary&quot;"') !== false, true);
$pagedCard = ArchiveStats::render(3, $sum, $names, $flag, $labels + array('pages' => 'pages'), 405);
check('card: total pages under the article count', preg_match('#<span class="ms-archive-stats__num">3</span> <span class="ms-archive-stats__label">articles</span></span><span class="ms-archive-stats__line ms-archive-stats__pages"><span class="ms-archive-stats__num">405</span> <span class="ms-archive-stats__label">pages</span></span>#', $pagedCard), 1);
check('card: no pages line without pages or without a label', array(strpos($card, 'ms-archive-stats__pages'), strpos(ArchiveStats::render(3, $sum, $names, $flag, $labels + array('pages' => 'pages'), 0), 'ms-archive-stats__pages'), strpos(ArchiveStats::render(3, $sum, $names, $flag, $labels, 405), 'ms-archive-stats__pages')), array(false, false, false));
check('card: no articles, no card', ArchiveStats::render(0, $sum, $names, $flag, $labels), '');
$many = array('authors' => 9, 'countries' => array('AA' => 9, 'BB' => 8, 'CC' => 7, 'DD' => 6, 'EE' => 5, 'FF' => 4, 'GG' => 3, 'HH' => 2));
$manyCard = ArchiveStats::render(2, $many, array(), $flag, $labels);
check('card: at most six flags, the rest as +N', substr_count($manyCard, 'class="ms-flag') === 6 && strpos($manyCard, '<span class="ms-archive-stats__more">+2</span>') !== false, true);
check('card: without countries only the counts', strpos(ArchiveStats::render(2, array('authors' => 2, 'countries' => array()), array(), $flag, $labels), 'ms-archive-stats__flags') === false, true);
$page = '<div class="obj_issue_summary"><a class="title">A</a></div><!-- .obj_issue_summary -->' . "\n" . '<div class="obj_issue_summary"><a class="title">B</a></div><!-- .obj_issue_summary -->';
$out = ArchiveStats::inject($page, array('<i>1</i>', '<i>2</i>'));
check('inject: card at the end of each issue block', $out === '<div class="obj_issue_summary"><a class="title">A</a><i>1</i></div><!-- .obj_issue_summary -->' . "\n" . '<div class="obj_issue_summary"><a class="title">B</a><i>2</i></div><!-- .obj_issue_summary -->', true);
check('inject: an issue without a card is skipped', substr_count(ArchiveStats::inject($page, array('', '<i>2</i>')), '<i>'), 1);
check('inject: block count differs from issue count, page untouched', ArchiveStats::inject($page, array('<i>1</i>')), $page);
check('inject: the Author Origins plugin badge is already there', ArchiveStats::inject('<div class="ao-badge"></div>' . $page, array('<i>1</i>', '<i>2</i>')), '<div class="ao-badge"></div>' . $page);
check('inject: no cards', ArchiveStats::inject($page, array()), $page);

class ArchIssue { public $id; public function __construct($id) { $this->id = $id; } public function getId() { return $this->id; } public function getJournalId() { return 1; } }
class ArchMgr {
	public $filters = array(); public $issues;
	public function __construct($issues) { $this->issues = $issues; }
	public function getTemplateVars($k) { return $this->issues; }
	public function registerFilter($t, $cb) { $this->filters[] = $cb; }
	public function apply($html) { foreach ($this->filters as $f) { $html = call_user_func($f, $html); } return $html; }
}
$prevSvc = Services::$service; $prevList = Services::$services;
Services::$services = array();
Services::$service = new FakeSubmissionService();
Services::$service->subs = array(
	new FakeSubmission(array(new FakeAuthor(array('email' => 'a@x.org', 'name' => 'A', 'country' => 'ID')), new FakeAuthor(array('email' => 'b@x.org', 'name' => 'B', 'country' => 'MY')))),
	new FakeSubmission(array(new FakeAuthor(array('email' => 'a@x.org', 'name' => 'A', 'country' => 'ID')))),
);
$theme = new TestTheme();
$mgr = new ArchMgr(array(new ArchIssue(7), new ArchIssue(6)));
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/issueArchive.tpl'));
$out = $mgr->apply('<html><body>' . $page . '</body></html>');
check('plugin: a card in each issue of the Archives page', substr_count($out, '<div class="ms-archive-stats" role="group"') === 2, true);
check('plugin: unique authors, countries and articles', strpos($out, '<span class="ms-archive-stats__num">2</span> <span class="ms-archive-stats__label">plugins.themes.manuscriptScholar.archive.authors') !== false && strpos($out, 'plugins.themes.manuscriptScholar.archive.articles') !== false, true);
$mgr = new ArchMgr(array(new ArchIssue(7)));
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/issueArchive.tpl'));
check('plugin: issue count not matching the page, page untouched', strpos($mgr->apply('<html><body>' . $page . '</body></html>'), 'ms-archive-stats'), false);
$mgr = new ArchMgr(array());
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/issueArchive.tpl'));
check('plugin: no issues, nothing registered but the page filter', strpos($mgr->apply('<html><body>' . $page . '</body></html>'), 'ms-archive-stats'), false);
$mgr = new ArchMgr(array(new ArchIssue(7)));
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/issue.tpl'));
check('plugin: other pages get no archive cards', strpos($mgr->apply('<html><body>' . $page . '</body></html>'), 'ms-archive-stats'), false);
Services::$service = $prevSvc; Services::$services = $prevList;

// DOI link in the action line
check('doi cleaning', array(ArticleActions::cleanDoi('10.31004/aulad.v9i3.1779'), ArticleActions::cleanDoi(' https://doi.org/10.1234/ab.c '), ArticleActions::cleanDoi('doi: 10.1234/x'), ArticleActions::cleanDoi('hello'), ArticleActions::cleanDoi(''), ArticleActions::cleanDoi('10.12/x')), array('10.31004/aulad.v9i3.1779', '10.1234/ab.c', '10.1234/x', '', '', ''));
check('doi url', ArticleActions::doiUrl('10.1234/a(b)#c'), 'https://doi.org/10.1234/a%28b%29%23c');
$withDoi = ArticleActions::render(array('title' => 'T', 'abstract' => '', 'url' => 'u', 'download' => 'https://e.org/d', 'doi' => '10.31004/aulad.v9i3.1779'), $labels);
check('doi pill with the DOI text, opens doi.org', strpos($withDoi, '<a href="https://doi.org/10.31004/aulad.v9i3.1779" class="ms-doi" target="_blank" rel="noopener">DOI: 10.31004/aulad.v9i3.1779</a>') !== false, true);
check('doi pill sits between download and Google Scholar', strpos($withDoi, 'ms-download') < strpos($withDoi, 'ms-doi') && strpos($withDoi, 'ms-doi') < strpos($withDoi, 'ms-scholar'), true);
check('no doi, no pill', strpos(ArticleActions::render(array('title' => 'T', 'abstract' => '', 'url' => 'u', 'download' => '', 'doi' => ''), $labels), 'ms-doi'), false);
check('junk doi, no pill', strpos(ArticleActions::render(array('title' => 'T', 'abstract' => '', 'url' => 'u', 'download' => '', 'doi' => '<script>'), $labels), 'ms-doi'), false);
// Content signature: cached fragments follow the articles
$sig = new PDO('sqlite::memory:');
$sig->exec('CREATE TABLE submissions (submission_id INTEGER, context_id INTEGER, status INTEGER, current_publication_id INTEGER, last_modified TEXT)');
$sig->exec('CREATE TABLE publications (publication_id INTEGER, submission_id INTEGER, last_modified TEXT)');
$sig->exec('CREATE TABLE publication_settings (publication_id INTEGER, locale TEXT, setting_name TEXT, setting_value TEXT)');
$sig->exec("INSERT INTO submissions VALUES (1,1,3,10,'2026-10-01 10:00:00'),(2,1,3,20,'2026-10-02 10:00:00'),(3,1,1,30,'2026-10-03 10:00:00'),(4,2,3,40,'2026-10-04 10:00:00')");
$sig->exec("INSERT INTO publications VALUES (10,1,'2026-10-01 10:00:00'),(20,2,'2026-10-02 10:00:00'),(30,3,'2026-10-03 10:00:00'),(40,4,'2026-10-04 10:00:00')");
$sig->exec("INSERT INTO publication_settings VALUES (10,'','issueId','29'),(20,'','issueId','29'),(30,'','issueId','29'),(40,'','issueId','29')");
$base = ContentSignature::forContext($sig, 1);
check('signature: 8 hex characters', preg_match('/^[0-9a-f]{8}$/', $base), 1);
check('signature: stable while nothing changes', ContentSignature::forContext($sig, 1), $base);
check('signature: issue and journal differ', ContentSignature::forContext($sig, 1, 29) !== '' && ContentSignature::forContext($sig, 1, 28) !== ContentSignature::forContext($sig, 1, 29), true);
$issueBase = ContentSignature::forContext($sig, 1, 29);
$sig->exec("INSERT INTO submissions VALUES (5,1,3,50,'2026-10-18 09:00:00')");
$sig->exec("INSERT INTO publications VALUES (50,5,'2026-10-18 09:00:00')");
$sig->exec("INSERT INTO publication_settings VALUES (50,'','issueId','29')");
check('signature: a newly published article changes the journal signature', ContentSignature::forContext($sig, 1) !== $base, true);
check('signature: and the signature of its issue', ContentSignature::forContext($sig, 1, 29) !== $issueBase, true);
$issueNow = ContentSignature::forContext($sig, 1, 29);
$sig->exec("UPDATE publications SET last_modified = '2026-10-18 11:00:00' WHERE publication_id = 10");
$beforeDraft = ContentSignature::forContext($sig, 1, 29);
$sig->exec("INSERT INTO publications VALUES (11,1,'2026-10-18 12:00:00')");
$sig->exec("UPDATE publications SET last_modified = '2026-10-18 12:30:00' WHERE publication_id = 11");
check('signature: saving a draft or older version of a published article does not change it', ContentSignature::forContext($sig, 1, 29) === $beforeDraft, true);
check('signature: editing an article changes it', ContentSignature::forContext($sig, 1, 29) !== $issueNow, true);
$issueNow = ContentSignature::forContext($sig, 1, 29);
$sig->exec("UPDATE submissions SET status = 4 WHERE submission_id = 2");
check('signature: withdrawing an article changes it', ContentSignature::forContext($sig, 1, 29) !== $issueNow, true);
check('signature: other journals do not matter', (function () use ($sig) { $a = ContentSignature::forContext($sig, 1); $sig->exec("UPDATE submissions SET last_modified = '2027-01-01 00:00:00' WHERE context_id = 2"); return ContentSignature::forContext($sig, 1) === $a; })(), true);
check('signatures of all issues in one query equal the single ones', (function () use ($sig) {
	$sig->exec("INSERT INTO submissions VALUES (6,1,3,60,'2026-10-19 09:00:00')");
	$sig->exec("INSERT INTO publications VALUES (60,6,'2026-10-19 09:00:00')");
	$sig->exec("INSERT INTO publication_settings VALUES (60,'','issueId','28')");
	$map = ContentSignature::forIssues($sig, 1);
	return array(array_keys($map) == array(28, 29) || array_keys($map) == array(29, 28), $map[29] === ContentSignature::forContext($sig, 1, 29), $map[28] === ContentSignature::forContext($sig, 1, 28), ContentSignature::emptyIssue() === ContentSignature::forContext($sig, 1, 27));
})(), array(true, true, true, true));
check('signatures of all issues: unreadable database gives null', ContentSignature::forIssues(new PDO('sqlite::memory:'), 1), null);
check('signature: unreadable database gives an empty signature', ContentSignature::forContext(new PDO('sqlite::memory:'), 1), '');
check('authors without a valid country', IssueCoverage::countWithoutCountry(array(array('key' => 'a', 'country' => 'UZ'), array('key' => 'b', 'country' => ''), array('key' => 'c'), array('key' => 'd', 'country' => 'Uzbekistan'), array('key' => 'e', 'country' => ' id '))), 3);

$sigDir = sys_get_temp_dir() . '/ms-test-sig-' . getmypid();
TestTheme::$cacheDir = $sigDir;
$theme = new TestTheme();
$theme->ttl = 600;
$theme->debug = false;
$builds = 0;
$mk = function () use (&$builds) { $builds++; return 'v' . $builds; };
check('cached fragment: same signature, one build', array($theme->cachedFor('coverage-29', 1, $mk, 'aaaa1111'), $theme->cachedFor('coverage-29', 1, $mk, 'aaaa1111'), $builds), array('v1', 'v1', 1));
check('cached fragment: new signature (an article was published), rebuilt at once', array($theme->cachedFor('coverage-29', 1, $mk, 'bbbb2222'), $builds), array('v2', 2));
check('cached fragment: no signature still works', array($theme->cachedFor('coverage-29', 1, $mk), $builds), array('v3', 3));
(new FragmentCache($sigDir, 60))->flush();
@rmdir($sigDir);
TestTheme::$cacheDir = '';

// Page count beside the total of articles
check('page count: range', IssueCoverage::pageCount('863-875'), 13);
check('page count: en dash and spaces', IssueCoverage::pageCount('1038 – 1045'), 8);
check('page count: single page', IssueCoverage::pageCount('12'), 1);
check('page count: several ranges', IssueCoverage::pageCount('1-5, 9-10'), 7);
check('page count: letters ignored', IssueCoverage::pageCount('e12-e20'), 9);
check('page count: nothing usable', array(IssueCoverage::pageCount(''), IssueCoverage::pageCount('xii'), IssueCoverage::pageCount('9-3'), IssueCoverage::pageCount(null)), array(0, 0, 0, 0));
check('total pages of an issue', IssueCoverage::totalPages(array('863-875', '876-886', '', '887-900')), 13 + 11 + 14);
$boxNoPages = IssueCoverage::render(3, array('authors' => 2, 'countries' => array()), array(), function ($c) { return ''; }, array('total' => 'Total Articles: 3', 'coverage' => 'c'));
check('box without pages keeps the old total line', strpos($boxNoPages, '<p class="ms-issue-coverage__total">Total Articles: 3</p>') !== false, true);
$boxPages = IssueCoverage::render(3, array('authors' => 2, 'countries' => array()), array(), function ($c) { return ''; }, array('total' => 'Total Articles: 3', 'pages' => 'Total Pages: 38', 'coverage' => 'c'));
check('box with pages: beside the total of articles', strpos($boxPages, '<p class="ms-issue-coverage__total">Total Articles: 3 <span class="ms-issue-coverage__sep" aria-hidden="true">|</span> Total Pages: 38</p>') !== false, true);
$keepServices = Services::$services; $keepService = Services::$service;
Services::$services = array();
Services::$service = new FakeSubmissionService();
$pubA = new FakeSubmission(array(new FakeAuthor(array('email' => 'a@x.org', 'name' => 'A', 'country' => 'ID')))); $pubA->pub->pages = '863-875';
$pubB = new FakeSubmission(array(new FakeAuthor(array('email' => 'b@x.org', 'name' => 'B', 'country' => 'UZ')))); $pubB->pub->pages = '876-886';
Services::$service->subs = array($pubA, $pubB);
$theme = new TestTheme();
$mgr = new FilterMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/issue.tpl'));
$out = call_user_func($mgr->filter, fp('<div class="obj_issue_toc"><div class="sections"></div></div>'));
check('plugin: pages of the issue shown beside the total of articles', strpos($out, 'Total Articles: 2 <span class="ms-issue-coverage__sep" aria-hidden="true">|</span> Total Pages: 24</p>') !== false, true);
Services::$service->subs = array(new FakeSubmission(array(new FakeAuthor(array('email' => 'a@x.org', 'name' => 'A', 'country' => 'ID')))));
$mgr = new FilterMgr();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/issue.tpl'));
$out = call_user_func($mgr->filter, fp('<div class="obj_issue_toc"><div class="sections"></div></div>'));
check('plugin: articles without page numbers show no page total', strpos($out, 'Total Pages') === false && strpos($out, 'Total Articles: 1</p>') !== false, true);
Services::$services = $keepServices; Services::$service = $keepService;

// Base and accent colours of the journal
check('colour: valid hex, upper-cased', ThemeColour::normalize('#2e7d32'), '#2E7D32');
check('colour: short hex expanded', ThemeColour::normalize(' #0a5 '), '#00AA55');
check('colour: junk gives the default', array(ThemeColour::normalize(''), ThemeColour::normalize(null), ThemeColour::normalize('green'), ThemeColour::normalize('#12345'), ThemeColour::normalize('#12345g'), ThemeColour::normalize(array('#fff')), ThemeColour::normalize('red;}body{display:none')), array('#4B7D92', '#4B7D92', '#4B7D92', '#4B7D92', '#4B7D92', '#4B7D92', '#4B7D92'));
check('colour: the Default Theme\'s base colour means Manuscript\'s', array(ThemeColour::base('#1e6292'), ThemeColour::base(null), ThemeColour::base('#2e7d32')), array('#4B7D92', '#4B7D92', '#2E7D32'));
check('colour: accent default and junk', array(ThemeColour::accent(null), ThemeColour::accent('x'), ThemeColour::accent('#c00')), array('#F7BC4A', '#F7BC4A', '#CC0000'));
check('colour: channels and rgb', array(ThemeColour::channels('#4B7D92'), ThemeColour::rgb('#4b7d92')), array(array(75, 125, 146), '75, 125, 146'));
check('colour: darker shade', array(ThemeColour::darker('#4B7D92'), ThemeColour::darker('#2E7D32'), ThemeColour::darker('#000000'), ThemeColour::darker('#FFFFFF')), array('#3A6070', '#236027', '#000000', '#C4C4C4'));
check('colour: white text contrast', array(ThemeColour::whiteContrast('#4B7D92') >= 4.5, ThemeColour::whiteContrast('#2E7D32') > 4.5, ThemeColour::whiteContrast('#FFFF00') < 1.5), array(true, true, true));
check('colour: text on the base colour', array(ThemeColour::textOn('#4B7D92'), ThemeColour::textOn('#FFFF00')), array('#ffffff', 'rgba(0,0,0,0.84)'));
check('colour: a light base colour keeps readable links', array(ThemeColour::primary('#2E7D32'), ThemeColour::primary('#F7BC4A')), array('#2E7D32', '#4B7D92'));
$greenLess = ThemeColour::lessVariables('#2e7d32', '#c00');
check('colour: LESS variables for a green journal', $greenLess, '@bg-base:#2E7D32;@text-bg-base:#ffffff;@primary:#2E7D32;@accent:#CC0000;');
check('colour: LESS variables for a yellow header', ThemeColour::lessVariables('#ffff00', null), '@bg-base:#FFFF00;@text-bg-base:rgba(0,0,0,0.84);@primary:#4B7D92;@accent:#F7BC4A;');
check('colour: no inline CSS for the default colours', array(ThemeColour::rootCss('#4b7d92', '#f7bc4a'), ThemeColour::rootCss('', null)), array('', ''));
check('colour: CSS variables for other colours', ThemeColour::rootCss('#2e7d32', null), 'html:root{--ms-base:#2E7D32;--ms-on-base:#ffffff;--ms-primary:#2E7D32;--ms-primary-dark:#236027;--ms-primary-rgb:46, 125, 50;--ms-accent:#F7BC4A;}');
check('colour: CSS variables for another accent only', ThemeColour::rootCss(null, '#cc0000'), 'html:root{--ms-base:#4B7D92;--ms-on-base:#ffffff;--ms-primary:#4B7D92;--ms-primary-dark:#3A6070;--ms-primary-rgb:75, 125, 146;--ms-accent:#CC0000;}');
class ColourTheme extends TestTheme {
	public $saved = array();
	public function getOption($name) { return isset($this->saved[$name]) ? $this->saved[$name] : null; }
	public function addStyle($name, $path, $args = array()) { $this->calls[] = array('addStyle', $name, $path, $args); }
}
$lessOf = function ($t) {
	$m = array_values(array_filter($t->calls, function ($c) { return $c[0] === 'modifyStyle' && isset($c[2]['addLessVariables']); }));
	return $m[0][2]['addLessVariables'];
};
$t = new ColourTheme();
$t->saved = array('baseColour' => '#2e7d32', 'accentColour' => '#cc0000');
$t->init();
$styles = array_values(array_filter($t->calls, function ($c) { return $c[0] === 'addStyle'; }));
check('init with saved colours: their LESS variables', $lessOf($t), '@bg-base:#2E7D32;@text-bg-base:#ffffff;@primary:#2E7D32;@accent:#CC0000;');
check('init with saved colours: inline variables after the stylesheet', array($styles[0][1], $styles[1][1], $styles[1][2], !empty($styles[1][3]['inline'])), array('manuscriptScholar', 'manuscriptScholarColour', ThemeColour::rootCss('#2e7d32', '#cc0000'), true));
$t = new ColourTheme();
$t->saved = array('baseColour' => '#1E6292', 'accentColour' => 'not a colour');
$t->init();
check('init with the parent default and an unusable accent: Manuscript palette, no inline style', array(strpos($lessOf($t), '@bg-base:#4B7D92;') !== false && strpos($lessOf($t), '@accent:#F7BC4A;') !== false, count(array_filter($t->calls, function ($c) { return $c[0] === 'addStyle'; }))), array(true, 1));
$opt = new TestTheme();
$opt->init();
$accentOption = array_values(array_filter($opt->calls, function ($c) { return $c[0] === 'addOption' && $c[1] === 'accentColour'; }));
check('init registers the accent colour option', array(count($accentOption), $accentOption[0][2], $accentOption[0][3]['default']), array(1, 'FieldColor', '#F7BC4A'));

// Country from the affiliation, share of foreign authors, image hints
$idx = CountryFromAffiliation::index(array(array('ID' => 'Indonesia', 'MY' => 'Malaysia', 'UZ' => 'Uzbekistan', 'US' => 'United States')));
check('affiliation: last part is a country', array(
	CountryFromAffiliation::detect('Universitas Indonesia, Depok, Indonesia', $idx),
	CountryFromAffiliation::detect('Universiti Malaya, Kuala Lumpur, MALAYSIA.', $idx),
	CountryFromAffiliation::detect('  Tashkent State University ; Uzbekistan  ', $idx),
	CountryFromAffiliation::detect('MIT, Cambridge, USA', $idx),
	CountryFromAffiliation::detect('Univ. X - Indonesia', $idx),
), array('ID', 'MY', 'UZ', 'US', 'ID'));
check('affiliation: no country at the end', array(
	CountryFromAffiliation::detect('Universitas Indonesia', $idx),
	CountryFromAffiliation::detect('Indonesia Institute of Sciences, Jakarta', $idx),
	CountryFromAffiliation::detect('', $idx),
	CountryFromAffiliation::detect(null, $idx),
	CountryFromAffiliation::detect('Depok, ,', $idx),
), array('', '', '', '', ''));
check('affiliation: first list wins, bad codes ignored', array(
	CountryFromAffiliation::index(array(array('ID' => 'Land'), array('MY' => 'Land')))['land'],
	isset(CountryFromAffiliation::index(array(array('XYZ' => 'Nowhere', '' => 'Blank')))['nowhere']),
), array('ID', false));

$sumF = array('authors' => 10, 'countries' => array('ID' => 7, 'MY' => 2, 'UZ' => 1));
check('foreign share', IssueCoverage::foreignShare($sumF, 'id'), array('foreign' => 3, 'known' => 10, 'percent' => 30));
check('foreign share: nobody from abroad, rounding up from zero, no home country, no countries', array(
	IssueCoverage::foreignShare(array('authors' => 3, 'countries' => array('ID' => 3)), 'ID')['percent'],
	IssueCoverage::foreignShare(array('authors' => 300, 'countries' => array('ID' => 299, 'MY' => 1)), 'ID')['percent'],
	IssueCoverage::foreignShare($sumF, ''),
	IssueCoverage::foreignShare($sumF, 'IDN'),
	IssueCoverage::foreignShare(array('authors' => 4, 'countries' => array()), 'ID'),
), array(0, 1, null, null, null));
$withForeign = IssueCoverage::render(5, $sumF, array(), function ($c) { return ''; }, array('total' => 'T', 'coverage' => 'C', 'foreign' => 'Foreign authors: 3 (30%)'));
check('coverage box shows the foreign line after the summary line', strpos($withForeign, '<p class="ms-issue-coverage__summary">C</p><p class="ms-issue-coverage__foreign">Foreign authors: 3 (30%)</p>') !== false, true);
check('coverage box without the label has no foreign line', strpos(IssueCoverage::render(5, $sumF, array(), function ($c) { return ''; }, array('total' => 'T', 'coverage' => 'C')), 'foreign') === false, true);
$fCard = ArchiveStats::render(3, $sumF, array(), function ($c) { return ''; }, array('authors' => 'authors', 'countries' => 'countries', 'articles' => 'articles', 'summary' => 's', 'foreign' => 'foreign'), 0, array('foreign' => 3, 'known' => 10, 'percent' => 30));
check('card: foreign share under the author count', strpos($fCard, '<span class="ms-archive-stats__num">10</span> <span class="ms-archive-stats__label">authors</span></span><span class="ms-archive-stats__line ms-archive-stats__foreign"><span class="ms-archive-stats__num">30%</span> <span class="ms-archive-stats__label">foreign</span></span>') !== false, true);
check('card: no foreign line without the share or the label', array(
	strpos(ArchiveStats::render(3, $sumF, array(), function ($c) { return ''; }, array('authors' => 'a', 'countries' => 'c', 'articles' => 'r', 'summary' => 's', 'foreign' => 'foreign')), 'ms-archive-stats__foreign'),
	strpos(ArchiveStats::render(3, $sumF, array(), function ($c) { return ''; }, array('authors' => 'a', 'countries' => 'c', 'articles' => 'r', 'summary' => 's'), 0, array('percent' => 30)), 'ms-archive-stats__foreign'),
), array(false, false));

class HomeCountryTheme extends TestTheme {
	public $home = 'ID';
	public function getOption($name) { return $name === 'manuscriptScholarHomeCountry' ? $this->home : null; }
	protected function affiliationIndex() { return CountryFromAffiliation::index(array(array('ID' => 'Indonesia', 'MY' => 'Malaysia'))); }
	public function coverageHtml($issue) { return $this->buildIssueCoverageHtml($issue); }
	public function archiveCard($issue) { return $this->buildArchiveCard($issue); }
	public function authors($issue) { return $this->collectIssueAuthors($issue); }
}
$prevSvc = Services::$service;
$prevServices = Services::$services;
Services::$services = array();
Services::$service = new FakeSubmissionService();
Services::$service->subs = array(
	new FakeSubmission(array(
		new FakeAuthor(array('email' => 'a@x.org', 'name' => 'A', 'country' => 'ID')),
		new FakeAuthor(array('email' => 'b@x.org', 'name' => 'B', 'country' => '', 'affiliation' => 'Universiti Malaya, Kuala Lumpur, Malaysia')),
		new FakeAuthor(array('email' => 'c@x.org', 'name' => 'C', 'country' => '', 'affiliation' => 'Universitas Indonesia')),
		new FakeAuthor(array('email' => 'd@x.org', 'name' => 'D', 'country' => 'id', 'affiliation' => 'Somewhere, Malaysia')),
	)),
);
$ht = new HomeCountryTheme();
$got = $ht->authors(new FakeIssue());
check('collect: country from the affiliation only when the field is empty', array_column($got['records'], 'country'), array('ID', 'MY', '', 'ID'));
check('collect: counts the derived countries', $got['derived'], 1);
check('coverage: foreign line from the option', strpos($ht->coverageHtml(new FakeIssue()), '<p class="ms-issue-coverage__foreign">Foreign authors: 1 (33%)</p>') !== false, true);
check('archive card: foreign percentage', strpos($ht->archiveCard(new FakeIssue()), '<span class="ms-archive-stats__num">33%</span> <span class="ms-archive-stats__label">foreign</span>') !== false, true);
$ht->home = '';
check('no home country: no foreign figure', array(strpos($ht->coverageHtml(new FakeIssue()), 'ms-issue-coverage__foreign'), strpos($ht->archiveCard(new FakeIssue()), 'ms-archive-stats__foreign')), array(false, false));
Services::$service = $prevSvc;
Services::$services = $prevServices;

$headerPage = '<div class="pkp_site_name"><a href="/" class="is_img"><img src="/h.png" width="900" alt="Aulad"></a></div><a class="cover" href="/1"><img class="img-fluid" src="/c1.jpg" alt=""></a>';
check('header image gets fetchpriority', ImageHints::prioritizeHeader($headerPage), str_replace('<img src="/h.png"', '<img fetchpriority="high" src="/h.png"', $headerPage));
check('header image: unchanged without such a link, or with its own hint', array(
	ImageHints::prioritizeHeader('<a href="/" class="x"><img src="/h.png"></a>'),
	ImageHints::prioritizeHeader('<a class="is_img"><img loading="lazy" src="/h.png"></a>'),
	ImageHints::prioritizeHeader('<a class="is_img"><img fetchpriority="low" src="/h.png"></a>'),
	ImageHints::prioritizeHeader('<p>no images</p>'),
), array('<a href="/" class="x"><img src="/h.png"></a>', '<a class="is_img"><img loading="lazy" src="/h.png"></a>', '<a class="is_img"><img fetchpriority="low" src="/h.png"></a>', '<p>no images</p>'));
$covers = '<a class="cover" href="/1"><img class="img-fluid" src="/1.jpg"></a> text <a class="cover" href="/2">' . "\n" . '<img src="/2.jpg" alt=""></a><a class="cover" href="/3"><img loading="eager" src="/3.jpg"></a><a class="cover" href="/4"><img src="/4.jpg"/></a>';
check('covers: the first stays eager, the others are lazy, own loading respected', ImageHints::lazyCovers($covers),
	'<a class="cover" href="/1"><img class="img-fluid" src="/1.jpg"></a> text <a class="cover" href="/2">' . "\n" . '<img loading="lazy" decoding="async" src="/2.jpg" alt=""></a><a class="cover" href="/3"><img loading="eager" src="/3.jpg"></a><a class="cover" href="/4"><img loading="lazy" decoding="async" src="/4.jpg"/></a>');
check('covers: no covers, no change', ImageHints::lazyCovers('<p>x</p>'), '<p>x</p>');

// Back-end "Select all" script
$adminTag = ManuscriptScholarThemePlugin::adminScriptTag('https://e.org/plugins/themes/manuscriptScholar/js/manuscriptScholarAdmin.js', '1.26.0', array('all' => 'Pilih "Semua"', 'none' => 'Batal Pilih'));
check('admin tag: versioned, escaped labels, deferred', $adminTag, '<script src="https://e.org/plugins/themes/manuscriptScholar/js/manuscriptScholarAdmin.js?v=1.26.0" data-select-all="Pilih &quot;Semua&quot;" data-clear-all="Batal Pilih" defer></script>');
$backPage = '<html><body><div id="app"></div></body></html>';
check('admin script goes before the closing body tag', ManuscriptScholarThemePlugin::injectAdminScript($backPage, $adminTag), '<html><body><div id="app"></div>' . $adminTag . '</body></html>');
check('admin script: not twice, not into fragments', array(
	ManuscriptScholarThemePlugin::injectAdminScript(ManuscriptScholarThemePlugin::injectAdminScript($backPage, $adminTag), $adminTag) === ManuscriptScholarThemePlugin::injectAdminScript($backPage, $adminTag),
	ManuscriptScholarThemePlugin::injectAdminScript('<div>fragment</div>', $adminTag),
	ManuscriptScholarThemePlugin::injectAdminScript('<html><p>no body</p></html>', $adminTag),
), array(true, '<div>fragment</div>', '<html><p>no body</p></html>'));
check('admin script: only the last body tag', substr_count(ManuscriptScholarThemePlugin::injectAdminScript('<html><body>a</body><iframe srcdoc="<body></body>"></iframe></html>', $adminTag), 'manuscriptScholarAdmin.js'), 1);

// "Journal home country" drop-down
$opts = CountryOptions::build(array('ID' => 'Indonesia', 'MY' => 'Malaysia', 'AU' => 'Australia', 'xx' => ' ', 'BAD' => 'Nowhere', 'UZ' => 'Uzbekistan'), '- none -');
check('country options: empty choice first, then names sorted', array_map(function ($o) { return $o['value'] . '=' . $o['label']; }, $opts), array('=- none -', 'AU=Australia', 'ID=Indonesia', 'MY=Malaysia', 'UZ=Uzbekistan'));
check('country options: nothing usable, nothing listed', array(CountryOptions::build(array(), 'x'), CountryOptions::build(array('ID' => ''), 'x')), array(array(), array()));
class CountryListTheme extends TestTheme {
	public $names = array();
	public $calls2 = array();
	protected function loadCountryNames() { $this->countryNames = $this->names; $this->countryNamesEnglish = array(); }
}
$cl = new CountryListTheme();
$cl->names = array('ID' => 'Indonesia', 'MY' => 'Malaysia');
$cl->init();
$home = array_values(array_filter($cl->calls, function ($c) { return $c[0] === 'addOption' && $c[1] === 'manuscriptScholarHomeCountry'; }));
check('home country option: a drop-down of full names when the list is available', array($home[0][2], array_column($home[0][3]['options'], 'label')), array('FieldSelect', array('plugins.themes.manuscriptScholar.option.homeCountry.none', 'Indonesia', 'Malaysia')));
$cl = new CountryListTheme();
$cl->init();
$home = array_values(array_filter($cl->calls, function ($c) { return $c[0] === 'addOption' && $c[1] === 'manuscriptScholarHomeCountry'; }));
check('home country option: a text field when no country list is available', array($home[0][2], isset($home[0][3]['options'])), array('FieldText', false));

// Expired fragments: served at once, rebuilt after the response
$staleDir = sys_get_temp_dir() . '/ms-stale-' . getmypid();
$jobs = array();
$defer = function ($job) use (&$jobs) { $jobs[] = $job; };
$sc = (new FragmentCache($staleDir, 60))->serveStale(3600, $defer);
$n = 0;
$mk = function () use (&$n) { $n++; return 'v' . $n; };
check('stale: first visit builds', array($sc->remember('home', $mk), $n, count($jobs)), array('v1', 1, 0));
$files = glob($staleDir . '/ms-*.cache');
touch($files[0], time() - 120);
check('stale: expired copy served at once, one rebuild queued', array($sc->remember('home', $mk), $n, count($jobs)), array('v1', 1, 1));
check('stale: a second visitor during the rebuild does not queue another', array($sc->remember('home', $mk), count($jobs)), array('v1', 1));
call_user_func($jobs[0]);
check('stale: after the rebuild the fresh copy is served and the lock is gone', array($sc->remember('home', $mk), $n, glob($staleDir . '/*.lock')), array('v2', 2, array()));
touch($files[0], time() - 7200);
check('stale: too old, rebuilt at once', array($sc->remember('home', $mk), $n, count($jobs)), array('v3', 3, 1));
touch($files[0], time() - 120);
check('stale: administrators (refresh) always rebuild at once', array($sc->remember('home', $mk, true), $n, count($jobs)), array('v4', 4, 1));
touch($files[0], time() - 120);
$noDefer = (new FragmentCache($staleDir, 60))->serveStale(3600, null);
check('stale: without a way to answer early, an expired copy is rebuilt at once', array($noDefer->remember('home', $mk), $n), array('v5', 5));
touch($files[0], time() - 120);
$jobs = array();
$sc->remember('home', $mk);
touch($files[0] . '.lock', time() - 600);
$jobs2 = count($jobs);
@unlink($files[0] . '.lock');
touch($files[0], time() - 120);
file_put_contents($files[0] . '.lock', '');
touch($files[0] . '.lock', time() - 600);
$sc->remember('home', $mk);
check('stale: an abandoned lock does not block rebuilds for ever', count($jobs), $jobs2 + 1);
$throwing = function () { throw new RuntimeException('db down'); };
touch($files[0], time() - 120);
@unlink($files[0] . '.lock');
$jobs = array();
$sc->remember('home', $throwing);
try { call_user_func($jobs[0]); } catch (RuntimeException $e) { }
$locksAfterFailure = glob($staleDir . '/*.lock');
check('stale: a failed rebuild frees the lock and keeps the old copy, the next visit queues a new rebuild', array($locksAfterFailure, $sc->remember('home', $mk) !== '', count($jobs)), array(array(), true, 2));
(new FragmentCache($staleDir, 60))->flush();
@rmdir($staleDir);

class DeferTheme extends TestTheme {
	public function queue($job) { $this->deferredJobs[] = $job; }
	public function pending() { return count($this->deferredJobs); }
}
$dt = new DeferTheme();
$order = array();
$dt->queue(function () use (&$order) { $order[] = 'job1'; });
$dt->queue(function () { throw new RuntimeException('x'); });
$dt->queue(function () use (&$order) { $order[] = 'job3'; });
$prevLog = ini_set('error_log', '/dev/null');
$dt->runDeferredJobs(function () use (&$order) { $order[] = 'response sent'; });
ini_set('error_log', $prevLog);
check('deferred jobs: response first, then every job, a failing one does not stop the rest', array($order, $dt->pending()), array(array('response sent', 'job1', 'job3'), 0));

// Lazy loading in the sidebar and the footer
$lazyPage = '<header><img src="/logo.png"></header><div class="pkp_structure_main"><img src="/cover.jpg"></div>'
	. '<div class="pkp_structure_sidebar left"><img src="/sinta.png" alt="Sinta"><img loading="eager" src="/keep.png">'
	. '<a href="https://www.flagcounter.com"><img src="https://s01.flagcounter.com/count/abc/"></a><iframe src="https://www.youtube.com/embed/x"></iframe>'
	. '<img src="https://c.statcounter.com/1/0/a/1/" alt=""><IMG SRC="/upper.png"></div>'
	. '<div class="pkp_structure_footer_wrapper"><img src="/pkp.png" /></div>';
$lazyOut = ImageHints::lazySidebarAndFooter($lazyPage);
check('lazy: header and main content untouched', substr($lazyOut, 0, strpos($lazyOut, '<div class="pkp_structure_sidebar')), substr($lazyPage, 0, strpos($lazyPage, '<div class="pkp_structure_sidebar')));
check('lazy: sidebar and footer images and frames', array(
	strpos($lazyOut, '<img loading="lazy" decoding="async" src="/sinta.png" alt="Sinta">') !== false,
	strpos($lazyOut, '<img loading="eager" src="/keep.png">') !== false,
	strpos($lazyOut, '<iframe loading="lazy" src="https://www.youtube.com/embed/x">') !== false,
	strpos($lazyOut, '<IMG loading="lazy" decoding="async" SRC="/upper.png">') !== false,
	strpos($lazyOut, '<img loading="lazy" decoding="async" src="/pkp.png" />') !== false,
), array(true, true, true, true, true));
check('lazy: visitor counters keep loading at once', array(
	strpos($lazyOut, '<img src="https://s01.flagcounter.com/count/abc/">') !== false,
	strpos($lazyOut, '<img src="https://c.statcounter.com/1/0/a/1/" alt="">') !== false,
), array(true, true));
check('lazy: page without sidebar or footer unchanged', ImageHints::lazySidebarAndFooter('<p><img src="/a.png"></p>'), '<p><img src="/a.png"></p>');
check('lazy: footer only', ImageHints::lazySidebarAndFooter('<img src="/a.png"><div class="pkp_structure_footer"><img src="/b.png"></div>'), '<img src="/a.png"><div class="pkp_structure_footer"><img loading="lazy" decoding="async" src="/b.png"></div>');

// CSS minifier used for the package
check('minify: comments, white space, last semicolon', CssMinifier::minify("/* head */\n.a ,\n .b  {\n\tcolor : red;\n\tmargin: 0 auto;\n}\n\n@media (min-width: 768px) {\n  .c { top: 0; }\n}\n"), ".a , .b{color : red;margin: 0 auto}@media (min-width: 768px){.c{top: 0}}\n");
check('minify: strings are left alone', CssMinifier::minify('a[href*="/*x*/"]::before { content: "a  ;  }  b"; }'), 'a[href*="/*x*/"]::before{content: "a  ;  }  b"}' . "\n");
check('minify: escaped quotes and descendant selectors keep their meaning', CssMinifier::minify(".x :hover , html body\n.y { content: '\\'' ; }"), ".x :hover , html body .y{content: '\\''}\n");
check('minify: a comment between two words still separates them', CssMinifier::minify('.a/* c */.b{} .c/**/ .d{}'), ".a .b{}.c .d{}\n");

// Meta description
check('meta text: tags, entities, white space', MetaDescription::text('<p>Aulad&nbsp;is a <b>journal</b></p><p>on early&amp;childhood.</p>'), "Aulad is a journal on early&childhood.");
$long = str_repeat('word ', 60);
$cut = MetaDescription::text($long);
check('meta text: cut at a word, at most 160 characters, with an ellipsis', array(mb_strlen($cut) <= 160, mb_substr($cut, -1), mb_substr($cut, -5, 4)), array(true, '…', 'word'));
check('meta compose: issue title, then the journal', MetaDescription::compose(array('Vol. 9 No. 3 (2026): August-December 2026', '', 'Aulad: Journal on Early Childhood', '<p>A peer-reviewed journal.</p>')), 'Vol. 9 No. 3 (2026): August-December 2026. Aulad: Journal on Early Childhood. A peer-reviewed journal');
check('meta compose: nothing usable', MetaDescription::compose(array('', ' ', '<p></p>')), '');
$head = '<html><head><title>T</title></head><body></body></html>';
check('meta inject: added before </head>, escaped', MetaDescription::inject($head, 'A "quoted" <b> & more'), '<html><head><title>T</title><meta name="description" content="A &quot;quoted&quot; &lt;b&gt; &amp; more">' . "\n" . '</head><body></body></html>');
check('meta inject: kept when the page has one, or nothing to add', array(
	MetaDescription::inject('<html><head><META NAME=\'description\' content="x"></head></html>', 'y'),
	MetaDescription::inject($head, ''),
	MetaDescription::inject('<div>fragment</div>', 'y'),
), array('<html><head><META NAME=\'description\' content="x"></head></html>', $head, '<div>fragment</div>'));

// File sizes on the workflow page
$fsDb = new PDO('sqlite::memory:');
$fsDb->exec('CREATE TABLE submissions (submission_id INTEGER, context_id INTEGER)');
$fsDb->exec('CREATE TABLE files (file_id INTEGER, path TEXT, mimetype TEXT)');
$fsDb->exec('CREATE TABLE submission_files (submission_file_id INTEGER, file_id INTEGER, submission_id INTEGER)');
$fsDb->exec("INSERT INTO submissions VALUES (8842, 1), (9000, 2)");
$fsDb->exec("INSERT INTO files VALUES (1, 'journals/1/articles/8842/a.docx', 'x'), (2, 'journals/1/articles/8842/b.docx', 'x'), (3, 'journals/2/articles/9000/c.docx', 'x'), (4, 'journals/1/articles/8842/gone.docx', 'x')");
$fsDb->exec("INSERT INTO submission_files VALUES (37807, 1, 8842), (37808, 2, 8842), (40000, 3, 9000), (37809, 4, 8842)");
check('file paths: only the files of that submission in this journal', FileSizes::paths($fsDb, 8842, 1), array(37807 => 'journals/1/articles/8842/a.docx', 37808 => 'journals/1/articles/8842/b.docx', 37809 => 'journals/1/articles/8842/gone.docx'));
check('file paths: a submission of another journal gives nothing', FileSizes::paths($fsDb, 9000, 1), array());
$filesDir = sys_get_temp_dir() . '/ms-files-' . getmypid();
@mkdir($filesDir . '/journals/1/articles/8842', 0777, true);
file_put_contents($filesDir . '/journals/1/articles/8842/a.docx', str_repeat('x', 8388608));
file_put_contents($filesDir . '/journals/1/articles/8842/b.docx', str_repeat('x', 61440));
check('file sizes: read from the disk, missing files and odd paths left out', FileSizes::sizes(FileSizes::paths($fsDb, 8842, 1) + array(5 => '../../etc/passwd', 6 => ''), $filesDir . '/'), array(37807 => 8388608, 37808 => 61440));
check('file sizes: data block', FileSizes::dataTag(array(37807 => 8388608, 37808 => 61440)), '<script type="application/json" id="ms-file-sizes">{"37807":8388608,"37808":61440}</script>');
check('file sizes: no block without sizes', FileSizes::dataTag(array()), '');
@unlink($filesDir . '/journals/1/articles/8842/a.docx');
@unlink($filesDir . '/journals/1/articles/8842/b.docx');
@rmdir($filesDir . '/journals/1/articles/8842'); @rmdir($filesDir . '/journals/1/articles'); @rmdir($filesDir . '/journals/1'); @rmdir($filesDir . '/journals'); @rmdir($filesDir);
class SizeTheme extends TestTheme { public function sizesFor($t) { return $this->workflowFileSizes($t); } }
check('file sizes: only on workflow and author dashboard templates', array((new SizeTheme())->sizesFor('frontend/pages/issue.tpl'), (new SizeTheme())->sizesFor('management/settings/website.tpl'), (new SizeTheme())->sizesFor(null)), array(array(), array(), array()));

// Reviewer page: only the files of that reviewer's own review
$rv = new PDO('sqlite::memory:');
$rv->exec('CREATE TABLE submissions (submission_id INTEGER, context_id INTEGER)');
$rv->exec('CREATE TABLE files (file_id INTEGER, path TEXT)');
$rv->exec('CREATE TABLE submission_files (submission_file_id INTEGER, file_id INTEGER, submission_id INTEGER, file_stage INTEGER, assoc_type INTEGER, assoc_id INTEGER)');
$rv->exec('CREATE TABLE review_assignments (review_id INTEGER, submission_id INTEGER, reviewer_id INTEGER)');
$rv->exec('CREATE TABLE review_files (review_id INTEGER, submission_file_id INTEGER)');
$rv->exec("INSERT INTO submissions VALUES (8840, 1)");
$rv->exec("INSERT INTO files VALUES (1, 'a/review.docx'), (2, 'a/other-review.docx'), (3, 'a/submission.docx'), (4, 'a/my-attachment.pdf'), (5, 'a/their-attachment.pdf')");
$rv->exec("INSERT INTO submission_files VALUES (37819, 1, 8840, 4, NULL, NULL), (37820, 2, 8840, 4, NULL, NULL), (37800, 3, 8840, 2, NULL, NULL), (37830, 4, 8840, 5, 517, 70), (37831, 5, 8840, 5, 517, 71)");
$rv->exec("INSERT INTO review_assignments VALUES (70, 8840, 5), (71, 8840, 6)");
$rv->exec("INSERT INTO review_files VALUES (70, 37819), (71, 37820)");
check('reviewer files: own review files and own attachments only', FileSizes::reviewerPaths($rv, 8840, 1, 5), array(37819 => 'a/review.docx', 37830 => 'a/my-attachment.pdf'));
check('reviewer files: not a reviewer of it, or another journal, gives nothing', array(FileSizes::reviewerPaths($rv, 8840, 1, 99), FileSizes::reviewerPaths($rv, 8840, 2, 5)), array(array(), array()));

// Theme files: the Manuscript LESS and fonts it refers to are in the package
$styleDir = __DIR__ . '/../styles';
preg_match_all('/@import "([^"]+)";/', file_get_contents($styleDir . '/index.less'), $imports);
check('index.less imports exist', array_values(array_filter($imports[1], function ($f) use ($styleDir) { return !is_file($styleDir . '/' . $f); })), array());
preg_match_all('#plugins/themes/([A-Za-z]+)/fonts/([^?\'\#]+)#', file_get_contents($styleDir . '/fonts.less'), $fonts);
check('fonts.less points at this theme folder', array_values(array_unique($fonts[1])), array('manuscriptScholar'));
check('fonts.less files exist', count($fonts[2]) > 0 && !array_filter($fonts[2], function ($f) { return !is_file(__DIR__ . '/../fonts/' . $f); }), true);
$css = file_get_contents($styleDir . '/manuscriptScholar.css');
check('stylesheet braces balanced', substr_count($css, '{'), substr_count($css, '}'));
foreach (array('ms-issue-coverage', 'ms-archive-stats', 'ms-article-actions', 'ms-highlights', 'ms-categories', 'ms-readmore__button', 'ms-country-line', 'ms-dimensions', 'ms-menu') as $class) {
	check("stylesheet styles .$class", strpos($css, '.' . $class) !== false, true);
}

echo $fail === 0 ? "OK\n" : "$fail failure(s)\n";
exit($fail === 0 ? 0 : 1);
