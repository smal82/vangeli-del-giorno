/**
 * Gestione calendario Vangeli e Santi
 */

jQuery(document).ready(function($) {
    let annoCorrente = new Date().getFullYear();
    let meseCorrente = new Date().getMonth() + 1;
    let datiMese = [];
    
    const nomiMesi = [
        'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
        'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'
    ];
    
    const nomiGiorni = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
    
    // Funzione per formattare la data in italiano
    function formattaDataItaliana(dataISO) {
        const parti = dataISO.split('-');
        const anno = parti[0];
        const mese = parseInt(parti[1]) - 1;
        const giorno = parseInt(parti[2]);
        
        return giorno + ' ' + nomiMesi[mese] + ' ' + anno;
    }
    
    // Carica il calendario subito all'apertura
    caricaCalendario();
    
    // Navigazione mesi
    $('#btn_mese_precedente').on('click', function() {
        meseCorrente--;
        if (meseCorrente < 1) {
            meseCorrente = 12;
            annoCorrente--;
        }
        caricaCalendario();
    });
    
    $('#btn_mese_successivo').on('click', function() {
        meseCorrente++;
        if (meseCorrente > 12) {
            meseCorrente = 1;
            annoCorrente++;
        }
        caricaCalendario();
    });
    
    // Carica i dati del calendario
    function caricaCalendario() {
        $('#calendario_mese_corrente').text(nomiMesi[meseCorrente - 1] + ' ' + annoCorrente);
        $('#calendario_container').html('<p>Caricamento...</p>');
        $('#dettaglio_data').hide();
        
        $.ajax({
            url: vangeloCeiCalendario.ajax_url,
            type: 'POST',
            data: {
                action: 'vangelo_cei_get_calendario',
                nonce: vangeloCeiCalendario.nonce,
                anno: annoCorrente,
                mese: meseCorrente
            },
            success: function(response) {
                if (response.success) {
                    datiMese = response.data.dati_mese;
                    renderizzaCalendario();
                }
            },
            error: function() {
                $('#calendario_container').html('<p style="color: red;">Errore nel caricamento del calendario.</p>');
            }
        });
    }
    
    // Renderizza il calendario
    function renderizzaCalendario() {
        const primoGiorno = new Date(annoCorrente, meseCorrente - 1, 1);
        const ultimoGiorno = new Date(annoCorrente, meseCorrente, 0);
        const numeroGiorni = ultimoGiorno.getDate();
        
        let primoGiornoSettimana = primoGiorno.getDay();
        primoGiornoSettimana = primoGiornoSettimana === 0 ? 7 : primoGiornoSettimana;
        
        let html = '<table class="calendario-vangeli">';
        
        // Header giorni della settimana
        html += '<thead><tr>';
        nomiGiorni.forEach(giorno => {
            html += '<th>' + giorno + '</th>';
        });
        html += '</tr></thead>';
        
        // Corpo calendario
        html += '<tbody><tr>';
        
        // Celle vuote prima del primo giorno
        for (let i = 1; i < primoGiornoSettimana; i++) {
            html += '<td class="calendario-vuoto"></td>';
        }
        
        // Giorni del mese
        for (let giorno = 1; giorno <= numeroGiorni; giorno++) {
            const dataCompleta = annoCorrente + '-' + String(meseCorrente).padStart(2, '0') + '-' + String(giorno).padStart(2, '0');
            const dataOggi = new Date().toISOString().split('T')[0];
            const passato = dataCompleta < dataOggi;
            
            // Trova i dati per questa data
            const datiGiorno = datiMese.find(d => d.data === dataCompleta);
            const haVangelo = datiGiorno ? parseInt(datiGiorno.ha_vangelo) === 1 : false;
            const haSanto = datiGiorno ? parseInt(datiGiorno.ha_santo) === 1 : false;
            
            let classe = '';
            if (passato) {
                classe = 'passato';
            } else if (haVangelo && haSanto) {
                classe = 'completo';
            } else if (!haVangelo && haSanto) {
                classe = 'solo-santo';
            } else if (haVangelo && !haSanto) {
                classe = 'solo-vangelo';
            } else {
                classe = 'assente';
            }
            
            const oggi = dataCompleta === dataOggi;
            const classeOggi = oggi ? ' oggi' : '';
            
            html += '<td class="calendario-giorno ' + classe + classeOggi + '" data-data="' + dataCompleta + '">';
            html += '<span class="giorno-numero">' + giorno + '</span>';
            html += '</td>';
            
            // Nuova riga dopo domenica
            if ((primoGiornoSettimana + giorno - 1) % 7 === 0) {
                html += '</tr><tr>';
            }
        }
        
        // Celle vuote dopo l'ultimo giorno
        const ultimoGiornoSettimana = ultimoGiorno.getDay();
        const celleFinali = ultimoGiornoSettimana === 0 ? 0 : 7 - (ultimoGiornoSettimana === 0 ? 7 : ultimoGiornoSettimana);
        for (let i = 0; i < celleFinali; i++) {
            html += '<td class="calendario-vuoto"></td>';
        }
        
        html += '</tr></tbody></table>';
        
        $('#calendario_container').html(html);
        
        // Aggiungi event listener per i click
        $('.calendario-giorno').on('click', function() {
            if ($(this).hasClass('passato')) return;
            
            // Rimuovi selezione precedente
            $('.calendario-giorno').removeClass('selezionato');
            // Rimuovi anche la classe oggi
            $('.calendario-giorno').removeClass('oggi');
            
            // Aggiungi classe alla data cliccata
            $(this).addClass('selezionato');
            
            const data = $(this).data('data');
            mostraDettaglioData(data);
        });
    }
    
    // Mostra dettaglio di una data specifica
    // Funzione per mostrare il dettaglio di una data
function mostraDettaglioData(data) {
    // Rimuovi la classe 'selezionato' da tutti i giorni e aggiungila al giorno corrente
    $('.calendario-giorno').removeClass('selezionato');
    const giornoElemento = $('.calendario-giorno[data-data="' + data + '"]');
    if (giornoElemento.length) {
        giornoElemento.addClass('selezionato');
    }

    // Imposta la data nel titolo e mostra il contenitore
    $('#data_selezionata_dettaglio').text(formattaDataItaliana(data));
    $('#dettaglio_data').show();
    $('#dettaglio_messaggio').html('⏳ Caricamento stato data...');
    $('#dettaglio_azioni').html('');
    $('#cancellazione_messaggio').html(''); // Pulisci anche i messaggi di cancellazione

    // SALVA LA DATA ISO PER LE CANCELLAZIONI
    $('#data_selezionata_dettaglio').data('iso', data);

    // Esegui la chiamata AJAX per recuperare lo stato della data
    $.ajax({
        url: vangeloCeiCalendario.ajax_url,
        type: 'POST',
        data: {
            action: 'vangelo_cei_check_data',
            nonce: vangeloCeiCalendario.nonce,
            data: data
        },
        success: function(response) {
            if (response.success) {
                const haVangelo = response.data.vangelo; // bool
                const haSanto = response.data.santo;      // bool

                // ⚠️ CORREZIONE: usiamo 'messaggio' che è la chiave corretta nel PHP
                $('#dettaglio_messaggio').html(response.data.messaggio); 

                // LOGICA ABILITAZIONE/DISABILITAZIONE BOTTONI CANCELLAZIONE
                // Questi bottoni devono esistere nel tuo HTML (pagina-admin.php)
                $('#btn_cancella_vangelo').prop('disabled', !haVangelo);
                $('#btn_cancella_santo').prop('disabled', !haSanto);
                $('#btn_cancella_entrambi').prop('disabled', !(haVangelo || haSanto)); 

                // Logica per il pulsante Importa
                if (!haVangelo || !haSanto) {
                    // Se almeno uno è mancante, mostriamo il pulsante di importazione singola
                    $('#dettaglio_azioni').html('<button id="btn_importa_data_singola" class="button button-primary">Importa Vangelo e Santo per questa data</button>');

                    $('#btn_importa_data_singola').on('click', function() {
                        importaDataSingola(data);
                    });
                } else {
                    // Se entrambi sono presenti, l'area azioni rimane vuota (o puoi aggiungere un messaggio)
                    $('#dettaglio_azioni').html('');
                }
            } else {
                // Se response.success è false (ma la comunicazione è avvenuta)
                $('#dettaglio_messaggio').html('<p style="color: #721c24;">❌ Errore nella logica PHP (response.success = false).</p>');
                $('#btn_cancella_vangelo, #btn_cancella_santo, #btn_cancella_entrambi').prop('disabled', true);
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            // ⚠️ CATTURA L'ERRORE FATALE
            // Se la comunicazione fallisce, il problema è il PHP sul server
            $('#dettaglio_messaggio').html(
                '<p style="color: #721c24;">❌ **ERRORE DI COMUNICAZIONE FATALE**.</p>' +
                '<p style="font-size: 12px; margin-top: 5px;">' + 
                'Stato: ' + textStatus + ' (' + jqXHR.status + ' ' + errorThrown + '). ' +
                'Questo indica un errore interno al server (PHP Fatal Error) che impedisce la risposta.' +
                '</p>'
            );
            $('#dettaglio_azioni').html('');
            $('#btn_cancella_vangelo, #btn_cancella_santo, #btn_cancella_entrambi').prop('disabled', true);
        }
    });
}
    /*function mostraDettaglioData(data) {
        const dataFormattata = formattaDataItaliana(data);
        $('#dettaglio_data_selezionata').text(dataFormattata);
        $('#dettaglio_data').show();
        $('#dettaglio_messaggio').html('<p>Verifica in corso...</p>');
        $('#dettaglio_azioni').html('');
        
        $.ajax({
            url: vangeloCeiCalendario.ajax_url,
            type: 'POST',
            data: {
                action: 'vangelo_cei_check_data',
                nonce: vangeloCeiCalendario.nonce,
                data: data
            },
            success: function(response) {
                if (response.success) {
                    $('#dettaglio_messaggio').html('<p style="font-size: 14px; line-height: 1.6;">' + response.data.messaggio + '</p>');
                    
                    if (!response.data.vangelo || !response.data.santo) {
                        $('#dettaglio_azioni').html('<button class="button button-primary" id="btn_importa_data_singola">📥 Importa Vangelo e Santo per questa data</button>');
                        
                        $('#btn_importa_data_singola').on('click', function() {
                            importaDataSingola(data);
                        });
                    }
                }
            }
        });
    }*/
    
    // Importa una singola data
    function importaDataSingola(data) {
        $('#dettaglio_messaggio').html('<p>⏳ Importazione in corso...</p>');
        $('#dettaglio_azioni').html('');
        
        $.ajax({
            url: vangeloCeiCalendario.ajax_url,
            type: 'POST',
            data: {
                action: 'vangelo_cei_importa_singolo',
                nonce: vangeloCeiCalendario.nonce,
                data: data
            },
            success: function(response) {
                if (response.success) {
                    $('#dettaglio_messaggio').html('<p style="color: #155724;">✅ ' + response.data.message + '</p>');
                    // Ricarica il calendario per aggiornare i colori
                    setTimeout(() => {
                        caricaCalendario();
                    }, 1500);
                } else {
                    $('#dettaglio_messaggio').html('<p style="color: #721c24;">❌ ' + response.data.message + '</p>');
                }
            },
            error: function() {
                $('#dettaglio_messaggio').html('<p style="color: #721c24;">❌ Errore durante l\'importazione</p>');
            }
        });
    }
    
    // Funzione per la cancellazione (Vangelo, Santo, o Entrambi)
    function cancellaLiturgia(data, tipo) {
        if (!confirm('Sei sicuro di voler cancellare ' + (tipo === 'vangelo' ? 'il Vangelo' : (tipo === 'santo' ? 'il Santo' : 'Vangelo e Santo')) + ' per la data ' + formattaDataItaliana(data) + '? Questa operazione è irreversibile.')) {
            return;
        }

        $('#cancellazione_messaggio').html('<p>⏳ Cancellazione in corso...</p>');
        // Disabilita tutti i pulsanti di cancellazione durante l'operazione
        $('#btn_cancella_vangelo, #btn_cancella_santo, #btn_cancella_entrambi').prop('disabled', true); 

        $.ajax({
            url: vangeloCeiCalendario.ajax_url,
            type: 'POST',
            data: {
                action: 'vangelo_cei_cancella_liturgia',
                nonce: vangeloCeiCalendario.nonce,
                data: data,
                tipo_cancella: tipo
            },
            success: function(response) {
                if (response.success) {
                    $('#cancellazione_messaggio').html('<p style="color: #155724;">✅ ' + response.data.message + '. Ricaricamento della pagina in corso...</p>');
                    
                    // Ricarica completa della pagina come richiesto dall'utente
                    setTimeout(() => {
                        location.reload(); 
                    }, 500);

                } else {
                    $('#cancellazione_messaggio').html('<p style="color: #721c24;">❌ ' + response.data.message + '</p>');
                }
            },
            error: function() {
                $('#cancellazione_messaggio').html('<p style="color: #721c24;">❌ Errore di comunicazione AJAX durante la cancellazione.</p>');
            }
        });
    }

    // Gestore di eventi per i nuovi pulsanti di cancellazione
    $('#dettaglio_cancellazione').on('click', 'button', function() {
        // Recupera la data ISO precedentemente salvata nell'attributo data-iso
        const dataISO = $('#data_selezionata_dettaglio').data('iso'); 
        const tipo = $(this).data('tipo');
        
        if (dataISO) {
            cancellaLiturgia(dataISO, tipo);
        } else {
             $('#cancellazione_messaggio').html('<p style="color: #721c24;">❌ Nessuna data selezionata. Riprova cliccando su un giorno del calendario.</p>');
        }
    });

}); // QUESTA È LA RIGA DI CHIUSURA ESISTENTE! Assicurati che il codice sia inserito prima di questa.