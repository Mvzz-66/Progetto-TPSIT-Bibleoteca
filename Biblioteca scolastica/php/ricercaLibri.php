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

// ── AJAX: AUTOCOMPLETE ────────────────────────────────────────────────────────
// Questo blocco si attiva SOLO quando JavaScript lo chiama in background.
// Va gestito prima dei POST perché usa exit() per fermare l'esecuzione.
// Parametro in ingresso: $_GET['q'] → testo digitato dall'utente
// Valore di ritorno: JSON array con titolo e autore dei libri trovati
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

// ── FUNZIONE: cercaLibriPerTesto() ────────────────────────────────────────────
// Parametro in ingresso: $q → testo da cercare
// Valore di ritorno: array di libri trovati (può essere vuoto)
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
