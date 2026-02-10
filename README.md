# Logistika WooCommerce

Plugin WordPress per integrare WooCommerce con il sistema di logistica Logistika. Supporta invio ordini via CSV/Email o API REST.

## Descrizione

Logistika WooCommerce permette di:

- Esportare ordini WooCommerce in formato CSV compatibile con i sistemi logistici
- Inviare automaticamente il CSV via email
- **Inviare ordini direttamente via API REST** (nuovo!)
- Gestire esportazioni singole o in blocco
- Configurare il codice vettore (CodVet) e altri parametri
- Ricevere aggiornamenti automatici da GitHub

## Requisiti

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+

## Installazione

### Metodo 1: Upload manuale

1. Scarica il file ZIP dalla sezione [Releases](https://github.com/your-username/logistika-woocommerce/releases)
2. Vai su WordPress Admin > Plugin > Aggiungi nuovo > Carica plugin
3. Seleziona il file ZIP e clicca "Installa ora"
4. Attiva il plugin

### Metodo 2: Via FTP

1. Scarica e decomprimi il file ZIP
2. Carica la cartella `logistika-woocommerce` nella directory `/wp-content/plugins/`
3. Attiva il plugin dal menu Plugin di WordPress

## Configurazione

Dopo l'attivazione, vai su **Logistika > Impostazioni** per configurare:

| Impostazione | Descrizione | Default |
|-------------|-------------|---------|
| CodVet | Codice identificativo del vettore | 40005 |
| Email Destinatario | Email a cui inviare i CSV | Email admin |
| Oggetto Email | Oggetto delle email (usa {order_id} e {date}) | Nuovo ordine Logistika - {order_id} |
| Export Automatico | Esporta automaticamente al cambio stato | Disattivo |
| Stato Export Auto | Stato ordine che attiva l'export automatico | In lavorazione |
| Formato Data | Formato data nel CSV | dd/mm/yy |
| Separatore CSV | Carattere separatore | ; (punto e virgola) |
| Metodo di Invio | Email, API o Entrambi | Email |
| URL API | URL base dell'API Logistika | - |
| API Key | Chiave di autenticazione API | - |

## Integrazione API REST

Il plugin supporta l'invio diretto degli ordini tramite API REST. Per attivare:

1. Vai su **Logistika > Impostazioni**
2. Nella sezione "Configurazione API", seleziona **API REST** come metodo di invio
3. Inserisci l'**URL API** fornito da Logistika (es: `https://tuodominio.com/logistika/app/api`)
4. Inserisci la tua **API Key** (disponibile nel pannello Logistika)
5. Clicca "Test API" per verificare la connessione

### Vantaggi dell'API

- Invio istantaneo degli ordini
- Sincronizzazione tracking in tempo reale
- Nessun ritardo email
- Maggiore affidabilita'

## Utilizzo

### Export singolo ordine

1. Vai su WooCommerce > Ordini
2. Apri un ordine
3. Nel box laterale "Logistika Export", clicca "Esporta Ora"

### Export multiplo

1. Vai su **Logistika > Esporta Ordini**
2. Filtra gli ordini per stato, data o stato di esportazione
3. Seleziona gli ordini da esportare
4. Scegli l'azione:
   - **Esporta e Invia Email**: genera il CSV e lo invia all'email configurata
   - **Esporta e Scarica**: genera il CSV e lo scarica sul tuo computer
   - **Anteprima CSV**: mostra un'anteprima del CSV

### Bulk Action dalla lista ordini

1. Vai su WooCommerce > Ordini
2. Seleziona gli ordini
3. Dal menu "Azioni di massa", scegli:
   - "Logistika - Esporta CSV" per scaricare
   - "Logistika - Esporta e Invia Email" per inviare via email

## Formato CSV

Il CSV generato contiene le seguenti colonne:

| Colonna | Descrizione |
|---------|-------------|
| CodVet | Codice vettore |
| NUMDOCEST | Numero ordine |
| DATADOCEST | Data ordine |
| DESTZCLI | Nome/Ragione sociale destinatario |
| INDDESTZCI | Indirizzo |
| CAPDESTZCI | CAP |
| CITDESTZCI | Citta' |
| PROVDESTZCI | Provincia (sigla) |
| CONTRASSEGNO | Importo contrassegno (se pagamento COD) |
| CodPor | Codice contrassegno |
| CFDESTZCI | Codice Fiscale |
| PIDESTZCI | Partita IVA |
| NOMESPEC | Nome persona specifica |
| TELDESTZCI | Telefono |
| MAILDESTZCI | Email |
| ImpLordoOrdine | Importo totale ordine |
| NOTE INTERNE | Note per il magazzino |
| NOTE SPEDIZIONE | Note per il corriere |
| INTERNOCODART | ID prodotto interno |
| CODART | SKU prodotto |
| DESCRIZIONE ARTICOLO | Nome prodotto |
| QTA RICHIESTA | Quantita' |

**Nota:** Se un ordine contiene piu' prodotti, viene generata una riga per ogni prodotto, ripetendo i dati dell'ordine.

## Aggiornamenti automatici

Il plugin supporta gli aggiornamenti automatici da GitHub. Quando viene pubblicata una nuova release:

1. Vai su Dashboard > Aggiornamenti
2. Il plugin apparira' nella lista degli aggiornamenti disponibili
3. Clicca "Aggiorna ora"

## Hook e Filtri

### Filtri disponibili

```php
// Modificare le intestazioni CSV
add_filter('logistika_csv_headers', function($headers) {
    return $headers;
});

// Modificare i dati di un ordine prima dell'export
add_filter('logistika_order_data', function($data, $order) {
    return $data;
}, 10, 2);

// Modificare il template email
add_filter('logistika_email_template', function($template_path) {
    return $template_path;
});
```

### Azioni disponibili

```php
// Dopo l'export di un ordine
add_action('logistika_after_export', function($order_id, $csv_content) {
    // Il tuo codice
}, 10, 2);

// Dopo l'invio email
add_action('logistika_after_email_sent', function($order_ids, $email, $success) {
    // Il tuo codice
}, 10, 3);
```

## Campi personalizzati

Il plugin cerca automaticamente i seguenti meta fields per Codice Fiscale e Partita IVA:

- `_billing_cf` / `billing_cf` / `_billing_codice_fiscale`
- `_billing_piva` / `billing_piva` / `_billing_partita_iva` / `_vat_number`

Per aggiungere note interne o di spedizione, usa i meta fields:

- `_logistika_note_interne`
- `_logistika_note_spedizione`

## Changelog

### 1.0.0
- Release iniziale
- Export singolo e multiplo ordini
- Invio email con allegato CSV
- **Integrazione API REST** per invio diretto ordini
- Configurazione CodVet e parametri
- Aggiornamenti automatici da GitHub
- Compatibilita' HPOS WooCommerce

## Supporto

Per segnalare bug o richiedere funzionalita':
- Apri una [Issue su GitHub](https://github.com/your-username/logistika-woocommerce/issues)

## Licenza

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## Credits

Sviluppato da [Logistika](https://logistika.it)
