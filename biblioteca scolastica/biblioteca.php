<?php
/**
 * @file biblioteca.php
 * @brief Sistema di gestione per una biblioteca scolastica.
 *
 * File unico che gestisce le seguenti pagine virtuali tramite $_SESSION['pagina']:
 * -# <b>login</b>        — accesso con codice smart card e password
 * -# <b>registrazione</b> — creazione account con generazione smart card automatica
 * -# <b>home</b>         — barra di ricerca con autocomplete visivo (AJAX)
 * -# <b>risultati</b>    — tabella con i libri trovati dalla ricerca
 * -# <b>scheda</b>       — dettaglio libro con disponibilità e noleggio
 *
 * Gli utenti sono salvati in utenti.txt con struttura:
 * SMART_CARD;NOME;COGNOME;EMAIL;PASSWORD_HASH
 *
 * Il catalogo libri è in lista-libri.txt con struttura:
 * N;ID;TITOLO;AUTORE;GENERE;ANNO;EDITORE;ISBN;LINGUA;PAGINE;DISPONIBILE;DATA_INIZIO;DATA_FINE
 *
 * Ogni azione POST termina con un redirect (pattern PRG - Post/Redirect/Get)
 * per evitare che parametri GET residui nella URL causino navigazioni
 * indesiderate al ricaricamento della pagina.
 *
 * @copyright COPYRIGHT (c) 2026 MySoft snc. All rights reserved.
 * @author    Massimiliano Drago
 * @author    Diego Scatizzi
 * @author    Duccio Donnini
 * @author    Gabriele Severi
 * @author    Matteo Procino
 * @version   4.2
 * @date      2026-05-23
 */

session_start();

// ── COSTANTE: URL BASE PULITA ─────────────────────────────────────────────────
// Usata nei redirect per eliminare sempre i parametri GET dalla URL
define('URL_BASE', strtok($_SERVER['REQUEST_URI'], '?'));

// ── NAVIGAZIONE: PAGINA DI DEFAULT ───────────────────────────────────────────
// Se la sessione non ha una pagina impostata, parte dal login
if (!isset($_SESSION['pagina'])) {
    $_SESSION['pagina'] = 'login';
}

// ── PROTEZIONE PAGINE INTERNE ─────────────────────────────────────────────────
// Se l'utente non è loggato e cerca di accedere a home/risultati/scheda,
// viene rimandato al login. Questo controllo avviene PRIMA di tutto il resto
// così i POST vengono processati solo dalla pagina corretta.
$paginePubbliche = ['login', 'registrazione'];
if (!in_array($_SESSION['pagina'], $paginePubbliche) && empty($_SESSION['loggato'])) {
    $_SESSION['pagina'] = 'login';
}

// ── PREPARAZIONE VARIABILI COMUNI PER L'HTML ─────────────────────────────────
$paginaCorrente    = $_SESSION['pagina'];
$nome              = isset($_SESSION['nome'])    ? htmlspecialchars($_SESSION['nome'])    : '';
$cognome           = isset($_SESSION['cognome']) ? htmlspecialchars($_SESSION['cognome']) : '';
$erroreLogin       = $_SESSION['errore_login']       ?? '';
$erroreReg         = $_SESSION['errore_reg']          ?? '';
$smartCardGenerata = $_SESSION['smart_card_generata'] ?? '';
$noleggiato        = false;
$libro             = null;
$risultati         = [];
$prestiti          = [];

// ── POST: LOGOUT ──────────────────────────────────────────────────────────────
// Qui perché il bottone "Esci" appare in home/risultati/scheda,
// non nel blocco login. Deve essere raggiungibile da qualsiasi pagina.
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . URL_BASE);
    exit();
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca Scolastica</title>
</head>
<body>

<?php if ($paginaCorrente === 'login'): ?>

<?php
// ════════════════════════════════════════════════════════════════
// PAGINA: LOGIN
// Autore: Gabriele Severi
// Data: 2026
// Versione: 2.1
// Funzionalità: Form di accesso con codice smart card e password.
//               Se le credenziali sono corrette va alla home.
//               Il bottone "Registrati" porta alla pagina di registrazione.
//               I messaggi di errore vengono passati tramite sessione.
// ════════════════════════════════════════════════════════════════

