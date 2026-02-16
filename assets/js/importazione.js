/**
 * Gestione importazione Vangeli - Pagina Admin
 */

jQuery(document).ready(function($) {
    let importazioneAttiva = false;
    let totaleDaImportare = 0;
    let contatoreImportati = 0;
    let contatoreSuccessi = 0;
    let contatoreSkip = 0;
    let contatoreErrori = 0;
    
    // Gestione tab
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        const tabId = $(this).data('tab');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tab-content').removeClass('active').hide();
        $('#tab-' + tabId).addClass('active').show();
    });
    
    // Funzione per aggiungere un log
    function aggiungiLog(messaggio, tipo = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        let colore = '#000';
        let icon = 'ℹ️';
        
        switch(tipo) {
            case 'success':
                colore = '#155724';
                icon = '✅';
                break;
            case 'error':
                colore = '#721c24';
                icon = '❌';
                break;
            case 'skip':
                colore = '#856404';
                icon = '⏭️';
                break;
            case 'info':
                colore = '#004085';
                icon = 'ℹ️';
                break;
        }
        
        const logEntry = `<div style="color: ${colore}; margin-bottom: 5px;">
            <span style="color: #666;">[${timestamp}]</span> ${icon} ${messaggio}
        </div>`;
        
        $('#log_importazione').prepend(logEntry);
    }
    
    // Funzione per aggiornare le statistiche
    function aggiornaStatistiche() {
        const percentuale = Math.round((contatoreImportati / totaleDaImportare) * 100);
        
        $('#progresso_testo').text(`${contatoreImportati} / ${totaleDaImportare}`);
        $('#progresso_percentuale').text(`${percentuale}%`);
        $('#barra_progresso').css('width', percentuale + '%');
        
        $('#count_successo').text(contatoreSuccessi);
        $('#count_skip').text(contatoreSkip);
        $('#count_errori').text(contatoreErrori);
    }
    
    // Funzione per generare array di date
    function generaDateDaImportare(dataInizio, anni) {
        const date = [];
        const start = new Date(dataInizio);
        const giorni = anni * 365;
        
        for (let i = 0; i < giorni; i++) {
            const data = new Date(start);
            data.setDate(start.getDate() + i);
            const dataFormattata = data.toISOString().split('T')[0];
            date.push(dataFormattata);
        }
        
        return date;
    }
    
    // Funzione per importare un singolo vangelo
    function importaVangeloSingolo(data, ritardo) {
        return new Promise((resolve) => {
            $.ajax({
                url: vangeloCeiAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vangelo_cei_importa_singolo',
                    nonce: vangeloCeiAjax.nonce,
                    data: data
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.status === 'skip') {
                            contatoreSkip++;
                            aggiungiLog(response.data.message, 'skip');
                        } else {
                            contatoreSuccessi++;
                            aggiungiLog(response.data.message, 'success');
                        }
                    } else {
                        contatoreErrori++;
                        aggiungiLog(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    contatoreErrori++;
                    aggiungiLog(`Errore AJAX per data ${data}: ${error}`, 'error');
                },
                complete: function() {
                    contatoreImportati++;
                    aggiornaStatistiche();
                    
                    setTimeout(() => {
                        resolve();
                    }, ritardo * 1000);
                }
            });
        });
    }
    
    // Funzione principale di importazione
    async function avviaImportazione() {
        if (importazioneAttiva) return;
        
        importazioneAttiva = true;
        contatoreImportati = 0;
        contatoreSuccessi = 0;
        contatoreSkip = 0;
        contatoreErrori = 0;
        
        const dataInizio = $('#data_inizio').val();
        const anni = parseInt($('#anni_importazione').val());
        const ritardo = parseFloat($('#ritardo_richieste').val());
        
        const date = generaDateDaImportare(dataInizio, anni);
        totaleDaImportare = date.length;
        
        $('#importazione_stats').show();
        $('#log_container').show();
        $('#btn_avvia_importazione').hide();
        $('#btn_ferma_importazione').show();
        $('#log_importazione').html('');
        
        aggiungiLog(`Inizio importazione di ${totaleDaImportare} vangeli...`, 'info');
        aggiornaStatistiche();
        
        // Crea la tabella
        await new Promise((resolve) => {
            $.ajax({
                url: vangeloCeiAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vangelo_cei_crea_tabella',
                    nonce: vangeloCeiAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        aggiungiLog(response.data.message, 'success');
                    }
                    resolve();
                }
            });
        });
        
        // Importa tutti i vangeli
        for (const data of date) {
            if (!importazioneAttiva) {
                aggiungiLog('Importazione fermata dall\'utente', 'info');
                break;
            }
            
            await importaVangeloSingolo(data, ritardo);
        }
        
        importazioneAttiva = false;
        $('#btn_avvia_importazione').show();
        $('#btn_ferma_importazione').hide();
        
        aggiungiLog(`Importazione completata! Successi: ${contatoreSuccessi}, Skip: ${contatoreSkip}, Errori: ${contatoreErrori}`, 'info');
    }
    
    // Event handlers
    $('#btn_avvia_importazione').on('click', function() {
        if (confirm('Sei sicuro di voler avviare l\'importazione? Questa operazione può richiedere diversi minuti.')) {
            avviaImportazione();
        }
    });
    
    $('#btn_ferma_importazione').on('click', function() {
        if (confirm('Vuoi davvero fermare l\'importazione?')) {
            importazioneAttiva = false;
        }
    });
});