# Actor CV Export

> **Custom development for [agenturfrehse.com](https://agenturfrehse.com)**
> This plugin was built exclusively for the talent agency Agentur Frehse and is tightly coupled to their WordPress setup (custom post type `schauspieler`, ACF PRO field structure, Hello Elementor child-theme). It is not a general-purpose plugin and will not work out of the box on other sites without significant adaptation.

Generates downloadable PDF versions of actor CVs for casting directors, triggered via URL parameter on any actor profile page.

## Tech Stack

- WordPress 6+ with Hello Elementor child-theme
- ACF PRO (custom fields & repeaters)
- mPDF 8.2 (PHP PDF generation)
- Custom post type: `schauspieler`

## How It Works

A casting director visits any actor profile page and appends `?actor-cv-export=1` to the URL. The plugin intercepts the request via `template_redirect`, collects all ACF field data, renders an HTML template, and streams a PDF download.

```
https://agenturfrehse.com/schauspieler/{slug}/?actor-cv-export=1
```

## File Structure

```
actor-cv-export/
├── actor-cv-export.php       # Entry point (template_redirect hook, mPDF init)
├── class-vita-renderer.php   # Collects ACF data and renders HTML template
├── templates/
│   └── vita-print.php        # PDF HTML template (inline CSS only)
└── vendor/                   # Composer dependencies (mPDF, PSR-Log)
```

## Installation on agenturfrehse.com

### 1. Install dependencies

```bash
composer install
```

### 2. Upload to child-theme

Copy the `actor-cv-export/` folder to:

```
wp-content/themes/agentur-frehse/actor-cv-export/
```

### 3. Include in functions.php

Add to the end of the child-theme's `functions.php`:

```php
require_once get_stylesheet_directory() . '/actor-cv-export/actor-cv-export.php';
```

### 4. Test

```
https://agenturfrehse.com/schauspieler/adriaan-van-veen/?actor-cv-export=1
```

## Troubleshooting

### "PDF-Generierung nicht verfuegbar"

mPDF library not found. Check:
1. `vendor/autoload.php` exists (run `composer install`)
2. `vendor/mpdf/mpdf/src/Mpdf.php` exists

### PDF is blank

In `actor-cv-export.php`, before `$mpdf->WriteHTML($html);` add temporarily:
```php
echo $html; exit;
```
Then check if ACF fields are populated.

### Images missing

- Image URLs must be absolute and publicly accessible
- SSL bypass is already configured

### Memory error

Increase limit in `actor-cv-export.php`:
```php
ini_set('memory_limit', '512M');
```

## Development

Built by [M31 Digital GmbH](https://m31.digital) for Agentur Frehse.