/**
 * @brief Cerca un utente nel file utenti.txt verificando smart card e password.
 *
 * Apre utenti.txt e confronta riga per riga il codice smart card e la password.
 * La verifica della password avviene tramite password_verify() che confronta
 * la stringa in chiaro con l'hash bcrypt salvato nel file.
 *
 * Struttura di utenti.txt: SMART_CARD;NOME;COGNOME;EMAIL;PASSWORD_HASH
 *
 * @author  Gabriele Severi
 * @version 2.1
 *
 * @param string $smartCard Codice smart card dell'utente (es. SC-AB12CD34)
 * @param string $password  Password in chiaro da verificare contro l'hash salvato
 * @return array|null Array associativo con chiavi 'nome' e 'cognome' se trovato,
 *                    null se le credenziali non corrispondono o il file non esiste
 */
function cercaUtente($smartCard, $password) {
    $percorso = __DIR__ . '/utenti.txt';
    if (!file_exists($percorso)) return null;

    $file = fopen($percorso, 'r');
    if (!$file) return null;

    while (($line = fgets($file)) !== false) {
        $cols = explode(';', trim($line));
        if (count($cols) < 5) continue;

        // password_verify() confronta la password in chiaro con l'hash salvato nel file
        if (trim($cols[0]) === $smartCard && password_verify($password, trim($cols[4]))) {
            fclose($file);
            return ['nome' => trim($cols[1]), 'cognome' => trim($cols[2])];
        }
    }
    fclose($file);
    return null;
}

// ── POST: ACCEDI ──────────────────────────────────────────────────────────────
// Verifica le credenziali. Se corrette imposta la sessione e vai alla home.
// Se errate salva il messaggio di errore in sessione e torna al login.
// Parametri: $_POST['smart_card'], $_POST['password']
if (isset($_POST['accedi'])) {
    $smartCardInput = trim($_POST['smart_card'] ?? '');
    $passwordInput  = trim($_POST['password']   ?? '');

    if (empty($smartCardInput) || empty($passwordInput)) {
        $_SESSION['errore_login'] = 'Compila tutti i campi.';
        $_SESSION['pagina']       = 'login';
    } else {
        $utente = cercaUtente($smartCardInput, $passwordInput);
        if ($utente !== null) {
            // Credenziali corrette: imposta sessione e vai alla home
            $_SESSION['loggato']      = true;
            $_SESSION['nome']         = $utente['nome'];
            $_SESSION['cognome']      = $utente['cognome'];
            $_SESSION['pagina']       = 'home';
            $_SESSION['errore_login'] = '';
        } else {
            // Credenziali errate: mostra errore e rimane al login
            $_SESSION['errore_login'] = 'Codice Smart Card o password errati.';
            $_SESSION['pagina']       = 'login';
        }
    }
    // Redirect verso URL pulita: elimina qualsiasi ?pagina=... dalla barra
    header('Location: ' . URL_BASE);
    exit();
}

// ── POST: VAI ALLA REGISTRAZIONE ──────────────────────────────────────────────
// Bottone nel login per andare alla registrazione
if (isset($_POST['vai_registrazione'])) {
    $_SESSION['pagina'] = 'registrazione';
    header('Location: ' . URL_BASE);
    exit();
}
?>

    <h1>Biblioteca Scolastica — Login</h1>

    <!-- Messaggio di errore mostrato se le credenziali sono errate -->
    <?php if (!empty($erroreLogin)): ?>
        <p><?= htmlspecialchars($erroreLogin) ?></p>
    <?php endif; ?>

    <!--
        Form di login. Metodo POST verso lo stesso file (URL base).
        Campi:
          - smart_card → codice ricevuto alla registrazione (es. SC-AB12CD34)
          - password   → password scelta alla registrazione
        Il campo accedi dice al PHP di verificare le credenziali.
    -->
    <form method="POST" action="<?= URL_BASE ?>">
        <label for="smart_card">Codice Smart Card:</label>
        <input type="text" id="smart_card" name="smart_card" placeholder="Es. SC-AB12CD34" required>
        <br>
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
        <br>
        <button type="submit" name="accedi">Accedi</button>
    </form>

    <br>

    <!--
        Form separato per andare alla registrazione.
        Usa POST (come tutto il resto) così non aggiunge parametri GET alla URL.
    -->
    <form method="POST" action="<?= URL_BASE ?>">
        <p>Non hai un account?
            <button type="submit" name="vai_registrazione">Registrati</button>
        </p>
    </form>

