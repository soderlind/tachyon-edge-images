#!/usr/bin/env bash
# Differential test: run the same cases against the Reference Plugin and
# Tachyon Edge Images in a throwaway WordPress (SQLite), then compare.
#
# Env: DIFF_REUSE=1 (reuse an existing .work install), WP_VERSION (default latest), REFERENCE_REF (default 652ecd2),
#      REFERENCE_DIR (use a local checkout instead of downloading).
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$HERE/../.." && pwd)"
WORK="$HERE/.work"
WP_DIR="$WORK/wp"
WP_VERSION="${WP_VERSION:-latest}"
REFERENCE_REF="${REFERENCE_REF:-652ecd2}"
# Run the WP-CLI phar with enough memory to extract WordPress.
WPCLI=(php -d memory_limit=1G "$(command -v wp)")
WP=("${WPCLI[@]}" --path="$WP_DIR" --skip-plugins --skip-themes --quiet)

if [ -n "${DIFF_REUSE:-}" ] && [ -f "$WP_DIR/wp-config.php" ]; then
	echo "Reusing $WORK"
else
rm -rf "$WORK"
mkdir -p "$WORK"

echo "Downloading WordPress $WP_VERSION"
"${WPCLI[@]}" core download --path="$WP_DIR" --version="$WP_VERSION" --quiet

echo "Installing the SQLite drop-in"
curl -sSLo "$WORK/sqlite.zip" https://downloads.wordpress.org/plugin/sqlite-database-integration.zip
unzip -q "$WORK/sqlite.zip" -d "$WP_DIR/wp-content/plugins"
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$WP_DIR/wp-content/plugins/sqlite-database-integration#" \
	-e 's#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#' \
	"$WP_DIR/wp-content/plugins/sqlite-database-integration/db.copy" > "$WP_DIR/wp-content/db.php"

"${WPCLI[@]}" config create --path="$WP_DIR" --dbname=wp --dbuser=wp --dbpass=wp --skip-check --quiet
"${WPCLI[@]}" core install --path="$WP_DIR" --url=https://example.com --title=Differential \
	--admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email --quiet

echo "Fetching the Reference Plugin at $REFERENCE_REF"
if [ -n "${REFERENCE_DIR:-}" ]; then
	git -C "$REFERENCE_DIR" archive --prefix=reference/ "$REFERENCE_REF" | tar -x -C "$WORK"
else
	mkdir -p "$WORK/reference"
	curl -sSL "https://codeload.github.com/humanmade/tachyon-plugin/tar.gz/$REFERENCE_REF" | tar -xz --strip-components=1 -C "$WORK/reference"
fi

"${WP[@]}" eval-file "$HERE/fixtures.php"
fi

for subject in reference edge; do
	for mode in configured unconfigured; do
		echo "Running $subject ($mode)"
		DIFF_SUBJECT="$subject" DIFF_MODE="$mode" DIFF_REFERENCE="$WORK/reference/tachyon.php" DIFF_EDGE="$ROOT/tachyon-edge-images.php" \
			"${WP[@]}" eval-file "$HERE/runner.php" > "$WORK/$subject-$mode.json" 2> "$WORK/$subject-$mode.log"
	done
done

echo "Reference Plugin PHP warnings: $(grep -c '^PHP ' "$WORK"/reference-*.log | awk -F: '{s+=$2} END {print s}')"
if grep -q . "$WORK"/edge-*.log; then
	echo "FAIL Tachyon Edge Images wrote to stderr:"
	cat "$WORK"/edge-*.log
	exit 1
fi

php "$HERE/compare.php" "$WORK"
