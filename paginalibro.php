<?php
// ── Dati del libro (puoi modificarli o recuperarli da un database) ──────────
$libro = [
    'titolo'        => 'Il Nome della Rosa',
    'autore'        => 'Umberto Eco',
    'isbn'          => '978-88-452-9204-4',
    'anno'          => '1980',
    'lingua'        => 'Italiano',
    'genere'        => 'Romanzo storico / Giallo',
    'pagine'        => '502',
    'editore'       => 'Bompiani',
    'prezzo'        => '18.90',
    'disponibile'   => true,
];

// ── Gestione acquisto via POST ────────────────────────────────────────────────
$acquistato = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acquista'])) {
    $acquistato = true;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($libro['titolo']) ?> — Scheda Libro</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Lato:wght@300;400;700&display=swap" rel="stylesheet"/>
</head>
<body>

<div class="page-bg"></div>

<main class="container">
  <div class="book-card">

    <!-- ── Colonna copertina ── -->
    <div class="book-cover-col">
      <div class="book-cover">
        <div class="cover-texture"></div>
        <div class="cover-title"><?= htmlspecialchars($libro['titolo']) ?></div>
        <div class="cover-author"><?= htmlspecialchars($libro['autore']) ?></div>
      </div>
      <div class="badge <?= $libro['disponibile'] ? 'badge--green' : 'badge--red' ?>">
        <?= $libro['disponibile'] ? 'Disponibile' : 'Esaurito' ?>
      </div>
    </div>

    <!-- ── Colonna informazioni ── -->
    <div class="book-info-col">
      <p class="label-top">Scheda libro</p>
      <h1 class="book-title"><?= htmlspecialchars($libro['titolo']) ?></h1>
      <p class="book-author">di <span><?= htmlspecialchars($libro['autore']) ?></span></p>

      <div class="divider"></div>

      <ul class="info-list">
        <li>
          <span class="info-label">ISBN</span>
          <span class="info-value"><?= htmlspecialchars($libro['isbn']) ?></span>
        </li>
        <li>
          <span class="info-label">Anno di pubblicazione</span>
          <span class="info-value"><?= htmlspecialchars($libro['anno']) ?></span>
        </li>
        <li>
          <span class="info-label">Lingua</span>
          <span class="info-value"><?= htmlspecialchars($libro['lingua']) ?></span>
        </li>
        <li>
          <span class="info-label">Genere</span>
          <span class="info-value"><?= htmlspecialchars($libro['genere']) ?></span>
        </li>
        <li>
          <span class="info-label">Numero di pagine</span>
          <span class="info-value"><?= htmlspecialchars($libro['pagine']) ?></span>
        </li>
        <li>
          <span class="info-label">Casa editrice</span>
          <span class="info-value"><?= htmlspecialchars($libro['editore']) ?></span>
        </li>
      </ul>

      <div class="divider"></div>

      <div class="price-row">
        <span class="price-label">Prezzo</span>
        <span class="price">€ <?= number_format((float)$libro['prezzo'], 2, ',', '.') ?></span>
      </div>

      <?php if ($acquistato): ?>
        <!-- Messaggio di conferma acquisto -->
        <div class="grazie-msg visible">
          <span class="grazie-icon">✅</span>
          <p>Grazie per l'acquisto!<br/>
             <strong><?= htmlspecialchars($libro['titolo']) ?></strong> è stato aggiunto al tuo ordine.</p>
        </div>
      <?php else: ?>
        <!-- Form acquisto -->
        <form method="POST" action="">
          <?php if ($libro['disponibile']): ?>
            <button type="submit" name="acquista" class="btn-acquista">
              <span class="btn-icon">🛒</span>
              <span class="btn-text">Acquista ora</span>
            </button>
          <?php else: ?>
            <button type="button" class="btn-acquista btn-acquista--disabled" disabled>
              <span class="btn-icon">✖</span>
              <span class="btn-text">Non disponibile</span>
            </button>
          <?php endif; ?>
        </form>
      <?php endif; ?>

    </div><!-- /book-info-col -->
  </div><!-- /book-card -->
</main>

</body>
</html>
