Formhammer — Tech Spec v0.1
Projekt: WordPress Plugin — Form-Spam-Schutz ohne Captcha, ohne externe API, ohne stored submissions.
Stack: PHP 8.1+ · WordPress Plugin API · Vanilla JS (ES6, kein Build-Step) · optional WP-CLI
Ziel: Drop-in-Schutz für CF7, Elementor Forms, WPForms, Gravity Forms, native HTML Forms — serverseitig, DSGVO-konform, zero-dependency.

Goals

Spam-Rate auf typischen WP-Formularen auf <2% senken ohne Nutzererfahrung zu verschlechtern.
Keine externe API-Abhängigkeit. Kein Google, kein hCaptcha, kein Cloudflare.
Keine Submissions in der Datenbank speichern — das Plugin ist ein Filter, kein Postfach.
Funktioniert auf shared Hosting (kein Docker, kein Node, kein Composer required).
3-Zeilen-Integration für jedes Formular.


Non-Goals (Hard Constraints)

Kein Dashboard mit stored submissions
Kein externes CDN für JS
Kein Composer / keine externen PHP-Dependencies
Kein jQuery (nur Vanilla JS)
Kein Block Editor / Gutenberg Widget
Kein Support für Multisite in v1
Keine ML-basierte Analyse
Kein ACF-Support in v1


Spam-Detection-Pipeline
Drei unabhängige Layer. Jeder Layer gibt einen Score zurück. Kombination ergibt Verdict.
Layer 1 — HMAC Timestamp Token: Direct POST protection + Freshness Check
Idee: Server generiert beim Laden des Formulars ein signiertes Token mit aktuellem Timestamp. Beim Submit wird Token serverseitig geprüft.
token = base64( json({ ts: unix_timestamp, form_id: "contact-form-1", nonce: random_bytes(8) }) )
hmac  = hash_hmac("sha256", token, FORMHAMMER_SECRET_KEY)
field = token . "." . hmac
Was es erkennt:

Direkter POST ohne Seitenaufruf (kein Token vorhanden)
Freshness Check (Token älter als max_age, default 3600s)
Token-Manipulation (HMAC-Verifikation schlägt fehl)

Wichtig: Echter Replay-Schutz braucht serverseitigen State (z. B. Transients oder DB), weil ein gültiger Token sonst bis zum Ablauf mehrfach verwendet werden kann. Das ist bewusst nicht in v1 enthalten.
Score: Token fehlt → REJECT sofort. Token manipuliert → REJECT sofort. Token abgelaufen → konfigurierbar (soft block oder flag).
Secret Key: Wird bei Plugin-Aktivierung einmalig generiert, gespeichert als WP-Option formhammer_secret_key. Kein Hardcoding.

Layer 2 — Timing Analysis
Idee: Echte Menschen brauchen Zeit zum Ausfüllen. Bots submittieren in <1s oder mit sehr präzisen Intervallen.
jswindow.formhammer_start = Date.now();
form.addEventListener('submit', () => {
  document.getElementById('hl_elapsed').value = Date.now() - window.formhammer_start;
});
Server-Logik:

elapsed < min_time (default 3000ms) → Spam-Score +40
elapsed > max_time (default 3600000ms = 1h) → ignorieren (Tab war im Hintergrund)
Elapsed nicht vorhanden → Score +20 (JS deaktiviert oder Bot)

Wichtig: Timing allein ist kein Hard-Block. Nur Score-Beitrag. Barrierefreiheit: Screen-Reader-User können langsamer sein → min_time konfiguierbar, default bewusst niedrig.

Layer 3 — Honeypot Field
Idee: Verstecktes Feld das kein Mensch ausfüllt, aber Bots oft befüllen.
html<div class="formhammer-hp" aria-hidden="true">
  <label for="hl_website">Website (leave empty)</label>
  <input type="text" name="hl_website" id="hl_website" autocomplete="off" tabindex="-1">
