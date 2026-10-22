# Stato lavori — CV riservati e convenzioni (nota di ripresa)

Nota breve per riprendere il lavoro dopo una pausa. Niente segreti, niente dati personali.
Procedure operative: `ops/cv-access/RUNBOOK.md`.

## 22/10/2026

| Voce | Stato |
|---|---|
| Codice | branch `feature/cv-access`, plugin 1.22.0 (`inc/cv-storage.php`, `inc/cv-access.php`, `inc/conventions-form.php`) + volume `temakinho_private` in Compose/Dockerfile |
| Backup pre-rilascio | 20261022T104710Z: dump + 4 volumi (anche la webroot anonima) + config Dokploy, cifrato age, copia esterna sul Mac verificata per decifratura, ripristino provato nello staging |
| Staging | progetto Compose `tmkstg` sul server, reti interne senza uscita, Mailpit, solo 127.0.0.1 (tunnel SSH); dati reali rimossi dopo la prova di ripristino |
| Prove | `ops/cv-access/tests/run-all.sh` (da zero: ripristino → 1.21.3 → 1.22.0 → rollback → 1.22.0) |
| Rilascio produzione | da fare |
| Migrazione CV | da fare |
| Account consultazione | da fare |
| Convenzioni | validazione server pronta; pagina in bozza; notifiche non attive (mancano destinatario e Reply-To) |
| Registro Excel | il file scaricato è una revisione precedente al log da 56 prove: da sistemare |
