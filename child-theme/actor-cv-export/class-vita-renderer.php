<?php
/**
 * Actor CV Renderer - Sammelt ACF Daten und rendert HTML Template
 *
 * @package AgenturFrehse
 */

defined('ABSPATH') || exit;

/**
 * Class AFV_Vita_Renderer
 */
class AFV_Vita_Renderer {

    /**
     * Post ID des Schauspielers
     *
     * @var int
     */
    private int $post_id;

    /**
     * Gesammelte Daten fuer das Template
     *
     * @var array
     */
    private array $data = [];

    /**
     * Constructor
     *
     * @param int $post_id Post ID des Schauspielers
     * @throws Exception wenn ACF nicht aktiv oder Post ungueltig
     */
    public function __construct(int $post_id) {
        // Post-ID Validierung
        if ($post_id <= 0) {
            throw new Exception('Ungültige Post-ID.');
        }
        
        // ACF Verfuegbarkeit pruefen
        if (!function_exists('get_field')) {
            throw new Exception('ACF Plugin ist nicht aktiviert.');
        }
        
        // Post-Typ pruefen
        if (get_post_type($post_id) !== 'schauspieler') {
            throw new Exception('Post ist kein Schauspieler.');
        }
        
        $this->post_id = $post_id;
        $this->load_acf_data();
    }

    /**
     * Laedt alle ACF Daten
     */
    private function load_acf_data(): void {
        // Name (Post Title)
        $this->data['name'] = get_the_title($this->post_id);

        // Profil-URL fuer Verlinkung
        $this->data['profile_url'] = get_permalink($this->post_id);

        // Kategorie (Taxonomy) - ALLE Terms, sortiert
        $terms = get_the_terms($this->post_id, 'filter');
        $this->data['kategorie'] = '';
        if ($terms && !is_wp_error($terms)) {
            // Prioritaetsliste: Hauptkategorien zuerst
            $priority_order = [
                'Schauspieler', 'Schauspielerin', 
                'Nachwuchsschauspieler', 'Nachwuchsschauspielerin',
                'Junges Talent'
            ];
            
            $term_names = array_map(function($term) { return $term->name; }, $terms);
            
            // Sortieren: Prioritaetskategorien zuerst, dann alphabetisch
            usort($term_names, function($a, $b) use ($priority_order) {
                $pos_a = array_search($a, $priority_order);
                $pos_b = array_search($b, $priority_order);
                
                // Wenn beide in der Prioritaetsliste sind
                if ($pos_a !== false && $pos_b !== false) {
                    return $pos_a - $pos_b;
                }
                // Wenn nur a in der Liste ist, kommt a zuerst
                if ($pos_a !== false) return -1;
                // Wenn nur b in der Liste ist, kommt b zuerst
                if ($pos_b !== false) return 1;
                // Sonst alphabetisch
                return strcmp($a, $b);
            });
            
            $this->data['kategorie'] = implode(', ', $term_names);
        }

        // Bilder (Gallery)
        $this->data['bilder'] = get_field('schauspielbilder', $this->post_id) ?: [];

        // Basisdaten
        $this->data['nationalitaet'] = get_field('staatsangehorigkeit_basisdaten', $this->post_id);
        $this->data['geburtsjahr'] = get_field('jahrgang_basisdaten', $this->post_id);
        $this->data['spielalter'] = get_field('spielalter_basisdaten', $this->post_id);
        $this->data['wohnsitz_1'] = get_field('basisdaten_1_wohnsitz_in', $this->post_id);
        $this->data['wohnort'] = get_field('wohnsitz_basisdaten', $this->post_id);
        $this->data['wohnmoeglichkeiten'] = get_field('wohnmoglichkeiten_basisdaten', $this->post_id);
        $this->data['haarfarbe'] = get_field('haarfarbe_basisdaten', $this->post_id);
        $this->data['haarlaenge'] = get_field('basisdaten_haarlaenge', $this->post_id);
        $this->data['augenfarbe'] = get_field('augenfarbe_basisdaten', $this->post_id);
        $this->data['statur'] = get_field('statur_basisdaten', $this->post_id);
        $this->data['groesse'] = get_field('grose_cm_basisdaten', $this->post_id);
        $this->data['gewicht'] = get_field('basisdaten_gewicht', $this->post_id);
        $this->data['konfektion'] = get_field('basisdaten_konfektion', $this->post_id);
        $this->data['sprachen'] = get_field('sprachen_basisdaten', $this->post_id);
        $this->data['akzent'] = get_field('akzent_basisdaten', $this->post_id);
        $this->data['stimmlage'] = get_field('stimmlage_basisdaten', $this->post_id);
        $this->data['sport'] = get_field('sport_basisdaten', $this->post_id);
        $this->data['tanz'] = get_field('tanz_basisdaten', $this->post_id);
        $this->data['fuehrerschein'] = get_field('fuhrerschein_basisdaten', $this->post_id);
        $this->data['verbaende'] = get_field('basisdate_verbaende', $this->post_id);
        
        // Zusaetzliche Basisdaten
        $this->data['geburtsort'] = get_field('basisdaten_geburtsort', $this->post_id);
        $this->data['dialekte'] = get_field('dialekte_basisdaten', $this->post_id);
        $this->data['gesang'] = get_field('gesang_basisdaten', $this->post_id);
        $this->data['instrument'] = get_field('instrument_basisdaten', $this->post_id);
        $this->data['spezielle_kenntnisse'] = get_field('basisdate_weitere_faehigkeiten', $this->post_id);

        // Vita-Tabellen (Repeater)
        $this->data['film_tv'] = $this->get_repeater_data('film_tv', [
            'jahr_film_tv',
            'titel_film_tv',
            'rolle_film_tv',
            'regie_film_tv',
            'sender__produktion_film_tv',
        ]);

        $this->data['theater'] = $this->get_repeater_data('theater', [
            'jahre_theater',
            'stuck_theater',
            'rolle_theater',
            'regie_theater',
            'theatername',
        ]);

        $this->data['werbung'] = $this->get_repeater_data('werbung', [
            'jahr_werbung',
            'titel_werbung',
            'werbung_rolle',
            'regie_werbung',
            'produktion_werbung',
        ]);

        $this->data['sprecher'] = $this->get_repeater_data('sprecher_vita', [
            'jahre_sprecher',
            'sendung_veranstaltung',
            'sprecher_rolle',
            'sprecher_regie',
            'auftraggeber',
        ]);

        $this->data['filmpreise'] = $this->get_repeater_data('filmpreise', [
            'jahr_filmpreise',
            'film_filmpreis',
            'preis_filmpreis',
            'kategorie_filmpreis',
            'ergebnis_filmpreis',
        ]);

        $this->data['ausbildung'] = $this->get_repeater_data('filmpreise_kopieren', [
            'jahre_ausbildung',
            'ausbildungsfach_ausbildung',
            'ausbildungsstatte_ausbildung',
            'dozent_ausbildung',
        ]);

        // Links (alle 11 Felder)
        $this->data['links'] = [
            'filmmakers' => get_field('filmmakers', $this->post_id),
            'schauspielervideos' => get_field('filmmakers_kopieren', $this->post_id),
            'castforward' => get_field('castforward', $this->post_id),
            'spotlight' => get_field('spotlight', $this->post_id),
            'crewunited' => get_field('crewunited', $this->post_id),
            'imdb' => get_field('imdb', $this->post_id),
            'webseite' => get_field('links_webseite', $this->post_id),
            'wikipedia' => get_field('links_wikipedia', $this->post_id),
            'facebook' => get_field('links_facebook', $this->post_id),
            'instagram' => get_field('links_instagram', $this->post_id),
            'twitter' => get_field('links_x_twitter', $this->post_id),
        ];
    }

