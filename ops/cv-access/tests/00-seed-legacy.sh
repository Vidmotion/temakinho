#!/usr/bin/env bash
# Fase 0 — con il codice ATTUALE di produzione (1.21.3) si ricrea lo stato da migrare, usando il
# form vero: candidature con PDF + anteprime JPG pubbliche, una nel cestino, un allegato orfano
# (candidatura eliminata definitivamente), un file CV orfano senza allegato. Solo dati fittizi.
. "$(dirname "$0")/lib.sh"
: > "$LOG"; rm -f "$STATE"/*
section "0. Stato iniziale con tmk-design-system $(cx "grep -m1 Version: /var/www/html/wp-content/plugins/tmk-design-system/tmk-design-system.php | awk '{print \$NF}'")"
wpe 'echo wp_json_encode( array_map( function ( $r ) { ksort( $r["capabilities"] ); return $r["capabilities"]; }, wp_roles()->roles ) );' > "$STATE/roles-before.json"
note "ruoli registrati: $(hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo implode(",", array_keys($j));' < "$STATE/roles-before.json")"

for i in 1 2 3 4 5 6 7 8; do mkpdf "legacy$i.pdf" "CV FITTIZIO LEGACY $i"; done
for i in 1 2 3 4 5 6 7; do
	c=$(apply "$T/files/legacy$i.pdf" "Prova" "Legacy$i" "legacy$i@staging.invalid" "curriculum-legacy-$i.pdf")
	check "candidatura legacy $i inviata col codice 1.21.3" 200 "$c"
done
L6=$(app_by_last Legacy6); L7=$(app_by_last Legacy7)
# Cestino come da wp-admin (wp_trash_post): WP-CLI non cestina i tipi di contenuto personalizzati.
wpe "wp_trash_post( $L6 );" >/dev/null; check "candidatura legacy 6 nel cestino" trash "$(wp post get "$L6" --field=post_status)"
CV7=$(cv_of "$L7"); wp post delete "$L7" --force >/dev/null
check "candidatura legacy 7 eliminata definitivamente (allegato rimasto orfano)" attachment "$(echo "select post_type from wp_posts where ID=$CV7;" | sql)"
echo "$L6" > "$STATE/app-trash"; echo "$CV7" > "$STATE/att-orphan"
# file CV orfano: nome casuale come quelli del form, nessun allegato
ON=$(openssl rand -base64 48 | tr -dc A-Za-z0-9 | head -c 32)
docker cp "$T/files/legacy8.pdf" "$WPC:/var/www/html/wp-content/uploads/2026/10/$ON.pdf"
cx "chown www-data:www-data /var/www/html/wp-content/uploads/2026/10/$ON.pdf"
echo "/var/www/html/wp-content/uploads/2026/10/$ON.pdf" > "$STATE/orphan-file"

# inventario dei file pubblici di prova (PDF + anteprime) con checksum
: > "$STATE/legacy-files.tsv"
for i in 1 2 3 4 5 6; do
	a=$(app_by_last "Legacy$i"); att=$(cv_of "$a"); main=$(abs_ref "$att"); base=${main%.pdf}
	for f in $(cx "ls $base.pdf $base-pdf*.jpg 2>/dev/null"); do printf '%s\t%s\t%s\n' "$att" "$f" "$(csha "$f")" >> "$STATE/legacy-files.tsv"; done
	echo "$a" >> "$STATE/apps-legacy"
done
main=$(abs_ref "$CV7"); base=${main%.pdf}
for f in $(cx "ls $base.pdf $base-pdf*.jpg 2>/dev/null"); do printf '%s\t%s\t%s\n' "$CV7" "$f" "$(csha "$f")" >> "$STATE/legacy-files.tsv"; done
printf '%s\t%s\t%s\n' 0 "/var/www/html/wp-content/uploads/2026/10/$ON.pdf" "$(csha "/var/www/html/wp-content/uploads/2026/10/$ON.pdf")" >> "$STATE/legacy-files.tsv"
NPDF=$(grep -c '\.pdf' "$STATE/legacy-files.tsv"); NJPG=$(grep -c '\.jpg' "$STATE/legacy-files.tsv")
check "file legacy creati: PDF (6 candidature + 1 allegato orfano + 1 file orfano)" 8 "$NPDF"
check "anteprime JPG pubbliche generate dal codice 1.21.3 (4 per PDF di candidatura)" 28 "$NJPG"
bad=0; while IFS=$'\t' read -r att f sha; do u="$URL/wp-content/uploads/${f#/var/www/html/wp-content/uploads/}"; [ "$(code -I "$u")" = 200 ] || bad=$((bad+1)); echo "$u" >> "$STATE/legacy-urls"; done < "$STATE/legacy-files.tsv"
check "con 1.21.3 tutti i file CV di prova sono pubblici via HTTP (problema riprodotto)" "$(wc -l < "$STATE/legacy-files.tsv") pubblici" "$(( $(wc -l < "$STATE/legacy-files.tsv") - bad )) pubblici"
section "0b. Modulo convenzioni con il codice 1.21.3 (solo controlli del browser)"
B=$(conv_count)
conv "Azienda Prova" "" "prova@staging.invalid" "0" "Logistica" "Milano" >/dev/null; check "1.21.3: dipendenti = 0 accettato dal server (problema riprodotto)" accettato "$(conv_ok)"
conv "Azienda Prova" "ftp://azienda.example" "prova@staging.invalid" "12" "Logistica" "Milano" >/dev/null; check "1.21.3: URL ftp:// accettato dal server (problema riprodotto)" accettato "$(conv_ok)"
conv "Azienda Prova" "" "prova@staging.invalid" "12" "Logistica" "" >/dev/null; check "1.21.3: nessuna citta' accettato dal server (problema riprodotto)" accettato "$(conv_ok)"
conv "Azienda Prova" "" "prova@staging.invalid" "12" "Logistica" "Napoli" >/dev/null; check "1.21.3: citta' fuori elenco accettata dal server (problema riprodotto)" accettato "$(conv_ok)"
check "1.21.3: i 4 invii non validi sono stati salvati" "$((B+4))" "$(conv_count)"
B=$(conv_count); conv "Azienda Spam" "" "spam@staging.invalid" "12" "Logistica" "Milano" "sono-un-bot" >/dev/null; echo "$(conv_ok) salvati:$(( $(conv_count) - B ))" > "$STATE/honeypot-old"
echo "$(contact_submit "$STATE/contact-old.json") $(echo "select count(*) from wp_e_submissions where element_id='tmkct015';" | sql)" > "$STATE/contact-old.code"
note "1.21.3: modulo Contattaci → $(hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo !empty($j["success"]) ? "accettato" : "rifiutato: ".$j["data"]["message"];' < "$STATE/contact-old.json") (comportamento di riferimento)"
note "1.21.3: honeypot compilato → $(cat "$STATE/honeypot-old" | cut -d' ' -f1) (comportamento di riferimento)"
summary
