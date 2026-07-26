# wp-plugin-ci

Strumenti e workflow condivisi per il rilascio dei plugin WordPress `speed-up-*`.

Serve a non mantenere otto copie della stessa CI. Fa **due cose insieme**:

- espone **workflow riutilizzabili**, richiamati dai repo dei plugin con `uses:`
- si installa come **dipendenza composer**, così gli stessi script sono
  disponibili in locale e all'hook pre-commit

Le due cose stanno nello stesso repository di proposito: workflow e script si
richiamano a vicenda, e tenerli separati significherebbe doverli versionare in
coppia.

> Questo README è in italiano perché è documentazione interna. I README dei
> plugin sono in inglese, perché li leggono gli utenti.

## Come lo usa un plugin

### 1. composer.json

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/nigrosimone/wp-plugin-ci" }
    ],
    "require-dev": {
        "nigrosimone/wp-plugin-ci": "^1.0",
        "squizlabs/php_codesniffer": "^3.9",
        "wp-coding-standards/wpcs": "^3.1",
        "phpcompatibility/phpcompatibility-wp": "^2.1",
        "dealerdirect/phpcodesniffer-composer-installer": "^1.0",
        "phpunit/phpunit": "^9.6"
    },
    "config": {
        "allow-plugins": {
            "dealerdirect/phpcodesniffer-composer-installer": true
        }
    },
    "scripts": {
        "post-install-cmd": "wp-plugin-install-hooks",
        "post-update-cmd": "wp-plugin-install-hooks",
        "lint": "wp-plugin-lint",
        "check-version": "wp-plugin-check-version",
        "phpcs": "phpcs",
        "phpcbf": "phpcbf",
        "test": "phpunit"
    }
}
```

`vcs` invece di Packagist: il pacchetto è per uso interno e non ha senso
pubblicarlo. Composer legge i tag di questo repository.

### 2. I quattro workflow

`.github/workflows/ci.yml`

```yaml
name: CI
on:
  push:
    branches: [main]
  pull_request:
  workflow_dispatch:
jobs:
  ci:
    uses: nigrosimone/wp-plugin-ci/.github/workflows/plugin-ci.yml@v1
    with:
      min-php: '7.0-'
      plugin-check-ignore-codes: 'WordPress.WP.AlternativeFunctions.file_system_operations_fopen,...'
```

`.github/workflows/release.yml`

```yaml
name: Rilascio
on:
  push:
    branches: [main]
  workflow_dispatch:
    inputs:
      force:
        description: 'Ripubblica anche se il tag esiste gia'
        type: boolean
        default: false
concurrency:
  group: release
  cancel-in-progress: false
jobs:
  release:
    uses: nigrosimone/wp-plugin-ci/.github/workflows/plugin-release.yml@v1
    with:
      min-php: '7.0-'
      force: ${{ inputs.force == true }}
    secrets:
      SVN_USERNAME: ${{ secrets.SVN_USERNAME }}
      SVN_PASSWORD: ${{ secrets.SVN_PASSWORD }}
```

`.github/workflows/prepare-release.yml`

```yaml
name: Prepara rilascio
on:
  workflow_dispatch:
    inputs:
      version:
        description: 'Nuova versione (X.Y.Z)'
        required: true
        type: string
      tested_up_to:
        description: 'Tested up to (vuoto = invariato)'
        required: false
        type: string
        default: ''
      changelog:
        description: 'Voci del changelog, separate da ";"'
        required: true
        type: string
jobs:
  prepare:
    uses: nigrosimone/wp-plugin-ci/.github/workflows/plugin-prepare-release.yml@v1
    with:
      version: ${{ inputs.version }}
      tested_up_to: ${{ inputs.tested_up_to }}
      changelog: ${{ inputs.changelog }}
```

`.github/workflows/svn-drift.yml`

```yaml
name: Controllo divergenza SVN
on:
  schedule:
    - cron: '0 6 * * 1'
  workflow_dispatch:
jobs:
  drift:
    uses: nigrosimone/wp-plugin-ci/.github/workflows/plugin-svn-drift.yml@v1
```

Lo `slug` su wordpress.org viene dedotto dal nome del repository: va passato
solo se i due non coincidono.

## Cosa contiene

| Comando | Cosa fa |
|---|---|
| `wp-plugin-lint` | `php -l` su tutti i file che finiscono nel pacchetto |
| `wp-plugin-version` | Stampa la versione dall'header, e nient'altro |
| `wp-plugin-check-version` | Verifica che `Version:`, `Stable tag` e changelog coincidano |
| `wp-plugin-bump-version` | Prepara una nuova versione: readme, header, costante `VERSION` |
| `wp-plugin-install-hooks` | Installa l'hook pre-commit e configura `git blame` |
| `bin/build-dist.sh` | Ricostruisce il pacchetto pubblicato e fallisce se ci finiscono file di sviluppo |

Gli script deducono la radice del plugin dalla **directory di lavoro**, non
dalla propria posizione: girano identici da `vendor/bin` e da un checkout
diretto. `PLUGIN_ROOT` la forza, se serve.

## Perché l'hook è generato e non committato

`wp-plugin-install-hooks` scrive `.githooks/pre-commit` copiandolo dal template
di questo pacchetto, e imposta `core.hooksPath`. La cartella va in `.gitignore`:
è rigenerata a ogni `composer install`, così l'hook non può divergere fra gli
otto plugin.

Il template non punta direttamente dentro `vendor/` per due motivi: il bit di
esecuzione va impostato sulla copia, e `composer install --no-dev` farebbe
sparire il percorso lasciando git configurato su una cartella inesistente.

## Versionamento

I plugin puntano al tag mobile `v1`, spostato a ogni release compatibile. Una
modifica che rompe i chiamanti va su `v2`, così gli otto plugin si aggiornano
uno alla volta invece che tutti insieme.

## Licenza

GPL-2.0-or-later
