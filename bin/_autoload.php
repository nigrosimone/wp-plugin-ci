<?php
/**
 * Carica le funzioni condivise, sia quando il pacchetto e' installato come
 * dipendenza (vendor/nigrosimone/wp-plugin-ci/bin/...) sia quando gli script
 * vengono lanciati direttamente da un checkout del repository.
 */

$candidates = array(
    // Pacchetto installato: vendor/nigrosimone/wp-plugin-ci/bin -> src
    __DIR__ . '/../src/Plugin.php',
    // Checkout diretto del repository
    dirname(__DIR__) . '/src/Plugin.php',
);

foreach ($candidates as $file) {
    if (is_file($file)) {
        require_once $file;
        return;
    }
}

fwrite(STDERR, "ERRORE: src/Plugin.php non trovato: installazione di wp-plugin-ci incompleta.\n");
exit(1);
