<?php
/**
 * Gestori AJAX
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Crea la tabella nel database
 */
function vangelo_cei_ajax_crea_tabella() {
    check_ajax_referer('vangelo_cei_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permessi insufficienti']);
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'vangeli_cei';
    
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        data date NOT NULL,
        riferimento varchar(100) NOT NULL,
        evangelista varchar(50) NOT NULL,
        testo text NOT NULL,
        html_completo text NOT NULL,
        santo_nome varchar(255) DEFAULT NULL,
        santo_immagine varchar(500) DEFAULT NULL,
        santo_testo text DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY data (data)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    wp_send_json_success(['message' => 'Tabella creata con successo']);
}
add_action('wp_ajax_vangelo_cei_crea_tabella', 'vangelo_cei_ajax_crea_tabella');

/**
 * AJAX: Importa vangelo e santo per una singola data
 */
function vangelo_cei_ajax_importa_singolo() {
    check_ajax_referer('vangelo_cei_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permessi insufficienti']);
    }
    
    $data = sanitize_text_field($_POST['data']);
    $data_formattata = date_i18n('j F Y', strtotime($data));
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'vangeli_cei';
    
    // Controlla se esiste già
    $exists = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE data = %s",
        $data
    ), ARRAY_A);
    
    if ($exists && !empty($exists['riferimento']) && !empty($exists['santo_nome'])) {
        wp_send_json_success([
            'message' => "Data $data_formattata già presente nel database (Vangelo e Santo)",
            'status' => 'skip'
        ]);
        return;
    }
    
    // Variabili per tracciare successi/fallimenti
    $vangelo_ok = false;
    $santo_ok = false;
    $vangelo_data = null;
    $santo_data = null;
    
    // Recupera il vangelo
    if (!$exists || empty($exists['riferimento'])) {
        $vangelo_data = vangelo_cei_scrape_liturgia($data);
        $vangelo_ok = ($vangelo_data !== false);
    } else {
        $vangelo_ok = true;
        $vangelo_data = [
            'data' => $exists['data'],
            'riferimento' => $exists['riferimento'],
            'evangelista' => $exists['evangelista'],
            'testo' => $exists['testo'],
            'html_completo' => $exists['html_completo']
        ];
    }
    
    // Recupera il santo
    if (!$exists || empty($exists['santo_nome'])) {
        $santo_data = vangelo_cei_scrape_santo($data);
        $santo_ok = ($santo_data !== false);
    } else {
        $santo_ok = true;
        $santo_data = [
            'santo_nome' => $exists['santo_nome'],
            'santo_immagine' => $exists['santo_immagine'],
            'santo_testo' => $exists['santo_testo']
        ];
    }
    
    // Se entrambi falliscono, errore completo
    if (!$vangelo_ok && !$santo_ok) {
        wp_send_json_error([
            'message' => "Errore per il $data_formattata: Vangelo e Santo non disponibili",
            'errore_tipo' => 'entrambi'
        ]);
        return;
    }
    
    // Prepara i dati da salvare con pulizia UTF-8
    $dati_completi = [];
    
    if ($vangelo_ok && $vangelo_data) {
        // Pulisci e forza UTF-8
        $dati_completi = [
            'data' => $vangelo_data['data'],
            'riferimento' => mb_convert_encoding($vangelo_data['riferimento'], 'UTF-8', 'UTF-8'),
            'evangelista' => mb_convert_encoding($vangelo_data['evangelista'], 'UTF-8', 'UTF-8'),
            'testo' => mb_convert_encoding($vangelo_data['testo'], 'UTF-8', 'UTF-8'),
            'html_completo' => mb_convert_encoding($vangelo_data['html_completo'], 'UTF-8', 'UTF-8')
        ];
    } else {
        $dati_completi = [
            'data' => $data,
            'riferimento' => '',
            'evangelista' => '',
            'testo' => '',
            'html_completo' => ''
        ];
    }
    
    if ($santo_ok && $santo_data) {
        // Pulisci e forza UTF-8
        $dati_completi['santo_nome'] = mb_convert_encoding($santo_data['santo_nome'], 'UTF-8', 'UTF-8');
        $dati_completi['santo_immagine'] = $santo_data['santo_immagine'];
        $dati_completi['santo_testo'] = mb_convert_encoding($santo_data['santo_testo'], 'UTF-8', 'UTF-8');
    } else {
        $dati_completi['santo_nome'] = '';
        $dati_completi['santo_immagine'] = '';
        $dati_completi['santo_testo'] = '';
    }
    
    // Inserisci o aggiorna nel database
    if ($exists) {
        $result = $wpdb->update(
            $table_name,
            $dati_completi,
            ['data' => $data]
        );
    } else {
        $result = $wpdb->insert($table_name, $dati_completi);
    }
    
    // Gestione errore con dettagli MySQL
    if ($result === false) {
        $mysql_error = $wpdb->last_error ? $wpdb->last_error : 'Nessun dettaglio disponibile';
        
        // Determina cosa non è stato salvato
        $errore_dettaglio = '';
        if (!$vangelo_ok && !$santo_ok) {
            $errore_dettaglio = 'Vangelo e Santo';
        } elseif (!$vangelo_ok) {
            $errore_dettaglio = 'Vangelo';
        } elseif (!$santo_ok) {
            $errore_dettaglio = 'Santo';
        }
        
        wp_send_json_error([
            'message' => "Errore nell'inserimento dei dati nel database per il $data_formattata" . ($errore_dettaglio ? " ($errore_dettaglio non disponibile)" : '') . ". MySQL: $mysql_error",
            'errore_tipo' => 'database',
            'mysql_error' => $mysql_error
        ]);
        return;
    }
    
    // Costruisci il messaggio di risposta
    $messaggio_parti = [];
    
    if ($vangelo_ok) {
        $messaggio_parti[] = "Vangelo: " . ($vangelo_data['riferimento'] ?? 'importato');
    } else {
        $messaggio_parti[] = "Vangelo: non disponibile";
    }
    
    if ($santo_ok) {
        $messaggio_parti[] = "Santo: " . ($santo_data['santo_nome'] ?? 'importato');
    } else {
        $messaggio_parti[] = "Santo: non disponibile";
    }
    
    $messaggio_finale = "$data_formattata - " . implode(" | ", $messaggio_parti);
    
    // Determina il tipo di errore parziale
    $errore_tipo = null;
    if (!$vangelo_ok) {
        $errore_tipo = 'vangelo';
    } elseif (!$santo_ok) {
        $errore_tipo = 'santo';
    }
    
    wp_send_json_success([
        'message' => $messaggio_finale,
        'status' => 'success',
        'vangelo_ok' => $vangelo_ok,
        'santo_ok' => $santo_ok,
        'errore_tipo' => $errore_tipo
    ]);
}
add_action('wp_ajax_vangelo_cei_importa_singolo', 'vangelo_cei_ajax_importa_singolo');