<?php elseif ($paginaCorrente === 'registrazione'): ?>

<?php
// ════════════════════════════════════════════════════════════════
// PAGINA: REGISTRAZIONE
// Autore: Duccio Donnini
// Data: 2026
// Versione: 2.1
// Funzionalità: Form di registrazione con nome, cognome, email e password.
//               Genera automaticamente un codice smart card univoco
//               formato SC-XXXXXXXX, lo mostra all'utente e lo salva
//               in utenti.txt insieme all'hash bcrypt della password.
//               Dopo la registrazione mostra il codice e un bottone
//               per tornare al login.
// ════════════════════════════════════════════════════════════════

/**
 * @brief Genera un codice smart card univoco nel formato SC-XXXXXXXX.
 *
 * Genera codici casuali con 8 caratteri alfanumerici maiuscoli e verifica
 * che non esistano già in utenti.txt. Ripete la generazione finché
 * non ottiene un codice effettivamente univoco.
 *
 * @author  Duccio Donnini
 * @version 2.1
 *
 * @return string Codice smart card univoco (es. SC-AB12CD34)
 */
function generaSmartCard() {
    $percorso = __DIR__ . '/utenti.txt';
    do {
        $codice       = 'SC-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        $codiceEsiste = false;
        if (file_exists($percorso)) {
            foreach (file($percorso) as $riga) {
                $cols = explode(';', trim($riga));
                if (count($cols) >= 1 && trim($cols[0]) === $codice) {
                    $codiceEsiste = true;
                    break;
                }
            }
        }
    } while ($codiceEsiste);
    return $codice;
}

/**
 * @brief Controlla se un indirizzo email è già registrato in utenti.txt.
 *
 * Il confronto è case-insensitive: "Mario@email.it" e "mario@email.it"
 * vengono considerati la stessa email.
 *
 * @author  Duccio Donnini
 * @version 2.1
 *
 * @param string $email Indirizzo email da verificare
 * @return bool true se l'email è già presente, false altrimenti
 */
function emailEsiste($email) {
    $percorso = __DIR__ . '/utenti.txt';
    if (!file_exists($percorso)) return false;
    foreach (file($percorso) as $riga) {
        $cols = explode(';', trim($riga));
        if (count($cols) >= 4 && strtolower(trim($cols[3])) === strtolower($email)) return true;
    }
    return false;
}

// ── POST: REGISTRATI ──────────────────────────────────────────────────────────
// Crea un nuovo account. Genera smart card, salva in utenti.txt.
// Parametri: $_POST['nome'], $_POST['cognome'], $_POST['email'], $_POST['password']
// La password viene salvata come hash bcrypt con password_hash()
if (isset($_POST['registrati'])) {
    $nomeReg     = htmlspecialchars(trim($_POST['nome']     ?? ''));
    $cognomeReg  = htmlspecialchars(trim($_POST['cognome']  ?? ''));
    $emailReg    = htmlspecialchars(trim($_POST['email']    ?? ''));
    $passwordReg = trim($_POST['password'] ?? '');

    if (empty($nomeReg) || empty($cognomeReg) || empty($emailReg) || empty($passwordReg)) {
        $_SESSION['errore_reg'] = 'Compila tutti i campi.';
        $_SESSION['pagina']     = 'registrazione';
    } elseif (emailEsiste($emailReg)) {
        $_SESSION['errore_reg'] = 'Questa email è già registrata.';
        $_SESSION['pagina']     = 'registrazione';
    } else {
        $smartCard    = generaSmartCard();
        $hashPassword = password_hash($passwordReg, PASSWORD_DEFAULT);
        // Struttura riga: SMART_CARD;NOME;COGNOME;EMAIL;PASSWORD_HASH
        file_put_contents(
            __DIR__ . '/utenti.txt',
            $smartCard . ';' . $nomeReg . ';' . $cognomeReg . ';' . $emailReg . ';' . $hashPassword . "\n",
            FILE_APPEND
        );
        // Salva la smart card in sessione per mostrarla nella pagina di conferma
        $_SESSION['smart_card_generata'] = $smartCard;
        $_SESSION['pagina']              = 'registrazione';
        $_SESSION['errore_reg']          = '';
    }
    header('Location: ' . URL_BASE);
    exit();
}