    /**
     * Holt Daten aus einem Repeater-Feld
     *
     * @param string $repeater_name Name des Repeater-Feldes
     * @param array  $sub_fields    Liste der Sub-Field Namen
     * @return array
     */
    private function get_repeater_data(string $repeater_name, array $sub_fields): array {
        $rows = [];

        if (have_rows($repeater_name, $this->post_id)) {
            while (have_rows($repeater_name, $this->post_id)) {
                the_row();
                $row = [];
                foreach ($sub_fields as $field) {
                    $row[$field] = get_sub_field($field);
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Rendert das HTML Template
     *
     * @return string HTML Content
     * @throws Exception wenn Template nicht gefunden
     */
    public function render(): string {
        $template_path = __DIR__ . '/templates/vita-print.php';
        
        // Template-Existenz pruefen
        if (!file_exists($template_path)) {
            throw new Exception('PDF Template nicht gefunden: ' . basename($template_path));
        }
        
        $data = $this->data;
        ob_start();
        include $template_path;
        $output = ob_get_clean();
        
        // Leeres Output abfangen
        if (empty($output)) {
            throw new Exception('PDF Template hat keinen Inhalt generiert.');
        }
        
        return $output;
    }

    /**
     * Generiert den Dateinamen fuer das PDF
     *
     * @return string Dateiname
     */
    public function getFilename(): string {
        $name = sanitize_file_name($this->data['name']);
        $name = str_replace(' ', '_', $name);
        return 'Vita_' . $name . '.pdf';
    }

}
