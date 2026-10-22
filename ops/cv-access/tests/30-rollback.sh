#!/usr/bin/env bash
# Fase 3 — rollback DOPO nuove candidature: si torna al plugin 1.21.3 senza perdere nessuna
# candidatura ne' la possibilita' di scaricarne il CV, poi si ritorna al codice nuovo.
# Procedura provata (la stessa del RUNBOOK):
#   1. wp tmk-cv rollback-prepare --yes        (codice nuovo ancora attivo)
#   2. deploy del plugin 1.21.3 con Compose/Dockerfile NUOVI (il volume privato resta montato)
#   3. rollback-prepare di nuovo con cv-storage.php caricato a mano (copre le candidature
#      arrivate fra il passo 1 e il passo 2)
#   Nessun ripristino del database: i dati arrivati dopo il rilascio restano.
. "$(dirname "$0")/lib.sh"
load_counters
NEW=${NEW_SHA:?}; OLD=${OLD_SHA:?}
ADMJ=$STATE/jar-admin
allapps(){ echo "select count(*) from wp_posts where post_type='job_application';" | sql; }
cv_sha_any(){ csha "$(abs_ref "$1")"; }

section "15. Rollback con candidature arrivate dopo il rilascio"
for i in 1 2; do mkpdf "rb$i.pdf" "CV FITTIZIO DOPO RILASCIO $i"; c=$(apply "$T/files/rb$i.pdf" Prova "Dopo$i" "dopo$i@staging.invalid" cv.pdf); check "candidatura $i arrivata dopo il rilascio" 200 "$c"; done
N_BEFORE=$(allapps)
# impronta di ogni CV ancora collegato a una candidatura (qualunque stato)
echo "select post_id, meta_value from wp_postmeta where meta_key='tmk_application_cv_id';" | sql | while read -r app att; do
	[ "$(echo "select post_type from wp_posts where ID=$att;" | sql)" = attachment ] && printf '%s %s %s\n' "$app" "$att" "$(cv_sha_any "$att")"
done > "$STATE/cv-shas-before-rollback"
note "candidature: $N_BEFORE, CV con impronta: $(wc -l < "$STATE/cv-shas-before-rollback")"
NPRIV=$(cx 'find /var/www/tmk-private/cv -type f | wc -l')
check "rollback senza conferma: non eseguito" 1 "$("$STG" wp tmk-cv rollback-prepare > "$STATE/rb.out" 2>&1; echo $?)"
check "rollback-prepare: esito" 0 "$("$STG" wp tmk-cv rollback-prepare --yes > "$STATE/rb.out" 2>&1; echo $?)"
check "nessun riferimento ancora privato" 0 "$(echo "select count(*) from wp_postmeta where meta_key='_wp_attached_file' and meta_value like '/var/www/tmk-private/%';" | sql)"
check "copie private conservate (nessun file dell'archivio eliminato)" "$NPRIV" "$(cx 'find /var/www/tmk-private/cv -type f | wc -l')"
# candidatura arrivata DOPO rollback-prepare ma prima del cambio di codice
mkpdf rb3.pdf "CV FITTIZIO FINESTRA ROLLBACK"; check "candidatura nella finestra del rollback" 200 "$(apply "$T/files/rb3.pdf" Prova Finestra finestra@staging.invalid cv.pdf)"
contains "candidatura nella finestra: CV privato" "^/var/www/tmk-private/" "$(raw_ref "$(cv_of "$(app_by_last Finestra)")")"

