<?php
/*
 * Autore: Diego Scatizzi
 * Data: 2026
 * Versione: 2.1
 * Funzionalità: File unico che gestisce due "pagine" virtuali:
 *               1. home      → barra di ricerca con autocomplete solo visivo
 *               2. risultati → tabella con i libri trovati dalla ricerca
 */
 
session_start(); // avvia la sessione per gestire login e navigazione tra pagine
 
// Legge nome e cognome dalla sessione. Se non esistono usa valori di default.
// htmlspecialchars converte caratteri speciali (es. <>) in testo sicuro, evitando attacchi XSS
$nome    = isset($_SESSION['nome'])    ? htmlspecialchars($_SESSION['nome'])    : 'Ospite';
$cognome = isset($_SESSION['cognome']) ? htmlspecialchars($_SESSION['cognome']) : '';
 
// ── NAVIGAZIONE: PAGINA DI DEFAULT ───────────────────────────────────────────
// Se non è ancora impostata, la pagina di default è la homepage
if (!isset($_SESSION['pagina'])) {
    $_SESSION['pagina'] = 'home';
}
 
// ── NAVIGAZIONE: TORNA ALLA HOME ─────────────────────────────────────────────
// Bottone "Nuova ricerca": resetta tutto e torna alla homepage
if (isset($_POST['torna_home'])) {
    $_SESSION['pagina']   = 'home';
    $_SESSION['id_libro'] = '';
    $_SESSION['query']    = '';
}
 
// ── NAVIGAZIONE: TORNA AI RISULTATI ──────────────────────────────────────────
// Bottone "Torna ai risultati": torna alla tabella dei risultati precedente
if (isset($_POST['torna_risultati'])) {
    $_SESSION['pagina'] = 'risultati';
}
 
// ── AJAX: AUTOCOMPLETE ────────────────────────────────────────────────────────
/*
 * Questo blocco si attiva SOLO quando JavaScript lo chiama in background.
 * Parametro in ingresso: $_GET['q'] → testo digitato dall'utente
 * Valore di ritorno: JSON array con titolo e autore dei libri trovati
 * Nota: non restituisce più l'ID perché i suggerimenti sono solo visivi,
 *       non cliccabili. Servono solo come aiuto durante la scrittura.
 */
if (isset($_GET['autocomplete'])) {
 
    // Converte la query in minuscolo e rimuove spazi iniziali/finali
    $q       = strtolower(trim($_GET['q'] ?? ''));
    $results = []; // array vuoto che conterrà i risultati
 
    // Cerca solo se l'utente ha scritto almeno 2 caratteri
    if (strlen($q) >= 2) {
 
        $file = fopen(__DIR__ . '/lista-libri.txt', 'r'); // apre il file in sola lettura
 
        if ($file) {
            fgets($file); // salta la prima riga (intestazione con i nomi delle colonne)
 
            /* Legge riga per riga finché:
             * - non finisce il file (fgets restituisce false)
             * - oppure ha già trovato 8 risultati (limite massimo suggerimenti)
             */
            while (($line = fgets($file)) !== false && count($results) < 8) {
 
                // Struttura colonne: N_RIGA;ID_LIBRO;TITOLO;AUTORE;GENERE;ANNO;EDITORE;ISBN;LINGUA;N_PAGINE;DISPONIBILE
                $cols = explode(';', trim($line));
 
                if (count($cols) < 4) continue; // riga malformata, salta
 
                $titolo = $cols[2]; // terza colonna
                $autore = $cols[3]; // quarta colonna
                $isbn   = $cols[7] ?? ''; // ottava colonna (con fallback vuoto se mancante)
 
                // Controlla se la query è contenuta nel titolo, autore o ISBN
                if (
                    str_contains(strtolower($titolo), $q) ||
                    str_contains(strtolower($autore), $q) ||
                    str_contains(strtolower($isbn),   $q)
                ) {
                    // Restituisce solo titolo e autore: i suggerimenti sono solo visivi
                    $results[] = ['titolo' => trim($titolo), 'autore' => trim($autore)];
                }
            }
            fclose($file); // chiude il file dopo l'uso
        }
    }
 
    header('Content-Type: application/json'); // dice al browser che risponde in JSON
    echo json_encode($results);               // converte l'array PHP in testo JSON e lo invia
    exit();                                   // ferma l'esecuzione: non deve caricare l'HTML sotto
}
 