// ── POST: VAI AL LOGIN DOPO REGISTRAZIONE ─────────────────────────────────────
// Bottone mostrato dopo la registrazione riuscita
if (isset($_POST['vai_login'])) {
    $_SESSION['pagina']              = 'login';
    $_SESSION['smart_card_generata'] = ''; // svuota il codice dalla sessione
    header('Location: ' . URL_BASE);
    exit();
}
?>

    <h1>Biblioteca Scolastica — Registrazione</h1>

    <?php if (!empty($smartCardGenerata)): ?>

        <!-- Registrazione riuscita: mostra il codice smart card generato -->
        <p>✅ Registrazione completata!</p>
        <p>Il tuo codice Smart Card è: <strong><?= htmlspecialchars($smartCardGenerata) ?></strong></p>
        <p>Conserva questo codice: ti servirà per accedere alla biblioteca.</p>

        <!--
            Bottone per tornare al login dopo aver annotato il codice.
            Il campo vai_login dice al PHP di impostare pagina='login' e
            svuotare smart_card_generata dalla sessione, poi fa redirect.
        -->
        <form method="POST" action="<?= URL_BASE ?>">
            <button type="submit" name="vai_login">Vai al login</button>
        </form>

    <?php else: ?>

        <!-- Messaggio di errore se la registrazione non è andata a buon fine -->
        <?php if (!empty($erroreReg)): ?>
            <p><?= htmlspecialchars($erroreReg) ?></p>
        <?php endif; ?>

        <!--
            Form di registrazione. Metodo POST verso URL base.
            Campi:
              - nome     → nome dell'utente
              - cognome  → cognome dell'utente
              - email    → email univoca
              - password → password (salvata come hash, mai in chiaro)
            Il campo registrati avvia la creazione dell'account.
        -->
        <form method="POST" action="<?= URL_BASE ?>">
            <p>
                <input type="text" name="nome" placeholder="Nome" required>
            </p>
            <p>
                <input type="text" name="cognome" placeholder="Cognome" required>
            </p>
            <p>
                <input type="email" name="email" placeholder="Inserire email" required>
            </p>
            <p>
                <input type="password" name="password" placeholder="Inserire password" required>
            </p>
            <p>
                <!-- Il codice smart card viene generato automaticamente dal PHP -->
                <button type="submit" name="registrati">Registrati</button>
            </p>
        </form>

        <br>

        <!-- Form per tornare al login se si ha già un account -->
        <form method="POST" action="<?= URL_BASE ?>">
            <p>Hai già un account?
                <button type="submit" name="vai_login">Vai al login</button>
            </p>
        </form>

    <?php endif; ?>

<?php else: ?>

    <!-- Barra utente in alto a sinistra, presente in home/risultati/scheda -->
    <div>
        <small>Accesso come <strong><?= $nome . ' ' . $cognome ?></strong></small>
        <form method="POST" action="<?= URL_BASE ?>" style="display:inline;">
            <button type="submit" name="logout">Esci</button>
        </form>
    </div>

    <?php if ($paginaCorrente === 'home'): ?>

<?php
// ════════════════════════════════════════════════════════════════
// PAGINA: HOME
// Autore: Diego Scatizzi
// Data: 2026
// Versione: 3.1
// Funzionalità: Barra di ricerca con autocomplete solo visivo (non cliccabile).
//               I suggerimenti aiutano durante la scrittura ma non navigano.
//               Solo il bottone "Cerca" avvia la ricerca e mostra i risultati.
// ════════════════════════════════════════════════════════════════

/**
 * @brief Endpoint AJAX per l'autocomplete della barra di ricerca.
 *
 * Questo blocco viene eseguito solo quando JavaScript lo chiama in background
 * tramite fetch(). Legge lista-libri.txt e restituisce un array JSON con
 * titolo e autore dei libri che corrispondono alla query (minimo 2 caratteri).
 * La ricerca è case-insensitive e cerca su titolo, autore e ISBN.
 * Restituisce al massimo 8 risultati. Termina con exit() per non produrre HTML.
 *
 * @author  Diego Scatizzi
 * @version 3.1
 *
 * Input  GET['q']        Testo digitato dall'utente (minimo 2 caratteri)
 * Output JSON            Array di oggetti con chiavi 'titolo' e 'autore'
 */
