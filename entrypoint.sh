#!/bin/sh
set -e

# Railway previously stored a literal shell expression in the service start command.
# Repair only that legacy php -S invocation; pass every other command through unchanged.
if [ "$1" = "php" ] && [ "$2" = "-S" ] && [ "$3" = '0.0.0.0:${PORT:-8080}' ]; then
  shift 3
  exec php -S "0.0.0.0:${PORT:-8080}" "$@"
fi

exec "$@"