// ── RICERCA COMPLETA (bottone Cerca) ─────────────────────────────────────────
/*
 * Si attiva quando l'utente clicca il bottone "Cerca".
 * Parametro in ingresso: $_POST['cerca'] → presente se il bottone è stato premuto
 *                        $_POST['titolo'] → testo scritto nella barra di ricerca
 * Salva la query in sessione e cambia pagina a 'risultati'
 */
if (isset($_POST['cerca'])) {
    $query = trim($_POST['titolo'] ?? '');
 
    // Mostra errore se la query è troppo corta
    if (strlen($query) < 2) {
        $_SESSION['pagina']         = 'home';
        $_SESSION['errore_ricerca'] = true;
    } else {
        $_SESSION['query']          = $query;
        $_SESSION['pagina']         = 'risultati';
        $_SESSION['errore_ricerca'] = false;
    }
}
 
// ── SELEZIONE LIBRO DA TABELLA RISULTATI ─────────────────────────────────────
// Quando l'utente clicca "Vedi" in una riga della tabella risultati,
// il form POST invia l'ID del libro selezionato.
if (isset($_POST['vai_scheda_da_risultati']) && !empty($_POST['id_libro'])) {
    $_SESSION['id_libro'] = htmlspecialchars(trim($_POST['id_libro']));
    $_SESSION['pagina']   = 'scheda';
}
 
// ── FUNZIONE: LEGGI PRESTITI ──────────────────────────────────────────────────
/*
 * Funzione leggiPrestiti()
 * Parametro in ingresso: nessuno
 * Valore di ritorno: array associativo [ ID_LIBRO => ['inizio' => ..., 'fine' => ...] ]
 * Legge le date di prestito direttamente da lista-libri.txt, colonne 11 e 12.
 * Struttura colonne: N_RIGA;ID_LIBRO;TITOLO;AUTORE;GENERE;ANNO;EDITORE;ISBN;LINGUA;N_PAGINE;DISPONIBILE;DATA_INIZIO;DATA_FINE
 * Ignora le righe dove DATA_INIZIO o DATA_FINE sono NULL.
 */
function leggiPrestiti() {
    $prestiti = []; // array che conterrà i prestiti attivi
 
    $file = fopen(__DIR__ . '/lista-libri.txt', 'r');
    if (!$file) return $prestiti;
 
    fgets($file); // salta la riga di intestazione
 
    // Legge ogni riga del file
    while (($line = fgets($file)) !== false) {
        $cols = explode(';', trim($line));
 
        if (count($cols) < 13) continue; // riga senza colonne date, salta
 
        $idLibro    = trim($cols[1]);  // seconda colonna: ID del libro
        $dataInizio = trim($cols[11]); // dodicesima colonna: data inizio noleggio
        $dataFine   = trim($cols[12]); // tredicesima colonna: data fine noleggio
 
        // Ignora righe dove le date non sono ancora state impostate
        if (strtoupper($dataInizio) === 'NULL' || strtoupper($dataFine) === 'NULL') continue;
 
        $prestiti[$idLibro] = ['inizio' => $dataInizio, 'fine' => $dataFine];
    }
 
    fclose($file);
    return $prestiti;
}
 
// ── FUNZIONE: CERCA LIBRO PER ID ─────────────────────────────────────────────
/*
 * Funzione cercaLibroPerID()
 * Parametro in ingresso: $idCercato → stringa con l'ID del libro (es. LIB001)
 * Valore di ritorno: array associativo con i dati del libro, oppure null se non trovato
 */
