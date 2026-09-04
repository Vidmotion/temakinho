# CLAUDE.md — Temakinho WordPress build

Working notes for this WordPress install. The authoritative project memory is
`~/Desktop/carlo/CLAUDE/REDESIGN/PROJECT_CONTEXT.md`; the frozen framework is
`REDESIGN/TMK/01–14`. This file describes **how this install actually works right
now**, verified against the running site — not the plan.

---

## 0. Hard rules — read these first

### 🚫 Prenota is off-limits (D-32)

Carlo owns the booking system on a separate fork. **Do not read, write, modify or
reason about any booking route, page, CTA target or `booking_url` value.**

- `booking_url` post meta is exposed in ACF as a **read-only notice**, never an
  editable field. Leave it that way.
- `TMK_BOOKING_URL` (`tmk-design-system.php:16`) is the governed fallback constant.
  Don't repoint it, don't "improve" it.
- If a task appears to require touching booking, stop and ask instead.

### ⚠️ PHP only when unavoidable — and flag it before writing

The client rejected the PHP-plugin implementation. The site must stay editable in
Elementor Pro by a non-developer. So:

1. Prefer Elementor Site Settings, Global Colors/Fonts, kit Custom CSS, and
   Elementor Pro Custom Code — all of which stay visible and editable in the UI.
2. Next preference: a numbered idempotent script in `_BUILD/` run via `wp eval-file`.
3. PHP edits to the theme or plugin are the **last** resort. Before writing any,
   describe the change and get approval. Log it in
   `REDESIGN/TMK OLD WEBSITE/_BUILD/PHP-PATCH-NOTE.md`.

Two patches are identified and logged in that file. **PATCH 1 was applied on
2026-08-01** (all five routing `require_once` lines are now commented out — see §10).
**PATCH 2 has NOT been applied.** Do not apply it silently as a side effect of other work.

### 🔒 `REDESIGN/TMK/` is frozen, read-only

Never modify, reorganise, rename or overwrite anything under
`~/Desktop/carlo/CLAUDE/REDESIGN/TMK/`. Documents 01–14 and `REFERENCE/` are the
source of truth and are append-never. Same for `TMK OLD WEBSITE/` page archives —
they are the *content source*, treated as evidence.

Corollaries:
- Where a reference image and a written rule conflict, **the written rule wins**.
- `PROJECT_CONTEXT.md` is the one file in `REDESIGN/` you may update — append to its
  Change Log when a decision is made or a constraint is discovered.
- Never invent content. Everything traces to `TMK OLD WEBSITE/`. Treat any unsourced
  value in this install as suspect until checked against the archive (13 of 14
  `location` posts were once empty and the 14th held fabricated data).

### Other standing rules

- **Every visual property is a token reference, never a hard-coded value.** Changing a
  colour in Site Settings must propagate sitewide.
- **Never hand-edit token values in the plugin's `css/tokens/`** — those are a verbatim
  port of the Claude Design system. Update upstream and re-sync.
- One primary CTA per screen. No third typographic register. ALL CAPS is for CTA
  labels only. When choosing between bolder and more restrained, ship the restrained one.

---

## 1. Environment

| | |
|---|---|
| WP root | `/Users/carlodesantis/Local Sites/temakinho/app/public` |
| Site URL | `http://localhost:10013` (Local, nginx + PHP-FPM) |
| WordPress | 7.0.2 · `WP_DEBUG` is **false** |
| PHP | 8.2.29 — `/Users/carlodesantis/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php` |
| WP-CLI | 2.12.0 — `/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp` |
| Theme | Hello Elementor 3.4.9, **unmodified** |
| Elementor / Pro | 4.2.0 / 4.2.0 |
| ACF | **free** 6.8.6 (no Repeater, no Gallery field — see §6) |
| TMK plugin | `tmk-design-system` 1.18.0 |
| Elementor Kit | **#5** (`elementor_active_kit`) |
| DB | `local` / root / root, prefix `wp_` |
| PHP error log | `/Users/carlodesantis/Local Sites/temakinho/logs/php/error.log` |

Both `php` and `wp` are on PATH. Run WP-CLI with an explicit path — don't rely on cwd:

```bash
wp --path="/Users/carlodesantis/Local Sites/temakinho/app/public" <cmd>
```

`WP_DEBUG` is false, so E_WARNING and fatals still display but **notices and
deprecations are suppressed**. Don't read a clean page as proof there are no notices.

---

## 2. Architecture — Elementor-first, plugin-backed

Two rendering engines coexist. Which one owns a URL is decided by whether the
plugin's `template_include` routing claims it.

### The Elementor layer

- **Header** = Elementor template **#25** (`tmk-header`), **Footer** = **#27**
  (`tmk-footer`). Both render on **every route**, including plugin-rendered ones.
  This is why global chrome fixes land sitewide.
- **Kit #5** holds Global Colors, Global Typography, layout defaults, and the kit
  **Custom CSS**. Served as `/wp-content/uploads/elementor/css/post-5.css`.
- **Elementor Pro Custom Code** snippet **#558** (`TMK Navbar + Motion`) injects the
  navbar-behaviour JS at `wp_body_end`, condition `include/general`.
- The plugin registers its design-system components as **native Elementor widgets**
  (`inc/elementor-widgets.php`) — thin control surfaces over the `tmk_render_*`
  engine. Markup/CSS/JS are unchanged; this is how an Elementor page gets real
  design-system components.

### The plugin layer

`wp-content/plugins/tmk-design-system/` — the single implementation layer.

- `inc/enqueue.php` enforces one dependency chain:
  `tokens → base → utilities → animations → components → layout → pages → refinements`.
  Each layer's handles depend on the previous, so it is structurally impossible to
  load a component before its tokens.
- **Hooked at `wp_enqueue_scripts` priority 20, deliberately not 5.** Hello
  Elementor's `style.min.css` (priority 10) resets `body` and `h1–h6` with bare
  element selectors. At priority 5 the TMK chain printed first, lost every
  specificity tie, and the whole site rendered in system sans on white. **Do not
  lower this priority.**
- Also enqueued into the Elementor editor iframe and preview, so builders design
  against real tokens.
- `assets/fonts/` holds the webfonts; `wp_head` priority 1 preloads the two
  critical WOFF2 files.

### Where the two meet

`#tmk-header` — the CSS ID on the header container in template #25 — is
**load-bearing**. The kit Custom CSS targets `#tmk-header …` specifically so its
one ID beats Elementor's per-widget class selectors (see §4). If that ID is ever
removed from the header container, the typography, sticky behaviour and motion all
silently stop applying. Treat it as API.

---

## 3. Page ownership — Elementor vs plugin

Verified by request against the running site. `elPage` = the response carries
`data-elementor-type="wp-page"`.

