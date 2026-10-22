#!/usr/bin/env bash
# Prove dell'account di consultazione in PRODUZIONE, via HTTPS pubblico, eseguite dal server — senza
# conoscere ne' cambiare la password WordPress dell'account (la imposta l'amministratore): la sessione
# di prova viene generata dal server per consultazione-cv e chiusa alla fine. Basic Auth dedicata
# letta da file riservato a root. I CV scaricati passano solo in una pipe verso sha256sum (mai salvati,
# mai mostrati): a schermo soltanto esiti e codici HTTP.
set -uo pipefail
umask 077
U=https://temakinho.com
S=/root/tmk-ops/secrets
C=temakinho-agyavu-wordpress-1
J=$S/jar.$$
P=0; F=0
BU=consultazione-cv
BP=$(cat "$S/basic-auth-consultazione-cv.pass")
ok(){ P=$((P+1)); echo "OK    | $*"; }
ko(){ F=$((F+1)); echo "FAIL  | $*"; }
check(){ if [ "$3" = "$2" ]; then ok "$1 → $3"; else ko "$1 → atteso «$2», ottenuto «$3»"; fi; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
sqlq(){ echo "$1" | /root/tmk-ops/sql.sh -N; }
wpq(){ /root/tmk-ops/wp.sh eval "$1" 2>/dev/null </dev/null; }

check "wp-login senza credenziali Basic Auth" 401 "$(code "$U/wp-login.php")"
check "wp-login con Basic Auth dedicata" 200 "$(code -u "$BU:$BP" "$U/wp-login.php")"
check "wp-login con password Basic Auth errata" 401 "$(code -u "$BU:sbagliata" "$U/wp-login.php")"
check "account: ruolo, email e capability invariati" "tmk_cv_viewer info@temakinho.com 1 0" "$(wpq '$u=get_user_by("login","consultazione-cv"); echo implode(",",$u->roles)," ",$u->user_email," ",(int)user_can($u,"tmk_view_applications")," ",(int)user_can($u,"edit_posts");')"

SESS=$(wpq '$u=get_user_by("login","consultazione-cv"); $e=time()+900; $t=WP_Session_Tokens::get_instance($u->ID)->create($e); echo $t,"\n",LOGGED_IN_COOKIE,"\t",wp_generate_auth_cookie($u->ID,$e,"logged_in",$t),"\n",SECURE_AUTH_COOKIE,"\t",wp_generate_auth_cookie($u->ID,$e,"secure_auth",$t),"\n";')
TOK=$(sed -n 1p <<<"$SESS")
cleanup(){ rm -f "$J" "$J.h"; wpq "WP_Session_Tokens::get_instance(get_user_by('login','consultazione-cv')->ID)->destroy('$TOK');" >/dev/null; }
trap cleanup EXIT
sed -n '2,3p' <<<"$SESS" | while IFS=$'\t' read -r n v; do printf 'temakinho.com\tFALSE\t/\tTRUE\t0\t%s\t%s\n' "$n" "$v"; done > "$J"
check "sessione di prova generata dal server" 2 "$(grep -c wordpress_ "$J")"

L=$(curl -s -b "$J" -w '\n%{http_code}' "$U/wp-admin/admin.php?page=tmk-candidature")
check "elenco candidature" 200 "$(tail -1 <<<"$L")"
NV=$(sqlq "select count(*) from wp_posts where post_type='job_application' and post_status in ('private','publish');")
check "righe dell'elenco = candidature consultabili" "$NV" "$(grep -c 'page=tmk-candidature&#038;application=' <<<"$L")"
if grep -q '[A-Za-z0-9]\{32\}\.\(pdf\|docx\|doc\|odt\)\|-pdf[-0-9x]*\.jpg\|tmk-private' <<<"$L"; then ko "elenco: compare un URL o nome di file CV"; else ok "elenco: nessun URL o nome di file CV"; fi
if grep -qi 'TEST Candidatura fittizia\|TEST TECNICO' <<<"$L"; then ko "elenco: compaiono ancora candidature di prova"; else ok "elenco: nessuna candidatura di prova"; fi

bad=0; n=0; ONE=; ONELINK=
for app in $(sqlq "select ID from wp_posts where post_type='job_application' and post_status in ('private','publish') order by ID;"); do
	n=$((n+1))
	att=$(sqlq "select meta_value from wp_postmeta where meta_key='tmk_application_cv_id' and post_id=$app;")
	f=$(sqlq "select meta_value from wp_postmeta where meta_key='_wp_attached_file' and post_id=$att order by meta_id desc limit 1;")
	exp=$(docker exec "$C" sha256sum "$f" 2>/dev/null | cut -d' ' -f1)
	link=$(sed 's/&#038;/\&/g' <<<"$L" | grep -o "admin-post.php?action=tmk_cv_download&application=$app&_wpnonce=[a-f0-9]*" | head -1)
	got=$(curl -s -b "$J" "$U/wp-admin/$link" | sha256sum | cut -d' ' -f1)
	if [ -z "$exp" ] || [ "$got" != "$exp" ]; then bad=$((bad+1)); echo "   candidatura $app: download non integro"; fi
	ONE=$app; ONELINK=$link
done
check "download di ogni CV (SHA-256 confrontato sul server)" "$n su $n" "$((n-bad)) su $n"
H=$(curl -s -b "$J" -D - -o /dev/null "$U/wp-admin/$ONELINK")
if grep -qi 'content-disposition: attachment' <<<"$H"; then ok "download: Content-Disposition attachment"; else ko "download: manca Content-Disposition"; fi
if grep -qi 'x-content-type-options: nosniff' <<<"$H"; then ok "download: nosniff"; else ko "download: manca nosniff"; fi
if grep -qi 'cache-control:.*no-store' <<<"$H"; then ok "download: no-store"; else ko "download: manca no-store"; fi
check "download con nonce errato" 403 "$(code -b "$J" "$U/wp-admin/admin-post.php?action=tmk_cv_download&application=$ONE&_wpnonce=0123456789")"
A=$(curl -s -w '\n%{http_code}' "$U/wp-admin/$ONELINK")
check "download senza login: solo reindirizzamento, nessun byte del file" "302 0" "$(tail -1 <<<"$A") $(grep -c '%PDF' <<<"$A")"
check "dettaglio di una candidatura" 200 "$(code -b "$J" "$U/wp-admin/admin.php?page=tmk-candidature&application=$ONE")"
check "dettaglio di una posizione (non candidatura)" 404 "$(code -b "$J" "$U/wp-admin/admin.php?page=tmk-candidature&application=245")"
check "bacheca reindirizzata all'elenco" 302 "$(code -b "$J" "$U/wp-admin/")"
for p in edit.php 'edit.php?post_type=job_application' 'edit.php?post_type=contact_message' 'edit.php?post_type=newsletter_sub' 'edit.php?post_type=page' post-new.php "post.php?post=$ONE&action=edit" upload.php users.php user-new.php plugins.php themes.php options-general.php tools.php export.php 'tools.php?page=tmk-cv-migrazione' 'admin.php?page=elementor' 'admin.php?page=e-form-submissions' 'admin.php?page=wp-mail-smtp' 'admin.php?page=sendinblue' customize.php site-health.php 'admin-post.php?action=altro'; do
	check "negato: $p" 403 "$(code -b "$J" "$U/wp-admin/$p")"
done
check "profilo personale" 200 "$(code -b "$J" "$U/wp-admin/profile.php")"
for a in query-attachments inline-save elementor_ajax save-attachment; do
	check "AJAX negata: $a" 403 "$(curl -s -b "$J" -o /dev/null -w '%{http_code}' -d "action=$a" "$U/wp-admin/admin-ajax.php")"
done
check "sito pubblico per l'account collegato" 200 "$(code -b "$J" "$U/")"
cp "$J" "$J.old"
cleanup; trap 'rm -f "$J.old"' EXIT
check "sessione di prova chiusa: lo stesso cookie non apre più l'elenco" 302 "$(code -b "$J.old" "$U/wp-admin/admin.php?page=tmk-candidature")"
echo "== RISULTATO: $P superati, $F falliti"