function cercaLibroPerID($idCercato) {
 
    $file = fopen(__DIR__ . '/lista-libri.txt', 'r'); // apre il file in sola lettura
 
    if (!$file) return null; // se il file non esiste restituisce null
 
    fgets($file); // salta la riga di intestazione
 
    // Legge riga per riga fino alla fine del file
    while (($line = fgets($file)) !== false) {
 
        // Struttura colonne: N_RIGA;ID_LIBRO;TITOLO;AUTORE;GENERE;ANNO;EDITORE;ISBN;LINGUA;N_PAGINE;DISPONIBILE
        $cols = explode(';', trim($line));
 
        if (count($cols) < 11) continue; // riga malformata, salta
 
        // Controlla se l'ID di questa riga corrisponde a quello cercato
        if (trim($cols[1]) === $idCercato) {
            fclose($file);
 
            // Restituisce un array associativo con tutti i campi del libro
            return [
                'id'          => trim($cols[1]),
                'titolo'      => trim($cols[2]),
                'autore'      => trim($cols[3]),
                'genere'      => trim($cols[4]),
                'anno'        => trim($cols[5]),
                'editore'     => trim($cols[6]),
                'isbn'        => trim($cols[7]),
                'lingua'      => trim($cols[8]),
                'pagine'      => trim($cols[9]),
                'disponibile' => strtoupper(trim($cols[10])) === 'SI', // converte SI/NO in true/false
            ];
        }
    }
 
    fclose($file);
    return null; // libro non trovato
}
 
// ── FUNZIONE: CERCA LIBRI PER TESTO ──────────────────────────────────────────
/*
 * Funzione cercaLibriPerTesto()
 * Parametro in ingresso: $q → stringa con il testo da cercare
 * Valore di ritorno: array di array associativi con i libri trovati (può essere vuoto)
 * Cerca per titolo, autore, genere e ISBN senza limite di risultati
 */
function cercaLibriPerTesto($q) {
 
    $q       = strtolower(trim($q)); // normalizza la query
    $results = []; // array che conterrà tutti i risultati trovati
 
    $file = fopen(__DIR__ . '/lista-libri.txt', 'r'); // apre il file in sola lettura
 
    if (!$file) return $results; // se il file non esiste restituisce array vuoto
 
    fgets($file); // salta la riga di intestazione
 
    // Legge tutte le righe del file senza limite di risultati
    while (($line = fgets($file)) !== false) {
 
        $cols = explode(';', trim($line));
 
        if (count($cols) < 11) continue; // riga malformata, salta
 
        $titolo = trim($cols[2]);
        $autore = trim($cols[3]);
        $genere = trim($cols[4]);
        $isbn   = trim($cols[7]);
 
        // Cerca la query in titolo, autore, genere e ISBN
        if (
            str_contains(strtolower($titolo), $q) ||
            str_contains(strtolower($autore), $q) ||
            str_contains(strtolower($genere), $q) ||
            str_contains(strtolower($isbn),   $q)
        ) {
            $results[] = [
                'id'          => trim($cols[1]),
                'titolo'      => $titolo,
                'autore'      => $autore,
                'genere'      => $genere,
                'anno'        => trim($cols[5]),
                'disponibile' => strtoupper(trim($cols[10])) === 'SI',
            ];
        }
    }
 
    fclose($file);
    return $results;
}
 
// ── FUNZIONE: AGGIORNA DISPONIBILITÀ SUL FILE ────────────────────────────────
/*
 * Funzione aggiornaDisponibilita()
 * Parametri in ingresso:
 *   - $idLibro    → ID del libro da aggiornare (es. LIB001)
 *   - $nuovoVal   → nuovo valore da scrivere ('SI' oppure 'NO')
 *   - $dataInizio → data inizio noleggio da scrivere in col. 11 (opzionale)
 *   - $dataFine   → data fine noleggio da scrivere in col. 12 (opzionale)
 * Valore di ritorno: true se l'aggiornamento è riuscito, false altrimenti
 * Legge tutto il file, modifica la riga corrispondente e lo riscrive
 */
