<?php
/**
 * Funzioni di scraping dal sito CEI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Scarica TUTTA la liturgia dal sito della CEI per una data specifica
 * 
 * @param string|null $data Data nel formato Y-m-d (o null per oggi)
 * @return array|false Array con i dati della liturgia completa o false in caso di errore
 */

function vangelo_cei_scrape_liturgia($data = null) {
    // 1. GESTIONE DATA E URL
    if ($data === null) {
        $data = date('Ymd');
    } else {
        $data = date('Ymd', strtotime($data));
    }

    $url = 'https://www.chiesacattolica.it/liturgia-del-giorno/?data-liturgia=' . $data;
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);

    // 2. PARSING HTML
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $body);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // Cerca TUTTI i blocchi della liturgia
    $nodes = $xpath->query('//div[contains(@class, "cci-liturgia-giorno-dettagli-content")]');

    // Array per i risultati finali
    $liturgia_completa = [];
    // Array per tracciare i TITOLI di sezione già aggiunti (assicura l'unicità del tipo di blocco)
    $titoli_unici = []; 

    // Variabili per l'estrazione del Vangelo
    $riferimento_vangelo = '';
    $evangelista = '';

    if ($nodes->length === 0) {
        if (function_exists('vangelo_lachiesa_scrape_liturgia')) {
            return vangelo_lachiesa_scrape_liturgia($data);
        }
        return false;
    }

    // 3. ESTRAZIONE E DEDUZIONE DEI BLOCCHI
    foreach ($nodes as $node) {
        // Estrai l'H2 (titolo della sezione)
        $h2_nodes = $xpath->query('.//h2[contains(@class, "cci-liturgia-giorno-section-title")]', $node);

        if ($h2_nodes->length > 0) {
            $titolo_sezione = trim($h2_nodes->item(0)->textContent);

            // --- NUOVA LOGICA: SALTA I BLOCCHI CON TITOLO DUPLICATO ---
            // Se il titolo è già stato aggiunto, passiamo al nodo successivo
            if (isset($titoli_unici[$titolo_sezione])) {
                continue; // Passa all'elemento successivo del foreach
            }
            // -----------------------------------------------------------

            // Estrai tutto l'HTML del blocco
            $html_blocco = $dom->saveHTML($node);

            // PULIZIA E MODIFICA DEI LINK (blocco non modificato, ma completo)
            $html_blocco = preg_replace_callback(
                '/<a\s+([^>]*?)>/i',
                function($matches) {
                    $attributes = $matches[1];
                    $attributes = preg_replace('/\s*data-target="[^"]*"/i', '', $attributes);
                    $attributes = preg_replace('/\s*data-tech-info-lt="[^"]*"/i', '', $attributes);
                    $attributes = preg_replace('/target="_self"/i', 'target="_blank"', $attributes);
                    if (!preg_match('/target=/i', $attributes)) {
                        $attributes .= ' target="_blank"';
                    }
                    $rel_attr = 'rel="noopener noreferrer nofollow"';
                    if (preg_match('/rel="([^"]*)"/i', $attributes)) {
                        $attributes = preg_replace('/rel="[^"]*"/i', $rel_attr, $attributes);
                    } else {
                        $attributes .= ' ' . $rel_attr;
                    }

                    return '<a ' . trim($attributes) . '>';
                },
                $html_blocco
            );

            // Aggiungi il blocco solo se ha titolo e contenuto valido
            if (!empty(trim($titolo_sezione)) && !empty(trim(strip_tags($html_blocco)))) {
                // Aggiungi il blocco alla collezione finale
                $liturgia_completa[] = [
                    'titolo' => $titolo_sezione,
                    'html' => $html_blocco,
                    'is_vangelo' => ($titolo_sezione === 'Vangelo')
                ];
                
                // Registra il titolo per evitare duplicati nelle iterazioni successive
                $titoli_unici[$titolo_sezione] = true;

                // 5. ESTRAZIONE DATI AGGIUNTIVI (SOLO SE È IL VANGELO)
                if ($titolo_sezione === 'Vangelo') {
                    // Estrai il riferimento dal link
                    preg_match('/<a[^>]*>([^<]+)<\/a>/i', $html_blocco, $link_match);
                    $riferimento_vangelo = isset($link_match[1]) ? trim(strip_tags($link_match[1])) : '';

                    // Se non trova il link, usa l'H3 ma tronca
                    if (empty($riferimento_vangelo)) {
                        preg_match('/<h3[^>]*>(.*?)<\/h3>/s', $html_blocco, $titolo);
                        $riferimento_vangelo = isset($titolo[1]) ? substr(strip_tags($titolo[1]), 0, 100) : '';
                    }

                    // Estrai l'evangelista
                    preg_match('/Dal Vangelo secondo ([^<]+)/i', $html_blocco, $evang_match);
                    $evangelista = isset($evang_match[1]) ? trim($evang_match[1]) : '';
                }
            }
        }
    }

    // 6. GESTIONE FALLBACK E RISULTATO FINALE
    if (empty($liturgia_completa)) {
        if (function_exists('vangelo_lachiesa_scrape_liturgia')) {
            return vangelo_lachiesa_scrape_liturgia($data);
        }
        return false;
    }

    // Estrai il testo del Vangelo (per retrocompatibilità)
    $testo_vangelo = '';
    foreach ($liturgia_completa as $blocco) {
        if ($blocco['is_vangelo']) {
            // Estrai il testo tra la frase di introduzione e la frase di conclusione
            preg_match('/Dal Vangelo secondo[^<]+<\/p>(.*?)Parola del Signore\./s', $blocco['html'], $testo);
            $testo_vangelo = isset($testo[1]) ? trim($testo[1]) : '';
            break;
        }
    }

    return [
        'data' => date('Y-m-d', strtotime($data)),
        'riferimento' => $riferimento_vangelo,
        'evangelista' => $evangelista,
        'testo' => $testo_vangelo,
        'html_completo' => serialize($liturgia_completa),
        'liturgia_array' => $liturgia_completa
    ];
}

