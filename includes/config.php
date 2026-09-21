<?php
/**
 * Session start + SQLite connection (with corrupted-file recovery).
 * Creating collection.db from scratch when it does not exist yet is
 * handled by PDO itself the moment it opens the sqlite: DSN below -
 * nothing else needs to run first for a brand new deploy.
 */

session_start();

// Initialize Database Connection
$db_file = dirname(__DIR__) . '/collection.db';
$db = new PDO("sqlite:" . $db_file);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("PRAGMA busy_timeout = 5000;");

// Validate the DB file is genuinely readable. A missing file is created automatically
// by the PDO/SQLite driver above, but an existing-yet-corrupted or invalid file (bad
// bytes, truncated write, wrong format, etc.) would otherwise fail silently later.
// If it's not a valid SQLite database, back it up out of the way and start fresh.
try {
    $db->query("PRAGMA integrity_check")->fetchColumn();
} catch (Exception $e) {
    $db = null;
    if (file_exists($db_file)) {
        @rename($db_file, $db_file . '.corrupted-' . date('Y-m-d_His') . '.bak');
    }
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA busy_timeout = 5000;");
}