function aggiornaDisponibilita($idLibro, $nuovoVal, $dataInizio = null, $dataFine = null) {
 
    $percorso = __DIR__ . '/lista-libri.txt';
    $righe    = file($percorso); // legge tutte le righe del file in un array
 
    if ($righe === false) return false; // errore di lettura
 
    $trovato = false; // diventa true se il libro viene trovato e modificato
 
    // Scorre ogni riga cercando quella con l'ID corrispondente
    foreach ($righe as $i => $riga) {
        $cols = explode(';', trim($riga));
 
        if (count($cols) < 11) continue; // riga malformata, salta
 
        // Confronta l'ID della riga con quello cercato
        if (trim($cols[1]) === $idLibro) {
            $cols[10] = $nuovoVal; // modifica l'undicesima colonna (DISPONIBILE)
 
            // Se sono state passate le date, le scrive nelle colonne 11 e 12
            if ($dataInizio !== null) $cols[11] = $dataInizio;
            if ($dataFine   !== null) $cols[12] = $dataFine;
 
            $righe[$i] = implode(';', $cols) . "\n"; // ricostruisce la riga
            $trovato   = true;
            break; // esce dal ciclo: ha trovato e modificato la riga
        }
    }
 
    if (!$trovato) return false; // libro non trovato nel file
 
    // Riscrive tutto il file con la riga modificata
    $risultato = file_put_contents($percorso, implode('', $righe));
    return $risultato !== false;
}
 
// ── GESTIONE NOLEGGIO ─────────────────────────────────────────────────────────
/*
 * Si attiva quando l'utente clicca "Noleggia" nella scheda libro.
 * Parametro in ingresso: $_POST['noleggia'] → presente se il bottone è stato premuto
 *                        $_POST['id_noleggio'] → ID del libro da noleggiare
 * Aggiorna il file lista-libri.txt: DISPONIBILE → NO e scrive le date
 * nelle colonne 11 (DATA_INIZIO) e 12 (DATA_FINE), sovrascrivendo NULL;NULL
 */
$noleggiato = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['noleggia'])) {
 
    $idNoleggio = htmlspecialchars(trim($_POST['id_noleggio'] ?? ''));
 
    if ($idNoleggio !== '') {
        $dataInizioNoleggio = date('d-m-Y');                          // oggi
        $dataFineNoleggio   = date('d-m-Y', strtotime('+30 days'));   // tra 30 giorni
 
        // Aggiorna disponibilità e date direttamente in lista-libri.txt
        aggiornaDisponibilita($idNoleggio, 'NO', $dataInizioNoleggio, $dataFineNoleggio);
 
        $noleggiato = true;
    }
 
    $_SESSION['pagina'] = 'scheda'; // rimane sulla scheda dopo il noleggio
}
 
// ── PREPARAZIONE DATI PER LA PAGINA CORRENTE ─────────────────────────────────
$paginaCorrente = $_SESSION['pagina'];
$libro          = null;
$risultati      = [];
$prestiti       = leggiPrestiti(); // carica tutti i prestiti attivi
 
// Se siamo sulla scheda, carica il libro dalla sessione
if ($paginaCorrente === 'scheda' && !empty($_SESSION['id_libro'])) {
    $libro = cercaLibroPerID($_SESSION['id_libro']);
 
    // Se il libro non viene trovato, torna alla home
    if ($libro === null) {
        $_SESSION['pagina'] = 'home';
        $paginaCorrente     = 'home';
    }
}
 
