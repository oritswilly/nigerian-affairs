#!/bin/sh
set -e

mkdir -p /app/storage/uploads

ensure_pdf() {
  name="$1"
  sha="$2"
  url="$3"
  dest="/app/storage/uploads/$name"
  if [ -f "$dest" ] && printf '%s  %s\n' "$sha" "$dest" | sha256sum -c - >/dev/null 2>&1; then
    echo "GALLEY_OK $name"
    return
  fi
  tmp="$dest.part"
  rm -f "$tmp"
  curl -fL --retry 3 --connect-timeout 20 "$url" -o "$tmp"
  printf '%s  %s\n' "$sha" "$tmp" | sha256sum -c -
  mv "$tmp" "$dest"
  echo "GALLEY_STORED $name"
}

ensure_pdf "na-1-8a6efc3695bdd66a89f064ab.pdf" "8a6efc3695bdd66a89f064abddaace4db3f4edf07779278111c16fd0ab63738a" "https://sdmntprbrazilsouth.oaiusercontent.com/files/00000000-cf48-820e-b052-53ff8596f279/raw?se=2026-09-25T09%3A34%3A13Z&sp=r&sv=2026-02-06&sr=b&scid=32e646ec-f5c3-5cb9-a134-6e7e83128c59&skoid=4a431cd2-eafd-49c7-90be-bbb4d45e696d&sktid=a48cca56-e6da-484e-a814-9c849652bcb3&skt=2026-09-25T06%3A59%3A11Z&ske=2026-09-26T06%3A59%3A11Z&sks=b&skv=2026-02-06&sig=0Mix8I3i6TdXW5LILxZM3vchTFhN8pSGnoEYY0zRxb8%3D"
ensure_pdf "na-2-aed91195bf0e5dd3d805273d.pdf" "aed91195bf0e5dd3d805273d97a47fc84312b98f94da089135b78534cf43066a" "https://sdmntprbrazilsouth.oaiusercontent.com/files/00000000-3680-820e-9399-21d3feff05d2/raw?se=2026-09-25T09%3A34%3A14Z&sp=r&sv=2026-02-06&sr=b&scid=bcc94e14-a6ed-57a0-a43e-3f42ccaac1a2&skoid=4a431cd2-eafd-49c7-90be-bbb4d45e696d&sktid=a48cca56-e6da-484e-a814-9c849652bcb3&skt=2026-09-25T09%3A23%3A25Z&ske=2026-09-26T09%3A23%3A25Z&sks=b&skv=2026-02-06&sig=6PWK%2BUsph%2BiXz5FWABmeVm4RGhPvYquP6eXP%2Br8kJKM%3D"
ensure_pdf "na-3-5e43b37f2054abc5a6921e1b.pdf" "5e43b37f2054abc5a6921e1bb3c7d239ee77e0cf901436c81d786b7dd44712dc" "https://sdmntprbrazilsouth.oaiusercontent.com/files/00000000-92a4-820e-a073-a18bc044db44/raw?se=2026-09-25T09%3A34%3A14Z&sp=r&sv=2026-02-06&sr=b&scid=43efea82-1656-5e16-b615-4b101eba6978&skoid=4a431cd2-eafd-49c7-90be-bbb4d45e696d&sktid=a48cca56-e6da-484e-a814-9c849652bcb3&skt=2026-09-25T09%3A16%3A35Z&ske=2026-09-26T09%3A16%3A35Z&sks=b&skv=2026-02-06&sig=OEIe1UmXG637xnUoykMAbn9cIJcePuzLBHM/fsfefsQ%3D"
ensure_pdf "na-4-794d942ed538edc985f4ba2b.pdf" "794d942ed538edc985f4ba2b85d314d2300f57dfa4ee29020e63679c62a935ca" "https://sdmntprbrazilsouth.oaiusercontent.com/files/00000000-e7c8-820e-acbc-eaf03e1b2071/raw?se=2026-09-25T09%3A34%3A13Z&sp=r&sv=2026-02-06&sr=b&scid=ed7a2338-b654-56a1-98bf-aef4a1ec91ab&skoid=4a431cd2-eafd-49c7-90be-bbb4d45e696d&sktid=a48cca56-e6da-484e-a814-9c849652bcb3&skt=2026-09-25T08%3A55%3A30Z&ske=2026-09-26T08%3A55%3A30Z&sks=b&skv=2026-02-06&sig=zGTiuPdxK2FuKin9tJodZtn4oGX4xIJ6ErRlvzuLqGE%3D"
ensure_pdf "na-5-2b831478336108d42cf3da6a.pdf" "2b831478336108d42cf3da6ad3aa8717178ca7fde1d47ced1bf754b635649d36" "https://sdmntprbrazilsouth.oaiusercontent.com/files/00000000-c8e4-820e-a920-af8b4d75239b/raw?se=2026-09-25T09%3A34%3A13Z&sp=r&sv=2026-02-06&sr=b&scid=34eb8472-d7b8-5eb0-8512-090deca899b3&skoid=4a431cd2-eafd-49c7-90be-bbb4d45e696d&sktid=a48cca56-e6da-484e-a814-9c849652bcb3&skt=2026-09-25T09%3A12%3A37Z&ske=2026-09-26T09%3A12%3A37Z&sks=b&skv=2026-02-06&sig=bplbfSpmg8Pef5lyH%2BxUJJa9XkwwV5AyBr9PBBaIUC0%3D"

echo "ALL_JUNE_2026_GALLEYS_READY"

# Repair only the legacy literal Railway command if it is supplied.
if [ "$1" = "php" ] && [ "$2" = "-S" ] && [ "$3" = '0.0.0.0:${PORT:-8080}' ]; then
  shift 3
  exec php -S "0.0.0.0:${PORT:-8080}" "$@"
fi

exec "$@"
