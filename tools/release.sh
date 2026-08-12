#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:?Uso: tools/release.sh <versao>}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="preventivemaintenance"
DIST_DIR="${ROOT_DIR}/dist"
ARCHIVE="glpi-${PLUGIN_DIR}-${VERSION}.tar.bz2"

# Empacota o estado atual do diretório de trabalho (já com a versão
# atualizada por bump-version.sh, ainda não commitada nesta etapa do
# semantic-release) em um tar.bz2 com o diretório do plugin como raiz,
# pronto para ser extraído em glpi/plugins/.
mkdir -p "$DIST_DIR"

tar -cjf "${DIST_DIR}/${ARCHIVE}" \
    --exclude-from="${ROOT_DIR}/tools/release-exclude.txt" \
    --transform "s|^\./|${PLUGIN_DIR}/|" \
    -C "$ROOT_DIR" .

echo "Pacote gerado em ${DIST_DIR}/${ARCHIVE}"
