#!/usr/bin/env bash
# Fase 1 — codice nuovo (1.22.0) sullo stato legacy creato dalla fase 0.
# Ruolo, nuovi caricamenti, accesso di consultazione, schermate negate, amministratore,
# migrazione con interruzioni / riferimento fallito / rimozione bloccata / nuove candidature
# in corso, rimozione delle copie pubbliche e verifica HTTP. Solo dati fittizi.
. "$(dirname "$0")/lib.sh"
load_counters
ADMJ=$STATE/jar-admin; VJ=$STATE/jar-viewer; SJ=$STATE/jar-sub
# esc_url() codifica & come &#038;
dl_link(){ sed 's/&#038;/\&/g; s/&amp;/\&/g' "$1" | grep -o "admin-post.php?action=tmk_cv_download&application=$2&_wpnonce=[a-f0-9]*" | head -1; }

section "1. Stack e build"
check "WordPress" 7.1.3 "$(wp core version)"
check "PHP 8.2" 8.2 "$(cx 'php -r "echo PHP_MAJOR_VERSION.\".\".PHP_MINOR_VERSION;"')"
check "MySQL 8.4" 8.4 "$(echo 'select substring_index(version(),".",2);' | sql)"
check "plugin attivo 1.22.0" "1.22.0" "$(wp plugin get tmk-design-system --field=version)"
check "tema attivo" hello-elementor "$(wp theme list --status=active --field=name)"
check "Elementor Pro attivo" active "$(wp plugin get elementor-pro --field=status)"
check "volume privato montato in /var/www/tmk-private" si "$(cx 'grep -qs " /var/www/tmk-private " /proc/self/mountinfo && echo si || echo no')"
check "archivio pronto" si "$(cvcli status | awk 'NR==2{print $1}')"
check "archivio fuori dalla webroot" no "$(cx 'case $(realpath /var/www/tmk-private) in /var/www/html*) echo si;; *) echo no;; esac')"

section "2. Ruolo Consultazione CV"
check "capability del ruolo" "read,tmk_view_applications" "$(wpe 'echo implode(",", array_keys(array_filter(get_role("tmk_cv_viewer")->capabilities)));')"
check "nome del ruolo" "Consultazione CV" "$(wpe 'echo wp_roles()->roles["tmk_cv_viewer"]["name"];')"
check "amministratore ha tmk_view_applications" 1 "$(wpe 'echo (int) get_role("administrator")->has_cap("tmk_view_applications");')"
wpe 'echo wp_json_encode( array_map( function ( $r ) { ksort( $r["capabilities"] ); return $r["capabilities"]; }, wp_roles()->roles ) );' > "$STATE/roles-after.json"
check "altri ruoli invariati (a parte la capability dell'admin)" "nessuna differenza" "$(hphp -r '$a=json_decode($argv[1],true); $b=json_decode($argv[2],true); unset($b["tmk_cv_viewer"]); unset($b["administrator"]["tmk_view_applications"]); echo $a===$b ? "nessuna differenza" : "DIVERSI";' "$(cat "$STATE/roles-before.json")" "$(cat "$STATE/roles-after.json")")"
wpe 'get_role("tmk_cv_viewer")->add_cap("edit_posts"); delete_option("tmk_cv_roles_version");' >/dev/null
curl -s -o /dev/null "$URL/"
check "capability aggiunta a mano tolta al riallineamento" "read,tmk_view_applications" "$(wpe 'echo implode(",", array_keys(array_filter(get_role("tmk_cv_viewer")->capabilities)));')"
check "ruolo non duplicato" 1 "$(wpe 'echo count(array_filter(array_keys(wp_roles()->roles), function($r){return $r==="tmk_cv_viewer";}));')"
# Account come in produzione: nessuna email inviata (WP-CLI non la manda senza --send-email).
M0=$(mailpit_count)
wp user create consultazione-cv consultazione-cv@staging.invalid --role=tmk_cv_viewer --display_name="Consultazione CV Temakinho" --user_pass="$VIEWER_PASS" --porcelain > "$STATE/viewer-id"
check "account di consultazione creato con il ruolo" tmk_cv_viewer "$(wp user get consultazione-cv --field=roles)"
check "nessuna email inviata alla creazione dell'account" "$M0" "$(mailpit_count)"

