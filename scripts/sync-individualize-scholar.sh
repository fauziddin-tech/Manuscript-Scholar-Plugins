#!/usr/bin/env bash
# Copies the theme-independent helpers of Manuscript Scholar into the
# Individualize Scholar theme (individualizeScholar/), with the class prefix
# Ms renamed to Is so both themes can be installed side by side. Run it after
# changing one of the listed files; `--check` only reports copies that are out
# of date (used by CI).
set -eu

repo_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
target_dir="$repo_dir/individualizeScholar"
check=0
[ "${1:-}" = "--check" ] && check=1

classes="ArchiveStats ArticleActions CategoryCards ContentSignature CountryFromAffiliation CountryOptions DimensionsBadge FileSizes FragmentCache HomeHighlights IssueCoverage MetaDescription"

work_dir=$(mktemp -d)
trap 'rm -rf "$work_dir"' EXIT
mkdir -p "$work_dir/classes" "$work_dir/js"

for name in $classes; do
	source="$repo_dir/classes/Ms$name.inc.php"
	{
		sed -n '1,2p' "$source"
		echo "// Generated from classes/Ms$name.inc.php by scripts/sync-individualize-scholar.sh: edit that file, not this one."
		sed -n '3,$p' "$source" | sed -e 's/\bMs\([A-Z][A-Za-z]*\)/Is\1/g' -e 's/manuscriptScholarAdmin\.js/individualizeScholarAdmin.js/g'
	} > "$work_dir/classes/Is$name.inc.php"
done
{
	sed -n '1p' "$repo_dir/js/manuscriptScholarAdmin.js"
	echo " * Generated from js/manuscriptScholarAdmin.js by scripts/sync-individualize-scholar.sh: edit that file, not this one."
	sed -n '2,$p' "$repo_dir/js/manuscriptScholarAdmin.js" | sed -e 's/manuscriptScholarAdmin\.js/individualizeScholarAdmin.js/g' -e 's/Manuscript Scholar/Individualize Scholar/g'
} > "$work_dir/js/individualizeScholarAdmin.js"

status=0
for file in $(cd "$work_dir" && find . -type f | sort); do
	if [ "$check" -eq 1 ]; then
		if ! cmp -s "$work_dir/$file" "$target_dir/$file"; then
			echo "individualizeScholar/${file#./} is out of date: run scripts/sync-individualize-scholar.sh" >&2
			status=1
		fi
	else
		mkdir -p "$(dirname "$target_dir/$file")"
		cp "$work_dir/$file" "$target_dir/$file"
	fi
done
exit "$status"
