<?php
/**
 * Actor CV Export - PDF Generator fuer Schauspieler
 *
 * Generiert PDF-Versionen der Schauspieler-Vitas fuer Caster.
 * Einbindung in functions.php:
 * require_once get_stylesheet_directory() . '/actor-cv-export/actor-cv-export.php';
 *
 * @package AgenturFrehse
 */

defined('ABSPATH') || exit;

// WICHTIG: mPDF wird NICHT global geladen um Autoloader-Konflikte zu vermeiden
// (z.B. mit WPIDE's Monolog). Laden erfolgt nur bei PDF-Generierung.

/**
 * Rate Limiting fuer PDF-Downloads
 * Schutz gegen DoS-Angriffe durch massenhafte PDF-Generierung
 * 
 * @return bool True wenn Request erlaubt, false wenn Limit erreicht
 */
function afv_check_pdf_rate_limit(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($ip)) {
        return false; // Keine IP = blockieren (Sicherheit)
    }
    
    // Sanitize IP fuer Transient-Key (MD5 Hash)
    $ip_hash = md5($ip);
    $transient_key = 'afv_pdf_limit_' . $ip_hash;
    
    $data = get_transient($transient_key);
    $now = time();
    
    if ($data === false) {
        // Erster Request dieser IP - Zaehler starten
        set_transient($transient_key, ['count' => 1, 'start' => $now], 60);
        return true;
    }
    
    // Limit: Max 5 PDFs pro Minute pro IP
    if ($data['count'] >= 5) {
        return false; // Limit erreicht
    }
    
    // Zaehler erhoehen, verbleibende Zeit beibehalten
    $data['count']++;
    $remaining = 60 - ($now - $data['start']);
    if ($remaining > 0) {
        set_transient($transient_key, $data, $remaining);
    }
    
    return true;
}

/**
 * Hook auf template_redirect fuer PDF-Generierung
 */
add_action('template_redirect', 'afv_generate_vita_pdf');

/**
 * Generiert PDF wenn ?vita-pdf oder ?vita-pdf=1 Parameter gesetzt ist
 */
function afv_generate_vita_pdf(): void {
    // Nur fuer Schauspieler-Posts mit actor-cv-export Parameter
    // Akzeptiert: ?actor-cv-export, ?actor-cv-export=1, ?actor-cv-export=true
    if (!is_singular('schauspieler') || !array_key_exists('actor-cv-export', $_GET)) {
        return;
    }

    // SECURITY: Rate Limiting - Max 5 PDFs pro Minute pro IP
    if (!afv_check_pdf_rate_limit()) {
        wp_die(
            'Zu viele PDF-Anfragen. Bitte warten Sie eine Minute.',
            'Rate Limit',
            ['response' => 429]
        );
    }

    // Caching verhindern
    nocache_headers();
    
    // Post-ID validieren
    $post_id = get_the_ID();
    if (!$post_id || get_post_status($post_id) !== 'publish') {
        wp_die(
            'Schauspieler nicht gefunden oder nicht veröffentlicht.',
            'PDF Fehler',
            ['response' => 404]
        );
    }
    
    // Memory und Timeout erhoehen BEVOR wir mPDF laden
    ini_set('memory_limit', '256M');
    set_time_limit(120);
    
    // ============================================================
    // LAZY LOADING: mPDF und VitaRenderer NUR hier laden
    // Verhindert Autoloader-Konflikte mit anderen Plugins (WPIDE)
    // ============================================================
    $vita_pdf_dir = dirname(__FILE__);
    
    // Autoloader laden (nur wenn noch nicht geladen)
    if (!class_exists('\Mpdf\Mpdf')) {
        $autoload_paths = [
            $vita_pdf_dir . '/vendor/autoload.php',
            $vita_pdf_dir . '/autoload.php',
        ];
        
        foreach ($autoload_paths as $autoload_path) {
            if (file_exists($autoload_path)) {
                require_once $autoload_path;
                if (class_exists('\Mpdf\Mpdf')) {
                    break;
                }
            }
        }
    }
    
    // VitaRenderer laden (nur wenn noch nicht geladen)
    if (!class_exists('AFV_Vita_Renderer')) {
        require_once $vita_pdf_dir . '/class-vita-renderer.php';
    }
    
    // Pruefen ob mPDF jetzt verfuegbar ist
    if (!class_exists('\Mpdf\Mpdf')) {
        // SECURITY: Debug-Info nur ins Error-Log, nicht an User (Information Disclosure)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $vendor_exists = file_exists($vita_pdf_dir . '/vendor/autoload.php') ? 'JA' : 'NEIN';
            $manual_exists = file_exists($vita_pdf_dir . '/autoload.php') ? 'JA' : 'NEIN';
            error_log('Actor CV Export: mPDF nicht verfuegbar. Vendor=' . $vendor_exists . ', Manual=' . $manual_exists . ', Pfad=' . $vita_pdf_dir);
        }
        
        wp_die(
            'PDF-Generierung nicht verfügbar.',
            'PDF Fehler',
            ['response' => 500]
        );
    }

    try {
        // VitaRenderer initialisieren
        $renderer = new AFV_Vita_Renderer($post_id);
        $html = $renderer->render();

        // mPDF initialisieren
        // tempDir: WordPress uploads statt /tmp (Server hat oft keine /tmp Rechte)
        $upload_dir = wp_upload_dir();
        $mpdf_tmp = $upload_dir['basedir'] . '/mpdf-tmp';
        if (!is_dir($mpdf_tmp)) {
            wp_mkdir_p($mpdf_tmp);
        }
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $mpdf_tmp,
            'default_font' => 'DejaVuSans',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);

        // SSL-Probleme bei Bildern umgehen (falls noetig)
        $mpdf->curlAllowUnsafeSslRequests = true;

        // Keep with Table - verhindert Titel-Trennung von Tabelle
        $mpdf->use_kwt = true;

        // HTML zu PDF konvertieren
        $mpdf->WriteHTML($html);

        // PDF als Download ausgeben
        $mpdf->Output($renderer->getFilename(), 'D');
        exit;

    } catch (\Mpdf\MpdfException $e) {
        wp_die(
            'PDF konnte nicht erstellt werden: ' . esc_html($e->getMessage()),
            'PDF Fehler',
            ['response' => 500]
        );
    } catch (\Throwable $e) {
        // Throwable faengt sowohl Exception als auch Error (PHP 7+)
        wp_die(
            'Ein Fehler ist aufgetreten: ' . esc_html($e->getMessage()),
            'Fehler',
            ['response' => 500]
        );
    }
}
