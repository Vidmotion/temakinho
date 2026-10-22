#!/usr/bin/env bash
# ==========================================================================
# Staging isolato di Temakinho (progetto Compose `tmkstg`) — sul server Dokploy.
#
#   stg.sh env-init                 genera /root/tmk-staging/env/.env.staging (segreti casuali)
#   stg.sh checkout <sha>           estrae il commit da /root/tmk-staging/repo.bundle in src/<sha>
#   stg.sh restore <sha> <backup>   crea i volumi tmkstg_* e vi ripristina il backup, con verifica
#   stg.sh restore-db <sha> <backup> solo il database (ricreato da zero), con verifica dei conteggi
#   stg.sh up <sha>                 build dell'immagine DAL COMMIT e avvio
#   stg.sh stop                     ferma i container (i volumi restano)
#   stg.sh reset                    elimina container e volumi tmkstg_* (solo staging)
#   stg.sh wp <args...>             WP-CLI nel container, come www-data
#   stg.sh sql                      client MySQL dello staging (stdin)
#
# Nessun comando qui tocca risorse del progetto di produzione `temakinho-agyavu`:
# ogni volume scritto deve chiamarsi tmkstg_* (controllo esplicito).
# ==========================================================================
set -euo pipefail
umask 077
BASE=/root/tmk-staging
P=tmkstg
ENVF=$BASE/env/.env.staging
OVR=$BASE/override.yml
WPCLI=/root/tmk-ops/wp-cli.phar

die(){ echo "ERRORE: $*" >&2; exit 1; }
src(){ local d="$BASE/src/$1"; [ -f "$d/docker-compose.yml" ] || die "checkout mancante: $1"; echo "$d"; }
dc(){ local d; d=$(src "$1"); shift; docker compose -p "$P" --env-file "$ENVF" -f "$d/docker-compose.yml" -f "$OVR" "$@"; }
guard_vol(){ case "$1" in tmkstg_*) ;; *) die "rifiuto di scrivere nel volume $1 (non e' di staging)";; esac; }
wpc(){ docker ps --format '{{.Names}}' | grep -E "^${P}-wordpress-1$" >/dev/null || die "staging non avviato"; echo "${P}-wordpress-1"; }

cmd=${1:-}; shift || true
case "$cmd" in
env-init)
  mkdir -p "$BASE/env"; [ -e "$ENVF" ] && die "$ENVF esiste gia'"
  r(){ openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c "${1:-40}"; }
  DBP=$(r 32)
  {
    echo "WORDPRESS_DB_HOST=db:3306"; echo "WORDPRESS_DB_NAME=temakinho"; echo "WORDPRESS_DB_USER=tmkstg"
    echo "WORDPRESS_DB_PASSWORD=$DBP"; echo "WORDPRESS_TABLE_PREFIX=wp_"
    echo "MYSQL_DATABASE=temakinho"; echo "MYSQL_USER=tmkstg"; echo "MYSQL_PASSWORD=$DBP"; echo "MYSQL_ROOT_PASSWORD=$(r 32)"
    echo "WORDPRESS_HOME=http://localhost:18080"; echo "WORDPRESS_SITEURL=http://localhost:18080"; echo "WORDPRESS_DEBUG="
    for k in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do echo "WORDPRESS_$k=$(r 64)"; done
  } > "$ENVF"; chmod 600 "$ENVF"; echo "creato $ENVF";;
checkout)
  sha=${1:?sha}; d="$BASE/src/$sha"; [ -d "$d" ] && { echo "gia' presente: $d"; exit 0; }
  git -c advice.detachedHead=false clone -q --no-checkout "$BASE/repo.bundle" "$d"
  git -C "$d" -c advice.detachedHead=false checkout -q "$sha"
  [ "$(git -C "$d" rev-parse HEAD)" = "$(git -C "$d" rev-parse "$sha^{commit}")" ] || die "checkout non coerente"
  echo "checkout $(git -C "$d" rev-parse HEAD) in $d";;
