#!/bin/sh
set -e

mkdir -p /app/storage/uploads
if [ -d /app/seed_galleys ]; then
  for src in /app/seed_galleys/*.pdf; do
    [ -f "$src" ] || continue
    dest="/app/storage/uploads/$(basename "$src")"
    if [ ! -f "$dest" ] || ! cmp -s "$src" "$dest"; then
      cp "$src" "$dest"
    fi
  done
fi

if [ "$1" = "php" ] && [ "$2" = "-S" ] && [ "$3" = '0.0.0.0:${PORT:-8080}' ]; then
  shift 3
  exec php -S "0.0.0.0:${PORT:-8080}" "$@"
fi

exec "$@"
