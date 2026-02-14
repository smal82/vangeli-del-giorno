<?php
/**
 * Gestione attivazione plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Funzione eseguita all'attivazione del plugin
 * Crea la tabella nel database con i campi per Vangelo e Santo
 */
function vangelo_cei_attivazione_plugin() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vangeli_cei';
    
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        data date NOT NULL,
        riferimento varchar(255) NOT NULL,
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
}