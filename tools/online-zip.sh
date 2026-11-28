#!/bin/sh
# Makes the zip for the live site: every file the app needs, taken from your last commit.
#
#   sh tools/online-zip.sh            -> ~/Downloads/teamsync-online.zip
#   sh tools/online-zip.sh other.zip  -> other.zip
#
# Then, in the host's File Manager: open htdocs -> Upload -> Zip & Extract -> this zip.
# The zip has no config/ folder, so the passwords in config/db.php on the server stay as they are.
# It has no database/, docs/ or tests/ either: the live site does not need them.
# It adds version.txt, so https://your-site/version.txt shows which commit is online.

set -e
cd "$(dirname "$0")/.."

out="${1:-$HOME/Downloads/teamsync-online.zip}"
case "$out" in /*) ;; *) out="$PWD/$out" ;; esac

# Everything the live site needs. A new top-level folder or file must be added here too
# (the test "tools/online-zip.sh packs every file the pages load" checks this).
APP_FILES="$(git ls-files '*.php' | grep -v /) favicon.ico manifest.json sw.js offline.html api assets includes lib"

if [ -n "$(git status --porcelain -- $APP_FILES)" ]; then
    echo "Note: you have changes that are not committed yet. The zip has your last commit only."
fi

rm -f "$out"
git archive --format=zip -o "$out" HEAD -- $APP_FILES

tmp="$(mktemp -d)"
git log -1 --date=format:'%Y-%m-%d %H:%M' --format='Teamsync %h (%ad) %s' > "$tmp/version.txt"
(cd "$tmp" && zip -q "$out" version.txt)
rm -rf "$tmp"

echo "Made $out"
echo "Version: $(git log -1 --format='%h %s')"
echo "Next: File Manager -> open htdocs -> Upload -> Zip & Extract -> this file."
