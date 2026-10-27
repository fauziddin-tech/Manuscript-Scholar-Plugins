#!/usr/bin/env bash
# Builds individualizeScholar-vX.Y.Z.tar.gz (root folder individualizeScholar/)
# from individualizeScholar/, with the country flags of the repository root
# and a minified stylesheet.
set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
repo_dir=$(CDPATH= cd -- "$script_dir/.." && pwd)
theme_dir="$repo_dir/individualizeScholar"
theme_version=$("$script_dir/check-version-individualize-scholar.sh")

"$script_dir/sync-individualize-scholar.sh" --check

archive_path=${1:-"$repo_dir/../individualizeScholar-v${theme_version}.tar.gz"}

work_dir=$(mktemp -d)
trap 'rm -rf "$work_dir"' EXIT
tar -C "$theme_dir" -cf - . | tar -xf - -C "$work_dir"
tar -C "$repo_dir" -cf - flags | tar -xf - -C "$work_dir"
cp "$repo_dir/LICENSE" "$work_dir/"
php "$script_dir/minify-css.php" "$work_dir/styles/individualizeScholar.css"

tar \
	--transform='s,^\.,individualizeScholar,' \
	-C "$work_dir" \
	-czf "$archive_path" \
	.

first_entry=$(tar -tzf "$archive_path" | sed -n '1p')
case "$first_entry" in
	individualizeScholar/*) ;;
	*)
		echo "Invalid package root: $first_entry" >&2
		exit 1
		;;
esac

echo "$archive_path"
