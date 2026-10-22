#!/usr/bin/env bash
# Usage: scripts/bump-version.sh X.Y.Z
# Sets the version in version.xml (with today's date), the plugin class and the stylesheet header.
set -eu

new=${1:-}
case "$new" in
	[0-9]*.[0-9]*.[0-9]*) ;;
	*) echo "Usage: $0 X.Y.Z" >&2; exit 1 ;;
esac

theme_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
today=$(date -u +%F)

sed -i "s:<release>[0-9.]*</release>:<release>$new.0</release>:; s:<date>[0-9-]*</date>:<date>$today</date>:" "$theme_dir/version.xml"
sed -i "s:const VERSION = '[0-9.]*';:const VERSION = '$new';:" "$theme_dir/ManuscriptScholarThemePlugin.inc.php"
sed -i "2s:Manuscript Scholar [0-9.]*:Manuscript Scholar $new:" "$theme_dir/styles/manuscriptScholar.css"

"$theme_dir/scripts/check-version.sh"
