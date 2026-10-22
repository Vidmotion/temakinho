#!/usr/bin/env bash
# Fase 2 — migrazione dei CV pubblici nell'archivio riservato, con tutti i casi di errore.
. "$(dirname "$0")/lib.sh"
load_counters
VJ=$STATE/jar-viewer
inv(){ "$STG" wp tmk-cv inventory --format=json 2>/dev/null; }
invsum(){ inv | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["summary"][$argv[1]];' "$1"; }
mig(){ "$STG" wp tmk-cv migrate "$@" > "$STATE/mig.out" 2>&1; echo $?; }
rem(){ "$STG" wp tmk-cv remove-public --yes > "$STATE/rem.out" 2>&1; echo $?; }
mstate(){ cx "php -r '\$m=json_decode(@file_get_contents(\"/var/www/tmk-private/manifest/att-$1.json\"),true); echo \$m[\"state\"] ?? \"-\";'"; }
mstate_key(){ cx "php -r '\$m=json_decode(@file_get_contents(\"/var/www/tmk-private/manifest/$1.json\"),true); echo \$m[\"state\"] ?? \"-\";'"; }
legacy_atts(){ cut -f1 "$STATE/legacy-files.tsv" | grep -v '^0$' | sort -un; }
public_left(){ cx "find /var/www/html/wp-content/uploads -regextype posix-extended -type f -regex '.*/[A-Za-z0-9]{32}(\.(pdf|docx?|odt)|-pdf(-[0-9]+x[0-9]+)?\.jpg)$' | wc -l"; }
dl_sha(){ # app -> sha del download fatto dall'account di consultazione
	local l; get "$VJ" '/wp-admin/admin.php?page=tmk-candidature' "$STATE/l.html" >/dev/null
	l=$(sed 's/&#038;/\&/g' "$STATE/l.html" | grep -o "admin-post.php?action=tmk_cv_download&application=$1&_wpnonce=[a-f0-9]*" | head -1)
	curl -s -b "$VJ" -o "$STATE/d.bin" -w '%{http_code}' "$URL/wp-admin/$l" > "$STATE/d.code"; sha256sum "$STATE/d.bin" | cut -d' ' -f1
}
ATTS=$(legacy_atts); FIRST=$(head -1 <<<"$ATTS"); SECOND=$(sed -n 2p <<<"$ATTS"); THIRD=$(sed -n 3p <<<"$ATTS")
ORPH=$(cat "$STATE/orphan-file"); ATT_ORPH=$(cat "$STATE/att-orphan"); APP_TRASH=$(cat "$STATE/app-trash")
for a in $ATTS; do echo "$a $(raw_ref "$a")"; done > "$STATE/refs-before"

section "7. Inventario prima della migrazione"
check "allegati CV pubblici (6 candidature + 1 orfano)" 7 "$(invsum pubblici)"
check "candidature nel cestino incluse" 1 "$(invsum cestino)"
check "allegati senza candidatura inclusi" 1 "$(invsum senza_candidatura)"
check "file CV orfani su disco rilevati" 1 "$(invsum orfani_su_disco)"
check "file pubblici da proteggere (PDF + anteprime + orfano)" 36 "$(invsum file_pubblici)"
inv | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); foreach($j["attachments"] as $r){ echo $r["att"],":",$r["app_status"],":",$r["location"],";"; }' > "$STATE/inv-before"
note "inventario: $(cat "$STATE/inv-before")"
contains "inventario: allegato della candidatura nel cestino" "trash:pubblico" "$(cat "$STATE/inv-before")"
contains "inventario: allegato orfano" "$ATT_ORPH:nessuna:pubblico" "$(cat "$STATE/inv-before")"

section "8. Simulazione"
check "simulazione: esito" 0 "$(mig --dry-run)"
check "simulazione: nessun manifesto" 0 "$(cx 'ls /var/www/tmk-private/manifest | wc -l')"
check "simulazione: riferimenti invariati" "$(cat "$STATE/refs-before")" "$(for a in $ATTS; do echo "$a $(raw_ref "$a")"; done)"
check "simulazione: file pubblici intatti" 36 "$(invsum file_pubblici)"

