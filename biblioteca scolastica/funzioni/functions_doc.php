<?php
/**
 * @file functions_doc.php
 * @brief Estratto delle funzioni di biblioteca.php — solo per Doxygen.
 *
 * Questo file NON va in produzione.
 * Contiene esclusivamente le dichiarazioni delle funzioni con i
 * rispettivi blocchi di documentazione Doxygen, senza HTML né
 * logica di navigazione. Doxygen lo parsa senza problemi.
 *
 * File originale: @ref biblioteca.php
 *
 * Pagine virtuali gestite tramite $_SESSION['pagina']:
 * -# <b>login</b>         — accesso con codice smart card e password
 * -# <b>registrazione</b> — creazione account con generazione smart card automatica
 * -# <b>home</b>          — barra di ricerca con autocomplete visivo (AJAX)
 * -# <b>risultati</b>     — tabella con i libri trovati dalla ricerca
 * -# <b>scheda</b>        — dettaglio libro con disponibilità e noleggio
 *
 * Struttura file dati:
 * - <tt>utenti.txt</tt>     : SMART_CARD;NOME;COGNOME;EMAIL;PASSWORD_HASH
 * - <tt>lista-libri.txt</tt>: N;ID;TITOLO;AUTORE;GENERE;ANNO;EDITORE;ISBN;LINGUA;PAGINE;DISPONIBILE;DATA_INIZIO;DATA_FINE
 *
 * @copyright COPYRIGHT (c) 2026 MySoft snc. All rights reserved.
 * @author    Massimiliano Drago
 * @author    Diego Scatizzi
 * @author    Duccio Donnini
 * @author    Gabriele Severi
 * @author    Matteo Procino
 * @version   4.1
 * @date      2026-05-23
 */

// ════════════════════════════════════════════════════════════════
// PAGINA: LOGIN
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

// ════════════════════════════════════════════════════════════════
// PAGINA: REGISTRAZIONE
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

// ════════════════════════════════════════════════════════════════
// PAGINA: HOME  (endpoint AJAX autocomplete)
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
 * @param string $_GET['q']  Testo digitato dall'utente (minimo 2 caratteri)
 * @return void  Risponde direttamente con JSON: array di oggetti {titolo, autore}
 *
 * @note Attivato dalla presenza del parametro GET <tt>autocomplete</tt>.
 */
function autocompleteAjax() {
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

// ════════════════════════════════════════════════════════════════
// PAGINA: RISULTATI
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

// ════════════════════════════════════════════════════════════════
// PAGINA: SCHEDA LIBRO
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
