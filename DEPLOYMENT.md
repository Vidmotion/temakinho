# Temakinho — Deploy su Dokploy

Repository sorgente del sito Temakinho. Contiene **solo codice custom**:
il plugin `tmk-design-system`, i documenti in `TMK DOCUMENTS/` e i file di
build Docker. Core WordPress, plugin di terze parti, temi, database e upload
non sono in Git: arrivano dall'immagine ufficiale, dall'installazione
nell'ambiente o da un'importazione manuale nei volumi.

Repository: `git@github.com:Vidmotion/temakinho.git` (privato)
Branch di lavoro: `staging` → `main` solo dopo QA.

---

## 1. Stack

| Componente | Versione | Provenienza |
|---|---|---|
| WordPress | 7.1 | immagine `wordpress:7.1-php8.2-apache` |
| PHP | 8.2 | idem |
| Apache + mod_rewrite | — | idem |
| MySQL | 8.4 | immagine `mysql:8.4` |
| `tmk-design-system` | 1.18.0 | **questo repository** |

---

## 2. Cosa NON è nel repository

### Plugin da installare privatamente nell'ambiente Dokploy

| Plugin | Versione locale | Tipo | Come installarlo |
|---|---|---|---|
| Elementor | 4.2.4 | gratuito, wordpress.org | Bacheca → Plugin → Aggiungi nuovo |
| **Elementor Pro** | **4.2.3** | **commerciale, con licenza** | Scaricare lo ZIP dal proprio account su `my.elementor.com` e caricarlo via Bacheca → Plugin → Aggiungi nuovo → Carica plugin. Poi attivare la licenza. **Mai committare lo ZIP o la licenza in Git.** |
| Advanced Custom Fields | 6.8.9 | gratuito, wordpress.org | Bacheca → Plugin → Aggiungi nuovo |
| Brevo (`mailin`) | 3.3.5 | gratuito, wordpress.org | Bacheca → Plugin → Aggiungi nuovo. API key da inserire a mano nelle impostazioni del plugin. |
| WP Mail SMTP | 4.9.0 | gratuito, wordpress.org | Bacheca → Plugin → Aggiungi nuovo. Credenziali SMTP a mano. |
| WordPress Importer | 0.9.6 | gratuito, wordpress.org | Serve solo se si importa il contenuto via XML |

### Temi

Nessun tema custom. Il tema attivo è **hello-elementor 3.4.9**, da
wordpress.org: Aspetto → Temi → Aggiungi nuovo → cercare "Hello Elementor".
I temi restano nel volume `temakinho_themes`, non in Git.

### Altro fuori da Git

`wp-config.php` (lo genera l'immagine dalle variabili d'ambiente), database,
`wp-content/uploads/`, cache, backup, log, file di Local by Flywheel.

---

## 3. Variabili d'ambiente

```bash
cp .env.example .env
```

Compilare `.env` sostituendo ogni `CHANGE_ME`. Per i salt:

```bash
curl -s https://api.wordpress.org/secret-key/1.1/salt/
```

Vincoli:

- `MYSQL_DATABASE` / `MYSQL_USER` / `MYSQL_PASSWORD` devono coincidere con
  `WORDPRESS_DB_NAME` / `WORDPRESS_DB_USER` / `WORDPRESS_DB_PASSWORD`.
- `WORDPRESS_DB_HOST` resta `db:3306` (nome del servizio Compose).
- `WORDPRESS_HOME` e `WORDPRESS_SITEURL` devono essere identici e con
  `https://`, senza slash finale.
- `WORDPRESS_DEBUG` va lasciata **vuota** per disattivare il debug:
  l'immagine ufficiale considera vero ogni valore non vuoto, `0` compreso.
  Usare `1` solo in staging.

**In Dokploy il file `.env` non si carica da Git**: i valori si incollano in
*Application → Environment*. Il `.env` locale serve solo per prove in locale.

---

## 4. Collegare il repository a Dokploy

1. Dokploy → **Create Application** → tipo **Docker Compose**.
2. Provider **GitHub**, repository `Vidmotion/temakinho`, branch **`staging`**.
3. Compose path: `docker-compose.yml`. Build context: root del repository.
4. *Environment*: incollare il contenuto di `.env` compilato.
5. **Deploy**.

La rete `dokploy-network` è dichiarata `external: true`: esiste già
sull'host Dokploy. Se il deploy segnala che manca:

```bash
docker network create dokploy-network
```

---

## 5. Volumi persistenti

Il Compose dichiara quattro volumi Docker nominati, creati automaticamente
al primo deploy:

| Volume | Punto di mount | Contenuto |
|---|---|---|
| `temakinho_db` | `/var/lib/mysql` | database MySQL |
| `temakinho_uploads` | `wp-content/uploads` | media, CSS generati da Elementor |
| `temakinho_plugins` | `wp-content/plugins` | plugin di terze parti |
| `temakinho_themes` | `wp-content/themes` | temi |

Il plugin `tmk-design-system` **non** dipende dal volume: viene riscritto
dall'immagine a ogni avvio del container, quindi ogni deploy lo riallinea
alla versione presente in Git. Tutto il resto sopravvive ai redeploy.

Dokploy prefissa i nomi con lo slug del progetto (es.
`temakinho-xxxx_temakinho_db`): è normale.

---

## 6. Importare database e upload (senza Git)

Il dump e i media non passano da GitHub. Dal server Dokploy:

```bash
# Nome esatto dei container del progetto
docker ps --format '{{.Names}}' | grep -i temakinho
```

