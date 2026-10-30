#!/usr/bin/env bash
#
# Builds the distributable plugin ZIP.
#
#   tools/build-zip.sh            # version taken from the plugin header
#   tools/build-zip.sh 1.0.1      # explicit version
#
# The archive contains a single top-level directory, `qevora-layouts/`, holding
# runtime files only: no version control metadata, no tests, no build tooling.
# The file list comes from `git ls-files` minus `.distignore`, so anything that
# is not committed cannot leak into a release either.
#
# Requirements: bash, git, zip. No Composer, no Node, no network access.

set -euo pipefail

usage() {
	echo "Usage: $0 [version]" >&2
	exit 2
}

case "${1:-}" in
	-h|--help)
		usage
		;;
esac

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PLUGIN_FILE="qevora-layouts.php"
SLUG="qevora-layouts"

if [[ ! -f "$PLUGIN_FILE" ]]; then
	echo "error: $PLUGIN_FILE not found in $ROOT" >&2
	exit 1
fi

VERSION="${1:-}"

if [[ -z "$VERSION" ]]; then
	VERSION="$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "$PLUGIN_FILE" | sed -E 's/.*Version:[[:space:]]*//; s/[[:space:]]*$//')"
fi

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[A-Za-z0-9.-]+)?$ ]]; then
	echo "error: '$VERSION' is not a valid version (expected x.y.z)" >&2
	exit 1
fi

DIST_IGNORE="$ROOT/.distignore"

if [[ ! -f "$DIST_IGNORE" ]]; then
	echo "error: .distignore is missing" >&2
	exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
	echo "error: zip is required but not installed" >&2
	exit 1
fi

# ---------------------------------------------------------------------------
# 1. Build the exclusion pattern for grep -E.
# ---------------------------------------------------------------------------

PATTERN_FILE="$(mktemp)"
trap 'rm -f "$PATTERN_FILE" "$STAGING_LIST" 2>/dev/null || true' EXIT

while IFS= read -r line || [[ -n "$line" ]]; do
	# Strip comments and blank lines.
	line="${line%%#*}"
	line="$(printf '%s' "$line" | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//')"

	if [[ -z "$line" ]]; then
		continue
	fi

	if [[ "$line" == */ ]]; then
		# Directory: match everything below it, at any depth.
		printf '(^|/)%s\n' "${line%/}" >> "$PATTERN_FILE"
	elif [[ "$line" == \*.* ]]; then
		# Extension glob.
		printf '\\%s$\n' "${line/\*/}" >> "$PATTERN_FILE"
	elif [[ "$line" == *.git* || "$line" == .* ]]; then
		# Dotfile at the repository root.
		printf '^%s$\n' "$(printf '%s' "$line" | sed -E 's/\./\\./g')" >> "$PATTERN_FILE"
	else
		printf '(^|/)%s(/|$)\n' "$line" >> "$PATTERN_FILE"
	fi
done < "$DIST_IGNORE"

# ---------------------------------------------------------------------------
# 2. Collect the tracked runtime files.
# ---------------------------------------------------------------------------

STAGING_LIST="$(mktemp)"

git ls-files | grep -Evf "$PATTERN_FILE" > "$STAGING_LIST" || true

COUNT="$(wc -l < "$STAGING_LIST" | tr -d ' ')"

if [[ "$COUNT" -eq 0 ]]; then
	echo "error: no files left after applying .distignore" >&2
	exit 1
fi

# ---------------------------------------------------------------------------
# 3. Stage them under a single top-level directory.
# ---------------------------------------------------------------------------

BUILD_DIR="$(mktemp -d)"
STAGE="$BUILD_DIR/$SLUG"

mkdir -p "$STAGE"

while IFS= read -r file; do
	[[ -z "$file" ]] && continue
	mkdir -p "$STAGE/$(dirname "$file")"
	cp -p "$file" "$STAGE/$file"
done < "$STAGING_LIST"

# ---------------------------------------------------------------------------
# 4. Zip it.
# ---------------------------------------------------------------------------

mkdir -p "$ROOT/release"
TARGET="$ROOT/release/$SLUG-$VERSION.zip"

rm -f "$TARGET"

( cd "$BUILD_DIR" && zip -qr "$TARGET" "$SLUG" )

rm -rf "$BUILD_DIR"

# ---------------------------------------------------------------------------
# 5. Verify the result.
# ---------------------------------------------------------------------------

echo "Built $TARGET"
echo "Files: $COUNT  Version: $VERSION  Size: $(du -h "$TARGET" | cut -f1)"
echo

FAILURES=0

check_absent() {
	local needle="$1"
	if unzip -l "$TARGET" | grep -q "$needle"; then
		echo "  FAIL: the archive contains $needle"
		FAILURES=$((FAILURES + 1))
	else
		echo "  ok:   no $needle"
	fi
}

check_present() {
	local needle="$1"
	if unzip -l "$TARGET" | grep -q "$needle"; then
		echo "  ok:   contains $needle"
	else
		echo "  FAIL: the archive is missing $needle"
		FAILURES=$((FAILURES + 1))
	fi
}

echo "Verifying:"

check_absent '/\.git/'
check_absent '/tests/'
check_absent '/tools/'
check_absent '/release/'
check_absent 'node_modules'
check_absent '\.zip$'
check_absent '\.distignore'

check_present "$SLUG/$PLUGIN_FILE"
check_present "$SLUG/uninstall.php"
check_present "$SLUG/LICENSE"
check_present "$SLUG/readme.txt"
check_present "$SLUG/includes/"
check_present "$SLUG/assets/"
check_present "$SLUG/templates/starter/"
check_present "$SLUG/languages/$SLUG.pot"

TOP_LEVEL="$(unzip -l "$TARGET" | awk 'NR>3 { print $4 }' | grep -v '^$' | cut -d/ -f1 | sort -u)"

if [[ "$TOP_LEVEL" != "$SLUG" ]]; then
	echo "  FAIL: unexpected top-level entries: $TOP_LEVEL"
	FAILURES=$((FAILURES + 1))
else
	echo "  ok:   single top-level directory '$SLUG/'"
fi

echo

if [[ "$FAILURES" -gt 0 ]]; then
	echo "$FAILURES verification check(s) failed." >&2
	exit 1
fi

echo "Release archive is ready: ${TARGET#$ROOT/}"
