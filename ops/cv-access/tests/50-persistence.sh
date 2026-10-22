#!/usr/bin/env bash
# Fase 5 — persistenza dopo la ricreazione del container (come a ogni deploy) ed errori PHP.
. "$(dirname "$0")/lib.sh"
load_counters
NEW=${NEW_SHA:?}; VJ=$STATE/jar-viewer
section "21. Persistenza dopo la ricreazione del container"
NF=$(cx 'find /var/www/tmk-private -type f | wc -l'); NAPP=$(echo "select count(*) from wp_posts where post_type='job_application';" | sql)
SHA_BEFORE=$(cx 'cd /var/www/tmk-private && find . -type f -print0 | sort -z | xargs -0 sha256sum | sha256sum | cut -d" " -f1')
OLDID=$(docker inspect "$WPC" --format '{{.Id}}')
docker compose -p tmkstg --env-file /root/tmk-staging/env/.env.staging -f "/root/tmk-staging/src/$NEW/docker-compose.yml" -f /root/tmk-staging/override.yml up -d --force-recreate wordpress >/dev/null 2>&1
for i in $(seq 1 60); do [ "$(code "$URL/wp-includes/js/wp-emoji-loader.min.js")" = 200 ] && break; sleep 3; done
check_ne "container ricreato" "$OLDID" "$(docker inspect "$WPC" --format '{{.Id}}')"
check "file dell'archivio identici dopo la ricreazione" "$NF $SHA_BEFORE" "$(cx 'find /var/www/tmk-private -type f | wc -l') $(cx 'cd /var/www/tmk-private && find . -type f -print0 | sort -z | xargs -0 sha256sum | sha256sum | cut -d" " -f1')"
check "candidature invariate" "$NAPP" "$(echo "select count(*) from wp_posts where post_type='job_application';" | sql)"
check "archivio pronto dopo il riavvio" si "$(cvcli status | awk 'NR==2{print $1}')"
check "Basic Auth dedicata ancora attiva (webroot persistente)" "401 200" "$(code "$URL/wp-login.php") $(code -u "$BASIC_USER:$BASIC_PASS" "$URL/wp-login.php")"
check "login consultazione dopo il riavvio" 302 "$(login consultazione-cv "$VIEWER_PASS" "$VJ")"
get "$VJ" '/wp-admin/admin.php?page=tmk-candidature' "$STATE/p.html" >/dev/null
A=$(app_by_last Nuovo1); L=$(sed 's/&#038;/\&/g' "$STATE/p.html" | grep -o "admin-post.php?action=tmk_cv_download&application=$A&_wpnonce=[a-f0-9]*" | head -1)
check "download dopo il riavvio" "200 $(sha256sum "$T/files/new1.pdf" | cut -d' ' -f1)" "$(curl -s -b "$VJ" -o "$STATE/p.bin" -w '%{http_code}' "$URL/wp-admin/$L") $(sha256sum "$STATE/p.bin" | cut -d' ' -f1)"
check "plugin nel container identico al commit $NEW" identico "$(docker cp "$WPC:/var/www/html/wp-content/plugins/tmk-design-system" /tmp/p-chk >/dev/null 2>&1 && diff -r "/root/tmk-staging/src/$NEW/wp-content/plugins/tmk-design-system" /tmp/p-chk >/dev/null && echo identico; rm -rf /tmp/p-chk)"
section "22. Errori PHP durante le prove"
E=$(docker logs --since "${SINCE:-3h}" "$WPC" 2>&1 | grep -E 'PHP (Fatal|Parse|Warning|Notice|Deprecated)' | grep -v 'TEST: interruzione' || true)
check "nessun errore o avviso PHP nel log del container" 0 "$(printf '%s' "$E" | grep -c .)"
[ -n "$E" ] && note "$(head -5 <<<"$E")"
summary
