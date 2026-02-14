<?php
/**
 * Funzioni di interazione con il database
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pulisce le date passate dal database (solo una volta al giorno)
 */
function vangelo_cei_pulisci_date_passate() {
    $ultima_pulizia = get_option('vangelo_cei_ultima_pulizia', '');
    $oggi = date('Y-m-d');
    
    if ($ultima_pulizia === $oggi) {
        return; 
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'vangeli_cei';
    
    // Controlla se la tabella esiste
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return;
    }

    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE data < %s",
        $oggi
    ));
    
    update_option('vangelo_cei_ultima_pulizia', $oggi);
}

/**
 * Recupera Vangelo e Santo dal database (con fallback automatico)
 */
function vangelo_cei_get_liturgia($data = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vangeli_cei';
    
    if ($data === null) {
        $data = date('Y-m-d');
    }
    
    vangelo_cei_pulisci_date_passate();
    
    // Recupera record esistente
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE data = %s",
        $data
    ), ARRAY_A);
    
    $vangelo_presente = ($record && !empty($record['riferimento']));
    $santo_presente = ($record && !empty($record['santo_nome']));
    
    // 1. Se è tutto presente, ritorna subito
    if ($vangelo_presente && $santo_presente) {
        return $record;
    }
    
    // Inizializza variabili
    $vangelo_data = null;
    $santo_data = null;
    $aggiorna_db = false;

    // 2. GESTIONE VANGELO
    if ($vangelo_presente) {
        // Mantieni dati esistenti
        $vangelo_data = [
            'data' => $record['data'],
            'riferimento' => $record['riferimento'],
            'evangelista' => $record['evangelista'],
            'testo' => $record['testo'],
            'html_completo' => $record['html_completo']
        ];
    } else {
        // Scarica se manca
        $vangelo_data = vangelo_cei_scrape_liturgia($data);
        if ($vangelo_data !== false) {
            $aggiorna_db = true; // Segnala che abbiamo nuovi dati da salvare
        }
    }

    // 3. GESTIONE SANTO
    if ($santo_presente) {
        // Mantieni dati esistenti
        $santo_data = [
            'santo_nome' => $record['santo_nome'],
            'santo_immagine' => $record['santo_immagine'],
            'santo_testo' => $record['santo_testo']
        ];
    } else {
        // Scarica se manca
        $santo_data = vangelo_cei_scrape_santo($data);
        if ($santo_data !== false) {
            $aggiorna_db = true; // Segnala che abbiamo nuovi dati da salvare
        }
    }

    // 4. COSTRUZIONE DATI FINALI
    
    // Se non abbiamo né record vecchio né nuovi download, fallimento totale
    if ($vangelo_data === false && $santo_data === false && !$record) {
        return null;
    }

    // Costruisci array per il salvataggio unendo vecchio e nuovo
    // Usa stringhe vuote come fallback se un download è fallito
    $dati_salvataggio = [
        'data' => $data,
        'riferimento'   => ($vangelo_data && isset($vangelo_data['riferimento'])) ? $vangelo_data['riferimento'] : ($record['riferimento'] ?? ''),
        'evangelista'   => ($vangelo_data && isset($vangelo_data['evangelista'])) ? $vangelo_data['evangelista'] : ($record['evangelista'] ?? ''),
        'testo'         => ($vangelo_data && isset($vangelo_data['testo'])) ? $vangelo_data['testo'] : ($record['testo'] ?? ''),
        'html_completo' => ($vangelo_data && isset($vangelo_data['html_completo'])) ? $vangelo_data['html_completo'] : ($record['html_completo'] ?? ''),
        'santo_nome'     => ($santo_data && isset($santo_data['santo_nome'])) ? $santo_data['santo_nome'] : ($record['santo_nome'] ?? ''),
        'santo_immagine' => ($santo_data && isset($santo_data['santo_immagine'])) ? $santo_data['santo_immagine'] : ($record['santo_immagine'] ?? ''),
        'santo_testo'    => ($santo_data && isset($santo_data['santo_testo'])) ? $santo_data['santo_testo'] : ($record['santo_testo'] ?? ''),
    ];

    // 5. SALVATAGGIO NEL DB
    // Salviamo SOLO se abbiamo scaricato qualcosa di nuovo ($aggiorna_db = true)
    if ($aggiorna_db) {
        if ($record) {
            $wpdb->update($table_name, $dati_salvataggio, ['data' => $data]);
        } else {
            $wpdb->insert($table_name, $dati_salvataggio);
        }
    }

    // Ritorna i dati (anche se parziali) per mostrarli a video
    return $dati_salvataggio;
}

/**
 * Verifica se esistono Vangeli per i prossimi giorni
 */
function vangelo_cei_check_importazione_iniziale() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vangeli_cei';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return false;
    }
    
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE riferimento IS NOT NULL AND riferimento != ''");
    
    if ($count == 0) {
        return false;
    }
    
    $oggi = date('Y-m-d');
    $tra_sette_giorni = date('Y-m-d', strtotime('+7 days'));
    
    $vangeli_futuri = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE data BETWEEN %s AND %s AND riferimento IS NOT NULL AND riferimento != ''",
        $oggi,
        $tra_sette_giorni
    ));
    
    if ($vangeli_futuri < 7) {
        return false;
    }
    
    return true;
}

/**
 * Verifica cosa esiste nel database per una data specifica
 */
function vangelo_cei_check_data_completa($data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vangeli_cei';
    
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT riferimento, santo_nome FROM $table_name WHERE data = %s",
        $data
    ), ARRAY_A);
    
    return [
        'vangelo' => ($record && !empty($record['riferimento'])),
        'santo' => ($record && !empty($record['santo_nome']))
    ];
}

/**
 * Ottieni statistiche sui Vangeli presenti per un mese specifico
 */
function vangelo_cei_get_vangeli_mese($anno, $mese) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vangeli_cei';
    
    $primo_giorno = sprintf('%04d-%02d-01', $anno, $mese);
    $ultimo_giorno = date('Y-m-t', strtotime($primo_giorno));
    
    $risultati = $wpdb->get_results($wpdb->prepare(
        "SELECT data, 
                CASE WHEN riferimento IS NOT NULL AND riferimento != '' THEN 1 ELSE 0 END as ha_vangelo,
                CASE WHEN santo_nome IS NOT NULL AND santo_nome != '' THEN 1 ELSE 0 END as ha_santo
         FROM $table_name 
         WHERE data BETWEEN %s AND %s 
         ORDER BY data",
        $primo_giorno,
        $ultimo_giorno
    ), ARRAY_A);
    
    return $risultati;
}