# commit di rollback: plugin 1.21.3, Compose e Dockerfile nuovi
SRC=/root/tmk-staging/src/$NEW; RB=/root/tmk-staging/src/rollback
rm -rf "$RB"; git clone -q "$SRC" "$RB"; git -C "$RB" -c advice.detachedHead=false checkout -q "$NEW"
git -C "$RB" checkout -q "$OLD" -- wp-content/plugins/tmk-design-system
git -C "$RB" -c user.name=staging -c user.email=staging@invalid commit -q -m "rollback di prova: plugin 1.21.3"
RBSHA=$(git -C "$RB" rev-parse HEAD); mv "$RB" "/root/tmk-staging/src/$RBSHA"
"$STG" up "$RBSHA" >/dev/null 2>&1
for i in $(seq 1 60); do [ "$(code "$URL/wp-includes/js/wp-emoji-loader.min.js")" = 200 ] && break; sleep 3; done
check "codice 1.21.3 attivo dopo il rollback" 1.21.3 "$(wp plugin get tmk-design-system --field=version)"
check "volume privato ancora montato" si "$(cx 'grep -qs " /var/www/tmk-private " /proc/self/mountinfo && echo si || echo no')"
docker cp "/root/tmk-staging/src/$NEW/wp-content/plugins/tmk-design-system/inc/cv-storage.php" "$WPC:/tmp/cv-storage.php"
check "rollback-prepare con cv-storage.php caricato a mano (plugin 1.21.3)" 0 "$("$STG" wp eval 'require "/tmp/cv-storage.php"; exit( tmk_cv_store_cli( array( "rollback-prepare" ), array( "yes" => 1 ) ) );' > "$STATE/rb2.out" 2>&1; echo $?)"
check "candidatura nella finestra: ora riferimento pubblico" "" "$(raw_ref "$(cv_of "$(app_by_last Finestra)")" | grep '^/var/www/tmk-private/')"
check "nessuna candidatura persa" "$((N_BEFORE+1))" "$(allapps)"
# con il codice 1.21.3 ogni CV e' di nuovo scaricabile dal link dell'elenco amministratore
login stg-admin "$ADMIN_PASS" "$ADMJ" >/dev/null
get "$ADMJ" '/wp-admin/edit.php?post_type=job_application&post_status=all&posts_per_page=100' "$STATE/rb-list.html" >/dev/null
bad=0; n=0
while read -r app att sha; do
	[ "$(wp post get "$app" --field=post_status)" = trash ] && continue
	n=$((n+1)); u=$(grep -o "href=\"[^\"]*\" download" "$STATE/rb-list.html" | sed 's/href="//;s/" download//' | while read -r h; do f=${h##*/}; [ "$(basename "$(abs_ref "$att")")" = "$f" ] && echo "$h"; done | head -1)
	[ -n "$u" ] && [ "$(curl -s "$u" | sha256sum | cut -d' ' -f1)" = "$sha" ] || { bad=$((bad+1)); note "non scaricabile: candidatura $app"; }
done < "$STATE/cv-shas-before-rollback"
check "con 1.21.3 ogni CV (anche quelli arrivati dopo il rilascio) e' scaricabile e integro" "$n su $n" "$((n-bad)) su $n"
mkpdf rb4.pdf "CV FITTIZIO DURANTE IL ROLLBACK"; check "candidatura con il codice 1.21.3 durante il rollback" 200 "$(apply "$T/files/rb4.pdf" Prova Durante durante@staging.invalid cv.pdf)"

section "16. Ritorno al codice nuovo dopo il rollback"
"$STG" up "$NEW" >/dev/null 2>&1
for i in $(seq 1 60); do [ "$(code "$URL/wp-includes/js/wp-emoji-loader.min.js")" = 200 ] && break; sleep 3; done
check "codice nuovo attivo" 1.22.0 "$(wp plugin get tmk-design-system --field=version)"
check "migrazione dopo il rollback" 0 "$("$STG" wp tmk-cv migrate > "$STATE/m2.out" 2>&1; echo $?)"
check "rimozione delle copie pubbliche" 0 "$("$STG" wp tmk-cv remove-public --yes > "$STATE/r2.out" 2>&1; echo $?)"
check "verifica" 0 "$("$STG" wp tmk-cv verify > "$STATE/v2.out" 2>&1; echo $?)"
check "nessun file CV pubblico" 0 "$(cx "find /var/www/html/wp-content/uploads -regextype posix-extended -type f -regex '.*/[A-Za-z0-9]{32}(\.(pdf|docx?|odt)|-pdf(-[0-9]+x[0-9]+)?\.jpg)$' | wc -l")"
check "tutte le candidature presenti" "$((N_BEFORE+2))" "$(allapps)"
bad=0; while read -r app att sha; do [ "$(cv_sha_any "$att")" = "$sha" ] || bad=$((bad+1)); done < "$STATE/cv-shas-before-rollback"
check "ogni CV identico a prima del rollback" 0 "$bad"
summary