restore)
  sha=${1:?sha}; B=${2:?backup}; [ -f "$B/meta/SHA256SUMS.plain" ] || die "backup non valido: $B"
  docker ps -a --format '{{.Names}}' | grep -E "^${P}-" && die "esistono gia' container di staging: fermarli/rimuoverli prima"
  ( cd "$B/plain" && sha256sum -c --quiet ../meta/SHA256SUMS.plain ) || die "checksum del backup non validi"
  dc "$sha" create --no-build 2>/dev/null || dc "$sha" create
  IMG=$(docker image inspect temakinho-agyavu-wordpress --format '{{.Id}}')
  for pair in uploads:tmkstg_temakinho_uploads plugins:tmkstg_temakinho_plugins themes:tmkstg_temakinho_themes webroot:tmkstg_webroot; do
    short=${pair%%:*}; vol=${pair#*:}; guard_vol "$vol"
    n=$(docker run --rm --entrypoint sh -v "$vol":/v "$IMG" -c 'find /v -mindepth 1 | wc -l')
    [ "$n" -eq 0 ] || [ "$short" = webroot ] || die "volume $vol non vuoto ($n voci)"
    echo "ripristino $short -> $vol"
    docker run --rm --entrypoint sh -v "$vol":/v -v "$B/plain":/b:ro -v "$B/meta":/m:ro "$IMG" -c \
      "set -e; find /v -mindepth 1 -delete; tar --numeric-owner -xzpf /b/vol-$short.tar.gz -C /v; cd /v && sha256sum -c --quiet /m/files-$short.sha256 && echo '  file verificati:' \$(wc -l < /m/files-$short.sha256)"
  done
  "$0" restore-db "$sha" "$B";;
restore-db)
  sha=${1:?sha}; B=${2:?backup}
  dc "$sha" up -d db
  # mysqladmin ping risponde "vivo" anche durante l'inizializzazione e anche con password errata:
  # si aspetta una query vera con la password di root definitiva.
  ok=0; for i in $(seq 1 90); do
    if docker exec ${P}-db-1 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -N -e "select 1" "$MYSQL_DATABASE"' >/dev/null 2>&1; then ok=1; break; fi; sleep 2; done
  [ "$ok" = 1 ] || die "database di staging non pronto"
  echo "import database (ricreato da zero)"
  docker exec ${P}-db-1 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "DROP DATABASE IF EXISTS \`$MYSQL_DATABASE\`; CREATE DATABASE \`$MYSQL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON \`$MYSQL_DATABASE\`.* TO \"$MYSQL_USER\"@\"%\";"'
  gunzip -c "$B/plain/db.sql.gz" | docker exec -i ${P}-db-1 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot "$MYSQL_DATABASE"'
  docker exec ${P}-db-1 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -N "$MYSQL_DATABASE" -e "select \"posts\",count(*) from wp_posts union all select \"postmeta\",count(*) from wp_postmeta union all select \"options\",count(*) from wp_options union all select \"users\",count(*) from wp_users union all select \"job_application\",count(*) from wp_posts where post_type=\"job_application\" union all select \"e_submissions\",count(*) from wp_e_submissions"' > "$BASE/restore-counts.tsv"
  diff "$B/meta/db-counts.tsv" "$BASE/restore-counts.tsv" && echo "conteggi database identici al backup" || die "conteggi database diversi dal backup";;
up)
  sha=${1:?sha}; dc "$sha" up -d --build --remove-orphans; dc "$sha" ps;;
reset)
  # Elimina SOLO container e volumi del progetto di staging (nomi verificati uno per uno).
  for c in $(docker ps -a --format '{{.Names}}' | grep -E "^${P}-" || true); do docker rm -f "$c" >/dev/null; echo "rimosso container $c"; done
  for v in $(docker volume ls --format '{{.Name}}' | grep -E '^tmkstg_' || true); do guard_vol "$v"; docker volume rm "$v" >/dev/null; echo "rimosso volume $v"; done;;
stop)
  for c in $(docker ps --format '{{.Names}}' | grep -E "^${P}-" || true); do docker stop "$c" >/dev/null; echo "fermato $c"; done;;
wp)
  c=$(wpc); docker cp "$WPCLI" "$c":/tmp/wp-cli.phar >/dev/null; docker exec -i -u www-data "$c" php /tmp/wp-cli.phar --path=/var/www/html "$@";;
sql)
  docker exec -i ${P}-db-1 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot --default-character-set=utf8mb4 "$MYSQL_DATABASE" "$@"' sh "$@";;
*) sed -n '2,15p' "$0"; exit 1;;
esac
