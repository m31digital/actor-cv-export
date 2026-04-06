# Agentur Frehse Actor CV Export

## Stack
WordPress 6+ | ACF PRO | mPDF 8.2 | Hello Elementor Child-Theme

## Scope
Child-Theme `agentur-frehse`: functions.php + actor-cv-export/ Ordner

## Architektur
- **actor-cv-export.php**: Hook auf `template_redirect`, mPDF Init
- **VitaRenderer**: ACF Daten sammeln (alle Basisdaten + Repeater)
- **vita-print.php**: HTML Template fuer PDF

## Critical Rules
1. CSS MUSS inline sein im Template (kein externes Stylesheet)
2. ACF Repeater: `have_rows()` / `the_row()` / `get_sub_field()` Pattern
3. Leere Felder MUESSEN geprueft werden (if/endif)
4. Bilder: Absolute URLs funktionieren direkt mit mPDF
5. Umlaute: UTF-8 mode + DejaVuSans Font in mPDF Config
6. KEIN Elementor/DCE im PDF-Template - nur reines HTML/CSS

## Custom Post Type
`schauspieler`

## URL Pattern
`/schauspieler/{slug}/?actor-cv-export=1` → PDF Download

## Deployment
1. `composer install` lokal
2. `actor-cv-export/` Ordner nach `wp-content/themes/agentur-frehse/` kopieren
3. In `functions.php`: `require_once get_stylesheet_directory() . '/actor-cv-export/actor-cv-export.php';`
4. Testen: `/schauspieler/adriaan-van-veen/?actor-cv-export=1`
