# Stato lavori — CV riservati e convenzioni (nota di ripresa)

Nota breve per riprendere il lavoro dopo una pausa. Niente segreti, niente dati personali.
Procedure: `ops/cv-access/RUNBOOK.md`. Prove: `ops/cv-access/tests/` (staging) e
`ops/cv-access/prod-viewer-session-test.sh` (produzione, senza toccare la password dell'account).

## 23/10/2026 — 1.23.2: convenzioni su info@temakinho.com, notifiche attive

Correzione definitiva di Temakinho: l'indirizzo è **info@temakinho.com**, non il `.it`. Ogni
attività per autenticare `temakinho.it` è interrotta (nessun DNS da aggiungere).

| Voce | Stato |
|---|---|
| Codice | 1.23.2: PR #5, merge `d8f5ae9`, deploy 09:39 UTC; `tmk_conv_sender()` = info@temakinho.com |
| Prove staging | 87/87 sul commit `4a81bbe`, da zero (backup `20261022T143813Z-pre-convenzioni` → 1.22.0 → 1.23.2) |
| Backup | `20261023T093343Z-pre-1.23.2` (cifrato age, copia sul Mac, decifratura di database e archivio CV verificata) |
| Brevo / DNS | `temakinho.com` autenticato: `brevo-code`, `brevo1/brevo2._domainkey`, `_dmarc` tutti `status: true`, e presenti nei DNS pubblici; SPF con `include:spf.brevo.com`. Nessun record toccato. Il dominio `temakinho.it` resta in Brevo non autenticato e inutilizzato (eliminabile) |
| Notifiche | **attive** dal 23/10 09:40 UTC: riepilogo a info@temakinho.com (Reply-To = email del referente), conferma all'azienda (Reply-To info@temakinho.com), mittente info@temakinho.com per entrambe. Copia di `_elementor_data` precedente in `/root/tmk-ops/secrets/` |
| Prova in produzione | browser reale sulla pagina pubblica, referente info@temakinho.com: salvata come Submission **#16** (ID richiesta generato dal server), azioni email/email2 «success», Brevo: entrambe `requests` → **`delivered`**, mittente info@temakinho.com. Invio #16 lasciato in archivio per aprire il link del riepilogo; si rimuove con `TMK_CONV_SID=16 … prova-invio.php elimina` |
| Account CV | prova di produzione 48/48 dopo il deploy; password non toccata (impronta dell'hash invariata) |
| Registro | resta **manuale** (procedura aggiornata in `ops/convenzioni/README.md`) |
| Privacy | bozza v1.1 (indirizzo .com, registro manuale): `Privacy_convenzioni_testo_da_approvare_v1.1.md` nella cartella outputs Codex. **Non approvata** |
| Chiave Brevo esposta | **ancora in uso** (impronta `cf585de054`): serve una chiave nuova creata dall'interfaccia Brevo. Procedura pronta, la chiave passa dagli appunti senza essere stampata: `ops/brevo/carica-nuova-chiave.sh verifica` → `applica` (email di prova a info@ con esito Brevo) → revoca in Brevo → `revoca-ok` |

## 22/10/2026 pomeriggio — 1.23.0 / 1.23.1 (convenzioni, email, registro, Brevo)

Nota: le indicazioni `.it` di questa sezione sono superate dalla correzione del 23/10.

| Voce | Stato |
|---|---|
| Codice | 1.23.0: PR #3, merge `0b91d96` (deploy 15:09 UTC). 1.23.1: registro manuale silenzioso (nessuna coda e nessun avviso finché non è configurato) |
| Prove staging | 1.23.1: 87/87 sul commit `7e7114c`; 1.23.0: 84/84 sul commit `b27d331`, da zero (backup `20261022T143813Z-pre-convenzioni` → 1.22.0 → 1.23.0); browser reale desktop 1440 + iPhone 13: 14/14 |
| Backup | `20261022T143813Z-pre-convenzioni` (cifrato age, copia sul Mac, decifratura verificata) |
| Login → Brevo | `inc/brevo-login-tracking.php`: tolta l'identificazione (email + username) al login e l'email degli utenti collegati nello script di tracciamento; file del plugin Brevo non modificati |
| Convenzioni — codice | ID richiesta generato dal server; Submission ID e link diretto `e-form-submissions#/<ID>` nel riepilogo; mittente `info@temakinho.it` solo per le due email del modulo (le altre email restano `info@temakinho.com`); limiti anti-abuso (3/24 h per indirizzo, 10/h per IP, 50/h in tutto) |
| Convenzioni — notifiche | script `ops/convenzioni/attiva-notifiche.php` pronto (interno e mittente `info@temakinho.it`, Reply-To conferma `info@temakinho.it`, Reply-To interno = email del referente). **Non attivate in produzione**: Brevo rifiuta il mittente `info@temakinho.it` finché il dominio `.it` non è autenticato (prova del 22/10: «sender … is not valid»). Dominio aggiunto in Brevo; mancano i 4 record DNS su register.it |
| Pagina 21226 | **pubblicata** il 22/10 alle 13:56 UTC da un amministratore dall'editor; Carlo ha deciso di lasciarla online. Testo privacy non ancora approvato |
| Privacy | testo v1.0 da approvare: `Privacy_convenzioni_testo_da_approvare_v1.0.md` (cartella outputs Codex) |
| Registro automatico | `inc/conventions-registry.php`: coda `wp_tmk_conv_sync` (chiave Submission ID), invio asincrono, tentativi crescenti, recupero orario, nessun duplicato, Stato/Note mai toccati; driver Microsoft Graph (Excel Online, tabella `tblRichieste`) provato con Graph simulato. **Non configurato: per scelta di Carlo il registro resta manuale per ora.** Nessuna coda finché non si configura; alla configurazione il primo passaggio recupera tutto l'archivio senza duplicati (`wp tmk-conv-registry configure|test|run|status|retry`) |
| Pulizia | rimossi allegati 21236/21237/21250, candidature di prova 21238/21251, 14 invii di prova del modulo convenzioni (script `ops/convenzioni/pulizia-dati-prova.php`, elenco chiuso di ID); nessun contatto Brevo creato dalle prove |
| Chiave Brevo esposta | identificata (impronta `cf585de0…`, chiave API v3 usata da WP Mail SMTP e plugin Brevo, copiata anche nel sito Local e nei dump locali); nessun altro progetto del server la usa. Sostituzione da fare dall'interfaccia Brevo (l'API non crea né revoca chiavi) |

## 22/10/2026 mattina — 1.22.0 rilasciato

| Voce | Stato |
|---|---|
| Codice in produzione | `main` @ merge `16c9b11` (contenuto = `86f555d`), PR #1, plugin **1.22.0** |
| Deploy | Dokploy automatico 12:28 UTC; webroot anonima riusata; volume `temakinho_private` creato e montato; sentinella presente |
| Prove staging | 324/324 sul commit `86f555d`, da zero (ripristino backup → 1.21.3 → 1.22.0 → rollback → 1.22.0) |
| Migrazione CV | 10 CV + 40 anteprime copiati e verificati (manifesti in `/var/www/tmk-private/manifest/`), riferimenti aggiornati, poi 50 copie pubbliche rimosse; 50 vecchi URL → 404 |
| Nuovi CV | vanno direttamente nell'archivio riservato, senza anteprime |
| Account | `consultazione-cv` / info@temakinho.com, ruolo `tmk_cv_viewer`, Basic Auth dedicata. Password WordPress: la imposta l'amministratore |
| Registro | rev. 2 manuale (chiave Submission ID, 2000 righe, controlli espliciti), 60/60 prove con LibreOffice |

## Aperti (fuori da questi rilasci)

- Modulo Contattaci in produzione: campi senza ID, nessun invio mai salvato.
- Limite upload PHP 2 MB contro i 5 MB dichiarati dal form candidature.
- Repository GitHub pubblico (DEPLOYMENT.md lo dava per privato).
- Export CSV di Elementor: le virgolette dentro un valore arrivano come `\"` nel registro (solo LibreOffice provato; Excel non disponibile).
