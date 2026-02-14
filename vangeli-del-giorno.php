<?php
/**
 * Plugin Name: Vangeli del Giorno CEI
 * Plugin URI: https://salvatoremaltese.it
 * Description: Importa e mostra il Vangelo del giorno dal sito della CEI con fallback automatico
 * Version: 3.0
 * Author: smalnet
 * Author URI: https://salvatoremaltese.it
 * License: GPL2
 */

// Impedisci l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Definisci costanti del plugin
define('VANGELO_CEI_VERSION', '3.0');
define('VANGELO_CEI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VANGELO_CEI_PLUGIN_URL', plugin_dir_url(__FILE__));

// Carica i file del plugin
require_once VANGELO_CEI_PLUGIN_DIR . 'includes/attivazione.php';
require_once VANGELO_CEI_PLUGIN_DIR . 'includes/scraping.php';
require_once VANGELO_CEI_PLUGIN_DIR . 'includes/database.php';
require_once VANGELO_CEI_PLUGIN_DIR . 'includes/ajax-handlers.php';
require_once VANGELO_CEI_PLUGIN_DIR . 'includes/avvisi-admin.php';
require_once VANGELO_CEI_PLUGIN_DIR . 'includes/shortcode.php';
require_once VANGELO_CEI_PLUGIN_DIR . 'includes/pagina-admin.php';

// Hook di attivazione
register_activation_hook(__FILE__, 'vangelo_cei_attivazione_plugin');

// Enqueue assets
function vangelo_cei_enqueue_admin_assets($hook) {
    if ($hook !== 'toplevel_page_vangeli-cei-importa') {
        return;
    }
    
    // CSS
    wp_enqueue_style(
        'vangelo-cei-admin-css',
        VANGELO_CEI_PLUGIN_URL . 'assets/css/admin-style.css',
        [],
        VANGELO_CEI_VERSION
    );
    
    // JavaScript
    wp_enqueue_script(
        'vangelo-cei-importazione-js',
        VANGELO_CEI_PLUGIN_URL . 'assets/js/importazione.js',
        ['jquery'],
        VANGELO_CEI_VERSION,
        true
    );
    
    wp_enqueue_script(
        'vangelo-cei-calendario-js',
        VANGELO_CEI_PLUGIN_URL . 'assets/js/calendario.js',
        ['jquery'],
        VANGELO_CEI_VERSION,
        true
    );
    
    // Localizza script
    wp_localize_script('vangelo-cei-importazione-js', 'vangeloCeiAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('vangelo_cei_nonce')
    ]);
    
    wp_localize_script('vangelo-cei-calendario-js', 'vangeloCeiCalendario', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('vangelo_cei_nonce')
    ]);
}
add_action('admin_enqueue_scripts', 'vangelo_cei_enqueue_admin_assets');

// Enqueue script per importazione rapida (su tutte le pagine admin per l'avviso)
function vangelo_cei_enqueue_importazione_rapida() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    wp_enqueue_script(
        'vangelo-cei-importazione-rapida-js',
        VANGELO_CEI_PLUGIN_URL . 'assets/js/importazione-rapida.js',
        ['jquery'],
        VANGELO_CEI_VERSION,
        true
    );
    
    wp_localize_script('vangelo-cei-importazione-rapida-js', 'vangeloCeiRapida', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('vangelo_cei_nonce')
    ]);
}
add_action('admin_enqueue_scripts', 'vangelo_cei_enqueue_importazione_rapida');