// Se siamo sui risultati, esegue la ricerca con la query salvata in sessione
if ($paginaCorrente === 'risultati' && !empty($_SESSION['query'])) {
    $risultati = cercaLibriPerTesto($_SESSION['query']);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca Scolastica</title>
</head>
<body>
 
<!-- Barra utente in alto a sinistra, presente in tutte le pagine -->
<div>
    <small>Accesso come <strong><?= $nome . ' ' . $cognome ?></strong></small>
    <a href="logout.php">Esci</a>
</div>
 
<?php if ($paginaCorrente === 'home'): ?>
 
<?php
/*
 * ════════════════════════════════════════════════════════════════
 * PAGINA: HOME
 * Funzionalità: Barra di ricerca con autocomplete solo visivo (non cliccabile).
 *               I suggerimenti aiutano durante la scrittura ma non navigano.
 *               Solo il bottone "Cerca" avvia la ricerca e mostra i risultati.
 * ════════════════════════════════════════════════════════════════
 */
?>
 
    <h1>Biblioteca Scolastica</h1>
 
    <!-- Messaggio di errore se la query è troppo corta -->
    <?php if (!empty($_SESSION['errore_ricerca'])): ?>
        <p>Scrivi almeno 2 caratteri per cercare un libro.</p>
    <?php endif; ?>
 
    <!--
        Form di ricerca.
        Metodo POST verso lo stesso file.
        Il bottone "Cerca" attiva la ricerca completa con tabella risultati.
        I suggerimenti sono solo visivi: non cliccando su di essi si naviga.
    -->
    <form method="POST" action="" id="searchForm">
        <input
            type="text"
            name="titolo"
            id="searchInput"
            placeholder="Barra di ricerca"
            autocomplete="off"
        >
        <!-- div che mostra i suggerimenti visivi durante la scrittura -->
        <div id="suggerimenti"></div>
        <br>
        <!-- Il bottone Cerca avvia la ricerca completa (pagina risultati) -->
        <button type="submit" name="cerca">Cerca</button>
    </form>
 
    <script>
        // Recupera i riferimenti agli elementi HTML che useremo
        const input       = document.getElementById('searchInput');  // campo di testo visibile
        const box         = document.getElementById('suggerimenti'); // div dove mostrare i suggerimenti
        let debounceTimer = null; // variabile per gestire il ritardo (vedi commento sotto)
 
        /*
         * Evento: si attiva ad ogni tasto premuto dentro il campo di ricerca
         * Scopo: chiamare il PHP in background e mostrare i suggerimenti VISIVI
         * Nota: i suggerimenti non sono cliccabili, servono solo come aiuto
         *       durante la scrittura. La ricerca parte solo con il bottone Cerca.
         */
        input.addEventListener('input', function () {
 
            // DEBOUNCE: cancella il timer precedente ad ogni tasto.
            // Senza debounce ogni singolo tasto farebbe una chiamata al server.
            // Con debounce si aspetta che l'utente smetta di scrivere per 200ms.
            // Es: se scrivo "isa" rapidamente → vengono annullate le chiamate per "i" e "is",
            //     e parte solo quella per "isa" dopo 200ms di pausa.
            clearTimeout(debounceTimer);
 
            const q = this.value.trim(); // prende il testo scritto, rimuovendo spazi
 
            // Se ha scritto meno di 2 caratteri, svuota i suggerimenti e non fa nulla
            if (q.length < 2) { box.innerHTML = ''; return; }
 
            // Avvia il timer: esegue la funzione dopo 200ms di pausa dalla scrittura
            debounceTimer = setTimeout(() => {
 
                /*
                 * fetch(): fa una chiamata HTTP in background (AJAX) allo stesso file PHP.
                 * Passa due parametri GET:
                 *   - autocomplete=1 → dice al PHP di entrare nel blocco dei suggerimenti
                 *   - q=testo        → il testo da cercare
                 * encodeURIComponent converte caratteri speciali per l'URL (es. spazi → %20)
                 */
                fetch(`?autocomplete=1&q=${encodeURIComponent(q)}`)
 
                    // quando arriva la risposta, la converte da testo JSON a array JavaScript
                    .then(r => r.json())
 
                    // riceve l'array di libri trovati e aggiorna il div suggerimenti
                    .then(libri => {
 
                        // Se il PHP non ha trovato niente, svuota i suggerimenti
                        if (!libri.length) { box.innerHTML = ''; return; }
 
                        /*
                         * .map(): trasforma ogni libro in un <div> di testo puro.
                         * Non ha onclick: i suggerimenti sono solo visivi.
                         * cursor:default impedisce il cursore a manina al passaggio.
                         * .join(''): unisce tutti i <div> in una stringa unica
                         */
                        box.innerHTML = libri.map(l =>
                            `<div style="cursor:default;">
                                ${l.titolo} — ${l.autore}
                            </div>`
                        ).join('');
                    });
 
            }, 200); // 200ms di attesa prima di fare la chiamata al server
        });
 
        /*
         * Evento sul documento intero: chiude i suggerimenti se l'utente
         * clicca in qualsiasi punto della pagina fuori dal campo o dal box.
         * Parametro: e → l'evento click, contiene e.target (elemento cliccato)
         * .contains() restituisce true se l'elemento cliccato è dentro input o box
         */
        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !box.contains(e.target)) {
                box.innerHTML = '';
            }
        });
    </script>
 
