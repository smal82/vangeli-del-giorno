<?php
/**
 * Avvisi amministratore e importazione rapida
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mostra avviso per importazione Vangeli
 */
function vangelo_cei_mostra_avviso_importazione() {
    // Verifica se l'utente può gestire le opzioni
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Verifica se ci sono già vangeli importati per i prossimi giorni
    if (vangelo_cei_check_importazione_iniziale()) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'vangeli_cei';
    $count_totale = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    // Verifica quale tipo di messaggio mostrare
    $is_first_import = ($count_totale == 0);
    
    // Mostra l'avviso
    ?>
    <div class="notice notice-warning is-dismissible vangelo-cei-avviso" style="padding: 15px;">
        <?php if ($is_first_import): ?>
            <h2 style="margin-top: 0;">⚠️ Vangeli del Giorno - Importazione Richiesta</h2>
            <p style="font-size: 14px;">
                <strong>Il plugin è attivo ma il database è vuoto.</strong><br>
                Per utilizzare il plugin è necessario importare i Vangeli dal sito della CEI.
            </p>
        <?php else: ?>
            <h2 style="margin-top: 0;">⚠️ Vangeli del Giorno - Serve Aggiornare</h2>
            <p style="font-size: 14px;">
                <strong>I Vangeli nel database stanno per finire!</strong><br>
                Non ci sono abbastanza Vangeli per i prossimi giorni. È consigliato importare nuove date.
            </p>
        <?php endif; ?>
        <p style="font-size: 14px;">
            Puoi scegliere tra:
        </p>
        <ul style="font-size: 14px; margin-left: 20px;">
            <li><strong>Importazione manuale:</strong> Vai alla pagina di importazione e configura il periodo desiderato</li>
            <li><strong>Importazione rapida:</strong> Clicca il pulsante qui sotto per importare automaticamente 3 mesi di Vangeli</li>
        </ul>
        <p>
            <a href="<?php echo admin_url('admin.php?page=vangeli-cei-importa'); ?>" class="button button-primary">
                📋 Vai alla Pagina di Importazione
            </a>
            <button id="btn_importazione_rapida" class="button button-secondary" style="margin-left: 10px;">
                ⚡ Importazione Rapida (3 mesi)
            </button>
        </p>
        <div id="importazione_rapida_status" style="margin-top: 15px; display: none;">
            <div style="background: #fff; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                <p><strong>Importazione in corso...</strong></p>
                <div style="background: #f0f0f1; height: 25px; border-radius: 4px; overflow: hidden;">
                    <div id="barra_rapida" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                </div>
                <p id="status_rapida" style="margin: 10px 0 0 0; font-size: 13px;">Inizializzazione...</p>
            </div>
        </div>
    </div>
    <?php
}
add_action('admin_notices', 'vangelo_cei_mostra_avviso_importazione');