#!/usr/bin/env bash
#
# Optional helper: point the e2e suite at a local WordPress install.
#
# WARNING: this RESETS the password of the given admin account. Never run it
# against a site whose credentials matter — use a throwaway dev install.
#
# Usage:
#   WP_PATH=/path/to/wordpress WP_ADMIN_USER=admin WP_ADMIN_PASS=password \
#     bash tests/e2e/setup.sh
#
set -euo pipefail

WP_PATH="${WP_PATH:-}"
ADMIN_USER="${WP_ADMIN_USER:-admin}"
ADMIN_PASS="${WP_ADMIN_PASS:-password}"

if [ -z "$WP_PATH" ]; then
  echo "WP_PATH is required — the WordPress directory to configure." >&2
  exit 1
fi

if ! command -v wp >/dev/null 2>&1; then
  echo "WP-CLI (wp) is required and was not found in PATH." >&2
  exit 1
fi

echo "About to reset the password of '${ADMIN_USER}' in ${WP_PATH}."
printf 'Continue? [y/N] '
read -r reply
case "$reply" in
  [yY]*) ;;
  *) echo "Aborted."; exit 1 ;;
esac

wp --path="$WP_PATH" --skip-plugins --skip-themes \
  user update "$ADMIN_USER" --user_pass="$ADMIN_PASS"

echo "Done. Credentials: ${ADMIN_USER} / ${ADMIN_PASS}"
echo "Next: copy tests/e2e/.env.test.example to tests/e2e/.env.test, then run 'yarn test:e2e'."
