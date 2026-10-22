#!/usr/bin/env bash
# Esegue l'intera suite da zero: ripristino del backup di produzione nello staging, dati fittizi,
# codice 1.21.3 -> codice nuovo -> rollback -> codice nuovo. Uso: run-all.sh <sha nuovo> <backup>
set -uo pipefail
T=/root/tmk-staging/tests; export NEW_SHA=${1:?sha nuovo}; B=${2:?backup}
export OLD_SHA=10e6649310022eb6d41b725c2268e7c91238742c
export LOG=$T/results.log
/root/tmk-staging/stg.sh checkout "$OLD_SHA" >/dev/null; /root/tmk-staging/stg.sh checkout "$NEW_SHA" >/dev/null
START=$(date -u +%Y-%m-%dT%H:%M:%SZ)
bash "$T/setup.sh" "$OLD_SHA" "$B" > "$T/setup.log" 2>&1 || { echo "setup fallito"; tail -20 "$T/setup.log"; exit 1; }
{ echo "Prove sullo staging isolato tmkstg — $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "Commit in prova: $NEW_SHA (rollback verso $OLD_SHA). Backup ripristinato: $B"
  echo "Solo dati fittizi; posta intercettata da Mailpit; nessuna uscita verso Internet."
  sed -n 's/^prova di ripristino/Prova di ripristino del backup/p; s/^dati reali rimossi/Dati reali rimossi dallo staging/p' "$T/setup.log"; } > "$T/header.txt"
bash "$T/00-seed-legacy.sh"
/root/tmk-staging/stg.sh up "$NEW_SHA" >/dev/null 2>&1
for i in $(seq 1 60); do [ "$(curl -s -o /dev/null -w %{http_code} http://localhost:18080/wp-includes/js/wp-emoji-loader.min.js)" = 200 ] && break; sleep 3; done
bash "$T/10-new-code.sh"; bash "$T/20-migration.sh"; bash "$T/30-rollback.sh"; bash "$T/40-conventions.sh"
SINCE=$START bash "$T/50-persistence.sh"
read -r P F < "$T/state/counters"
{ cat "$T/header.txt"; echo; cat "$LOG"; echo "== RISULTATO FINALE: $P superati, $F falliti"; } > "$T/report-$NEW_SHA.log"
echo "== RISULTATO FINALE: $P superati, $F falliti  ($T/report-$NEW_SHA.log)"
