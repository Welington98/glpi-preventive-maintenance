#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:?Uso: tools/bump-version.sh <versao>}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

node -e "
  const fs = require('fs');
  const path = '${ROOT_DIR}/manifest.json';
  const data = JSON.parse(fs.readFileSync(path, 'utf8'));
  data.version = '${VERSION}';
  fs.writeFileSync(path, JSON.stringify(data, null, 2) + '\n');
"

sed -i.bak -E "s/'version'([[:space:]]*)=>([[:space:]]*)'[^']*'/'version'\1=>\2'${VERSION}'/" "${ROOT_DIR}/setup.php"
rm -f "${ROOT_DIR}/setup.php.bak"

echo "Versão atualizada para ${VERSION} em manifest.json e setup.php"
