/**
 * Gestione importazione rapida dall'avviso admin
 */

jQuery(document).ready(function($) {
    let importazioneRapidaAttiva = false;
    let erroriConsecutivi = 0;
    const MAX_ERRORI_CONSECUTIVI = 10;
    
    $('#btn_importazione_rapida').on('click', function() {
        if (importazioneRapidaAttiva) return;
        
        if (!confirm('Vuoi avviare l\'importazione rapida di 3 mesi di Vangeli?\n\nQuesta operazione richiederà alcuni minuti.')) {
            return;
        }
        
        importazioneRapidaAttiva = true;
        $('#btn_importazione_rapida').prop('disabled', true).text('⏳ Importazione in corso...');
        $('#importazione_rapida_status').show();
        
        avviaImportazioneRapida();
    });
    
    async function avviaImportazioneRapida() {
        const dataInizio = new Date();
        const dataFine = new Date();
        dataFine.setMonth(dataFine.getMonth() + 3);
        
        const date = [];
        let current = new Date(dataInizio);
        
        while (current <= dataFine) {
            date.push(current.toISOString().split('T')[0]);
            current.setDate(current.getDate() + 1);
        }
        
        const totale = date.length;
        let importati = 0;
        let successi = 0;
        let errori = 0;
        let ultimoTipoErrore = ''; // Tipo ultimo errore (senza numero)
        let listaErrori = []; // Array completo per il riepilogo finale
        
        $('#status_rapida').text('0 / ' + totale + ' giorni importati');
        
        // Crea la tabella
        await $.ajax({
            url: vangeloCeiRapida.ajax_url,
            type: 'POST',
            data: {
                action: 'vangelo_cei_crea_tabella',
                nonce: vangeloCeiRapida.nonce
            }
        });
        
        for (const data of date) {
            if (erroriConsecutivi >= MAX_ERRORI_CONSECUTIVI) {
                let riepilogoErrori = '';
                if (listaErrori.length > 0) {
                    riepilogoErrori = '<br><small>' + listaErrori.join(' || ') + '</small>';
                }
                
                $('#status_rapida').html('<span style="color: #d63638;">❌ Troppi errori consecutivi. Importazione fermata.<br>Importati: ' + successi + ' | Totale errori: ' + errori + riepilogoErrori + '<br><strong>Il plugin userà il fallback automatico per le date mancanti.</strong></span>');
                $('#btn_importazione_rapida').text('⚠️ Importazione Interrotta');
                setTimeout(() => location.reload(), 3000);
                return; // FERMATI QUI
            }
            
            try {
                const response = await $.ajax({
                    url: vangeloCeiRapida.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'vangelo_cei_importa_singolo',
                        nonce: vangeloCeiRapida.nonce,
                        data: data
                    }
                });
                
                if (response.success) {
                    if (response.data.status !== 'skip') {
                        successi++;
                        
                        // Traccia errori parziali (ma conta come successo parziale)
                        if (response.data.errore_tipo === 'vangelo') {
                            errori++;
                            ultimoTipoErrore = 'Vangelo';
                            listaErrori.push('Errore ' + errori + ': Vangelo');
                            erroriConsecutivi++; // IMPORTANTE: incrementa errori consecutivi
                        } else if (response.data.errore_tipo === 'santo') {
                            errori++;
                            ultimoTipoErrore = 'Santo';
                            listaErrori.push('Errore ' + errori + ': Santo');
                            erroriConsecutivi++; // IMPORTANTE: incrementa errori consecutivi
                        } else {
                            ultimoTipoErrore = ''; // Nessun errore
                            erroriConsecutivi = 0; // RESET errori consecutivi
                        }
                    } else {
                        erroriConsecutivi = 0; // RESET se già presente (skip)
                    }
                } else {
                    errori++;
                    erroriConsecutivi++;
                    
                    // Traccia tipo di errore completo
                    if (response.data.errore_tipo === 'entrambi') {
                        ultimoTipoErrore = 'Vangelo e Santo';
                        listaErrori.push('Errore ' + errori + ': Vangelo e Santo');
                    } else if (response.data.errore_tipo === 'vangelo') {
                        ultimoTipoErrore = 'Vangelo';
                        listaErrori.push('Errore ' + errori + ': Vangelo');
                    } else if (response.data.errore_tipo === 'santo') {
                        ultimoTipoErrore = 'Santo';
                        listaErrori.push('Errore ' + errori + ': Santo');
                    } else {
                        ultimoTipoErrore = 'Generico';
                        listaErrori.push('Errore ' + errori + ': Generico');
                    }
                }
            } catch (error) {
                errori++;
                erroriConsecutivi++;
                ultimoTipoErrore = 'Vangelo e Santo';
                listaErrori.push('Errore ' + errori + ': Vangelo e Santo');
            }
            
            importati++;
            const percentuale = Math.round((importati / totale) * 100);
            $('#barra_rapida').css('width', percentuale + '%');
            
            // Mostra solo il tipo di errore (senza numero ripetuto)
            let testoErrore = ultimoTipoErrore ? ' - ' + ultimoTipoErrore : '';
            
            $('#status_rapida').text(importati + ' / ' + totale + ' giorni elaborati | Successi: ' + successi + ' | Errori: ' + errori + testoErrore);
            
            await new Promise(resolve => setTimeout(resolve, 500));
        }
        
        // Completamento: mostra tutti gli errori se presenti
        let dettaglioFinale = '';
        if (listaErrori.length > 0) {
            dettaglioFinale = '<br><small style="display: block; margin-top: 10px; max-height: 100px; overflow-y: auto;">' + listaErrori.join(' || ') + '</small>';
        }
        
        $('#status_rapida').html('<span style="color: #00a32a;">✅ Importazione completata!<br>Successi: ' + successi + ' | Errori: ' + errori + dettaglioFinale + '</span>');
        $('#btn_importazione_rapida').text('✅ Completato');
        
        setTimeout(() => location.reload(), 2000);
    }
});