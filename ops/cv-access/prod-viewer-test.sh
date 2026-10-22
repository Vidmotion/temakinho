#!/usr/bin/env bash
# Prove dell'account di consultazione in PRODUZIONE, via HTTPS pubblico, eseguite dal server.
# I CV scaricati passano solo in una pipe verso sha256sum (mai salvati, mai mostrati): a schermo
# soltanto esiti e codici HTTP. Credenziali lette da file riservati a root, mai stampate.
set -uo pipefail
umask 077
U=https://temakinho.com
S=/root/tmk-ops/secrets
C=temakinho-agyavu-wordpress-1
J=$S/jar.$$
FAKE=${FAKE_APP:-21238}
P=0; F=0
BU=consultazione-cv
BP=$(cat "$S/basic-auth-consultazione-cv.pass")
WPP=$(cat "$S/viewer-wp.tmp")
ok(){ P=$((P+1)); echo "OK    | $*"; }
ko(){ F=$((F+1)); echo "FAIL  | $*"; }
check(){ if [ "$3" = "$2" ]; then ok "$1 → $3"; else ko "$1 → atteso «$2», ottenuto «$3»"; fi; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
sqlq(){ echo "$1" | /root/tmk-ops/sql.sh -N; }
trap 'rm -f "$J" "$J.h"' EXIT

check "wp-login senza credenziali Basic Auth" 401 "$(code "$U/wp-login.php")"
check "wp-login con Basic Auth dedicata" 200 "$(code -u "$BU:$BP" "$U/wp-login.php")"
check "wp-login con password Basic Auth errata" 401 "$(code -u "$BU:sbagliata" "$U/wp-login.php")"
curl -s -c "$J" -b "$J" -u "$BU:$BP" -o /dev/null "$U/wp-login.php"
c=$(curl -s -c "$J" -b "$J" -u "$BU:$BP" -D "$J.h" -o /dev/null -w '%{http_code}' --data-urlencode "log=consultazione-cv" --data-urlencode "pwd=$WPP" -d "wp-submit=Accedi&testcookie=1" "$U/wp-login.php")
check "login dell'account di consultazione" 302 "$c"
if grep -qi '^location:.*page=tmk-candidature' "$J.h"; then ok "dopo il login: elenco candidature"; else ko "dopo il login: destinazione inattesa"; fi

L=$(curl -s -b "$J" -w '\n%{http_code}' "$U/wp-admin/admin.php?page=tmk-candidature")
check "elenco candidature" 200 "$(tail -1 <<<"$L")"
NV=$(sqlq "select count(*) from wp_posts where post_type='job_application' and post_status in ('private','publish');")
check "righe dell'elenco = candidature consultabili" "$NV" "$(grep -c 'page=tmk-candidature&#038;application=' <<<"$L")"
if grep -q '[A-Za-z0-9]\{32\}\.\(pdf\|docx\|doc\|odt\)\|-pdf[-0-9x]*\.jpg\|tmk-private' <<<"$L"; then ko "elenco: compare un URL o nome di file CV"; else ok "elenco: nessun URL o nome di file CV"; fi

bad=0; n=0; FAKELINK=
for app in $(sqlq "select ID from wp_posts where post_type='job_application' and post_status in ('private','publish') order by ID;"); do
	n=$((n+1))
	att=$(sqlq "select meta_value from wp_postmeta where meta_key='tmk_application_cv_id' and post_id=$app;")
	f=$(sqlq "select meta_value from wp_postmeta where meta_key='_wp_attached_file' and post_id=$att order by meta_id desc limit 1;")
	exp=$(docker exec "$C" sha256sum "$f" 2>/dev/null | cut -d' ' -f1)
	link=$(sed 's/&#038;/\&/g' <<<"$L" | grep -o "admin-post.php?action=tmk_cv_download&application=$app&_wpnonce=[a-f0-9]*" | head -1)
	got=$(curl -s -b "$J" "$U/wp-admin/$link" | sha256sum | cut -d' ' -f1)
	if [ -z "$exp" ] || [ "$got" != "$exp" ]; then bad=$((bad+1)); echo "   candidatura $app: download non integro"; fi
	[ "$app" = "$FAKE" ] && FAKELINK=$link
done
check "download di ogni CV (SHA-256 confrontato sul server)" "$n su $n" "$((n-bad)) su $n"
H=$(curl -s -b "$J" -D - -o /dev/null "$U/wp-admin/$FAKELINK")
if grep -qi 'content-disposition: attachment' <<<"$H"; then ok "CV fittizio: Content-Disposition attachment"; else ko "CV fittizio: manca Content-Disposition"; fi
if grep -qi 'x-content-type-options: nosniff' <<<"$H"; then ok "CV fittizio: nosniff"; else ko "CV fittizio: manca nosniff"; fi
if grep -qi 'cache-control:.*no-store' <<<"$H"; then ok "CV fittizio: no-store"; else ko "CV fittizio: manca no-store"; fi
check "download con nonce errato" 403 "$(code -b "$J" "$U/wp-admin/admin-post.php?action=tmk_cv_download&application=$FAKE&_wpnonce=0123456789")"
A=$(curl -s -w '\n%{http_code}' "$U/wp-admin/$FAKELINK")
check "download senza login: solo reindirizzamento, nessun byte del file" "302 0" "$(tail -1 <<<"$A") $(grep -c '%PDF' <<<"$A")"
check "dettaglio della candidatura fittizia" 200 "$(code -b "$J" "$U/wp-admin/admin.php?page=tmk-candidature&application=$FAKE")"
check "dettaglio di una posizione (non candidatura)" 404 "$(code -b "$J" "$U/wp-admin/admin.php?page=tmk-candidature&application=245")"
check "bacheca reindirizzata all'elenco" 302 "$(code -b "$J" "$U/wp-admin/")"
for p in edit.php 'edit.php?post_type=job_application' 'edit.php?post_type=contact_message' 'edit.php?post_type=newsletter_sub' 'edit.php?post_type=page' post-new.php "post.php?post=$FAKE&action=edit" upload.php users.php user-new.php plugins.php themes.php options-general.php tools.php export.php 'tools.php?page=tmk-cv-migrazione' 'admin.php?page=elementor' 'admin.php?page=e-form-submissions' 'admin.php?page=wp-mail-smtp' 'admin.php?page=sendinblue' customize.php site-health.php 'admin-post.php?action=altro'; do
	check "negato: $p" 403 "$(code -b "$J" "$U/wp-admin/$p")"
done
check "profilo personale" 200 "$(code -b "$J" "$U/wp-admin/profile.php")"
for a in query-attachments inline-save elementor_ajax save-attachment; do
	check "AJAX negata: $a" 403 "$(curl -s -b "$J" -o /dev/null -w '%{http_code}' -d "action=$a" "$U/wp-admin/admin-ajax.php")"
done
CK=$(awk '$6 ~ /^wordpress_logged_in_/ {print $6"="$7}' "$J" | tail -1)
RN=$(/root/tmk-ops/wp.sh eval "\$kv = explode( '=', '$CK', 2 ); \$_COOKIE[ \$kv[0] ] = urldecode( \$kv[1] ); wp_set_current_user( get_user_by( 'login', 'consultazione-cv' )->ID ); echo wp_create_nonce( 'wp_rest' );" 2>/dev/null </dev/null)
check "REST autenticata negata" 403 "$(code -b "$J" -H "X-WP-Nonce: $RN" "$U/wp-json/wp/v2/users/me")"
check "sito pubblico per l'account collegato" 200 "$(code -b "$J" "$U/")"
echo "== RISULTATO: $P superati, $F falliti"
