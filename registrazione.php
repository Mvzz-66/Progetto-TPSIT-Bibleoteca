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

// ── FUNZIONE: generaSmartCard() ───────────────────────────────────────────────
// Parametro in ingresso: nessuno
// Valore di ritorno: stringa con codice smart card univoco formato SC-XXXXXXXX
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

// ── FUNZIONE: emailEsiste() ───────────────────────────────────────────────────
// Parametro in ingresso: $email da controllare
// Valore di ritorno: true se già registrata, false altrimenti
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