if (isset($_GET['autocomplete'])) {
    $q       = strtolower(trim($_GET['q'] ?? ''));
    $results = [];

    if (strlen($q) >= 2) {
        $file = fopen(__DIR__ . '/lista-libri.txt', 'r');
        if ($file) {
            fgets($file); // salta intestazione
            while (($line = fgets($file)) !== false && count($results) < 8) {
                $cols = explode(';', trim($line));
                if (count($cols) < 4) continue;
                $titolo = $cols[2];
                $autore = $cols[3];
                $isbn   = $cols[7] ?? '';
                if (
                    str_contains(strtolower($titolo), $q) ||
                    str_contains(strtolower($autore), $q) ||
                    str_contains(strtolower($isbn),   $q)
                ) {
                    $results[] = ['titolo' => trim($titolo), 'autore' => trim($autore)];
                }
            }
            fclose($file);
        }
    }

    header('Content-Type: application/json');
    echo json_encode($results);
    exit();
}

// ── POST: CERCA ───────────────────────────────────────────────────────────────
if (isset($_POST['cerca'])) {
    $query = trim($_POST['titolo'] ?? '');
    if (strlen($query) < 2) {
        $_SESSION['errore_ricerca'] = true;
        $_SESSION['pagina']         = 'home';
    } else {
        $_SESSION['query']          = $query;
        $_SESSION['pagina']         = 'risultati';
        $_SESSION['errore_ricerca'] = false;
    }
    header('Location: ' . URL_BASE);
    exit();
}

?>

        <h1>Biblioteca Scolastica</h1>

        <!-- Messaggio di errore se la query è troppo corta -->
        <?php if (!empty($_SESSION['errore_ricerca'])): ?>
            <p>Scrivi almeno 2 caratteri per cercare un libro.</p>
        <?php endif; ?>

        <!--
            Form di ricerca. Metodo POST verso URL base.
            Il bottone "Cerca" attiva la ricerca completa con tabella risultati.
            I suggerimenti sono solo visivi: non cliccando su di essi si naviga.
        -->
        <form method="POST" action="<?= URL_BASE ?>" id="searchForm">
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
                        .then(r => r.json())
                        .then(libri => {
                            if (!libri.length) { box.innerHTML = ''; return; }

                            /*
                             * .map(): trasforma ogni libro in un <div> di testo puro.
                             * Non ha onclick: i suggerimenti sono solo visivi.
                             * cursor:default impedisce il cursore a manina al passaggio.
                             * .join(''): unisce tutti i <div> in una stringa unica
                             */
                            box.innerHTML = libri.map(l =>
                                `<div style="cursor:default;">${l.titolo} — ${l.autore}</div>`
                            ).join('');
                        });

                }, 200); // 200ms di attesa prima di fare la chiamata al server
            });

            /*
             * Evento sul documento intero: chiude i suggerimenti se l'utente
             * clicca in qualsiasi punto della pagina fuori dal campo o dal box.
             */
            document.addEventListener('click', function (e) {
                if (!input.contains(e.target) && !box.contains(e.target)) {
                    box.innerHTML = '';
                }
            });
        </script>

    <?php elseif ($paginaCorrente === 'risultati'): ?>

<?php
// ════════════════════════════════════════════════════════════════
// PAGINA: RISULTATI
// Autore: Diego Scatizzi
// Data: 2026
// Versione: 3.1
// Funzionalità: Mostra in una tabella tutti i libri che corrispondono
//               alla query cercata. Per ogni libro mostra titolo, autore,
//               genere, anno e disponibilità aggiornata dal file.
//               Il bottone "Vedi" apre la scheda dettaglio del libro.
// ════════════════════════════════════════════════════════════════