<?php elseif ($paginaCorrente === 'risultati'): ?>
 
<?php
/*
 * ════════════════════════════════════════════════════════════════
 * PAGINA: RISULTATI
 * Funzionalità: Mostra in una tabella tutti i libri che corrispondono
 *               alla query cercata. Per ogni libro mostra titolo, autore,
 *               genere, anno e disponibilità aggiornata dal file.
 *               Il bottone "Vedi" apre la scheda dettaglio del libro.
 * ════════════════════════════════════════════════════════════════
 */
?>
 
    <!-- Bottone per tornare alla homepage e fare una nuova ricerca -->
    <form method="POST" action="" style="display:inline;">
        <button type="submit" name="torna_home">← Nuova ricerca</button>
    </form>
 
    <h1>Risultati per: "<?= htmlspecialchars($_SESSION['query']) ?>"</h1>
 
    <?php if (empty($risultati)): ?>
 
        <!-- Nessun libro trovato con la query inserita -->
        <p>Nessun libro trovato. Prova con un altro termine.</p>
 
    <?php else: ?>
 
        <!-- Tabella con tutti i libri trovati dalla ricerca -->
        <p>Trovati <?= count($risultati) ?> libri:</p>
 
        <table border="1" cellpadding="6">
            <thead>
                <tr>
                    <th>Titolo</th>
                    <th>Autore</th>
                    <th>Genere</th>
                    <th>Anno</th>
                    <th>Disponibile</th>
                    <th>Azione</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($risultati as $riga): ?>
                <tr>
                    <td><?= htmlspecialchars($riga['titolo']) ?></td>
                    <td><?= htmlspecialchars($riga['autore']) ?></td>
                    <td><?= htmlspecialchars($riga['genere']) ?></td>
                    <td><?= htmlspecialchars($riga['anno']) ?></td>
                    <td><?= $riga['disponibile'] ? '✅ Sì' : '❌ No' ?></td>
                    <td>
                        <!--
                            Form per ogni riga della tabella.
                            Invia l'ID del libro al PHP per aprire la scheda dettaglio.
                        -->
                        <form method="POST" action="">
                            <input type="hidden" name="id_libro" value="<?= htmlspecialchars($riga['id']) ?>">
                            <button type="submit" name="vai_scheda_da_risultati">Vedi</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
 
    <?php endif; ?>
 
<?php elseif ($paginaCorrente === 'scheda'): ?>
