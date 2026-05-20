<?php
/*
 * ════════════════════════════════════════════════════════════════
 * PAGINA: SCHEDA LIBRO
 * Autore: Matteo Procino
 * Data: 2026
 * Versione: 2.0
 * Funzionalità: Mostra tutte le informazioni del libro selezionato.
 *               Se disponibile mostra il bottone Noleggia che:
 *                 - aggiorna DISPONIBILE=NO nel file lista-libri.txt
 *                 - scrive le date nelle colonne DATA_INIZIO e DATA_FINE di lista-libri.txt
 *               Se non disponibile mostra le date di inizio e fine prestito
 *               lette direttamente da lista-libri.txt (colonne DATA_INIZIO e DATA_FINE).
 * ════════════════════════════════════════════════════════════════
 */
?>
 
    <!-- Bottoni di navigazione: torna ai risultati o direttamente alla home -->
    <form method="POST" action="" style="display:inline;">
        <button type="submit" name="torna_risultati">← Torna ai risultati</button>
    </form>
    <form method="POST" action="" style="display:inline;">
        <button type="submit" name="torna_home">⌂ Nuova ricerca</button>
    </form>
 
    <h1><?= htmlspecialchars($libro['titolo']) ?></h1>
    <p>di <strong><?= htmlspecialchars($libro['autore']) ?></strong></p>
 
    <hr>
 
    <!-- Tabella con tutte le informazioni del libro -->
    <table border="1" cellpadding="6">
        <tr>
            <td><strong>ISBN</strong></td>
            <td><?= htmlspecialchars($libro['isbn']) ?></td>
        </tr>
        <tr>
            <td><strong>Anno di pubblicazione</strong></td>
            <td><?= htmlspecialchars($libro['anno']) ?></td>
        </tr>
        <tr>
            <td><strong>Genere</strong></td>
            <td><?= htmlspecialchars($libro['genere']) ?></td>
        </tr>
        <tr>
            <td><strong>Casa editrice</strong></td>
            <td><?= htmlspecialchars($libro['editore']) ?></td>
        </tr>
        <tr>
            <td><strong>Lingua</strong></td>
            <td><?= htmlspecialchars($libro['lingua']) ?></td>
        </tr>
        <tr>
            <td><strong>Numero di pagine</strong></td>
            <td><?= htmlspecialchars($libro['pagine']) ?></td>
        </tr>
    </table>
 
    <hr>
 
    <!-- Sezione disponibilità e noleggio -->
    <?php if ($libro['disponibile']): ?>
 
        <p><strong>Disponibilità:</strong> ✅ Disponibile</p>
 
        <?php if ($noleggiato): ?>
 
            <!-- Messaggio di conferma mostrato subito dopo il click su Noleggia -->
            <p>✅ Noleggio confermato! Il libro è stato prenotato a nome di
               <strong><?= $nome . ' ' . $cognome ?></strong>.
            </p>
            <?php
            // Recupera le date appena scritte in lista-libri.txt per mostrarle nella conferma
            $prestitiAggiornati = leggiPrestiti();
            $idCorrente         = $_SESSION['id_libro'];
            if (isset($prestitiAggiornati[$idCorrente])):
            ?>
            <p>Data inizio noleggio: <strong><?= $prestitiAggiornati[$idCorrente]['inizio'] ?></strong></p>
            <p>Data restituzione prevista: <strong><?= $prestitiAggiornati[$idCorrente]['fine'] ?></strong></p>
            <?php endif; ?>
            <p>Potrai ritirarlo in biblioteca entro 3 giorni.</p>
 
        <?php else: ?>
 
            <!--
                Form noleggio.
                Metodo POST verso lo stesso file.
                id_noleggio passa l'ID del libro al PHP per aggiornare il file.
            -->
            <form method="POST" action="">
                <input type="hidden" name="id_noleggio" value="<?= htmlspecialchars($libro['id']) ?>">
                <button type="submit" name="noleggia">Noleggia</button>
            </form>
 
        <?php endif; ?>
 
    <?php else: ?>
 
        <!-- Libro non disponibile: legge le date del prestito da lista-libri.txt -->
        <p><strong>Disponibilità:</strong> ❌ Non disponibile</p>
 
        <?php if (isset($prestiti[$libro['id']])): ?>
 
            <!-- Date di prestito trovate in lista-libri.txt -->
            <p>Questo libro è attualmente in prestito:</p>
            <ul>
                <li>Data inizio prestito: <strong><?= $prestiti[$libro['id']]['inizio'] ?></strong></li>
                <li>Data fine prestito prevista: <strong><?= $prestiti[$libro['id']]['fine'] ?></strong></li>
            </ul>
            <p>Torna a controllare dopo il <?= $prestiti[$libro['id']]['fine'] ?>.</p>
 
        <?php else: ?>
 
            <!-- Il libro è segnato come non disponibile nel file ma non ha date registrate -->
            <p>Questo libro non è al momento disponibile.</p>
 
        <?php endif; ?>
 
    <?php endif; ?>
 
<?php endif; ?>
 
</body>
</html>