/**
 * @brief Cerca libri nel catalogo in base a un testo libero.
 *
 * Scorre lista-libri.txt e restituisce tutti i libri il cui titolo,
 * autore, genere o ISBN contengono la stringa cercata.
 * La ricerca è case-insensitive.
 *
 * @author  Diego Scatizzi
 * @version 3.1
 *
 * @param string $q Testo da cercare (minimo 2 caratteri consigliati)
 * @return array Array di array associativi, ciascuno con le chiavi:
 *               'id', 'titolo', 'autore', 'genere', 'anno', 'disponibile'
 */
function cercaLibriPerTesto($q) {
    $q       = strtolower(trim($q));
    $results = [];
    $file    = fopen(__DIR__ . '/lista-libri.txt', 'r');
    if (!$file) return $results;
    fgets($file);
    while (($line = fgets($file)) !== false) {
        $cols = explode(';', trim($line));
        if (count($cols) < 11) continue;
        $titolo = trim($cols[2]);
        $autore = trim($cols[3]);
        $genere = trim($cols[4]);
        $isbn   = trim($cols[7]);
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

// ── POST: VAI SCHEDA DA RISULTATI ─────────────────────────────────────────────
if (isset($_POST['vai_scheda_da_risultati']) && !empty($_POST['id_libro'])) {
    $_SESSION['id_libro'] = htmlspecialchars(trim($_POST['id_libro']));
    $_SESSION['pagina']   = 'scheda';
    header('Location: ' . URL_BASE);
    exit();
}

// ── POST: TORNA HOME ──────────────────────────────────────────────────────────
if (isset($_POST['torna_home'])) {
    $_SESSION['pagina']   = 'home';
    $_SESSION['id_libro'] = '';
    $_SESSION['query']    = '';
    header('Location: ' . URL_BASE);
    exit();
}

// Esegue la ricerca per popolare la tabella
if (!empty($_SESSION['query'])) {
    $risultati = cercaLibriPerTesto($_SESSION['query']);
}
?>

        <form method="POST" action="<?= URL_BASE ?>" style="display:inline;">
            <button type="submit" name="torna_home">← Nuova ricerca</button>
        </form>

        <h1>Risultati per: "<?= htmlspecialchars($_SESSION['query']) ?>"</h1>

        <?php if (empty($risultati)): ?>
            <p>Nessun libro trovato. Prova con un altro termine.</p>
        <?php else: ?>

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
                            <form method="POST" action="<?= URL_BASE ?>">
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

<?php
// ════════════════════════════════════════════════════════════════
// PAGINA: SCHEDA LIBRO
// Autore: Matteo Procino
// Data: 2026
// Versione: 2.1
// Funzionalità: Mostra tutte le informazioni del libro selezionato.
//               Se disponibile mostra il bottone Noleggia che:
//                 - aggiorna DISPONIBILE=NO nel file lista-libri.txt
//                 - scrive le date nelle colonne DATA_INIZIO e DATA_FINE
//               Se non disponibile mostra le date di inizio e fine prestito
//               lette direttamente da lista-libri.txt.
// ════════════════════════════════════════════════════════════════

/**
 * @brief Legge tutti i prestiti attivi dal catalogo lista-libri.txt.
 *
 * Scorre lista-libri.txt e raccoglie solo i libri che hanno date di
 * prestito valorizzate (colonne 11 e 12 diverse da NULL).
 *
 * @author  Matteo Procino
 * @version 2.1
 *
 * @return array Array associativo indicizzato per ID libro:
 *               [ ID_LIBRO => ['inizio' => 'dd-mm-yyyy', 'fine' => 'dd-mm-yyyy'] ]
 */
function leggiPrestiti() {
    $prestiti = [];
    $file = fopen(__DIR__ . '/lista-libri.txt', 'r');
    if (!$file) return $prestiti;
    fgets($file);
    while (($line = fgets($file)) !== false) {
        $cols = explode(';', trim($line));
        if (count($cols) < 13) continue;
        $dataInizio = trim($cols[11]);
        $dataFine   = trim($cols[12]);
        if (strtoupper($dataInizio) === 'NULL' || strtoupper($dataFine) === 'NULL') continue;
        $prestiti[trim($cols[1])] = ['inizio' => $dataInizio, 'fine' => $dataFine];
    }
    fclose($file);
    return $prestiti;
}

/**
 * @brief Cerca un libro nel catalogo tramite il suo ID univoco.
 *
 * Scorre lista-libri.txt e restituisce i dati completi del libro
 * la cui colonna ID (indice 1) corrisponde al valore cercato.
 *
 * @author  Matteo Procino
 * @version 2.1
 *
 * @param string $idCercato ID del libro da cercare (es. LIB001)
 * @return array|null Array associativo con le chiavi 'id', 'titolo', 'autore',
 *                    'genere', 'anno', 'editore', 'isbn', 'lingua', 'pagine',
 *                    'disponibile' (bool), oppure null se non trovato
 */
function cercaLibroPerID($idCercato) {
    $file = fopen(__DIR__ . '/lista-libri.txt', 'r');
    if (!$file) return null;
    fgets($file);
    while (($line = fgets($file)) !== false) {
        $cols = explode(';', trim($line));
        if (count($cols) < 11) continue;
        if (trim($cols[1]) === $idCercato) {
            fclose($file);
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
                'disponibile' => strtoupper(trim($cols[10])) === 'SI',
            ];
        }
    }
    fclose($file);
    return null;
}

/**
 * @brief Aggiorna la disponibilità di un libro e le date di prestito in lista-libri.txt.
 *
 * Legge l'intero file, trova la riga con l'ID specificato, aggiorna
 * il campo DISPONIBILE (colonna 10) e, se fornite, le date di inizio
 * e fine prestito (colonne 11 e 12). Riscrive il file aggiornato.
 *
 * @author  Matteo Procino
 * @version 2.1
 *
 * @param string      $idLibro    ID del libro da aggiornare (es. LIB001)
 * @param string      $nuovoVal   Nuovo valore di disponibilità: 'SI' o 'NO'
 * @param string|null $dataInizio Data inizio prestito nel formato d-m-Y (opzionale)
 * @param string|null $dataFine   Data fine prestito nel formato d-m-Y (opzionale)
 * @return bool true se l'aggiornamento è riuscito, false se il libro non esiste
 *              o se la scrittura del file fallisce
 */
function aggiornaDisponibilita($idLibro, $nuovoVal, $dataInizio = null, $dataFine = null) {
    $percorso = __DIR__ . '/lista-libri.txt';
    $righe    = file($percorso);
    if ($righe === false) return false;
    $trovato = false;
    foreach ($righe as $i => $riga) {
        $cols = explode(';', trim($riga));
        if (count($cols) < 11) continue;
        if (trim($cols[1]) === $idLibro) {
            $cols[10] = $nuovoVal;
            if ($dataInizio !== null) $cols[11] = $dataInizio;
            if ($dataFine   !== null) $cols[12] = $dataFine;
            $righe[$i] = implode(';', $cols) . "\n";
            $trovato   = true;
            break;
        }
    }
    if (!$trovato) return false;
    return file_put_contents($percorso, implode('', $righe)) !== false;
}

// ── POST: TORNA HOME ──────────────────────────────────────────────────────────
if (isset($_POST['torna_home'])) {
    $_SESSION['pagina']   = 'home';
    $_SESSION['id_libro'] = '';
    $_SESSION['query']    = '';
    header('Location: ' . URL_BASE);
    exit();
}

// ── POST: TORNA AI RISULTATI ──────────────────────────────────────────────────
if (isset($_POST['torna_risultati'])) {
    $_SESSION['pagina'] = 'risultati';
    header('Location: ' . URL_BASE);
    exit();
}

// ── POST: NOLEGGIA ────────────────────────────────────────────────────────────
// Aggiorna DISPONIBILE=NO in lista-libri.txt e scrive le date del prestito.
// Parametro in ingresso: $_POST['id_noleggio'] → ID del libro da noleggiare
if (isset($_POST['noleggia'])) {
    $idNoleggio = htmlspecialchars(trim($_POST['id_noleggio'] ?? ''));
    if ($idNoleggio !== '') {
        aggiornaDisponibilita(
            $idNoleggio,
            'NO',
            date('d-m-Y'),                        // data inizio: oggi
            date('d-m-Y', strtotime('+30 days'))  // data fine: tra 30 giorni
        );
        $_SESSION['noleggiato'] = $idNoleggio; // salva l'ID per la conferma
    }
    $_SESSION['pagina'] = 'scheda';
    header('Location: ' . URL_BASE);
    exit();
}

// Controlla se c'è appena stato un noleggio confermato
if (!empty($_SESSION['noleggiato'])) {
    $noleggiato             = true;
    $_SESSION['noleggiato'] = ''; // svuota dopo aver letto
}

// Carica libro e prestiti; se l'ID non esiste torna alla home
if (!empty($_SESSION['id_libro'])) {
    $prestiti = leggiPrestiti();
    $libro    = cercaLibroPerID($_SESSION['id_libro']);
    if ($libro === null) {
        $_SESSION['pagina'] = 'home';
        header('Location: ' . URL_BASE);
        exit();
    }
}
?>

        <form method="POST" action="<?= URL_BASE ?>" style="display:inline;">
            <button type="submit" name="torna_risultati">← Torna ai risultati</button>
        </form>
        <form method="POST" action="<?= URL_BASE ?>" style="display:inline;">
            <button type="submit" name="torna_home">⌂ Nuova ricerca</button>
        </form>

        <h1><?= htmlspecialchars($libro['titolo']) ?></h1>
        <p>di <strong><?= htmlspecialchars($libro['autore']) ?></strong></p>

        <hr>

        <table border="1" cellpadding="6">
            <tr><td><strong>ISBN</strong></td><td><?= htmlspecialchars($libro['isbn']) ?></td></tr>
            <tr><td><strong>Anno di pubblicazione</strong></td><td><?= htmlspecialchars($libro['anno']) ?></td></tr>
            <tr><td><strong>Genere</strong></td><td><?= htmlspecialchars($libro['genere']) ?></td></tr>
            <tr><td><strong>Casa editrice</strong></td><td><?= htmlspecialchars($libro['editore']) ?></td></tr>
            <tr><td><strong>Lingua</strong></td><td><?= htmlspecialchars($libro['lingua']) ?></td></tr>
            <tr><td><strong>Numero di pagine</strong></td><td><?= htmlspecialchars($libro['pagine']) ?></td></tr>
        </table>

        <hr>

        <?php if ($noleggiato): ?>

            <!-- Messaggio di conferma: mostrato subito dopo il click su Noleggia-->
            <p>✅ Noleggio confermato! Il libro è stato prenotato a nome di
                <strong><?= $nome . ' ' . $cognome ?></strong>.
            </p>
        <?php
            $prestitiAggiornati = leggiPrestiti();
            $idCorrente = $_SESSION['id_libro'];
            if (isset($prestitiAggiornati[$idCorrente])):
        ?>
            <p>Data inizio noleggio: <strong><?= $prestitiAggiornati[$idCorrente]['inizio'] ?></strong></p>
            <p>Data restituzione prevista: <strong><?= $prestitiAggiornati[$idCorrente]['fine'] ?></strong></p>
        <?php endif; ?>
            <p>Potrai ritirarlo in biblioteca entro 3 giorni.</p>

        <?php elseif ($libro['disponibile']): ?>

            <!-- Libro disponibile e non appena noleggiato: mostra il bottone -->
            <p><strong>Disponibilità:</strong> ✅ Disponibile</p>

            <form method="POST" action="<?= URL_BASE ?>">
                <input type="hidden" name="id_noleggio" value="<?= htmlspecialchars($libro['id']) ?>">
                <button type="submit" name="noleggia">Noleggia</button>
            </form>

        <?php else: ?>

                <!-- Libro non disponibile -->
                <p><strong>Disponibilità:</strong> ❌ Non disponibile</p>

            <?php if (isset($prestiti[$libro['id']])): ?>
                <p>Questo libro è attualmente in prestito:</p>
                <ul>
                    <li>Data inizio prestito: <strong><?= $prestiti[$libro['id']]['inizio'] ?></strong></li>
                    <li>Data fine prestito prevista: <strong><?= $prestiti[$libro['id']]['fine'] ?></strong></li>
                </ul>
                <p>Ricontrolla la disponibilità dopo la data di fine prestito.</p>
            <?php else: ?>
                <p>Questo libro non è al momento disponibile.</p>
            <?php endif; ?>

        <?php endif; ?>

    <?php endif; ?>

<?php endif; ?>

</body>
</html>