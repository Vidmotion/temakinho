# Stato lavori — CV riservati e convenzioni (nota di ripresa)

Nota breve per riprendere il lavoro dopo una pausa. Niente segreti, niente dati personali.
Procedure: `ops/cv-access/RUNBOOK.md`. Prove: `ops/cv-access/tests/` (staging) e
`ops/cv-access/prod-viewer-test.sh` (produzione).

## 22/10/2026 — rilasciato

| Voce | Stato |
|---|---|
| Codice in produzione | `main` @ merge `16c9b11` (contenuto = `86f555d`), PR #1, plugin **1.22.0** |
| Deploy | Dokploy automatico 12:28 UTC; webroot anonima riusata; volume `temakinho_private` creato e montato; sentinella presente |
| Prove staging | 324/324 sul commit `86f555d`, da zero (ripristino backup → 1.21.3 → 1.22.0 → rollback → 1.22.0) |
| Migrazione CV | 10 CV + 40 anteprime copiati e verificati (manifesti in `/var/www/tmk-private/manifest/`), riferimenti aggiornati, poi 50 copie pubbliche rimosse; 50 vecchi URL → 404 |
| Nuovi CV | vanno direttamente nell'archivio riservato, senza anteprime (provato in produzione con un CV fittizio) |
| Account | `consultazione-cv` / info@temakinho.com, ruolo `tmk_cv_viewer`, Basic Auth dedicata; 47/47 prove in produzione. Password WordPress: la imposta l'amministratore (quella delle prove viene sostituita con una casuale non conservata) |
| Backup | pre-rilascio e post-migrazione (con volume privato), cifrati age, copia sul Mac di Carlo, decifratura verificata, ripristino provato |
| Convenzioni | validazione server attiva sul modulo 9fc0788; pagina ancora in **bozza**; notifiche spente (mancano destinatario interno e Reply-To); revisione privacy in bozza da approvare |
| Registro | rev. 2 manuale (chiave Submission ID, 2000 righe, controlli espliciti), 60/60 prove con LibreOffice; sincronizzazione automatica: rimandata su indicazione di Carlo |

## Aperti (fuori da questo rilascio)

- Modulo Contattaci in produzione: campi senza ID, nessun invio mai salvato.
- Limite upload PHP 2 MB contro i 5 MB dichiarati dal form candidature.
- Repository GitHub pubblico (DEPLOYMENT.md lo dava per privato).