function vangelo_lachiesa_scrape_liturgia($data) {
    if ($data === null) {
        $data = date('Ymd');
    } else {
        $data = date('Ymd', strtotime($data));
    }

    $url = 'https://www.lachiesa.it/calendario/' . $data . '.html';
    $response = wp_remote_get($url);
    if (is_wp_error($response)) return false;

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) return false;

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8">' . $body);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $titoli = $xpath->query('//div[@class="section-title"]');
    $liturgia_completa = [];
    $riferimento_vangelo = '';
    $evangelista = '';
    $testo_vangelo = '';
    $is_first_block = true;

    foreach ($titoli as $titolo_node) {

        $titolo_sezione = trim($titolo_node->textContent);

        // Salta il primo blocco
        if ($is_first_block) {
            $is_first_block = false;
            continue;
        }

        // Salta "Omelie"
        if (strcasecmp($titolo_sezione, 'Omelie') === 0) continue;

        // Contenuto immediatamente successivo
        $contenuto_node = $xpath->query('./following-sibling::div[@class="section-content"][1]', $titolo_node);
        if ($contenuto_node->length < 1) continue;

        $node = $contenuto_node->item(0);
        $html_blocco = $dom->saveHTML($node);

        if (empty(trim(strip_tags($html_blocco)))) continue;

        // 🔹 Rimuovi tutti i tag <audio> eventualmente presenti
        $html_blocco = preg_replace('/<audio[\s\S]*?<\/audio>/i', '', $html_blocco);

        // Sezione Vangelo → estrai info aggiuntive
        if ($titolo_sezione === 'Vangelo') {
            // Riferimento dal link
            preg_match('/<a[^>]*>([^<]+)<\/a>/i', $html_blocco, $ref);
            $riferimento_vangelo = isset($ref[1]) ? trim($ref[1]) : '';

            if (empty($riferimento_vangelo)) {
                preg_match('/<h3[^>]*>(.*?)<\/h3>/s', $html_blocco, $h3);
                $riferimento_vangelo = isset($h3[1]) ? trim(strip_tags($h3[1])) : '';
            }

            // Evangelista
            preg_match('/Dal Vangelo secondo ([^<]+)/i', $html_blocco, $ev);
            $evangelista = isset($ev[1]) ? trim($ev[1]) : '';

            // Testo del Vangelo
            preg_match('/Dal Vangelo secondo[\s\S]+?<\/p>(.*?)Parola del Signore/si', $html_blocco, $txt);
            $testo_vangelo = isset($txt[1]) ? trim($txt[1]) : '';
        }

        // Salva la sezione
        $liturgia_completa[] = [
            'titolo' => $titolo_sezione,
            'html' => $html_blocco,
            'is_vangelo' => ($titolo_sezione === 'Vangelo')
        ];
    }

    if (empty($liturgia_completa)) return false;

    return [
        'data' => date('Y-m-d', strtotime($data)),
        'riferimento' => $riferimento_vangelo,
        'evangelista' => $evangelista,
        'testo' => $testo_vangelo,
        'html_completo' => serialize($liturgia_completa),
        'liturgia_array' => $liturgia_completa
    ];
}

/**
 * Scarica il Santo del Giorno dal sito della CEI per una data specifica
 * 
 * @param string|null $data Data nel formato Y-m-d (o null per oggi)
 * @return array|false Array con i dati del Santo o false in caso di errore
 */
function vangelo_cei_scrape_santo($data = null) {
    // Se non viene passata una data, usa oggi
    if ($data === null) {
        $data = date('Ymd');
    } else {
        // Converti la data nel formato AAAAMMGG
        $data = date('Ymd', strtotime($data));
    }
    
    $url = 'https://www.chiesacattolica.it/santo-del-giorno/?data-liturgia=' . $data;
    $response = wp_remote_get($url);
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    
    if (empty($body)) {
        return false;
    }
    
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $body);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // Estrai il nome del santo (H1)
    $nome_nodes = $xpath->query('//h1[@class="cci_content_single_title"]');
    $santo_nome = '';
    if ($nome_nodes->length > 0) {
        $santo_nome = trim($nome_nodes->item(0)->textContent);
    }
    
    // Estrai l'immagine
    $img_nodes = $xpath->query('//div[contains(@class, "santo-del-giorno-image")]//img');
    $santo_immagine = '';
    if ($img_nodes->length > 0) {
        $santo_immagine = $img_nodes->item(0)->getAttribute('src');
        
        // Se l'URL è relativo, rendilo assoluto
        if (strpos($santo_immagine, 'http') !== 0) {
            $santo_immagine = 'https://www.chiesacattolica.it' . $santo_immagine;
        }
    }
    
    // Estrai il testo dal Martirologio
    $testo_nodes = $xpath->query('//div[@class="cci-santo-del-giorno-fonte-container"]');
    $santo_testo = '';
    if ($testo_nodes->length > 0) {
        $santo_testo = trim($testo_nodes->item(0)->textContent);
    }
    
    // Se non abbiamo trovato nulla, ritorna false
    if (empty($santo_nome) && empty($santo_immagine) && empty($santo_testo)) {
        return false;
    }
    
    return [
        'santo_nome' => $santo_nome,
        'santo_immagine' => $santo_immagine,
        'santo_testo' => $santo_testo
    ];
}