| Route | Engine | Notes |
|---|---|---|
| `/` | **Elementor** page #36 | Built from TMK widgets: `tmk-hero`, `tmk-narrative`, `tmk-city-bands`, `tmk-trust`, `tmk-cta-banner`, `tmk-newsletter` + 1 stock heading |
| `/menu/` | **Elementor** page #216 | 3 stock `shortcode` widgets wrapping the plugin's menu renderer. Approved as-is (D-30) |
| `/storie/` | plugin | `inc/story-routing.php` |
| `/eventi/` | plugin | `inc/event-routing.php` |
| `/lavora-con-noi/` | plugin | `inc/careers-routing.php` |
| `/la-nostra-storia/` (#250, Heritage) | plugin | `inc/pages-routing.php` |
| `/contattaci/` (#251) | plugin | `inc/pages-routing.php` + `contact-render.php` |
| `/shop/` (#252) | plugin | `inc/pages-routing.php` + `shop-render.php` |
| `/allergeni/` (#254) | plugin | `inc/legal-render.php` |
| `/ristoranti/{city}/` | plugin | `inc/city-routing.php` — data-driven from the `city` taxonomy |
| `/ristoranti/{city}/{location}/` | plugin | `inc/location-routing.php` — **one template for all 14 restaurants** (D-30) |
| `/?s=…` | plugin | `inc/system-routing.php` |
| 404 | plugin | `inc/system-routing.php` (correctly returns 404) |

**The intent (D-29)** is that Header, Footer, Homepage, Contact, Shop, Heritage,
Storie, Eventi, Lavora con noi, Allergeni, 404 and Search all move to Elementor,
while **Menu and Ristoranti keep the plugin renderer** (the Menu page is approved,
and 14 restaurants need one data-driven template, not 14 hand-built pages).

**Since PATCH 1 (2026-08-01)** that blocker is gone: the five routing files are
disabled, so those URLs no longer hit a plugin template and an Elementor page built at
any of them will render. The table above describes the **pre-PATCH-1** ownership; see
§10 for what each route does now.

Also of note: `Elementor Kit #5` and the `TMK Location — Single` template (#255)
exist alongside these. There is no `elementor_library` template for the other routes yet.

---

## 4. Design tokens

Layered `PRIMITIVE → SEMANTIC → COMPONENT → INSTANCE`. Nothing may be introduced at
the instance layer that doesn't already exist as a semantic or component role.
Naming: `category.role.variant.state` → CSS custom properties mirror it exactly
(`--color-action-primary`).

Defined in `wp-content/plugins/tmk-design-system/css/tokens/`:
`fonts.css · colors.css · typography.css · spacing.css · layout.css · elevation.css · motion.css`

### Colour — authoritative, from the Claude Design token files

**Orange is primary, green is secondary** (D-37). Earlier PDF-sampled values
(`#EC6723` / `#FAF7F0` / `#231D14`) were wrong — PDF export shifts colour. The token
file wins. Corrected by `_BUILD/80-authoritative-tokens.php`.

| Role | Token | Hex |
|---|---|---|
| action-primary | `ember-500` | **`#E7591D`** |
| action-primary-hover | `ember-600` | `#C74814` |
| action-primary-active | `ember-700` | `#9E3910` |
| action-on-primary | `tatami-50` | `#FDFBF5` |
| surface | `tatami-100` | **`#F7F1E3`** |
| surface-raised | `tatami-50` | `#FDFBF5` |
| surface-sunken | `tatami-200` | `#EFE5CF` |
| border-default | `tatami-300` | `#E2D3B6` |
| text-primary | `ink` | `#241C13` |
| text-secondary | `tatami-700` | `#5C4530` |
| action-secondary | `erva-700` | `#50643B` |

Governance: a colour used for `action-primary` on a view may not be reused
decoratively on that same view.

### Typography — two roles only

```css
--font-voice:     'Londrina Solid', Impact, 'Arial Black', system-ui, sans-serif;  /* 900 / 400 */
--font-structure: 'Basis Grotesque', 'Helvetica Neue', Arial, sans-serif;          /* 400 / 500 / 700 */
```

`voice` = personality, used sparingly. `structure` = nav, body, UI. **No third
register. No italic** — neither face ships one; synthetic oblique reads as broken.
Only request the weights that have a matching `@font-face`, or the browser fakes bold.

Modular scale, 1.25 ratio, 8 steps, line-height paired to each role token:

```
--text-display   clamp(3.052rem, calc(2rem + 3.5vw), 4.768rem)   leading 0.98
--text-h1        clamp(2.441rem, calc(1.8rem + 2vw), 3.052rem)   leading 1.02
--text-h2        1.953rem   leading 1.08     --text-h3   1.563rem  leading 1.15
--text-h4        1.25rem    leading 1.25     --text-body 1rem      leading 1.55
--text-body-small 0.875rem  leading 1.5      --text-caption 0.75rem leading 1.4
--tracking-voice 0.01em     --tracking-caps 0.08em
--weight-voice 900 · --weight-voice-soft 400
--weight-structure 400 · --weight-structure-medium 500 · --weight-structure-bold 700
```

### Spacing, layout, radius, motion

- **Spacing** 4px base, 10 steps: `4 8 12 16 24 32 48 64 96 128`. Three roles —
  inset, stack, inline.
- **Containers** narrow 680 · standard 1040 · wide **1280** (the kit's
  `container_width`). 12-column grid; gutters resolve to a spacing inline step.
- **Breakpoints** mobile-first: 0–599 · 600–1023 · 1024–1439 · 1440+.
  Kit `viewport_tablet` 600, `viewport_lg` 1024.
- **Radius** exactly three values: `sharp 0` · `soft 8px` · `pill 9999px`. No fourth.
- **Motion** `--motion-feedback 120ms` · `--motion-transition 240ms` ·
  `--motion-narrative 480ms`. `ease-out` for entrances, `ease-in-out` for reversible.
  **Every motion role carries its reduced-motion equivalent as part of the token** —
  not as an accessibility patch bolted on afterwards.
- **WCAG 2.2 AA** is met at the semantic-token level, so no component can be
  assembled out of compliant tokens into a non-compliant result. A visible focus
  state is a required property of every interactive token.

### Elementor mirror

The tokens are mirrored into Kit #5 and exposed as `--e-global-color-*` /
`--e-global-typography-*`. Verified in the served `post-5.css`: **4 system colours +
20 custom `tmk_*` colours = 24**, and **11 typography styles** (4 system + 7 `tmk_*`).
(PROJECT_CONTEXT says "27 Global Colors" — the served kit has 24. Trust the kit.)

System slots: `primary` → `#E7591D`, `secondary` → `#50643B`, `text` → `#241C13`,
`accent` → `#50643B`. Typography `primary`/`secondary` → Londrina Solid,
`text`/`accent` → Basis Grotesque.

**Build in Elementor with globals, never literal values.** A hardcoded hex is a bug.

⚠️ **The four Concept accent tokens have collapsed to two distinct values** —
`tmk_accent_aqua` and `tmk_accent_arya` are both `#50643B`, `tmk_accent_tierra` and
`tmk_accent_fuego` are both `#C74814`. Aqua/Arya/Tierra/Fuego are therefore not
visually distinguishable through their own accent tokens right now. This came from
`50-menus-and-green.php`, not from `80-authoritative-tokens.php` (which doesn't touch
them). Unclear whether intended by the Session-10 green collapse. **Ask before
assigning four distinct hues** — the Concept palette is a client-facing brand
decision, not a cleanup.

---

## 5. Build scripts

`~/Desktop/carlo/CLAUDE/REDESIGN/TMK OLD WEBSITE/_BUILD/` — numbered, ordered,
**idempotent, safe to re-run**:

```
20-import-media.php  30-inspect.php  40-populate-locations.php
50-menus-and-green.php  60-header-footer.php  70-fonts-logo-background.php
80-authoritative-tokens.php  90-navbar-fonts-motion.php
```

Run pattern — always lint first:

```bash
S="/Users/carlodesantis/Desktop/carlo/CLAUDE/REDESIGN/TMK OLD WEBSITE/_BUILD/90-navbar-fonts-motion.php"
php -l "$S" && wp --path="/Users/carlodesantis/Local Sites/temakinho/app/public" eval-file "$S"
```

Conventions to follow when adding one: guard with `if ( ! defined( 'ABSPATH' ) ) exit(1);`,
make every write idempotent (check-then-act), echo a readable report, back up
anything destructive to a `*-backup.json` next to the script, and finish with
`\Elementor\Plugin::$instance->files_manager->clear_cache()`.

**Always clear the Elementor cache after changing kit settings or template data** —
`post-5.css`, `post-25.css` etc. are generated files and stale CSS looks exactly
like a broken fix. Also `delete_post_meta( $id, '_elementor_css' )` for a touched template.

Sidecar data: `media-map.json` (195 imported images), `restaurants.json`,
`locations-backup.json`, `header-footer-backup.json`, `site-state.json`.

### Run build scripts as an admin: `wp --user=1 eval-file …`

WP-CLI runs as user 0 by default. Any meta registered with a `sanitize_callback`
that checks `unfiltered_html` will be silently mangled. This bit us on
`_elementor_code`: `wp_kses_post()` stripped the `<script>` tag, entity-encoded `>`
and `&&`, and deleted everything between a `<` and the next `>` — turning
`if (y < REVEAL_AT) {…} else if (Math.abs(delta) > THRESHOLD)` into
`if (y  THRESHOLD) {`. It fails silently and looks like a caching problem.
**Default to `--user=1` for any script that writes meta containing markup.**

### Elementor Pro Custom Code snippets — the full contract

Creating an `elementor_snippet` post with `post_content` and a plausible location is
**not enough**; the snippet just never prints. Read from
`elementor-pro/modules/custom-code/` (v4.2.0), all four are required:

| Meta | Value | Why |
|---|---|---|
| `_elementor_code` | the code, **including its own `<script>` tag** | `document.php::print_content()` reads this meta and echoes it raw. `post_content` is **never** read. |
| `_elementor_location` | `elementor_body_end` | The `wp_footer` hook prints `OPTION_LOCATION_BODY_END`, whose value is this string. `wp_body_end` matches no location. |
| `_elementor_priority` | a real postmeta **row** | It is `WP_Query`'s orderby `meta_key`, which becomes a SQL EXISTS clause. |
| `_elementor_template_type` | `code_snippet` | Optional — `Documents_Manager` falls back to the CPT map — but it is what a real snippet carries. |

⚠️ `_elementor_priority` is registered with `'default' => 1`, so `get_post_meta()`
returns `1` **even when no row exists**. Check with `metadata_exists()`, never by
reading the value — otherwise you "confirm" a snippet the SQL query cannot see.

`Conditions_Manager::save_conditions()` takes an array of **part arrays**
(`[['include','general']]`), which it implodes with `/`. Passing pre-joined strings
throws `Cannot unset string offsets`.

`_BUILD/95-fix-custom-code-snippet.php` repairs all of this idempotently and replays
the module's own query to prove the snippet is selectable.

### CSS gotchas that cost real time here

- **Never `animation-fill-mode: both` on an element whose `transform` is also driven
  by a state class.** Animations outrank normal author declarations in the cascade,
  so a finished `both`-filled animation keeps applying its final keyframe forever and
  silently beats the state rule. `#tmk-header` had `animation: tmkHeaderIn … both`
  and `.tmk-nav-hidden { transform: translateY(-100%) }`: the class toggled correctly
  but the header never moved. Fixed by using `backwards`.
- **`overflow-x: clip` on `<body>`, never `hidden`** — `hidden` computes the other
  axis to `auto`, creating a scroll container that breaks `position: sticky`.
  Already correct in `css/refinements/shell.css:153`; don't "tidy" it.
- **PHP heredocs (`<<<CSS … CSS;`)**: no line inside may begin with the closing marker
  word after optional whitespace. A comment line starting `   CSS animations…` ends
  the heredoc and yields a baffling "Invalid body indentation level" parse error.

Known media IDs — logo black **#443**, logo white **#446**, spotify **#442**,
hero slides TMKSLIDE **#257** / AQUA **#259** / ARYA **#261** / FUEGO **#263** /
TIERRA **#265**, hero-cluster **#45** (also `option tmk_hero_cluster_id`).

`80-authoritative-tokens.php` sideloads 7 illustrations from `~/Downloads`.
`garland-frame.png` is **not** in the media library and not in `~/Downloads` — the
script skips it with a notice. The other 6 are imported.

---

## 6. Content model and the ACF constraint

**5 CPTs** — `location`, `menu_item` (no public URL), `job`, `event`, `testimonial`
(no public URL). `post` is extended, not replaced.
**6 taxonomies** — `city`, `concept` (Aqua/Arya/Fuego/Tierra/**Standard**),
`menu_category`, `dietary`, `department`, native `category`.

ACF is the client-editing layer for Ristoranti / Menu / Città. **Field `name` values
map exactly onto the meta keys the plugin already reads** — so never rename an ACF
field name to something prettier, and never rename an ACF field key post-launch.
That is what keeps the renderer unchanged.

Open constraint: ACF **free** has no Repeater and no Gallery field. `hours` needs a
repeater, `gallery_ids` needs multi-image. Options are laid out in
`_BUILD/PHP-PATCH-NOTE.md` (ACF Pro recommended, ~€49/yr). **Undecided — do not
pick for Carlo.**

Recorded, not a bug: `location-render.php` reads `address`, `phone`, `tagline`,
`short_description`, `accessibility_notes`, `booking_url`, but `content-model.php`
registers only `delivery_available`, `gallery_ids`, `hours`. `get_post_meta()`
doesn't need registration, so pages work — but those fields are invisible to the
REST API and Elementor Dynamic Tags.

---

## 7. Naming conventions

| Thing | Convention | Example |
|---|---|---|
| Tokens | `category.role.variant.state` | `color.action.primary.hover` |
| CSS custom properties | mirror the token exactly | `--color-action-primary` |
| Components | PascalCase, named for their **job**, never appearance | `LocationCard`, not `TealBox` |
| Gutenberg blocks | `tmk/{kebab-case}` | `tmk/location-card` |
| Elementor widgets | `tmk-{kebab-case}` | `tmk-cta-banner` |
| ACF field keys | `tmk_{entity}_{field}` | `tmk_location_hero_media` |
| Plugin CSS | one file per component, layer folder decides load order | `css/components/card.css` |
| Build scripts | `NN-kebab-description.php`, NN = run order | `80-authoritative-tokens.php` |

No abbreviation without a glossary entry. An abbreviation used once must be used
identically everywhere.

---

## 8. CTA vocabulary — one governed verb per action

| Action | Label | Never |
|---|---|---|
| Booking | **Prenota** | "Riserva un tavolo", "Prenotazioni" |
| Ordering | **Ordina** | — |
| Contact | **Scrivici** | — |
| Secondary | **Scopri**, **Esplora** | noun phrases ("Il Menu") |

Client-specified nav (2026-07-31):
`CHI SIAMO · RISTORANTI · STORIE · EVENTI · LAVORA CON NOI` — SHOP hidden until built.
`PRENOTA ORA` is the orange header CTA on the right, **not** a nav item.
RISTORANTI dropdown = restaurant names only, clickable, **no repeated PRENOTA
buttons** (the reference PDF's per-card buttons are explicitly rejected).

Errors never blame the user. Success copy is warm and specific. Alt text preserves
narrative intent; ARIA labels are functional and literal.

---

## 9. Verification

There is no test suite. Verify by request, and don't declare a visual fix done
without checking the **served** CSS — not the source file, not the DB value.

```bash
# status + PHP warnings
curl -s -o /tmp/h.html -w "%{http_code}\n" http://localhost:10013/
grep -inE "Warning:|Notice:|Fatal error|Deprecated:|Parse error" /tmp/h.html

# stylesheet load order actually served
grep -oE "href='[^']*\.css[^']*'" /tmp/h.html | nl

# the generated kit CSS (where kit Custom CSS lands)
curl -s "http://localhost:10013/wp-content/uploads/elementor/css/post-5.css" | tr '}' '}\n' | grep font-family
```

When a style doesn't apply, check in this order: (1) is the rule in the *generated*
`post-*.css` at all, or is the Elementor cache stale; (2) is it inside an unexpected
`@media`; (3) does a higher-specificity Elementor per-widget selector win; (4) does
the `var()` it depends on actually resolve; (5) does the font/asset file itself
return 200. Reason about the real cascade — don't guess and re-run.

### Browser verification with Playwright

Installed in the session scratchpad (`npm install playwright` + `npx playwright
install chromium`). `npx playwright install chromium` alone fails — the npm package
must exist first. Drive it with a small `.mjs` script: load the page, probe
`getComputedStyle` / `getBoundingClientRect`, screenshot at 375 / 768 / 1440.

**Scroll with `page.mouse.wheel()` in increments, not `window.scrollTo()`.** An
instant jump fires a single scroll event; any reflow afterwards (a lazy image
landing, the page shrinking a few px) fires a second event whose negative delta the
header's direction logic correctly reads as "scrolled up" and re-reveals it. That
produced a false failure at 375 that did not reproduce under real scrolling.

**Standing risk:** across 11 build sessions almost no page had been visually verified
in a browser at any breakpoint. The homepage now has been, at three widths
(screenshots in `~/Desktop/tmk-qa/`). Every other route still has not. Static
verification is necessary but not sufficient — say so rather than implying visual
sign-off.

### Known open defect — no mobile navigation below 600px

The header's nav column (Elementor element `23d7b26`) carries
`elementor-hidden-mobile` and computes to `display: none` at ≤599px, taking the
hamburger toggle with it. At 375px the header shows only the logo and PRENOTA ORA —
there is no way to reach any other page. This breaks the spec's "≤3 taps to any
Location" rule. The plugin ships its own `tmk-mobilenav` drawer
(`css/layout/header.css`) but the Elementor header does not render it. Needs a
decision: unhide the Elementor toggle, or wire up the plugin drawer.

---

## 10. PATCH 1 applied — 2026-08-01

**File:** `wp-content/plugins/tmk-design-system/tmk-design-system.php`
**Change:** deletions only. Five `require_once` lines commented out, each with a
`// PATCH 1 (D-29):` comment naming the routes that moved to Elementor. Nothing else in
the file was touched; `php -l` clean. One-line revert per route.

```
inc/story-routing.php      (line ~69)   /storie/, /storie/{slug}/
inc/event-routing.php      (line ~74)   /eventi/, /eventi/{slug}/
inc/careers-routing.php    (line ~79)   /lavora-con-noi/, /lavora-con-noi/{slug}/
inc/pages-routing.php      (line ~87)   /la-nostra-storia/, /contattaci/, /shop/, /allergeni/
inc/system-routing.php     (line ~102)  404 + /?s=…
```

**Still live and unchanged:** `location-routing.php`, `city-routing.php`,
`menu-render.php`, `location-render.php`, `city-render.php`, `content-model.php`,
`enqueue.php`, `elementor-kit.php`, and every component renderer. The *render* files for
the disabled routes (`story-render.php`, `event-render.php`, `careers-render.php`,
`heritage-render.php`, `contact-render.php`, `shop-render.php`, `legal-render.php`,
`notfound-render.php`, `search-render.php`) also all stay loaded — only the
`template_include` filters that claimed those URLs are gone.

### Verified by request (12 routes, WP_DEBUG temporarily true, then restored to false)

| Route | HTTP | Renders via | Header/Footer | `<main>` body chars (pre → post) |
|---|---|---|---|---|
| `/` | 200 | Elementor page #36 | yes / yes | 22764 → 22764 **byte-identical** |
| `/menu/` | 200 | plugin (`<main id="main">`) | yes / yes | 112249 → 112249 **byte-identical** |
| `/ristoranti/milano/` | 200 | plugin `city-routing` | yes / yes | 8485 → 8485 **byte-identical** |
| `/ristoranti/milano/tierra/` | 200 | plugin `location-routing` | yes / yes | **byte-identical** |
| `/contattaci/` | 200 | Hello Elementor page tpl | yes / yes | 3704 → 3775 |
| `/shop/` | 200 | Hello Elementor page tpl | yes / yes | 11490 → 11561 |
| `/la-nostra-storia/` | 200 | Hello Elementor page tpl | yes / yes | 8692 → 8763 |
| `/storie/` | 200 | Hello Elementor page tpl | yes / yes | 12446 → 12468 |
| `/eventi/` | 200 | Hello Elementor page tpl | yes / yes | 7442 → 4460 |
| `/lavora-con-noi/` | 200 | Hello Elementor page tpl | yes / yes | 10349 → 3524 |
| `/allergeni/` | 200 | Hello Elementor page tpl | yes / yes | 1505 → 618 |
| 404 (bogus slug) | **404** | Hello Elementor 404 tpl | yes / yes | 600 → 90 |

No 500s. Header and Footer render on **all 12** — the Elementor Theme Builder locations
are independent of the disabled routing, as expected.

Two distinct post-patch behaviours, both correct:
- **Content survived** on `/contattaci/`, `/shop/`, `/la-nostra-storia/`, `/storie/`
  (body grew ~+71 chars). Those pages' content is TMK **shortcodes in `post_content`**;
  `pages-routing.php` only swapped the *template file* (full-width vs constrained), so
  `the_content()` still renders them — now inside Hello Elementor's wrapper.
- **Content shrank** on `/eventi/`, `/lavora-con-noi/`, `/allergeni/` and 404, whose
  entire body came from the plugin template. These are now near-empty canvases awaiting
  their Elementor build. Expected and correct.

### Two things this surfaced (both pre-existing, neither caused by PATCH 1)

1. **`register_meta` notice fires 4× on every page load.** `content-model.php` registers
   array-type meta (`gallery_ids`, `hours`) without `show_in_rest.schema.items`. With
   `WP_DEBUG` on these print **before `<!DOCTYPE>`**, prepending ~2152 bytes of `<b>Notice</b>`
   markup to every response and breaking the HTML prologue. Invisible with `WP_DEBUG`
   false, which is why it went unnoticed. Fixing it is a `content-model.php` edit → flag first.
2. **Location pages have no `<main>` landmark.** `/ristoranti/milano/tierra/` contains no
   `<main>`, no `role="main"` and no `id="main"` — so §4's landmark claim does not hold for
   the 14 location pages, and there is no skip-link target. Pre-existing; a renderer change.

**Grep caution for future verification:** WordPress prints notices as
`<b>Notice</b>:  Function …`, so a `grep -E "Notice:"` over served HTML returns a **false
clean**. Match `<b>Notice</b>` or strip tags first.

---

## Homepage (page #36) — rebuilt as native Elementor

Built by `_BUILD/100-homepage.php` (idempotent, deterministic element IDs `tmkhp001…085`,
`php -l` clean, run with `wp --user=1 eval-file`). Backups: `_BUILD/homepage-backup.json`
holds the **pre-build original** and is never overwritten; `homepage-backup-last.json` is
rewritten each run.

**Sources, in priority order:** composition + section order from Claude Design
`c2a8913b…/pages/Homepage.jsx`; city names / counts / taglines / conversion copy from
`pages/homepage-data.js`; the five slide headings verbatim from
`REDESIGN/TMK OLD WEBSITE/HOME/page.md`. No copy invented.

### Section order (Homepage.jsx's own)

1. **Arrival** — `hero-cluster.png` (#45) as the hero; the artwork *is* the hero. The single
   `<h1>` ("Temakinho — sushi giapponese, anima brasiliana") is `.tmk-sr-only`, so the outline
   is correct without a competing headline. Attachment alt set from the design's `mediaAlt`.
2. **Slides** — five old-site slides (#257/259/261/263/265) with their verbatim H2s, Pro
   `slides` widget, `slides_title_tag=h2`. *Not in Homepage.jsx* — added on request.
3. **Milano** band (#29) + 4 location cards · **Roma** band (#31) + 3 location cards.
4. **Le altre città** — Bologna (#30) · Lione (#32) · Rimini-wide (#33).
5. **Closing band** — "Il tuo tavolo ti aspetta" + copy, no CTA.

Cities with `locations` become bands; the rest compose the trio. That split is Homepage.jsx's
`anchors`/`others` filter, not a layout choice. Location permalinks are resolved via
`get_permalink()` on the `location` CPT, not string-built.

### Client overrides applied on top of Homepage.jsx

- **Prenota is navbar-only.** Homepage.jsx puts a "Prenota ora" CTA in the hero, a "Prenota"
  CTA on the closing band and a "Prenota" chip on every location card; the old-site slides each
  carried "PRENOTA UN TAVOLO". All dropped. Card chips read **Esplora** and the whole card is
  the click target. Verified: the only "PRENOTA ORA" on the page is in the header.
- **No per-card buttons.** Cards are containers with `html_tag: 'a'` + `link`.

### Token discipline

Zero hex, zero font-family in `_elementor_data` (28.7 KB) — 88 `__globals__` references
instead. The script *fails the run* if a hex appears, and now also regenerates the page CSS and
asserts every referenced global actually emitted a CSS var. Widths from the layout tokens:
wide 1280 (hero) / standard 1040 (city + trio) / narrow 680 (closing).

### The trap that cost a rebuild — `__globals__` keys for GROUP controls

`base.php::is_global_control()` builds the lookup key as `groupPrefix . groupType`. So a
typography group named `typography` must be keyed **`typography_typography`**, and the Slides
widget's `heading_typography` group **`heading_typography_typography`**. A plain control
(`title_color`, `text_color`, `heading_color`) keys on its own name.

Using the bare group name **fails silently**: `update_post_meta` accepts it, colours land
correctly, and every heading quietly inherits the kit's display face. First build had all six
heading roles computing to Londrina 900 / 48.83px. After the fix: eyebrows Basis Grotesque
700 / 12px / uppercase, card titles Londrina 900 / 25px, chips Basis 700 / 14px caps, band
titles Londrina 900 / 31px. The new guard in `100-homepage.php` catches this class outright.

**Also note:** `$widget->get_controls()` called outside the editor returns an incomplete set —
it omits `padding`, `flex_direction`, `title_color`, all of which demonstrably work. Read the
widget source for control names; do not trust that call.

### Verified with Playwright (375 / 768 / 1440)

Screenshots in `~/Desktop/tmk-qa/homepage-{mobile-375,tablet-768,desktop-1440}.png`.
All three: **0** console errors, **0** page errors, **0** failed requests, **no** horizontal
scroll (`scrollWidth === clientWidth`), exactly **one** `<h1>`, 11 images with none broken,
and the nav computing to `"Londrina Solid"` weight 400 with the 400 face reaching
`status: 'loaded'`. Grid collapses 4/3 → 2 (tablet) → 1 (mobile).

### Still unresolved

1. **Mobile header has no navigation.** At 375 every nav affordance inside `#tmk-header`
   computes `visible: false`; the only visible `.elementor-menu-toggle` sits at y≈4555, in the
   **footer**. Mobile visitors cannot reach the menu. Pre-existing — lives in header template
   **#25**, untouched by this build — but it blocks mobile navigation and should be next.
2. **Header is tall** — 160px at 1440, **197px at 375** (the CTA wraps to its own row), against
   the ~72px that `90-navbar-fonts-motion.php` targets. Also header template #25.
3. **Location cards repeat the city photo.** Aqua/Arya/Fuego/Tierra all show
   `interior-milano.jpg`; Monti/Ponte Milvio/Borgo all show `interior-roma.jpg`. This is
   faithful to Homepage.jsx (`media={c.img}`) but reads as repetition. Needs per-location
   photography before it looks intentional.
4. **`garland-frame` has no attachment.** Every other illustration resolved
   (macaw #557, koi #229, snake #47, leaves-banana #46, garland-horizontal #26).
5. **Kit global typography `secondary` is mislabelled** — titled "TMK Structure (Basis
   Grotesque — everything else)" but set to `Londrina Solid` 400 in kit #5. Nothing in this
   build uses it; anything that does will get the wrong face. Fix in `inc/elementor-kit.php`.
6. **Milano's Linate and Roma's Fiumicino are absent** from the homepage — `homepage-data.js`
   omits them although both exist as `location` posts and appear in the nav. Design-source gap,
   not a build error.

### 101 — Contattaci (page #251) · `_BUILD/101-contattaci.php`

Composition from `pages/Contact.jsx`; **H1 and both intro paragraphs verbatim from
`CONTATTACI/page.md`**. Design copy used only for the eyebrow ("Scrivici"), the aside title
("Come raggiungerci") and two aside labels — marked `DESIGN COPY` in the script.

**No contact details invented.** `contact-data.js` ships placeholders (`ciao@temakinho.it`,
`+39 02 000 000`, `press@temakinho.it`); the archive verified in the live DOM that
`#MainContent` has **zero** `mailto:` and **zero** `tel:` anchors and records "no address, no
phone, no email, no press contact, no map" as an explicit absence. The aside therefore carries
only the four real social profiles + `TEMAKINHO s.r.l. — P. IVA 03134960305`.

Three confirmed decisions: map shows all locations (not the single-address stock
`google_maps` widget — a keyless Maps **search** embed in a stock `html` widget; per-location
`map_url` meta was rejected, only 4/14 populated and some point at renamed places);
the restaurant select lists the **live 14** from the `location` CPT, not the archive's 16
(which still offered Brescia, Firenze, Milano Magenta/Navigli, Roma Rinascente, Verona);
Prenota stays navbar-only, so the verbatim "clicca qui" is plain text and the design's
"Prenotazioni" aside row is dropped. Form posts to `save-to-database` — no email action,
because there is no published address to send to.

Verified 375/768/1440: 0 console errors, 0 page errors, 0 failed requests, no horizontal
scroll, exactly one `<h1>` ("Contattaci"). Screenshots `~/Desktop/tmk-qa/contattaci-*.png`.

## ⚠ Kit breakpoints are broken — affects EVERY page

`viewport_mobile=599`, `viewport_tablet=600`, so Elementor emits the tablet rules as
`@media(max-width:600px)`. Confirmed against `Plugin::$instance->breakpoints`:

    mobile   value=599  direction=max
    tablet   value=600  direction=max

**The tablet band is 600–600px.** Every `*_tablet` control value is dead, and any viewport
from 601px up — iPads, small laptops, the 768 QA breakpoint — receives **desktop** styles.
Elementor's defaults are mobile 767 / tablet 1024.

Consequences already hit:
- Contattaci's two columns did not collapse at 768; the fixed 320px aside pushed the document
  56px past the viewport. Worked around **without** media queries: both columns carry a px
  basis and grow/shrink so they wrap on their own. Any page relying on `_tablet` will fail
  the same way.
- **Correction to the homepage entry above:** its card grid does *not* "collapse 4/3 → 2
  (tablet) → 1 (mobile)". `grid_columns_grid_tablet` never applies, so 768 still renders 4
  columns (they shrink to ~170px rather than overflow, which is why QA passed). Only the
  mobile (≤599) single-column rule is live.

Two Elementor flex traps worth keeping (both cost a rebuild on this page):
- `_flex_size: 'grow'` expands to `--flex-grow:1; --flex-shrink:0`. A fixed-width sibling
  next to a `grow` sibling gets crushed to **0×0** while still reporting `visible` /
  `opacity:1` — that is how the macaw vanished. Give the fixed column `_flex_size:'none'`
  and let the flexible one `'shrink'` with `width:100%`.
- A cross-origin map iframe is usually **blank in a fullPage screenshot** — it had not painted
  yet. Do not call it broken from the image: check the frame (`p.frames()`) or the network
  trace. This one loads 301 → `google.com/maps/embed` → tiles, 48 images inside the frame.

**Map caveat:** a keyless search embed shows Google's own result set for "Temakinho" — about
three pin clusters at z=5, not a guaranteed 14. Pinning all 14 needs a Google My Maps URL or a
Maps Embed API key. Neither exists yet.

### 099 — kit breakpoints repaired · `_BUILD/099-kit-breakpoints.php`

Kit #5 set to Elementor's defaults: **mobile ≤767 · tablet 768–1024 · desktop 1025+**
(was mobile ≤599 / tablet 600–600). Originals saved to `kit-breakpoints-backup.json`.
Confirmed through `Plugin::$instance->breakpoints`, not the meta.

Re-QA'd both shipped pages at 375/768/1440 after the change — homepage and Contattaci both
still clean (0 errors, no horizontal scroll, one `<h1>`). Two real improvements:
- Homepage card grid now genuinely responds: **4 cols @1440 → 2 @768 → 1 @375**. The earlier
  "collapses at tablet" claim is true from here on; it was not before.
- The header's burger toggle **now appears at 768**, where it previously never did.

**Mobile header nav (375) is still broken, and the cause is now known.** The toggle is not
hidden by CSS — it computes `display:flex` at **0×0**. It is flex-crushed by the header row
(logo + nav widget + CTA) exactly like the macaw on Contattaci. Fix is in header template
**#25**: give the nav-menu widget `_flex_size:'none'` (or let the logo/CTA shrink) so the
burger keeps its intrinsic width. Not done — #25 is outside the page-build scope.

### 102 — Shop (page #252) · `_BUILD/102-shop.php`

Composition from `pages/Shop.jsx` (photographic hero → featured diptych → product grid →
conversion). **Five products, not fourteen.**

The "14" is `/collections/all`; the live `/pages/shop` grid rendered exactly five, and those
five are the only entries with `Product type: Merchandising`. Of the other nine: **eight are
€0,00 event tickets** (BOLOGNA BUSINESS SCHOOL PARTY, Dinner Show, I PORCI COMEDY ×2, La
Festa, LA SOIRÉE, TAROCCHI NIGHT, TEMAKHOUSE) which belong to the Eventi archive (106), and
one is **Caipirinha**, whose entire description is four cocktail recipes — menu content.
`I PORCI COMEDY - Fuego by Temakinho` exists **twice** as two distinct Shopify products.

| Product | Price | Attachment | Canonical URL |
|---|---|---|---|
| Polo vintage Temakinho *(featured)* | €9,90 | #362 | `/products/polo-vintage-temakinho` |
| Borsa in tela | €2,90 | #361 | `/products/shopper-in-tela` |
| Piatto del Buon Ricordo | €7,90 | #363 | `/products/piatto-del-buon-ricordo` |
| Borraccia | €8,90 | #367 | `/products/borracce` |
| Mug | €6,90 | #372 | `/products/mug` |

**No invented copy.** Every Merchandising product has an EMPTY `body_html`, so cards carry
name + price only. `shop-data.js` was used for composition only — its catalogue (Kit temaki
€48, Salsa di anacardi, Gift card…) is entirely fictional. Page copy is the archive's:
H2 `MERCHANDISING` promoted to the single `<h1>` (the archive records **no h1** on
`/pages/shop`) and the second H2 as the standfirst. Closing band is DESIGN COPY, marked.

**No cart.** Each card links to that product's real canonical URL on the external store, new
tab; the section CTA uses `TMK_SHOP_URL`. **Nav "Shop" label untouched — still hidden**
(verified: header nav renders CHI SIAMO · RISTORANTI · STORIE · EVENTI · LAVORA CON NOI).

Verified 375/768/1440: 0 console errors, 0 page errors, 0 failed requests, no horizontal
scroll, one `<h1>` ("MERCHANDISING"), 5 images none broken.

**Token gap found:** the kit has no "structure face, bold, large" global — every large global
(`tmk_h1`–`tmk_h4`) is Londrina 900. `Shop.jsx` wants the price in Basis Grotesque 700 at h3
scale. Prices use `tmk_h4` (Londrina) rather than hardcode an off-token font. Worth adding a
`tmk_price` / `tmk_structure_lg` global.

**Minor:** the Mug photo (`AQUAMUGFULL.jpg`) has a baked-in white background while the other
four are transparent PNGs, so its card shows a white block against the cream. Source-asset
inconsistency, not a build issue.

---

## Visual parity audit vs Claude Design — 2026-08-03

Measured in Chromium at 375 / 768 / 1440 against the **rendered** design prototype
(`c2a8913b…` `TMK Homepage.html`, served with its real fonts and assets), not read off the
source. Raw numbers: `~/Desktop/tmk-qa/parity/*.json`; screenshots `design-*` vs `wp-*`.

⚠ **099 repaired the kit breakpoints, so tablet values are live for the first time.**
Anything previously "QA'd at 768" was being served desktop styles and is unverified.

### Header @1440 — parity is poor, and none of it was in scope this pass

| Property | Design | WP | Match |
|---|---|---|---|
| height | **103px** | **160px** (197 @375) | ✗ |
| inline padding | 32px | 40px (20 ≤1023px) | ✗ |
| background | `#F7F1E3` | `#F7F1E3` | ✓ |
| logo box | 66×66 @ x=32 | 136×136 @ x=40 | ✗ |
| nav face / weight | Londrina Solid 400 | Londrina Solid 400 | ✓ |
| nav size | 12px | 15.84px | ✗ (deliberate — see §90 comment) |
| nav tracking | 0.96px (0.08em) | 0.63px (0.04em) | ✗ (deliberate) |
| nav colour | `#5C4530` tatami-700 | `#241C13` ink | ✗ |
| CTA label | Londrina 400 11.2px | Londrina 400 15px | ✗ |
| CTA background | **`#50643B` green** | **`#E7591D` orange** | ✗ |
| announcement bar | in the shell spec | absent | ✗ |

The header is **57px taller** than the design and its CTA is a different colour family.
`refinements.css` puts the persistent Prenota on tatami green; WP has it on ember orange.
Prenota is off-limits (D-32) so this was not touched — flagging only.

### Footer @1440 — after `_BUILD/120-footer-parity.php`

| Property | Design | WP | Match |
|---|---|---|---|
| background | `#241C13` ink | `#241C13` | ✓ |
| row count | 3 (crown · main · legal) | 3 | ✓ |
| garland asset | `garland-horizontal.png` | same, media #26 | ✓ |
| garland crown row | cream `#F7F1E3`, h 173.59 | cream, h 173.59 | ✓ |
| garland width | min(90%, 880px) | min(90%, 880px) | ✓ |
| main row inner | boxed 1280 @ x=80 | boxed 1280 @ x=80 | ✓ |
| column gap | 48px | 48px | ✓ |
| columns 375/768/1440 | 1 / 3 / 5 | 1 / 3 / 5 | ✓ |
| column heading | Londrina **700** 12px .96px caps `#C9B28C` | Londrina **400**, else identical | ✗ *deliberate* |
| column link | Basis Grotesque 400 14px `#EFE5CF` | **Londrina** 400 14px `#EFE5CF` | ✗ *client override 2A* |
| legal divider | 1px `rgba(247,241,227,.12)` | identical | ✓ |
| legal type | Basis 400 12px `#A68C63` | identical | ✓ |
| legal links | 5 | 5 | ✓ |
| newsletter | email + "Iscriviti" | same, and functional | ✓ |
| newsletter button | transparent + 1px cream border | **orange kit button** | ✗ |
| brand logo | 44×44 white **SVG** square mark | 140×59 white **PNG** wordmark | ✗ *asset gap* |
| social | 3 text links | 4 bare glyph icons | ✗ *deliberate* |
| total height | 502px | 597px | ✗ (+95, mostly the taller logo) |

Four departures are deliberate and recorded in the script header: **Prenotazioni** omitted
(booking is off-limits, D-32), **FAQ** omitted (`/faq/` is 404), **Shop** omitted (nav label
deliberately hidden, re-verified after 102), heading weight 400 not 700 (Londrina ships
100/300/400/900 — 700 is browser-synthesised).

Two residual defects worth fixing later: the newsletter button inherits the kit's orange
button style instead of the design's outline (kit selector out-specifies
`.tmk-newsletter--footer`), and there is **no white square logo mark as SVG** in the media
library, only a wide PNG wordmark.

### Gutters — the design cannot be "matched"; it is self-inconsistent

| Source | Value |
|---|---|
| `tokens/spacing.css` | `--space-5: 24px` |
| `tokens/layout.css` | `--grid-gutter: var(--space-5)`; `.tmk-container{padding-inline:var(--space-5)}` |
| `tokens/layout.css` | containers narrow 680 · standard 1040 · **wide 1280** |
| `shell/refinements.css` | **sets no horizontal gutter at all** — no `padding-inline`, no `margin-inline` |
| rendered Header / Footer | **32px** (`--space-6`) — they do **not** use `.tmk-container` |

So the source gives two answers: the utility class says **24px**, the shipped header and
footer render **32px**. Effective measured gutters:

| Width | Design header | Design footer | WP header | WP footer |
|---|---|---|---|---|
| 375 | 32 | 32 | 20 | 16 |
| 768 | 32 | 32 | 20 | 40 |
| 1440 | 32 | **112** | 40 | **80** |

The design's own header and footer disagree at 1440 (32 vs 112) — the same class of defect
as WP's 40 vs 80. And the design is **wider** than WP at 375 and 768, so adopting it would
make mobile content narrower, the opposite of the brief.

**Mechanism, now fully characterised.** In Elementor a boxed container's padding sits
*outside* the max-width box, so at 1440 the gutter is
`padding + (1440 − 2·padding − 1280)/2 = 80px` **for any padding value**. Changing padding
provably cannot change the desktop gutter — only `boxed_width` / kit `container_width` can.
That is the "hidden 40px". `#tmk-header` escapes it only because it is `content_width:full`.

**Status: OPEN — awaiting a decision, no number invented.** `120-footer-parity.php`
deliberately preserved today's 40/40/16 padding so the footer rebuild introduced no gutter
change. Three candidate scales are in the session report; all are built from `--space-5`
(24px), the design's own token.

**Superseded by `130-header-shell.php` below — the decision landed.**

## 130 — header shell + gutters · `_BUILD/130-header-shell.php`

Client decisions applied: kit `container_width` 1280→1440, header/footer boxed inline
padding→24px; header PRENOTA stays orange (D-32, untouched); footer keeps the PNG wordmark
(untouched). Root cause of the mobile nav crush: Elementor 4.2.0 does not register
`_flex_size` server-side (zero PHP hits, confirmed by grep) — the real fix is a `nowrap` +
auto-width override in kit Custom CSS, since Elementor's own default
`--flex-wrap-mobile:wrap` decides line-breaks using each item's unshrunk size regardless of
any flex-shrink setting. Verified with Playwright at 375/768/1024/1440/1920 on `/` and
`/ristoranti/milano/tierra/`.

| Width | Property | Design | WP before | WP after |
|---|---|---|---|---|
| 375 | header height | 103 | 197 | 91 |
| 768 | header height | 103 | 160 | 91 |
| 1024 | header height | 103 | 160 | 91 |
| 1440 | header height | 103 | 160 | 90 |
| 1920 | header height | 103 | 160 | 90 |
| 375 | header gutter (inline padding) | 32 | 20 | 24 |
| 768 | header gutter | 32 | 20 | 24 |
| 1024 | header gutter | 32 | 24 | 24 |
| 1440 | header gutter | 32 | 40 | 24 |
| 1920 | header gutter | — | 40 | 24 |
| 375 | mobile toggle size | — | 0×0 (crushed) | 33×33 |
| 375 | mobile toggle opens, lists all 5 nav items | — | no | yes |
| 375 | footer gutter | 32 | 16 | 24 |
| 768 | footer gutter | 32 | 40 | 24 |
| 1440 | footer gutter | 112 | 80 | 24 |
| 1920 | footer gutter | — | — | **240** |
| all | footer visible columns 375/768/1440 | 1/3/5 | 1/3/5 | 1/3/5 (unchanged) |
| all | newsletter button background | transparent | `#E7591D` orange (kit `button` out-specified it) | transparent |
| all | kit `container_width` | — | 1280 | 1440 |
| all | horizontal scroll, both pages, all widths | none | none | none |

⚠ **1920 footer gutter is 240px — flagging, not silently fixing.** Confirmed empirically
(not derived): `gutter = max(padding, (viewport − container_width)/2)`. The design never
defines anything past its 1440 "wide" tier, so there's no reference value to match, and this
is the same class of unbounded growth the old 1280/40px setup already had past 1440, just
from a lower floor. Options: leave it; raise `container_width` again for very wide screens
(moves the problem, doesn't remove it); or add a clamped Custom CSS cap on the boxed rows'
outer margin past some viewport. Not picked — ask Carlo.

Files: `130-header-shell.php`; backups `header25-backup.json` (pre-130 original),
`header25-backup-last.json`, `footer27-backup-last.json` (original stays `footer27-backup.json`
from 120), `kit-header-shell-backup.json`.

---

## 131 — Homepage (#36) body parity pass · `_BUILD/131-homepage-parity.php`

Not a rebuild — `100-homepage.php`'s composition, copy, and both client overrides (Prenota
navbar-only, the 5 old-site slides) are untouched. This is a surgical settings patch on the
existing `_elementor_data` (same pattern as `130-header-shell.php`): walk the tree by known
element ID, mutate only what a measured delta proved different, re-save.

**Measurement method:** the header/footer parity pass (§ above) opened the design's static
export directly; that fails for the homepage *body* because `TMK Homepage.html` renders via
Babel-in-browser (`<script type="text/babel">`), which fetches `.jsx` files over XHR — blocked
by CORS under `file://`. Served over a local HTTP server instead
(`python3 -m http.server` from the export's `project/` directory, found at
`~/Downloads/temakinho-website-generation-ingestion/project/`, not under `REDESIGN/TMK/`),
it renders with 0 console errors. Measured both pages at 375/768/1440 with Playwright
(`getBoundingClientRect` + `getComputedStyle`).

⚠ **Elementor `_css_classes` on a heading/text widget lands on the wrapper `<div
class="elementor-widget-heading">`, not the inner `<h2>`/`<p>` itself.** A `querySelector`
on the class name grabs the wrapper and reads its (inherited, generic) computed style, not
the styled child — this looked like a global-not-applying bug on first measurement and
wasn't; querying the actual `h2`/`p` tag gave the correct (and correctly-styled) values.
Card/band measurements avoided this by filtering for leaf text nodes instead of matching by
class, which is why those numbers were right on the first pass.

⚠ **The design prototype's own mobile card has a responsive bug, not a target to copy.** At
375 a `variant="city"` Card computed a width (472.5px) wider than its 311px grid column —
traced to a fixed aspect-ratio driving width off `min-height`, correct at 768/1440 (where the
ratio happens to land back inside the container: 268.19×ratio=704, 463.23×ratio=1216) but too
short at 375 to hold it. The same 375 full-page screenshot also showed large blank gaps below
the hero: `.tmk-reveal` (`shell/refinements.css:44`) starts at `opacity:0;
transform:translateY(26px)` until an `IntersectionObserver` adds `.tmk-in` on scroll —
Playwright's `fullPage` screenshot resizes the viewport instantly and does not replay that
scroll, so below-the-fold sections were captured pre-reveal. Neither is a WP defect (WP has no
`.tmk-reveal` equivalent and no horizontal scroll at any width, before or after this patch);
**375 numbers were excluded from the delta table for this reason** — 768/1440 were reliable.

**The same lazy-render trap reappeared measuring WP itself**, from the *opposite* direction: a
`fullPage` screenshot taken immediately after `networkidle` showed one or both city-band
background photos as a flat, undetailed fill (a photo *is* set — `background-image` — but
hadn't visually resolved by capture time). Scrolling through in `page.mouse.wheel()`
increments before capturing (the same technique `## 9` already documents for the header's
scroll-direction logic) resolved it on every retake. Screenshots in this section were all
taken after a scroll-through; static `networkidle`-only screenshots of this homepage should
not be trusted for the band photos.

### Delta table (design vs WP-before, 768/1440 only — see 375 caveat above)

| Section | Property | Design | WP-before | Fix |
|---|---|---|---|---|
| body width | every section below the hero | ONE width (contentWrap maxWidth 1280 → inner 1216 @1440, 704 @768 — same figure for hero/city/trio/closing) | THREE widths: hero 1280, city+trio 1040, closing 680 — reads as inset boxes inside a wider shell (worse since 130 widened the kit shell to 1440) | unify city/trio/closing to **1280** |
| city/trio | inline padding (sides) | `--space-6` = 32px, constant at every measured width | 24px | 32px |
| city/trio | section top+bottom padding | rhythm-structured token (clamp 52–72), ONE gap between adjacent sections | 40/40 → **doubled to 80px** between adjacent sections (40 bottom + 40 top) | 32/32 (doubles to 64, inside the token range) |
| closing | own padding | CTABanner component: 64 top/bottom (`--space-8`), 48 sides (`--space-7`) — exact measured match | 56/24 | **64/48** |
| closing | background | dark ink, measured `rgb(36,28,19)` = exact hex match to the existing `tmk_surface_inverse` global | none (transparent — inherits the cream page surface) | `background_background:classic` + `tmk_surface_inverse` |
| closing | title/body colour | cream, measured `rgb(253,251,245)` = exact hex match to `tmk_text_inverse` | ink (`tmk_text_primary`) | `tmk_text_inverse` (no separate muted-body global exists in the kit — title and body both use the one that does) |
| trio | "Le altre città" heading colour | ember, measured `rgb(231,89,29)` = exact hex match to `primary` | ink (`tmk_text_primary`) | `primary` |
| hero | width/padding/sr-only h1 | 1280 boxed, 48/24 padding, sr-only h1 | matches already | none |
| card/band typography+colour | — | Basis Grotesque / Londrina Solid role split, cream on-photo | matches already (same `card()`/`band()` helpers as 101/102) | none |
| section order | — | hero → city\* → trio → closing | hero → **slides** → city\* → trio → closing | none — slides are the confirmed client add-on, not in Homepage.jsx |

No new colour or width value was introduced — 1280 is the same `WIDE` token the hero already
used; `primary`/`tmk_surface_inverse`/`tmk_text_inverse` are existing kit globals used
elsewhere on this page. Zero hex, zero font-family in the re-saved `_elementor_data` (guard
enforced, as with 100/101/102); every referenced global confirmed reaching the generated CSS.

**Reported, not fixed** (unchanged from the homepage section above, plus one new item): location
cards repeat the city photo · `garland-frame` has no attachment · `homepage-data.js` omits
Milano Linate and Roma Fiumicino · kit global typography `secondary` is mislabelled · **and
newly observed**: the design's `rhythm-airy` bottom spacer (72–112px added by contentWrap
*after* the closing band, on top of its own padding) has no WP equivalent — Elementor top-level
sections have no shared "gap after last child" without wrapping the whole page body in one
outer flex container, which is a bigger structural change than a parity patch.

**Superseded:** §5's "Widths from the layout tokens: wide 1280 (hero) / standard 1040 (city +
trio) / narrow 680 (closing)" and the homepage section's identical line are both from
`100-homepage.php` and are now out of date — city, trio, and closing are all **1280** as of
this patch.

Verified 375/768/1440 (post scroll-through, per the caveat above): 0 console errors, 0 failed
requests, no horizontal scroll, exactly one `<h1>`, 0 broken images. Screenshots
`~/Desktop/tmk-qa/homepage-131-{375,768,1440}.png`.

Files: `131-homepage-parity.php`; backup `homepage-backup-131.json` (this script's own
pre-patch state, rewritten every run) — `homepage-backup.json` (the original pre-`100`
layout) untouched.

---

## 104 — Lavora con noi, Careers Hub, as a Theme Builder ARCHIVE template · `_BUILD/104-lavora-con-noi.php`

**STEP 0 finding: this route was never a Page.** The `job` CPT registers `has_archive =>
'lavora-con-noi'` (`content-model.php`) — the URL is the CPT archive, always was. No `page` post
for the slug exists. PATCH 1 (§10) only commented out `careers-routing.php`'s `template_include`
filter, so the request fell through to Hello Elementor's generic `archive.php`: confirmed live,
body class `post-type-archive-job`, no `data-elementor-type`, `<main>` body 3581 chars (down from
10349 pre-patch). Fixed by creating a real Theme Builder **archive** template (new
`elementor_library` post **#559**, "TMK Careers — Archive"), not a Page. 4 real `job` posts exist
(Sushi Chef #245, Maître di sala #246, Bartender #247, Cameriere/a #248) with real featured
images, `job_where`/`employment_type` meta and a `department` term — not empty, nothing invented.

**Condition string, verified from source, not guessed.** `Conditions_Manager::$conditions` is a
flat map keyed by each condition's own `get_name()`; `Post_Type_Archive`'s sub-condition for
`job` registers flatly as `job_archive` — same mechanism that gives the Location Single template
(#255) its 2-segment `"include/location"` string rather than a nested `"include/singular/
location"`. So the correct part array is `[['include', 'job_archive']]`, **not**
`[['include', 'archive', 'job']]` — a plausible-looking 3-segment guess would have saved without
error and simply never matched, the same silent-failure shape `PHP-PATCH-NOTE.md` already
documents for `_elementor_priority`.

**A second, more serious silent failure was hit and fixed in the script itself.** Elementor's
`Documents_Manager::get($id)` caches Document instances by post ID (default `$from_cache = true`).
`wp_insert_post()` for the new template triggers Elementor's own `save_post` hooks, which resolve
and cache a Document for that ID **before** this script's own `update_post_meta(...,
'_elementor_template_type', 'archive')` call ran — so the cached instance was the wrong document
class, with no `archive` location. `Conditions_Cache::regenerate()` (called inside
`save_conditions()`) reads through that same cache, so on the first run it silently rebuilt
`elementor_pro_theme_builder_conditions` **without** the new template — `save_conditions()` still
returned `true`, no error anywhere, condition meta looked correct, and the front end simply never
resolved the template. Confirmed by reproducing it (the option's `archive` key was entirely
absent) before fixing it: the script now calls `\Elementor\Plugin::$instance->documents->
get($TPL_ID, false)` — forcing a fresh, correctly-typed instance into the cache — immediately
before `save_conditions()`. The script's own verification step now checks the actual cached
option (`elementor_pro_theme_builder_conditions['archive'][$TPL_ID]`), not just that
`save_conditions()` returned truthy, since that return value is misleading in a different way
too: it is WordPress's `update_post_meta()` result, which is `false` on every idempotent re-run
where the value hasn't changed — not a failure signal by itself.

**Composition** from CareersHub.jsx (Hero → Culture → Pillars → Trust → Roles → Conversion, its
own order). **Copy handling, same rule as 101/102:**
- Hero H1 + subheadline are **verbatim** from `LAVORA CON NOI/page.md` — the archive's only
  heading ("Lavora con noi", an H3 wrapped in `<strong>`, no H1 on the source page) promoted to
  h1, and its intro paragraph word for word.
- Conversion band ("Candidatura spontanea") is **verbatim** from the archive's linked
  `/pages/jobs` — both paragraphs, "Mais Amor" included as the brand's own phrase. The archive's
  closing sentence naming reference cities (Milano, Bologna, Rimini, Roma, Reggio Calabria, Capo
  D'Orlando) is dropped — those last two aren't in the current `city` taxonomy and Lione is
  missing from that old list, so carrying it forward would be stale/misleading; flagged here
  rather than silently fixed or silently kept. The recruiting mailto is corrected to a working
  address: the archive's own href has a leading space and trailing full stop
  (`mailto: recruiting@temakinho.com.`) and is not valid as served — the visible email text and
  domain are real archived data and are kept, only the href is fixed.
- Open Roles are **real `job` CPT data** (title, permalink, featured image, `job_where` +
  `employment_type`), superseding the archive's own 2-card "Head quarter / Ristoranti" split
  (which pointed at a flat external `/pages/jobs` listing) — same D-30 logic as one location
  template beating 14 hand-built pages, applied to careers.
- Culture / Pillars / Trust bands are **DESIGN COPY** (CareersHub.jsx + careers-data.js — no
  archive counterpart), same handling as shop-data.js's fictional catalogue or contact-data.js's
  placeholder email.

⚠ **Flagging, not deciding: the Trust band's testimonial is a named, fictional placeholder.**
"Giulia — Restaurant Manager, Milano" is careers-data.js's own copy — there is no real employee
testimonial in the archive or in the `testimonial` CPT (which holds only press mentions: Gambero
Rosso, Elle Decor). A quote attributed to a specific named person is a stronger claim than generic
marketing copy and reads as genuine if left as-is. **Ask before this goes live** — either swap in
a real testimonial or drop the attribution.

**A third bug was hit and fixed: the Culture band's photo+text row was the flex-crush trap
`130-header-shell.php` already documents for the header, reproduced here in ordinary page
content.** The macaw image column was set `_flex_size:'shrink'` next to a `_flex_size:'grow'`
text column; at 768px the image column measured `width: 0` (crushed, still reporting
`visible`/`opacity:1`) and the text column — `flex-grow:1; flex-shrink:0` — refused to compress,
overflowing the viewport by 8px. Fixed per the documented pattern: the fixed column is
`_flex_size:'none'` (immune to the flex algorithm), the flexible column is `_flex_size:'shrink'`
with `width:100%` (takes the remainder via shrink, not grow).

Idempotent, deterministic element IDs (`tmklc###`). Zero hex, zero font-family in
`_elementor_data` (guard enforced). Job Detail (single-job) is untouched and out of scope.

Verified 375/768/1440 with Playwright (scroll-through, per the established lazy-render caveat):
`data-elementor-type="archive"` present, exactly one `<h1>` ("Lavora con noi"), 0 console errors,
0 page errors, 0 failed requests, no horizontal scroll at any width, all 4 role cards link to
real permalinks. The 2 "broken image" hits at 375 (`leaves-banana`, `snake` decorative
illustrations) are a pre-existing sitewide condition, reproduced identically on the already-QA'd
Contattaci page — their parent is `display:none` at mobile, so the browser never loads them; not
introduced by this build. Screenshots `~/Desktop/tmk-qa/lavora-con-noi-{375,768,1440}.png`.

Files: `104-lavora-con-noi.php`; backup `lavora-con-noi-backup-last.json` (rewritten every run —
there is no pre-existing "original" to preserve, this is a new template).

---

## 105 — Editorial Article, native `post` SINGLE template · `_BUILD/105-editorial-article.php`

A Theme Builder **SINGLE** template (`_elementor_template_type = single-post`) for the native
`post` post type — the "Storie" magazine article. Unlike 104's `archive` condition, the
correct condition string was traced through `elementor-pro/modules/theme-builder` source rather
than guessed: `conditions/singular.php`'s `Post` sub-condition (one per public post type) has
`get_name() === $post_type->name`, i.e. literally `'post'`, and `Conditions_Manager` stores every
condition in one flat map by that name regardless of nesting — so the correct part array is
`[['include', 'post']]` → `include/post` (2 segments), **not** the 3-segment
`include/singular/post` a plausible-looking guess would produce (same silent-failure shape 104
already documents for `job_archive` vs `archive/job`). Cross-checked against the DB: Location
Single (#255) stores `include/location` and buckets under cache key `single`, confirming the
pattern. Verified after saving: `_elementor_conditions` matches, `'post'` resolves in
`Conditions_Manager`, and template #560 is present in the cached `single` bucket — not just that
`save_conditions()` returned truthy.

**Fully dynamic, per the task's instruction** — title/image/content/date/taxonomy come from
Elementor Pro's own `theme-post-title`, `theme-post-featured-image`, `theme-post-excerpt`,
`theme-post-content` and `post-info` widgets, not the plugin's `tmk-*` widgets (no article/story
widget exists among them, and `tmk-breadcrumb`'s `current_post` mode is hardcoded to the
`location` CPT only — extending it would be a plugin PHP edit, the last resort tier). Dynamic tag
references are built with Elementor's own
`Plugin::$instance->dynamic_tags->tag_data_to_tag_text(null, $name, $settings)` — the exact call
Elementor Pro's own widget classes use internally to wire their control defaults (read from
`post-title.php`/`post-featured-image.php`/`post-excerpt.php`) — rather than hand-assembling the
`[elementor-tag …]` bracket text.

⚠ **STEP 0 finding, flagged prominently per the task's own request to report it: none of the 7
published `post` records trace to the real archive.** The archived blog articles live at
`_CAPTURE/RAW/www.temakinho.com/blogs/{mais-amor,news,food-drinks}/*.html` (14 real Shopify
articles). Post #232's title/byline/pull-quote match Claude Design's own `pages/article-data.js`
placeholder dataset verbatim; posts #233–238 are additional fabricated pieces matching neither
the archive nor that dataset, including an invented quote attributed to a named "Restaurant
Manager" who does not exist. Same class of issue as the Careers Trust testimonial (§104) and the
pre-`80-` location data. **Not corrected here** — a dynamic template renders whatever `post` data
exists by design; fixing the underlying 7 posts (replace with real archive content, or mark as
placeholder) is a separate decision for Carlo.

Two disclosed simplifications, not silent gaps:
- **Byline** — only #232 has a `byline` postmeta value; the other 6 have neither `byline` nor a
  WP author display name (checked — both empty). The "Post Custom Field" dynamic tag's own
  `fallback` setting is set to "Redazione Temakinho" (generic, unattributed), never a fabricated
  name. Date uses the "Post Date" tag with format `d/m/Y` (numeric) rather than a word format —
  sidesteps `tmk_date_it()`'s documented site-locale problem (not it_IT → English month names)
  without touching the global language setting.
- **Related Location bridge** — `EditorialArticle.jsx`'s version is a full photographic city Card
  resolved relationally from the `related_city` postmeta slug. No native Elementor Pro dynamic tag
  can resolve slug → term → a specific location's photo/name without ACF Pro's relationship field
  (undecided, `PHP-PATCH-NOTE.md`) or a custom PHP widget (last resort). Built instead as a
  governed "Esplora" link whose href is dynamic (Post Custom Field with `before`/`after` wrapping
  the value into `/ristoranti/{slug}/`, `fallback` to the plain `/ristoranti/` index when empty).
  Elementor Pro's Display Conditions module (`display-conditions/conditions/dynamic-tags-
  condition.php`) was investigated as a way to hide the block outright when the field is empty —
  confirmed available on this license, but its legacy-element storage format (`e_display_
  conditions`, reached through a `$conditions_json[0]` access whose exact shape isn't nailed down
  by reading the source alone) could not be verified with the same confidence as the rest of this
  script, so it was deliberately **not** hand-authored — a working generic fallback beats a
  guessed, possibly-silently-broken condition.

`theme-post-content` renders `post_content` uniformly (one typography group), so the lead
paragraph / pull-quote / full-bleed figure differentiation from `EditorialArticle.jsx` is done as
a small, idempotent, marker-delimited Kit #5 Custom CSS block scoped to `.tmk-article-body`,
referencing only existing tokens (`var(--text-h3)`, `var(--font-voice)`, etc. — zero hardcoded
values), per §0's own preference order (Custom CSS above a build script, above PHP).

Idempotent, deterministic element IDs (`tmkea###`). Zero hex, zero font-family in
`_elementor_data` (guard enforced).

Verified on a real story URL (375/768/1440, scroll-through per the established lazy-render
caveat): `data-elementor-type="single-post"`, exactly one `<h1>`, 0 console errors, 0 page errors,
0 failed requests, no horizontal scroll, 0 broken images. Kicker renders "CULTURA · 18/07/2026",
byline "di Beatriz Costa · 18/07/2026" on #232 and the "Redazione Temakinho" fallback on posts
without one, breadcrumb "Home / Storie / {title}", related-location href resolves per-post
(`/ristoranti/milano/`, etc.) and falls back correctly. Confirmed all 7 Story Hub cards
(`/storie/`) link to real permalinks that now resolve through this template. Screenshots
`~/Desktop/tmk-qa/editorial-article-{375,768,1440}.png`.

Files: `105-editorial-article.php`; backup `editorial-article-backup-last.json` (rewritten every
run — new template, no pre-existing original).

---

## 106 — Eventi: Hub (archive) + Detail (single) · `_BUILD/106-eventi.php`

**Route, verified not assumed:** no `page` named "eventi" exists; `event` registers
`has_archive => 'eventi'` — the exact same shape 104 documented for `/lavora-con-noi/`. Needed a
Theme Builder **ARCHIVE** template, not a Page.

⚠ **5 published `event` posts exist (#240-244) and all 5 are fabricated** — titles, dates, venues
and copy match `pages/event-data.js` (`TMKEventHubData`/`TMKEventDetailData`) verbatim, same
pattern as the 7 fake `post` records (§105) and the Careers Trust testimonial (§104). **The real
event content — 7 distinct events from the 8 Shopify tickets 102 excluded from Shop (I PORCI
COMEDY deduplicated to its `available:true` copy) — is NOT imported as `event` posts.** Real
titles/dates/venues extracted from each product's own embedded description in
`_CAPTURE/RAW/www.temakinho.com/products/*.html`; images already in the media library (#378-383,
388). **Not created here**, per instruction — flagged for a decision, nothing fabricated.

**Conditions verified against source, not the task's suggested strings** (`include/archive/event`
/ `include/singular/event` — both 3-segment guesses of the exact shape 104/105 already warn
about): `Post_Type_Archive::get_name()` → `event_archive`; `Post::get_name()` → `event`. Correct
arrays are `[['include','event_archive']]` and `[['include','event']]` (2 segments each).
Confirmed after saving: both conditions resolve, both templates land in their expected cache
buckets (`archive` #561, `single` #562).

**D-32 applied.** `EventDetail.jsx`'s hero CTA (`/prenota/milano/aqua`) and its `BookingWidget`
are booking routes/targets — dropped entirely, no replacement (navbar Prenota already covers it),
same precedent as the Homepage build. The Trust testimonial (100% event-data.js placeholder) was
also dropped rather than shipping another flagged fabrication.

**Hub** built the same way 104 built the Careers Hub — real current `event` posts baked in at
build time (title/thumbnail/kind/venue/date), not a live Posts/Loop widget (its Cards-skin
control-naming scheme has no verified precedent in this codebase — same reasoning 105 used to
skip Display Conditions). Featured = whichever event currently sorts earliest by `event_start`.
**Detail** is fully dynamic (`theme-post-title/-excerpt/-content/-featured-image`, Post Custom
Field for `event_kind`/`event_venue`/`event_start`/practical-info fields). "Altri eventi" is a
single link to `/eventi/` — matches `EventDetail.jsx`'s own related cards, which ALL point at that
generic href verbatim, not a reduction below the source. No gallery (`event_gallery_ids` has no
ACF/dynamic-tag path, same constraint §6 documents for `location`).

Idempotent, deterministic element IDs (`tmkev###`). Zero hex, zero font-family.

Verified both routes 375/768/1440 (scroll-through): `data-elementor-type="archive"` /
`"single-post"`, exactly one `<h1>` each, 0 console/page errors, 0 failed requests, no horizontal
scroll, 0 broken images. All 5 Hub cards link to real permalinks. Screenshots
`~/Desktop/tmk-qa/eventi-hub-{375,768,1440}.png`, `~/Desktop/tmk-qa/evento-detail-{375,768,1440}.png`.

Files: `106-eventi.php`; backups `eventi-hub-backup-last.json`, `eventi-single-backup-last.json`.

---

## 107 — Job Detail (single) · `_BUILD/107-job-detail.php`

4 published `job` posts (104's build: Sushi Chef, Maître di sala, Bartender, Cameriere/a) — real
`job_where`/`employment_type`/`department` on all 4. ⚠ **Sushi Chef's body matches
`careers-data.js`'s `TMKJobDetailData` placeholder verbatim** (the "Cosa farai/Chi
cerchiamo/Cosa offriamo" h3+ul, word for word); the other 3 correctly have empty content —
nothing fabricated for them. Flagged, not corrected.

**No application form exists anywhere.** `JobDetail.jsx`'s `JobApplicationForm` has no real
counterpart: `LAVORA CON NOI/page.md`'s own archivist notes record `document.querySelectorAll(
'#MainContent form').length === 0` on both `/pages/lavora-con-noi` and its linked `/pages/jobs`.
`application_method='native'` on all 4 posts points at a form that doesn't exist in this codebase
or the archive — not built here (inventing its fields/labels/consent copy would be exactly the
fabrication this task says not to do). The apply CTA uses the real, already-verified
`mailto:recruiting@temakinho.com` (104) instead.

**Condition verified against source** (not the task's suggested `include/singular/job`):
`Post::get_name()` → `job`, flat map → `[['include','job']]` → `include/job` (2 segments). Same
`Post` class 105 verified for `post` and 106 verified for `event`. Confirmed: resolves, lands in
the cached `single` bucket (#563).

Dynamic per the task's instruction: `theme-post-title/-excerpt/-content/-featured-image` +
`post-info` with a `terms` item for the `department` taxonomy + Post Custom Field for
`job_where`/`employment_type`. **Bug caught by testing, not assumed:** `post-info`'s repeater
`custom_text` control needs its OWN per-row `__dynamic__` key
(`$row['__dynamic__']['custom_text']`) — `Controls_Stack::parse_dynamic_settings()` recurses into
each repeater row with that row as `$all_settings`. Setting the bracket tag text as the row's
plain `custom_text` value (the first attempt) printed the raw `[elementor-tag …]` string on the
live page instead of rendering it; fixed and re-verified.

Disclosed simplification: "Related Roles" is a single link to `/lavora-con-noi/`, not per-role
links like `JobDetail.jsx` — genuinely a step down from the source (unlike 106's equivalent,
which matched the design's own generic href), same Skin-widget-risk reasoning as 105/106.

Idempotent, deterministic element IDs (`tmkjd###`). Zero hex, zero font-family.

Verified 375/768/1440 (scroll-through): `data-elementor-type="single-post"`, exactly one `<h1>`
("Sushi Chef"), 0 console/page errors, 0 failed requests, no horizontal scroll, 0 broken images,
kicker renders "Cucina · Milano · Aqua · Full-time", apply CTA → real mailto. Confirmed all 4
Careers Hub cards link to real job permalinks. Screenshots
`~/Desktop/tmk-qa/job-detail-{375,768,1440}.png`.

Files: `107-job-detail.php`; backup `job-detail-backup-last.json`.

---

## 110 — 404 + Search Results, native Elementor Theme Builder templates · `_BUILD/110-404-search.php`

Two small utility templates: `Error_404` (`_elementor_template_type = error-404`, location
`single`) and `Search_Results` (`search-results`, location `archive`). The task's suggested
`_elementor_template_type` values were exactly right — they're each Document subclass's own
`get_type()` — but `_elementor_conditions` is a different namespace, verified against source
rather than guessed (same trap 104-107 already documented for `job_archive` vs `archive/job`):
`conditions/singular.php`'s `Singular::$sub_conditions` includes `not_found404`
(`check() → is_404() || is_missing_term_or_author_archive()`); `conditions/archive.php`'s
`Archive::$sub_conditions` includes `search` (`check() → is_search()`). Correct arrays:
`[['include','not_found404']]` → `include/not_found404`, `[['include','search']]` →
`include/search` — the task's suggested `error-404`/`search-results` guesses would have saved
without error and never matched. Cache bucket comes from each *document* class's own
`$properties['location']` (`Single_Base` → `single`, `Archive` → `archive`), independent of the
condition string. Same `documents->get($id, false)` cache-bust immediately before
`save_conditions()` that 104 needed (Elementor caches a Document by post ID on `wp_insert_post`,
before this script's own `_elementor_template_type` write is visible).

**No old-site archive copy exists for either page** — checked (`ALTRE PAGINE/` holds only "Lista
allergeni e decongelati" and "Live Music", nothing 404/search-shaped) — so both carry **NEW
COPY**, written for this build in the CLAUDE.md §8 vocabulary, styled on (not copied verbatim
from) the Claude Design blueprint's own `pages/util-data.js` microcopy — UI wayfinding text, not
a factual claim, so reusing its spirit isn't the fabrication risk flagged elsewhere this session
for named testimonials/events. One deliberate departure: `Search.jsx`'s per-card action label
"Apri" isn't a governed §8 verb — used "Esplora" instead, matching every other card link built
this session.

**404** is fully static (no post context to be dynamic about): numeral + eyebrow use
`tmk_border_strong` (tatami-700 — the closest kit global to the design's ungoverned
`tatami-600`; §4's 24-colour list confirmed no such global exists), the one real `<h1>` uses full
`tmk_text_primary`, primary button "Torna alla home" → `/`, secondary text-link "Esplora i
ristoranti" → `/ristoranti/` (same accent-typography/primary-colour link pattern as 107's related
link). Verified `curl -w "%{http_code}"` on a bogus slug returns **404**, not 200 — confirmed
`data-elementor-type="error-404"` renders and HTTP status is real 404 simultaneously.

**Search** renders real matches, not a mock: `location`/`job`/`event` are all `public => true`
with no `exclude_from_search` override in `content-model.php`, so WordPress's own default
(`exclude_from_search = !$public`) includes all three in native search alongside `post` —
confirmed empirically before building anything (`WP_Query('s'=>'temaki','post_type'=>'any')` → 12
matches: 5 location, 3 post, 2 page, 1 job, 1 event). Built on Elementor Pro's own `archive-posts`
widget, whose `query_posts()` reads the global `$wp_query` directly — the real search query, never
a hand-built post-type allow-list to keep in sync. Skin = Cards; live re-search via Elementor
Pro's `search-form` widget (real GET form, `name="s"`, submits to `home_url()`).

⚠ **Bug caught by testing, not assumed:** the active-skin control is `_skin` (underscore-prefixed
— `widget-base.php::register_skin_control()`), not `skin`. First attempt set the plain `skin` key;
no error anywhere, `_skin` silently kept its own control default (the *first* registered skin,
Classic, not Cards), so the page rendered under Classic and the correctly-named
`archive_cards_read_more_text` override was stored but never read — cards showed Classic's own
English default "Read More »". Caught by checking the rendered card action text against what was
set, not by trusting that the write succeeded because nothing errored. Fixed
(`_skin => 'archive_cards'`); re-verified the served HTML shows class `elementor-posts--skin-cards`
and every card's action text is "Esplora".

`nothing_found_message` (a plain, non-skin control) carries the empty-state copy — non-blaming,
no query-term interpolation (no dynamic tag exists anywhere in this codebase for "current search
query", so building `"Nessun risultato per '{$s}'"` would mean hand-splicing unescaped user input
into a control that HTML-escapes by default; skipped rather than risk it). Verified empty state
directly: `/?s=zzznonexistentqueryxyz123` renders "Nessun risultato per questa ricerca. Prova con
il nome di un piatto, una città, un evento o una posizione aperta." A permanent "Esplora i
ristoranti" link sits below the results grid regardless of result count, standing in for
`Search.jsx`'s `SystemState` empty-state action (which the escaped `nothing_found_message`
control can't itself carry as a link).

**Not fixed, flagged:** Cards skin's default `meta_data` shows a generic WordPress author avatar
per card — meaningless for a `location`/`job`/`event` result, but real (unfabricated) post meta,
not fixed here per the same "don't guess further skin-prefixed control names beyond what's
verified" doctrine 105-107 already established.

Idempotent, deterministic element IDs (`tmk404###` / `tmksr###`). Zero hex, zero font-family in
`_elementor_data` (guard enforced).

Verified 375/768/1440 with Playwright: 404 → HTTP 404 + `data-elementor-type="error-404"`,
search → HTTP 200 + `data-elementor-type="search-results"`; both exactly one `<h1>`, 0 console
errors, 0 page errors, 0 failed requests, 0 broken images, no horizontal scroll at any width.
Search grid collapses 3 → 2 → 1 columns per the repaired kit breakpoints (§099). Screenshots
`~/Desktop/tmk-qa/404-{375,768,1440}.png`, `~/Desktop/tmk-qa/search-{375,768,1440}.png`.

Files: `110-404-search.php`; backups `404-backup-last.json`, `search-backup-last.json` (both
rewritten every run — new templates, no pre-existing originals).

---

## 140 — Ristoranti (14 `location` posts) content audit · `_BUILD/140-locations-content.php`

Requested as a bulk gap-fill ("fill the 14 Ristoranti pages with all remaining content and
images from the archive"). **Audit-first, per the task's own gate — nothing was written until
two full tables were printed and reviewed.** The audit found there was almost no gap: this is a
report of that finding, not a rebuild. Architecture untouched — no Elementor pages built, no
routing files touched, `location-render.php`/`city-routing.php`/`location-routing.php` all
unmodified. `booking_url` not read, not written, per D-32.

**Text fields — already correct, verified line-by-line against every `page.md`.**
`address`/`phone`/`tagline`/`hours` were checked against all 14 archive pages, not just spot-
checked: every value matches verbatim, including the genuine archive absences (Fiumicino has no
phone or tagline published anywhere; Capo d'Orlando has no phone; Linate has no tagline) — these
are correctly empty, not unfilled gaps. `40-populate-locations.php` (an earlier session) had
already done this work correctly.

**`short_description` and `accessibility_notes` have no source anywhere in the archive.** Not one
of the 14 `page.md` files contains anything resembling either field — there is nothing to fill
"verbatim from page.md" here for any of the 14, on any restaurant.

⚠ **Rimini (#22) held fabricated values for exactly those two fields** — "Ottoni caldi, velluti
corallo…" and "Ingresso a livello strada, sala e servizi accessibili…" appear nowhere in
`RISTORANTI/Rimini/page.md`. Same class of invented placeholder as the old "000 000" phone this
task warned about (that one was already fixed in an earlier session; these two were not, until
now). **Cleared to empty** on Carlo's decision — no archive source exists for either field on any
restaurant, so inventing one is worse than leaving it blank.

**Images — 0 missing.** Cross-checked every distinct archive filename (109 raw files under
`RISTORANTI/*/images/`, deduped for the `w1200_` resolution-variant duplicates that several
folders carry) against the media library by `_wp_attached_file` basename, programmatically, not
by spot check. Zero are missing. Every restaurant's one apparent "gap" (distinct archive count =
DB gallery count + 1) is the same thing every time: the hero logotype/wordmark graphic,
consistently excluded from the ambiance photo gallery by the earlier population script — a
deliberate, uniform curatorial choice, not an oversight. Reggio Calabria (#23) and Capo d'Orlando
(#24) reusing Bologna's (#20) 3 banner images plus a generic neutral signage graphic
(`a44a_TMK-Insegna-Vettoriale_Neutra.png`) as their hero — confirmed exactly as flagged in the
task, real fact, not fabricated, no fix applied. Linate's 7 gallery images are confirmed genuinely
location-specific, matching the task's own claim.

⚠ **Linate (#15) gallery alt text was corrupted, not "the literal string null" as archived.**
`images.md` documents 5 of Linate's 7 gallery images as carrying the literal 4-character string
`null` as their published alt (a Shopify-era bug on the original site, recorded faithfully in the
archive, not corrected there). The database instead held the archivist's full markdown annotation
— `` `null` *(literal string, as published)* `` — meaning a prior import step copied the
human-readable table-cell text instead of the value it was describing. **Fixed to the bare
4-character string `null`** on all 5 affected attachments (#313–317) — this is what "record
faithfully, don't write prettier alt text" actually requires; the corrupted value wasn't
prettier, it was a different, wrong string. Attachments #312 and #318 correctly have empty alt
(matches archive) and were left untouched.

**ACF editability, as requested:** `address`, `phone`, `tagline`, `short_description` and
`accessibility_notes` are plain-text ACF fields — client-editable in wp-admin. `hours` (label/
value rows) and `gallery_ids` (attachment ID list) are plain `register_post_meta` arrays with no
ACF Repeater/Gallery field behind them (ACF free has neither) — they render correctly
(`location-render.php` reads raw postmeta directly, not through ACF), but **the client cannot
edit opening hours or reorder/swap gallery photos through wp-admin** after this or any prior run.
Not installing ACF Pro or anything else to change this, per instruction — flagged only.

**Verified rendering, not DB values** — 7 real location URLs (exceeds the required 5), across
Milano/Roma/Bologna/Rimini/Reggio Calabria, all HTTP 200, 0 PHP warnings/notices, 0 broken images:
`/ristoranti/milano/aqua/`, `/ristoranti/milano/linate/`, `/ristoranti/roma/borgo/`,
`/ristoranti/roma/fiumicino/`, `/ristoranti/bologna/temakinho-bologna/`,
`/ristoranti/rimini/temakinho-rimini/`, `/ristoranti/reggio-calabria/temakinho-reggio-calabria/`.
Address/phone/hours/gallery images confirmed present in the served HTML for every one (grep, not
assumed); Linate's fixed `alt="null"` confirmed rendering on its full-size gallery `<img>`;
Rimini's cleared fields confirmed absent (no fabricated text, no orphaned empty section).
Screenshots at 375/1440 for all 7 in `~/Desktop/tmk-qa/locations/`.

Pre-existing, not introduced here: location pages still have no `<main>` landmark (§10's prior
finding, unchanged).

Files: `140-locations-content.php`; backup `locations-content-backup.json` (rewritten every run).
Idempotent — re-run confirmed zero changes on the second pass.

---

## 141 — Ristoranti (14 `location` posts): full archive content mapped into the design's own
## layout · `_BUILD/141-locations-copy.php` + PATCH 3

Layout read from Claude Design's `pages/LocationRimini.jsx` + `rimini-data.js` first, per
instruction — not invented. Section order (fixed, never reordered): Hero → Breadcrumb →
Orientation (NarrativeBand + Gallery) → Practical Info (hours → address → phone →
accessibility → map) → Menu preview → Trust → Conversion (booking, D-32, untouched) →
Closure (nearby). ⚠ `rimini-data.js`'s own content values are the **same fabricated
placeholder dataset** flagged and cleared in §140 (`address: 'Piazzale Fellini 1…'`,
`phone: '+39 0541 000 000'`, the exact `accessibility`/`narrative.body` strings already
known to be invented) — used for layout only, never as a content source.

**Content model — six archive content types, checked against `content-model.php` before
proposing anything:** `email` and `map_embed` were already registered but never populated
or rendered — not new fields. Three genuinely new fields added, `tmk_location_*` prefixed
per instruction: `tmk_location_logotype_id` (attachment ID), `tmk_location_neighbourhood_
note` (string — a distinct field from `short_description`, per instruction: introduction
sells, neighbourhood orients), `tmk_location_has_live_music` (boolean). Full diff shown and
approved before writing (Part 7 gate) — see `PHP-PATCH-NOTE.md` PATCH 3 for the complete,
applied diff across `content-model.php`, `location-render.php`, `practical-info-render.php`.

⚠ **Discovered mid-verification: the live page doesn't run `tmk_render_location()` at all.**
Elementor Pro being active means `location-routing.php`'s own `template_include` filter
yields to an Elementor Theme Builder Single template (`elementor_library` #255) built from
native `tmk-*` widgets, each re-fetching its own field bundle via `tmk_location_fields()`
(`inc/elementor-widgets.php`) — a second mapping that mirrors but is independent of
`tmk_render_location()`'s. PATCH 3 initially had zero effect on the served page for exactly
this reason (confirmed by direct reproduction — `tmk_render_location(11)` returned correct
markup in every context tested, including a script executed through the live webserver
itself, while the actual URL, even after an explicit `opcache_reset()`, did not — because it
was never calling that function). Extended the same approved fix into `tmk_location_fields()`
and the `tmk-narrative`/`tmk-practical`/`tmk-menu-preview` widget classes — mechanically
required plumbing for already-approved content to appear anywhere, not a new design
decision. Full detail in `PHP-PATCH-NOTE.md`.

**Text rules, so the mapping is auditable:** `\n\n` = a real paragraph break (blank line in
the archive). A single `\n` is used only where the archivist's own note says a line break is
a `<br>`-style soft break as published (Bologna, Rimini, Reggio Calabria, Capo d'Orlando's
neighbourhood note) — every other page.md line wrap is the archivist tool's own text-wrap,
rejoined with a space. Straight apostrophes kept straight exactly where archived that way
(Tierra's "un'opzione"; Linate's, Lione's, Fiumicino's "dall'incontro"). Reggio Calabria and
Capo d'Orlando's `short_description` excludes the description block's opening sentence
because it's already stored as `tagline` — avoids the same sentence appearing twice on one
page. Rimini's "Galleria Savoia" mention kept as a real link (survives `wp_kses_post()`,
already run inside `tmk_render_narrative_band()`).

**Alt text — 11 of 13 logotypes corrected, 1 left alone, 2 share a deliberately generic
alt.** The archive's own alt ("Temakinho - Milan Brera" on 8 of them; "Temakinho Bologna" on
Tierra and the Reggio Calabria/Capo d'Orlando shared signage) is a copy-paste artifact on the
original Shopify theme — not copied forward, per instruction. New alt text written from each
restaurant's own identity. Bologna's archived alt was already correct — left untouched, not
"corrected" into something else. The Reggio Calabria/Capo d'Orlando shared file is generic
signage, not bespoke art for either restaurant — its new alt ("Insegna Temakinho") says so
honestly rather than claiming a specific identity neither location has.

**Explicitly NOT captured (D-32 and one redundancy call, both flagged before writing, not
silently dropped):** Lione's "Prenota il tuo tavolo telefonicamente / Chiama e prenota"
block is a booking mechanism — excluded outright, no exception for it being phone-based
instead of link-based. Lione's "Chiama e ordina" delivery CTA is real, non-booking content,
but reuses the exact phone number already captured in `phone` — dropped as redundant rather
than building a one-off field for a single restaurant.

**Sitewide, stored once (Part 3):** the menu paragraph ("Ogni tavola ha il suo menu…") is a
`define()` constant (`TMK_LOCATION_MENU_INTRO`) in `location-render.php`, printed by both the
shortcode path and the `tmk-menu-preview` widget — never per-restaurant postmeta.

**ACF editability, unchanged from §140's finding:** the three new fields are plain-text/
boolean/integer `register_post_meta` — `tmk_location_logotype_id` and `tmk_location_has_
live_music` are technical fields with no ACF UI control registered for them (by design —
the client sets these by uploading a photo/ticking a box would need a PHP-PATCH-NOTE-logged
ACF field-group edit, not done here); `tmk_location_neighbourhood_note` is a plain text field
alongside the existing ones. `hours`/`gallery_ids` remain client-uneditable in wp-admin for
the reason already recorded in §140 — ACF free has neither Repeater nor Gallery.

**Music flag rendering:** stored as a real boolean for all 14 (true only for Tierra #13 and
Fuego #14); rendered as plain, non-linked text ("Musica & atmosfera") since `/live-music/`
404s on this site — linking to a dead route would be a worse defect than omitting the link.

### Coverage — verified in the SERVED HTML, not DB values, all 14 URLs, HTTP 200, 0 warnings

| # | Restaurant | Introduction | Neighbourhood | Email | Logotype motif | Music |
|---|---|---|---|---|---|---|
| 11 | Aqua | ✅ | ✅ | ✅ aqua@temakinho.it | ✅ (corrected alt) | — |
| 12 | Arya | ✅ | ✅ | ✅ arya@temakinho.it | ✅ (corrected alt) | — |
| 13 | Tierra | ✅ | ✅ | ✅ tierra@temakinho.it | ✅ (corrected alt) | ✅ |
| 14 | Fuego | ✅ | ✅ | ✅ fuego@temakinho.it | ✅ (corrected alt) | ✅ |
| 15 | Linate | ✅ | absent *(archive has none)* | ✅ linate@temakinho.com | ✅ (corrected alt) | — |
| 16 | Borgo | ✅ | ✅ | ✅ borgo@temakinho.com | ✅ (corrected alt) | — |
| 17 | Monti | ✅ | ✅ | ✅ monti@temakinho.com | ✅ (corrected alt) | — |
| 18 | Ponte Milvio | ✅ | ✅ | ✅ pontemilvio@temakinho.com | ✅ (corrected alt) | — |
| 19 | Fiumicino | ✅ | absent *(archive has none)* | ✅ fiumicino@temakinho.com | ✅ (corrected alt) | — |
| 20 | Bologna | ✅ (soft breaks preserved) | ✅ | ✅ bologna@temakinho.com | ✅ (alt already correct) | — |
| 21 | Lione | ✅ (straight apostrophe kept) | absent *(archive has none)* | ✅ lyon@temakinho.com | ✅ (corrected alt) | — |
| 22 | Rimini | ✅ (soft breaks + real link kept) | ✅ | ✅ rimini@temakinho.com | ✅ (corrected alt) | — |
| 23 | Reggio Calabria | ✅ (opening sentence excluded, dedup) | absent *(archive has none)* | ✅ reggiocalabria@temakinho.com | ✅ (shared, generic alt) | — |
| 24 | Capo d'Orlando | ✅ (opening sentence excluded, dedup) | ✅ | absent *(archive has none)* | ✅ (shared, generic alt) | — |

No page shows an empty section where real content exists. Zero failures across HTTP status,
broken images, empty-narrative checks, and console errors at 375/1440 (Playwright, all 14).
Screenshots `~/Desktop/tmk-qa/locations-141/{name}-{375,1440}.png`.

Files: `141-locations-copy.php`; backup `locations-copy-backup.json` (rewritten every run).
Idempotent — re-run confirmed zero changes on the second pass. Plugin diffs logged in full
in `PHP-PATCH-NOTE.md` PATCH 3 (including the mid-session widget-layer extension).

---

## Location template — design refinement pass (CSS only, no PHP)

Content unchanged from §141 — this pass touched layout/visual CSS only, on the render path
that actually serves the live page (§141's PATCH 3 discovery: the widget path, not
`tmk_render_location()` directly — re-confirmed at the start of this pass before touching
anything).

### Delta table — Claude Design `pages/LocationRimini.jsx` (served locally, scrolled through
to trigger `.tmk-reveal`) vs. the live Rimini page, measured at 375×812 / 768×1024 / 1440×900

| Property | Design | WP (before) | Match? |
|---|---|---|---|
| Section order | Hero → Breadcrumb → Orientation(Narrative+Gallery) → Practical(+Map) → Menu → Trust → Booking → Nearby | identical | ✓ |
| Hero height | `min(72vh, 640px)` → 584.6 / 640 / 640px | 584.6 / 640 / 640px | ✓ exact |
| H1 font-size | 48.832 / 58.88 / 76.288px | identical | ✓ exact |
| Narrative band width | `--container-narrow` (680px), centred | 680px | ✓ exact — already token-correct |
| Narrative body measure | inline `max-width:560px`, left-start | `.tmk-narrative-band__body{max-width:560px}` — same value, already in the ported CSS | ✓ exact, **no gap found** |
| Narrative+Gallery arrangement | side-by-side (2-col `auto-fit minmax(300px,1fr)`) at ≥600px | always stacked (narrative full-width, gallery below) | ✗ structural — **not changed**, outside the 4 stated priorities, flagged not fixed |
| Neighbourhood note placement | *(component has no such field — design predates it)* | inside the Indirizzo cell, under the address link | N/A — **already matches the instruction's intent**, no gap |
| Gallery cell size/ratio | `auto-fill minmax(240px,1fr)`, square | same pattern, square | ✓ pattern match, but... |
| Gallery at low/odd counts | *(design's instance only ever showed 3, in a narrow paired column, so the defect never surfaced there)* | **grid `auto-fill` left 40–100% of the trailing row empty** at 3 images (Capo d'Orlando) and after the 5th image at 7 (Linate) | ✗ **fixed** |
| Hours presentation | plain label:value rows, no border, no background | `<table>` inherited Hello Elementor's `reset.css` generic zebra-stripe (`hsla(0,0%,50%,.071)` background, `hsla(0,0%,50%,.502)` border) — not a design-system value anywhere | ✗ **fixed** |
| Practical Info layout | small facts card + large map, ~30/70 split | facts + map roughly 50/50 (`auto-fit minmax(260px,1fr)`) | ✗ cosmetic, **not changed** — outside the 4 stated priorities |
| Colours (body text, surface, ink) | tokens | same tokens, same computed rgb values | ✓ exact |

### Fixes applied — both CSS-only, at the component layer, tokens untouched

1. **Gallery dead-space** (Priority 4) — `css/components/gallery.css`: `.tmk-gallery__items`
   changed from `display:grid` (`auto-fill minmax(240px,1fr)`) to `display:flex;flex-wrap:wrap`
   with `.tmk-gallery__item{flex:1 1 240px}`. Root cause: a grid track only collapses when NO
   row uses it — a short trailing row (7 images = 5+2) still leaves the empty tracks it shares
   with a fuller row above it. Flex rows are independent, so every row — 3 images alone, or the
   trailing 2 of 7 — grows to fill its own width. Verified: `galleryTrailingGapPx` is now `0`
   for every restaurant checked (3, 4, 7 images) at all three widths.
2. **Hours zebra-stripe** (Priority 3) — `css/components/practical-info-band.css`: added
   `.tmk-practical-info .tmk-practical-info__hours td, ...th{border:none;background:transparent}`.
   Root cause traced to source, not guessed: Hello Elementor's own `reset.css` ships a generic
   `table tbody>tr:nth-child(odd)>td` rule at specificity (0,1,4); a first-attempt single-class
   override at (0,1,1) won the border fix but lost the background fix on the element-count
   tie-break. Chaining the band's own wrapper class for (0,2,1) beats it outright — no
   `!important`. Verified on Tierra (4 day-ranges) and Reggio Calabria (1 day-range) — both
   clean, no borders, no stripe, at every width.

### Priorities 1 and 2 — verified already correct, nothing changed

- **Priority 1 (narrow measure):** `.tmk-narrative-band{max-width:var(--container-narrow)}` +
  `.tmk-narrative-band__body{max-width:560px}` were already in the ported CSS, matching the
  design's own inline values exactly. Measured actual rendered text: 560px wide, left-aligned,
  ~65 characters/line at the body font size — already within the comfortable range. No CSS
  changed for this priority; changing it would have made lines *shorter* than the design's own,
  not fixed a defect.
- **Priority 2 (neighbourhood with address, not introduction):** already correctly implemented
  by §141's PATCH 3 — `tmk_location_neighbourhood_note` renders as a paragraph inside the same
  `.tmk-practical-info__cell` as the address link, visually and semantically grouped with it,
  never inside the narrative/introduction band. Confirmed on all 5 verification restaurants.

### Not changed, flagged (real deltas, outside the 4 stated priorities)

- Narrative+Gallery render stacked (full-width, one above the other) rather than the design's
  side-by-side 2-column arrangement. Fixing this would be a structural layout change beyond
  "fix what the table shows as different" for the 4 named priorities — flagged, not applied.
- Practical Info's facts-card-vs-map proportion (roughly 50/50 here vs. the design's narrower
  card + larger map) — cosmetic, not one of the 4 priorities, not changed.

### D-32 / parity guardrails honoured

No booking CTA added or altered anywhere in this pass — the design source's hero/conversion
booking CTAs were read for layout reference only, per instruction, and ignored for content.
Reggio Calabria and Capo d'Orlando's shared Bologna banner photography and shared generic
signage graphic were left exactly as they are — the gallery fix changes how images size within
their row, never which images appear or how many.

### Verified — 5 extreme-case restaurants × 3 widths (375/768/1440), all passing

Tierra (4 day-ranges, 7 fields incl. music flag), Linate (sparse, 7 gallery images), Capo
d'Orlando (3 images), Lione (French phone, extra archive blocks — still correctly excluded per
D-32), Fiumicino (sparsest — no tagline, no neighbourhood, no menu block). All 15 checks: HTTP
200, exactly one `<h1>`, no horizontal scroll, 0 broken images, 0 empty narrative sections where
real content exists, 0 residual zebra-stripe background, 0px gallery trailing-row gap. Screenshots
`~/Desktop/tmk-qa/locations-design/{name}-{375,768,1440}.png`.

Files touched: `css/components/gallery.css`, `css/components/practical-info-band.css`. No PHP
changed this pass — no diff gate needed. `css/tokens/` untouched, per standing rule.

---

## Location template — closing the two flagged deltas · `_BUILD/142`, `_BUILD/143`

### Measured before changing anything (design vs WP-before), 375×812 / 768×1024 / 1440×900

**Narrative + Gallery pairing** — design uses `grid-template-columns: repeat(auto-fit,
minmax(300px,1fr))`, gap 48px:

| Width | Design cols | Design nar/gal width | WP-before |
|---|---|---|---|
| 375 | 1 (stacked) | 311 / 311 | stacked |
| 768 | 2 (paired) | 328 / 328 | stacked |
| 1440 | 2 (paired) | 464 / 464 | stacked |

Real, confirmed delta.

**Practical Info facts/map proportion** — ⚠ **correcting last session's report.** The first
measurement queried the wrong DOM level (the outer `Band` wrapper) and reported an apparent
~30/70 split. Re-measured at the correct level — the actual `PracticalInfoBand` component's own
grid — both design and WP use the byte-identical rule
(`repeat(auto-fit,minmax(260px,1fr))`) and render an **exact 50/50 split at every width**
(design 439/439 at 1440, WP 647/647 at 1440 — different absolute numbers because the containers
themselves differ in width, but both exactly 50/50). **No delta exists. Nothing changed** — the
"fix" from a non-existent problem would have been the real defect.

### The fix — and why it needed PHP (structural, not CSS-only)

Both `tmk-narrative` and `tmk-gallery` are separate sibling widgets directly inside the live
Elementor template's (`elementor_library` #255) column container — no wrapping element exists
for a CSS media query to target. `_BUILD/142-location-atmosphere-layout.php` restructures the
template's stored `_elementor_data`: a new container `tmkloc_atmo` is spliced in at the exact
position the two widgets occupied, with the widgets moved inside unchanged (same ids, same
settings) — `tmkloc_bc` (breadcrumb, before) and `tmkloc_prac` (practical info, after) keep their
exact sequence position. `flex_direction:'row'` (desktop+tablet) / `flex_direction_mobile:
'column'`, `flex_gap:48px`, matching the design's own mechanism and gap value.

**No conflict with the protected 560/680 measure, by construction.** The narrative column's
flex-basis is fixed at exactly 560px — the *same* existing value already governing
`.tmk-narrative-band__body{max-width:560px}` (untouched). It cannot render wider than that
measure at any width; the gallery column takes whatever remains. Verified: `narWidth` measured
`560` (never more) at every one of the 15 checks below.

⚠ **A real Elementor rendering bug surfaced mid-verification, fixed in the same pass:**
`_flex_size` compiled correctly this time (`--flex-grow`/`--flex-shrink` custom properties were
present and correct — a different situation from the *separate*, already-documented "`_flex_size`
isn't registered" finding in `130-header-shell.php`, which was about a different element). The
actual bug: `flex-basis` defaulted to `auto`, and a flex item whose content is `display:grid`
with no explicit `grid-template-columns` (`.tmk-narrative-band`) computed that `auto` to **0px**
in this Elementor+Chromium combination — confirmed directly via `getComputedStyle`, not guessed.
`_BUILD/143-location-atmosphere-css.php` adds a small Kit Custom CSS block (marked, same
replace-between-markers convention as `130-header-shell.php`) giving `tmkloc_nar` a *concrete*
`flex-basis:560px` instead of `auto`, sidestepping the ambiguous auto-sizing calculation
entirely; `tmkloc_gal` gets `flex:1 1 0%; min-width:0` to fill the remainder cleanly.

Both scripts are idempotent (`tmkloc_atmo`-exists / marker-replace checks) — re-run confirmed
zero changes on the second pass for both.

### Verified — 5 extreme-case restaurants × 3 widths, 15/15 passing

Tierra, Linate, Capo d'Orlando, Lione, Fiumicino at 375/768/1440: HTTP 200, exactly one `<h1>`,
no horizontal scroll, 0 broken images, 0 empty narrative sections, narrative width **never**
exceeds 560px at any width for any restaurant, 0px gallery trailing-row gap (3, 4, and 7 images
all clean), genuine side-by-side confirmed visually at 768 and 1440 for all five (including
Tierra's 7-image gallery and Fiumicino's sparsest content — no tagline, no neighbourhood note, no
music flag — degrading gracefully with no orphaned empty sections). Screenshots
`~/Desktop/tmk-qa/locations-design-2/{name}-{375,768,1440}.png`.

D-32 honoured — no booking CTA added, altered, or referenced; the design source's booking widgets
were read for layout dimensions only. Reggio Calabria/Capo d'Orlando's shared Bologna photography
and shared generic signage are untouched by this pass — only the row's flex sizing changed, never
which images or how many.

Files: `142-location-atmosphere-layout.php` (backup `location-atmosphere-layout-backup.json`),
`143-location-atmosphere-css.php` (backup `kit-custom-css-backup-143.json`).

---

## Location page design refinements — client review round 3

⚠ Two render paths exist for the Location template — `location-render.php` (inactive shortcode
path) and the LIVE Elementor-widget path over the same `tmk_render_*()` engine (§141/PATCH 3).
Every item below was proven against **served HTML** at `localhost:10013/ristoranti/milano/aqua/`
and cross-checked on Tierra, Linate, Capo d'Orlando, Reggio Calabria and Lione — never the file,
DB value, or editor preview.

### Before / after

| § | Item | Before | After |
|---|---|---|---|
| 1 | Hero left alignment | Hero content padding resolved to **112px** (generic rule: `max-width:1280` + `margin:0 auto` + `padding:32px`, landing off-axis on this page's 1440-wide container) vs. breadcrumb at **24px** | Hero content padding **24px** — exact match to the breadcrumb's own computed left offset. Scoped override in `css/components/hero.css` (`body.single-location .tmk-hero--full-bleed .tmk-hero__content`) |
| 2 | L'Atmosfera description presence | `font-size:var(--text-body)` (16px) — read as secondary body copy | `font-size:var(--text-h4)` (20px), same `--leading-body`, 560px prose cap unchanged. `css/components/narrative-band.css` |
| 3 | Logotype axis | DOM box was already left-aligned with the description column, but the *visible artwork* wasn't — each logotype PNG carries a different baked-in transparent left margin (7–31px), invisible to a `getBoundingClientRect()` check | Per-asset optical trim computed by GD alpha-channel scan (`_BUILD/144-logotype-optical-trim.php`, cross-checked against an independent Python/Pillow scan — exact agreement on all 13 logotypes) stored as `_tmk_optical_trim_px`; applied as a negative `margin-left` in `inc/narrative-band-render.php`. Visible artwork now shares the description's axis |
| 4 | Gallery → carousel | Static grid, orange image frames, no way to browse without opening every photo | Real Elementor Pro `Media_Carousel` widget (skin `slideshow`) instantiated standalone from per-location `gallery_ids` (`inc/gallery-render.php::tmk_render_gallery_carousel()`), soft-radius only (`css/components/gallery.css`). Verified genuinely interactive (not just present) on both extremes: 3 images (Capo d'Orlando) and 7 images (Linate) — main swiper height 450px, thumbnail strip 5/4/3 slides-per-view at desktop/tablet/mobile, next-arrow click advances the active slide, thumbnail click drives the main swiper via swiper's own `thumbs` module |
| 5 | Practical-info card | Plain white-on-cream, one weight/size for every field | `--color-surface-sunken` background + 3px `--color-action-primary` top edge; hours/address bumped to bold/`--text-body`; phone + email restyled as CTA-style chips (`--color-surface-raised` fill, `--color-action-secondary` border, `--radius-soft`) — token references only. Verified on Tierra (4 day-ranges) and Reggio Calabria (1 day-range) |
| 6 | Container alignment | "VEDI IL MENÙ COMPLETO" flush left, testimonial column narrower and offset left of centre, closing band wider than both | Testimonial `figure.tmk-trust-band` centred via `margin-inline:auto` on the live widget-path selector (`.elementor-widget-tmk-trust figure.tmk-trust-band`, `css/components/trust-band.css`) — internal design of the quote untouched, only its container moved |
| 7 | Illustrations over body text | Investigated and initially found no contrast defect at the sitewide page-percentage position — but that position (`top:26%/64%` of *total document height*, `css/refinements/shell.css`) is inherently unstable across pages of different content length, so it was relocated proactively rather than left as a latent risk | Location pages skip the sitewide decor entirely (`is_singular('location')` guard in `tmk_render_page_decor()`) and instead render each illustration **locally**: leaves-banana inside the gallery carousel (`tmk_render_gallery_carousel()`), snake inside the practical-info card (`tmk_render_practical_info_band()`) — both new `tmk_render_local_decor()` calls, positioned by the component-layer stylesheet with `overflow:clip` on the container. Verified with a pixel screenshot on Tierra (where the geometric bounding boxes touch the "Musica & atmosfera" heading below): the illustration is fully clipped at the card edge with visible whitespace before the heading — zero actual paint overlap |
| 8 | Garland gap above footer | Measured **64px** — a normal token-scale gap, not a defect | No change — re-confirmed, still 64px |

### The carousel's two hidden bugs (plumbing beyond the original diff, both now fixed and verified)

Elementor Pro's `Media_Carousel` widget was instantiated **standalone** (`render_content()` only,
bypassing its normal Document/editor lifecycle) so it would never carry Elementor's own runtime
wrapper or per-instance settings CSS. Two consequences, found only by testing interactivity, not
by reading the diff:

1. **Elementor's frontend.js never initialises swiper on this markup** — its auto-init hook keys
   off `data-widget_type="media-carousel.default"`, which belongs to a different widget than ours
   (`tmk-gallery.default`). Fixed with a new `js/interactions/carousel.js` that initialises the
   real swiper.js library directly, using its documented `thumbs` module to sync main + thumbnail
   strips — not a hand-rolled slide-swap.
2. **Two static-CSS rules that depend on classes/settings Elementor normally injects at render
   time never applied**: `.elementor-skin-slideshow .elementor-main-swiper{height:450px}` needs
   the `elementor-skin-slideshow` class Widget_Base::render() would have added (added manually to
   our own wrapper div); the thumbnail strip's `slidesPerView` (Elementor's own stated default:
   desktop/laptop/widescreen 5, tablet 4, mobile 3) lives only inside the frontend JS handler that
   never runs on our markup (replicated in `carousel.js`'s swiper config). Without both fixes the
   carousel *looked* present in markup but had 0 height and a full-width single "thumbnail" —
   confirmed both bugs, then confirmed both fixes, via Playwright click/measurement tests, not by
   eyeballing a screenshot.

### Verified — 5 restaurants × 3 widths, 15/15 passing

Aqua, Tierra, Linate, Capo d'Orlando, Lione at 375/768/1440 (scroll-through per the established
lazy-render caveat): exactly one `<h1>`, no horizontal scroll, 0 broken images, 0 console/page
errors, 0 failed requests, carousel genuinely interactive on both extremes (3 and 7 images), zero
illustration/text overlap anywhere (geometric-overlap check itself corrected mid-task to account
for `overflow:clip` ancestors, then double-checked with a real screenshot on the one case that
still geometrically touched). Screenshots `~/Desktop/tmk-qa/locations-design-3/{name}-{375,768,1440}.png`.

D-32 honoured throughout — the hero and closing-band Prenota CTAs are untouched plugin output;
nothing about them was restyled, removed, or repointed.

Files: `inc/refinements.php`, `inc/gallery-render.php`, `inc/practical-info-render.php`,
`inc/narrative-band-render.php`, `inc/elementor/class-tmk-widgets.php`, `_BUILD/144-logotype-optical-trim.php`
(backup `logotype-optical-trim-backup.json`), `_BUILD/145-gallery-carousel-variant.php` (backup
`gallery-carousel-variant-backup.json`), `js/interactions/carousel.js` (new), `css/components/{hero,
narrative-band,practical-info-band,trust-band,gallery}.css`.

---

## Trust Band — testimonial targeting · `inc/trust-band-render.php` + `_BUILD/150-151`

`tmk_render_trust_band()` (`[tmk_trust_band]` shortcode + the `tmk-trust` Elementor widget) used
to hardcode a fallback quote whenever the `testimonial` CPT query came back empty, so it could
never actually be absent — which is why Rimini's Elle Decor quote rendered on every location,
Aqua included. Client review ("wire the testimonial targeting system") asked for real per-page
targeting instead: a location page shows only its own testimonial, the homepage shows only
homepage-flagged ones, careers pages show only `scope=staff`, everywhere else shows nothing —
and "nothing" has to mean no markup at all, not a hidden element.

### STEP 0 — the CPT had no ACF group at all

`testimonial` registers `supports => array('title')` only; `quote`/`author`/`rating`/`location_id`
(`content-model.php`) are bare `register_post_meta()` calls with zero admin UI. Before this task
the client could not author or edit a testimonial through wp-admin **at all** — confirmed by
listing every `acf-field-group` post: the three that exist (`Informazioni Ristorante` →
`location`, `Dettagli Piatto` → `menu_item`, `Informazioni Città` → the `city` taxonomy) don't
touch `testimonial`. The two real records' `quote`/`author` values existed only as raw postmeta,
written by an earlier session's script, never through an editor field.

### A second finding, same shape — all five named call sites turned out to be dead code

Location-render.php, story-render.php, event-render.php and careers-render.php (hub + job detail)
each call `tmk_render_trust_band()` from inside a function reachable only via its own shortcode
(`[tmk_location]`, `[tmk_article]`, `[tmk_event]`, `[tmk_careers_hub]`, `[tmk_job]`). A sitewide
`post_content` search for every one of those shortcodes returned **zero** matches — the same
"two render paths, only one live" pattern §141/PATCH 3 already documents for the location
template, just not yet noticed for this component. The **only** live placement of the `tmk-trust`
widget sitewide (grepped across every `_elementor_data` row) was `elementor_library #255`
(Location Single). The homepage and both careers templates had no trust-band placement anywhere.

### The fix

**`content-model.php`** — two new keys, `tmk_*` convention (the four pre-existing keys predate
that rule and are left alone — renaming a live meta key would silently orphan the two real
records' data): `tmk_testimonial_homepage` (boolean) and `tmk_testimonial_scope` (string).

**`trust-band-render.php`** — the fallback quote is gone. Context resolves in this order: an
explicit `context`/`location_id` shortcode/widget att, else auto-detected from the current query
(`is_singular('location')` → that location's `location_id`; `is_front_page()` →
`tmk_testimonial_homepage`; `is_singular('job')` or the job archive → `scope=staff`; anything
else → empty string immediately, no query run at all). Auto-detection means the existing
`tmk-trust` widget instance on template #255 needed **no changes** to pick up per-location
targeting — it already passes no context, so it always resolved via auto-detection.

**`_BUILD/150-testimonial-fields.php`** — a real, wp-admin-editable ACF field group
(`acf_import_field_group()`, the same function ACF's own JSON-import feature calls — not
`acf_add_local_field_group()`, which registers a PHP-only group that never appears as an editable
post). Scope widened beyond the three targeting fields on request: `quote`/`author`/`rating` are
now editable too, since a targeting UI bolted onto an otherwise-blank editor would have been a
strange half-fix. `location_id` is a real post-object picker scoped to the `location` CPT
(`return_format:id`, `allow_null:1` for "no specific restaurant"), not a raw ID box. Backfills
the two real records with real targets, not invented ones: **Elle Decor** (#35) → `location_id`
= Rimini (its own quote names "La sala di Rimini" by name), `scope=press`, `homepage=true`.
**Gambero Rosso** (#34) → no location (names no restaurant), `scope=press`, `homepage=true`.
Neither is `scope=staff`, so careers pages correctly render nothing until a real staff
testimonial exists — none was fabricated for this.

**`_BUILD/151-trust-band-placements.php`** — adds one `tmk-trust` instance to the homepage (#36,
before the closing band) and the careers hub template (#559, before Open Roles, matching
`CareersHub.jsx`'s own Hero→Culture→Pillars→**Trust**→Roles→Conversion order per §104) so the
homepage/careers behaviour above is something a browser can actually show, not just something
the code is correct about. Homepage instance is `rotating:'yes'` — both real testimonials are
now homepage-flagged, and `.tmk-trust-band__item{display:none}` /
`.is-active{display:block}` (trust-band.css) means a second item is **permanently unreachable**
without rotation, not just visually secondary. Careers instance stays `rotating:'no'`, matching
every other existing call site.

### Two real bugs caught by measuring, not by reading the diff

1. **A container's own empty state is not the same as a widget's.** Elementor core's own
   `print_element()` (`element-base.php`) already skips a **widget's** wrapper entirely when its
   rendered content is empty (`$should_render = !empty($content) || $this->should_print_empty()`)
   — confirmed directly: an untargeted location page has *zero* `tmk-trust` markup, not a hidden
   figure, with no extra work needed on this component's part. A **container** does not get the
   same treatment: `151`'s first run left a real, measured 20px-tall empty `<div
   class="e-con-inner">` on the careers hub page (0 matching testimonials) — Elementor's own
   default container padding, not anything this script set. Fixed by setting `padding:0` on both
   new containers; a real testimonial still gets correct spacing from the component's own
   `.tmk-trust-band{padding:var(--space-6) 0}`, same as how the widget already sits with no extra
   wrapper padding on the location template.
2. **The build script's own backup was silently wrong.** `tmk_insert_before_top_level()` mutated
   `$doc` by reference, and the backup was written from that already-mutated `$doc` afterwards —
   so the "pre-write" backup was actually a post-write snapshot, and a subsequent restore-from-
   backup was a no-op (confirmed empirically when the padding fix above didn't reach the live
   page after a "restore"). Fixed by taking a deep copy before any mutation and rewriting the
   insert logic (`tmk_ensure_placement()`) to also be self-healing: re-running the script now
   verifies and patches settings on an already-present element instead of only skipping, so it
   converges on the same correct state regardless of which version last touched it.

### Verified in SERVED HTML, not the DB

Aqua and Tierra (no `location_id` match) — confirmed zero `tmk-trust-band` markup, not just a
hidden one. Rimini (`location_id` = its own post) — renders exactly its own quote, nothing else.
Homepage — both `homepage=true` quotes present, rotation genuinely functional (clicked
next-arrow, active quote text changed). Careers hub (`/lavora-con-noi/`) — zero markup, matching
the current zero `scope=staff` records. All measured with `WP_DEBUG` temporarily true then
restored false, per this file's own standing verification method — no trust-band-related notice
or warning surfaced. `h1` count and horizontal scroll checked on both new placements at
375/768/1440.

**Flagged, not fixed — pre-existing, unrelated to this task:** the homepage has a real horizontal
scroll at 375px, traced to `tmkhp002` (the hero image, from the original §100 build) rendering
400px wide against a 375px viewport. Confirmed unrelated to this change — the offending element
predates this session and sits nowhere near the new trust-band placement.

Screenshots `~/Desktop/tmk-qa/testimonial-targeting/{homepage,careers}-{375,768,1440}.png`.

Files: `inc/content-model.php`, `inc/trust-band-render.php`, `_BUILD/150-testimonial-fields.php`
(backup `testimonial-fields-backup.json`), `_BUILD/151-trust-band-placements.php` (backups
`trust-band-placements-{36,559}-backup.json`).

---

## Gallery carousel — polish pass (thumbnails, arrows, lightbox) · `css/components/gallery.css`, `js/interactions/carousel.js`, `inc/gallery-render.php`

⚠ Same two-render-path caveat as everywhere else on this template — proven in served HTML on
the LIVE widget path, never the shortcode path. ⚠ Same standalone-instantiation caveat as the
carousel's own original build: Elementor's frontend JS doesn't bind here, so anything Elementor
would normally wire up for free has to be tested, not assumed — this pass found two more
instances of that exact trap.

### 1–3 — thumbnail spacing, hover, active state

`.elementor-thumbnails-swiper{margin-top:var(--space-3)}` (breathing room from the main image);
hover is opacity 0.6→0.85 + `scale(1.03)` on `--motion-feedback`/`ease-out` (reduced-motion-safe
via the token itself, no separate rule needed); the active thumbnail gets full opacity + a
`--color-action-primary` border (a transparent border is reserved on every thumb up front so the
active one doesn't shift layout on arrival) — border/opacity/scale, no new colour, matching what
was asked. Swiper's own Thumbs module already toggles `.swiper-slide-thumb-active` for free
(confirmed by inspection before writing any CSS) — no JS needed for the active-state tracking,
only for what's below.

### 4–5 — arrows and edge fade

Elementor's own default (`e-swiper.min.css`) is a bare `color:hsla(0,0%,93%,.9)` chevron with no
background at all — illegible over any light photo area, the exact complaint. Rebuilt as a real
40px pill button: `--color-surface-raised` fill, `--elevation-raised` shadow, hover to
`--color-action-primary` with a `--motion-feedback`/`ease-out` transition, focus ring free from
the sitewide `:focus-visible` rule (base.css) since these are real focusable elements. Contrast
computed, not eyeballed: resting 16.23:1 (ink on tatami-50), hover 4.65:1 (tatami-50 on
ember-600) — both pass AA independent of whatever photo sits behind the button, because the
surface is opaque. Edge fade is a `::before`/`::after` pair using the existing warm-ink scrim
tokens (`--color-scrim-mid` → transparent), narrow and low-opacity by design so it never obscures
the photo and is never the only thing making an arrow visible (the button's own contrast was
computed with the fade removed from the equation entirely). Selectors are chained to match
Elementor core's own specificity (`.tmk-gallery-carousel .swiper .elementor-swiper-button`,
0-3-0) rather than `!important` — this stylesheet loads after Elementor's core CSS (enqueue.php,
priority 20), so an equal-specificity tie resolves in our favour, the same pattern already used
for the hours-table zebra-stripe and trust-band centering fixes elsewhere in this codebase.

### 6 — lightbox

Tested Elementor's own lightbox first, not assumed: clicking the main image did nothing (no
modal, no `elementor-lightbox` markup anywhere) — confirms it never binds here, same root cause
as the swiper init. Built instead on `js/interactions/modal.js`, the generic engine the grid
gallery variant already uses (open/Escape/backdrop-click/focus-trap/focus-return, all pre-audited)
via its existing declarative contract — needed **zero new JS for the modal itself**.
`inc/gallery-render.php::tmk_render_gallery_carousel()` now scopes a regex to the substring
*before* `elementor-thumbnails-swiper` so only the MAIN swiper's slide images become triggers
(`role="button" tabindex="0" data-tmk-modal-trigger`) — the thumbnail strip's own copies keep
their existing job. One `tmk-modal--lightbox` (same markup shape as the grid variant, real alt
text carried through) is appended per image.

**Two real bugs caught by testing, not by reading the diff:**
1. **Enter/Space on the new trigger did nothing, even though the arrows already respond to
   both.** Turned out the arrows' keyboard support is swiper's OWN Navigation module binding
   keydown to its nav elements specifically — not a general browser behaviour for `role="button"`
   divs, confirmed by testing a *different* `role="button"` div (this trigger) and finding
   `.focus()` + Enter did nothing while a JS `.click()` did. Fixed with an explicit
   keydown-to-`.click()` shim in `carousel.js`, plus a `tabindex` sync (0 on the active slide
   only, -1 on the rest, updated on every `slideChange`) so Tab doesn't land on invisible
   off-screen triggers.
2. **The same shim doesn't work for thumbnails.** A plain JS `.click()` on a thumbnail never
   moved the main swiper — traced to swiper's Thumbs module (`onThumbClick`, swiper.js source)
   listening for swiper's own internal `'tap'` event, not a DOM `click`, so nothing a script
   dispatches ever reaches it. Fixed by calling `mainSwiper.slideTo(index)` directly on
   Enter/Space — the exact call the module's own handler resolves to with loop disabled (as it
   is on both swipers here), reaching the identical end state without faking swiper's internal
   event system.

### A third bug, pre-existing, exposed rather than caused by this pass

Verifying arrow/lightbox clicks at 768px on Capo d'Orlando failed consistently — traced to the
gallery column on `elementor_library #255` measuring **92px wide** at that breakpoint (720px row
− 20px padding − 48px gap − the narrative's own 560px ceiling = 92). This mechanism has existed
since §142/143; the old grid gallery's own per-photo `flex:1 1 240px` floor absorbed the squeeze
by simply wrapping to a narrow-but-usable column, while the carousel (added afterward, §145)
renders at the true container width with no such floor, turning the same pre-existing squeeze
into a literally unusable 92px carousel. Fixed in the same Kit Custom CSS block (`143-location-
atmosphere-css.php`, re-run) by giving the gallery a `flex-basis:280px` floor — this is what
forces the narrative's own `flex-shrink:1` (already set, never previously triggered) to engage
below its 560px ceiling, but only where the combined demand exceeds what's available: ~435px
narrative / ~217px gallery at 768px, 1440 and ≤767 unaffected (measured, not assumed).

### 7 — radius

Untouched — still `--radius-soft` throughout, confirmed on every check below.

### Verified by interaction, not by reading markup — Capo d'Orlando (3 images) and Linate (7),
### 375/768/1440, both before AND after the 768px fix

Slide/thumbnail/trigger/modal counts match the real image count exactly (no empty slots, no
missing modals) on both extremes at all three widths; arrow click advances the main swiper;
thumbnail click drives the main swiper AND updates `.swiper-slide-thumb-active`; lightbox opens
on click, closes on Escape, tabindex stays synced to the active slide only; zero console/page
errors throughout. One genuine testing pitfall hit along the way: `page.hover()` /
`scrollIntoViewIfNeeded()` do an instant scroll jump that can displace the target before the
mouse arrives (the same reflow quirk this file's own §9 already documents for `window.scrollTo`)
— switching to `page.mouse.wheel()` in increments resolved several false "hover doesn't work"
readings on already-correct CSS.

Screenshots `~/Desktop/tmk-qa/carousel-polish/{capo,linate}-{375,768,1440}.png`.

Files: `css/components/gallery.css`, `js/interactions/carousel.js`, `inc/gallery-render.php`,
`_BUILD/143-location-atmosphere-css.php` (updated in place, backup
`kit-custom-css-backup-143.json`).

---

## Sitewide horizontal padding — 24px → 32px · `_BUILD/152-padding-32.php`

⚠ Reverses Session 130's decision. That session set 24px because the client wanted content
wider at the time; the client now wants it larger. 32px is not invented — it's the design
source's own header/footer value (`--space-6`).

### STEP 1 — audit (measured header-logo / footer-logo / `<h1>` left-offset, all 12 routes,
### 375/768/1024/1440, before touching anything)

Found the client's "they're not all the same" complaint was structurally correct — at least
**six independent mechanisms**, most still carrying the old 24px (or a stray 20/40) that Session
130 either never reached or a later change undid:

1. **Header** — the element's own padding control was already 24px everywhere, but Kit Custom
   CSS (from 095/130) had a separate `@media(max-width:1023px){ #tmk-header{ padding-inline:
   20px !important } }` rule silently overriding it below 1024px with a *different* literal
   value. Found by reading the generated Kit CSS, not guessed.
2. **Footer** — a flat 24px control on `tmkft020` (120's "main row" container), internally
   consistent, just at the old number.
3. **Location Single** — `tmkloc_col` (the shared boxed column for breadcrumb/practical-info/
   menu/trust/booking/nearby) had its own flat 24px control, numerically matched to (but
   structurally separate from) `hero.css`'s `body.single-location` scoped override — both had to
   move together or the hero and breadcrumb go back out of axis, the exact defect that override
   was built to fix in the first place.
4. **The generic `.tmk-hero--full-bleed .tmk-hero__content` rule** (Menu, the `/ristoranti/
   {city}/` archive) was already `--space-6`/32px — no change needed.
5. **Three real `page` posts — Storie hub, La Nostra Storia, Allergeni — have no `_elementor_data`
   at all** (shortcode in `post_content`) and are the only checked routes whose `<body>` carries
   no `elementor-page-*` class — confirmed by checking every route's body class, not assumed.
   That routes them through **Hello Elementor's own theme.css**, which has a completely separate,
   previously-undocumented boxed-width rule (`body:not([class*=elementor-page-]) .site-main` at
   500/600/800/1140px tiers) capping a `.tmk-hero--full-bleed` — meant to render edge-to-edge —
   into a narrow legacy blog column, compounding with the hero's own padding into the growing,
   seemingly-arbitrary offsets these three measured (e.g. Storie: 42/116/144/182 across the four
   breakpoints, matching neither the flat-24 nor flat-32 pattern of anything else). Contattaci and
   Shop were once in this same bucket per PATCH 1's own §10 log, but were later converted to real
   dedicated Elementor page templates in their own build sessions (101, 102) and do carry
   `elementor-page-*` — confirmed unaffected.
6. **Contattaci, Eventi Hub and Careers Hub** are built from multiple per-section Elementor
   containers, each with its own `content_width` (boxed/full) and its own literal
   `padding.left/right` — 24 on most, 40 on the outer full-width bands (itself a pre-130
   leftover, the same "40" §130's own table shows header/footer used to carry, apparently never
   propagated to these other templates when built afterward). **Shop and the 404 template were
   found to need the identical fix but had been omitted from the first implementation pass
   entirely** — caught by re-measuring the served pages after the first run, not by re-reading
   the diff.

### STEP 2 — the fix, one idempotent script, no plugin PHP touched

All six mechanisms are Kit settings, Kit Custom CSS, or Elementor `_elementor_data` element
settings — none require editing a plugin `.php` file. `_BUILD/152-padding-32.php`:
recursively walks the header (#25), footer (#27), location single tmkloc_col (#255), Contattaci
(#251), Eventi Hub (#561), Careers Hub (#559), Shop (#252) and the 404 template (#568), bumping
any `padding`/`padding_tablet`/`padding_mobile` left/right value of 24, 40, 20 or 16 to 32 —
**every match individually verified beforehand to be a top-level `container` with
`content_width` boxed/full** (i.e. a real page gutter), never a small nested component inset, to
avoid inflating something like a 4px badge padding into 32px. Also updates the Kit Custom CSS
header override to 32px and adds a new marked block neutralising Hello Elementor's `.site-main`
box sitewide (scoped to the same `body:not([class*=elementor-page-])` condition that rule itself
uses, rather than three separate per-page patches — protects any future page that falls into the
same shape). `css/components/hero.css`'s `single-location` scoped override moves from
`--space-5` to `--space-6` in the same pass, so it stays matched to `tmkloc_col`.

**Two real bugs caught by re-measuring the served page, not by re-reading the script:**
1. Elementor stores each responsive breakpoint as its **own separate settings key**
   (`padding_tablet`, `padding_mobile`), not nested inside one `padding` object. The first pass
   only bumped the base (desktop) key, which reported success truthfully — the desktop value
   really did change — while silently leaving header/footer at the old 24px on tablet and
   mobile. Confirmed by re-measuring at 375/768, not by trusting the script's own "done" output.
2. Two whole templates (Shop, 404) were simply never in the fix loop — an omission, not a logic
   error — caught the same way, by finding their numbers hadn't moved on a fresh audit.
3. The script's own hex/font-family guard initially blocked on its own docblock comment text
   (`Storie hub #231` etc. — `#231` matches the hex-colour regex) and separately on a genuinely
   pre-existing condition (header template #25 already carries 13 hardcoded hex values in
   unrelated nav/button widget settings, predating this project's guard convention). Fixed by
   rewording the comment and by changing the guard to compare hex/font-family **counts before
   vs. after** the change rather than failing on any pre-existing occurrence — a padding-only
   mutation that only ever writes a numeric string cannot introduce either, and blocking on a
   finding this script had no part in isn't this pass's place to silently fix.

### The 1920px gutter — mechanism confirmed, not fixed, per instruction

For a centered boxed container, `gutter = padding + (viewport − 2·padding − container_width)/2`
algebraically reduces to `gutter = viewport/2 − container_width/2` the moment the container is
narrower than the viewport — **the padding term cancels out completely**. At 1920 with
`container_width` still 1440: `(1920−1440)/2 = 240px`, regardless of whether padding is 24 or
32 — identical to the 240px already flagged as open in this file's own §130 log. The 24→32
change does not move this number at all. Not fixed here, per instruction — raising
`container_width` further only moves the same problem, a clamped Custom CSS cap is the other
option, still nobody's decision to make silently.

### Verified — all 12 routes, 375/768/1024/1440

Header and footer: flat 32px on **every** route, **every** breakpoint — the actual gutter is now
genuinely unified, which is what the client asked for. Body content (`<h1>` left-offset): Menu,
Storie, La Nostra Storia and the Ristoranti city archive now match each other exactly (32/32/32,
diverging to 112 only at 1440 — expected boxed-centering math against the hero's own 1280px
content-width ceiling, a content-width decision, not a gutter one, same mechanism the header/
footer would also hit past ~1504px). Eventi, Lavora con noi and Shop: flat 32px at **every**
breakpoint, no divergence at all. Fuego (location single): flat 32px at every breakpoint,
matching its own breadcrumb by construction. Contattaci diverges further at 1440 (200px) because
its own `boxed_width` is 1040, narrower than the hero's 1280 — same expected mechanism, earlier
threshold, not a defect. Zero new horizontal scroll introduced by this change on any of the 12
routes at any of the 4 widths (checked before and after).

**Flagged, not fixed — confirmed pre-existing on both, unrelated to padding:** Home and La Nostra
Storia both show horizontal scroll at 375px in both the before- and after-audit. Home's is
already documented in this file's own §7 write-up (`tmkhp002`, the hero image, 400px against a
375px viewport). La Nostra Storia's is a `.tmk-heritage{display:grid}` with no explicit
`grid-template-columns` letting its child's own `max-width:680px` overflow the implicit grid
track regardless of the container's real (375px) width — the same `min-width:auto`-class
shrink-refusal already documented multiple times in this file for flex, here in a grid context.
Neither was introduced or affected by the padding change (confirmed: both showed identical
overflow behaviour in the very first, pre-change audit). Allergeni still has zero `<h1>` — a
pre-existing content gap (§10), not a padding defect.

Screenshots `~/Desktop/tmk-qa/padding-32/{route}-{375,768,1024,1440}.png` for all 12 routes.

Files: `css/components/hero.css`, `_BUILD/152-padding-32.php` (backups
`padding-32-{kit-custom-css,25,27,255,251,561,559,252,568}-backup.json`).

---

## Location page — client review round 4 (seven fixes) · `_BUILD/153-location-shell-fixes.php` + CSS

⚠ Same two-render-path caveat as every other Location pass: everything below was proven against
**served HTML on the LIVE Elementor-widget path** (§141/PATCH 3), never the shortcode path, the
file, or the DB value. ⚠ Same standalone-carousel caveat: Elementor's frontend JS does not bind
to this markup, so every interactive claim here comes from a real click/keypress/hit-test.

**No plugin PHP was changed.** All seven landed as component CSS + Kit Custom CSS + stored
Elementor padding values. D-32 honoured: no booking CTA touched.

### 1 · Lightbox close button — root cause was a stray `transform`, not z-index

The X was not covered by anything and had no `pointer-events` problem. Measured with the modal
open at 1440: `.tmk-modal{position:fixed;inset:0;z-index:100}` reported a rect of
**1376 × 543 at y=444** instead of **1440 × 900 at y=0**.

Chain, each step measured:
1. Kit #5 Custom CSS declares `.tmk-in-view { animation: tmkFadeUp … **both** }`, and Custom
   Code snippet **#558** adds `.tmk-in-view` to every `.e-con` as it scrolls into view. With
   fill-mode `both` the finished animation keeps filling its 100% keyframe forever, so every
   revealed container is left holding `transform: matrix(1,0,0,1,0,0)`. Confirmed by direct
   comparison: `.elementor-element-tmkloc_atmo` computes `transform:none` at load and
   `matrix(1,0,0,1,0,0)` once scrolled past.
2. A transformed element is the **containing block for its `position:fixed` descendants**, so
   the modal stopped resolving against the viewport and started resolving against the atmosphere
   row.
3. `.tmk-gallery-carousel--decorated{overflow:clip}` — a descendant of that same transformed
   element, so its clip now genuinely applies to the modal — clipped what was left.
4. The panel is vertically centred and taller than that 543px box, so it overflowed **above**
   it: the close button measured y=**429**, i.e. 15px outside the top of the region the modal
   was allowed to paint. That is also why the carousel's next-arrow appeared to sit *on top of*
   the open modal — the arrow was still inside the visible box; the button was not.

**This is the second instance of a trap this file already documents.** The comment block
immediately above `.tmk-in-view` in the same Custom CSS explains at length why `#tmk-header`
must use `backwards` and never `both` — and the next rule uses `both`. One-word fix, applied to
`.tmk-in-view` and back-ported into `_BUILD/90-navbar-fonts-motion.php` so a re-run can't
reintroduce it. Nothing pre-hides an un-revealed element (there is no `.tmk-reveal{opacity:0}`
counterpart in this Custom CSS), so `backwards` is visually identical. **Site-wide benefit:** no
`.e-con` anywhere is left holding a containing block for fixed-position content.

| Property | Before | After |
|---|---|---|
| modal rect @1440 | 1376 × 543 @ y=444 | **1440 × 900 @ y=0** |
| modal rect @375 | clipped to the carousel box | **375 × 844 @ y=0** |
| `elementFromPoint` at the X | button reachable only by luck of scroll position | `button.tmk-modal__close` at 375/390/768/1440 |
| click the X | dead | **closes** |
| Escape | worked | works |
| backdrop click | worked | works |
| click at the next-arrow's position while open | advanced the carousel | hits `.tmk-modal__scrim` and **closes** — the arrow is now behind the scrim |

### 2 · The grey hover — it is Elementor Pro's, and it is not a hover

Source, found by reading CSS rather than guessing:
`elementor-pro/assets/css/widget-media-carousel.min.css`

```
.elementor-skin-slideshow .elementor-thumbnails-swiper .swiper-slide:not(.swiper-slide-active):after
  { background-color: rgba(0,0,0,.3); content:""; inset:0; position:absolute }
```

A flat **30% black square-cornered veil** painted over every thumbnail that is not the swiper's
own active slide — which is exactly why it also carried the "wrong border-radius": the veil is a
pseudo-element on `.swiper-slide` (radius **0**) sitting on an image that carries
`--radius-soft` (**8px**). Measured `::after` on all six thumbs: `content:""`,
`rgba(0,0,0,0.3)`, `border-radius:0px`. Two further reasons it is wrong here: it keys off
`.swiper-slide-active` (the *thumbs* swiper's own index), not `.swiper-slide-thumb-active` (the
slide the main swiper is showing), so once the strip scrolls the veil lands on the current
photograph too; and this component already expresses inactive-ness with `opacity`, so it was a
second, conflicting dimming stacked on the first.

Killed with `content:none` at (0,5,1) specificity against Elementor's (0,4,1) — outright win, no
`!important`, no load-order dependency. Verified: `veilsPresent: 0` on all 6 restaurants × 4
widths.

**Also removed, sitewide, second grey:** Hello Elementor's `reset.css` ships
`table tbody tr:hover>td, table tbody tr:hover>th { background-color: hsla(0,0%,50%,.102) }` — a
literal 50%-grey row wash from outside this design system entirely (no grey exists in
`tokens/colors.css`; every neutral here is warm tatami/ink). An earlier pass had neutralised only
its **zebra-stripe twin**, and only inside `.tmk-practical-info`, leaving the hover live on every
other table on the site. Now handled at the base layer. Confirmed on Tierra's hours table:
first row cell bg on hover = `rgba(0, 0, 0, 0)`. `/menu/` and `/allergeni/` were checked and have
no tables at all.

**Focus states are intact** — verified, not assumed. `:focus-visible{outline:var(--focus-ring)}`
(base.css) is an outline, never a background, so nothing removed here could touch it. Tabbed
through the carousel: 8 consecutive stops (lightbox trigger → next arrow → 6 thumbnails), every
one computing `solid 2px rgb(199,72,20)`. Header nav link, thumbnail and arrow all measured the
same ring.

**Reported, not changed:** `.elementor-carousel-image-overlay{background-color:rgba(105,114,125,.8)}`
in the same Elementor Pro file is also a grey-blue from outside the system, but it only renders
when a carousel slide carries overlay content, which none here do — it is not on the page.
`.tmk-cta--secondary:hover{background:rgba(36,28,19,0.06)}` is warm ink, ours, and deliberate.

### 3 · Thumbnail hover clipping

Root cause as suspected: `transform: scale(1.03)` grew a 143.2 × 61.4 thumbnail to
**147.5 × 63.2** inside a `.swiper-slide` **and** an `.elementor-thumbnails-swiper` that are
*both* `overflow:hidden` — swiper's own core CSS, which this component cannot relax without
breaking the slider's clipping contract. Measured clip: **0.92px** off top and bottom, **~2.15px**
off each side.

Giving the container room would mean padding the strip and re-deriving swiper's slide arithmetic.
Took the other option instead: the affordance no longer changes the box. Hover is now
opacity 0.6 → **1** plus the **already-reserved** transparent 2px border taking
`--color-border-strong`; active keeps opacity 1 + `--color-action-primary`. `--radius-soft` (8px)
untouched, no fourth radius value.

| | Before | After |
|---|---|---|
| hover transform | `matrix(1.03,0,0,1.03,0,0)` | **`none`** |
| clip top / bottom | 0.92 / 0.92 px | **0 / 0** |
| clip left / right | ~2.15 / ~2.15 px | **0 / 0** |
| radius | 8px | 8px |
| hover affordance | opacity 0.85 + scale | opacity 1 + tatami-700 border |

### 4 · The dead left column in the practical-info card

`inc/practical-info-render.php` wraps the ambient snake in `<span aria-hidden="true">…</span>` as
the card's **first child** — and the card is `display:grid`, so that span was a full **grid item**.
Its only content is `position:absolute`, so it contributed nothing and occupied a whole track.

| @1440 | Before | After |
|---|---|---|
| `grid-template-columns` | `416px 416px 416px 0px` | **`640px 640px`** (+2 collapsed 0px tracks) |
| card children in flow | 3 (decor span, facts, map) | **2** |
| card content box starts | x = 64 | x = 64 |
| first fact label (`ORARI`) starts | x = **512** | x = **64** |
| dead space | **448px** | **0** |
| map | 416 × 312 | **640 × 480** |

Fixed with `.tmk-practical-info>[aria-hidden="true"]{display:contents}` — removed from layout, not
from the DOM; the illustration is absolutely positioned and its containing block
(`.tmk-practical-info--decorated`) is unchanged, so the snake sits exactly where it did. Two
children → two equal tracks, which is the design's own 50/50 facts/map split (re-measured and
confirmed byte-identical in the `_BUILD/142` session — deliberately **not** re-proportioned).

### 5 · One left axis on the Location template

Measured left offset of every top-level element, `/ristoranti/milano/arya/` @1440:

| Element | Before | After | Why it was off |
|---|---|---|---|
| `tmkloc_col` inner (the axis) | 32 | 32 | — |
| breadcrumb | 32 | 32 | — |
| `tmkloc_atmo` children | **42** | 32 | container has **no** padding control → Elementor's own `--container-default-padding-inline` **10px** (`_BUILD/142` created it without one) |
| narrative band | **42** | 32 | inherits the 10px above |
| logotype (box) | 38 | 4 | per-asset optical trim (`_tmk_optical_trim_px` = 28) — see below |
| **description text** | **66** | **32** | 10px above **+ 24px** from `.tmk-narrative-band{padding:0 var(--space-5)}`, a gutter meant for the shortcode path where the band sits flush to the viewport |
| carousel (right edge) | **42** | 32 | the same 10px |
| practical card | 32 | 32 | — |
| menu preview | 32 | 32 | — |
| booking band | 32 | 32 | — |
| closing / nearby band | 32 | 32 | — |

Two independent causes, two fixes: `tmkloc_atmo` gets an explicit `padding:0` (all three
breakpoints) in `_BUILD/153`; the narrative gutter is zeroed under `body.single-location` in
`css/components/narrative-band.css`, scoped exactly like `hero.css`'s existing override because
the homepage uses the same widget *outside* a gutter-bearing column. The 560px prose measure and
the 680px band cap are untouched.

**The logotype box left is intentionally not 32.** It carries a negative margin equal to that
asset's own baked-in transparent left margin (`_BUILD/144`), so the *visible artwork* lands on the
axis. Checked against the stored trim on every verified restaurant: Arya 4 + 28, Fuego 3 + 29,
Tierra 2 + 30, Reggio 12 + 20, Capo 12 + 20, Linate 11 + 21 — **all = 32**.

Verified identical (32 left, 32 right on every row) on Arya, Fuego, Tierra, Reggio, Linate and
Capo d'Orlando at 375 / 390 / 768 / 1440.

### 6 · Sitewide padding — re-audited, 12 routes × 4 widths

"Page gutter" below = the smallest non-zero left offset among a route's top-level content rows
(deliberate full-bleed rows read 0 and are excluded); for the three shortcode routes it is the
first content element's own left offset.

| Route | 375 | 768 | 1024 | 1440 | hscroll before → after |
|---|---|---|---|---|---|
| `/` | **16 → 32** | **24 → 32** | **24 → 32** | 80 → 80 ¹ | **41px → 0** |
| `/menu/` | **10 → 32** | **10 → 32** | **10 → 32** | **10 → 32** | 0 → 0 |
| `/storie/` | 32 → 32 | 32 → 32 | 32 → 32 | **112 → 32** ² | 0 → 0 |
| `/eventi/` | 32 → 32 | 32 → 32 | 32 → 32 | 200 → 200 ¹ | 0 → 0 |
| `/lavora-con-noi/` | 32 → 32 | 32 → 32 | 32 → 32 | 200 → 200 ¹ | 0 → 0 |
| `/la-nostra-storia/` | 32 → 32 | 32 → 32 | 32 → 32 | **112 → 32** ² | **353px → 0** |
| `/contattaci/` | 32 → 32 | 32 → 32 | 32 → 32 | 200 → 200 ¹ | 0 → 0 |
| `/shop/` | 32 → 32 | 32 → 32 | 32 → 32 | 200 → 200 ¹ | 0 → 0 |
| `/allergeni/` | **0 → 32** ³ | **0 → 32** ³ | **0 → 32** ³ | **0 → 32** ³ | 0 → 0 |
| `/ristoranti/milano/` | 32 → 32 | 32 → 32 | 32 → 32 | **112 → 32** ² | 0 → 0 |
| `/ristoranti/milano/arya/` | 32 → 32 | 32 → 32 | 32 → 32 | 32 → 32 | 0 → 0 |
| 404 | 32 → 32 | 44 → 44 ¹ | 172 → 172 ¹ | 380 → 380 ¹ | 0 → 0 |

Header and footer inner rows measured **32 / 32** on every route at every width, before and
after — session 152's work held. Every value is symmetric (left == right) on every row measured.

Four defects found and fixed, four mechanisms — session 152's own "six independent mechanisms"
finding, extended:

1. **Homepage #36 was never in 152's fix loop.** It still carried the pre-152 numbers: 24px
   desktop / 16px mobile on the hero band, 16px mobile on all three city bands, 48/24 on the
   closing band — three different gutters on one page at 375. Now 32 at every breakpoint on all
   five top-level bands. Only the top-level bands were touched; the nested card-grid containers
   (32/20 and 4px) are component insets, not page gutters. Vertical padding preserved verbatim.
2. **Homepage hero image `tmkhp002`** carries `_padding` with **200px left and right** — 400px of
   inner padding, which forced the widget to 400px at a 375px viewport and produced the 41px of
   horizontal scroll the homepage has had since `_BUILD/100`. (`max-width:100%` on the image can't
   help — the padding sits outside it.) Given responsive values (48 tablet / 0 mobile) rather than
   removed, so the desktop composition is untouched.
3. **`/menu/`'s `tmkmenu3cta` has no padding control**, so it got Elementor's own 10px default —
   the same mechanism as `tmkloc_atmo` in §5. Now 32.
4. **`.tmk-hero--full-bleed` behaved two different ways.** Its content box centred a
   `--container-wide` (1280px) box, putting the hero's own left edge at **112px** at 1440 (80px of
   centring slack + the 32px gutter) while every other row on the same page sat at 32. Review
   round 3 had already given the Location template a scoped `max-width:none` for exactly this
   reason — so one component had two behaviours: Location heroes at 32, Menu / Storie / Città /
   La nostra storia heroes at 112. The override is now the base rule and the scoped copy is gone.
   Text measure is unaffected (h1 keeps its 800px cap, standfirst its 540px); only the axis moved.

¹ **Not a padding value — boxed-container centring, and it needs a decision, not a patch.** For a
centred boxed row, `gutter = max(padding, (viewport − boxed_width)/2)`; past the crossover the
padding term drops out entirely. The residual numbers are therefore the design system's own
**content measures**, one per row: `--container-narrow` 680 → 44 @768 / 172 @1024 / 380 @1440;
`--container-standard` 1040 → 200 @1440; `--container-wide` 1280 → 80 @1440; the kit's 1440 shell
→ 32 at every width up to 1440. Making these numbers equal across routes means **collapsing
narrow/standard/wide into a single measure** — a design-system decision that would undo §131's
homepage parity work and Contattaci's and the 404's deliberately narrow columns. Flagged with the
arithmetic, **not** decided here. The related 1920px `240px` gutter, already open in §130 and
§152, is unchanged and unaffected by anything in this pass.

² Fixed by mechanism 4 above.

³ **`/allergeni/` was rendering with a literal 0px gutter at every width — text touching both
viewport edges.** Session 152 rightly neutralised Hello Elementor's `.site-main` boxed cap for the
three non-Elementor routes with `padding-inline:0 !important` (it was strangling their full-bleed
heroes into a legacy blog column). Storie and La Nostra Storia were unaffected because everything
they print is a TMK component carrying its own gutter — Allergeni is not: its `post_content` is
plain `<p>`/`<ul>`, so it lost its last remaining inline space. Restored on the plain flow
elements only (`css/layout/container.css`), never on component wrappers, so the heroes stay
edge-to-edge and nothing gets a double gutter. Measured text-box left = 32 at all four widths.
⚠ **Still flagged, not fixed:** Allergeni has no `<h1>` and no prose measure, so at 1440 a
paragraph now runs the full 1376px. That is the page's own unfinished build (§10), not a padding
decision — capping it would mean choosing a measure for a page nobody has designed yet.

**Also fixed, found by the audit:** `/la-nostra-storia/` had **353px of horizontal scroll at 375**.
`.tmk-heritage` and `.tmk-heritage__origins` are grids with no `grid-template-columns`, so each got
one implicit `auto` column sized to its content's max-content contribution — and
`.tmk-heritage__origin`'s own `repeat(auto-fit,minmax(300px,1fr))` reports **728px** (2 × 300 + a
64px gap + 2 × 32px padding) no matter how narrow the viewport is. The page's 32px gutter was
intact; the track under it wasn't. `grid-template-columns:minmax(0,1fr)` pins the track to the
container so auto-fit collapses to one column as designed — the same `min-width:auto`
shrink-refusal this file already records twice for flex, here in grid.

### 7 · Practical-info at phone width

**Why the hours wrapped mid-range.** At 390 the card's content box is 262px; the day column takes
its intrinsic width (148px, because `.tmk-practical-info__day` is `white-space:nowrap`) and leaves
the value **114px** — so a 29-character range pair (~190px at 14px bold) always wrapped to two
lines. *Where* it wrapped was then decided by whatever the day label happened to measure, which is
why the break landed between ranges on Tierra ("Venerdì") but **inside one** on Fuego
(`12.00 - 15.30 | 17.30 -` / `23.30`), whose day label is the 5-character "Dalle" and therefore
left a wider, worse-fitting value column. Shrinking the type or the day column cannot fix it: the
value needs ~190px and the longest day label ("Dal Venerdì alla Domenica") needs most of the rest.

Cells are stacked instead, giving the value the card's full width. Deliberately **not**
`white-space:nowrap` — that would trade a mid-range wrap for a horizontal scroll on any viewport
narrower than tested, and no-horizontal-scroll is the harder rule.

**The stack runs to ≤1023, not ≤767**, because the constraint is the facts column's width, not the
phone: measured 375 → 263px, 768 → **304px** (the card is already two columns there), 1024 → 432px,
1440 → 640px. 768 wrapped exactly like 375 did until the breakpoint was widened.

| | Before | After |
|---|---|---|
| hours value lines, Fuego @390 | **2** (breaks mid-range) | **1** |
| hours value lines, Fuego @768 | **2** | **1** |
| hours value lines, Tierra @390 (3 range pairs) | **2 each** | **1 each** |
| hours value lines, Tierra @768 | **2 each** | **1 each** |
| hierarchy | 2 levels (label / row) | 3: section label (caption caps ember) → day (caption secondary) → value (body-small bold ink) |
| block gap / pair gap / intra-pair | 24 / 8 / — | `--space-6` 32 / `--space-3` 12 / `--space-1` 4 |
| card inset @≤767 | `--space-6` 32 | `--space-5` 24 (this is also what buys the value its full line) |
| map @390 | 267 × **200** — collapsed onto its own `min-height` floor, a letterbox strip | 278 × **348**, `--ratio-hero-mobile` |

**Trade-off, stated rather than hidden:** `display:block` on table parts drops the native table
role, so AT reads the pair as "Venerdì / 12.00 - 15.00 | 19.00 - 24.00" in DOM order instead of as
row-header + cell. That reads correctly as a stacked list; the alternative — restoring
`role="table"/"rowheader"/"cell"` — is markup, i.e. a plugin PHP change.

**Offered, not applied (would need a PHP diff and approval):** splitting each `|`-separated range
into its own `<span>` in `practical-info-render.php` would make "never break inside a range" a
structural guarantee at *any* width rather than one established by measurement. Every one of the
14 restaurants' values renders on a single line at 375 / 390 / 768 / 1024 / 1440 today, so it buys
nothing at any supported width.

### Verified — 6 restaurants × 4 widths, 24/24 passing

Arya, Fuego, **Tierra (5 hours rows, 4 with range pairs)**, **Reggio (1 row)**, **Linate (7
images)**, **Capo d'Orlando (3 images)** at **375 / 390 / 768 / 1440**, scroll-through per the
established lazy-render caveat: 0 console errors, 0 page errors, 0 failed requests, 0 broken
images, exactly one `<h1>`, **no horizontal scroll**; lightbox opens and closes by X-click, Escape
and backdrop at every width; 0 grey veils on 3-, 6-, 7- and 8-image strips; 0px thumbnail hover
clip; focus rings intact across 8 consecutive Tab stops; one shared 32px left axis on every
top-level element; no hours value wrapping anywhere.

All 12 sitewide routes re-audited at 375/768/1024/1440: correct HTTP status (404 returns 404),
0 console errors, **no horizontal scroll on any route at any width** (was 41px on Home @375 and
353px on La Nostra Storia @375).

Screenshots `~/Desktop/tmk-qa/locations-design-4/` — `{arya,fuego,tierra,reggio,linate,capo}-{375,390,768,1440}.png`,
plus `lightbox-open-{375,1440}.png`, `thumbs-hover-{375,1440}.png`, `practical-{tierra,reggio}-390.png`.

Files: `css/base/base.css`, `css/layout/container.css`, `css/components/{gallery,hero,narrative-band,practical-info-band}.css`,
`css/pages/heritage.css`, `_BUILD/153-location-shell-fixes.php` (backups
`153-{kit-custom-css,255,36,216}-backup.json`), `_BUILD/90-navbar-fonts-motion.php` (the `both` →
`backwards` back-port). Idempotent — 153 re-run confirms no-op.

---

## 160 — Homepage (#36) cards restyled to the Location card design · `_BUILD/160-homepage-cards.php`

Client decision: one card style everywhere on the homepage, matching the "Altri ristoranti
Temakinho" row on a Location page. Homepage only — `css/components/card.css` was not touched and
the Location template re-verified unchanged afterwards (Arya, 375/390/768/1440: axis, lightbox,
practical-info grid and hours all identical to the round-4 numbers).

### STEP 1 — the target, measured in Chromium before anything was written

`/ristoranti/milano/arya/`, `.tmk-location__nearby .tmk-card`, at 375 / 768 / 1440. Not read off
a screenshot and not read off the source file — `getComputedStyle` + `getBoundingClientRect`.

| Property | Measured value |
|---|---|
| element | `<a class="tmk-card tmk-card--overlay tmk-card--city">` — the whole card is the link |
| radius · overflow | `8px` (`--radius-soft`) · `hidden` |
| min-height · max-width | `180px` · `100%` |
| aspect-ratio | `21/8` at ≥600, **`16/9` at ≤599** |
| rendered card | 310.5×180 @375 · 339.5×180 @768 · 325.5×180 @1440 (min-height governs at this width) |
| box-shadow | rest **`none`** · hover `rgba(36,28,19,.16) 0 8px 24px` (`--elevation-overlay`) |
| background image | `.tmk-card__media` `position:absolute; inset:0`; `<img>` `object-fit:cover` |
| overlay | `.tmk-card__scrim`, `linear-gradient(to right, rgba(0,0,0,0) 30%, rgba(20,14,8,.55) 78%)` — i.e. `--color-scrim-clear` → `--color-scrim-veil`, a **right-hand** falloff, not a flat wash |
| body block | `position:absolute; right:32px; bottom:24px; display:grid; gap:12px; justify-items:end; text-align:right` |
| eyebrow | Basis Grotesque **700 / 12px / 0.96px (0.08em) / uppercase / `#EFE5CF`** (tatami-200), line-height 18.6px |
| eyebrow content | a real count — "4 ristoranti" / "1 ristorante" |
| title | Londrina Solid **900**, `clamp(2rem,4vw,3.4rem)` → **32px @375 · 32px @768 · 54.4px @1440**, line-height 0.95, `#FDFBF5`, text-shadow `rgba(20,14,8,.35) 0 2px 16px` |
| ESPLORA element | **`<span>`, not a link or a button** |
| ESPLORA fill · radius · padding | `#C74814` (ember-600) · `8px` · **`6px 14px`** |
| ESPLORA label | Basis Grotesque **700 / 10.4px (0.65rem) / 0.832px (0.08em) / uppercase / `#FDFBF5`** |
| ESPLORA hover | `#9E3910` (ember-700, `--color-action-primary-hover`) |
| media hover | `transform: scale(1.04)` |
| grid | `display:flex; flex-wrap:wrap; justify-content:center; gap:24px` (`--grid-gutter`), cols 4 / 2 / 1 |
| focusable elements per card | **0 inside** — the card `<a>` itself is the only tab stop |

⚠ Two findings worth keeping from this measurement:
- **The chip is `#C74814`, not the kit's `primary` `#E7591D`.** `css/tokens/colors.css` sets
  `--color-action-primary: var(--ember-600)` with the reason inline: ember-500 scores **3.47:1**
  on cream and FAILS AA, ember-600 scores **4.65:1** and passes. The homepage cards were on the
  kit global, i.e. the failing value. They are now on the token.
- **The target's own resting `box-shadow` computes to `none`**, despite `.tmk-card` declaring
  `--elevation-raised`. Not chased — the homepage now shares the same classes, so it matches by
  construction; recorded so a future session doesn't "fix" one side into disagreeing.

### How it is applied — still native Elementor, no shortcode, no `tmk-*` widget

Every element stays what it was: a Container, an Image widget, a Heading widget. Nothing was
replaced by plugin output. What changed is the CSS classes each element carries plus a handful of
layout settings, so the homepage card is styled by **the same `css/components/card.css` rules that
style the target** — an exact match by construction, with no duplicated values and no second copy
to keep in sync when a token moves. `_css_classes` is an ordinary Elementor control on the
Advanced tab; photo, copy, link and add/remove-a-card all stay ordinary Elementor edits.

**Deliberate consequence, not a side effect:** the four text roles lose their per-widget
`__globals__` typography/colour, because the component owns them now. That is what makes
"changing a colour in Site Settings propagates sitewide" actually true for these cards — and it is
what moved the chip off the failing ember-500.

**The ESPLORA chip is not a nested link.** It is a Heading widget with `header_size => 'div'`
carrying `tmk-card__action`, so it renders `<div class="… tmk-card__action"><div
class="elementor-heading-title">Esplora</div></div>` — the component's chip styling on a
non-interactive element. Verified by tabbing, not by reading markup: **12 consecutive Tab stops,
one per card, every one the card's own `<a>`**, each with a `solid 2px` focus ring, and
`querySelectorAll('a,button,[tabindex]')` inside every card returns **0**.

### Before → after, measured at 375 / 768 / 1440

| | Before | After |
|---|---|---|
| Milano / Roma bands | photo as a CSS `background-image` + flat 50% ink overlay, text **bottom-LEFT**, radius **0**, min-height 240/340 | photo as a real Image widget media layer, right-hand scrim gradient, text **bottom-RIGHT**, radius **8**, `21/8` (16/9 ≤599) → 180 / 268 / 488px tall |
| 4 concept cards + Roma trio of 3 | media-on-top / text-below **content** shape, no photo behind the text, radius 0 | photo-backed overlay shape, `tmk-card--location` `4/3` → 233 / 255 / 226px tall |
| Bologna / Lione / Rimini | same content shape | `tmk-card--city` `21/8`, 180px at every width |
| card eyebrow | Basis 700 / 12px / caps / **`#E7591D`** (ember-500, AA-failing) | Basis 700 / 12px / caps / **`#EFE5CF`** — exact target match |
| card title | Londrina 900 / **25px fixed** / `#241C13` ink on cream | Londrina 900 / **32 → 54.4px** (city) or **25.6 → 38.4px** (location) / `#FDFBF5` + text-shadow |
| ESPLORA | a bare orange **text link label**, no fill | filled chip, `#C74814`, `6px 14px`, radius 8, Basis 700 / 10.4px / caps / `#FDFBF5` |
| hover | none | shadow `--elevation-overlay` + media `scale(1.04)` + chip → `#9E3910` — **byte-identical to the target card, measured side by side** |
| grid gap | 24 desktop / **20 mobile** | 24 at every breakpoint (`--grid-gutter`) |
| focusables per card | 0 | 0 (unchanged — there were never nested links; now verified by tab-through) |
| horizontal scroll @375 | **0** (already repaired by `_BUILD/153`) | 0 |

### Counts come from the taxonomy

Each card's own link URL is parsed for its city slug, published `location` posts carrying that
`city` term are counted, and the eyebrow is rebuilt with Italian pluralisation (**1 ristorante** /
**N ristoranti**). Location cards have no count, so their slot takes the city **term name**,
resolved the same way. Result: Milano 5 · Roma 4 · Bologna/Lione/Rimini 1 each — the script reports
that all five already matched the strings `_BUILD/100` had written, so nothing changed, but they
are now derived rather than typed.

### The one place this is not a pure copy — the band standfirst

The two bands carry a one-line sentence ("Quattro concept, una città che non dorme.") that the
target component **has no slot for**. It was kept rather than dropped — deleting copy is not a
styling decision — and constrained instead, with the width derived from the scrim rather than
picked: the gradient is transparent until 30% and full strength at 78%, so anything left of ~62%
of the card sits over an undarkened photograph. Measured at 375 the sentence was the widest item
in the body, stretched the body to the full card, and put its first half over the bright part of
the picture. Capped at `calc(38% - var(--space-6))` it starts at **62% @768** and **76% @1440**,
entirely inside the scrim. Below 768 the card is on its 180px floor with the stack already 6px
from the top edge, so it is hidden there — which is also exactly what the target card shows at
that size. The copy stays in the page and in the Elementor editor; unhiding it is one line.

### Three bridges in `css/pages/homepage.css`, and nothing else

Elementor's markup differs from `tmk_render_card()`'s in exactly three ways. No value the
component already owns is re-declared.
1. **Elementor puts `_css_classes` on the widget WRAPPER**, so `tmk-card__title` lands on a
   `<div>` and the real `<h3>` sits a level down, where base.css's own `h1–h6` rules beat plain
   inheritance. One `font:inherit; letter-spacing:inherit` rule hands the computed type through —
   the same wrapper-vs-child trap §131 records, solved once instead of per role.
2. **The photo is an Image widget**, so there is one `.elementor-widget-container` between
   `.tmk-card__media` and the `<img>`; it needs `height:100%` or the image has nothing to cover.
3. **There is no `.tmk-card__scrim` element** (an empty Elementor Container would carry its own
   default padding and read as a stray box in the editor's structure panel), so the scrim is a
   `::after` built from the same two scrim tokens — `--color-scrim-clear` is `rgba(20,14,8,0)`,
   `--color-scrim-veil` is `rgba(20,14,8,.55)`, identical output to card.css's own literal stops.
   `z-index` is needed only here: `::after` is the card's **last** box, so without it the scrim
   would paint over the body. The body is lifted to `z-index:2` by its own Elementor control
   (visible in the UI); the media stays at auto.

⚠ **Two silent failures hit while writing that block, both caught by measuring, not by reading:**
- **A stray `*/`.** An edit left six lines of comment prose *after* the comment's closing marker.
  The CSS parser swallowed them plus the following selector, so the rule was simply **absent from
  the parsed stylesheet** while `curl` showed it present in the file. Diagnosed by counting
  `document.styleSheets[…].cssRules` (10 instead of 11), not by re-reading the source.
- **Elementor self-inflates its own specificity.** `.elementor.elementor .e-con >
  .elementor-widget{max-width:100%}` — the repeated `.elementor` is deliberate and gives (0,4,0).
  A one- or two-class rule loses to it silently: `max-width` kept computing to `100%` and the cap
  did nothing. (0,5,0) beats it outright, no `!important`, no load-order dependency.

### Verified 375 / 768 / 1440

0 console errors · 0 page errors · 0 failed requests · 0 broken images · **no horizontal scroll**
· exactly one `<h1>`, still `.tmk-sr-only` (1px) · 12 cards, **0 focusable elements inside any of
them**, 0 nested `<a>` · 5 slides intact · trust-band placement intact · **0 Prenota anywhere in
the page body**. Every measured card property matches the target table above exactly, at every
width. Idempotent — a second run reports "already present" throughout and writes the same 25,514
bytes. Screenshots `~/Desktop/tmk-qa/homepage-cards/`: `homepage-{375,768,1440}.png`,
`band-milano-{375,768,1440}.png`, and the side-by-side pairs `card-hp-375.png` / `card-loc-375.png`,
`card-hp-768.png` / `card-loc-768.png`, `card-homepage-1440.png` / `card-location-1440.png`.

### Reported, not fixed — all pre-existing, all already on record

- Every Milano card reuses `interior-milano.jpg` and every Roma card `interior-roma.jpg`. The
  overlay shape makes this **more** visible than the old content shape did, because the photograph
  is now the whole card. Needs per-location photography.
- `garland-frame` still has no attachment in the media library.
- `homepage-data.js` omits Milano Linate and Roma Fiumicino, so neither has a card. They **are**
  counted, because the counts are taxonomy-derived — Milano reads "5 ristoranti" above four cards.
  Correct data, visibly incomplete composition.

Files: `_BUILD/160-homepage-cards.php` (backup `160-homepage-cards-backup.json` — `homepage-backup.json`
and `homepage-backup-131.json` untouched), `css/pages/homepage.css`. Two elements created:
`tmkhpm34`/`tmkhpb34` and `tmkhpm59`/`tmkhpb59`, the bands' media layer and body wrapper.

---

## 170 — Mobile + tablet header (template #25) · `_BUILD/170-mobile-header.php`

Scope: below the desktop tier only (`@media (max-width:1024px)` — the width at which Elementor's
`dropdown-tablet` setting swaps the inline nav for the burger). No rule reaches 1025px+.
**No plugin PHP and no #25 element setting was changed** — everything is kit Custom CSS plus one
block appended to Custom Code snippet **#558**, the two mechanisms §0 ranks above a build script.

### STEP 1 — diagnosis (measured before any change)

Row: `display:flex; flex-wrap:nowrap; justify-content:space-between; align-items:center;
gap:24px; padding:12px 32px`. Three children, all `order:0`:

| child | role | flex | w @375 | @390 | @768 |
|---|---|---|---|---|---|
| `3da6a60` | logo | `0 1 auto` | 42.5 | 47.3 | 118 |
| `23d7b26` | nav | **`0 0 auto`** | **132.1** | **132.1** | 408.3 |
| `6f7db9d` | CTA | `0 1 auto` | 88.4 | 98.5 | 129.7 |

**(a) Why the burger was centred — two stacked artefacts, neither intentional.**
`.elementor-menu-toggle` computed `margin: 0px 49.5625px` — **auto side margins**, Elementor's own
`toggle_align:center`. It centred itself inside a widget that was **132.1px wide** because the
CLOSED dropdown (`nav.elementor-nav-menu--dropdown`) is `position:static` with height 0 but a
max-content width of 132.1px — the width of "LAVORA CON NOI". §130's
`width:auto!important; flex:0 0 auto!important` then sized the container to that and forbade it
shrinking. A 33px control centred in a 132px box sized by invisible text.

**(b) The orange box — `css/base/base.css:16`, `:focus-visible{outline:var(--focus-ring)}`,**
with `--focus-ring: 2px solid var(--color-action-primary-focus)` = `2px solid #C74814`
(`css/tokens/colors.css:77–78`). Measured: at rest the toggle has `outline: 3px none` (nothing);
after a tap it computes `outline: 2px solid rgb(199,72,20)` + `outline-offset:2px` and **persists**,
because Elementor renders the toggle as `<div role="button" tabindex="0">` — not a native button —
so Chrome matches `:focus-visible` on pointer activation. **Not** a §130 border, **not** Elementor
styling. It is the design system's own required ring and it **stays** (WEBSITE_SHELL_SPEC L56).
What was removed is the off-system box *underneath* it: Elementor's
`.elementor-menu-toggle{background-color:rgba(0,0,0,.05); border-radius:3px}` — a grey that exists
nowhere in `tokens/colors.css`, and a **fourth radius value** against the governed three. Ring on a
grey rounded rect reads as a box; ring on a clean icon reads as a ring.

**(c) Why "PRENOTA ORA" wrapped — hypothesis CONFIRMED by arithmetic, cause fixed not symptom.**
At 375: 64 padding + 48 gaps + 42.5 logo + 132.1 nav = 286.6 → **88.4px** left for a CTA whose
natural width is **116.5px** (80.5 label + 36 padding). It is the only child with `flex-shrink:1`,
so with the row forced `flex-wrap:nowrap` by §130 it absorbed the entire deficit and its label
broke to 2 lines. `ctaLines` measured **2** at 375/390/414 **and 768**; 1 only at 1024/1440.
Taking the closed dropdown out of flow returns ~99px — far more than the 28px shortfall — so the
label fits on one line with **no `white-space:nowrap`, no type shrink, no crushed burger**.

**Design source DOES define a mobile header** (`WEBSITE_SHELL_SPEC.md`): `MobileNav` = left
**Drawer**, city **accordion**, flat links, sticky Prenota, **≥44px** targets (L19/L48); hamburger
reveals it below the tablet tier (L45); Ristoranti is a **city-level accordion, no nested flyouts**,
≤3 taps (L37); Drawer **focus-trapped, Escape/scrim-closable**, 2px ember ring everywhere (L56).
Followed: accordion, ≥44px rows, Escape, ember ring, token surfaces. **Three disclosed deviations**
— (1) it is a full-width panel under the bar, not a left-anchored Drawer, because a Drawer means
replacing Elementor's nav widget; (2) L45's "header CTA goes full-width at mobile" is overridden by
this task's explicit STEP 2 (logo left, controls right, one line); (3) **no focus trap** — the task
requires the menu "must not trap the user with no way back", and a non-modal panel with natural tab
order + Escape satisfies that better than a trap.

### CTA label — kept "PRENOTA ORA" (option 2), and why

Option 1 (shorten to "PRENOTA" at mobile) was rejected on mechanism, not principle: Elementor's
button `text` control is **not responsive**, so it would either change the desktop label — a
regression this task forbids — or require a second CTA element, i.e. duplicating a booking button
(D-32 territory). Once the real cause was fixed the full label fits on one line at 375 with ~65px
of slack left in the row, so option 2 costs nothing. **D-32 honoured**: `href`, target,
`TMK_BOOKING_URL`, booking routes and `booking_url` meta all untouched; colour unchanged
(`rgb(231,89,29)`); only mobile size/padding/position changed.

### Before → after (route `/`, closed header)

| width | header h | burger | CTA lines | CTA w | gap logo→burger | gap burger→CTA | right inset |
|---|---|---|---|---|---|---|---|
| 375 | 91 → **88** | 33×33 centred → **64×64 right** | 2 → **1** | 88.4 → **117** | 73.5 → **64.5** | 73.6 → **24** | 32 → **32** |
| 390 | 91 → **88** | 33×33 → **64×64** | 2 → **1** | 98.5 → **117** | — → **65.5** | — → **24** | — → **32** |
| 414 | 91 → **88** | 33×33 → **64×64** | 2 → **1** | 114.7 → **117** | — | — → **24** | **32** |
| 768 | 91 → **90** | 33×33 → **64×64** | 2 → **1** | 129.7 → **141** | — | — → **24** | **32** |
| 1024 | 91 → **90** | 33×33 → **64×64** | 1 → 1 | 140.5 → **141** | — | — → **24** | **32** |
| **1440** | **90 → 90** | hidden → hidden | 1 → 1 | **140.5 → 141** | — | — | — | 

Header height never exceeds the §130 baseline of ~91px at any width. The burger is `--space-8`
(64px), which is why 375–414 land at 88px: it is now the tallest child.

### STEP 3 — the open state, reviewed for the first time

| | before | after |
|---|---|---|
| header height on open | **91 → 301px**, `<main>` pushed to y=301 | **88px, page not moved** |
| dropdown position | `static`, in flow inside the header row | `absolute`, `top:100%`, `inset-inline:0` |
| dropdown width | **132px** on a 375px screen | **full viewport width** |
| background | `rgb(255,255,255)` pure white (off-token) | `--color-surface` |
| row height | **42px** (below the 44px rule) | **≥44px** (`--space-8` min) |
| row hover | `#3f444b` on `#fff` (blue-grey, off-palette) | `--color-surface-sunken` |
| stray orange bar across rows | yes — the desktop `.elementor-item::after` underline is pinned `left:0;right:0` and its selector matches the dropdown `<ul>` too | `content:none` in the dropdown only |
| overflow | `overflow-y:hidden` — could not scroll | `auto`, `max-block-size: calc(100dvh - 100%)` |
| **Escape closes** | **no** | **yes, and focus returns to the burger** |
| **Enter opens** | no | **yes** |
| click outside closes | no | yes |
| RISTORANTI accordion | arrow present, unstyled | 14 locations, indented on sunken, panel scrolls |
| sub-arrow target | ~10px glyph | **64×64** |

⚠ **Enter was fixed by DELETING code, not adding it.** An added keydown handler made it worse: an
event trace showed `keydown:Enter → click → open`, then **Elementor's own keyup handler** →
`click` → closed again. Elementor already activates the toggle on keyUP; the keydown handler was a
double-toggle. Removed. Escape and click-outside are the only genuinely missing behaviours and are
all snippet #558 now adds.

### Verified — 375 / 390 / 414 / 768 / 1024 / 1440 × `/`, `/ristoranti/milano/arya/`, `/menu/`

Burger and CTA on one line each, CTA label on 1 line at every width, no stray orange box, burger
opens/closes by click **and** keyboard, all 5 nav items reachable with a visible `2px solid
rgb(199,72,20)` ring, RISTORANTI accordion reveals 14 locations and the panel scrolls, 0 horizontal
scroll, 0 console errors. **1440 unchanged** (header 90px, CTA 140.5→141px, burger hidden).
Menu-page suites re-run after the header height change (91→88): all four still pass, including the
category bar's dock sync (100% of frames) — it tracks the header's real bottom edge, so it followed
automatically. Screenshots `~/Desktop/tmk-qa/mobile-header/`.

⚠ **Reported, not changed:** 1024 is inside the changed range (Elementor shows the burger up to
1024, and the kit's tablet tier ends there), so its burger moved from centred to right-clustered —
listed above so the delta is explicit rather than hidden. If 1024 must stay literally untouched it
is one number in the media query.

Files: `_BUILD/170-mobile-header.php`; backups `170-{header25,kit-custom-css,snippet558}-original.json`
(written once, never overwritten — the pristine kit CSS reconstructs to exactly 17467 bytes, its
pre-change length) plus `-last.json` snapshots each run. Idempotent — both payloads are
marker-delimited and replaced, confirmed no-op on re-run.

---

## 180–182 — Chiusura progetto: griglia, tipografia, decor, recruiting, Storie · 2026-09-03

Five tasks in one closing session. **No browser automation was used** — the client excluded it —
so everything below is verified against **served HTML, served CSS and reproduced CSS arithmetic**,
never against a screenshot. Read the QA caveat at the end before treating any of it as visual
sign-off.

### The one grid — `--container-page` / `--page-gutter` / `--page-inset`

Four horizontal grids were live at once and only coincided at ≤1504px, which is why eleven
sessions never saw it: Elementor **boxed** rows (footer, `tmkloc_col`) capped at the kit's 1440
`container_width`; Elementor **full** rows (`#tmk-header`, every hero) had no cap at all, just a
32px inset; plugin page wrappers sat at `--container-standard` 1040 or `--container-wide` 1280;
reading columns at 680. At 1900 the Location hero's headline was at x=32 and the breadcrumb
directly under it at x=230 — the 198px step the client reported.

`tokens/layout.css` now defines, additively:

```
--container-page:1440px            /* mirrors the kit's own container_width, not a new number */
--page-gutter:var(--space-6)       /* 32px, the value 152 already rolled out */
--page-inset:max(var(--page-gutter),calc((100% - var(--container-page)) / 2))
```

`--page-inset` is the whole mechanism: for a centred boxed row
`gutter = max(padding,(viewport − container_width)/2)`, and `max()` *is* that formula. Applied as
`padding-inline` to a full-width element it reproduces Elementor's boxed geometry **exactly**,
while the background stays edge-to-edge. Used on `#tmk-header` (kit Custom CSS, keyed on the ID at
(1,0,0) so it beats Elementor's per-element (0,3,0) with no `!important`), on all three hero
variants, and on `.tmk-megamenu` (which already used this pattern but against 1280).

**Two roles only, for every top-level region:** `.tmk-page` (SECTION → 1440) and
`.tmk-page--reading` (READING → 680). `--container-standard` (1040) is no longer used by any page
section: three page widths were two too many and the middle one is what made the footer look
misaligned with everything above it. Reading columns also gained their true measure — the padding
now sits **outside** the cap, so a "680 column" is 680 wide instead of 616.

`_BUILD/180-global-shell.php` **removes** the explicit `boxed_width` (1040/1280) from every
top-level section of #251 #252 #559 #560 #561 #562 #563 #569 rather than rewriting it to 1440, so
those sections inherit the kit and there is exactly one number to change in future. It also
finishes 152's job: `padding{,_tablet,_mobile}.left/right` of 16/20/24/40 → 32 everywhere.

**Computed alignment, all four mechanisms (px from the viewport edge to the content column):**

| viewport | header | footer | hero | content | homepage hero | homepage city |
|---|---|---|---|---|---|---|
| 1920 | **240** | **240** | **240** | **240** | 320 | 400 |
| 1600 | **80** | **80** | **80** | **80** | 160 | 240 |
| 1440 | **32** | **32** | **32** | **32** | 80 | 160 |
| 1024 / 768 / 390 | **32** | **32** | **32** | **32** | 24 | 24 |

⚠ **The homepage is the one deliberate exception** and needs a decision — see "Aperto" below.

### Decor — a real system instead of two stickers

The composition that works lived **only** in page #36's own Elementor Custom CSS; every other route
got `tmk_render_page_decor()`: two `<img>` pinned at 26% and 64% of **total document height**. Two
pieces is not a composition and a percentage of document height drifts the moment an editor adds a
paragraph — the "sticker messi casualmente / tagliati male".

`css/components/decor.css` is that system, generalised from the homepage's own principles
(viewport-edge anchoring via `--edge:calc(50% - 50vw)`, section-relative vertical anchoring,
`z-index:-1` inside an `isolation:isolate` stacking context, `overflow-x:clip` so crops land on the
viewport edge, `pointer-events:none`). **Nine motifs, five assets — the same five the homepage
composes with** (garland #26, leaves-banana #46, snake #47, koi #229, macaw #557). Nothing new was
drawn. A page opts in by adding one class to a section that already exists; art URLs arrive as
`--tmk-art-*` custom properties printed once per request by `tmk_decor_art_vars()`.

**Restraint is enforced by the API, not by discipline:** left motifs are `::before`, right motifs
`::after`, so a section can carry at most one of each and never three.

⚠ **The geometry is derived, not eyeballed.** Each motif states its own `--w` and anchors at a
FRACTION of that width (`--f`), because since the grid change the gutter is `(vw−1440)/2` — 240px
at 1920 and literally zero at 1440. A fixed offset that sits in the gutter at 1920 sits a third of
the way into the text column at 1440. Rotation is accounted for: `rotate()` does not move `left`,
so the visible edge is offset by `(bboxWidth − width)/2`, and each rule states the multiplier that
falls out of its own angle and ratio. A mid tier (1025–1599) adds `--push:0.16` and
`--tmk-decor-fade:.62`; ≤1024 everything is hidden except an explicit `.tmk-decor--keep` piece,
exactly as the homepage does.

Computed intrusion **behind** the text column (never over it — everything is `z-index:-1`):
0px at 1920 for every motif; at 1440, 8–38px. The vines are excluded from `--push` at full
strength because their visible width is only 0.197×`--w`, so a 16% push would have made them
vanish entirely below 1600 — caught by running the geometry, not by reading the rule.

### Typography — the missing middle layer

The scale was in `tokens/typography.css` and the faces in `tokens/fonts.css`, but nothing in
between: every page re-spelled the same role by hand, and the line-height drifted 1.4 / 1.5 / 1.6 /
1.7 across nine near-copies. **That drift is what read as "everything has the same weight".**

- **Audit first:** no new face was needed or added. The project ships exactly two, both self-hosted;
  Elementor loads no Google font of its own and the theme adds none.
- `h5`/`h6` had **no size at all** — base set Londrina 900 on `h1–h6` but sized only `h1–h4`, so an
  `<h5>` fell back to the UA's 0.83em and rendered *smaller than body text in the display face*.
- `--text-h2` / `--text-h3` are now fluid `clamp()`s over the same 1.25 ratio, with the **previous
  fixed value as the floor** — nothing renders smaller at any viewport.
- New roles: `--text-lead` + `--leading-lead` (the standfirst, previously written as `--text-h4`,
  a *heading* token, for body copy), `--leading-prose` (1.7, the value eight files each hard-coded),
  `--text-h5`/`--text-h6`, `--leading-meta`.
- New classes in `base.css`: `.tmk-lead`, `.tmk-body-large`, `.tmk-meta`, `.tmk-title-1/2/3`,
  `.tmk-section-title`, `.tmk-section-rule`.
- **Kit globals realigned to the CSS tokens** (`_BUILD/180`): `tmk_h1/h2/h3` were frozen at the
  clamp floor, so an Elementor `<h1>` was up to 9px smaller than a plugin `<h1>` on the same page.
  Elementor accepts `{"unit":"custom","size":"clamp(...)"}` — verified empirically against the
  generated `post-5.css` before relying on it.
- Added `tmk_display`, `tmk_lead`, `tmk_structure_lg` (the gap `_BUILD/102` recorded: every large
  global was the Voice face, so the Shop's prices had to fall back to Londrina — they are now
  Basis Grotesque 700).
- **`secondary` mislabel fixed.** Titled "TMK Structure (Basis Grotesque)", actually set to
  Londrina Solid 400. Safe: a full scan of every `_elementor_data` for
  `globals/typography?id=...` returns **zero** references to `primary` or `secondary`.
- `_BUILD/182` promotes band labels across both engines: **an `<h2>` whose typography global is
  `tmk_eyebrow`** → `tmk_h2` + `tmk_text_primary` + the ember rule (`.tmk-section-rule`). Matching
  on what the element *is* rather than on a class name matters — the first pass keyed on `__label`
  and silently missed the Shop's two labels, which carry `tmk-sh-eyebrow`.

### Lavora con noi — the client can now actually run it

**STEP 0: before this, they could not create a usable opportunity at all.** No ACF group existed
for `job` (the four that exist cover location / menu_item / city / testimonial); every careers
field was a bare `register_post_meta()` with no admin UI; the CPT declared neither `thumbnail` nor
`excerpt` while the front end read both; and the hub's Open Roles were **four hard-coded Elementor
card containers** from `_BUILD/104`, so a job created in wp-admin could never appear and a deleted
one would never go away.

Now: `job_area` taxonomy with exactly **Head Quarter** and **Ristoranti** (checkbox metabox, so a
third category cannot be typed by accident); a real ACF group whose field `name`s are the existing
meta keys; `location_id` as a Post Object picker scoped to `location`, with the displayed sede
**derived** from it; `[tmk_jobs]` rendering the live listing with a progressive-enhancement filter
(every role is server-rendered, the bar is `hidden` until JS reveals it, and it only shows
categories that actually have a job); `[tmk_job_form]` with first/last name, email, phone, **real
CV upload** and a dedicated **cover letter**; `[tmk_job_requirements]` rendering the specs with a
heading and real line breaks, absent when empty.

Verified end to end by POSTing to `admin-ajax.php`: valid submission → stored + CV attached +
notified; bad nonce → 403; missing/invalid fields → one message naming every one; `.exe` renamed →
rejected by extension+MIME; no CV → rejected; honeypot → answered with a success shape (a bot told
it failed retries). CRUD verified: create → appears with the derived kicker; edit to Head Quarter
with no location → kicker follows; delete → gone. Test data removed afterwards.

⚠ **CV privacy.** Stored filenames are randomised (32 hex) because WordPress serves
`/wp-content/uploads/` directly: a `private` attachment is hidden from the admin list and REST, but
its **file** has no capability check in front of it. Randomising makes the URL unguessable; it is
**not** access control. See "Decisioni" below.

### Storie

`tmk_story_related_cities()` was `array( 'rimini', 'milano' )` — two slugs typed into the
signature, shown whatever the magazine was about. It is now derived from the stories on the page,
in display order, de-duplicated. The card kicker gains the story's own city
("Cultura · Milano · 29 maggio 2026"); reading time moves to the cover and the article header,
where it is a real decision aid. The row uses `tmk_render_city_card()` — the identical
`.tmk-card--overlay.tmk-card--city` the Location page's "Altri ristoranti" row and the city bands
use. **There is no Storie-only restaurant card, and there never was one**; what was missing was the
connection, not the component. Band rhythm is now two-tier (airy between movements, structured
between a band and the band that continues its thought) instead of one uniform 72–112px gap — the
"spazio vuoto percepito" was uniformity, not size.

### ⚠ QA caveat — what was and was not verified

Browser automation was excluded by the client, so **no page was measured or screenshotted in a
browser this session**. What WAS done: all 18 routes returned the correct status with **0** PHP
warnings/notices, exactly one `<h1>` (except `/allergeni/`, which still has none — pre-existing),
0 empty `src`/`href`; **103 unique URLs** crawled, 0 broken images and 1 broken link
(`/ristoranti/milano/feed/`, a `rel=alternate` feed link, pre-existing); the homepage's page body
is **byte-identical** before and after; every new rule confirmed present in the *served* CSS; and
the grid and decor geometry reproduced arithmetically at 1920/1600/1504/1440/1280/1024/768/390.
Static verification is necessary but **not sufficient** — nothing here is visual sign-off.

### Aperto — richiede una decisione

1. **Homepage vs the grid.** #36 keeps its own 1280/1120 boxed widths and 24px padding, so it is
   now the only route off the shared grid (table above). Aligning it means either changing those
   widths — which moves every `--edge`-anchored decoration in its Custom CSS — or, much cheaper,
   changing only its padding 24 → 32, which affects nothing above 1344px and would align the
   homepage with the rest of the site at 1024 / 768 / 390. Not done: the brief says do not alter
   the homepage's composition.
2. **CV storage.** Randomised filenames are a mitigation, not access control. Real privacy needs
   the uploads path protected at the server (nginx here, so no `.htaccess`) or applications routed
   to an ATS.
3. **Mail transport.** There is no SMTP plugin and no mail configuration on this install; `wp_mail`
   falls through to PHP `mail()`. `recruiting@temakinho.com` (real, archived) is stored as the
   option `tmk_applications_email` and is filterable. **Production needs a real transport** or
   notifications will silently not arrive — the application itself is stored first and a failed
   send is recorded on the record, so nothing is lost.
4. **`/allergeni/`** still has no `<h1>`, no prose measure and no TMK component (its routing was
   disabled by PATCH 1 and the page was never rebuilt), so it could not take a decor composition
   either. It is the last unbuilt route.
5. **`/menu/` carries no decoration** — deliberately skipped: its sticky category bar docks to the
   header, and `isolation:isolate` on that section would confine a fixed-position descendant's
   stacking order. Needs a look before decorating.
6. **Location pages carry no `<main>` landmark** — unchanged, on record since §10.

Files: `_BUILD/180-global-shell.php`, `181-careers-fields.php`, `182-section-titles.php` (backups
`180-*`, `181-*`, `182-*-backup.json`); plugin CSS `tokens/{layout,typography}.css`,
`base/base.css`, `layout/{container,header,footer}.css`,
`components/{decor(new),hero,card,form,narrative-band,timeline}.css`,
`pages/{story,city,location,contact,careers,event,article,heritage,system,legal,menu}.css`,
`refinements/shell.css`; plugin PHP `inc/{content-model,careers-render,card-render,refinements,
story-render,city-render,heritage-render,legal-render,enqueue}.php`; new
`js/pages/careers-filter.js`. All PHP edits logged in `_BUILD/PHP-PATCH-NOTE.md` PATCH 4.

### 180–182 · second pass — card system unified, Location decor, metadata register

**One photographic-card language, not two.** The site had drifted into two: the Homepage was
rebuilt by hand into `.tmk-hp-card`/`.tmk-hp-band` with its own Custom CSS, while
`.tmk-card--overlay` still rendered the restaurant cards on Storie, the City pages, the Location
"Altri ristoranti" row and the Careers hub. Measured side by side they disagreed on radius
(18 vs 8), scrim (bottom-up ink vs left-to-right), title (uppercase / 0.9 leading vs sentence case
/ 0.95), label tracking (1.6px vs 0.96px), chip (13px, 10-20 padding vs 10.4px, 6-14) and hover
(lift + wide soft shadow vs shadow alone).

The component is aligned **to** the Homepage; the Homepage is not touched and **cannot** be — it
carries none of these classes (verified in served HTML: zero `tmk-card`, `tmk-card--overlay`,
`tmk-card__scrim` on `/`), and its page body is byte-identical before and after (25 511 bytes).

Four additive tokens, each documented where it lives: **`--radius-media:18px`** (an explicit,
client-authorised FOURTH radius, scoped to photographic media surfaces only — the chip stays on
`--radius-soft`, because 6px would have been a fifth value for a 2px difference),
`--elevation-lift`, `--color-scrim-hint`, `--tracking-caps-wide`. Off-scale reference values were
snapped, not copied (22/40px insets → `--space-5/6`, 10-20px chip padding → `--space-3/5`, a 13px
chip label → `--text-body-small`).

⚠ **Two deliberate departures from the reference, both stated rather than hidden:**
1. **The chip stays on ember-600, not the Homepage's `primary` (ember-500).** Recomputed this
   session: cream on ember-500 is **3.47:1**, on ember-600 **4.65:1**. The chip label is 14px bold
   — WCAG "large text" starts at 18.66px bold, so this is NORMAL text and the 4.5:1 threshold
   applies. ember-500 fails it. "WCAG AA is met at the semantic-token level" is a governing rule
   here and a colour cannot be un-failed by copying it.
2. **The eyebrow stays tatami-200, not full cream.** The reference sets label and title to the
   same inverse colour; one step of separation is what keeps the label subordinate to the title —
   the same hierarchy this session's typography task asks for.

Sizing was deliberately NOT unified: `min-height` + `aspect-ratio` stay as they are. The brief
allows layout to vary by context, this component renders in 1/2/3/4-up grids where the reference
only renders full-width or 4-up, and the ratio+min-height pairing is load-bearing against the
documented 375px horizontal-overflow bug.

**Job cards** moved from the `city` variant (21:8 banner) to `location` (4:3) — at three-up a 21:8
card is ~460×175 with no room for the title.

**Location pages now carry decoration too** (they were the one route the first pass left bare).
They have no decoratable *section* — `tmkloc_col` holds widgets directly — so `_BUILD/180` gained
a nested pass that writes `_css_classes` on two widget wrappers. The choice of which two is a
constraint, not taste: the decor engine opens a stacking context (`isolation:isolate`), so any
anchor containing a `position:fixed` descendant would confine it. That rules out `tmkloc_atmo`
and `tmkloc_col` (the gallery lightbox lives in both — the same class of bug review round 4 spent
a section fixing), `tmkloc_book` (D-32) and `tmkloc_prac` (already has its own local snake). Menu
preview + nearby row remain. With the two existing local pieces that is four illustrations per
Location page, the same density as every other route. Lightbox re-verified present and unaffected.

**Metadata register.** `.tmk-article__byline` was uppercase at `--tracking-caps` — byline, kicker
and eyebrow all typeset identically. It now takes the `.tmk-meta` treatment (caption, structure
400, sentence case). This reaches the LIVE template too, not just the dormant shortcode path:
Elementor puts `_css_classes` on the widget wrapper and `text-transform`/`letter-spacing`/`color`
inherit into the `<p>` inside it, so template #560's byline was being uppercased from here.
`.tmk-legal__updated` and `.tmk-breadcrumb__list` were given the same explicit metrics.

**Font audit (task 3, "non usare font-weight non realmente caricati").** Scanned every
`_elementor_data` and `_elementor_page_settings` plus all 47 stylesheets: only the two project
families are referenced anywhere (Londrina Solid ×12, Basis Grotesque ×19 in Elementor documents),
**every weight used has a matching `@font-face`**, `elementor_google_fonts` is `false`, and no
route requests `fonts.googleapis.com` or `fonts.gstatic.com`. No foreign family, no synthesised
weight.

**Regression, proven not asserted:** `practical-info-render.php`, `map-block-render.php`,
`elementor-widgets.php` and `elementor/class-tmk-widgets.php` are **byte-identical** to their
pre-session state, so the location-map behaviour is untouched. All 14 location pages re-checked:
HTTP 200, 0 PHP messages, one `<h1>`, hero + gallery + lightbox + hours + nearby present on all
14; `tel:` absent on Fiumicino and Capo d'Orlando and `mailto:` absent on Capo d'Orlando (the
archive publishes none — already on record); map absent on **Linate and Fiumicino only**, which
`practical-info-render.php`'s own comment records as intended ("Absent -> no map at all (Linate,
Fiumicino: the old site published no map link for them)").

⚠ **Flagged, not changed — `/ristoranti/{city}/` labels its restaurant cards "Prenota".** The card
links to the restaurant page, not to booking, so under §8 the governed verb is wrong; the same
component says "Esplora" on Storie and on the Location page's nearby row. It is left alone because
D-32 makes anything Prenota-shaped a stop-and-ask, and because the Homepage — the declared
art-direction reference — puts "PRENOTA ORA" on the same kind of card.

⚠ **No privacy/consent checkbox was added to the application form.** The brief says to include one
"se già previsto dal progetto" — it is not: neither the contact form nor the newsletter has any
consent field, anywhere. Inventing GDPR copy is not a design decision to make silently, and a CV
plus a cover letter is personal data. Flagged for Carlo.

### 183 — la card ristorante condivisa arriva sulla singola storia · `_BUILD/183-shared-restaurant-card.php`

Audit di dove compariva `.tmk-card--overlay` PRIMA di toccare qualsiasi cosa: `/storie/` 3 ·
`/ristoranti/{city}/` 5 · pagina ristorante 4 · `/lavora-con-noi/` 4 · **singola storia 0** ·
homepage 0 (sistema proprio). La singola storia era il buco.

Template #560 aveva una card **finta**: un container Elementor costruito a mano (`tmkea014`) con
due heading — "Esplora i nostri ristoranti" e un chip "Esplora" — che linkava a una URL di città
composta dal meta `related_city`. Nessuna foto, nessun nome città, nessun conteggio: un blocco di
testo a forma di card. `_BUILD/105` ne aveva documentato la ragione (nessun dynamic tag di
Elementor Pro risolve slug → term → foto di quel term) registrandola come un passo indietro
rispetto al design. PHP non ha quel limite: `[tmk_story_city]` rende `tmk_render_city_card()`, cioè
**lo stesso identico componente** delle altre quattro collocazioni, dal `city` term reale.
L'heading viaggia dentro lo shortcode così il blocco può sparire tutto quando una storia non ha
città, invece di lasciare un titolo sopra il vuoto (stessa logica di `tmk_job_requirements`).

**`/menu/` decorata — l'assunzione della prima passata era sbagliata.** Era stata saltata perché
si riteneva che i suoi modal e la barra categorie sticky rendessero insicuro un ancoraggio
`isolation:isolate`. Ricontrollato sulla pagina servita: `grep -c tmk-modal` aveva contato il
`<link>` del CSS e il tag dello script, **non markup** — `/menu/` non rende alcun modal. L'unico
`position:fixed` della pagina è il search overlay, stampato in `wp_footer`, fuori da ogni
container. E `position:sticky` non è toccato né da `isolation` (uno stacking context non è un
containing block) né da `overflow-x:clip` (clip non crea uno scroll container — la ragione per cui
questo progetto usa clip e mai hidden, già a verbale per `<body>`).

**Audit statico dell'overflow orizzontale** (sostituisce la misura in browser, esclusa dal brief):
scansione dei 47 fogli per `width:100vw` in flusso, margini negativi orizzontali reali, larghezze
fisse >360px in flusso e track di griglia non comprimibili. **5 dichiarazioni in tutto il
progetto, nessuna introdotta in questa sessione**: due sono il pattern `.alignfull` standard
(preesistente, protetto da `body{overflow-x:clip}`), una è il bleed di `.tmk-menu-index`
(preesistente), e le due mie sono margini **verticali** (sinistra/destra = 0), incapaci per
costruzione di causare scroll orizzontale.

**Report di chiusura:** `FINAL_SITE_CLOSURE_AUDIT.md` nella root dell'install — 14 sezioni, con
prima/dopo/perché/dove per ogni intervento e le 6 decisioni che restano a Carlo.

### QA pre-lancio con browser reale · 2026-09-03

MCP Chrome ancora non connesso, ma Chrome e i browser Playwright erano già in cache sul Mac:
`npm install playwright` nello scratchpad e Chromium reale pilotato da lì. **Sei difetti trovati,
nessuno rilevabile staticamente** — dettaglio completo in `FINAL_SITE_CLOSURE_AUDIT.md` §Q1.

1. **Asse hero, doppia correzione.** Un intervento del 2026-09-02 nel **CSS aggiuntivo del
   Customizer** (post `custom_css` #20890) cappava `.tmk-hero__content` a 1504px con
   `margin-inline:auto` — corretto quando il padding era 32px fisso. Con `--page-inset` le due si
   sommavano: a 1900 l'H1 dell'hero stava a **428** contro 230 di header/breadcrumb/footer.
   Invisibile sotto i 1504. `_BUILD/184` rimuove solo quel blocco; il blocco "nome sede" resta.
   ⚠ **Quel campo del Customizer esisteva e nessuna sessione lo aveva mai letto.** Vale la pena
   controllarlo prima di attribuire un comportamento al plugin o al kit.
2. **`/ristoranti/` era il loop grezzo di WordPress** — HTTP 200, zero `<h1>`, `archive.php` di
   Hello. Linkata da 9 punti, incluso il breadcrumb di ogni pagina ristorante. Costruita con soli
   componenti esistenti (hero `minimal` + `tmk_render_city_card`), città derivate dalla tassonomia:
   il legacy `[tmk_city_cards]` ha 5 slug hard-coded e ometteva Reggio Calabria e Capo d'Orlando.
3. **`form.action` restituiva l'`<input name="action">`, non l'URL.** `HTMLFormElement` è
   `[LegacyOverrideBuiltIns]`: un controllo che si chiama come una proprietà dell'interfaccia la
   oscura. `fetch()` chiedeva un URL chiamato `[object HTMLInputElement]`, 404, `catch`, messaggio
   d'errore — **senza alcun errore in console**. Rompeva il form candidatura **e la newsletter, che
   sta nel footer di ogni pagina**. Il test via curl non poteva vederlo: POSTa direttamente ad
   `admin-ajax.php` senza passare dal JS.
4. **L'attributo `hidden` perdeva contro qualsiasi `display` di classe** (specificità 0 contro 0,1,0):
   dopo un invio riuscito il modulo compilato restava a schermo. Risolto una volta nel layer base con
   `[hidden][hidden]`.
5. **Gutter del Menu** ancora a 24/16 su tablet e mobile (residuo del vecchio sistema).
6. **Il sistema decorativo non dipingeva affatto.** Tre tentativi prima di azzeccarlo, tutti
   misurati — la sequenza completa è nel PATCH 8. In sintesi: `overflow-x:clip` **sull'ancora**
   cancella un pezzo posizionato fuori dall'ancora; `overflow-clip-margin` **viene calcolato ma
   Chromium lo ignora** quando un asse è `clip` e l'altro `visible`; togliere il clip fa allargare
   il documento perché l'overflow di `<body>` **propagato** al viewport lascia `<body>` di fatto
   `visible`. La soluzione è **`html{overflow-x:clip}`**.
   ⚠ **Misurare la scatola di uno pseudo-elemento non dice se è dipinto.** Le prime sonde davano
   "motivo attivo, box corretta" mentre a schermo non c'era niente. Trovato guardando uno screenshot.

**Numeri finali:** overflow orizzontale **0 su 125** combinazioni (25 route × 5 breakpoint) ·
griglia allineata **85/90** (le 5 sono la homepage, esclusa per decisione del cliente) ·
**0** errori console/pagina/richieste/immagini su 90 · **167 CTA** hit-testate, **0** bloccate ·
**81** istanze di decorazione, **0** violazioni di contratto · un `<h1>` ovunque.
Flusso Lavora con noi verificato dall'admin loggato fino alla mail catturata da **Mailpit**.
Homepage byte-identica e con tutte e 7 le decorazioni.

**Verdetto: NO-GO fino a cinque azioni di configurazione**, nessuna delle quali è codice — fuso
orario su `Europe/Rome` (oggi UTC: gli orari eventi escono 2 ore indietro), 89 URL `localhost`
slash-escaped dentro `_elementor_data` (un `wp search-replace` normale **non li trova**: serve la
forma escaped o Elementor → Strumenti → Sostituisci URL), Sample Page da eliminare, SMTP da
configurare, testo del consenso privacy dal cliente.

---

## Rework visivo pagine interne · 2026-09-04 · `_BUILD/185-187` + `FINAL_DESIGN_REWORK_AUDIT.md`

Richiesta: portare le pagine interne al livello della Home, con la Home come riferimento di art
direction. Non correzioni di padding — decisioni di design. Screenshot **guardati**, non solo
misurati: è così che sono emersi i tre difetti sotto, nessuno dei quali produceva un numero
sbagliato.

### Il sistema decorativo aveva tre difetti sovrapposti

1. ⚠ **Su ogni container Elementor lo pseudo-elemento veniva STIRATO.** `.e-con::before` è già
   usato da Elementor per il proprio background-overlay con `inset:0; height:100%`, e quelle
   dichiarazioni battono `aspect-ratio` (che governa solo quando una dimensione è `auto`). Il
   motivo prendeva l'altezza dell'ancora: **497px** su Lavora con noi, **11 308px** su `/menu/`,
   contro i 237 corretti su Chi siamo — che non è un container Elementor, ed è l'unico posto dove
   sembrava funzionare. Risolto con `inset:auto; height:auto` nella regola condivisa.
2. **Le dimensioni erano in `vw`, non nello spazio disponibile.** Dopo l'unificazione della griglia
   a 1440 lo spazio fuori dalla colonna è `(100vw−1440)/2`: 240px a 1920, **zero** a 1440. Un
   motivo in vw stava nel gutter a 1920 e attraversava il testo a 1440. Ora esiste
   `--tmk-decor-room` e ogni motivo ci divide la propria frazione visibile: la decorazione occupa
   lo spazio che c'è.
3. **Scala e opacità**: larghezze ~2–3×, opacità a 1 sopra i 1600. A 1920: foglie 68→233px
   visibili, koi 45→167, serpente 158→255, ara 87→213.

**La ghirlanda è stata tolta dalle bande di chiusura chiare**: su Eventi e Shop dipingeva 1 043px e
ne entrava **1 011 oltre il bordo del testo**, passando sotto titolo e CTA.

### Difetti strutturali trovati guardando, non leggendo

- **Blocco informazioni ristorante**: `grid-template-columns` valeva `304px 304px 304px 304px` —
  `repeat(auto-fit,minmax(260px,1fr))` su 1376px con gap 32 genera **quattro** tracce. I dati
  stavano in una da 304px (orari a capo in mezzo a un intervallo), la mappa scendeva sotto a
  1301px. Ora due tracce esplicite 1 : 1.35, mappa in colonna 2: dati 535×427, mappa 723×605.
- **Card posizioni larghe 209px** su Lavora con noi: `tmklc062` era rimasto un **container Grid
  Elementor a 2 colonne** da quando conteneva le quattro card hard-coded di `_BUILD/104`;
  `_BUILD/181` ne aveva sostituito i figli ma non il layout, e l'unico figlio prendeva 676px dei
  1376.
- **Spacing hero→contenuto**: 40 · 48 · 64 · 64 · 67 · 67 · 115 su sette pagine. Ora due soli
  attacchi documentati, `--hero-to-breadcrumb` e `--hero-to-section`.

### Illustrazioni → fotografia (Chi siamo, Lavora con noi)

Koi e ara alti mezzo schermo accanto a quattro righe di testo non raccontavano niente e non
aggiungevano significato. Sostituiti da **fotografie reali già in libreria e mai usate**, scelte
guardandole: bancone illuminato d'ambra (#227) per *Il rigore*, persone che ridono con la
caipirinha (#377) per *La gioia*, sala apparecchiata (#228) per *Prima le persone*. Le
illustrazioni restano nel sistema decorativo di gutter, dove sono ambiente e non contenuto.
Chiusure di Chi siamo, Eventi, Shop e Lavora con noi: **bande scure**, come la Home.

### Home
Toccata **solo** la ghirlanda in basso a sinistra, come richiesto: la finestra visibile mostrava il
suo viticcio *terminale* fermo a mezz'aria. `rotate(-5deg)` → `rotate(16deg)`: il terminale scende
di ~186px e finisce sotto la fascia CTA scura (che ha già `z-index:2`). Resto invariato.

---

## ⚠ La griglia delle pagine interne e' cambiata — 2026-09-04 · `_BUILD/190–194`

Rapporto completo: `_BUILD/FINAL_VISUAL_DESIGN_QA_2026-09-04.md`.

**Due griglie, non piu' una.** `--container-page` (1440) resta la griglia della **cromatura**:
header e footer, gutter 32px. La **colonna di contenuto** delle pagine interne e' ora
`--container-body: 1120px` centrata — cioe' 160px di margine a 1440, 400px a 1920.

Non e' una preferenza estetica: e' la griglia della Home, misurata (logo di testata a x=32,
colonne del footer a x=32, card a x=160). Prima le pagine interne avevano 1440 con 32px di gutter,
**margine zero**, e con margine zero NON esiste una collocazione valida per un'illustrazione:
qualunque motivo ancorato al bordo del viewport arriva sotto la prima riga di testo. Provato
empiricamente — ingrandire le decorazioni senza aprire il margine sposta il difetto invece di
risolverlo (i motivi finivano sopra i titoli di sezione).

```css
--container-body:1120px;
--body-inset:max(var(--page-gutter),calc((100% - var(--container-body)) / 2));
--tmk-decor-room:max(0px,(100vw - var(--container-body)) / 2);   /* la corsia dell'arte */
```

**Trappole verificate, da non ripetere:**

- ⚠ **`max-width` + `padding-inline:var(--body-inset)` si SOMMANO.** `--body-inset` contiene un
  `100%`, e in una `padding-inline` il `100%` si risolve sulla larghezza del **contenitore**, non
  dell'elemento. Su `.tmk-menu-hub` questo portava il contenuto a x=288 contro il 160 del suo
  stesso hero. Usare l'uno **o** l'altro: `.tmk-page` usa max-width + gutter fisso, `.tmk-stack` e
  `.tmk-menu-hub` usano un cap largo (1504) + `--body-inset`.
- ⚠ **Un CONTAINER Elementor tiene le classi in `css_classes`, un WIDGET in `_css_classes`.**
  Scrivere solo la seconda su un container e' un fallimento silenzioso completo: nessun errore,
  la classe non compare mai nel markup.
- ⚠ **Le quattro illustrazioni sono OPACHE fino ai propri bordi** (verificato sul canale alpha di
  `garland-horizontal`, `leaves-banana`, `snake`, `koi`). Ogni asset e' gia' un ritaglio: un motivo
  interamente dentro il viewport mostra per forza il proprio taglio netto. Deve **sempre** sbordare
  dal bordo, oppure avere l'estremita' dissolta con una `mask-image`.
- ⚠ **Un'altezza esplicita batte `aspect-ratio` a prescindere dalla specificita'.**
  `.tmk-event-card img{height:220px}` teneva l'immagine dell'evento in evidenza a 220px nonostante
  una regola piu' specifica dichiarasse `aspect-ratio:4/3`. Serve `height:auto` esplicito.
- ⚠ **Hello Elementor sottolinea tutto**: `.comments-area a, .page-content a{text-decoration:
  underline}` a (0,1,1) batte `.tmk-card` (0,1,0), e la sottolineatura si eredita a tutti i
  discendenti dell'`<a>`. Colpisce le tre sole route rese dal template del tema (`/storie/`,
  `/la-nostra-storia/`, `/allergeni/`). Neutralizzata in `css/base/base.css` **solo** sui
  componenti: i link di prosa restano sottolineati.
- ⚠ **In headless Chromium non c'e' WebGL: la mappa Google esce come un rettangolo vuoto.** Vale
  anche per gli screenshot `fullPage` headed. Una mappa vuota in uno screenshot **non e' un
  difetto** — verificarla con uno screenshot di elemento in browser headed.

**Sotto i 1300px la corsia non esiste** (`(100vw − 1120)/2` e' troppo sottile) e i motivi di pagina
escono; resta solo `.tmk-decor--keep`, l'accento che una pagina dichiara e che vive sopra una
fotografia. E' la stessa risposta della Home, misurata: a 390px la Home tiene UNA foglia tagliata
dal bordo e nient'altro.

**L'ara (pappagallo) e' rimossa da tutto il sito** su richiesta del cliente. La regola
`.tmk-decor-r-macaw` resta definita ma **nessuna pagina la usa**: non va riapplicata senza una
decisione.

**Della Home e' stata modificata solo** la dissolvenza sull'estremita' bassa di
`.tmk-decor--bo-vine-l` (`_BUILD/194`). Stessa altezza di pagina (3648px), stessa composizione.
