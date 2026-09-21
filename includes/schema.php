<?php
/**
 * Schema creation + migrations (runs on every request; every step is
 * idempotent, so this is exactly what builds the database from
 * scratch on a brand new deploy - CREATE TABLE IF NOT EXISTS, then
 * ALTER TABLE upgrades for older databases, then one-time seeded
 * data). Also reads the app name and the per-request auth/flash
 * state ($is_admin, $flash_message) used by everything after it.
 */

// Safe Bootstrap & Column Migration (seamlessly updates schema without data loss)
// Robust Auto-Bootstrap & Self-Healing Database Initialization
try {
    $db->exec("
    CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        username TEXT NOT NULL UNIQUE, 
        password_hash TEXT NOT NULL, 
        role TEXT NOT NULL DEFAULT 'admin',
        is_default_password INTEGER DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS platforms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        code TEXT NOT NULL UNIQUE,
        track_total INTEGER DEFAULT 0,
        has_release_no INTEGER DEFAULT 0,
        has_line_series INTEGER DEFAULT 0,
        has_legacy INTEGER DEFAULT 0,
        has_status INTEGER DEFAULT 1,
        has_cib INTEGER DEFAULT 0,
        has_region INTEGER DEFAULT 0,
        has_media_type INTEGER DEFAULT 0,
        has_notes INTEGER DEFAULT 1,
        show_title INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS games (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        platform_id INTEGER NOT NULL,
        release_no INTEGER,
        title TEXT NOT NULL,
        line_series TEXT,
        is_legacy INTEGER DEFAULT 0,
        is_owned INTEGER DEFAULT 1,
        is_cib INTEGER DEFAULT 1,
        region TEXT DEFAULT 'EUR (PAL)',
        media_type TEXT DEFAULT 'Physical',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(platform_id) REFERENCES platforms(id) ON DELETE CASCADE
    );
    ");

    // Dynamic migrations for platforms
    $p_cols = $db->query("PRAGMA table_info(platforms)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('has_status', $p_cols)) $db->exec("ALTER TABLE platforms ADD COLUMN has_status INTEGER DEFAULT 1");
    if (!in_array('has_cib', $p_cols)) $db->exec("ALTER TABLE platforms ADD COLUMN has_cib INTEGER DEFAULT 0");
    if (!in_array('has_region', $p_cols)) $db->exec("ALTER TABLE platforms ADD COLUMN has_region INTEGER DEFAULT 0");
    if (!in_array('has_media_type', $p_cols)) $db->exec("ALTER TABLE platforms ADD COLUMN has_media_type INTEGER DEFAULT 0");

    // Dynamic migrations for games
    $g_cols = $db->query("PRAGMA table_info(games)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('is_cib', $g_cols)) $db->exec("ALTER TABLE games ADD COLUMN is_cib INTEGER DEFAULT 1");
    if (!in_array('region', $g_cols)) $db->exec("ALTER TABLE games ADD COLUMN region TEXT DEFAULT 'EUR (PAL)'");
    if (!in_array('media_type', $g_cols)) $db->exec("ALTER TABLE games ADD COLUMN media_type TEXT DEFAULT 'Physical'");

    // Dynamic migrations for users
    $u_cols = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('is_default_password', $u_cols)) $db->exec("ALTER TABLE users ADD COLUMN is_default_password INTEGER DEFAULT 1");

    // Auto-seed default admin if none exists (required so the app is always accessible;
    // this is not "demo" data, just the one account needed to sign in)
    $admin_count = $db->query("SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();
    if ($admin_count == 0) {
        $default_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $db->prepare("INSERT OR IGNORE INTO users (id, username, password_hash, role, is_default_password) VALUES (1, 'admin', ?, 'admin', 1)")->execute([$default_hash]);
    }

} catch (Exception $e) {
    // Log or handle bootstrap error if needed
}

// Field library tables + one-time upgrade from the old hard-coded has_* platform switches.
// The upgrade runs once (guarded by a settings flag), so deleting a built-in field later
// is never undone behind your back.
try {
    $db->exec("
    CREATE TABLE IF NOT EXISTS fields (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        field_key TEXT NOT NULL UNIQUE,
        label TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        field_type TEXT NOT NULL DEFAULT 'text',
        options TEXT,
        is_builtin INTEGER NOT NULL DEFAULT 0,
        sort_order INTEGER NOT NULL DEFAULT 0,
        sortable INTEGER NOT NULL DEFAULT 1,
        quick_toggle INTEGER NOT NULL DEFAULT 0,
        colors TEXT,
        bold INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS platform_fields (
        platform_id INTEGER NOT NULL,
        field_id INTEGER NOT NULL,
        PRIMARY KEY (platform_id, field_id)
    );
    CREATE TABLE IF NOT EXISTS game_field_values (
        game_id INTEGER NOT NULL,
        field_id INTEGER NOT NULL,
        value TEXT,
        PRIMARY KEY (game_id, field_id)
    );
    CREATE TABLE IF NOT EXISTS platform_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        track_total INTEGER NOT NULL DEFAULT 0,
        field_ids TEXT NOT NULL DEFAULT '[]',
        is_builtin INTEGER NOT NULL DEFAULT 0,
        default_name TEXT
    );
    CREATE TABLE IF NOT EXISTS kpis (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        label TEXT NOT NULL,
        color TEXT NOT NULL DEFAULT 'slate',
        calc_type TEXT NOT NULL DEFAULT 'count',
        n1_field TEXT NOT NULL DEFAULT '', n1_op TEXT NOT NULL DEFAULT '', n1_value TEXT NOT NULL DEFAULT '',
        n2_field TEXT NOT NULL DEFAULT '', n2_op TEXT NOT NULL DEFAULT '', n2_value TEXT NOT NULL DEFAULT '',
        d1_field TEXT NOT NULL DEFAULT '', d1_op TEXT NOT NULL DEFAULT '', d1_value TEXT NOT NULL DEFAULT '',
        d2_field TEXT NOT NULL DEFAULT '', d2_op TEXT NOT NULL DEFAULT '', d2_value TEXT NOT NULL DEFAULT '',
        group_field TEXT NOT NULL DEFAULT '',
        is_builtin INTEGER NOT NULL DEFAULT 0,
        builtin_key TEXT UNIQUE,
        sort_order INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS platform_kpis (
        platform_id INTEGER NOT NULL,
        kpi_id INTEGER NOT NULL,
        PRIMARY KEY (platform_id, kpi_id)
    );
    ");

    // Dynamic migrations for platform_templates (older databases won't have this column yet)
    $pt_cols = $db->query("PRAGMA table_info(platform_templates)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('default_name', $pt_cols)) $db->exec("ALTER TABLE platform_templates ADD COLUMN default_name TEXT");

    // Dynamic migrations for fields (older databases won't have these columns yet)
    $f_cols = $db->query("PRAGMA table_info(fields)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('sortable', $f_cols)) $db->exec("ALTER TABLE fields ADD COLUMN sortable INTEGER NOT NULL DEFAULT 1");
    if (!in_array('quick_toggle', $f_cols)) $db->exec("ALTER TABLE fields ADD COLUMN quick_toggle INTEGER NOT NULL DEFAULT 0");
    if (!in_array('colors', $f_cols)) $db->exec("ALTER TABLE fields ADD COLUMN colors TEXT");
    if (!in_array('bold', $f_cols)) $db->exec("ALTER TABLE fields ADD COLUMN bold INTEGER NOT NULL DEFAULT 0");

    // Dynamic migration for kpis (older databases won't have this column yet) -
    // the field a "Most Common" KPI groups by.
    $k_cols = $db->query("PRAGMA table_info(kpis)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('group_field', $k_cols)) $db->exec("ALTER TABLE kpis ADD COLUMN group_field TEXT NOT NULL DEFAULT ''");

    // Dynamic migration for platforms (older databases won't have this column yet) -
    // whether the Title column/field is shown at all on this platform. Defaults to
    // 1 (shown) so existing platforms are unaffected.
    $p_cols = $db->query("PRAGMA table_info(platforms)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('show_title', $p_cols)) $db->exec("ALTER TABLE platforms ADD COLUMN show_title INTEGER NOT NULL DEFAULT 1");

    // Pick up the renamed built-in defaults ("Spine #" -> "Spine", "Legacy?" -> "Legacy")
    // for fields that still have their old default label (never renamed by the user)
    $db->prepare("UPDATE fields SET label = 'Spine' WHERE field_key = 'release_no' AND is_builtin = 1 AND label = 'Spine #'")->execute();
    $db->prepare("UPDATE fields SET label = 'Legacy' WHERE field_key = 'legacy' AND is_builtin = 1 AND label = 'Legacy?'")->execute();

    $already_migrated = $db->query("SELECT value FROM settings WHERE key = 'fields_migrated'")->fetchColumn();
    if (!$already_migrated) {
        $db->beginTransaction();
        seed_builtin_fields($db);

        $builtin_ids = [];
        foreach ($db->query("SELECT id, field_key FROM fields WHERE is_builtin = 1") as $r) {
            $builtin_ids[$r['field_key']] = (int)$r['id'];
        }
        $link = $db->prepare("INSERT OR IGNORE INTO platform_fields (platform_id, field_id) VALUES (?, ?)");
        foreach ($db->query("SELECT * FROM platforms")->fetchAll(PDO::FETCH_ASSOC) as $p) {
            foreach (BUILTIN_FIELDS as $key => $d) {
                if (!empty($p['has_' . $key] ?? null) && isset($builtin_ids[$key])) {
                    $link->execute([(int)$p['id'], $builtin_ids[$key]]);
                }
            }
        }
        $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('fields_migrated', '1')")->execute();
        $db->commit();
    }

    // One-time seed + enable of the built-in KPIs, giving each existing
    // platform a sensible starting set based on its fields - so upgrading
    // shows something reasonable until the admin adjusts it via "Manage KPIs",
    // which is the one place KPI visibility is decided from now on.
    $kpis_already_migrated = $db->query("SELECT value FROM settings WHERE key = 'kpis_migrated'")->fetchColumn();
    if (!$kpis_already_migrated) {
        $db->beginTransaction();
        seed_builtin_kpis($db);

        $kpi_ids = [];
        foreach ($db->query("SELECT id, builtin_key FROM kpis WHERE is_builtin = 1") as $r) {
            $kpi_ids[$r['builtin_key']] = (int)$r['id'];
        }
        $enable = $db->prepare("INSERT OR IGNORE INTO platform_kpis (platform_id, kpi_id) VALUES (?, ?)");
        foreach ($db->query("SELECT * FROM platforms")->fetchAll(PDO::FETCH_ASSOC) as $p) {
            // The real, current field set lives in platform_fields, not any
            // stale column on the platforms row itself.
            $p_fields = get_platform_fields($db, (int)$p['id']);
            $p_has = $p_fields[0];
            $keys = default_kpi_keys_for($p_has);
            foreach ($keys as $key) {
                if (isset($kpi_ids[$key])) $enable->execute([(int)$p['id'], $kpi_ids[$key]]);
            }
        }
        $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('kpis_migrated', '1')")->execute();
        $db->commit();
    }

    // One-time upgrade: Title used to be governed by its own platforms.show_title
    // flag instead of going through the fields/platform_fields system like every
    // other built-in - so it couldn't be renamed or managed alongside the rest.
    // This seeds it as a normal built-in field (a no-op if it's already there -
    // seed_builtin_fields() is idempotent) and, for each existing platform,
    // carries over whatever show_title already said, so nothing changes on
    // screen until the admin actually touches it via "Manage Fields" or the
    // "Enabled Fields" checklist.
    $title_already_migrated = $db->query("SELECT value FROM settings WHERE key = 'title_field_migrated'")->fetchColumn();
    if (!$title_already_migrated) {
        $db->beginTransaction();
        seed_builtin_fields($db);
        $title_id = $db->query("SELECT id FROM fields WHERE field_key = 'title'")->fetchColumn();
        if ($title_id) {
            $link = $db->prepare("INSERT OR IGNORE INTO platform_fields (platform_id, field_id) VALUES (?, ?)");
            foreach ($db->query("SELECT id, show_title FROM platforms")->fetchAll(PDO::FETCH_ASSOC) as $p) {
                if ($p['show_title']) $link->execute([(int)$p['id'], (int)$title_id]);
            }
        }
        $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('title_field_migrated', '1')")->execute();
        $db->commit();
    }
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    $_SESSION['flash_message'] = "Field setup error: " . $e->getMessage();
}

// One-time seed of the built-in "Evercade" platform template (Spine, Line/Series,
// Legacy, Status and Notes)
try {
    $has_evercade = $db->query("SELECT COUNT(*) FROM platform_templates WHERE is_builtin = 1 AND name = 'Evercade'")->fetchColumn();
    if (!$has_evercade) {
        $keys = ['release_no', 'line_series', 'legacy', 'status', 'notes'];
        $ph = implode(',', array_fill(0, count($keys), '?'));
        $st = $db->prepare("SELECT id FROM fields WHERE is_builtin = 1 AND field_key IN ($ph)");
        $st->execute($keys);
        $field_ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $db->prepare("INSERT INTO platform_templates (name, field_ids, is_builtin, default_name) VALUES ('Evercade', ?, 1, 'Evercade')")
           ->execute([json_encode($field_ids)]);
    }
} catch (Exception $e) {}

// Read App Name with memory fallback
$app_name = 'Collection Vault';
try {
    $stmt = $db->query("SELECT value FROM settings WHERE key = 'app_name'");
    if ($stmt) {
        $val = $stmt->fetchColumn();
        $stmt->closeCursor();
        if ($val) $app_name = $val;
    }
} catch (Exception $e) {}
unset($stmt);

// Authentication Helpers
$is_admin = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
$login_error = '';
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
