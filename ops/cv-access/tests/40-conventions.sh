#!/usr/bin/env bash
# Fase 4 — modulo "Convenzioni aziendali": validazione lato server (codice nuovo), altri moduli
# invariati, notifiche (con indirizzi segnaposto di staging) inviate solo per invii validi.
. "$(dirname "$0")/lib.sh"
load_counters
rej(){ # descrizione campo-atteso args...
	local d=$1 f=$2; shift 2; local b; b=$(conv_count); conv "$@" >/dev/null
	check "rifiutato: $d" "rifiutato $f $b" "$(conv_ok) $(conv_err) $(conv_count)"
}
acc(){ local d=$1; shift; local b; b=$(conv_count); conv "$@" >/dev/null; check "accettato: $d" "accettato $((b+1))" "$(conv_ok) $(conv_count)"; }

section "17. Convenzioni: invii validi"
acc "invio completo" "Azienda Prova Srl" "https://www.azienda-prova.it" "referente@staging.invalid" "120" "Logistica" "Milano|Roma"
acc "sito facoltativo vuoto" "Azienda Due" "" "due@staging.invalid" "1" "Consulenza" "Bologna"
acc "URL http con percorso" "Azienda Tre" "http://azienda-tre.it/chi-siamo?x=1" "tre@staging.invalid" "2500" "Retail" "Lione|Rimini|Reggio Calabria"
acc "numero con spazi attorno" "Azienda Quattro" "" "quattro@staging.invalid" " 3 " "Servizi" "Milano"
SID=$(echo "select max(id) from wp_e_submissions where element_id='$CONV_FORM';" | sql)
check "valori salvati in Submissions (dipendenti, ripulito da Elementor)" "3" "$(echo "select value from wp_e_submissions_values where submission_id=$SID and \`key\`='dipendenti';" | sql)"

section "18. Convenzioni: invii non validi (nessun salvataggio)"
for v in 0 -5 1.5 abc 1e3 "12,5" "+7" "10000000"; do rej "dipendenti «$v»" dipendenti "Azienda" "" "a@staging.invalid" "$v" "Logistica" "Milano"; done
rej "dipendenti vuoto" dipendenti "Azienda" "" "a@staging.invalid" "" "Logistica" "Milano"
for v in "ftp://azienda.example" "javascript:alert(1)" "www.azienda.it" "http://localhost" "https://utente:pw@azienda.it" "https://azienda .it"; do rej "sito «$v»" sito_web "Azienda" "$v" "a@staging.invalid" "10" "Logistica" "Milano"; done
rej "nessuna città" citta "Azienda" "" "a@staging.invalid" "10" "Logistica" ""
rej "città fuori elenco" citta "Azienda" "" "a@staging.invalid" "10" "Logistica" "Napoli"
rej "città ripetuta" citta "Azienda" "" "a@staging.invalid" "10" "Logistica" "Milano|Milano"
rej "città valida + non valida" citta "Azienda" "" "a@staging.invalid" "10" "Logistica" "Milano|Napoli"
rej "email non valida" email "Azienda" "" "non-una-email" "10" "Logistica" "Milano"
rej "nome azienda vuoto" azienda "" "" "a@staging.invalid" "10" "Logistica" "Milano"
rej "nome azienda di un carattere" azienda "A" "" "a@staging.invalid" "10" "Logistica" "Milano"
rej "settore vuoto" settore "Azienda" "" "a@staging.invalid" "10" "" "Milano"
conv "Azienda" "" "a@staging.invalid" "0" "Logistica" "Napoli" >/dev/null
check "più errori insieme, uno per campo" "citta,dipendenti" "$(conv_err | tr ',' '\n' | sort | paste -sd, -)"
contains "messaggi in italiano" "numero intero" "$(cat "$STATE/conv.json" | hphp -r 'echo json_encode(json_decode(stream_get_contents(STDIN)), JSON_UNESCAPED_UNICODE);')"
B=$(conv_count); conv "Azienda Spam" "" "spam@staging.invalid" "12" "Logistica" "Milano" "sono-un-bot" >/dev/null
check "honeypot: comportamento identico a 1.21.3" "$(cat "$STATE/honeypot-old")" "$(conv_ok) salvati:$(( $(conv_count) - B ))"

section "19. Altri moduli invariati (Contattaci)"
C1=$(contact_submit "$STATE/contact-new.json")
check "Contattaci: risposta identica a quella con 1.21.3" "$(cat "$STATE/contact-old.code" | cut -d' ' -f1) $(hphp -r 'echo json_encode(json_decode(stream_get_contents(STDIN),true));' < "$STATE/contact-old.json")" "$C1 $(hphp -r 'echo json_encode(json_decode(stream_get_contents(STDIN),true));' < "$STATE/contact-new.json")"
check "Contattaci: stesso numero di invii salvati di prima" "$(cut -d' ' -f2 "$STATE/contact-old.code")" "$(echo "select count(*) from wp_e_submissions where element_id='tmkct015';" | sql)"

section "20. Notifiche convenzioni (indirizzi segnaposto di staging)"
docker cp /root/tmk-staging/ops/attiva-notifiche.php "$WPC:/tmp/attiva-notifiche.php" >/dev/null
check "attivazione senza indirizzi: rifiutata" 1 "$(docker exec -u www-data "$WPC" php /tmp/wp-cli.phar --path=/var/www/html --user=stg-admin eval-file /tmp/attiva-notifiche.php >/dev/null 2>&1; echo $?)"
check "attivazione con indirizzi segnaposto" 0 "$(docker exec -u www-data -e TMK_CONV_TO=convenzioni-interno@staging.invalid -e TMK_CONV_REPLY_TO=convenzioni-risposte@staging.invalid "$WPC" php /tmp/wp-cli.phar --path=/var/www/html --user=stg-admin eval-file /tmp/attiva-notifiche.php >/dev/null 2>&1; echo $?)"
check "attivazione idempotente" "Nessuna modifica" "$(docker exec -u www-data -e TMK_CONV_TO=convenzioni-interno@staging.invalid -e TMK_CONV_REPLY_TO=convenzioni-risposte@staging.invalid "$WPC" php /tmp/wp-cli.phar --path=/var/www/html --user=stg-admin eval-file /tmp/attiva-notifiche.php 2>&1 | grep -o 'Nessuna modifica')"
curl -s -X DELETE http://localhost:18025/api/v1/messages >/dev/null
conv "Azienda Notifiche" "" "azienda@staging.invalid" "40" "Logistica" "Milano|Bologna" >/dev/null
sleep 2
check "invio valido: accettato" accettato "$(conv_ok)"
check "invio valido: due email intercettate" 2 "$(mailpit_count)"
curl -s "http://localhost:18025/api/v1/messages?limit=10" > "$STATE/mails.json"
check "riepilogo interno al destinatario segnaposto" 1 "$(grep -o 'convenzioni-interno@staging.invalid' "$STATE/mails.json" | wc -l | awk '{print ($1>=1)?1:0}')"
check "conferma all'email dell'azienda" 1 "$(grep -o 'azienda@staging.invalid' "$STATE/mails.json" | wc -l | awk '{print ($1>=1)?1:0}')"
ID2=$(hphp -r '$j=json_decode(stream_get_contents(STDIN),true); foreach($j["messages"] as $m){ foreach($m["To"] as $t){ if($t["Address"]==="azienda@staging.invalid") echo $m["ID"]; } }' < "$STATE/mails.json")
curl -s "http://localhost:18025/api/v1/message/$ID2" > "$STATE/mail2.json"
contains "conferma: Reply-To segnaposto" "convenzioni-risposte@staging.invalid" "$(hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo json_encode($j["ReplyTo"]);' < "$STATE/mail2.json")"
contains "conferma: la richiesta non attiva una convenzione" "non attiva una convenzione" "$(hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["Text"];' < "$STATE/mail2.json")"
ID1=$(hphp -r '$j=json_decode(stream_get_contents(STDIN),true); foreach($j["messages"] as $m){ foreach($m["To"] as $t){ if($t["Address"]==="convenzioni-interno@staging.invalid") echo $m["ID"]; } }' < "$STATE/mails.json")
contains "riepilogo interno: stato «Da valutare»" "Da valutare" "$(curl -s "http://localhost:18025/api/v1/message/$ID1" | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["Text"];')"
curl -s -X DELETE http://localhost:18025/api/v1/messages >/dev/null
B=$(conv_count); conv "Azienda" "ftp://x.example" "azienda@staging.invalid" "0" "Logistica" "Napoli" >/dev/null; sleep 2
check "invio non valido con notifiche attive: nessun salvataggio, nessuna email" "rifiutato $B 0" "$(conv_ok) $(conv_count) $(mailpit_count)"
check "disattivazione delle notifiche" 0 "$(docker exec -u www-data -e TMK_CONV_DISABLE=1 "$WPC" php /tmp/wp-cli.phar --path=/var/www/html --user=stg-admin eval-file /tmp/attiva-notifiche.php >/dev/null 2>&1; echo $?)"
curl -s -X DELETE http://localhost:18025/api/v1/messages >/dev/null
conv "Azienda Senza Notifiche" "" "azienda@staging.invalid" "40" "Logistica" "Milano" >/dev/null; sleep 2
check "dopo la disattivazione: salvato, nessuna email" "accettato 0" "$(conv_ok) $(mailpit_count)"
summary
