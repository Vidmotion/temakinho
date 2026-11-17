<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Registro convenzioni · Temakinho</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f7f2e8;color:#30261e;font:16px/1.5 system-ui,sans-serif}header{background:#292119;color:#fff;padding:14px max(5vw,18px);display:flex;justify-content:space-between;align-items:center}header span{font-weight:700}header a{color:inherit}main{max-width:1600px;margin:auto;padding:clamp(22px,4vw,60px) max(3vw,16px)}h1{font-size:clamp(28px,4vw,48px);line-height:1.1;margin:0 0 28px}form{display:flex;gap:12px;align-items:end;flex-wrap:wrap}label{display:grid;gap:4px;font-weight:600;font-size:14px}input,button,.clear{font:inherit;padding:9px 13px;border-radius:5px;border:1px solid #948679;background:#fffdf9;color:inherit;min-height:44px}button{cursor:pointer;font-weight:600}button.primary{background:#974017;color:white;border-color:#974017}.clear{text-decoration:none}.scroll{overflow:auto;background:#fffdf9;border:1px solid #d5cbbd;border-radius:7px}table{border-collapse:collapse;width:100%;text-align:left;font-size:14px}td,th{padding:12px;border-bottom:1px solid #ded6cb;vertical-align:top}th{background:#eee5d6;white-space:nowrap}td{max-width:35ch;overflow-wrap:anywhere}td:first-child{min-width:14ch}nav{margin:16px 0;display:flex;gap:12px;align-items:center}#state{font-size:14px;color:#614c3d}a:focus-visible,button:focus-visible,input:focus-visible,.scroll:focus-visible{outline:3px solid #99401b;outline-offset:2px}@media(max-width:640px){label{width:100%}input{width:100%}}
</style></head><body><header><span>Temakinho · Area team</span><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Esci</a></header><main>
<h1>Registro convenzioni</h1>
<form method="post" id="filters" action="<?php echo esc_url( tmk_conv_team_url() ); ?>">
<?php wp_nonce_field( 'tmk_team_read', '_wpnonce', false ); ?>
<label>Cerca per azienda, email o ID<input name="q" type="search" value="<?php echo esc_attr( $filters['q'] ); ?>"></label>
<label>Città<input name="citta" value="<?php echo esc_attr( $filters['citta'] ); ?>"></label>
<label>Dal<input name="dal" type="date" value="<?php echo esc_attr( $filters['dal'] ); ?>"></label>
<label>Al<input name="al" type="date" value="<?php echo esc_attr( $filters['al'] ); ?>"></label>
<button class="primary">Cerca</button><a class="clear" href="<?php echo esc_url( tmk_conv_team_url() ); ?>">Azzera</a>
<button type="submit" name="azione" value="xlsx">Scarica Excel</button>
</form>
<div id="results"><?php tmk_conv_team_table( $rows, $total, $page ); ?></div>
<p id="state" role="status">Aggiornato alle <?php echo esc_html( wp_date( 'H:i' ) ); ?>.</p>
</main>
<script>
(() => {
 const form = document.querySelector('#filters'), results = document.querySelector('#results'), state = document.querySelector('#state');
 const applied = new FormData(form);
 applied.set('pagina',<?php echo (int) $page; ?>);
 applied.set('azione','lista');
 let pending = false;
 async function refresh() {
   if (pending || document.hidden || results.contains(document.activeElement)) return;
   pending = true;
   try {
     const response = await fetch(form.action,{method:'POST',body:applied,cache:'no-store',redirect:'error'});
     if (!response.ok) throw Error();
     const html = await response.text();
     if (!results.contains(document.activeElement)) {
       const left = results.querySelector('.scroll')?.scrollLeft || 0;
       results.innerHTML = html;
       const table = results.querySelector('.scroll');
       if (table) table.scrollLeft = left;
     }
     state.textContent = 'Aggiornato alle ' + new Date().toLocaleTimeString('it-IT',{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Rome'}) + '.';
   } catch { state.textContent = 'Aggiornamento sospeso. Riapri il registro per riconnetterti.'; }
   finally { pending = false; }
 }
 setInterval(refresh,30000);
 document.addEventListener('visibilitychange',refresh);
})();
</script></body></html>
