#!/usr/bin/env bash
# Prepara lo staging per le prove: ripristino del backup con il codice di produzione, rimozione dei
# dati personali reali e dei segreti, accesso Basic Auth dedicato, utenti di prova.
set -euo pipefail
OLD=${1:?commit di produzione}; B=${2:?cartella backup}
STG=/root/tmk-staging/stg.sh
$STG reset
$STG restore "$OLD" "$B"
$STG up "$OLD" >/dev/null 2>&1
for i in $(seq 1 60); do [ "$(curl -s -o /dev/null -w %{http_code} http://localhost:18080/wp-includes/js/wp-emoji-loader.min.js)" = 200 ] && break; sleep 3; done
echo "prova di ripristino: home $(curl -s -o /dev/null -w %{http_code} http://localhost:18080/)  menu $(curl -s -o /dev/null -w %{http_code} http://localhost:18080/menu/)  login $(curl -s -o /dev/null -w %{http_code} http://localhost:18080/wp-login.php)"
# dati personali reali e segreti: fuori dallo staging
$STG sql < /root/tmk-staging/scrub.sql >/dev/null
echo "UPDATE wp_options SET option_value='a:1:{s:14:\"activate_email\";s:2:\"no\";}' WHERE option_name='sib_home_option'; UPDATE wp_options SET option_value='a:0:{}' WHERE option_name='sib_main_option';" | $STG sql
$STG wp eval '
$o = get_option( "wp_mail_smtp" );
if ( is_array( $o ) ) { foreach ( $o as $k => $v ) { if ( is_array( $v ) ) { foreach ( array( "api_key", "pass", "client_secret", "access_token", "refresh_token" ) as $s ) { if ( isset( $o[ $k ][ $s ] ) ) { $o[ $k ][ $s ] = ""; } } } } update_option( "wp_mail_smtp", $o ); }
global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%sib\\_push\\_app%\" OR option_name IN (\"sib_token_store\",\"sib_push_settings\")" );' >/dev/null 2>&1
docker exec tmkstg-wordpress-1 bash -c 'cd /var/www/html/wp-content/uploads && find 2026 -regextype posix-extended -type f -regex ".*/[A-Za-z0-9]{32}(\.(pdf|docx?|odt)|-pdf(-[0-9]+x[0-9]+)?\.jpg)$" -delete; rm -rf /var/www/html/.tmk-forensics-*'
echo "dati reali rimossi: candidature $(echo "select count(*) from wp_posts where post_type in ('job_application','newsletter_sub','contact_message');" | $STG sql -N), invii elementor $(echo 'select count(*) from wp_e_submissions;' | $STG sql -N), file CV $(docker exec tmkstg-wordpress-1 sh -c 'find /var/www/html/wp-content/uploads -regextype posix-extended -regex ".*/[A-Za-z0-9]{32}(\.(pdf|docx?|odt)|-pdf.*\.jpg)$" | wc -l')"
. /root/tmk-staging/env/test.env
H=$(docker exec -e P="$BASIC_PASS" tmkstg-wordpress-1 php -r 'echo password_hash(getenv("P"), PASSWORD_BCRYPT);')
docker exec -e H="$H" tmkstg-wordpress-1 sh -c 'f=/var/www/html/.tmk-auth/.htpasswd; grep -v "^consultazione-cv:" "$f" > /tmp/h; printf "consultazione-cv:%s\n" "$H" >> /tmp/h; cat /tmp/h > "$f"; rm /tmp/h'
$STG wp user create stg-admin stg-admin@staging.invalid --role=administrator --user_pass="$ADMIN_PASS" >/dev/null 2>&1
$STG wp user create stg-sub stg-sub@staging.invalid --role=subscriber --user_pass="$SUB_PASS" >/dev/null 2>&1
curl -s -X DELETE http://localhost:18025/api/v1/messages >/dev/null
echo "staging pronto"
