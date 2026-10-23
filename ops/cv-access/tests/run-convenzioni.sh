#!/usr/bin/env bash
# Prove 1.23.0 da zero: ripristino del backup di produzione nello staging isolato, codice di
# produzione (fase "old": la prova deve rilevare l'identificazione Brevo), poi codice nuovo.
# Uso: run-convenzioni.sh <sha nuovo> <backup> [sha produzione]
set -uo pipefail
T=/root/tmk-staging/tests; NEW=${1:?sha nuovo}; B=${2:?backup}; OLD=${3:-25dd8aa}
export LOG=$T/results-conv.log
rm -f "$LOG" "$T/state/counters"
# SHA completo del commit di produzione, risolto nel checkout del commit nuovo (che ne contiene la
# storia). --verify -q: in caso di errore non stampa nulla (rev-parse semplice ristampa l'argomento).
/root/tmk-staging/stg.sh checkout "$NEW" >/dev/null
OLD=$(git -C /root/tmk-staging/src/"$NEW" rev-parse --verify -q "$OLD^{commit}" || echo "$OLD")
/root/tmk-staging/stg.sh checkout "$OLD" >/dev/null
bash "$T/setup.sh" "$OLD" "$B" > "$T/setup-conv.log" 2>&1 || { echo "setup fallito"; tail -20 "$T/setup-conv.log"; exit 1; }
{ echo "Prove 1.23.0 sullo staging isolato tmkstg — $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "Commit in prova: $NEW (produzione: $OLD). Backup ripristinato: $B"
  echo "Solo dati fittizi; posta intercettata da Mailpit; nessuna uscita verso Internet; Microsoft Graph simulato."
  grep -E '^(prova di ripristino|dati reali rimossi)' "$T/setup-conv.log"; } > "$T/header-conv.txt"
bash "$T/60-convenzioni-email-registro.sh" old
/root/tmk-staging/stg.sh up "$NEW" >/dev/null 2>&1
for i in $(seq 1 60); do [ "$(curl -s -o /dev/null -w %{http_code} http://localhost:18080/wp-includes/js/wp-emoji-loader.min.js)" = 200 ] && break; sleep 3; done
check_ver=$(/root/tmk-staging/stg.sh wp plugin get tmk-design-system --field=version 2>/dev/null </dev/null)
echo "   versione del plugin avviata: $check_ver" | tee -a "$LOG"
bash "$T/60-convenzioni-email-registro.sh" new
read -r P F < "$T/state/counters"
{ cat "$T/header-conv.txt"; echo; cat "$LOG"; echo "== RISULTATO FINALE: $P superati, $F falliti"; } > "$T/report-conv-$NEW.log"
echo "== RISULTATO FINALE: $P superati, $F falliti  ($T/report-conv-$NEW.log)"
