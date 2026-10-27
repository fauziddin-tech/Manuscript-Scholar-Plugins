<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Standalone checks of the Individualize Scholar theme (individualizeScholar/); no OJS installation needed.
function import($class) {}
class FakeParentTheme {
	public $options;
	public $values = array();
	public $saved = array();
	public function __construct() {
		$this->options = array('homepageBlocks' => (object) array(
			'options' => array(array('value' => 'frontend/components/homepage-blocks/about.tpl', 'label' => 'About')),
			'default' => array('frontend/components/homepage-blocks/about.tpl'),
		));
	}
	public function getOption($name) { return array_key_exists($name, $this->values) ? $this->values[$name] : $this->options[$name]->default; }
	public function saveOption($name, $value, $contextId = null) { $this->saved[] = array($name, $value, $contextId); }
}
class ThemePlugin {
	public $calls = array();
	public $parent = null;
	public static $parentInstalled = true;
	public static $active = true;
	public function getOption($name) { return $this->parent ? $this->parent->getOption($name) : null; }
	public function getOptionConfig($name) { return $this->parent && isset($this->parent->options[$name]) ? $this->parent->options[$name] : false; }
	public function setParent($name) { $this->calls[] = array('setParent', $name); if (self::$parentInstalled) { $this->parent = new FakeParentTheme(); } }
	public function addStyle($name, $path, $args = array()) { $this->calls[] = array('addStyle', $name, $path); }
	public function addOption($name, $type, $args) { $this->calls[] = array('addOption', $name, $type); }
	public function isActive() { return self::$active; }
	public function getPluginPath() { return 'plugins/themes/individualizeScholar'; }
}
class HookRegistry {
	public static $hooks = array();
	public static function register($name, $cb) { self::$hooks[] = $name; }
}
class AppLocale { public static function getLocale() { return 'en_US'; } }
class PKPString { public static function stripUnsafeHtml($html) { return strip_tags($html, '<p><em>'); } }
define('STYLE_SEQUENCE_LAST', 9);
define('STATUS_PUBLISHED', 3);
define('ROUTE_PAGE', 1);
function __($key, $params = array()) {
	$text = $key;
	foreach ($params as $k => $v) { $text .= " $k=$v"; }
	return $text;
}
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
require __DIR__ . '/../individualizeScholar/IndividualizeScholarThemePlugin.inc.php';

class FakeGalley {
	public $id; public $pdf;
	public function __construct($id, $pdf) { $this->id = $id; $this->pdf = $pdf; }
	public function getId() { return $this->id; }
	public function isPdfGalley() { return $this->pdf; }
	public function getBestGalleyId() { return 'g' . $this->id; }
}
class FakePublication {
	public $data;
	public function __construct($data) { $this->data = $data; }
	public function getData($k) { return $this->data[$k] ?? null; }
	public function getLocalizedData($k) { return $this->data[$k] ?? ''; }
	public function getLocalizedFullTitle() { return $this->data['title'] ?? ''; }
	public function getLocalizedTitle() { return $this->data['title'] ?? ''; }
	public function getStoredPubId($type) { return $this->data['doi'] ?? ''; }
}
class FakeArticle {
	public $id; public $publication;
	public function __construct($id, $publication) { $this->id = $id; $this->publication = $publication; }
	public function getId() { return $this->id; }
	public function getBestId() { return $this->id; }
	public function getCurrentPublication() { return $this->publication; }
}
class FakeAuthor {
	public $data;
	public function __construct($data) { $this->data = $data; }
	public function getData($k) { return $this->data[$k] ?? null; }
}
class FakeSmarty {
	public $vars = array();
	public $registered_plugins = array();
	public $filters = array();
	public function assign($k, $v = null) { if (is_array($k)) { $this->vars = array_merge($this->vars, $k); } else { $this->vars[$k] = $v; } }
	public function getTemplateVars($k) { return $this->vars[$k] ?? null; }
	public function registerPlugin($type, $name, $cb) { $this->registered_plugins[$type][$name] = $cb; }
	public function registerFilter($type, $cb) { $this->filters[] = $cb; }
	public function apply($html) { foreach ($this->filters as $f) { $html = call_user_func($f, $html); } return $html; }
}
class TestTheme extends IndividualizeScholarThemePlugin {
	public $debug = true;
	public $values = array();
	public $cards = array('categories' => '', 'highlights' => '');
	public function getOption($name) {
		if (array_key_exists($name, $this->values)) {
			return $this->values[$name];
		}
		return $name === 'individualizeScholarHomeBlocks' ? array('categories', 'highlights') : parent::getOption($name);
	}
	protected function buildCategoryCards() { return $this->cards['categories']; }
	protected function buildHomeHighlights() { return $this->cards['highlights']; }
	protected function canSeeDebug() { return $this->debug; }
	protected function homeCountryChoices() { return array(array('value' => 'ID', 'label' => 'Indonesia')); }
	protected function getCountryName($code) {
		$names = array('ID' => 'Indonesia', 'MY' => 'Malaysia');
		return $names[strtoupper(trim((string) $code))] ?? '';
	}
	protected function getAssetUrl($relativePath) { return 'https://example.org/plugins/themes/individualizeScholar/' . $relativePath; }
}

