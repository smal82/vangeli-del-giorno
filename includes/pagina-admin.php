<?php
/**
 * Pagina di amministrazione del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Aggiungi menu admin
 */
function vangelo_cei_add_admin_menu() {
    add_menu_page(
        'Importa Vangeli CEI',
        'Vangeli CEI',
        'manage_options',
        'vangeli-cei-importa',
        'vangelo_cei_render_admin_page',
        'dashicons-book-alt',
        30
    );
}
add_action('admin_menu', 'vangelo_cei_add_admin_menu');

/**
 * HTML della pagina admin
 */
function vangelo_cei_render_admin_page() {
    ?>
    <div class="wrap">
        <h1>Gestione Vangeli del Giorno</h1>
        
        <!-- TAB NAVIGATION -->
        <h2 class="nav-tab-wrapper">
            <a href="#tab-calendario" class="nav-tab nav-tab-active" data-tab="calendario">Calendario</a>
            <a href="#tab-importazione" class="nav-tab" data-tab="importazione">Importazione</a>
        </h2>
        
        <!-- TAB CALENDARIO -->
        <div id="tab-calendario" class="tab-content active">
            <div class="card" style="max-width: 900px; margin-top: 20px;">
                <h2>Calendario Vangeli e Santi</h2>
                <p class="description">
                    Visualizza quali Vangeli e Santi sono presenti nel database.<br>
                    <span style="display: inline-block; width: 18px; height: 18px; background: #d4edda; border-radius: 3px; vertical-align: middle; border: 1px solid #c3e6cb;"></span> Vangelo e Santo presenti | 
                    <span style="display: inline-block; width: 18px; height: 18px; background: #fff3cd; border-radius: 3px; vertical-align: middle; border: 1px solid #ffeaa7;"></span> Solo Santo presente | 
                    <span style="display: inline-block; width: 18px; height: 18px; background: #5bbccd; border-radius: 3px; vertical-align: middle; border: 1px solid #3a9fb0;"></span> Solo Vangelo presente | 
                    <span style="display: inline-block; width: 18px; height: 18px; background: #f8d7da; border-radius: 3px; vertical-align: middle; border: 1px solid #f5c6cb;"></span> Nessuno presente
                </p>
                
                <div id="calendario-controlli" style="margin: 20px 0; text-align: center;">
                    <button id="btn_mese_precedente" class="button">‹ Mese Precedente</button>
                    <span id="calendario_mese_corrente" style="margin: 0 20px; font-size: 18px; font-weight: bold;"></span>
                    <button id="btn_mese_successivo" class="button">Mese Successivo ›</button>
                </div>
                
                <div id="calendario_container"></div>
                
                <div id="dettaglio_data" style="margin-top: 20px; display: none;">
                    <div class="card" style="padding: 15px; background: #f9f9f9;">
                        <h3 style="margin-top: 0;">Dettaglio Data: <span id="data_selezionata_dettaglio" data-iso=""></span></h3>
                        <div id="dettaglio_messaggio"></div>
                        <div id="dettaglio_azioni" style="margin-top: 15px;"></div> 
<div id="dettaglio_cancellazione" style="margin-top: 15px; border-top: 1px solid #ddd; padding-top: 15px;">
    <h4>Opzioni di Cancellazione</h4>
    <p class="description">
        L'operazione è irreversibile e ricaricherà la pagina.
    </p>
    <button id="btn_cancella_vangelo" class="button button-secondary" data-tipo="vangelo" disabled>Cancella Solo Vangelo</button>
    <button id="btn_cancella_santo" class="button button-secondary" data-tipo="santo" disabled>Cancella Solo Santo</button>
    <button id="btn_cancella_entrambi" class="button button-danger" data-tipo="entrambi" disabled>Cancella Vangelo e Santo</button>
    <div id="cancellazione_messaggio" style="margin-top: 10px;"></div>
</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- TAB IMPORTAZIONE -->
        <div id="tab-importazione" class="tab-content" style="display: none;">
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Configura Importazione</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="data_inizio">Data Inizio</label>
                        </th>
                        <td>
                            <input type="date" id="data_inizio" value="<?php echo date('Y-m-d'); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="anni_importazione">Anni da Importare</label>
                        </th>
                        <td>
                            <input type="number" id="anni_importazione" value="3" min="1" max="10" class="small-text">
                            <p class="description">Numero di anni di vangeli da importare (da 1 a 10)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ritardo_richieste">Ritardo tra richieste (secondi)</label>
                        </th>
                        <td>
                            <input type="number" id="ritardo_richieste" value="1" min="0.5" max="5" step="0.5" class="small-text">
                            <p class="description">Pausa tra ogni richiesta per non sovraccaricare il server</p>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button id="btn_avvia_importazione" class="button button-primary button-hero">
                        Avvia Importazione
                    </button>
                    <button id="btn_ferma_importazione" class="button button-secondary" style="display: none;">
                        Ferma Importazione
                    </button>
                </p>
            </div>
            
            <div id="importazione_stats" style="display: none; margin-top: 20px;">
                <div class="card" style="max-width: 800px;">
                    <h3>Avanzamento Importazione</h3>
                    <div style="background: #f0f0f1; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span><strong>Progresso:</strong> <span id="progresso_testo">0 / 0</span></span>
                            <span><strong>Percentuale:</strong> <span id="progresso_percentuale">0%</span></span>
                        </div>
                        <div style="background: #fff; height: 30px; border-radius: 4px; overflow: hidden; border: 1px solid #ddd;">
                            <div id="barra_progresso" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px;">
                        <div style="background: #d4edda; padding: 10px; border-radius: 4px; text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: #155724;" id="count_successo">0</div>
                            <div style="font-size: 12px; color: #155724;">Successi</div>
                        </div>
                        <div style="background: #fff3cd; padding: 10px; border-radius: 4px; text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: #856404;" id="count_skip">0</div>
                            <div style="font-size: 12px; color: #856404;">Già presenti</div>
                        </div>
                        <div style="background: #f8d7da; padding: 10px; border-radius: 4px; text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: #721c24;" id="count_errori">0</div>
                            <div style="font-size: 12px; color: #721c24;">Errori</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="log_container" style="display: none; margin-top: 20px;">
                <div class="card" style="max-width: 800px;">
                    <h3>Log Importazione</h3>
                    <div id="log_importazione" style="max-height: 400px; overflow-y: auto; background: #f9f9f9; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px;">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}