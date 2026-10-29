#!/usr/bin/env bash
#
# Run a script from the private scripts repo in this site's context.
#
# The private repo is cloned into scripts/ (git-ignored here), so scripts keep
# using paths like scripts/foo.php relative to the project root.
#
# Usage (from anywhere inside the checkout):
#   tools/run-private-script.sh fetch                  clone or update scripts/
#   tools/run-private-script.sh [--no-pull] NAME [ARGS...]
#
#   NAME.php  ->  $DRUSH php:script scripts/NAME.php [-- ARGS...]
#   NAME.sh   ->  DRUSH=$DRUSH bash scripts/NAME.sh [ARGS...]
#
# DRUSH defaults to "ddev drush" in a DDEV project on the host, otherwise
# ./drush-dir/drush (the server). Set DRUSH to override it.
# PRIVATE_SCRIPTS_REPO overrides the clone URL.
#
set -euo pipefail

REPO_URL="${PRIVATE_SCRIPTS_REPO:-git@github.com:thesolentmetropolitan/thesolentmetropolitan.com-content-refactoring.git}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
DIR="$ROOT/scripts"
cd "$ROOT"

fetch() {
  if [ -d "$DIR/.git" ]; then
    git -C "$DIR" pull --ff-only --quiet || {
      echo "Could not fast-forward scripts/ (local commits or changes?). Sort it out in scripts/, or rerun with --no-pull." >&2
      exit 1
    }
  elif [ -e "$DIR" ] && [ -n "$(ls -A "$DIR")" ]; then
    echo "scripts/ exists but is not a clone of the private repo; move it aside first." >&2
    exit 1
  else
    git clone --quiet "$REPO_URL" "$DIR"
  fi
}

if [ -z "${DRUSH:-}" ]; then
  if [ -z "${IS_DDEV_PROJECT:-}" ] && [ -d .ddev ] && command -v ddev >/dev/null 2>&1; then
    DRUSH="ddev drush"
  else
    DRUSH="./drush-dir/drush"
  fi
fi
export DRUSH

PULL=1
if [ "${1:-}" = "--no-pull" ]; then PULL=0; shift; fi

case "${1:-}" in
  ""|-h|--help)
    sed -n '3,17p' "$0" | sed 's/^# \{0,1\}//'
    exit 0
    ;;
  fetch)
    fetch
    echo "scripts/ is at $(git -C "$DIR" log -1 --format='%h %ad' --date=short)"
    exit 0
    ;;
esac

NAME="${1#scripts/}"
shift
[ "$PULL" = 1 ] && fetch

if [ ! -f "$DIR/$NAME" ]; then
  echo "No such script: scripts/$NAME" >&2
  exit 1
fi

case "$NAME" in
  *.php)
    if [ $# -gt 0 ]; then
      [ "$1" = "--" ] && shift
      exec $DRUSH php:script "scripts/$NAME" -- "$@"
    fi
    exec $DRUSH php:script "scripts/$NAME"
    ;;
  *.sh)
    exec bash "scripts/$NAME" "$@"
    ;;
  *)
    echo "Don't know how to run scripts/$NAME (expected .php or .sh)." >&2
    exit 1
    ;;
esac