### Database

```bash
# In locale: esportare dal sito Local
# (Local by Flywheel → tasto destro sul sito → Open site shell)
wp db export ~/temakinho.sql

# Copiare il dump sul server
scp ~/temakinho.sql utente@server-dokploy:/tmp/

# Sul server: importare nel container db
docker exec -i <container-db> \
  mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" < /tmp/temakinho.sql
```

### Upload

```bash
# In locale, dalla root del sito
tar czf ~/temakinho-uploads.tar.gz -C wp-content uploads
scp ~/temakinho-uploads.tar.gz utente@server-dokploy:/tmp/

# Sul server
docker cp /tmp/temakinho-uploads.tar.gz <container-wordpress>:/tmp/
docker exec <container-wordpress> \
  tar xzf /tmp/temakinho-uploads.tar.gz -C /var/www/html/wp-content
docker exec <container-wordpress> \
  chown -R www-data:www-data /var/www/html/wp-content/uploads
```

Cancellare i file da `/tmp` sul server a importazione completata.

---

## 7. Impostare gli URL di staging

Il dump locale contiene gli URL di Local (es. `http://temakinho.local`).
`WP_HOME` e `WP_SITEURL` sono già forzati dall'ambiente, ma i riferimenti
salvati nei contenuti vanno riscritti.

```bash
# Sul server, dentro il container wordpress
docker exec -it <container-wordpress> bash

# Se wp-cli non è presente nell'immagine:
curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp

wp --allow-root search-replace 'http://temakinho.local' \
   'https://staging.temakinho.com' --all-tables --precise --skip-columns=guid
wp --allow-root cache flush
```

---

## 8. Elementor dopo la migrazione

I dati Elementor sono serializzati e vanno trattati con gli strumenti del
plugin, non con una sostituzione SQL:

1. Bacheca → **Elementor → Strumenti → Sostituisci URL**: da
   `http://temakinho.local` a `https://staging.temakinho.com` → *Sostituisci URL*.
2. Bacheca → **Elementor → Strumenti → Generali → Rigenera file e dati**
   (*Regenerate Files & Data*).
3. Bacheca → **Elementor → Strumenti → Svuota cache**.
4. Verificare che il Kit attivo sia quello atteso e che i token del design
   system (`--e-global-color-*`) siano presenti.

Da fare in questo ordine e **dopo** l'attivazione della licenza Elementor Pro,
altrimenti i widget Pro nei template risultano mancanti.

---

## 9. Dominio, HTTPS e backup in Dokploy

**Dominio e certificato** — Application → *Domains* → Add Domain:

- Host: `staging.temakinho.com`
- Container port: `80`
- Service: `wordpress`
- HTTPS: **on**, provider `Let's Encrypt`

Sul DNS: record `A` di `staging.temakinho.com` verso l'IP del server Dokploy.
Il certificato viene emesso da Dokploy dopo la propagazione. Niente di tutto
questo va nel `docker-compose.yml`.

**Backup** — Dokploy → Database/Backups:

- destinazione S3 o volume esterno;
- backup giornaliero del volume `temakinho_db`;
- backup settimanale di `temakinho_uploads`;
- verificare almeno un restore prima del passaggio in produzione.

---

## 10. QA prima del merge su `main`

Da completare su `staging` prima di promuovere:

- [ ] Home, pagine locali, città, storie, eventi, menu, careers, legal, 404
- [ ] Header, footer, breadcrumb, ricerca
- [ ] Widget Elementor TMK presenti e funzionanti nell'editor
- [ ] Token colore/tipografia corretti (i quattro accenti Concept distinti)
- [ ] Nessun URL `temakinho.local` residuo (`wp search-replace --dry-run`)
- [ ] Immagini e media caricati (nessun 404 nella console)
- [ ] Form newsletter/contatti: invio reale via Brevo + WP Mail SMTP
- [ ] PDF di `TMK DOCUMENTS/` raggiungibili dalle pagine legal
- [ ] HTTPS senza contenuti misti; redirect http → https
- [ ] Permalink funzionanti su una pagina profonda (test mod_rewrite)
- [ ] Lighthouse mobile: nessuna regressione rispetto al locale
- [ ] `staging.temakinho.com` non indicizzabile (noindex o password)

Poi: merge `staging` → `main` e deploy dell'applicazione di produzione con il
proprio `.env` e i propri volumi.

---

## 11. Prova in locale (opzionale)

```bash
docker network create dokploy-network   # una volta sola
cp .env.example .env                    # e compilarlo
docker compose config                   # validazione
docker compose up -d --build
docker compose logs -f wordpress
```

Senza porte pubblicate il sito non è raggiungibile da `localhost`: per una
prova locale aggiungere temporaneamente `ports: ["8080:80"]` al servizio
`wordpress`, **senza committare la modifica**.

---

## 12. Note operative

- Aggiornamento del design system: modificare
  `wp-content/plugins/tmk-design-system/`, commit su `staging`, redeploy.
  L'entrypoint riscrive il plugin nel volume.
- Aggiornamento di WordPress: cambiare il tag in `Dockerfile`
  (es. `wordpress:7.2-php8.2-apache`) e ridistribuire. Non aggiornare il core
  dalla Bacheca: la modifica andrebbe persa al deploy successivo.
- `DISALLOW_FILE_EDIT` è attivo: nessuna modifica di file da Bacheca.
- Nessun segreto in questo repository. Se una credenziale finisce per errore
  in un commit, va ruotata, non solo rimossa dalla storia.