/**
 * AJAX: Ottieni dati calendario per un mese specifico
 */
function vangelo_cei_ajax_get_calendario() {
    check_ajax_referer('vangelo_cei_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permessi insufficienti']);
    }
    
    $anno = intval($_POST['anno']);
    $mese = intval($_POST['mese']);
    
    $dati_mese = vangelo_cei_get_vangeli_mese($anno, $mese);
    
    wp_send_json_success([
        'dati_mese' => $dati_mese
    ]);
}
add_action('wp_ajax_vangelo_cei_get_calendario', 'vangelo_cei_ajax_get_calendario');

/**
 * AJAX: Verifica stato di una singola data
 */
function vangelo_cei_ajax_check_data() {
    // ⚠️ Importante: Controlla che il file database.php sia incluso PRIMA
    // che questa funzione venga chiamata.
    
    check_ajax_referer('vangelo_cei_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permessi insufficienti']);
    }
    
    $data = sanitize_text_field($_POST['data']);
    
    $stato = vangelo_cei_check_data_completa($data); // <--- Ora questa chiamata funziona!
    
    $messaggio = '';
    
    if ($stato['vangelo'] && $stato['santo']) {
        $messaggio = '✅ <strong>Vangelo:</strong> Presente nel database<br>✅ <strong>Santo:</strong> Presente nel database';
    } elseif ($stato['vangelo'] && !$stato['santo']) {
        $messaggio = '✅ <strong>Vangelo:</strong> Presente nel database<br>⚠️ <strong>Santo:</strong> Non presente. Verrà caricato dal sito della CEI quando necessario.';
    } elseif (!$stato['vangelo'] && $stato['santo']) {
        $messaggio = '⚠️ <strong>Vangelo:</strong> Non presente. Verrà caricato dal sito della CEI quando necessario.<br>✅ <strong>Santo:</strong> Presente nel database';
    } else {
        $messaggio = '⚠️ <strong>Vangelo:</strong> Non presente nel database<br>⚠️ <strong>Santo:</strong> Non presente nel database<br><em>Entrambi verranno caricati dal sito della CEI quando necessario.</em>';
    }
    
    wp_send_json_success([
        'vangelo' => $stato['vangelo'],
        'santo' => $stato['santo'],
        'messaggio' => $messaggio
    ]);
}
add_action('wp_ajax_vangelo_cei_check_data', 'vangelo_cei_ajax_check_data');

