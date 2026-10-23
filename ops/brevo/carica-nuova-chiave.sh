#!/usr/bin/env bash
# Sostituzione della chiave API Brevo di produzione, dal Mac. La nuova chiave si crea nell'interfaccia
# Brevo (l'API non crea né revoca chiavi), si copia negli appunti e non viene mai stampata: a schermo
# solo impronte sha256 (10 caratteri) e codici HTTP. Logica sul database: sostituisci-chiave.php.
#
#   carica-nuova-chiave.sh verifica   appunti → file riservato sul server; prova della nuova chiave, nessuna modifica
#   carica-nuova-chiave.sh applica    la mette in uso (WP Mail SMTP + plugin Brevo), email di prova a
#                                     info@temakinho.com con esito Brevo, svuota gli appunti
#   carica-nuova-chiave.sh revoca-ok  dopo la revoca in Brevo: la vecchia chiave deve rispondere 401
set -euo pipefail
H=root@62.171.151.1
C=temakinho-agyavu-wordpress-1
S=/root/tmk-ops/secrets
K=$S/brevo-new-key
mode=${1:-}
here=$(cd "$(dirname "$0")" && pwd)

# Esegue sostituisci-chiave.php nel container ($1 = verifica|applica) con la chiave del file riservato.
remote_replace(){
	scp -q "$here/sostituisci-chiave.php" "$H:/root/tmk-ops/sostituisci-chiave.php"
	ssh -o BatchMode=yes "$H" bash -s -- "$1" <<EOF
set -euo pipefail
umask 077
[ -s $K ] || { echo "Manca $K: eseguire prima «verifica»." >&2; exit 1; }
docker cp /root/tmk-ops/wp-cli.phar $C:/tmp/wp-cli.phar
docker cp /root/tmk-ops/sostituisci-chiave.php $C:/tmp/sostituisci-chiave.php
docker exec $C chmod 644 /tmp/sostituisci-chiave.php
docker exec -i -u www-data $C sh -c 'umask 077; cat > /tmp/brevo-new-key' < $K
trap 'docker exec $C rm -f /tmp/brevo-new-key' EXIT
docker exec -u www-data -e TMK_BREVO_NEW_KEY_FILE=/tmp/brevo-new-key $C php /tmp/wp-cli.phar --path=/var/www/html --user=1 eval-file /tmp/sostituisci-chiave.php "\$1"
[ "\$1" = applica ] || exit 0
B=$S/brevo-chiave-sostituita-\$(date -u +%Y%m%dT%H%M%SZ).bak.json
docker exec $C cat /tmp/brevo-new-key.bak.json > "\$B"
docker exec $C rm -f /tmp/brevo-new-key.bak.json
echo "copia delle opzioni precedenti (contiene la vecchia chiave, da eliminare dopo la revoca): \$B"
SUBJ="Temakinho – prova di invio dopo la sostituzione della chiave Brevo \$(date -u +%H%M%S)"
docker exec -u www-data $C php /tmp/wp-cli.phar --path=/var/www/html eval "echo wp_mail( 'info@temakinho.com', '\$SUBJ', 'Email di prova tecnica: nessuna azione richiesta.' ) ? 'wp_mail: accettata' : 'wp_mail: RIFIUTATA', PHP_EOL;"
sleep 20
/root/tmk-ops/brevo.sh GET "/v3/smtp/statistics/events?limit=20&days=1&email=info%40temakinho.com&sort=desc" </dev/null | tail -n +2 \
  | docker exec -i $C php -r '\$j = json_decode( stream_get_contents( STDIN ), true ); \$n = 0; foreach ( (array) ( \$j["events"] ?? array() ) as \$e ) { if ( \$e["subject"] === \$argv[1] ) { echo "Brevo: ", \$e["event"], " ", \$e["date"], PHP_EOL; \$n++; } } if ( ! \$n ) { echo "Brevo: nessun evento ancora, ricontrollare tra poco", PHP_EOL; }' "\$SUBJ"
EOF
}

case "$mode" in
verifica)
	key=$(pbpaste | tr -d '[:space:]')
	[[ $key =~ ^xkeysib-[A-Za-z0-9]{64}-[A-Za-z0-9]{16}$ ]] || { echo "Negli appunti non c'è una chiave API v3 Brevo (xkeysib-…)." >&2; exit 1; }
	printf %s "$key" | ssh -o BatchMode=yes "$H" "umask 077; cat > $K"
	unset key
	remote_replace verifica
	;;
applica)
	remote_replace applica
	ssh -o BatchMode=yes "$H" "shred -u $K 2>/dev/null || rm -f $K"
	printf '' | pbcopy
	echo "Appunti svuotati. Ora revocare la vecchia chiave in Brevo (SMTP & API › Chiavi API), poi: $0 revoca-ok"
	;;
revoca-ok)
	ssh -o BatchMode=yes "$H" bash -s <<EOF
set -euo pipefail
B=\$(ls -t $S/brevo-chiave-sostituita-*.bak.json 2>/dev/null | head -1)
[ -n "\$B" ] || { echo "Nessuna sostituzione registrata in $S." >&2; exit 1; }
docker exec -i -u www-data $C sh -c 'umask 077; cat > /tmp/brevo-old.json' < "\$B"
trap 'docker exec $C rm -f /tmp/brevo-old.json' EXIT
docker exec -u www-data $C php -r '
\$o = json_decode( file_get_contents( "/tmp/brevo-old.json" ), true );
foreach ( array( "WP Mail SMTP" => \$o["wp_mail_smtp"]["sendinblue"]["api_key"] ?? "", "plugin Brevo" => \$o["sib_api_key_v3"] ?? "" ) as \$n => \$k ) {
	if ( "" === \$k ) { continue; }
	\$c = curl_init( "https://api.brevo.com/v3/account" );
	curl_setopt_array( \$c, array( CURLOPT_RETURNTRANSFER => 1, CURLOPT_HTTPHEADER => array( "api-key: \$k", "accept: application/json" ), CURLOPT_TIMEOUT => 20 ) );
	curl_exec( \$c );
	\$h = curl_getinfo( \$c, CURLINFO_HTTP_CODE );
	echo "vecchia chiave (\$n, impronta ", substr( hash( "sha256", \$k ), 0, 10 ), "): HTTP \$h ", 401 === \$h ? "→ revocata" : "→ ANCORA VALIDA", PHP_EOL;
}'
/root/tmk-ops/brevo.sh GET /v3/account </dev/null | head -1 | sed 's/^/chiave in uso: /'
EOF
	;;
*) echo "uso: $0 verifica|applica|revoca-ok" >&2; exit 2 ;;
esac
