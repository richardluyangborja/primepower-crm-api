#!/bin/sh
# Syncs the React frontend build into Laravel's public dir for
# single-artifact deployments (Laravel serves the SPA + API on one origin).
#
# Usage (from repo root):
#   cd front && npm ci && VITE_API_URL= npm run build
#   ./backend/scripts/sync-frontend.sh [front/dist] [backend/public]
#
# Notes:
# - Build with an EMPTY VITE_API_URL so the SPA calls the same origin.
# - Preserves Laravel's public/index.php, .htaccess, build/ (backend Vite).
# - Re-run after every frontend change; the copied files are what Docker
#   picks up via `COPY . .` (backend + frontend are separate repos, so the
#   Dockerfile cannot reach ../front at image build time on the host).
set -e

DIST="${1:-$(dirname "$0")/../../front/dist}"
PUBLIC="${2:-$(dirname "$0")/../public}"

if [ ! -f "$DIST/index.html" ]; then
  echo "error: $DIST/index.html not found. Build the frontend first:" >&2
  echo "  cd front && npm ci && VITE_API_URL= npm run build" >&2
  exit 1
fi

mkdir -p "$PUBLIC"

# SPA shell.
cp "$DIST/index.html" "$PUBLIC/index.html"

# Hashed JS/CSS.
if [ -d "$DIST/assets" ]; then
  rm -rf "$PUBLIC/assets"
  cp -r "$DIST/assets" "$PUBLIC/assets"
fi

# Top-level public files Vite copies through (logos, svg, etc.), excluding
# its own index.html which is handled above.
for f in "$DIST"/*; do
  base="$(basename "$f")"
  case "$base" in
    index.html|assets) continue ;;
  esac
  if [ -f "$f" ]; then
    cp "$f" "$PUBLIC/$base"
  fi
done

echo "Synced $DIST -> $PUBLIC"
ls -la "$PUBLIC" | grep -E 'index\.html|assets' || true