/**
 * AJAX: Cancella Vangelo, Santo o entrambi per una singola data
 */
function vangelo_cei_ajax_cancella_liturgia() {
    check_ajax_referer('vangelo_cei_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permessi insufficienti']);
    }

    $data = sanitize_text_field($_POST['data']);
    $tipo_cancella = sanitize_text_field($_POST['tipo_cancella']); // 'vangelo', 'santo', 'entrambi'

    if (empty($data) || !in_array($tipo_cancella, ['vangelo', 'santo', 'entrambi'])) {
        wp_send_json_error(['message' => 'Dati mancanti o non validi.']);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'vangeli_cei';

    $update_data = [];
    $message = '';
    $where = ['data' => $data];

    switch ($tipo_cancella) {
        case 'vangelo':
            $update_data = [
                'riferimento' => '',
                'evangelista' => '',
                'testo' => '',
                'html_completo' => ''
            ];
            $message = "Vangelo cancellato con successo per la data $data.";
            break;
        case 'santo':
            // Impostiamo a NULL i campi VARCHAR per i santi
            $update_data = [
                'santo_nome' => NULL,
                'santo_immagine' => NULL,
                'santo_testo' => NULL
            ];
            $message = "Santo cancellato con successo per la data $data.";
            break;
        case 'entrambi':
            // Cancella sia Vangelo che Santo.
            $update_data = [
                'riferimento' => '',
                'evangelista' => '',
                'testo' => '',
                'html_completo' => '',
                'santo_nome' => NULL,
                'santo_immagine' => NULL,
                'santo_testo' => NULL
            ];
            $message = "Vangelo e Santo cancellati con successo per la data $data.";
            break;
    }

    if (!empty($update_data)) {
        // Aggiorna il record
        $wpdb->update($table_name, $update_data, $where);

        // Dopo la cancellazione, verifichiamo se il record è diventato completamente vuoto.
        $record_dopo = $wpdb->get_row($wpdb->prepare(
            "SELECT riferimento, santo_nome FROM $table_name WHERE data = %s",
            $data
        ), ARRAY_A);
        
        $ha_vangelo = ($record_dopo && !empty($record_dopo['riferimento']));
        $ha_santo = ($record_dopo && !empty($record_dopo['santo_nome']));
        
        // Se non c'è più Vangelo né Santo (entrambi vuoti/null), eliminiamo l'intera riga.
        if (!$ha_vangelo && !$ha_santo) {
             $wpdb->delete($table_name, $where);
             $message .= " Il record è stato eliminato dal database.";
        }
        
        wp_send_json_success([
            'message' => $message,
            'data' => $data
        ]);

    } else {
        wp_send_json_error(['message' => 'Nessuna operazione di cancellazione specificata.']);
    }
}
add_action('wp_ajax_vangelo_cei_cancella_liturgia', 'vangelo_cei_ajax_cancella_liturgia');

/**
 * AJAX FRONTEND: Carica l'INTERA liturgia se mancava qualcosa
 */
