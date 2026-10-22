#!/usr/bin/env bash
# Backup coerente di Temakinho production. Si ferma al primo errore.
# Uso: backup.sh <etichetta>   -> /root/tmk-backups/<ts>-<etichetta>/{plain,enc,meta}
set -euo pipefail
umask 077
LABEL=${1:-manual}
TS=$(date -u +%Y%m%dT%H%M%SZ)
OUT=/root/tmk-backups/$TS-$LABEL
WP=temakinho-agyavu-wordpress-1
DB=temakinho-agyavu-db-1
REC=/root/tmk-ops/age-recipient.txt
mkdir -p "$OUT"/{plain,enc,meta,secret}
log(){ echo "[$(date -u +%H:%M:%S)] $*" | tee -a "$OUT/meta/backup.log"; }
die(){ log "ERRORE: $*"; exit 1; }

# 1. identita dei volumi realmente montati (nessun segreto)
for c in $WP $DB; do
  docker inspect "$c" --format "{{.Name}} image={{.Image}} created={{.Created}}{{range .Mounts}}{{println}}  {{.Type}} name={{.Name}} src={{.Source}} dst={{.Destination}} rw={{.RW}}{{end}}" >> "$OUT/meta/mounts.txt"
  docker inspect "$c" > "$OUT/secret/inspect-$c.json"
done
mapfile -t VOLS < <(docker inspect $WP --format "{{range .Mounts}}{{.Name}}|{{.Source}}|{{.Destination}}{{println}}{{end}}" | grep -v "^$")
[ "${#VOLS[@]}" -ge 4 ] || die "attesi almeno 4 mount sul container wordpress, trovati ${#VOLS[@]}"
PG=$(docker ps -q -f name=dokploy-postgres)
docker exec "$PG" psql -U dokploy -d dokploy -At -c "select row_to_json(c) from compose c where \"appName\"='temakinho-agyavu'" > "$OUT/secret/dokploy-compose.json"
[ -s "$OUT/secret/dokploy-compose.json" ] || die "configurazione Dokploy vuota"
log "mount verificati: ${#VOLS[@]} (+ db)"

# 2. database (prima dei file: ogni riga nel dump ha il suo file gia scritto)
log "dump database"
docker exec "$DB" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -N -e "select table_name, table_rows from information_schema.tables where table_schema=\"$MYSQL_DATABASE\" order by 1"' > "$OUT/meta/db-tables-estimate.tsv"
docker exec "$DB" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --quick --routines --triggers --events --hex-blob --no-tablespaces --default-character-set=utf8mb4 "$MYSQL_DATABASE"' 2> "$OUT/meta/dump.err" | pigz -6 > "$OUT/plain/db.sql.gz"
[ ! -s "$OUT/meta/dump.err" ] || die "mysqldump ha scritto su stderr: $(head -c 300 "$OUT/meta/dump.err")"
pigz -t "$OUT/plain/db.sql.gz" || die "gzip del dump non valido"
pigz -dc "$OUT/plain/db.sql.gz" | tail -n 1 | grep -q "^-- Dump completed" || die "dump troncato (manca il trailer)"
NT_DUMP=$(pigz -dc "$OUT/plain/db.sql.gz" | grep -c "^CREATE TABLE ")
NT_DB=$(wc -l < "$OUT/meta/db-tables-estimate.tsv")
[ "$NT_DUMP" -eq "$NT_DB" ] || die "tabelle nel dump $NT_DUMP != tabelle nel DB $NT_DB"
# conteggi esatti delle tabelle principali, per la prova di ripristino
docker exec "$DB" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -N "$MYSQL_DATABASE" -e "select \"posts\",count(*) from wp_posts union all select \"postmeta\",count(*) from wp_postmeta union all select \"options\",count(*) from wp_options union all select \"users\",count(*) from wp_users union all select \"job_application\",count(*) from wp_posts where post_type=\"job_application\" union all select \"e_submissions\",count(*) from wp_e_submissions"' > "$OUT/meta/db-counts.tsv"
log "dump ok: $NT_DUMP tabelle, $(stat -c %s "$OUT/plain/db.sql.gz") byte"

# 3. volumi file (uploads, plugins, themes, webroot anonimo)
for v in "${VOLS[@]}"; do
  IFS="|" read -r name src dst <<< "$v"
  short=$(echo "$dst" | sed "s#/var/www/html/wp-content/##; s#^/var/www/html\$#webroot#; s#^/var/www/tmk-private\$#private#")
  case "$short" in uploads|plugins|themes|webroot|private) ;; *) die "mount non previsto: $dst";; esac
  [ -d "$src" ] || die "sorgente mancante $src"
  log "archivio $short ($name)"
  ( cd "$src" && find . -type f -print0 | sort -z | xargs -0 sha256sum ) > "$OUT/meta/files-$short.sha256"
  tar --numeric-owner -C "$src" -cpf - . 2> "$OUT/meta/tar-$short.err" | pigz -6 > "$OUT/plain/vol-$short.tar.gz"
  [ ! -s "$OUT/meta/tar-$short.err" ] || die "tar $short: $(head -c 300 "$OUT/meta/tar-$short.err")"
  pigz -t "$OUT/plain/vol-$short.tar.gz" || die "gzip $short non valido"
  NF_TAR=$(tar -tzf "$OUT/plain/vol-$short.tar.gz" | grep -vc "/$" || true)
  NF_SRC=$(wc -l < "$OUT/meta/files-$short.sha256")
  NL_SRC=$(cd "$src" && find . ! -type d | wc -l)
  [ "$NF_TAR" -eq "$NL_SRC" ] || die "$short: voci file nel tar $NF_TAR != sorgente $NL_SRC"
  echo "$short|$name|$src|$dst|files=$NF_SRC" >> "$OUT/meta/volumes.txt"
done
tar -C "$OUT" -cf "$OUT/plain/secret.tar" secret
# 4. checksum, cifratura per il destinatario age (chiave privata solo sul Mac di Carlo)
( cd "$OUT/plain" && sha256sum * > ../meta/SHA256SUMS.plain )
for f in "$OUT"/plain/*; do age -R "$REC" -o "$OUT/enc/$(basename "$f").age" "$f"; done
( cd "$OUT/enc" && sha256sum * > ../meta/SHA256SUMS.enc )
rm -rf "$OUT/secret"
log "COMPLETATO $OUT"
echo "$OUT"
