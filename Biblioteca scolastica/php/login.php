<?php
/*
<!--
 *   COPYRIGHT (c) 2026 MySoft snc. All rights Res.
 *   Biblioteca scolastica
 *   @author Massimiliano Drago - Diego Scatizzi - Duccio Donnini -
 *                  - Gabriele Severi - Matteo Procino
 *   @version 4.1 2026-05-23
 *   Funzionalità: File unico che gestisce le pagine virtuali:
 *               1. login        → accesso con codice smart card e password
 *               2. registrazione → creazione account con generazione smart card
 *               3. home         → barra di ricerca con autocomplete solo visivo
 *               4. risultati    → tabella con i libri trovati dalla ricerca
 *               5. scheda       → dettaglio libro con disponibilità e noleggio
 *               Gli utenti sono salvati in utenti.txt.
 *               La navigazione tra le pagine avviene tramite $_SESSION['pagina'].
 *               Ogni azione POST termina con un redirect (PRG pattern) per
 *               evitare che parametri GET residui nella URL causino navigazioni
 *               indesiderate al ricaricamento della pagina.
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

// ── FUNZIONE: cercaUtente() ───────────────────────────────────────────────────
// Parametri in ingresso: $smartCard, $password (in chiaro)
// Valore di ritorno: array con dati utente oppure null se non trovato
// Struttura utenti.txt: SMART_CARD;NOME;COGNOME;EMAIL;PASSWORD_HASH
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