section "9. Interruzioni"
cx 'echo "planned exit" > /tmp/tmk-test-checkpoint'
check "interruzione dopo il manifesto (processo terminato)" 3 "$(mig)"
check "manifesto registrato prima di ogni copia" planned "$(mstate "$FIRST")"
check "riferimento non cambiato" "$(grep "^$FIRST " "$STATE/refs-before" | cut -d' ' -f2)" "$(raw_ref "$FIRST")"
check "nessuna copia privata ancora" 0 "$(cx 'find /var/www/tmk-private/cv -path "*2026/10/*" -name "*-pdf*.jpg" | wc -l')"
cx 'echo "copied exit" > /tmp/tmk-test-checkpoint'
check "ripresa + interruzione dopo la copia" 3 "$(mig)"
check "manifesto: copie verificate" copied "$(mstate "$FIRST")"
check "riferimento ancora pubblico dopo la copia" "$(grep "^$FIRST " "$STATE/refs-before" | cut -d' ' -f2)" "$(raw_ref "$FIRST")"
check "copie private integre (5 file)" 5 "$(cx "php -r '\$m=json_decode(file_get_contents(\"/var/www/tmk-private/manifest/att-$FIRST.json\"),true); \$n=0; foreach(\$m[\"files\"] as \$f){ if(hash_file(\"sha256\",\$f[\"dst\"])===\$f[\"sha256\"]) \$n++; } echo \$n;'")"
cx 'echo "referenced-db exit" > /tmp/tmk-test-checkpoint'
check "ripresa + interruzione fra database e manifesto" 3 "$(mig)"
contains "riferimento gia' privato nel database" "^/var/www/tmk-private/cv/" "$(raw_ref "$FIRST")"
check "manifesto rimasto a 'copied'" copied "$(mstate "$FIRST")"

section "10. Salvataggio del riferimento fallito"
cx 'touch /tmp/tmk-test-fail-ref'
check "migrazione con salvataggio fallito: esito di errore" 1 "$(mig)"
contains "errore riportato" "aggiornamento del riferimento non riuscito" "$(cat "$STATE/mig.out")"
check "allegato interrotto prima completato dal manifesto" referenced "$(mstate "$FIRST")"
check "riferimento originale ripristinato e verificato" "$(grep "^$SECOND " "$STATE/refs-before" | cut -d' ' -f2)" "$(raw_ref "$SECOND")"
check "manifesto resta a 'copied' (nessuna rimozione possibile)" copied "$(mstate "$SECOND")"
check "file pubblici ancora tutti presenti" 36 "$(public_left)"
cx 'rm -f /tmp/tmk-test-fail-ref'

section "11. Nuove candidature durante la migrazione"
cx 'echo "copied-file sleep:1" > /tmp/tmk-test-checkpoint'
( "$STG" wp tmk-cv migrate > "$STATE/mig-bg.out" 2>&1; echo $? > "$STATE/mig-bg.rc" ) &
BG=$!
sleep 2
check "lock: una seconda migrazione in parallelo viene rifiutata" 1 "$(mig)"
contains "lock: messaggio" "un'altra operazione" "$(cat "$STATE/mig.out")"
for i in 1 2 3; do mkpdf "conc$i.pdf" "CV FITTIZIO CONCORRENTE $i"; c=$(apply "$T/files/conc$i.pdf" Prova "Conc$i" "conc$i@staging.invalid" cv.pdf); check "candidatura $i inviata durante la migrazione" 200 "$c"; done
wait "$BG"; cx 'rm -f /tmp/tmk-test-checkpoint'
check "migrazione completata" 0 "$(cat "$STATE/mig-bg.rc")"
for i in 1 2 3; do a=$(app_by_last "Conc$i"); contains "candidatura concorrente $i: CV nell'archivio" "^/var/www/tmk-private/cv/" "$(raw_ref "$(cv_of "$a")")"; done
check "nessun allegato CV ancora pubblico" 0 "$(invsum pubblici)"
for a in $ATTS; do check "allegato $a: manifesto 'referenced'" referenced "$(mstate "$a")"; done
check "file orfano: copia privata verificata" copied "$(mstate_key "orphan-$(hphp -r 'echo substr(hash("sha256",$argv[1]),0,24);' "$ORPH")")"
check "copie pubbliche ancora presenti prima della rimozione" 36 "$(public_left)"
note "esito: $(grep -c 'copiato e verificato' "$STATE/mig-bg.out") allegati copiati in questo passaggio"

section "12. Download dopo la migrazione (copie pubbliche ancora presenti)"
login consultazione-cv "$VIEWER_PASS" "$VJ" >/dev/null
for a in $(cat "$STATE/apps-legacy"); do
	att=$(cv_of "$a"); exp=$(awk -F'\t' -v t="$att" '$1==t && $2 ~ /\.pdf$/ {print $3}' "$STATE/legacy-files.tsv")
	if [ "$a" = "$APP_TRASH" ]; then check "candidatura nel cestino: non consultabile" 404 "$(get "$VJ" "/wp-admin/admin.php?page=tmk-candidature&application=$a")"; continue; fi
	got=$(dl_sha "$a"); check "download CV migrato (candidatura $a): integro" "200 $exp" "$(cat "$STATE/d.code") $got"
done
check "seconda migrazione: nessuna operazione" 0 "$(mig)"
check "seconda migrazione: nessuna copia nuova" 0 "$(grep -c 'copiato e verificato' "$STATE/mig.out")"

section "13. Rimozione bloccata"
# a) riferimento alterato dopo la copia
OREF=$(raw_ref "$THIRD"); wpe "update_post_meta( $THIRD, '_wp_attached_file', '$(grep "^$THIRD " "$STATE/refs-before" | cut -d' ' -f2)' );" >/dev/null
# b) copia privata corrotta
CORR=$(cx "php -r '\$m=json_decode(file_get_contents(\"/var/www/tmk-private/manifest/att-$FIRST.json\"),true); echo \$m[\"files\"][\"size:thumbnail\"][\"dst\"];'")
cx "printf x >> '$CORR'"
# c) cancellazione impedita dal filesystem (attributo immutabile, dal server)
VOLP=$(docker volume inspect tmkstg_temakinho_uploads --format '{{.Mountpoint}}')
LOCKF=$(cx "php -r '\$m=json_decode(file_get_contents(\"/var/www/tmk-private/manifest/att-$SECOND.json\"),true); echo \$m[\"files\"][\"main\"][\"src\"];'")
chattr +i "$VOLP/${LOCKF#/var/www/html/wp-content/uploads/}"
check "rimozione con condizioni non soddisfatte: esito di errore" 1 "$(rem)"
contains "riferimento alterato: allegato bloccato" "att-$THIRD: BLOCCATO: il riferimento attuale non punta alla copia privata" "$(cat "$STATE/rem.out")"
contains "copia privata non integra: allegato bloccato" "att-$FIRST: BLOCCATO: copia privata mancante o non integra" "$(cat "$STATE/rem.out")"
contains "file non cancellabile: rimozione incompleta segnalata" "att-$SECOND: rimozione INCOMPLETA" "$(cat "$STATE/rem.out")"
check "riferimento alterato: copie pubbliche intatte" 5 "$(awk -F'\t' -v t="$THIRD" '$1==t' "$STATE/legacy-files.tsv" | while IFS=$'\t' read -r a f s; do cexists "$f"; done | grep -c si)"
check "copia corrotta: copie pubbliche intatte" 5 "$(awk -F'\t' -v t="$FIRST" '$1==t' "$STATE/legacy-files.tsv" | while IFS=$'\t' read -r a f s; do cexists "$f"; done | grep -c si)"
check "file non cancellabile: ancora presente" si "$(cexists "$LOCKF")"
check "file non cancellabile: manifesto non chiuso" referenced "$(mstate "$SECOND")"
check "allegati senza problemi: copie pubbliche rimosse" public_removed "$(mstate "$(sed -n 4p <<<"$ATTS")")"
# ripristino delle condizioni e ripresa
chattr -i "$VOLP/${LOCKF#/var/www/html/wp-content/uploads/}"
SRC=$(cx "php -r '\$m=json_decode(file_get_contents(\"/var/www/tmk-private/manifest/att-$FIRST.json\"),true); echo \$m[\"files\"][\"size:thumbnail\"][\"src\"];'")
cx "cp '$SRC' '$CORR'"
check "riferimento rimesso a posto rieseguendo la migrazione" 0 "$(mig)"
check "riferimento di nuovo privato" "$OREF" "$(raw_ref "$THIRD")"
check "rimozione ripresa: esito" 0 "$(rem)"
for a in $ATTS; do check "allegato $a: copie pubbliche rimosse" public_removed "$(mstate "$a")"; done
check "file orfano: copia pubblica rimossa" public_removed "$(mstate_key "orphan-$(hphp -r 'echo substr(hash("sha256",$argv[1]),0,24);' "$ORPH")")"

section "14. Stato finale e verifica HTTP"
check "nessun file CV o anteprima rimasto negli upload pubblici" 0 "$(public_left)"
bad=0; n=0; while read -r u; do n=$((n+1)); c=$(code -I "$u"); [ "$c" = 404 ] || { bad=$((bad+1)); note "ancora servito: $u ($c)"; }; done < "$STATE/legacy-urls"
check "vecchi URL dei CV (PDF + JPG) → 404" "$n su $n" "$((n-bad)) su $n"
check "verifica dell'archivio senza problemi" 0 "$("$STG" wp tmk-cv verify > "$STATE/verify.out" 2>&1; echo $?)"
check "inventario: file pubblici residui" 0 "$(invsum file_pubblici)"
check "inventario: allegati tutti privati" 0 "$(invsum pubblici)"
for a in $(cat "$STATE/apps-legacy"); do
	[ "$a" = "$APP_TRASH" ] && continue
	att=$(cv_of "$a"); exp=$(awk -F'\t' -v t="$att" '$1==t && $2 ~ /\.pdf$/ {print $3}' "$STATE/legacy-files.tsv")
	got=$(dl_sha "$a"); check "download dopo la rimozione (candidatura $a): integro" "200 $exp" "$(cat "$STATE/d.code") $got"
done
for f in $(cx "find /var/www/tmk-private/cv -type f -name '*.pdf' | head -20"); do
	rel=${f#/var/www/tmk-private/cv/}; c=$(code "$URL/wp-content/uploads/$rel"); [ "$c" = 404 ] || ko "copia privata raggiungibile via upload: $rel ($c)"
done
ok "nessuna copia privata raggiungibile da URL degli upload"
check "seconda rimozione: nulla da fare" 0 "$(rem)"
# eliminazione dell'allegato da parte dell'amministratore: anche il file privato sparisce
EA=$(cv_of "$(app_by_last Escape)"); EF=$(raw_ref "$EA")
wp post delete "$EA" --force >/dev/null
check "eliminazione allegato: file privato rimosso" no "$(cexists "$EF")"
summary