</div>
css.formhammer-hp { position: absolute; left: -9999px; height: 1px; overflow: hidden; }
Server-Logik:

Feld befüllt → REJECT sofort
Feld leer → Score +0

Barrierefreiheit: aria-hidden="true", tabindex="-1" auf Input und Label → Screen-Reader und Keyboard-User sehen/erreichen es nicht.

Score-Aggregation & Verdict
if honeypot_filled:   → REJECT (sofort, kein Score)
if hmac_invalid:      → REJECT (sofort, kein Score)
if total_score >= 60: → BLOCK
if total_score >= 30: → FLAG (konfigurierbar: durchlassen oder blockieren, Admin-Notiz)
if total_score < 30:  → PASS
Schwellwerte sind WP-Options, änderbar via Settings-Page oder WP-CLI.

Public API (PHP)
Direktaufruf (für eigene Forms)
php// Escaped HTML für Token, Honeypot und Timing-Feld zurückgeben
formhammer_get_fields(string $form_id): string

// Escaped HTML direkt ausgeben
formhammer_fields(string $form_id): void

// Im Submit-Handler validieren; gibt WP_Error zurück
formhammer_validate(array $data, string $form_id): WP_Error

JS-Integration
html<script src="<?php echo formhammer_script_url(); ?>"></script>
<?php formhammer_fields('my-form-id'); ?>
Das Script initialisiert sich selbst via DOMContentLoaded, findet alle Forms mit data-formhammer und setzt Timing-Listener. Ab Slice 3.5 lädt es Token per REST/AJAX nach, damit gecachtes Formular-HTML keine statischen Tokens ausliefert.

Data Model
Kein eigener DB-Table. Nur WP-Options:
OptionTypDefaultformhammer_secret_keystringrandom 32 bytes bei Aktivierungformhammer_min_timeint (ms)3000formhammer_max_ageint (s)3600formhammer_block_thresholdint60formhammer_flag_thresholdint30formhammer_log_enabledboolfalseformhammer_log_retentionint (days)7
Optionales Logging: Wenn aktiviert, schreibt Plugin in {prefix}formhammer_log nur: timestamp, form_id, verdict, score — kein POST-Body, keine User-Daten, keine IP (DSGVO).

File Structure
formhammer/
├── formhammer.php              # Plugin header, bootstrap
├── includes/
│   ├── class-validator.php     # HMAC, Timing, Score-Aggregation
│   ├── class-injector.php      # HTML-Field-Injection
│   ├── class-settings.php      # WP-Options, Settings-Page
│   ├── class-logger.php        # optionales Logging
│   └── integrations/
│       ├── cf7.php             # Contact Form 7
│       ├── elementor.php       # Elementor Forms (Pro)
│       ├── wpforms.php         # WPForms
│       └── gravity-forms.php   # Gravity Forms
├── assets/
│   └── formhammer.js           # Timing-Listener, Vanilla JS, kein Build
├── languages/
│   └── formhammer.pot
└── README.txt

Integrations
Hinweis: Hook-Signaturen für WPForms, Elementor Forms und Gravity Forms werden erst bei den jeweiligen Integrations-Slices gegen die aktuelle Hersteller-Dokumentation verifiziert und dann final implementiert.
Contact Form 7
phpadd_filter('wpcf7_validate', function($result, $tags) {
    $validation = formhammer_validate($_POST, 'cf7-' . wpcf7_get_current_contact_form()->id());
    if ($validation->has_errors()) {
        $result->invalidate($tags[0], __('Submission blocked.', 'formhammer'));
    }
    return $result;
}, 10, 2);
Elementor Forms
php// Nur laden wenn Elementor Pro aktiv
if (!class_exists('ElementorPro\Plugin')) return;

add_action('elementor_pro/forms/validation', function($record, $ajax_handler) {
    $form_id = 'elementor-' . $record->get_form_settings('id');
    $validation = formhammer_validate($_POST, $form_id);
    if ($validation->has_errors()) {
        $ajax_handler->add_error_message(__('Submission blocked.', 'formhammer'));
    }
}, 10, 2);