function vangelo_cei_ajax_frontend_load() {
    $data_richiesta = isset($_POST['data']) ? sanitize_text_field($_POST['data']) : date('Y-m-d');
    $atts = isset($_POST['atts']) ? $_POST['atts'] : []; // Attributi shortcode originali
    
    // Forza il recupero completo (scarica ciò che manca)
    $dati = vangelo_cei_get_liturgia($data_richiesta);
    
    if (!$dati) {
        wp_send_json_error(['message' => 'Impossibile recuperare la liturgia.']);
    }

    // Ricostruiamo l'HTML COMPLETO usando una funzione helper (che definiremo nello shortcode.php per non duplicare codice)
    // Ma siccome siamo in AJAX, non possiamo chiamare funzioni definite dentro shortcode.php se non è stato caricato.
    // Quindi definiremo la logica di rendering qui o in una funzione condivisa.
    
    // Per pulizia, includiamo il file shortcode.php se necessario, 
    // ma la soluzione migliore è avere la funzione di render in un file separato o renderla qui.
    // Replichiamo la logica di render qui per sicurezza ed evitare dipendenze circolari complesse ora.
    
    $output = '';
    $mostra = isset($atts['mostra']) ? $atts['mostra'] : 'completo';
    $unique_id = isset($_POST['container_id']) ? sanitize_text_field($_POST['container_id']) : 'liturgia-' . uniqid();
    
    $ha_vangelo = ($dati && !empty($dati['riferimento']));
    $ha_santo   = ($dati && !empty($dati['santo_nome']));

    // --- LOGICA DI RENDERING (Copia fedele di quella dello shortcode) ---
    
    // Selettore Stile
    if ($mostra === 'solo_riferimento') {
        if ($ha_vangelo) $output = '<p><strong>' . esc_html($dati['riferimento']) . '</strong></p>';
    } 
    elseif ($mostra === 'solo_testo') {
        if ($ha_vangelo) $output = '<div class="vangelo-testo">' . wp_kses_post($dati['testo']) . '</div>';
    }
    elseif ($mostra === 'solo_santo') {
        if ($ha_santo) {
            $output .= '<div class="santo-del-giorno">';
            $output .= '<h2 class="wp-block-heading alignfull has-text-align-center section-title visible">Santo del Giorno</h2>';
            $output .= '<h2 class="cci-liturgia-giorno-section-title" style="text-align:center;">' . esc_html($dati['santo_nome']) . '</h2>';
            if (!empty($dati['santo_immagine'])) {
                $output .= '<img src="' . esc_url($dati['santo_immagine']) . '" alt="' . esc_attr($dati['santo_nome']) . '" class="santo-immagine" style="max-width:300px;height:auto;display:block;margin:15px auto;">';
            }
            if (!empty($dati['santo_testo'])) {
                $output .= '<div class="santo-testo">' . wp_kses_post(wpautop($dati['santo_testo'])) . '</div>';
            }
            $output .= '</div>';
        }
    }
    elseif ($mostra === 'solo_vangelo') {
        if ($ha_vangelo) {
            $liturgia_array = maybe_unserialize($dati['html_completo']);
            if (is_array($liturgia_array)) {
                foreach ($liturgia_array as $blocco) {
                    if ($blocco['is_vangelo']) {
                        $output = '<div class="vangelo-completo">' . wp_kses_post($blocco['html']) . '</div>';
                        break;
                    }
                }
            }
        }
    }
    else { // 'completo' (default)
        // Vangelo
        if ($ha_vangelo) {
            $liturgia_array = maybe_unserialize($dati['html_completo']);
            if (is_array($liturgia_array)) {
                foreach ($liturgia_array as $index => $blocco) {
                    $is_open = $blocco['is_vangelo']; // Apri solo Vangelo
                    $active_class = $is_open ? ' active' : '';
                    $display_style = $is_open ? ' style="display:block;"' : '';
                    $target_id = $unique_id . '-content-' . $index;
                    $html_senza_titolo = preg_replace('/<h2 class="cci-liturgia-giorno-section-title">.*?<\/h2>/si', '', $blocco['html']);

                    $output .= '<div class="liturgia-accordion-item">';
                    $output .= '<div class="liturgia-accordion-header' . $active_class . '" data-target="#' . esc_attr($target_id) . '">';
                    $freccia = $is_open ? '▲' : '▼';
                    $output .= '<span class="accordion-icon">' . $freccia . '</span> ' . esc_html($blocco['titolo']);
                    $output .= '</div>';
                    $output .= '<div class="liturgia-accordion-content" id="' . esc_attr($target_id) . '"' . $display_style . '>';
                    $output .= wp_kses_post($html_senza_titolo);
                    $output .= '</div></div>';
                }
            }
        }

        // Santo
        if ($ha_santo) {
            $separatore = $ha_vangelo ? ' style="margin-top:30px;padding-top:30px;border-top:2px solid #ddd;"' : '';
            $output .= '<div class="santo-del-giorno"' . $separatore . '>';
            $output .= '<h2 class="wp-block-heading alignfull has-text-align-center section-title visible">Santo del Giorno</h2>';
            $output .= '<h2 class="cci-liturgia-giorno-section-title" style="text-align:center;">' . esc_html($dati['santo_nome']) . '</h2>';
            if (!empty($dati['santo_immagine'])) {
                $output .= '<img src="' . esc_url($dati['santo_immagine']) . '" alt="' . esc_attr($dati['santo_nome']) . '" class="santo-immagine" style="max-width:300px;height:auto;display:block;margin:15px auto;">';
            }
            if (!empty($dati['santo_testo'])) {
                $output .= '<div class="santo-testo">' . wp_kses_post(wpautop($dati['santo_testo'])) . '</div>';
            }
            $output .= '</div>';
        }
    }

    wp_send_json_success(['html' => $output]);
}
add_action('wp_ajax_vangelo_frontend_load', 'vangelo_cei_ajax_frontend_load');
add_action('wp_ajax_nopriv_vangelo_frontend_load', 'vangelo_cei_ajax_frontend_load');
