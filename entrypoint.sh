#!/bin/sh
set -e

# Compatibility guard for the legacy Railway start-command form.
if [ "$1" = "php" ] && [ "$2" = "-S" ] && [ "$3" = '0.0.0.0:${PORT:-8080}' ]; then
  shift 3
  exec php -S "0.0.0.0:${PORT:-8080}" "$@"
fi

exec "$@"
