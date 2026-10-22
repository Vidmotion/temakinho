#!/usr/bin/env bash
# Funzioni comuni delle prove sullo staging isolato (tmkstg). Solo dati fittizi.
set -uo pipefail
STG=/root/tmk-staging/stg.sh
URL=http://localhost:18080
WPC=tmkstg-wordpress-1
T=/root/tmk-staging/tests
LOG=${LOG:-$T/results.log}
STATE=$T/state; mkdir -p "$STATE" "$T/files"
# shellcheck disable=SC1091
. /root/tmk-staging/env/test.env
PASS=${PASS:-0}; FAIL=${FAIL:-0}

ok(){ PASS=$((PASS+1)); printf 'OK    | %s\n' "$*" | tee -a "$LOG"; }
ko(){ FAIL=$((FAIL+1)); printf 'FAIL  | %s\n' "$*" | tee -a "$LOG"; }
note(){ printf '   %s\n' "$*" | tee -a "$LOG"; }
section(){ printf '== %s\n' "$*" | tee -a "$LOG"; }
check(){ if [ "$3" = "$2" ]; then ok "$1 → $3"; else ko "$1 → atteso «$2», ottenuto «$3»"; fi; }
check_ne(){ if [ "$3" != "$2" ]; then ok "$1 → $3"; else ko "$1 → non doveva essere «$2»"; fi; }
contains(){ if grep -q -- "$2" <<<"$3"; then ok "$1"; else ko "$1 (manca «$2»)"; fi; }
lacks(){ if grep -q -- "$2" <<<"$3"; then ko "$1 (trovato «$2»)"; else ok "$1"; fi; }
summary(){ printf '== RISULTATO PARZIALE: %d superati, %d falliti\n' "$PASS" "$FAIL" | tee -a "$LOG"; echo "$PASS $FAIL" > "$STATE/counters"; }
load_counters(){ [ -f "$STATE/counters" ] && read -r PASS FAIL < "$STATE/counters" || true; }

