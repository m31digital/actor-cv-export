<?php
/**
 * Vita Print Template - Corporate Design Agentur FREHSE
 *
 * Variablen:
 * - $data (array): Alle ACF-Daten vom VitaRenderer
 *
 * @package AgenturFrehse
 */

defined('ABSPATH') || exit;

// Logo als Base64 einbetten
$logo_path = __DIR__ . '/../Logo-4.svg';
$logo_base64 = '';
if (file_exists($logo_path)) {
    $logo_base64 = base64_encode(file_get_contents($logo_path));
}

/**
 * Hilfsfunktion: Array zu String konvertieren
 * Behandelt auch JSON-Strings wie ["Hip Hop"]
 * 
 * @param mixed $value Wert zum Konvertieren
 * @param string $separator Trennzeichen (Standard: ', ')
 * @return string Konvertierter String
 */
if (!function_exists('afv_array_to_string')) {
    function afv_array_to_string($value, $separator = ', ') {
        // Null/Empty Check
        if ($value === null || $value === '') {
            return '';
        }
        
        // JSON-String erkennen und dekodieren (z.B. ["Hip Hop"])
        if (is_string($value) && preg_match('/^\[.*\]$/', trim($value))) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }
        
        if (is_array($value)) {
            $flat = array_map(function($item) {
                if (is_array($item)) {
                    // Rekursiv flach machen
                    return implode(' ', array_filter($item, 'is_string'));
                }
                return is_string($item) || is_numeric($item) ? (string)$item : '';
            }, $value);
            return implode($separator, array_filter($flat, function($v) {
                return $v !== '' && $v !== null;
            }));
        }
        
        return is_string($value) || is_numeric($value) ? (string)$value : '';
    }
}

/**
 * Hilfsfunktion: Bild quadratisch zuschneiden (center-center) und als Base64 zurueckgeben
 * 
 * SECURITY HARDENING:
 * - Host-Whitelist gegen SSRF
 * - Dateigroessen-Check VOR Download (Memory Bomb Schutz)
 * - Pixel-Limit NACH Laden (Memory Schutz)
 * - Fallback auf Original-URL bei Fehlern (keine leeren Bilder)
 * 
 * @param string $img_url URL des Bildes
 * @param int $size Zielgroesse in Pixel (Standard: 500 fuer iPad + Druck, unter PCRE-Limit)
 * @return string Base64-kodiertes Bild oder Original-URL bei Fehler
 */
