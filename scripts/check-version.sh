#!/usr/bin/env bash
# Checks that every place that carries the theme version agrees with version.xml.
set -eu

theme_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
version=$(sed -n 's:.*<release>\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\)\.0</release>.*:\1:p' "$theme_dir/version.xml")
date_value=$(sed -n 's:.*<date>\([0-9-]*\)</date>.*:\1:p' "$theme_dir/version.xml")
status=0

if [ -z "$version" ]; then
	echo "version.xml: cannot read <release> (expected X.Y.Z.0)" >&2
	exit 1
fi

if ! grep -q "const VERSION = '$version';" "$theme_dir/ManuscriptScholarThemePlugin.inc.php"; then
	echo "ManuscriptScholarThemePlugin.inc.php: VERSION is not $version" >&2
	status=1
fi
if ! sed -n '2p' "$theme_dir/styles/manuscriptScholar.css" | grep -q "Manuscript Scholar $version:"; then
	echo "styles/manuscriptScholar.css: line 2 does not say 'Manuscript Scholar $version:'" >&2
	status=1
fi
if ! printf '%s' "$date_value" | grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'; then
	echo "version.xml: <date> is not YYYY-MM-DD" >&2
	status=1
fi

[ "$status" -eq 0 ] && echo "$version"
exit "$status"
