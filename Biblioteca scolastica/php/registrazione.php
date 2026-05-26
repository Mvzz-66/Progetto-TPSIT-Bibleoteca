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

// ── FUNZIONE: leggiPrestiti() ─────────────────────────────────────────────────
// Parametro in ingresso: nessuno
// Valore di ritorno: array associativo [ ID_LIBRO => ['inizio' => ..., 'fine' => ...] ]
// Legge le date di prestito direttamente da lista-libri.txt, colonne 11 e 12.
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

// ── FUNZIONE: cercaLibroPerID() ───────────────────────────────────────────────
// Parametro in ingresso: $idCercato → ID del libro (es. LIB001)
// Valore di ritorno: array associativo con i dati del libro, oppure null
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

// ── FUNZIONE: aggiornaDisponibilita() ─────────────────────────────────────────
// Parametri in ingresso: $idLibro, $nuovoVal ('SI'/'NO'), $dataInizio, $dataFine
// Valore di ritorno: true se riuscito, false altrimenti
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
