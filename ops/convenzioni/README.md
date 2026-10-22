# Convenzioni aziendali — procedure operative

Pagina `/convenzioni-aziendali/` (ID 21226), modulo Elementor «Convenzioni aziendali» (elemento
`9fc0788`). Fonte persistente: Elementor › Submissions. Codice: `inc/conventions-form.php`,
`inc/conventions-registry.php` del plugin `tmk-design-system` (≥ 1.23.0). Nessun segreto qui.

WP-CLI in produzione: `/root/tmk-ops/wp.sh …` (vedi `ops/cv-access/RUNBOOK.md`).

## 1. Notifiche email

Prerequisito: il mittente `info@temakinho.it` deve essere valido in Brevo (dominio `temakinho.it`
autenticato). Controllo: `brevo.sh GET /v3/senders/domains/temakinho.it` → `"authenticated": true`.
Record richiesti da Brevo (zona DNS di temakinho.it su register.it):

| Tipo | Nome | Valore |
|---|---|---|
| TXT | `temakinho.it` (@) | `brevo-code:387c4c6d599ce6488ab26b135ad0f38a` |
| CNAME | `brevo1._domainkey` | `b1.temakinho-it.dkim.brevo.com` |
| CNAME | `brevo2._domainkey` | `b2.temakinho-it.dkim.brevo.com` |
| TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:rua@dmarc.brevo.com` |

Il TXT `brevo-code` si aggiunge accanto a quelli esistenti (SPF compreso), senza sostituirli.

Attivazione (idempotente, copia di sicurezza di `_elementor_data`):

```bash
TMK_CONV_TO=info@temakinho.it TMK_CONV_REPLY_TO=info@temakinho.it \
  wp --user=1 eval-file attiva-notifiche.php [dry-run]
```

Disattivazione: `TMK_CONV_DISABLE=1 wp --user=1 eval-file attiva-notifiche.php`.

Attenzione: l'API di Brevo risponde 201 anche quando poi rifiuta il mittente; l'esito vero è
nell'evento transazionale (`/v3/smtp/statistics/events`): `requests` = accettata,
`delivered` = consegnata, `opened` = aperta, `error`/`blocked`/`hardBounce` = non consegnata.

## 2. Registro condiviso automatico

Ogni invio salvato entra nella coda `wp_tmk_conv_sync` (chiave Submission ID). Senza configurazione
resta lì; l'amministratore vede un avviso. Configurazione (Microsoft 365 / Excel Online):

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
Per nuove prove in produzione usare `info@temakinho.it` come referente e un nome azienda che inizi
con «TEST», e rimuoverle dall'archivio Submissions a prova finita (ID annotati).
