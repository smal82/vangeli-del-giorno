<?php
/**
 * Gestione shortcode frontend con Lazy Loading COMPLETO
 */

if (!defined('ABSPATH')) {
    exit;
}

function vangelo_cei_enqueue_frontend_scripts() {
    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }
    // Definisci ajaxurl
    wp_add_inline_script('jquery', 'var vangeloCeiAjax = {"ajaxurl": "'. admin_url('admin-ajax.php') .'"};', 'before');
}
add_action('wp_enqueue_scripts', 'vangelo_cei_enqueue_frontend_scripts');

function vangelo_cei_display_shortcode($atts) {
    vangelo_cei_enqueue_frontend_scripts();
    
    $atts = shortcode_atts([
        'mostra' => 'completo'
    ], $atts);
    
    // Controlliamo cosa c'è nel DB SENZA fare scraping
    $data_oggi = date('Y-m-d');
    $stato = vangelo_cei_check_data_completa($data_oggi);
    
    // Logica di decisione:
    // Se manca QUALCOSA (Vangelo o Santo) -> Modalità AJAX
    // Se c'è TUTTO -> Modalità Statica
    
    $serve_ajax = false;
    if (!$stato['vangelo'] || !$stato['santo']) {
        $serve_ajax = true;
    }

    $unique_id = 'liturgia-' . uniqid();
    $output = '';

    // Stili CSS
    $output .= '<style>
        .liturgia-accordion-item { margin-bottom:10px; }
        .liturgia-accordion-header {
            background:transparent; border:1px solid #ddd; padding:15px 20px;
            cursor:pointer; font-weight:bold; font-size:18px; position:relative;
            transition:all 0.3s ease; display:flex; align-items:center;
        }
        .liturgia-accordion-header:hover { background:rgba(0,0,0,0.05); }
        .liturgia-accordion-content {
            display:none; padding:20px; border:1px solid #ddd; border-top:none; background:transparent;
        }
        .accordion-icon { margin-right:8px; font-size:18px; transition:transform 0.2s ease; }
        
        .vangelo-loading-container { padding: 40px; text-align: center; background: #f9f9f9; border: 1px dashed #ddd; }
        .vangelo-loading { font-size: 16px; color: #666; font-style: italic; display: inline-block; }
        .vangelo-loading:after { content: "..."; animation: dots 1.5s steps(5, end) infinite;}
        @keyframes dots { 0%, 20% { color: rgba(0,0,0,0); text-shadow: .25em 0 0 rgba(0,0,0,0), .5em 0 0 rgba(0,0,0,0);} 40% { color: #666; text-shadow: .25em 0 0 rgba(0,0,0,0), .5em 0 0 rgba(0,0,0,0);} 60% { text-shadow: .25em 0 0 #666, .5em 0 0 rgba(0,0,0,0);} 80%, 100% { text-shadow: .25em 0 0 #666, .5em 0 0 #666;}}
        
        @media (max-width: 768px) {
            .liturgia-accordion-header { padding:12px 16px !important; font-size:16px !important; }
            .liturgia-accordion-content { padding:15px !important; }
            .liturgia-accordion-content img, .liturgia-accordion-content iframe, .liturgia-accordion-content table { max-width:100% !important; height:auto; }
            .santo-del-giorno .santo-immagine { width:100% !important; max-width:100% !important; height:auto !important; display:block; margin:15px auto !important; }
             .santo-del-giorno::before { content: ""; display: block; width: 60%; height: 4px; background: linear-gradient(to right, transparent, #ccc, transparent); margin: 1rem auto 0; border-radius: 2px; }
        }
    </style>';

    // Contenitore Principale
    $output .= '<div id="' . esc_attr($unique_id) . '" class="liturgia-completa-accordion">';

    if ($serve_ajax) {
        // --- MODALITÀ AJAX ---
        $output .= '<div class="vangelo-loading-container">';
        $output .= '<div class="vangelo-loading">Aggiornamento liturgia di oggi in corso</div>';
        $output .= '</div>';
        
        $output .= '<script>
        jQuery(document).ready(function($) {
            $.ajax({
                url: vangeloCeiAjax.ajaxurl,
                type: "POST",
                data: {
                    action: "vangelo_frontend_load",
                    data: "' . $data_oggi . '",
                    atts: ' . json_encode($atts) . ',
                    container_id: "' . $unique_id . '"
                },
                success: function(response) {
                    if(response.success) {
                        $("#' . esc_attr($unique_id) . '").html(response.data.html);
                        initAccordionClick(); 
                    } else {
                        $("#' . esc_attr($unique_id) . '").html("<div style=\'padding:20px;color:red;\'>Impossibile caricare la liturgia al momento.</div>");
                    }
                }
            });
        });
        </script>';
    } else {
        // --- MODALITÀ STATICA (Tutto già nel DB) ---
        $dati = vangelo_cei_get_liturgia($data_oggi);
        
        $ha_vangelo = ($dati && !empty($dati['riferimento']));
        $ha_santo   = ($dati && !empty($dati['santo_nome']));
        
        // 1. GESTIONE LITURGIA (Letture, Salmi, Vangelo)
        if ($atts['mostra'] !== 'solo_santo' && $ha_vangelo) {
            $liturgia_array = maybe_unserialize($dati['html_completo']);
            
            if (is_array($liturgia_array)) {
                foreach ($liturgia_array as $index => $blocco) {
                    
                    // CASO A: MOSTRA COMPLETO -> Stampa TUTTI i blocchi (non solo vangelo)
                    if ($atts['mostra'] === 'completo') {
                        // Apri di default solo il Vangelo
                        $is_open = $blocco['is_vangelo']; 
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
                    
                    // CASO B: SOLO VANGELO / TESTO / RIFERIMENTO -> Filtra solo il Vangelo
                    elseif ($blocco['is_vangelo']) {
                        if ($atts['mostra'] === 'solo_vangelo') {
                            $output .= '<div class="vangelo-completo">' . wp_kses_post($blocco['html']) . '</div>';
                        } elseif ($atts['mostra'] === 'solo_riferimento') {
                            $output .= '<p><strong>' . esc_html($dati['riferimento']) . '</strong></p>';
                        } elseif ($atts['mostra'] === 'solo_testo') {
                             $output .= '<div class="vangelo-testo">' . wp_kses_post($dati['testo']) . '</div>';
                        }
                    }
                }
            }
        }
        
        // 2. GESTIONE SANTO
        if (($atts['mostra'] === 'completo' || $atts['mostra'] === 'solo_santo') && $ha_santo) {
            $separatore = ($ha_vangelo && $atts['mostra'] !== 'solo_santo') ? ' style="margin-top:30px;padding-top:30px;border-top:2px solid #ddd;"' : '';
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

    $output .= '</div>'; // Chiude #unique_id

    // Script JS condiviso
    $output .= '<script>
    function initAccordionClick() {
        jQuery(".liturgia-accordion-header").off("click").on("click", function(e){
            e.preventDefault();
            var $header = jQuery(this);
            var target = $header.data("target");
            var $content = jQuery(target);
            var $icon = $header.find(".accordion-icon");
            var $container = $header.closest(".liturgia-completa-accordion");

            $container.find(".liturgia-accordion-header").not($header).removeClass("active").find(".accordion-icon").text("▼");
            $container.find(".liturgia-accordion-content").not($content).slideUp(300);

            $header.toggleClass("active");
            $content.stop(true,true).slideToggle(300);
            $icon.text($header.hasClass("active") ? "▲" : "▼");
        });
    }
    jQuery(document).ready(function($){ initAccordionClick(); });
    </script>';

    return $output;
}
add_shortcode('vangelo_del_giorno', 'vangelo_cei_display_shortcode');