$fail = 0;
function check($label, $actual, $expected) {
	global $fail;
	if ($actual !== $expected) {
		$fail++;
		echo "FAIL $label: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	}
}

// Set-up: child of Individualize with two options of its own
$theme = new TestTheme();
$theme->init();
check('init: parent is Individualize', $theme->calls[0], array('setParent', 'individualizetheme'));
check('init: options and stylesheet', array_values(array_map(function ($c) { return $c[0] . ' ' . $c[1]; }, array_slice($theme->calls, 1))), array('addOption individualizeScholarHomeCountry', 'addOption individualizeScholarHomeBlocks', 'addStyle individualizeScholar'));
check('init: hooks', HookRegistry::$hooks, array('TemplateManager::display', 'Templates::Article::Details'));
check('init: Individualize\'s homepage block option is left alone', count($theme->getOptionConfig('homepageBlocks')->options), 1);
ThemePlugin::$active = false;
HookRegistry::$hooks = array();
(new TestTheme())->init();
check('init of an inactive theme (Appearance form): options only, no hooks', HookRegistry::$hooks, array());
ThemePlugin::$active = true;
ThemePlugin::$parentInstalled = false;
$orphan = new TestTheme();
$orphan->init();
check('without Individualize: no parent, no error', $orphan->parent, null);
ThemePlugin::$parentInstalled = true;

// Homepage blocks: added to the rendered list only
$about = 'frontend/components/homepage-blocks/about.tpl';
$toc = 'frontend/components/homepage-blocks/issue-toc.tpl';
$summary = 'frontend/components/homepage-blocks/issue-summary.tpl';
$submit = 'frontend/components/homepage-blocks/how-to-submit.tpl';
$cat = IndividualizeScholarThemePlugin::BLOCK_CATEGORIES;
$hl = IndividualizeScholarThemePlugin::BLOCK_HIGHLIGHTS;
check('blocks: categories above the current issue, highlights at the end', IndividualizeScholarThemePlugin::placeBlocks(array($about, $toc, $submit), true, true), array($about, $cat, $toc, $submit, $hl));
check('blocks: above the issue summary block too', IndividualizeScholarThemePlugin::placeBlocks(array($summary, $toc), true, false), array($cat, $summary, $toc));
check('blocks: no current issue block, categories at the end', IndividualizeScholarThemePlugin::placeBlocks(array($about), true, true), array($about, $cat, $hl));
check('blocks: none chosen, list unchanged', IndividualizeScholarThemePlugin::placeBlocks(array($about, $toc), false, false), array($about, $toc));
check('blocks: never twice, no list at all', array(IndividualizeScholarThemePlugin::placeBlocks(array($cat, $about, $hl), false, true), IndividualizeScholarThemePlugin::placeBlocks(null, true, false)), array(array($about, $hl), array($cat)));

// Article order by first page
check('start page', array(IsArticleOrder::startPage('1029-1038'), IsArticleOrder::startPage('e12'), IsArticleOrder::startPage(''), IsArticleOrder::startPage(null)), array(1029, 12, null, null));
$sections = array(
	5 => array('title' => 'A', 'articles' => array('x' => 'p20', 'p3', 'none1', 'p100', 'none2', 'p3b')),
	7 => array('title' => 'B', 'articles' => array('p9')),
	9 => array('title' => 'C', 'articles' => array()),
);
$pages = array('p20' => '20-25', 'p3' => '3-8', 'none1' => '', 'p100' => '100', 'none2' => null, 'p3b' => '3');
$sorted = IsArticleOrder::sortSections($sections, function ($a) use ($pages) { return $pages[$a]; });
check('sort: by start page, equal pages keep order, no page last', $sorted[5]['articles'], array('p3', 'p3b', 'p20', 'p100', 'none1', 'none2'));
check('sort: other sections and keys kept', array(array_keys($sorted), $sorted[5]['title'], $sorted[7], $sorted[9]), array(array(5, 7, 9), 'A', $sections[7], $sections[9]));

// Article actions: data comes from the article itself, no page parsing
$publication = new FakePublication(array(
	'title' => 'A "quoted" title',
	'abstract' => '<p>Abstract <em>text</em><script>x</script></p>',
	'doi' => 'https://doi.org/10.1234/abc.5',
	'galleys' => array(new FakeGalley(41, false), new FakeGalley(42, true)),
));
$article = new FakeArticle(77, $publication);
$smarty = new FakeSmarty();
$theme->smartyArticleActions(array('article' => $article, 'context' => new FakeContext(), 'assign' => 'a'), $smarty);
$actions = $smarty->vars['a'];
check('actions: PDF galley reported so its button is left out', $actions['pdfGalleyId'], 42);
check('actions: download link to the PDF', strpos($actions['html'], 'href="https://example.org/index.php/aulad/article/download/77/g42" class="ms-download"') !== false, true);
check('actions: show abstract with a unique panel', strpos($actions['html'], 'aria-controls="ms-abstract-77-1"') !== false && strpos($actions['html'], 'id="ms-abstract-77-1" hidden') !== false, true);
check('actions: abstract sanitized', strpos($actions['html'], '<script>') === false && strpos($actions['html'], '<em>text</em>') !== false, true);
check('actions: DOI and Google Scholar', strpos($actions['html'], 'href="https://doi.org/10.1234/abc.5" class="ms-doi"') !== false && strpos($actions['html'], 'scholar.google.com/scholar?q=') !== false, true);
check('actions: title escaped in the Scholar link', strpos($actions['html'], '"quoted"') === false, true);
$printed = $theme->smartyArticleActions(array('article' => $article, 'hideAbstract' => true), $smarty);
check('actions: no abstract when the summary already shows it', strpos($printed, 'ms-show-abstract') === false && strpos($printed, 'ms-abstract') === false && strpos($printed, 'ms-download') !== false, true);
$noPdf = new FakeArticle(78, new FakePublication(array('title' => 'T', 'galleys' => array(new FakeGalley(50, false)))));
$theme->smartyArticleActions(array('article' => $noPdf, 'assign' => 'b'), $smarty);
check('actions: without a PDF, no download and every galley button stays', array($smarty->vars['b']['pdfGalleyId'], strpos($smarty->vars['b']['html'], 'ms-download')), array(0, false));
check('actions: nothing for a missing article', $theme->smartyArticleActions(array(), $smarty), '');

// Author country
$theme->smartyAuthorCountry(array('author' => new FakeAuthor(array('country' => 'id')), 'assign' => 'c'), $smarty);
check('author country: localized name', $smarty->vars['c'], 'Indonesia');
check('author country: none for an unknown code', $theme->smartyAuthorCountry(array('author' => new FakeAuthor(array('country' => 'XX'))), $smarty), '');

// Dimensions badge in the "Article details" hook, once
$smarty->vars['publication'] = $publication;
$hookOutput = '';
$theme->addDimensionsBadge('Templates::Article::Details', array(array(), $smarty, &$hookOutput));
check('dimensions: badge with the DOI', strpos($hookOutput, 'data-doi="10.1234/abc.5"') !== false, true);
$theme->addDimensionsBadge('Templates::Article::Details', array(array(), $smarty, &$hookOutput));
check('dimensions: added once', substr_count($hookOutput, '__dimensions_badge_embed__'), 1);
$smarty->vars['publication'] = new FakePublication(array());
$empty = '';
$theme->addDimensionsBadge('Templates::Article::Details', array(array(), $smarty, &$empty));
check('dimensions: nothing without a DOI', $empty, '');

// Display hook: functions registered, articles sorted, fold on the home page, filter at the end of the page
$a1 = new FakeArticle(1, new FakePublication(array('pages' => '30-40')));
$a2 = new FakeArticle(2, new FakePublication(array('pages' => '1-29')));
$mgr = new FakeSmarty();
$mgr->vars['publishedSubmissions'] = array(3 => array('title' => 'S', 'articles' => array($a1, $a2)));
$mgr->vars['homepageBlocks'] = array($about, $toc);
$theme->values['individualizeScholarHomeBlocks'] = array();
$theme->handleDisplay('TemplateManager::display', array($mgr, 'frontend/pages/indexJournal.tpl'));
check('display: template functions registered', array_keys($mgr->registered_plugins['function']), array('individualize_scholar_actions', 'individualize_scholar_country', 'individualize_scholar_coverage', 'individualize_scholar_archive_card'));
check('display: articles in page order', array_map(function ($a) { return $a->getId(); }, $mgr->vars['publishedSubmissions'][3]['articles']), array(2, 1));
check('display: home page fold', $mgr->vars['individualizeScholarFold'], IndividualizeScholarThemePlugin::HOME_ARTICLE_LIMIT);
check('display: cards that are off are not built nor listed', array($mgr->vars['individualizeScholarHighlights'], $mgr->vars['individualizeScholarCategories'], $mgr->vars['homepageBlocks']), array('', '', array($about, $toc)));
$page = '<html><head><title>x</title></head><body><header><img class="header-desktop-logo" src="/l.png"></header><main>m</main><div class="footer"><img src="/f.png"><img src="https://www.histats.com/c.gif"></div>' . "\n</html>";
$out = $mgr->apply($page);
check('filter: script and status before </html> (Individualize has no </body>)', (bool) preg_match('#</div>\n<script src="[^"]*individualizeScholar\.js\?v=1\.0\.0" defer></script><!-- individualizeScholar 1\.0\.0 \| frontend/pages/indexJournal\.tpl \| article order by pages: sorted .*--></html>$#', $out), true);
check('filter: logo fetched first', strpos($out, '<img fetchpriority="high" class="header-desktop-logo"') !== false, true);
check('filter: footer image lazy, counter not', strpos($out, '<img loading="lazy" decoding="async" src="/f.png">') !== false && strpos($out, '<img src="https://www.histats.com/c.gif">') !== false, true);
check('filter: fragments untouched', $mgr->apply('<div>block</div>'), '<div>block</div>');
$withCards = new FakeSmarty();
$withCards->vars['homepageBlocks'] = array($about, $toc);
$theme->values['individualizeScholarHomeBlocks'] = array('categories', 'highlights');
$theme->cards = array('categories' => '<section class="ms-categories">c</section>', 'highlights' => '');
$theme->handleDisplay('TemplateManager::display', array($withCards, 'frontend/pages/indexJournal.tpl'));
check('display: a card with content is listed, an empty one is not', array($withCards->vars['individualizeScholarCategories'], $withCards->vars['homepageBlocks']), array('<section class="ms-categories">c</section>', array($about, $cat, $toc)));
unset($theme->values['individualizeScholarHomeBlocks']);
check('home cards: both on by default', (function () { return $this->homeBlocks(); })->call($theme), array('categories', 'highlights'));
$theme->debug = false;
$mgr2 = new FakeSmarty();
$theme->handleDisplay('TemplateManager::display', array($mgr2, 'frontend/pages/about.tpl'));
check('filter: visitors see only the version', strpos($mgr2->apply('<html><body></body></html>'), '<!-- individualizeScholar 1.0.0 --></body>') !== false, true);
$theme->debug = true;

check('page end: </body> preferred', IndividualizeScholarThemePlugin::beforePageEnd('<body>a</body></html>', 'X'), '<body>aX</body></html>');
check('page end: last </html>', IndividualizeScholarThemePlugin::beforePageEnd('<html>a</html>', 'X'), '<html>aX</html>');
check('page end: appended otherwise', IndividualizeScholarThemePlugin::beforePageEnd('a', 'X'), 'aX');
check('cache buster', IndividualizeScholarThemePlugin::addCacheBuster('<link href="https://e.org/plugins/themes/individualizeScholar/styles/individualizeScholar.css?x=1">', '1.0.0'), '<link href="https://e.org/plugins/themes/individualizeScholar/styles/individualizeScholar.css?v=1.0.0">');

// Page hints
check('archive covers: the first stays eager',
	IsPageHints::lazyCovers('<img class="issue-summary-cover" src="/1.jpg"><img class="issue-summary-cover" src="/2.jpg"><img class="issue-cover-summary-image" loading="eager" src="/3.jpg"><img class="issue-cover-summary-image" src="/4.jpg">'),
	'<img class="issue-summary-cover" src="/1.jpg"><img loading="lazy" decoding="async" class="issue-summary-cover" src="/2.jpg"><img class="issue-cover-summary-image" loading="eager" src="/3.jpg"><img loading="lazy" decoding="async" class="issue-cover-summary-image" src="/4.jpg">');
check('logo: own loading kept', IsPageHints::prioritizeLogo('<img class="header-desktop-logo" loading="lazy" src="/l.png">'), '<img class="header-desktop-logo" loading="lazy" src="/l.png">');
check('footer: no footer, no change', IsPageHints::lazyFooter('<img src="/a.png">'), '<img src="/a.png">');

// Theme files
$dir = __DIR__ . '/../individualizeScholar';
$registered = array('individualize_scholar_actions', 'individualize_scholar_country', 'individualize_scholar_coverage', 'individualize_scholar_archive_card');
$english = file_get_contents($dir . '/locale/en_US/locale.po');
$keys = array();
$templates = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir . '/templates', FilesystemIterator::SKIP_DOTS));
foreach ($templates as $file) {
	$tpl = file_get_contents($file->getPathname());
	$name = substr($file->getPathname(), strlen($dir) + 1);
	foreach (array('if', 'foreach', 'capture') as $tag) {
		check("$name: {{$tag}} balanced", preg_match_all('/\{' . $tag . '\b/', $tpl), preg_match_all('/\{\/' . $tag . '\}/', $tpl));
	}
	preg_match_all('/\{(individualize_scholar_\w+)/', $tpl, $calls);
	check("$name: only registered functions", array_values(array_diff($calls[1], $registered)), array());
	preg_match_all('/key="(plugins\.themes\.individualizeScholar\.[\w.]+)"/', $tpl, $used);
	$keys = array_merge($keys, $used[1]);
}
preg_match_all("/'(plugins\.themes\.individualizeScholar\.[\w.]+)'/", file_get_contents($dir . '/IndividualizeScholarThemePlugin.inc.php'), $used);
$keys = array_unique(array_merge($keys, $used[1]));
check('every locale key used exists in en_US', array_values(array_filter($keys, function ($k) use ($english) { return strpos($english, 'msgid "' . $k . '"') === false; })), array());
foreach (array($hl, $cat) as $block) {
	check("block template exists: $block", is_file($dir . '/templates/' . $block), true);
}
$css = file_get_contents($dir . '/styles/individualizeScholar.css');
check('stylesheet braces balanced', substr_count($css, '{'), substr_count($css, '}'));
foreach (array('ms-issue-coverage', 'ms-archive-stats', 'ms-article-actions', 'ms-abstract', 'ms-highlights', 'ms-categories', 'ms-readmore', 'ms-country-line', 'ms-dimensions') as $class) {
	check("stylesheet styles .$class", strpos($css, '.' . $class) !== false, true);
}
$wrong = array();
foreach (glob($dir . '/classes/*.php') as $file) {
	preg_match_all('/^\s*class\s+(\w+)/m', file_get_contents($file), $m);
	foreach ($m[1] as $class) {
		if (strpos($class, 'Is') !== 0 || basename($file) !== $class . '.inc.php') {
			$wrong[] = basename($file) . ': ' . $class;
		}
	}
}
check('helper classes are prefixed Is and named after their file', $wrong, array());

echo $fail === 0 ? "OK\n" : "$fail failure(s)\n";
exit($fail === 0 ? 0 : 1);
