<?php
// Minify a CSS file in place (used by scripts/package-theme.sh).
require __DIR__ . '/../classes/CssMinifier.inc.php';
if ($argc !== 2 || !is_file($argv[1])) {
	fwrite(STDERR, "usage: php scripts/minify-css.php FILE.css\n");
	exit(1);
}
$source = file_get_contents($argv[1]);
// Keep the licence header comment (the first comment of the file)
$header = preg_match('~^\s*(/\*.*?\*/)~s', $source, $m) ? $m[1] . "\n" : '';
file_put_contents($argv[1], $header . CssMinifier::minify($source));
