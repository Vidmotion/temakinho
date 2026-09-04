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

exec docker-entrypoint.sh "$@"
SCRIPT
chmod +x /usr/local/bin/tmk-entrypoint.sh
BASH

ENTRYPOINT ["/usr/local/bin/tmk-entrypoint.sh"]
CMD ["apache2-foreground"]
