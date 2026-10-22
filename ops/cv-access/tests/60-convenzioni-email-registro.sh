#!/usr/bin/env bash
# Prove 1.23.0 sullo staging isolato: tracciamento Brevo al login, notifiche convenzioni (mittente .it
# solo per quel modulo), limiti, ID lato server, sincronizzazione del registro con Graph simulato.
# Uso: 60-convenzioni-email-registro.sh old   (con il codice 1.22.0 avviato: verifica che la prova
#                                              rilevi davvero l'identificazione Brevo)
#      60-convenzioni-email-registro.sh new   (con il codice nuovo)
. "$(dirname "$0")/lib.sh"
load_counters
PHASE=${1:?old|new}
ADMIN_EMAIL=stg-admin@staging.invalid
HTTPLOG=/tmp/tmk-staging-http.log

brevo_seed(){ # tracciamento "marketing automation" attivo con una chiave fittizia (lo staging non esce su Internet)
	wpe 'update_option( "sib_main_option", array( "ma_key" => "stgfakemakey00000000000000" ) ); $h = (array) get_option( "sib_home_option", array() ); $h["activate_ma"] = "yes"; $h["activate_email"] = "no"; update_option( "sib_home_option", $h );' >/dev/null
}
brevo_calls(){ cx "if [ -f $HTTPLOG ]; then grep -c 'in-automate.brevo.com' $HTTPLOG; else echo 0; fi"; }
front_as(){ # jar -> HTML della home vista con quella sessione
	curl -s -b "$1" "$URL/"
}

