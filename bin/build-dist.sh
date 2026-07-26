#!/usr/bin/env bash
#
# Ricostruisce il pacchetto che verrebbe pubblicato su wordpress.org, cioe' il
# repository del plugin meno tutto quanto elencato in .distignore.
#
# E' la stessa selezione che applica 10up/action-wordpress-plugin-deploy: averla
# in un posto solo evita che CI, controllo divergenza e rilascio usino criteri
# diversi fra loro.
#
# Opera sulla directory di lavoro corrente, che deve essere la radice del
# plugin (o su $PLUGIN_ROOT se impostata).
#
# Uso: bash .../build-dist.sh [destinazione]      (default: build)

set -euo pipefail

DEST="${1:-build}"
ROOT="${PLUGIN_ROOT:-$PWD}"

cd "$ROOT"

if [ ! -f .distignore ]; then
    echo "ERRORE: .distignore non trovato in $ROOT" >&2
    exit 1
fi

rm -rf "$DEST"
mkdir -p "$DEST"

rsync -a \
    --exclude='.git' \
    --exclude='/build' \
    --exclude-from='.distignore' \
    ./ "$DEST/"

echo "Pacchetto ricostruito in: $DEST"
find "$DEST" -type f | sort | sed 's|^|  |'

# Rete di sicurezza: ogni file di sviluppo aggiunto nella radice va escluso a
# mano in .distignore, ed e' facile dimenticarsene. Un file nascosto nel
# pacchetto e' sempre un errore: a chi installa il plugin non serve, e finirebbe
# nello zip scaricato dagli utenti.
#
# Non basta .gitignore: l'action di deploy copia dal disco, non da git, quindi
# un artefatto generato dai test (.phpunit.result.cache) verrebbe pubblicato.
LEAKED="$(find "$DEST" -name '.*' -not -name '.' -not -name '..' | sort)"

if [ -n "$LEAKED" ]; then
    echo "" >&2
    echo "ERRORE: file di sviluppo finiti nel pacchetto pubblicato:" >&2
    echo "$LEAKED" | sed 's|^|  |' >&2
    echo "" >&2
    echo "Aggiungili a .distignore." >&2
    exit 1
fi