// Honeypot + Token in Elementor-Form injizieren
add_action('elementor_pro/forms/render_field/after', function($field, $item, $item_index, $form) {
    if ($item_index === 0) {
        formhammer_fields('elementor-' . $form->get_settings('id'));
    }
}, 10, 4);
WPForms
phpadd_action('wpforms_process_validate', function($form_data) {
    $validation = formhammer_validate($_POST, 'wpforms-' . $form_data['id']);
    if ($validation->has_errors()) {
        wpforms()->process->errors[$form_data['id']]['header'] = __('Submission blocked.', 'formhammer');
    }
});
Gravity Forms
phpadd_filter('gform_validation', function($validation_result) {
    $form_id = 'gf-' . $validation_result['form']['id'];
    $validation = formhammer_validate($_POST, $form_id);
    if ($validation->has_errors()) {
        $validation_result['is_valid'] = false;
    }
    return $validation_result;
});

Edge Cases
| Situation | Handling |
| --- | --- |
| JS deaktiviert | Timing-Field fehlt → Score +20, kein Hard-Block. HMAC und Honeypot greifen noch. |
| Screen-Reader-User sehr langsam | min_time niedrig halten (3s default), FLAG statt BLOCK bei Timing-Fail. |
| Caching-Plugin cached Formular-HTML | Ab Slice 3.5 lädt das Frontend den aktuellen HMAC-Token per REST/AJAX nach. Dadurch darf Formular-HTML gecacht werden, ohne statische Tokens auszuliefern. |
| Token-Ablauf bei langer Tab-Session | max_age default 1h. Abgelaufene Tokens → weicher Fehler mit Reload-Hinweis, kein harter Block. |
| Elementor Pro nicht aktiv | elementor.php prüft class_exists('ElementorPro\Plugin') vor dem Laden — kein Fatal Error. |
| AJAX-Forms (CF7, Elementor Standard) | JS-Timing-Listener auf submit-Event, HMAC-Token im Hidden-Field mitgesendet — funktioniert. |
| Multisite | Explicitly not supported in v1. |

Test Plan
Unit Tests (PHPUnit):

HMAC-Generierung und Verifikation korrekt
Abgelaufenes Token wird erkannt
Manipuliertes Token wird erkannt
Scoring-Logik gibt korrektes Verdict zurück
Honeypot befüllt → sofortiges REJECT
Alle vier Integration-Hooks feuern korrekt (gemockte Form-Objekte)

Integration Tests:

Native WP-Form: Token wird injiziert, Spam-Submission blockiert
CF7: Hook feuert, Submission mit Score ≥60 blockiert
Elementor: Hook feuert, ajax_handler bekommt Error
JS deaktiviert: Formular funktioniert noch (degraded, nicht broken)

Manual QA:

cURL-POST ohne Token → REJECT
cURL-POST mit gültigem Token aber elapsed=0 → Score-Erhöhung
Honeypot via DevTools befüllen → REJECT
Freshness Check: Token älter als max_age → expired_token
Replay: echter One-Time-Token-Block nur mit serverseitigem State, bewusst nicht in v1


Vertical Slices (Implementierungsreihenfolge)

Slice 1: HMAC Validator — pure PHP, kein WordPress
Slice 2: Honeypot
Slice 3: Timing JS + serverseitige Auswertung
Slice 3.5: REST Endpoint + AJAX Token Loading (löst Caching-Problem)
Slice 4: Score-Aggregation + Settings-Page
Slice 5: CF7-Integration — integrations/cf7.php
Slice 6: Elementor Forms Integration — integrations/elementor.php
Slice 7: WPForms + Gravity Forms — integrations/wpforms.php, integrations/gravity-forms.php
Slice 8: Optionales Logging — class-logger.php
