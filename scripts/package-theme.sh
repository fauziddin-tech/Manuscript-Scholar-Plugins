#!/usr/bin/env bash
set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
theme_dir=$(CDPATH= cd -- "$script_dir/.." && pwd)
theme_version=$(sed -n 's:.*<release>\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\)\.0</release>.*:\1:p' "$theme_dir/version.xml")

if [ -z "$theme_version" ]; then
	echo "Unable to read the release number from version.xml" >&2
	exit 1
fi

archive_path=${1:-"$theme_dir/../manuscriptScholar-v${theme_version}.tar.gz"}

# Build from a copy so the stylesheet can be minified without touching the source
work_dir=$(mktemp -d)
trap 'rm -rf "$work_dir"' EXIT
tar \
	--exclude='./.git' \
	--exclude='./.github' \
	--exclude='./scripts' \
	--exclude='./tests' \
	-C "$theme_dir" \
	-cf - . | tar -xf - -C "$work_dir"
php "$script_dir/minify-css.php" "$work_dir/styles/manuscriptScholar.css"

tar \
	--transform='s,^\.,manuscriptScholar,' \
	-C "$work_dir" \
	-czf "$archive_path" \
	.

first_entry=$(tar -tzf "$archive_path" | sed -n '1p')
case "$first_entry" in
	manuscriptScholar/*) ;;
	*)
		echo "Invalid package root: $first_entry" >&2
		exit 1
		;;
esac

echo "$archive_path"
