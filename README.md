# Vangeli del Giorno CEI - Plugin WordPress

Plugin WordPress per importare e visualizzare il Vangelo del giorno dal sito della Conferenza Episcopale Italiana (CEI).

## Caratteristiche

- ✅ **Importazione automatica** dei Vangeli dal sito chiesacattolica.it
- ✅ **Fallback automatico** con scraping in tempo reale per date mancanti
- ✅ **Calendario visivo** per verificare quali Vangeli sono stati importati
- ✅ **Avvisi intelligenti** quando i Vangeli stanno per finire
- ✅ **Shortcode semplice** per mostrare il Vangelo nelle pagine
- ✅ **Cache automatica** per migliorare le prestazioni

## Installazione

1. Carica la cartella `vangeli-del-giorno` in `/wp-content/plugins/`
2. Attiva il plugin dalla sezione Plugin di WordPress
3. Vai su "Vangeli CEI" nel menu laterale
4. Avvia l'importazione rapida o configura un'importazione personalizzata

## Utilizzo degli Shortcode

### Vangelo completo
```
[vangelo_del_giorno]
```

### Solo il testo del Vangelo
```
[vangelo_del_giorno mostra="solo_testo"]
```

### Solo il riferimento biblico
```
[vangelo_del_giorno mostra="solo_riferimento"]
```

## Struttura dei File

```
vangeli-del-giorno/
├── vangeli-del-giorno.php          (File principale)
├── includes/
│   ├── attivazione.php             (Creazione tabella)
│   ├── avvisi-admin.php            (Avvisi dashboard)
│   ├── scraping.php                (Scraping dal sito CEI)
│   ├── database.php                (Gestione database)
│   ├── ajax-handlers.php           (Handler AJAX)
│   ├── shortcode.php               (Shortcode frontend)
│   └── pagina-admin.php            (Pagina amministrazione)
├── assets/
│   ├── js/
│   │   ├── importazione.js         (Gestione importazione)
│   │   ├── calendario.js           (Calendario interattivo)
│   │   └── importazione-rapida.js  (Importazione rapida)
│   └── css/
│       └── admin-style.css         (Stili admin)
└── README.md                       (Questo file)
```

## Funzionalità Principali

### Importazione Manuale
- Scegli data di inizio e numero di anni da importare
- Controllo della velocità di importazione
- Log dettagliato in tempo reale
- Possibilità di fermare l'importazione

### Importazione Rapida
- Importa automaticamente 3 mesi di Vangeli
- Si attiva dall'avviso in dashboard
- Si ferma automaticamente dopo 10 errori consecutivi
- Barra di avanzamento in tempo reale

### Calendario
- Visualizzazione mensile dei Vangeli importati
- 🟢 Verde = Vangelo presente nel database
- 🔴 Rosso = Vangelo non presente
- Click su una data per vedere lo stato
- Possibilità di importare singole date mancanti

### Fallback Automatico
- Se un Vangelo non è nel database, viene scaricato in tempo reale
- Viene salvato automaticamente per le volte successive
- Zero manutenzione richiesta

## Database

Il plugin crea una tabella `wp_vangeli_cei` con la seguente struttura:

- `id` - ID univoco
- `data` - Data del Vangelo (formato Y-m-d)
- `riferimento` - Riferimento biblico (es. "Lc 14,1-6")
- `evangelista` - Nome dell'evangelista
- `testo` - Testo del Vangelo (HTML)
- `html_completo` - HTML completo con formattazione

## Requisiti

- WordPress 5.0 o superiore
- PHP 7.0 o superiore
- Connessione internet per scaricare i Vangeli

## Supporto

Per problemi o domande, apri una issue su GitHub o contatta l'autore.

## Licenza

GPL v2 o successiva

## Credits

Dati liturgici forniti da [chiesacattolica.it](https://www.chiesacattolica.it/)