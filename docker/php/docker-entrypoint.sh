#!/bin/sh
#
# Rend inscriptibles, au démarrage du conteneur, les répertoires dans lesquels
# l'application écrit (logs Monolog, uploads, cache, sessions).
#
# Le code source est monté depuis l'hôte (bind mount), ce qui écrase l'ownership
# défini au build : les fichiers portent l'uid/gid de l'hôte alors qu'Apache écrit
# en www-data. Selon l'hôte le comportement diffère :
#   - Linux / WSL2 natif : les fichiers appartiennent à l'utilisateur hôte
#     (uid 1000 typiquement) et www-data ne peut pas écrire -> il faut réparer ;
#   - Docker Desktop (Windows/macOS) : le montage est déjà permissif et chown est
#     un no-op silencieux -> il ne faut surtout pas payer un chown -R inutile.
#
# On teste donc l'accès en écriture avant d'agir : web/uploads contient plusieurs
# milliers de fichiers, un chown -R inconditionnel à chaque démarrage serait lent.

set -e

APP_ROOT="${APP_ROOT:-/var/www/html}"

# Répertoires devant être inscriptibles par Apache. web/uploads/evenements reçoit
# des sous-dossiers créés à la volée (un par année), d'où la nécessité d'avoir le
# droit d'écriture sur le répertoire parent et pas seulement sur les fichiers.
WRITABLE_DIRS="
var/logs
var/cache
var/sessions
web/uploads
web/uploads/evenements
web/uploads/lieux
web/uploads/lieux/galeries
web/uploads/organisateurs
web/uploads/fichiers/lieux
"

# Vrai si www-data peut créer un fichier dans le répertoire donné.
is_writable_by_www_data() {
    probe="$1/.docker-write-probe.$$"
    if su -s /bin/sh -c "touch '$probe'" www-data 2>/dev/null; then
        rm -f "$probe"
        return 0
    fi
    return 1
}

ensure_writable() {
    dir="$1"

    [ -d "$dir" ] || mkdir -p "$dir"

    if is_writable_by_www_data "$dir"; then
        return 0
    fi

    # Sur un bind mount non modifiable ces commandes échouent sans conséquence :
    # on ne veut pas empêcher le conteneur de démarrer pour autant.
    chown -R www-data:www-data "$dir" 2>/dev/null || true
    chmod -R u+rwX,g+rwX "$dir" 2>/dev/null || true

    if ! is_writable_by_www_data "$dir"; then
        echo "[entrypoint] AVERTISSEMENT : $dir n'est pas inscriptible par www-data." >&2
    fi
}

# Uniquement pertinent tant que l'on tourne en root (cas nominal : Apache démarre
# en root puis déprivilégie ses workers vers www-data).
if [ "$(id -u)" = "0" ]; then
    for dir in $WRITABLE_DIRS; do
        ensure_writable "$APP_ROOT/$dir"
    done
fi

# Rend la main à l'entrypoint de l'image officielle php:apache.
exec docker-php-entrypoint "$@"