wp(){ "$STG" wp "$@" 2>/dev/null; }
wpe(){ "$STG" wp eval "$1" 2>/dev/null; }
wpe_rc(){ "$STG" wp eval "$1" >/dev/null 2>&1; echo $?; }
cvcli(){ "$STG" wp tmk-cv "$@" 2>&1; }
sql(){ "$STG" sql -N 2>/dev/null; }
cx(){ docker exec "$WPC" sh -c "$1"; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
# PHP CLI: il server non ce l'ha, si usa quello del container WordPress.
hphp(){ docker exec -i "$WPC" php "$@"; }
mailpit_count(){ curl -s http://localhost:18025/api/v1/messages?limit=1 | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo (int)($j["messages_count"] ?? $j["total"] ?? 0);'; }
mailpit_last(){ curl -s http://localhost:18025/api/v1/message/latest | hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo ($j["Subject"] ?? ""), "\n", implode(",", array_map(function($t){return $t["Address"];}, $j["To"] ?? [])), "\n", ($j["Text"] ?? "");'; }

# login <utente> <password> <cookie-jar>  -> codice HTTP del POST (302 = riuscito)
login(){
	rm -f "$3"
	curl -s -c "$3" -b "$3" -u "$BASIC_USER:$BASIC_PASS" -o /dev/null "$URL/wp-login.php"
	curl -s -c "$3" -b "$3" -u "$BASIC_USER:$BASIC_PASS" -o /dev/null -w '%{http_code}' -D "$3.h" \
		--data-urlencode "log=$1" --data-urlencode "pwd=$2" -d "wp-submit=Accedi&testcookie=1" "$URL/wp-login.php"
}
# destinazione del reindirizzamento dopo l'ultimo login con quel cookie jar
login_redirect(){ grep -i '^location:' "$1.h" | tail -1 | tr -d '\r' | cut -d' ' -f2-; }
# nonce REST valido per la sessione del cookie jar (calcolato da WordPress per quel token di sessione)
rest_nonce(){ # jar user_login
	local c; c=$(awk '$6 ~ /^wordpress_logged_in_/ {print $6"="$7}' "$1" | tail -1)
	wpe "\$kv = explode( '=', '$c', 2 ); \$_COOKIE[ \$kv[0] ] = urldecode( \$kv[1] ); wp_set_current_user( get_user_by( 'login', '$2' )->ID ); echo wp_create_nonce( 'wp_rest' );"
}
# get <jar> <percorso> [file corpo]  -> codice HTTP
get(){ curl -s -b "$1" -c "$1" -o "${3:-/dev/null}" -w '%{http_code}' "$URL$2"; }

# Crea un PDF fittizio valido (Ghostscript nel container) e lo copia in $T/files.
mkpdf(){ # nome testo
	cx "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -o /tmp/$1 -c '/Helvetica findfont 28 scalefont setfont 72 700 moveto ($2) show 72 660 moveto (Documento fittizio di prova - nessun dato reale) show showpage'" >/dev/null 2>&1
	docker cp "$WPC:/tmp/$1" "$T/files/$1" >/dev/null 2>&1
}
# Pagina di una posizione pubblicata (form candidatura).
JOB_SLUG=${JOB_SLUG:-sushi-chef-milano}
# apply <file> <nome> <cognome> <email> <nome file inviato> [campo extra]  -> codice HTTP; corpo in $STATE/apply.json
apply(){
	local page nonce jid
	page=$(curl -s "$URL/lavora-con-noi/$JOB_SLUG/")
	nonce=$(grep -o 'name="tmk_apply_nonce" value="[^"]*"' <<<"$page" | head -1 | sed 's/.*value="//;s/"$//')
	jid=$(grep -o 'name="job_id" value="[0-9]*"' <<<"$page" | head -1 | sed 's/.*value="\([0-9]*\)".*/\1/')
	# --form-string: con -F un valore che inizia con < o @ verrebbe letto da un file.
	curl -s -o "$STATE/apply.json" -w '%{http_code}' --form-string action=tmk_job_apply --form-string "tmk_apply_nonce=${NONCE_OVERRIDE:-$nonce}" --form-string "job_id=$jid" \
		--form-string "first_name=$2" --form-string "last_name=$3" --form-string "email=$4" --form-string "phone=000 000" \
		--form-string "cover_letter=Lettera di presentazione fittizia, scritta solo per le prove tecniche." --form-string privacy=1 \
		${6:+--form-string "$6"} -F "cv=@$1;filename=$5" "$URL/wp-admin/admin-ajax.php"
}
# ID della candidatura piu' recente con quel cognome
app_by_last(){ echo "select post_id from wp_postmeta where meta_key='tmk_application_last_name' and meta_value='$1' order by post_id desc limit 1;" | sql; }
cv_of(){ echo "select meta_value from wp_postmeta where meta_key='tmk_application_cv_id' and post_id=$1;" | sql; }
raw_ref(){ echo "select meta_value from wp_postmeta where meta_key='_wp_attached_file' and post_id=$1 order by meta_id desc limit 1;" | sql; }
abs_ref(){ local r; r=$(raw_ref "$1"); case "$r" in /*) echo "$r";; *) echo "/var/www/html/wp-content/uploads/$r";; esac; }
csha(){ cx "sha256sum '$1' 2>/dev/null | cut -d' ' -f1"; }
# URL o nomi di file CV (nome casuale di 32 caratteri, anteprime -pdf*.jpg, percorso dell'archivio)
CVPAT='[A-Za-z0-9]\{32\}\(\.pdf\|\.docx\|\.doc\|\.odt\|-pdf[-0-9x]*\.jpg\)\|tmk-private'
cexists(){ cx "[ -e '$1' ] && echo si || echo no"; }

# --- Convenzioni aziendali (Elementor Pro, pagina 21226, modulo 9fc0788) -------------------------
CONV_POST=${CONV_POST:-21226}; CONV_FORM=${CONV_FORM:-9fc0788}
conv_count(){ echo "select count(*) from wp_e_submissions where element_id='$CONV_FORM';" | sql; }
# conv <azienda> <sito> <email> <dipendenti> <settore> <citta separate da |> [hp]  -> codice HTTP; corpo in $STATE/conv.json
conv(){
	local args=() c IFS_OLD=$IFS
	IFS='|'; for c in $6; do [ -n "$c" ] && args+=(--data-urlencode "form_fields[citta][]=$c"); done; IFS=$IFS_OLD
	curl -s -o "$STATE/conv.json" -w '%{http_code}' -d action=elementor_pro_forms_send_form -d "post_id=$CONV_POST" -d "form_id=$CONV_FORM" \
		-d "queried_id=$CONV_POST" --data-urlencode "referer_title=Convenzioni aziendali" --data-urlencode "referrer=$URL/?page_id=$CONV_POST" \
		--data-urlencode "form_fields[azienda]=$1" --data-urlencode "form_fields[sito_web]=$2" --data-urlencode "form_fields[email]=$3" \
		--data-urlencode "form_fields[dipendenti]=$4" --data-urlencode "form_fields[settore]=$5" "${args[@]}" \
		-d "form_fields[privacy]=on" --data-urlencode "form_fields[hp_check]=${7:-}" -d "form_fields[id_richiesta]=CONV-TEST" \
		"$URL/wp-admin/admin-ajax.php"
}
conv_ok(){ hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo !empty($j["success"]) ? "accettato" : "rifiutato"; ' < "$STATE/conv.json"; }
conv_err(){ hphp -r '$j=json_decode(stream_get_contents(STDIN),true); echo implode(",", array_keys((array)($j["data"]["errors"] ?? array())));' < "$STATE/conv.json"; }
