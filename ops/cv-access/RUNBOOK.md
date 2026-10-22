# CV riservati — procedura operativa (produzione Dokploy)

Riguarda `tmk-design-system` ≥ 1.22.0 (`inc/cv-storage.php`, `inc/cv-access.php`) e il volume
`temakinho_private` (`docker-compose.yml`). Nessun segreto in questo file.

## Dove stanno le cose

| Cosa | Dove |
|---|---|
| CV (nuovi e migrati) | volume `<progetto>_temakinho_private` → `/var/www/tmk-private/cv/AAAA/MM/` — **fuori dalla webroot** |
| Manifesti della migrazione | `/var/www/tmk-private/manifest/att-<ID>.json`, `orphan-<hash>.json` |
| Sentinella "volume vero" | `/var/www/tmk-private/.tmk-persistent` (scritta dall'entrypoint solo se è un mount) |
| Consultazione | wp-admin › **Candidature** (`admin.php?page=tmk-candidature`), ruolo `tmk_cv_viewer` |
| Migrazione | wp-admin › Strumenti › **Migrazione CV**, oppure `wp tmk-cv …` |

Senza sentinella l'archivio è "non disponibile": le candidature vengono **rifiutate** (mai salvate
negli upload pubblici) e l'amministratore vede un avviso in bacheca.

WP-CLI non è nell'immagine: si copia il phar in `/tmp` del container (non persistente) e si esegue
come `www-data`:

```bash
C=temakinho-agyavu-wordpress-1
docker cp wp-cli.phar $C:/tmp/wp-cli.phar
docker exec -u www-data $C php /tmp/wp-cli.phar --path=/var/www/html tmk-cv status
```

## 1. Prima del rilascio

1. Backup coerente (`ops/cv-access/backup.sh`): dump con controllo di stderr, trailer e numero
   di tabelle; tar di **tutti** i mount reali del container (anche il volume anonimo della
   webroot e, dopo il rilascio, `temakinho_private`), checksum per file, cifratura `age` verso
   la chiave pubblica di Carlo. Copia esterna sul Mac e verifica di decifratura.
2. Prova di ripristino nello staging isolato (`ops/cv-access/staging/stg.sh restore`).

## 2. Rilascio

Push su `main` → deploy automatico Dokploy (`docker compose up -d --build`): il container
WordPress viene ricreato, il volume anonimo della webroot (Basic Auth, wp-config) è riusato,
`temakinho_private` viene creato al primo avvio. Controlli: `wp tmk-cv status` = pronto; stesso ID
del volume webroot di prima; `wp-login.php` → 401 senza credenziali.

## 3. Migrazione dei CV già pubblici

```bash
wp tmk-cv inventory              # sola lettura: candidature in ogni stato (cestino incluso), orfani
wp tmk-cv migrate --dry-run
wp tmk-cv migrate                # copia verificata + riferimento, NIENTE rimozioni
wp tmk-cv verify
```

`migrate` si può interrompere e rieseguire in qualunque momento: riprende dallo stato scritto nel
manifesto. Le candidature che arrivano nel frattempo finiscono già nell'archivio.

Prima di rimuovere: download di prova dal pannello e **nuovo backup** che includa il volume privato.

```bash
wp tmk-cv remove-public --yes    # solo file con riferimento attuale = copia privata integra
wp tmk-cv verify                 # 0 copie pubbliche, ogni copia privata integra
```

Ogni originale pubblico viene eliminato solo se: il riferimento attuale letto dal database punta
esattamente alla copia privata registrata, tutte le copie private esistono con lo stesso SHA-256,
il file pubblico è ancora quello copiato. Un file che non si riesce a eliminare viene segnalato e
la rimozione si riprende rieseguendo il comando.

## 4. Rollback (tornare a 1.21.3) senza perdere le nuove candidature

**Mai ripristinare il database del backup**: si perderebbero le candidature arrivate dopo.

1. Con il codice nuovo ancora attivo: `wp tmk-cv rollback-prepare --yes` — per **ogni** CV
   dell'archivio (anche quelli nati privati) ricrea una copia pubblica verificata e riporta lì il
   riferimento. Le copie private restano.
2. Commit di rollback che riporta **solo il plugin** a 1.21.3 e lascia `docker-compose.yml` e
   `Dockerfile` nuovi (il volume privato deve restare montato):
   `git checkout 10e6649 -- wp-content/plugins/tmk-design-system && git commit`, push su `main`.
3. A deploy finito, ripetere il passo 1 caricando il file a mano (copre le candidature arrivate fra
   1 e 2):
   `docker cp cv-storage.php $C:/tmp/` poi
   `wp eval 'require "/tmp/cv-storage.php"; exit( tmk_cv_store_cli( array( "rollback-prepare" ), array( "yes" => 1 ) ) );'`
4. Con 1.21.3 i CV tornano scaricabili dai link "Scarica" di Job Applications (e di nuovo pubblici:
   è lo stato precedente).

Ritorno al codice nuovo: deploy, poi `migrate` e `remove-public --yes` come al punto 3.
La procedura è provata nello staging (`ops/cv-access/tests/30-rollback.sh`).

## 5. Account di consultazione

- Utente `consultazione-cv`, ruolo `tmk_cv_viewer`, creato con WP-CLI **senza email**.
- Il login passa da Basic Auth (`.tmk-auth/.htpasswd` nel volume webroot): l'account ha una voce
  dedicata `consultazione-cv` (bcrypt), le altre voci e l'eccezione IP restano invariate.
- Password WordPress: la imposta l'amministratore da Utenti › consultazione-cv.
