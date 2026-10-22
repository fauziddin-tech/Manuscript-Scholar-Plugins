#!/usr/bin/env bash
# Every locale file must define the same message ids as en_US.
set -eu

theme_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
base="$theme_dir/locale/en_US/locale.po"
status=0

for file in "$theme_dir"/locale/*/locale.po; do
	if ! diff <(grep '^msgid' "$base") <(grep '^msgid' "$file") >/dev/null; then
		echo "$file: message ids differ from en_US" >&2
		status=1
	fi
done
exit "$status"