if (!function_exists('afv_crop_square_image')) {
    function afv_crop_square_image($img_url, $size = 500) {
        if (empty($img_url)) return '';
        
        // Lokalen Dateipfad bevorzugen (schneller, kein Groessenlimit)
        $local_path = '';
        $upload_dir = wp_upload_dir();
        $upload_url = $upload_dir['baseurl'];
        $upload_base = $upload_dir['basedir'];
        
        // URL zu lokalem Pfad konvertieren (nur fuer eigene Uploads)
        if (!empty($upload_url) && strpos($img_url, $upload_url) === 0) {
            $relative = substr($img_url, strlen($upload_url));
            $candidate = $upload_base . $relative;
            if (file_exists($candidate) && is_readable($candidate)) {
                $local_path = $candidate;
            }
        }
        // Auch ohne www pruefen
        if (empty($local_path) && !empty($upload_url)) {
            $alt_url = str_replace('://www.', '://', $upload_url);
            if ($alt_url !== $upload_url && strpos($img_url, $alt_url) === 0) {
                $relative = substr($img_url, strlen($alt_url));
                $candidate = $upload_base . $relative;
                if (file_exists($candidate) && is_readable($candidate)) {
                    $local_path = $candidate;
                }
            }
        }
        
        // Bild laden: lokal (bevorzugt) oder per HTTP (Fallback)
        $image_data = false;
        
        if (!empty($local_path)) {
            $image_data = @file_get_contents($local_path);
        } else {
            // HTTP Fallback: Host-Whitelist gegen SSRF
            $allowed_hosts = ['agenturfrehse.com', 'www.agenturfrehse.com'];
            $parsed_url = parse_url($img_url);
            $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
            $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] : '';
            
            if (empty($host) || !in_array($scheme, ['http', 'https'], true) || !in_array($host, $allowed_hosts, true)) {
                return $img_url;
            }
            
            $context = stream_context_create([
                'http' => ['timeout' => 15],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);
            $image_data = @file_get_contents($img_url, false, $context);
        }
        
        if (!$image_data || strlen($image_data) < 100) {
            return $img_url;
        }
        
        $source = @imagecreatefromstring($image_data);
        unset($image_data);
        if (!$source) {
            return $img_url;
        }
        
        $orig_width = imagesx($source);
        $orig_height = imagesy($source);
        
        // Pixel-Limit (Memory Schutz) - max 6000x6000
        if ($orig_width > 6000 || $orig_height > 6000 || $orig_width < 10 || $orig_height < 10) {
            imagedestroy($source);
            return $img_url;
        }
        
        // Quadratischen Ausschnitt berechnen (top-center)
        $crop_size = min($orig_width, $orig_height);
        $src_x = ($orig_width - $crop_size) / 2;
        $src_y = 0;
        
        $dest = imagecreatetruecolor($size, $size);
        if (!$dest) {
            imagedestroy($source);
            return $img_url;
        }
        
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        
        imagecopyresampled($dest, $source, 0, 0, (int)$src_x, (int)$src_y, $size, $size, $crop_size, $crop_size);
        imagedestroy($source);
        
        ob_start();
        imagejpeg($dest, null, 80);
        $jpeg_data = ob_get_clean();
        imagedestroy($dest);
        
        if (empty($jpeg_data)) {
            return $img_url;
        }
        
        return 'data:image/jpeg;base64,' . base64_encode($jpeg_data);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?php echo esc_html($data['name']); ?> - Vita | Agentur FREHSE</title>
    <style>
        /* ============================================
           CORPORATE DESIGN - AGENTUR FREHSE
           Primaerfarbe: #a67ecc (Violett)
           Sekundaerfarbe: #434343 (Dunkelgrau)
           Schrift: DejaVuSans (mPDF kompatibel)
           ============================================ */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVuSans, Helvetica, Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.5;
            color: #333;
            background: #fff;
        }

        /* ============================================
           HEADER - Logo rechts, Name links
           ============================================ */
        .header {
            width: 100%;
            margin-bottom: 10px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-left {
            vertical-align: bottom;
            width: 55%;
        }

        .header-right {
            text-align: right;
            vertical-align: top;
        }

        .logo {
            height: 22px;
            width: auto;
        }

        .actor-name {
            font-family: DejaVuSans, Helvetica, sans-serif;
            font-size: 18pt;
            font-weight: bold;
            color: #434343;
            margin: 0;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .kategorie {
            font-family: DejaVuSans, Helvetica, sans-serif;
            font-size: 8pt;
            color: #a67ecc;
            font-weight: normal;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
            display: block;
        }

        /* ============================================
           FOTO-GRID (bis zu 12 Bilder, quadratisch)
           ============================================ */
        .photo-section {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        .photo-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .photo-grid td {
            padding: 2px;
            vertical-align: top;
            text-align: center;
        }

        .photo-container {
            text-align: center;
        }

        .photo-container img {
            width: 50mm;
            height: 50mm;
        }

        /* ============================================
           SEKTIONEN - Mehr Abstand + Keep with Next
           ============================================ */
        .section {
            margin-top: 30px;
            margin-bottom: 15px;
        }

        .section-title {
            font-family: DejaVuSans, Helvetica, sans-serif;
            font-size: 12pt;
            font-weight: bold;
            color: #434343;
            text-align: center;
            padding: 12px 0;
            margin-bottom: 8px;
            page-break-after: avoid;
        }

        /* Titel als Tabellenzeile - bleibt mit Header zusammen */
        .section-title-row {
            page-break-after: avoid !important;
            page-break-inside: avoid !important;
        }

        .section-title-cell {
            font-family: DejaVuSans, Helvetica, sans-serif;
            font-size: 12pt;
            font-weight: bold;
            color: #434343;
            text-align: center;
            padding: 30px 0 40px 0;
            background-color: transparent !important;
            border: none !important;
        }

        /* Titel-Zeile und Spacer sollen keine Hintergrundfarbe bekommen */
        .vita-table tr.section-title-row td,
        .vita-table tr.spacer-row td {
            background-color: transparent !important;
            border: none !important;
        }

        .spacer-row {
            page-break-after: avoid !important;
        }

        /* ============================================
           BASISDATEN - Einspaltung (untereinander)
           ============================================ */
        .basisdaten-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }

        .basisdaten-table tr {
            border-bottom: 1px solid #e0e0e0;
        }

        .basisdaten-table tr:nth-child(even) td {
            background-color: #f9f9f9;
        }

        .basisdaten-table tr:nth-child(odd) td {
            background-color: #fff;
        }

        .basisdaten-table td {
            padding: 6px 8px;
            vertical-align: top;
            border-bottom: 1px solid #e0e0e0;
        }

        .basisdaten-table .label {
            font-weight: 600;
            color: #434343;
            width: 35%;
        }

        .basisdaten-table .value {
            width: 65%;
        }

        /* ============================================
           VITA TABELLEN
           ============================================ */
        .vita-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            page-break-inside: auto;
            page-break-before: avoid; /* Nicht direkt nach Titel umbrechen */
        }

        /* Jahr-Spalte: NIEMALS umbrechen */
        .vita-table .col-jahr,
        .vita-table td.col-jahr,
        .vita-table th.col-jahr {
            width: 14%;
            min-width: 70px;
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            hyphens: none !important;
        }

        .vita-table th {
            background-color: #1a1a1a;
            color: #fff;
            font-weight: 600;
            text-align: left;
            padding: 8px 10px;
            font-size: 8.5pt;
        }

        /* Titel + Spacer + Header: Zusammenhalten */
        .vita-table tr:first-child,
        .vita-table tr:nth-child(2),
        .vita-table tr:nth-child(3) {
            page-break-after: avoid !important;
        }
        
        /* Erste Datenzeile: Mit Header zusammenhalten */
        .vita-table tr:nth-child(4) {
            page-break-before: avoid !important;
        }

        .vita-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: top;
            /* Verhindert Umbruch bei einzelnen Buchstaben */
            word-break: keep-all;
            overflow-wrap: break-word;
            hyphens: auto;
            -webkit-hyphens: auto;
        }

        .vita-table tr:nth-child(even) td {
            background-color: #f9f9f9;
        }

        .vita-table tr:nth-child(odd) td {
            background-color: #fff;
        }

        .vita-table td:first-child {
            white-space: nowrap;
        }

        /* ============================================
           FOOTER
           ============================================ */
        .footer {
            position: absolute;
            bottom: 10mm;
            left: 15mm;
            right: 15mm;
            text-align: center;
        }

        .footer-logo {
            height: 22px;
            margin-bottom: 8px;
        }

        .footer-text {
            font-size: 8pt;
            color: #666;
        }

        .footer-text a {
            color: #a67ecc;
            text-decoration: none;
        }

        .footer-impressum {
            font-size: 7pt;
            color: #666;
            margin-top: 8px;
            line-height: 1.6;
        }

        .footer-impressum a {
            color: #a67ecc;
            text-decoration: none;
        }

        /* ============================================
           ZEITSTEMPEL
           ============================================ */
        .download-timestamp {
            font-size: 7pt;
            color: #999;
            text-align: center;
            margin-top: 12px;
        }

        /* ============================================
           LINKS IM HEADER
           ============================================ */
        .actor-name-link {
            color: #434343;
            text-decoration: none;
        }

        .logo-link {
            text-decoration: none;
        }

        /* ============================================
           PAGE BREAK
           ============================================ */
        .page-break {
            page-break-before: always;
        }

        .no-break {
            page-break-inside: avoid;
        }

    </style>
</head>
<body>

    <!-- ============================================
         HEADER - Logo rechts, Name links
         ============================================ -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-left">
                    <a href="<?php echo esc_url($data['profile_url']); ?>" class="actor-name-link" target="_blank">
                        <div class="actor-name"><?php echo esc_html($data['name']); ?></div>
                    </a>
                    <?php if (!empty($data['kategorie'])): ?>
                        <div class="kategorie"><?php echo esc_html($data['kategorie']); ?></div>
                    <?php endif; ?>
                </td>
                <td class="header-right">
                    <?php if ($logo_base64): ?>
                        <a href="https://agenturfrehse.com" class="logo-link" target="_blank">
                            <img class="logo" src="data:image/svg+xml;base64,<?php echo esc_attr($logo_base64); ?>" alt="Agentur FREHSE">
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- ============================================
         FOTO-GRID (bis zu 12 Bilder, 3 pro Reihe)
         ============================================ -->
    <?php if (!empty($data['bilder']) && is_array($data['bilder'])): ?>
    <div class="photo-section">
        <table class="photo-grid">
            <?php 
            $bilder = array_slice($data['bilder'], 0, 12);
            $chunks = array_chunk($bilder, 3);
            foreach ($chunks as $row): 
            ?>
            <tr>
                <?php foreach ($row as $bild): ?>
                <td style="width: 33.33%;">
                    <?php 
                    $img_url = '';
                    $original_url = ''; // Original-URL fuer Hyperlink
                    if (is_array($bild) && !empty($bild['url'])) {
                        $img_url = $bild['url'];
                        $original_url = $bild['url'];
                    } elseif (is_string($bild)) {
                        $img_url = $bild;
                        $original_url = $bild;
                    } elseif (is_numeric($bild)) {
                        $img_url = wp_get_attachment_url($bild);
                        $original_url = $img_url;
                    }
                    if ($img_url): 
                        // Bild quadratisch zuschneiden (300px)
                        $cropped_img = afv_crop_square_image($img_url, 500);
                    ?>
                        <div class="photo-container">
                            <a href="<?php echo esc_url($original_url); ?>" target="_blank"><img src="<?php echo esc_attr($cropped_img); ?>" alt=""></a>
                        </div>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
                <?php 
                $empty_cells = 3 - count($row);
                for ($i = 0; $i < $empty_cells; $i++): 
                ?>
                <td style="width: 33.33%;"></td>
                <?php endfor; ?>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ============================================
         BASISDATEN - Einspaltung (untereinander)
         ============================================ -->
    <div class="section">
        <div class="section-title">Basisdaten</div>
        <table class="basisdaten-table">
            <?php if (!empty($data['nationalitaet'])): ?>
            <tr>
                <td class="label">Nationalität</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['nationalitaet'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['geburtsjahr'])): ?>
            <tr>
                <td class="label">Geburtsjahr</td>
                <td class="value"><?php echo esc_html($data['geburtsjahr']); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['geburtsort'])): ?>
            <tr>
                <td class="label">Geburtsort</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['geburtsort'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['spielalter'])): ?>
            <tr>
                <td class="label">Spielalter</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['spielalter'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['wohnsitz_1'])): ?>
            <tr>
                <td class="label">1. Wohnsitz in</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['wohnsitz_1'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['wohnort'])): ?>
            <tr>
                <td class="label">Wohnort</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['wohnort'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['wohnmoeglichkeiten'])): ?>
            <tr>
                <td class="label">Wohnmöglichkeiten</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['wohnmoeglichkeiten'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['haarfarbe'])): ?>
            <tr>
                <td class="label">Haarfarbe</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['haarfarbe'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['haarlaenge'])): ?>
            <tr>
                <td class="label">Haarlänge</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['haarlaenge'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['augenfarbe'])): ?>
            <tr>
                <td class="label">Augenfarbe</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['augenfarbe'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['statur'])): ?>
            <tr>
                <td class="label">Statur</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['statur'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['groesse'])): ?>
            <tr>
                <td class="label">Größe</td>
                <td class="value"><?php echo esc_html($data['groesse']); ?> cm</td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['gewicht'])): ?>
            <tr>
                <td class="label">Gewicht</td>
                <td class="value"><?php echo esc_html($data['gewicht']); ?> kg</td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['konfektion'])): ?>
            <tr>
                <td class="label">Konfektion</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['konfektion'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['sprachen'])): ?>
            <tr>
                <td class="label">Sprachen</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['sprachen'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['akzent'])): ?>
            <tr>
                <td class="label">Akzent</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['akzent'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['stimmlage'])): ?>
            <tr>
                <td class="label">Stimmlage</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['stimmlage'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['sport'])): ?>
            <tr>
                <td class="label">Sport</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['sport'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['tanz'])): ?>
            <tr>
                <td class="label">Tanz</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['tanz'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['fuehrerschein'])): ?>
            <tr>
                <td class="label">Führerschein</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['fuehrerschein'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['verbaende'])): ?>
            <tr>
                <td class="label">Verbände</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['verbaende'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['dialekte'])): ?>
            <tr>
                <td class="label">Dialekte</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['dialekte'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['gesang'])): ?>
            <tr>
                <td class="label">Gesang</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['gesang'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['instrument'])): ?>
            <tr>
                <td class="label">Instrument</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['instrument'])); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!empty($data['spezielle_kenntnisse'])): ?>
            <tr>
                <td class="label">Spezielle Kenntnisse</td>
                <td class="value"><?php echo esc_html(afv_array_to_string($data['spezielle_kenntnisse'])); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- ============================================
         FILM, TV & KINO
         ============================================ -->
    <?php if (!empty($data['film_tv'])): ?>
    <div class="section">
        <table class="vita-table">
            <tr class="section-title-row"><td colspan="5" class="section-title-cell">Film, TV & Kino</td></tr>
            <tr class="spacer-row"><td colspan="5" style="height: 20px; border: none; background: transparent;"></td></tr>
            <tr>
                <th class="col-jahr">Jahr</th>
                <th style="width:25%">Titel</th>
                <th style="width:20%">Rolle</th>
                <th style="width:20%">Regie</th>
                <th style="width:23%">Sender / Produktion</th>
            </tr>
            <?php foreach ($data['film_tv'] as $row): ?>
            <tr>
                <td class="col-jahr"><?php echo esc_html($row['jahr_film_tv'] ?? ''); ?></td>
                <td><?php echo esc_html($row['titel_film_tv'] ?? ''); ?></td>
                <td><?php echo esc_html($row['rolle_film_tv'] ?? ''); ?></td>
                <td><?php echo esc_html($row['regie_film_tv'] ?? ''); ?></td>
                <td><?php echo esc_html($row['sender__produktion_film_tv'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ============================================
         THEATER
         ============================================ -->
    <?php if (!empty($data['theater'])): ?>
    <div class="section">
        <table class="vita-table">
            <tr class="section-title-row"><td colspan="5" class="section-title-cell">Theater</td></tr>
            <tr class="spacer-row"><td colspan="5" style="height: 20px; border: none; background: transparent;"></td></tr>
            <tr>
                <th class="col-jahr">Jahr(e)</th>
                <th style="width:26%">Stück</th>
                <th style="width:20%">Rolle</th>
                <th style="width:20%">Regie</th>
                <th style="width:22%">Theater</th>
            </tr>
            <?php foreach ($data['theater'] as $row): ?>
            <tr>
                <td class="col-jahr"><?php echo esc_html($row['jahre_theater'] ?? ''); ?></td>
                <td><?php echo esc_html($row['stuck_theater'] ?? ''); ?></td>
                <td><?php echo esc_html($row['rolle_theater'] ?? ''); ?></td>
                <td><?php echo esc_html($row['regie_theater'] ?? ''); ?></td>
                <td><?php echo esc_html($row['theatername'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ============================================
         WERBUNG
         ============================================ -->
    <?php if (!empty($data['werbung'])): ?>
    <div class="section">
        <table class="vita-table">
            <tr class="section-title-row"><td colspan="5" class="section-title-cell">Werbung</td></tr>
            <tr class="spacer-row"><td colspan="5" style="height: 20px; border: none; background: transparent;"></td></tr>
            <tr>
                <th class="col-jahr">Jahr</th>
                <th style="width:25%">Titel</th>
                <th style="width:20%">Rolle</th>
                <th style="width:20%">Regie</th>
                <th style="width:23%">Produktion</th>
            </tr>
            <?php foreach ($data['werbung'] as $row): ?>
            <tr>
                <td class="col-jahr"><?php echo esc_html($row['jahr_werbung'] ?? ''); ?></td>
                <td><?php echo esc_html($row['titel_werbung'] ?? ''); ?></td>
                <td><?php echo esc_html($row['werbung_rolle'] ?? ''); ?></td>
                <td><?php echo esc_html($row['regie_werbung'] ?? ''); ?></td>
                <td><?php echo esc_html($row['produktion_werbung'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ============================================
         AUDIO / SPRECHER
         ============================================ -->
    <?php if (!empty($data['sprecher'])): ?>
    <div class="section">
        <table class="vita-table">
            <tr class="section-title-row"><td colspan="5" class="section-title-cell">Audio / Sprecher</td></tr>
            <tr class="spacer-row"><td colspan="5" style="height: 20px; border: none; background: transparent;"></td></tr>
            <tr>
                <th class="col-jahr">Jahr(e)</th>
                <th style="width:25%">Titel</th>
                <th style="width:18%">Rolle</th>
                <th style="width:20%">Regie</th>
                <th style="width:25%">Produktion</th>
            </tr>
            <?php foreach ($data['sprecher'] as $row): ?>
            <tr>
                <td class="col-jahr"><?php echo esc_html($row['jahre_sprecher'] ?? ''); ?></td>
                <td><?php echo esc_html($row['sendung_veranstaltung'] ?? ''); ?></td>
                <td><?php echo esc_html($row['sprecher_rolle'] ?? ''); ?></td>
                <td><?php echo esc_html($row['sprecher_regie'] ?? ''); ?></td>
                <td><?php echo esc_html($row['auftraggeber'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ============================================
         PREISE & AUSZEICHNUNGEN
         ============================================ -->
    <?php if (!empty($data['filmpreise'])): ?>
    <div class="section">
        <table class="vita-table">
            <tr class="section-title-row"><td colspan="5" class="section-title-cell">Preise & Auszeichnungen</td></tr>
            <tr class="spacer-row"><td colspan="5" style="height: 20px; border: none; background: transparent;"></td></tr>
            <tr>
                <th class="col-jahr">Jahr</th>
                <th style="width:20%">Film</th>
                <th style="width:28%">Preis</th>
                <th style="width:22%">Kategorie</th>
                <th style="width:18%">Ergebnis</th>
            </tr>
            <?php foreach ($data['filmpreise'] as $row): ?>
            <tr>
                <td class="col-jahr"><?php echo esc_html($row['jahr_filmpreise'] ?? ''); ?></td>
                <td><?php echo esc_html($row['film_filmpreis'] ?? ''); ?></td>
                <td><?php echo esc_html($row['preis_filmpreis'] ?? ''); ?></td>
                <td><?php echo esc_html($row['kategorie_filmpreis'] ?? ''); ?></td>
                <td><?php echo esc_html($row['ergebnis_filmpreis'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ============================================
         AUSBILDUNG
         ============================================ -->
    <?php if (!empty($data['ausbildung'])): ?>
    <div class="section">
        <table class="vita-table">
            <tr class="section-title-row"><td colspan="4" class="section-title-cell">Ausbildung</td></tr>
            <tr class="spacer-row"><td colspan="4" style="height: 20px; border: none; background: transparent;"></td></tr>
            <tr>
                <th class="col-jahr">Jahr(e)</th>
                <th style="width:28%">Ausbildungsfach</th>
                <th style="width:25%">Ausbildungsstätte</th>
                <th style="width:35%">Dozent(en)</th>
            </tr>
            <?php foreach ($data['ausbildung'] as $row): ?>
            <tr>
                <td class="col-jahr"><?php echo esc_html($row['jahre_ausbildung'] ?? ''); ?></td>
                <td><?php echo esc_html($row['ausbildungsfach_ausbildung'] ?? ''); ?></td>
                <td><?php echo esc_html($row['ausbildungsstatte_ausbildung'] ?? ''); ?></td>
                <td><?php echo esc_html($row['dozent_ausbildung'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- ============================================
         LINKS
         ============================================ -->
    <?php
    // Link-Labels fuer Anzeige
    $link_labels = [
        'filmmakers' => 'Filmmakers',
        'schauspielervideos' => 'Schauspielervideos',
        'castforward' => 'Castforward',
        'spotlight' => 'Spotlight',
        'crewunited' => 'CrewUnited',
        'imdb' => 'IMDb',
        'webseite' => 'Webseite',
        'wikipedia' => 'Wikipedia',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'twitter' => 'X',
    ];
    
    // Nur Links mit Inhalt sammeln
    $active_links = [];
    if (!empty($data['links']) && is_array($data['links'])) {
        foreach ($data['links'] as $key => $url) {
            if (!empty($url) && isset($link_labels[$key])) {
                $active_links[$key] = [
                    'label' => $link_labels[$key],
                    'url' => $url,
                ];
            }
        }
    }
    ?>
    <?php if (!empty($active_links)): ?>
    <?php 
    $links_array = array_values($active_links);
    $total = count($links_array);
    $half = ceil($total / 2);
    
    // Links als einfachen String bauen
    $links_line1 = [];
    $links_line2 = [];
    for ($i = 0; $i < $total; $i++) {
        $link = $links_array[$i];
        $link_html = '<a href="' . esc_url($link['url']) . '" target="_blank" style="color: #a67ecc; text-decoration: none;">' . esc_html($link['label']) . '</a>';
        if ($i < $half) {
            $links_line1[] = $link_html;
        } else {
            $links_line2[] = $link_html;
        }
    }
    ?>
    <div class="section">
    <table class="vita-table">
        <tr class="section-title-row">
            <td class="section-title-cell">Links</td>
        </tr>
        <tr class="spacer-row"><td style="height: 20px; border: none; background: transparent;"></td></tr>
        <tr>
            <td style="text-align: center; border: none; padding: 0;">
                <div style="font-size: 9pt; line-height: 2;"><?php echo implode('<span style="color: #ccc;"> | </span>', $links_line1); ?></div>
                <?php if (!empty($links_line2)): ?>
                <div style="font-size: 9pt; line-height: 2;"><?php echo implode('<span style="color: #ccc;"> | </span>', $links_line2); ?></div>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    </div>
    <?php endif; ?>

    <!-- ============================================
         FOOTER - Nur letzte Seite, am BOTTOM der Seite
         ============================================ -->
    <div class="footer">
        <?php if ($logo_base64): ?>
            <a href="https://agenturfrehse.com" class="logo-link" target="_blank">
                <img class="footer-logo" src="data:image/svg+xml;base64,<?php echo esc_attr($logo_base64); ?>" alt="Agentur FREHSE">
            </a>
        <?php endif; ?>
        <div class="footer-text">
            Schauspielagentur München
        </div>
        <div class="footer-impressum">
            Tanja Frehse · Agentur FREHSE<br>
            Annafeldstr. 4 · 86919 Utting am Ammersee<br>
            Tel: +49 173 377 27 54 · <a href="mailto:anfrage@agenturfrehse.com" target="_blank">anfrage@agenturfrehse.com</a>
        </div>
        <div class="download-timestamp">Stand: <?php echo esc_html(date('d.m.Y')); ?></div>
    </div>

</body>
</html>
