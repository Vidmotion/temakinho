# Convenzioni aziendali — procedure operative

Pagina `/convenzioni-aziendali/` (ID 21226), modulo Elementor «Convenzioni aziendali» (elemento
`9fc0788`). Fonte persistente: Elementor › Submissions. Codice: `inc/conventions-form.php`,
`inc/conventions-registry.php` del plugin `tmk-design-system` (≥ 1.23.0). Nessun segreto qui.

WP-CLI in produzione: `/root/tmk-ops/wp.sh …` (vedi `ops/cv-access/RUNBOOK.md`).

## 1. Notifiche email

| | |
|---|---|
| Riepilogo interno | a `info@temakinho.com`, mittente `info@temakinho.com`, Reply-To = email del referente (campo `email` del modulo) |
| Conferma all'azienda | all'email del referente, mittente `info@temakinho.com`, Reply-To `info@temakinho.com` |

Indirizzi confermati da Temakinho il 23/10/2026 (`info@temakinho.com`; il `.it` indicato il 22/10 è
stato corretto e non va usato). Il mittente del modulo è fissato anche nel codice
(`tmk_conv_sender()`, filtro `tmk_conv_sender_email`).

Prerequisito: dominio `temakinho.com` autenticato in Brevo. Controllo:
`brevo.sh GET /v3/senders/domains/temakinho.com` → `"authenticated": true` e `status: true` sui
quattro record (verificato il 23/10/2026). I record esistono già nella zona DNS di `temakinho.com`
(`brevo-code` TXT, `brevo1._domainkey` / `brevo2._domainkey` CNAME, `_dmarc` TXT; SPF con
`include:spf.brevo.com`): **non** vanno aggiunti né modificati.

Attivazione (idempotente, copia di sicurezza di `_elementor_data`):

```bash
TMK_CONV_TO=info@temakinho.com TMK_CONV_REPLY_TO=info@temakinho.com \
  wp --user=1 eval-file attiva-notifiche.php [dry-run]
```

Disattivazione: `TMK_CONV_DISABLE=1 wp --user=1 eval-file attiva-notifiche.php`.

Attenzione: l'API di Brevo risponde 201 anche quando poi rifiuta il mittente; l'esito vero è
nell'evento transazionale (`/v3/smtp/statistics/events`): `requests` = accettata,
`delivered` = consegnata, `opened` = aperta, `error`/`blocked`/`hardBounce` = non consegnata.

## 2. Registro condiviso (oggi manuale)

Per scelta di Carlo il registro resta **manuale**: il team lo compila a partire da Elementor ›
Submissions o dal riepilogo interno. Finché il connettore non è configurato il sito non crea code,
non mostra avvisi e non invia dati al registro (1.23.1). Se in futuro si attiva la copia automatica,
il primo passaggio recupera tutto l'archivio senza duplicati (chiave Submission ID). Configurazione
(Microsoft 365 / Excel Online):

1. Nel tenant Microsoft 365 di Temakinho: registrazione di un'app (Entra ID) con permesso
   applicativo **Sites.Selected** (concesso dall'amministratore del tenant solo sul sito che contiene
   il file) e un client secret.
2. Caricare `Registro_richieste_convenzioni_OPERATIVO_rev2.xlsx` nel sito/raccolta scelti; ricavare
   `drive-id` e `item-id` del file.
3. Sul server:

```bash
printf %s "<client secret>" | wp tmk-conv-registry configure --driver=msgraph --tenant=<tenant-id> \
  --client-id=<app-id> --drive-id=<drive-id> --item-id=<item-id> --secret-stdin
wp tmk-conv-registry test      # legge intestazioni e chiavi, non scrive
wp tmk-conv-registry run       # recupera e sincronizza gli invii in coda
wp tmk-conv-registry status
```

`retry` rimette in coda gli invii in errore (`--id=N` ricontrolla anche un invio già sincronizzato:
la riga non viene duplicata). In alternativa alle opzioni, le variabili d'ambiente
`TMK_CONV_REGISTRY_*` (DRIVER, TENANT, CLIENT_ID, CLIENT_SECRET, DRIVE_ID, ITEM_ID, TABLE) prevalgono.

Il connettore scrive solo le colonne A:I (dal modulo); le colonne calcolate restano formule (se Excel
non le estende alla nuova riga le copia dalla prima riga), «Stato (manuale)» e «Note interne» non
vengono mai scritte. Testo che inizia con `= + - @` viene scritto come testo.

## 3. Pulizia dei dati di prova

`pulizia-dati-prova.php` (elenco chiuso di ID verificati il 22/10/2026): eseguito il 22/10/2026.
Per nuove prove in produzione usare `info@temakinho.com` come referente (unico destinatario ammesso
per le prove) e un nome azienda che inizi con «TEST», e rimuoverle dall'archivio Submissions a prova
finita (ID annotati).
