<?php
/**
 * Funzioni condivise dagli script di rilascio.
 *
 * Gli script girano sia dal repository del plugin (in CI) sia da
 * vendor/nigrosimone/wp-plugin-ci/bin (in locale), quindi non possono dedurre
 * la radice del plugin dalla propria posizione: la ricavano dalla directory di
 * lavoro, che in entrambi i casi e' la radice del plugin.
 */

/**
 * Radice del plugin su cui operare.
 *
 * PLUGIN_ROOT permette di forzarla nei casi in cui la directory di lavoro non
 * coincida (per esempio uno script lanciato da un'altra cartella).
 */
function wpci_plugin_root() {
    $override = getenv('PLUGIN_ROOT');
    if (is_string($override) && $override !== '' && is_dir($override)) {
        return rtrim($override, "/\\");
    }
    $cwd = getcwd();
    if ($cwd === false) {
        fwrite(STDERR, "ERRORE: impossibile determinare la directory di lavoro.\n");
        exit(1);
    }
    return rtrim($cwd, "/\\");
}

/**
 * Percorso del file principale del plugin, cioe' quello con l'header
 * "Plugin Name:". Restituisce null se non lo trova.
 */
function wpci_find_plugin_file($root) {
    $candidates = glob($root . '/*.php');
    if (!is_array($candidates)) {
        return null;
    }
    sort($candidates);
    foreach ($candidates as $file) {
        $head = (string) file_get_contents($file, false, null, 0, 8192);
        if (strpos($head, 'Plugin Name:') !== false) {
            return $file;
        }
    }
    return null;
}

/**
 * Estrae un campo dell'header, gestendo sia " Version:" che " * Version:".
 * I plugin di questa famiglia usano entrambi gli stili.
 */
function wpci_header_field($contents, $field) {
    $pattern = '/^[ \t\/*#@]*' . preg_quote($field, '/') . ':\s*(.+)$/mi';
    if (preg_match($pattern, $contents, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

/**
 * Legge un file normalizzando le fine riga a LF e togliendo un eventuale BOM.
 */
function wpci_read_lf($path) {
    $raw = (string) file_get_contents($path);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    return str_replace(array("\r\n", "\r"), "\n", $raw);
}

/**
 * Termina con un errore su STDERR.
 */
function wpci_fail($message) {
    fwrite(STDERR, 'ERRORE: ' . $message . "\n");
    exit(1);
}

/**
 * Radice del plugin, oppure errore se non contiene un plugin riconoscibile.
 */
function wpci_require_plugin_file($root) {
    $file = wpci_find_plugin_file($root);
    if ($file === null) {
        wpci_fail("nessun file con header 'Plugin Name:' trovato in $root.");
    }
    return $file;
}
