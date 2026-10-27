#!/usr/bin/env bash
# Every locale file must define the same message ids as en_US (for both themes).
set -eu

theme_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
status=0

for locale_dir in "$theme_dir/locale" "$theme_dir/individualizeScholar/locale"; do
	base="$locale_dir/en_US/locale.po"
	for file in "$locale_dir"/*/locale.po; do
		if ! diff <(grep '^msgid' "$base") <(grep '^msgid' "$file") >/dev/null; then
			echo "$file: message ids differ from en_US" >&2
			status=1
		fi
	done
done
exit "$status"
