# syntax=docker/dockerfile:1
# ==========================================================================
# Temakinho — immagine WordPress per Dokploy
#
# Contiene:      core WordPress ufficiale + Apache + PHP 8.2
#                + il plugin custom `tmk-design-system`.
# NON contiene:  segreti, wp-config.php, database, upload, cache, backup,
#                file dell'ambiente Local, core WordPress locale, temi,
#                plugin di terze parti (Elementor, Elementor Pro, ACF,
#                Brevo, WP Mail SMTP → installati in Dokploy, vedi
#                DEPLOYMENT.md).
#
# Il core arriva dall'immagine ufficiale, non dalla root locale. Il tag
# corrisponde alla versione installata in locale: WordPress 7.1 / PHP 8.2.
# ==========================================================================
FROM wordpress:7.1-php8.2-apache

# Permalink WordPress → serve mod_rewrite.
RUN a2enmod rewrite

# Il plugin custom viene depositato in /usr/src/tmk, non direttamente in
# /var/www/html: quel percorso e' popolato a runtime dall'entrypoint
# ufficiale e da volumi persistenti. La copia avviene all'avvio, cosi' ogni
# deploy riallinea il plugin alla versione in Git anche quando
# wp-content/plugins e' un volume persistente.
COPY wp-content/plugins/tmk-design-system/ /usr/src/tmk/plugins/tmk-design-system/

# Archivio riservato dei CV: FUORI dalla webroot (/var/www/html), su un volume dedicato
# (temakinho_private, vedi docker-compose.yml). Creato qui con proprietario www-data perche' un
# volume nominato nuovo eredita proprietario e permessi della cartella dell'immagine.
RUN install -d -o www-data -g www-data -m 0750 /var/www/tmk-private /var/www/tmk-private/cv

# Entrypoint: sincronizza il plugin custom, poi passa il controllo
# all'entrypoint ufficiale (che genera wp-config.php dalle variabili
# d'ambiente e installa il core se la directory e' vuota).
RUN <<'BASH'
set -eux
cat > /usr/local/bin/tmk-entrypoint.sh <<'SCRIPT'
#!/usr/bin/env bash
set -euo pipefail

target=/var/www/html/wp-content/plugins
mkdir -p "$target"

# Il plugin custom e' immutabile: la versione dell'immagine vince sempre su
# quella eventualmente presente nel volume.
rm -rf "$target/tmk-design-system"
cp -a /usr/src/tmk/plugins/tmk-design-system "$target/tmk-design-system"
chown -R www-data:www-data "$target/tmk-design-system"

# Archivio CV: utilizzabile SOLO se /var/www/tmk-private e' davvero un volume montato. Il plugin
# accetta un CV solo se trova .tmk-persistent, che viene scritto qui e soltanto in quel caso: senza
# volume i CV finirebbero nello strato effimero del container e sparirebbero al deploy successivo,
# quindi le candidature vengono rifiutate invece di salvare il file altrove.
priv=/var/www/tmk-private
if grep -qs " $priv " /proc/self/mountinfo; then
  install -d -o www-data -g www-data -m 0750 "$priv" "$priv/cv" "$priv/manifest"
  if [ ! -e "$priv/.tmk-persistent" ]; then
    echo "volume persistente dell'archivio CV - creato $(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$priv/.tmk-persistent"
  fi
  chown www-data:www-data "$priv/.tmk-persistent"
else
  echo "tmk-entrypoint: $priv non e' un volume montato: le candidature con CV verranno rifiutate" >&2
fi

exec docker-entrypoint.sh "$@"
SCRIPT
chmod +x /usr/local/bin/tmk-entrypoint.sh
BASH

ENTRYPOINT ["/usr/local/bin/tmk-entrypoint.sh"]
CMD ["apache2-foreground"]