if [ "$PHASE" = old ]; then
	section "21. Brevo con il codice 1.22.0 (la prova deve vedere l'identificazione)"
	brevo_seed; cx "rm -f $HTTPLOG"
	cx 'cd /var/www/html/wp-content/plugins/mailin && find . -type f -name "*.php" | sort | xargs sha256sum | sha256sum | cut -c1-16' > "$STATE/mailin.sha"
	check "login amministratore" 302 "$(login stg-admin "$ADMIN_PASS" "$STATE/jar-old")"
	check "1.22.0: il login chiama l'identificazione Brevo" 1 "$( [ "$(brevo_calls)" -ge 1 ] && echo 1 || echo 0)"
	contains "1.22.0: la home vista da collegato porta l'email a Brevo" "$ADMIN_EMAIL" "$(front_as "$STATE/jar-old" | grep -o '"email_id":"[^"]*"' | sed 's/\\//g')"
	summary; exit 0
fi

section "22. Brevo con il codice nuovo"
brevo_seed; cx "rm -f $HTTPLOG"
check "login amministratore" 302 "$(login stg-admin "$ADMIN_PASS" "$STATE/jar-adm")"
check "il login non chiama più l'identificazione Brevo" 0 "$(brevo_calls)"
check "login utente semplice" 302 "$(login stg-sub "$SUB_PASS" "$STATE/jar-sub")"
check "nessuna identificazione neanche per l'utente semplice" 0 "$(brevo_calls)"
H=$(front_as "$STATE/jar-adm")
lacks "home vista da collegato: nessuna email nello script Brevo" "$ADMIN_EMAIL" "$(grep -o '"email_id":"[^"]*"' <<<"$H" | sed 's/\\//g')"
lacks "home vista da collegato: script di tracciamento assente" "sdk-loader.js" "$H"
contains "home anonima: tracciamento anonimo invariato" "sdk-loader.js" "$(curl -s "$URL/")"
lacks "home anonima: nessuna email" '"email_id":"[^"n]' "$(curl -s "$URL/")"
check "callback Brevo ancora registrato (non modificato nel plugin)" 1 "$(wpe 'global $wp_filter; $n=0; foreach ( $wp_filter["wp_login"]->callbacks as $cbs ) { foreach ( $cbs as $cb ) { if ( is_array( $cb["function"] ) && "sib_wp_login_identify" === $cb["function"][1] ) { $n++; } } } echo $n;')"
check "file del plugin Brevo invariati" "$(cat "$STATE/mailin.sha" 2>/dev/null || echo assente)" "$(cx 'cd /var/www/html/wp-content/plugins/mailin && find . -type f -name "*.php" | sort | xargs sha256sum | sha256sum | cut -c1-16')"
wpe 'update_option( "sib_main_option", array() );' >/dev/null

section "23. Convenzioni: attivazione notifiche con gli indirizzi confermati"
docker cp /root/tmk-staging/ops/attiva-notifiche.php "$WPC:/tmp/attiva-notifiche.php" >/dev/null
ACT(){ docker exec -u www-data -e TMK_CONV_TO=info@temakinho.it -e TMK_CONV_REPLY_TO=info@temakinho.it "$WPC" php /tmp/wp-cli.phar --path=/var/www/html --user=stg-admin eval-file /tmp/attiva-notifiche.php "$@" 2>&1; }
contains "attivazione" "Notifiche attivate" "$(ACT)"
contains "seconda esecuzione: nessuna modifica" "Nessuna modifica" "$(ACT)"
check "azioni: archivio per primo, poi le due email" "save-to-database,email,email2" "$(wpe '$d=json_decode(get_post_meta(21226,"_elementor_data",true),true); $f=null; $w=function($e)use(&$w,&$f){foreach($e as $x){if(($x["id"]??"")==="9fc0788")$f=$x; if(!empty($x["elements"]))$w($x["elements"]);}}; $w($d); echo implode(",",$f["settings"]["submit_actions"]);')"

mp_list(){ curl -s "http://localhost:18025/api/v1/messages?limit=50"; }
mp_id_to(){ mp_list | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); foreach($j["messages"] as $m){ foreach($m["To"] as $t){ if(strcasecmp($t["Address"],$argv[1])===0 && stripos($m["Subject"],$argv[2])!==false){ echo $m["ID"]; exit; } } }' "$1" "$2"; }
mp_get(){ curl -s "http://localhost:18025/api/v1/message/$2" | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); $f=$argv[1]; if($f==="From") echo $j["From"]["Address"]; elseif($f==="ReplyTo") echo implode(",",array_map(function($a){return $a["Address"];},$j["ReplyTo"]??[])); elseif($f==="To") echo implode(",",array_map(function($a){return $a["Address"];},$j["To"]??[])); else echo $j[$f]??"";' "$1"; }
mp_hdrs(){ curl -s "http://localhost:18025/api/v1/message/$1/headers" | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo implode(" | ", array_keys($j));'; }
mp_hdr(){ curl -s "http://localhost:18025/api/v1/message/$1/headers" | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); foreach($j as $k=>$v){ if(strcasecmp($k,$argv[1])===0) echo implode("",$v); }' "$2"; }
mp_clear(){ curl -s -X DELETE http://localhost:18025/api/v1/messages >/dev/null; }
last_sid(){ echo "select max(id) from wp_e_submissions where element_id='$CONV_FORM';" | sql; }
sval(){ echo "select value from wp_e_submissions_values where submission_id=$1 and \`key\`='$2';" | sql; }

section "24. Convenzioni: invio valido, due notifiche"
mp_clear
conv "TEST staging – Azienda Uno" "https://www.azienda-uno.example" "referente@staging.invalid" "120" "Logistica" "Milano|Roma" >/dev/null; sleep 2
check "accettato" accettato "$(conv_ok)"
S1=$(last_sid); REQ=$(sval "$S1" id_richiesta)
check "ID richiesta generato dal server (non quello del browser)" 1 "$(grep -Ec '^CONV-[0-9]{8}-[0-9A-F]{8}$' <<<"$REQ")"
check "due email intercettate" 2 "$(mailpit_count)"
I1=$(mp_id_to info@temakinho.it "n. $S1"); C1=$(mp_id_to referente@staging.invalid "ricevuta")
check_ne "riepilogo interno a info@temakinho.it con il Submission ID nell'oggetto" "" "$I1"
check "riepilogo: mittente .it" info@temakinho.it "$(mp_get From "$I1")"
check "riepilogo: Reply-To = email del referente" referente@staging.invalid "$(mp_get ReplyTo "$I1")"
TXT=$(mp_get Text "$I1")
contains "riepilogo: Submission ID nel testo" "Submission ID (archivio WordPress): $S1" "$TXT"
contains "riepilogo: collegamento diretto all'invio nell'archivio riservato" "wp-admin/admin.php?page=e-form-submissions#/$S1" "$TXT"
contains "riepilogo: ID richiesta uguale a quello salvato" "ID richiesta: $REQ" "$TXT"
contains "riepilogo: città" "Città di interesse: Milano, Roma" "$TXT"
contains "riepilogo: stato Da valutare" "Da valutare" "$TXT"
check_ne "conferma all'indirizzo inserito dall'azienda" "" "$C1"
check "conferma: mittente .it" info@temakinho.it "$(mp_get From "$C1")"
check "conferma: Reply-To info@temakinho.it" info@temakinho.it "$(mp_get ReplyTo "$C1")"
TXT2=$(mp_get Text "$C1")
contains "conferma: attesta la ricezione senza attivare la convenzione" "non attiva una convenzione" "$TXT2"
lacks "conferma: nessun link all'archivio riservato" "wp-admin" "$TXT2"
lacks "conferma: nessun Submission ID interno" "%%TMK" "$TXT2"
lacks "riepilogo: intestazione interna rimossa" "X-Tmk-Sender" "$(mp_hdrs "$I1")"
lacks "conferma: intestazione interna rimossa" "X-Tmk-Sender" "$(mp_hdrs "$C1")"
contains "formato testo semplice" "text/plain" "$(mp_hdr "$I1" Content-Type)"

section "25. Le altre email del sito restano col mittente .com"
mp_clear
curl -s -o /dev/null -u "$BASIC_USER:$BASIC_PASS" --data-urlencode "user_login=stg-sub" -d "wp-submit=Ottieni+una+nuova+password" "$URL/wp-login.php?action=lostpassword"; sleep 2
RP=$(mp_list | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["messages"][0]["ID"]??"";')
check "recupero password: mittente invariato" info@temakinho.com "$(mp_get From "$RP")"
lacks "recupero password: nessuna intestazione interna" "X-Tmk-Sender" "$(mp_hdrs "$RP")"
mp_clear
mkpdf cv-conv.pdf "CV fittizio convenzioni"
apply "$T/files/cv-conv.pdf" Prova Convenzioni candidato@staging.invalid cv.pdf >/dev/null; sleep 2
NA=$(mp_list | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["messages"][0]["ID"]??"";')
check "notifica candidatura: mittente invariato" info@temakinho.com "$(mp_get From "$NA")"
wpe 'wp_mail( "qualcuno@staging.invalid", "Prova generica", "corpo", array( "From: Altro <info@temakinho.it>" ) );' >/dev/null; sleep 1
GEN=$(mp_id_to qualcuno@staging.invalid "Prova generica")
check "email generica che chiede il .it senza passare dal modulo: forzata al .com" info@temakinho.com "$(mp_get From "$GEN")"
wpe 'wp_mail( "qualcuno@staging.invalid", "Prova intestazione", "corpo", array( "X-TMK-Sender: 0123456789abcdef01234567" ) );' >/dev/null; sleep 1
GH=$(mp_id_to qualcuno@staging.invalid "Prova intestazione")
check "marcatura inventata da altro codice: rimossa" "" "$(mp_hdr "$GH" X-Tmk-Sender)"
check "marcatura inventata da altro codice: mittente resta .com" info@temakinho.com "$(mp_get From "$GH")"

section "26. Convenzioni: limiti per indirizzo"
mp_clear
for i in 1 2 3; do conv "TEST staging – Limite $i" "" "limite@staging.invalid" "10" "Servizi" "Bologna" >/dev/null; done
check "tre invii dallo stesso indirizzo accettati" accettato "$(conv_ok)"
B=$(conv_count); conv "TEST staging – Limite 4" "" "limite@staging.invalid" "10" "Servizi" "Bologna" >/dev/null; sleep 2
check "quarto invio nelle 24 ore: rifiutato, non salvato" "rifiutato $B" "$(conv_ok) $(conv_count)"
contains "messaggio comprensibile" "ultime 24 ore" "$(hphp -r 'echo json_encode(json_decode(stream_get_contents(STDIN)), JSON_UNESCAPED_UNICODE);' < "$STATE/conv.json")"
check "email inviate: solo per i tre accettati (6)" 6 "$(mailpit_count)"
B=$(conv_count); conv "TEST staging – Altro" "" "altro@staging.invalid" "10" "Servizi" "Rimini" >/dev/null
check "un altro indirizzo resta libero" "accettato $((B+1))" "$(conv_ok) $(conv_count)"
B=$(conv_count); mp_clear; conv "Azienda" "ftp://x.example" "nonvalido@staging.invalid" "0" "Logistica" "Napoli" >/dev/null; sleep 1
check "invio non valido: né salvataggio né email" "rifiutato $B 0" "$(conv_ok) $(conv_count) $(mailpit_count)"
wpe 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"_transient%tmk_conv_rl_%\"");' >/dev/null

section "27. Registro manuale (non configurato): nessuna coda, nessun avviso"
check "nessuna riga in coda senza configurazione" 0 "$(echo "select count(*) from wp_tmk_conv_sync;" | sql)"
contains "status: registro manuale" "registro manuale" "$(wp tmk-conv-registry status)"
contains "nessuna richiesta esterna senza configurazione" "0" "$(cx "grep -c graph $HTTPLOG 2>/dev/null || echo 0")"
check "passaggio orario senza configurazione: nessuna coda" 0 "$(wp tmk-conv-registry run >/dev/null; echo "select count(*) from wp_tmk_conv_sync;" | sql)"
DASH=$(curl -s -u "$BASIC_USER:$BASIC_PASS" -b "$STATE/jar-adm" -w '\n%{http_code}' "$URL/wp-admin/index.php")
check "bacheca amministratore caricata" 200 "$(tail -1 <<<"$DASH")"
lacks "bacheca amministratore: nessun avviso del registro" "Registro convenzioni" "$DASH"

section "28. Registro: Microsoft Graph simulato"
docker cp /root/tmk-staging/ops/mock-graph.php "$WPC:/tmp/mock-graph.php" >/dev/null
cx 'rm -rf /tmp/mockgraph; pkill -f "php -S 127.0.0.1:18999" || true'
docker exec -d -u www-data "$WPC" php -S 127.0.0.1:18999 /tmp/mock-graph.php; sleep 1
MS(){ cx 'cat /tmp/mockgraph/state.json'; }
MCELL(){ MS | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); $v=$j["rows"][$argv[1]][$argv[2]]??null; echo is_string($v)?$v:json_encode($v);' "$1" "$2"; }
MCOUNT(){ MS | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo count($j["rows"]);'; }
echo -n "mock-secret" | docker exec -i -u www-data "$WPC" php /tmp/wp-cli.phar --path=/var/www/html tmk-conv-registry configure --driver=msgraph --tenant=mock-tenant --client-id=mock-client --drive-id=mock-drive --item-id=mock-item --graph-base=http://127.0.0.1:18999/v1.0 --login-base=http://127.0.0.1:18999/login --secret-stdin >/dev/null 2>&1
contains "configurazione completa" "completa (msgraph" "$(wp tmk-conv-registry status)"
contains "test di connessione senza scritture" "nessuna scrittura" "$(wp tmk-conv-registry test)"
check "il test non ha scritto nulla" 0 "$(cx 'grep -cE "^(POST|PATCH) /v1" /tmp/mockgraph/requests.log || true')"
N=$(echo "select count(*) from wp_e_submissions where element_id='$CONV_FORM' and status<>'trash';" | sql)
wp tmk-conv-registry run >/dev/null
check "configurato: il primo passaggio recupera e sincronizza tutti gli invii dell'archivio" "$N 0" "$(echo "select count(*) from wp_tmk_conv_sync where status='done';" | sql) $(echo "select count(*) from wp_tmk_conv_sync where status<>'done';" | sql)"
check "righe nel registro = invii (la riga vuota iniziale è stata usata)" "$N" "$(MCOUNT)"
check "prima riga: Submission ID numerico" "$S1" "$(MCELL 0 0)"
check "prima riga: ID richiesta" "$REQ" "$(MCELL 0 1)"
check "prima riga: azienda" "TEST staging – Azienda Uno" "$(MCELL 0 3)"
check "prima riga: dipendenti numerico" "120" "$(MS | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo is_int($j["rows"][0][6])?$j["rows"][0][6]:"testo";')"
check "prima riga: città insieme" "Milano, Roma" "$(MCELL 0 8)"
EXP=$(wpe '$s=$GLOBALS["wpdb"]->get_var("select created_at_gmt from wp_e_submissions where id='"$S1"'"); $ts=strtotime($s." UTC"); $d=(new DateTime("@".$ts))->setTimezone(new DateTimeZone("Europe/Rome")); echo round(25569+($ts+$d->getOffset())/86400,8);')
check "prima riga: data come numero Excel in ora italiana" "$EXP" "$(MCELL 0 2)"
contains "prima riga: formule delle città conservate" 'SUBSTITUTE(R1C' "$(MCELL 0 9)"
check "riga aggiunta: formula dello Stato ripristinata" '=IF(RC1="","",IF(RC19="","Da valutare",RC19))' "$(MCELL 1 19)"
contains "riga aggiunta: formula città ripristinata" 'SUBSTITUTE(R1C' "$(MCELL 1 14)"
check "riga aggiunta: Stato (manuale) vuoto" "" "$(MCELL 1 18)"
check "riga aggiunta: Note interne vuote" "" "$(MCELL 1 20)"

section "29. Registro: nessun duplicato, stato e note del team preservati"
cx 'php -r '"'"'$f="/tmp/mockgraph/state.json"; $j=json_decode(file_get_contents($f),true); $j["rows"][0][18]="In valutazione"; $j["rows"][0][20]="Chiamato il referente"; file_put_contents($f,json_encode($j,JSON_UNESCAPED_UNICODE));'"'"''
C0=$(MCOUNT)
wp tmk-conv-registry retry --id="$S1" >/dev/null; wp tmk-conv-registry run >/dev/null
check "ritentato: nessuna riga in più" "$C0" "$(MCOUNT)"
contains "esito: già presente" "gia' presente" "$(echo "select last_error from wp_tmk_conv_sync where submission_id=$S1;" | sql)"
check "stato del team invariato" "In valutazione" "$(MCELL 0 18)"
check "note del team invariate" "Chiamato il referente" "$(MCELL 0 20)"
echo "delete from wp_tmk_conv_sync where submission_id=$S1;" | sql
wp tmk-conv-registry run >/dev/null
check "recupero: invio mancante dalla coda rimesso e non duplicato" "done $C0" "$(echo "select status from wp_tmk_conv_sync where submission_id=$S1;" | sql) $(MCOUNT)"

section "30. Registro: errori, nuovi tentativi, token scaduto, testo che sembra una formula"
conv "=HYPERLINK(\"http://x.example\",\"clic\") Srl" "" "formula@staging.invalid" "5" "Test" "Lione" >/dev/null
SF=$(last_sid)
cx 'touch /tmp/mockgraph/fail-3'
wp tmk-conv-registry run >/dev/null
check "servizio non disponibile: errore registrato, un tentativo" "error 1" "$(echo "select status, attempts from wp_tmk_conv_sync where submission_id=$SF;" | sql | tr '\t' ' ')"
contains "errore leggibile" "HTTP 503" "$(echo "select last_error from wp_tmk_conv_sync where submission_id=$SF;" | sql)"
check "nuovo tentativo programmato più avanti (non subito)" 1 "$(echo "select next_try > utc_timestamp() from wp_tmk_conv_sync where submission_id=$SF;" | sql)"
cx 'rm -f /tmp/mockgraph/fail-*'
wp tmk-conv-registry run >/dev/null
check "prima della scadenza non riprova" error "$(echo "select status from wp_tmk_conv_sync where submission_id=$SF;" | sql)"
echo "update wp_tmk_conv_sync set next_try=utc_timestamp() - interval 1 second where submission_id=$SF;" | sql
wp tmk-conv-registry run >/dev/null
check "alla scadenza: sincronizzato" done "$(echo "select status from wp_tmk_conv_sync where submission_id=$SF;" | sql)"
contains "testo che inizia con = scritto come testo" "'=HYPERLINK" "$(MS)"
cx 'touch /tmp/mockgraph/expire; : > /tmp/mockgraph/requests.log'
conv "TEST staging – Token" "" "token@staging.invalid" "7" "Test" "Rimini" >/dev/null; ST=$(last_sid)
wp cron event run tmk_conv_sync_run >/dev/null 2>&1; wp tmk-conv-registry run >/dev/null
check "token scaduto: nuovo token e sincronizzato" "done 1" "$(echo "select status from wp_tmk_conv_sync where submission_id=$ST;" | sql) $(cx 'grep -c "oauth2/v2.0/token" /tmp/mockgraph/requests.log')"
cx 'touch /tmp/mockgraph/autofill; : > /tmp/mockgraph/requests.log'
conv "TEST staging – Excel estende" "" "estende@staging.invalid" "9" "Test" "Reggio Calabria" >/dev/null
wp tmk-conv-registry run >/dev/null
check "se Excel estende le formule, il connettore non le riscrive" 0 "$(cx 'grep -c "^PATCH" /tmp/mockgraph/requests.log || true')"
check "nessun invio fallito o in sospeso" 0 "$(echo "select count(*) from wp_tmk_conv_sync where status<>'done';" | sql)"
check "nessun Submission ID ripetuto nel registro" 0 "$(MS | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); $k=array_column($j["rows"],0); echo count($k)-count(array_unique($k));')"
TR=$(echo "select id from wp_e_submissions where element_id='$CONV_FORM' order by id limit 1;" | sql)
echo "update wp_e_submissions set status='trash' where id=$TR; delete from wp_tmk_conv_sync where submission_id=$TR;" | sql
wp tmk-conv-registry run >/dev/null
check "invio nel cestino non rimesso in coda" 0 "$(echo "select count(*) from wp_tmk_conv_sync where submission_id=$TR;" | sql)"
cx 'pkill -f "php -S 127.0.0.1:18999" || true'
B=$(conv_count); conv "TEST staging – Registro spento" "" "spento@staging.invalid" "3" "Test" "Milano" >/dev/null
check "registro irraggiungibile: il modulo salva comunque" "accettato $((B+1))" "$(conv_ok) $(conv_count)"
wpe 'delete_option( "tmk_conv_registry" );' >/dev/null
summary