section "3. Nuove candidature (codice nuovo)"
mkpdf new1.pdf "CV FITTIZIO NUOVO 1"
c=$(apply "$T/files/new1.pdf" Prova Nuovo1 nuovo1@staging.invalid curriculum-nuovo.pdf)
check "candidatura con CV PDF accettata" 200 "$c"
N1=$(app_by_last Nuovo1); A1=$(cv_of "$N1"); F1=$(raw_ref "$A1")
contains "riferimento nell'archivio riservato" "^/var/www/tmk-private/cv/20[0-9][0-9]/[0-9][0-9]/[A-Za-z0-9]\{32\}\.pdf$" "$F1"
check "file privato identico all'originale (SHA-256)" "$(sha256sum "$T/files/new1.pdf" | cut -d' ' -f1)" "$(csha "$F1")"
B1=$(basename "$F1" .pdf)
check "nessuna anteprima JPG generata, in nessuna cartella" 0 "$(cx "find /var/www/html/wp-content/uploads /var/www/tmk-private -name '$B1*' ! -name '$B1.pdf' | wc -l")"
check "nessuna copia negli upload pubblici" 0 "$(cx "find /var/www/html/wp-content/uploads -name '$B1*' | wc -l")"
check "metadati senza anteprime" 0 "$(wpe "\$m = wp_get_attachment_metadata( $A1 ); echo isset( \$m['sizes'] ) ? count( \$m['sizes'] ) : 0;")"
check "permessi del file 640" 640 "$(cx "stat -c %a '$F1'")"
check "URL indovinato negli upload → 404" 404 "$(code "$URL/wp-content/uploads/${F1#/var/www/tmk-private/cv/}")"
check "URL indovinato /tmk-private → 404" 404 "$(code "$URL/tmk-private/cv/${F1#/var/www/tmk-private/cv/}")"
check_ne "URL relativo con .. → non servito (Apache 400)" 200 "$(code --path-as-is "$URL/../tmk-private/cv/${F1#/var/www/tmk-private/cv/}")"
check "wp_get_attachment_url vuoto per il CV privato" "" "$(wpe "echo wp_get_attachment_url( $A1 );")"
MAIL=$(mailpit_last)
contains "notifica inviata al destinatario delle candidature" "recruiting@temakinho.com" "$MAIL"
contains "notifica con link all'area riservata" "admin.php?page=tmk-candidature&application=$N1" "$MAIL"
lacks "notifica senza URL degli upload" "/wp-content/uploads/" "$MAIL"
lacks "notifica senza il nome del file" "$B1" "$MAIL"
lacks "notifica senza percorso dell'archivio" "tmk-private" "$MAIL"
# .exe rinominato in .pdf
printf 'MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff\x00\x00finto eseguibile' > "$T/files/fake.pdf"
NB=$(cx 'find /var/www/tmk-private/cv -type f | wc -l'); NA=$(echo "select count(*) from wp_posts where post_type='attachment';" | sql)
c=$(apply "$T/files/fake.pdf" Prova Exe exe@staging.invalid cv.pdf)
check "file non PDF con estensione .pdf rifiutato" 400 "$c"
contains "messaggio di formato non accettato" "Formato non accettato" "$(cat "$STATE/apply.json")"
check "nessun file salvato per il rifiuto" "$NB" "$(cx 'find /var/www/tmk-private/cv -type f | wc -l')"
check "nessun allegato creato per il rifiuto" "$NA" "$(echo "select count(*) from wp_posts where post_type='attachment';" | sql)"
c=$(apply "$T/files/new1.pdf" Prova Mismatch mm@staging.invalid cv.docx)
check "PDF con estensione .docx rifiutato" 400 "$c"
# DOCX vero (zip con struttura Word minima)
docker exec "$WPC" php -r '$z=new ZipArchive; $z->open("/tmp/cv.docx", ZipArchive::CREATE|ZipArchive::OVERWRITE); $z->addFromString("[Content_Types].xml","<?xml version=\"1.0\"?><Types xmlns=\"http://schemas.openxmlformats.org/package/2006/content-types\"><Default Extension=\"xml\" ContentType=\"application/xml\"/><Override PartName=\"/word/document.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml\"/></Types>"); $z->addFromString("_rels/.rels","<?xml version=\"1.0\"?><Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\"><Relationship Id=\"r1\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument\" Target=\"word/document.xml\"/></Relationships>"); $z->addFromString("word/document.xml","<?xml version=\"1.0\"?><w:document xmlns:w=\"http://schemas.openxmlformats.org/wordprocessingml/2006/main\"><w:body><w:p><w:r><w:t>CV fittizio</w:t></w:r></w:p></w:body></w:document>"); $z->close();'
docker cp "$WPC:/tmp/cv.docx" "$T/files/cv.docx" >/dev/null
c=$(apply "$T/files/cv.docx" Prova Docx docx@staging.invalid curriculum.docx)
check "CV DOCX accettato" 200 "$c"
contains "DOCX nell'archivio riservato" "^/var/www/tmk-private/cv/.*\.docx$" "$(raw_ref "$(cv_of "$(app_by_last Docx)")")"
c=$(NONCE_OVERRIDE=deadbeef00 apply "$T/files/new1.pdf" Prova Nonce n@staging.invalid cv.pdf)
check "nonce del form errato → 403" 403 "$c"
c=$(apply "$T/files/new1.pdf" Prova Honey h@staging.invalid cv.pdf "website=http://spam.example")
check "honeypot: risposta con forma di successo" 200 "$c"
check "honeypot: nessuna candidatura salvata" "" "$(app_by_last Honey)"
c=$(curl -s -o "$STATE/apply.json" -w '%{http_code}' -F action=tmk_job_apply -F "tmk_apply_nonce=$(curl -s "$URL/lavora-con-noi/$JOB_SLUG/" | grep -o 'name="tmk_apply_nonce" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')" -F job_id=245 -F first_name=Prova -F last_name=SenzaCV -F email=s@staging.invalid -F "cover_letter=Lettera di presentazione fittizia per le prove." -F privacy=1 "$URL/wp-admin/admin-ajax.php")
check "candidatura senza CV rifiutata" 400 "$c"
# archivio non disponibile: nessun ripiego pubblico
cx 'mv /var/www/tmk-private/.tmk-persistent /var/www/tmk-private/.tmk-persistent.off'
NU=$(cx 'find /var/www/html/wp-content/uploads -type f | wc -l'); NP=$(cx 'find /var/www/tmk-private/cv -type f | wc -l')
c=$(apply "$T/files/new1.pdf" Prova SenzaArchivio sa@staging.invalid cv.pdf)
check "archivio non confermato → candidatura rifiutata (500)" 500 "$c"
check "archivio non confermato → nessun file negli upload pubblici" "$NU" "$(cx 'find /var/www/html/wp-content/uploads -type f | wc -l')"
check "archivio non confermato → nessun file nell'archivio" "$NP" "$(cx 'find /var/www/tmk-private/cv -type f | wc -l')"
check "archivio non confermato → nessuna candidatura salvata" "" "$(app_by_last SenzaArchivio)"
cx 'mv /var/www/tmk-private/.tmk-persistent.off /var/www/tmk-private/.tmk-persistent'
check "archivio di nuovo disponibile" si "$(cvcli status | awk 'NR==2{print $1}')"

section "4. Accesso di consultazione"
check "wp-login senza Basic Auth → 401" 401 "$(code "$URL/wp-login.php")"
check "login dell'account di consultazione → 302" 302 "$(login consultazione-cv "$VIEWER_PASS" "$VJ")"
contains "dopo il login arriva all'elenco candidature" "page=tmk-candidature" "$(login_redirect "$VJ")"
check "elenco candidature → 200" 200 "$(get "$VJ" '/wp-admin/admin.php?page=tmk-candidature' "$STATE/list.html")"
contains "elenco: candidatura nuova presente" "Nuovo1" "$(cat "$STATE/list.html")"
contains "elenco: candidatura legacy presente" "Legacy1" "$(cat "$STATE/list.html")"
lacks "elenco: candidatura nel cestino assente" "Legacy6" "$(cat "$STATE/list.html")"
lacks "elenco: nessun URL o nome di file CV" "$CVPAT" "$(cat "$STATE/list.html")"
lacks "elenco: nessun link di modifica" "action=edit" "$(cat "$STATE/list.html")"
lacks "elenco: nessun link di cestino" "action=trash" "$(cat "$STATE/list.html")"
check "dettaglio → 200" 200 "$(get "$VJ" "/wp-admin/admin.php?page=tmk-candidature&application=$N1" "$STATE/detail.html")"
contains "dettaglio: lettera di presentazione" "Lettera di presentazione fittizia" "$(cat "$STATE/detail.html")"
check "dettaglio di un ID inesistente → 404" 404 "$(get "$VJ" '/wp-admin/admin.php?page=tmk-candidature&application=99999999')"
check "dettaglio di una posizione (non candidatura) → 404" 404 "$(get "$VJ" '/wp-admin/admin.php?page=tmk-candidature&application=245')"
check "dettaglio di una candidatura nel cestino → 404" 404 "$(get "$VJ" "/wp-admin/admin.php?page=tmk-candidature&application=$(cat "$STATE/app-trash")")"
check "dettaglio con ID non numerico → 404" 404 "$(get "$VJ" '/wp-admin/admin.php?page=tmk-candidature&application=abc')"
# escaping: una candidatura con markup nei campi
c=$(apply "$T/files/new1.pdf" '<b>Inj</b>' "Escape" 'esc@staging.invalid' cv.pdf)
NE=$(app_by_last Escape); wpe "update_post_meta( $NE, 'tmk_application_notes', '<script>alert(1)</script>' );" >/dev/null
get "$VJ" "/wp-admin/admin.php?page=tmk-candidature&application=$NE" "$STATE/esc.html" >/dev/null
lacks "dettaglio: markup dei dati non interpretato" "<script>alert(1)</script>" "$(cat "$STATE/esc.html")"
contains "dettaglio: markup mostrato come testo" "&lt;script&gt;alert(1)&lt;/script&gt;" "$(cat "$STATE/esc.html")"
DL=$(dl_link "$STATE/list.html" "$N1")
check_ne "elenco: link di download protetto" "" "$DL"
check "download del CV (consultazione) → 200" 200 "$(curl -s -b "$VJ" -D "$STATE/dl.h" -o "$STATE/dl.bin" -w '%{http_code}' "$URL/wp-admin/$DL")"
check "contenuto scaricato identico all'originale" "$(sha256sum "$T/files/new1.pdf" | cut -d' ' -f1)" "$(sha256sum "$STATE/dl.bin" | cut -d' ' -f1)"
contains "Content-Disposition: attachment" "Content-Disposition: attachment" "$(cat "$STATE/dl.h")"
contains "header nosniff" "X-Content-Type-Options: nosniff" "$(cat "$STATE/dl.h")"
contains "Cache-Control no-store" "no-store" "$(cat "$STATE/dl.h")"
check "download con nonce errato → 403" 403 "$(get "$VJ" "/wp-admin/admin-post.php?action=tmk_cv_download&application=$N1&_wpnonce=0123456789")"
N2=$(app_by_last Docx); DL2=$(dl_link "$STATE/list.html" "$N2")
WRONG=$(sed "s/application=$N2/application=$N1/" <<<"$DL2")
check "nonce di un'altra candidatura → 403" 403 "$(get "$VJ" "/wp-admin/$WRONG")"
check "download senza login → 302" 302 "$(curl -s -o "$STATE/anon.bin" -w '%{http_code}' "$URL/wp-admin/$DL")"
check "download senza login: nessun byte del file" 0 "$(grep -c '%PDF' "$STATE/anon.bin")"
contains "download senza login: reindirizzamento al login" "wp-login.php" "$(curl -s -o /dev/null -w '%{redirect_url}' "$URL/wp-admin/$DL")"
L1=$(head -1 "$STATE/apps-legacy")
check "CV legacy non ancora migrato: download negato → 403" 403 "$(get "$VJ" "/wp-admin/$(dl_link "$STATE/list.html" "$L1")" "$STATE/legacy.html")"
contains "messaggio: CV non ancora spostato" "non ancora spostato" "$(cat "$STATE/legacy.html")"

section "5. Schermate negate all'account di consultazione"
check "bacheca → reindirizzata all'elenco" 302 "$(get "$VJ" '/wp-admin/')"
for p in edit.php 'edit.php?post_type=job_application' 'edit.php?post_type=contact_message' 'edit.php?post_type=newsletter_sub' 'edit.php?post_type=page' 'edit.php?post_type=elementor_library' post-new.php "post.php?post=$N1&action=edit" "post.php?post=$N1&action=trash" upload.php users.php user-new.php 'user-edit.php?user_id=1' plugins.php themes.php options-general.php options.php tools.php export.php 'tools.php?page=tmk-cv-migrazione' edit-comments.php 'admin.php?page=elementor' 'admin.php?page=e-form-submissions' 'admin.php?page=wp-mail-smtp' 'admin.php?page=sendinblue' customize.php site-health.php 'admin-post.php?action=elementor_library_direct_actions' 'admin-post.php?action=tmk_qualcosa'; do
	check "negato: $p → 403" 403 "$(get "$VJ" "/wp-admin/$p")"
done
check "profilo personale consentito → 200" 200 "$(get "$VJ" '/wp-admin/profile.php')"
for a in query-attachments inline-save elementor_ajax wp-remove-post-lock save-attachment closed-postboxes; do
	check "AJAX non pubblica negata: $a → 403" 403 "$(curl -s -b "$VJ" -o /dev/null -w '%{http_code}' -d "action=$a" "$URL/wp-admin/admin-ajax.php")"
done
check "AJAX heartbeat consentita" 200 "$(curl -s -b "$VJ" -o /dev/null -w '%{http_code}' -d 'action=heartbeat&data[wp-auth-check]=true&_nonce=x&interval=60' "$URL/wp-admin/admin-ajax.php")"
RN=$(rest_nonce "$VJ" consultazione-cv)
check "REST: nonce valido per la sessione (controllo della prova)" 10 "${#RN}"
check "REST autenticata negata (users/me) → 403" 403 "$(curl -s -b "$VJ" -H "X-WP-Nonce: $RN" -o /dev/null -w '%{http_code}' "$URL/wp-json/wp/v2/users/me")"
check "REST autenticata negata (media privati) → 403" 403 "$(curl -s -b "$VJ" -H "X-WP-Nonce: $RN" -o /dev/null -w '%{http_code}' "$URL/wp-json/wp/v2/media?status=private")"
curl -s -b "$VJ" "$URL/wp-json/wp/v2/media?per_page=100&mime_type=application/pdf" > "$STATE/rest-media.json"
check "REST senza nonce: nessun allegato CV di candidatura nell'elenco media" 0 "$(for a in $(echo "select meta_value from wp_postmeta where meta_key='tmk_application_cv_id';" | sql); do grep -c "\"id\":$a," "$STATE/rest-media.json"; done | awk '{s+=$1} END {print s+0}')"
check "REST senza nonce: richiesta di media privati rifiutata" 400 "$(code -b "$VJ" "$URL/wp-json/wp/v2/media?status=private")"
check "password per applicazioni non disponibili" 0 "$(wpe "echo (int) wp_is_application_passwords_available_for_user( get_user_by( 'login', 'consultazione-cv' ) );")"
check "candidatura intatta dopo i tentativi di cestino" private "$(wp post get "$N1" --field=post_status)"
check "sito pubblico normale per l'account collegato" 200 "$(get "$VJ" '/')"
check "login subscriber → 302" 302 "$(login stg-sub "$SUB_PASS" "$SJ")"
check "elenco candidature negato al subscriber → 403" 403 "$(get "$SJ" '/wp-admin/admin.php?page=tmk-candidature')"
check "download negato al subscriber → 403" 403 "$(get "$SJ" "/wp-admin/$DL")"

section "6. Amministratore"
check "login amministratore → 302" 302 "$(login stg-admin "$ADMIN_PASS" "$ADMJ")"
check "elenco Job Applications → 200" 200 "$(get "$ADMJ" '/wp-admin/edit.php?post_type=job_application' "$STATE/adm-list.html")"
contains "Job Applications: link Scarica protetto" "action=tmk_cv_download" "$(cat "$STATE/adm-list.html")"
lacks "Job Applications: nessun URL o nome di file CV" "$CVPAT" "$(cat "$STATE/adm-list.html")"
check "pagina Candidature accessibile all'amministratore" 200 "$(get "$ADMJ" '/wp-admin/admin.php?page=tmk-candidature')"
check "Strumenti › Migrazione CV → 200" 200 "$(get "$ADMJ" '/wp-admin/tools.php?page=tmk-cv-migrazione' "$STATE/tools.html")"
contains "Strumenti: archivio disponibile" "disponibile" "$(cat "$STATE/tools.html")"
check "Strumenti: operazione con nonce errato → 403" 403 "$(curl -s -b "$ADMJ" -o /dev/null -w '%{http_code}' -d "_wpnonce=bad&tmk_cv_op=copy" "$URL/wp-admin/tools.php?page=tmk-cv-migrazione")"
TN=$(grep -o 'name="_wpnonce" value="[a-f0-9]*"' "$STATE/tools.html" | head -1 | sed 's/.*value="//;s/"$//')
curl -s -b "$ADMJ" -o "$STATE/tools-remove.html" -d "_wpnonce=$TN&_wp_http_referer=/wp-admin/tools.php?page=tmk-cv-migrazione&tmk_cv_op=remove" "$URL/wp-admin/tools.php?page=tmk-cv-migrazione"
contains "Strumenti: rimozione senza conferma rifiutata" "manca la conferma esplicita" "$(cat "$STATE/tools-remove.html")"
curl -s -b "$ADMJ" -d "action=query-attachments&query[posts_per_page]=500" "$URL/wp-admin/admin-ajax.php" > "$STATE/media.json"
check "Libreria media: un PDF normale del sito e' visibile (controllo della prova)" 1 "$(grep -c '"id":20943,' "$STATE/media.json")"
check "Libreria media: CV nuovi nascosti all'amministratore" 0 "$(grep -c "\"id\":$A1," "$STATE/media.json")"
summary
