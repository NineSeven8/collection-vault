<?php
/**
 * Handles every POST action (login, CRUD for platforms/games/fields/
 * KPIs/templates, AJAX endpoints, CSV/DB export+import). Each branch
 * either redirects or exit()s, so nothing below this file runs for a
 * POST request that matched one of these actions.
 */

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Login
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // If no admin user exists in DB yet, seed it
            if (!$user && $username === 'admin') {
                $default_hash = password_hash('admin123', PASSWORD_DEFAULT);
                $db->prepare("INSERT OR IGNORE INTO users (id, username, password_hash, role, is_default_password) VALUES (1, 'admin', ?, 'admin', 1)")->execute([$default_hash]);
                $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $success = false;
            if ($user) {
                // If default password flag is active and user types admin123, allow it
                if (!empty($user['is_default_password']) && $username === 'admin' && $password === 'admin123') {
                    $success = true;
                } elseif (password_verify($password, $user['password_hash'])) {
                    $success = true;
                }
            }

            if ($success) {
                $_SESSION['user'] = [
                    'id' => $user['id'], 
                    'username' => $user['username'], 
                    'role' => $user['role']
                ];
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            } else {
                $login_error = "Invalid username or password.";
            }
        } catch (Exception $e) {
            $login_error = "Auth Error: " . $e->getMessage();
        }
    }

    // Logout
    if ($action === 'logout') {
        unset($_SESSION['user']);
        $_SESSION['flash_message'] = "You have been logged out.";
        $_SESSION['flash_type'] = 'red';
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    // RESTRICTED ADMIN ACTIONS
    if (!$is_admin && in_array($action, ['add_platform', 'edit_platform', 'delete_platform', 'add_game', 'edit_game', 'delete_game', 'bulk_delete_games', 'toggle_owned_ajax', 'toggle_cib_ajax', 'cycle_field_ajax', 'update_note_ajax', 'save_column_order', 'save_sort_ajax', 'change_password', 'update_app_name', 'save_field', 'delete_field', 'restore_builtin_fields', 'save_kpi', 'delete_kpi', 'restore_builtin_kpis', 'toggle_kpi_platform', 'save_kpi_order', 'save_platform_template', 'delete_platform_template', 'export_db', 'import_db', 'set_cover_field_ajax', 'wipe_db'])) {
        if ($action === 'toggle_owned_ajax' || $action === 'toggle_cib_ajax' || $action === 'cycle_field_ajax' || $action === 'update_note_ajax' || $action === 'save_column_order' || $action === 'save_kpi_order' || $action === 'save_sort_ajax' || $action === 'set_cover_field_ajax') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        die("Unauthorized: Admin privileges required.");
    }

    // Export a full backup: a clean, consistent snapshot of the live database
    // (platforms, titles, fields, templates, settings, users) plus every
    // uploaded cover image, bundled into a single zip downloaded straight to
    // the browser.
    if ($action === 'export_db') {
        $tmp_db = sys_get_temp_dir() . '/collection_export_' . bin2hex(random_bytes(6)) . '.db';
        $tmp_zip = sys_get_temp_dir() . '/collection_export_' . bin2hex(random_bytes(6)) . '.zip';
        try {
            if (!class_exists('ZipArchive')) {
                throw new Exception("The PHP zip extension isn't enabled on this server.");
            }
            try {
                // Preferred: atomic, consistent snapshot. Needs SQLite 3.27+ (bundled
                // with PHP's sqlite3 extension); older system libsqlite3 builds (common
                // on plain nginx/PHP-FPM installs) don't support this statement.
                $db->exec("VACUUM INTO " . $db->quote($tmp_db));
            } catch (Exception $e) {
                // Fallback for older SQLite: force any WAL data into the main file,
                // then copy the file directly. Safe as long as nothing writes to the
                // DB mid-copy, which is true for this single-process app.
                $db->exec("PRAGMA wal_checkpoint(TRUNCATE)");
                if (!copy($db_file, $tmp_db)) {
                    throw new Exception("Could not create a backup copy of the database file.");
                }
            }

            $zip = new ZipArchive();
            if ($zip->open($tmp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception("Could not create the backup archive.");
            }
            $zip->addFile($tmp_db, 'collection.db');

            // Keep the folder entry even when there are no covers yet, so import
            // can tell "an intentionally cover-less backup" apart from "an old
            // database-only backup that never touched covers at all".
            $zip->addEmptyDir('uploads/covers');
            $covers_dir = dirname(__DIR__) . '/uploads/covers';
            if (is_dir($covers_dir)) {
                foreach (scandir($covers_dir) as $f) {
                    if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
                    $full = $covers_dir . '/' . $f;
                    if (is_file($full)) $zip->addFile($full, 'uploads/covers/' . $f);
                }
            }
            $zip->close();

            $fname = 'collection-backup-' . date('Y-m-d_His') . '.zip';
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $fname . '"');
            header('Content-Length: ' . filesize($tmp_zip));
            header('Cache-Control: no-store');
            readfile($tmp_zip);
            @unlink($tmp_db);
            @unlink($tmp_zip);
            exit;
        } catch (Exception $e) {
            @unlink($tmp_db);
            @unlink($tmp_zip);
            $_SESSION['flash_message'] = "Export failed: " . $e->getMessage();
            header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
    }

    // Export one platform's titles as a CSV, with only the fields the admin
    // picked in the field checklist (Backup/Restore > Export Titles as CSV).
    if ($action === 'export_csv') {
        try {
            $platform_id = (int)($_POST['platform_id'] ?? 0);
            $field_ids = isset($_POST['fields']) && is_array($_POST['fields']) ? array_map('intval', $_POST['fields']) : [];
            if ($platform_id <= 0) throw new Exception("No platform selected.");
            if (empty($field_ids)) throw new Exception("Choose at least one field to export.");

            $pstmt = $db->prepare("SELECT * FROM platforms WHERE id = ?");
            $pstmt->execute([$platform_id]);
            $platform = $pstmt->fetch(PDO::FETCH_ASSOC);
            if (!$platform) throw new Exception("Platform not found.");

            // Only fields actually enabled on this platform, kept in the
            // order they were posted (the picker lists them in column order).
            $fph = implode(',', array_fill(0, count($field_ids), '?'));
            $fstmt = $db->prepare("SELECT f.* FROM fields f JOIN platform_fields pf ON pf.field_id = f.id WHERE pf.platform_id = ? AND f.id IN ($fph)");
            $fstmt->execute(array_merge([$platform_id], $field_ids));
            $fields_by_id = [];
            foreach ($fstmt->fetchAll(PDO::FETCH_ASSOC) as $f) $fields_by_id[(int)$f['id']] = $f;
            $export_fields = [];
            foreach ($field_ids as $fid) {
                if (isset($fields_by_id[$fid])) $export_fields[] = $fields_by_id[$fid];
            }
            if (empty($export_fields)) throw new Exception("None of the selected fields are enabled on this platform.");

            $gstmt = $db->prepare("SELECT * FROM games WHERE platform_id = ? ORDER BY title ASC");
            $gstmt->execute([$platform_id]);
            $export_games = $gstmt->fetchAll(PDO::FETCH_ASSOC);

            $needs_custom = false;
            foreach ($export_fields as $f) { if (empty($f['is_builtin'])) { $needs_custom = true; break; } }
            if ($needs_custom && !empty($export_games)) {
                $gids = array_map(function($g) { return (int)$g['id']; }, $export_games);
                $gph = implode(',', array_fill(0, count($gids), '?'));
                $vstmt = $db->prepare("SELECT game_id, field_id, value FROM game_field_values WHERE game_id IN ($gph)");
                $vstmt->execute($gids);
                $cfmap = [];
                foreach ($vstmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $cfmap[(int)$r['game_id']][(int)$r['field_id']] = (string)$r['value'];
                }
                foreach ($export_games as &$gref) {
                    $gref['cf'] = $cfmap[(int)$gref['id']] ?? [];
                }
                unset($gref);
            }

            $fname = 'collection-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $platform['name']) . '-' . date('Y-m-d') . '.csv';
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $fname . '"');
            header('Cache-Control: no-store');

            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel doesn't mangle accented titles
            fputcsv($out, array_map(function($f) { return $f['label']; }, $export_fields));
            foreach ($export_games as $g) {
                fputcsv($out, array_map(function($f) use ($g) { return csv_export_value($g, $f); }, $export_fields));
            }
            fclose($out);
            exit;
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "CSV export failed: " . $e->getMessage();
            header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
    }

    // Import a backup: validates the uploaded file is a genuine, intact backup
    // of this app before replacing the live database (and, for a zip backup,
    // the cover images) with it. Whatever was in use before is kept as a
    // timestamped .bak alongside it, never deleted.
    if ($action === 'import_db') {
        $tmp_extract_db = null;
        $tmp_covers_dir = null;
        try {
            if (empty($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                if (!empty($_SERVER['CONTENT_LENGTH']) && empty($_POST) && empty($_FILES)) {
                    throw new Exception("That file is too large for this server to accept.");
                }
                throw new Exception("Please choose a backup file to upload.");
            }
            $tmp_upload = $_FILES['backup_file']['tmp_name'];

            // Sniff the real format instead of trusting the extension: a zip
            // backup starts with the "PK" local-file-header signature.
            $is_zip = @file_get_contents($tmp_upload, false, null, 0, 2) === 'PK';

            // Whether the archive carries cover images to restore. null means
            // "not a zip / no covers folder at all" - an old, database-only
            // backup - so the covers on disk are left untouched. An empty
            // array means the zip has the folder but no images in it, which
            // does replace whatever covers are currently on disk (with none),
            // matching what was actually backed up.
            $covers_from_zip = null;

            if ($is_zip) {
                if (!class_exists('ZipArchive')) {
                    throw new Exception("The PHP zip extension isn't enabled on this server.");
                }
                $zip = new ZipArchive();
                if ($zip->open($tmp_upload) !== true) {
                    throw new Exception("That file isn't a valid zip archive.");
                }

                $db_index = $zip->locateName('collection.db');
                if ($db_index === false) {
                    // Be forgiving of the exact filename in case it's been renamed
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $n = $zip->getNameIndex($i);
                        if (strpos($n, '/') === false && preg_match('/\.(db|sqlite|sqlite3)$/i', $n)) {
                            $db_index = $i;
                            break;
                        }
                    }
                }
                if ($db_index === false) throw new Exception("That archive doesn't contain a database file.");

                $tmp_extract_db = sys_get_temp_dir() . '/collection_import_' . bin2hex(random_bytes(6)) . '.db';
                $db_data = $zip->getFromIndex($db_index);
                if ($db_data === false || file_put_contents($tmp_extract_db, $db_data) === false) {
                    throw new Exception("Could not read the database from the archive.");
                }

                $cover_names = [];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $n = $zip->getNameIndex($i);
                    if (strpos($n, 'uploads/covers/') === 0 && substr($n, -1) !== '/') $cover_names[] = $n;
                }
                if ($zip->locateName('uploads/covers/') !== false || !empty($cover_names)) {
                    $covers_from_zip = $cover_names;
                    if (!empty($cover_names)) {
                        $tmp_covers_dir = sys_get_temp_dir() . '/collection_import_covers_' . bin2hex(random_bytes(6));
                        @mkdir($tmp_covers_dir, 0777, true);
                        $zip->extractTo($tmp_covers_dir, $cover_names);
                    }
                }
                $zip->close();
                $tmp_upload = $tmp_extract_db;
            }

            // Validate: must be a genuine, intact SQLite database with the tables this app expects
            $check = new PDO("sqlite:" . $tmp_upload);
            $check->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $ok = $check->query("PRAGMA integrity_check")->fetchColumn();
            if ($ok !== 'ok') throw new Exception("That file isn't a valid, intact database.");
            $tables = $check->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
            foreach (['platforms', 'games', 'fields'] as $required_table) {
                if (!in_array($required_table, $tables, true)) throw new Exception("That file doesn't look like a Collection Vault backup.");
            }
            $check = null;

            // Release our connection to the live database before replacing its file
            $db = null;

            if (file_exists($db_file)) {
                @rename($db_file, $db_file . '.pre-import-' . date('Y-m-d_His') . '.bak');
            }
            if (!move_uploaded_file($tmp_upload, $db_file) && !copy($tmp_upload, $db_file)) {
                throw new Exception("Could not save the uploaded file.");
            }
            if ($tmp_extract_db && file_exists($tmp_extract_db)) @unlink($tmp_extract_db);

            // Swap in the covers the archive carried, keeping whatever's
            // currently on disk as a dated backup rather than deleting it.
            if ($covers_from_zip !== null) {
                $covers_dir = dirname(__DIR__) . '/uploads/covers';
                if (is_dir($covers_dir)) {
                    @rename($covers_dir, $covers_dir . '.pre-import-' . date('Y-m-d_His') . '.bak');
                }
                @mkdir($covers_dir, 0777, true);
                if ($tmp_covers_dir && is_dir($tmp_covers_dir . '/uploads/covers')) {
                    foreach (scandir($tmp_covers_dir . '/uploads/covers') as $f) {
                        if ($f === '.' || $f === '..') continue;
                        @rename($tmp_covers_dir . '/uploads/covers/' . $f, $covers_dir . '/' . $f);
                    }
                }
            }

            unset($_SESSION['user']);
            $_SESSION['flash_message'] = "Database restored from backup" . ($covers_from_zip !== null ? " (including cover images)" : "") . ". Please sign in again.";
        } catch (Exception $e) {
            if ($tmp_extract_db && file_exists($tmp_extract_db)) @unlink($tmp_extract_db);
            $_SESSION['flash_message'] = "Import failed: " . $e->getMessage();
        }
        // Clean up the temp extraction folder either way
        if ($tmp_covers_dir && is_dir($tmp_covers_dir)) {
            if (is_dir($tmp_covers_dir . '/uploads/covers')) {
                foreach (scandir($tmp_covers_dir . '/uploads/covers') as $f) {
                    if ($f !== '.' && $f !== '..') @unlink($tmp_covers_dir . '/uploads/covers/' . $f);
                }
                @rmdir($tmp_covers_dir . '/uploads/covers');
            }
            @rmdir($tmp_covers_dir . '/uploads');
            @rmdir($tmp_covers_dir);
        }
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    // Wipe Database and Start From Scratch (Factory Reset)
    if ($action === 'wipe_db') {
        $confirm_text = trim($_POST['confirm_text'] ?? '');
        if (strtoupper($confirm_text) !== 'WIPE') {
            $_SESSION['flash_message'] = "Wipe aborted: You must type WIPE to confirm.";
            $_SESSION['flash_type'] = 'error';
            header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }

        try {
            // 1. Wipe all uploaded cover images
            $wiped_covers = wipe_all_cover_files();

            // 2. Safety backup of database file
            $backup_file = $db_file . '.pre-wipe-' . date('Y-m-d_His') . '.bak';
            if (file_exists($db_file)) {
                @copy($db_file, $backup_file);
            }

            // 3. Close PDO connection so Windows unlocks file
            $db = null;

            // 4. Delete the database file so schema.php reinitializes everything fresh
            $deleted = false;
            if (file_exists($db_file)) {
                $deleted = @unlink($db_file);
                if (!$deleted) {
                    $deleted = @rename($db_file, $backup_file);
                }
            }

            // If file lock prevented unlink/rename, drop all tables as reliable fallback
            if (file_exists($db_file)) {
                $fallback_db = new PDO("sqlite:" . $db_file);
                $fallback_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $tables = $fallback_db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $tbl) {
                    $fallback_db->exec("DROP TABLE IF EXISTS \"$tbl\"");
                }
                $fallback_db = null;
            }

            // 5. Keep current admin user logged in seamlessly
            $_SESSION['user'] = [
                'id' => 1,
                'username' => 'admin',
                'role' => 'admin'
            ];
            $cover_msg = $wiped_covers > 0 ? " ($wiped_covers cover images deleted)" : "";
            $_SESSION['flash_message'] = "Database wiped successfully and reset from scratch$cover_msg. A backup was saved on the server.";
            $_SESSION['flash_type'] = 'success';
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Wipe failed: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
        }

        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    // Update App Name
    if ($action === 'update_app_name') {
        $new_name = trim($_POST['app_name'] ?? '');
        if ($new_name !== '') {
            try {
                $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('app_name', ?)");
                $stmt->execute([$new_name]);
                $_SESSION['flash_message'] = "Application name updated.";
            } catch (Exception $e) {
                $_SESSION['flash_message'] = "Error updating name: " . $e->getMessage();
            }
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Change Admin Password
    if ($action === 'change_password') {
        $new_pass = trim($_POST['new_password'] ?? '');
        if (strlen($new_pass) >= 6) {
            try {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT OR REPLACE INTO users (id, username, password_hash, role, is_default_password) VALUES (?, 'admin', ?, 'admin', 0)");
                $stmt->execute([$_SESSION['user']['id'] ?? 1, $new_hash]);
                $_SESSION['flash_message'] = "Password updated successfully. Default fallback disabled.";
            } catch (Exception $e) {
                $_SESSION['flash_message'] = "Error updating password: " . $e->getMessage();
            }
        } else {
            $_SESSION['flash_message'] = "Password must be at least 6 characters.";
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Save the current "Configure Fields" setup (from the Add Platform dialog) as a
    // reusable named template — either a brand new one, or (when template_id is set)
    // overwriting an existing one, built-in or custom, with the current setup
    if ($action === 'save_platform_template') {
        $name = str_cut(trim($_POST['name'] ?? ''), 40);
        $field_ids = array_map('intval', (array)($_POST['fields'] ?? []));
        $default_name = str_cut(trim($_POST['default_name'] ?? ''), 60);
        $template_id = (int)($_POST['template_id'] ?? 0);
        try {
            if ($name === '') throw new Exception("Please enter a name for the template.");
            $st = $db->prepare("SELECT COUNT(*) FROM platform_templates WHERE LOWER(name) = LOWER(?) AND id != ?");
            $st->execute([$name, $template_id]);
            if ($st->fetchColumn() > 0) throw new Exception("A template named \"$name\" already exists.");

            $valid = array_map('intval', $db->query("SELECT id FROM fields")->fetchAll(PDO::FETCH_COLUMN));
            $field_ids = array_values(array_intersect($field_ids, $valid));
            $default_name_val = $default_name !== '' ? $default_name : null;

            if ($template_id > 0) {
                $exists = $db->prepare("SELECT COUNT(*) FROM platform_templates WHERE id = ?");
                $exists->execute([$template_id]);
                if (!$exists->fetchColumn()) throw new Exception("That template no longer exists.");
                $db->prepare("UPDATE platform_templates SET name = ?, field_ids = ?, default_name = ? WHERE id = ?")
                   ->execute([$name, json_encode($field_ids), $default_name_val, $template_id]);
                $_SESSION['flash_message'] = "Template \"$name\" updated.";
            } else {
                $db->prepare("INSERT INTO platform_templates (name, field_ids, default_name, is_builtin) VALUES (?, ?, ?, 0)")
                   ->execute([$name, json_encode($field_ids), $default_name_val]);
                $_SESSION['flash_message'] = "Template \"$name\" saved.";
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Template not saved: " . $e->getMessage();
        }
        header("Location: ?platform=" . (int)($_POST['platform_id'] ?? 0));
        exit;
    }

    // Delete a (custom) platform template
    if ($action === 'delete_platform_template') {
        $tid = (int)($_POST['template_id'] ?? 0);
        try {
            $st = $db->prepare("SELECT is_builtin FROM platform_templates WHERE id = ?");
            $st->execute([$tid]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception("That template no longer exists.");
            if ($row['is_builtin']) throw new Exception("Built-in templates can't be deleted.");
            $db->prepare("DELETE FROM platform_templates WHERE id = ?")->execute([$tid]);
            $_SESSION['flash_message'] = "Template deleted.";
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Error: " . $e->getMessage();
        }
        header("Location: ?platform=" . (int)($_POST['platform_id'] ?? 0));
        exit;
    }

    // Add Platform
    if ($action === 'add_platform') {
        $name = trim($_POST['name'] ?? '');
        $code = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        $field_ids = array_map('intval', (array)($_POST['fields'] ?? []));
        $cover_field_id = !empty($_POST['cover_field_id']) ? (int)$_POST['cover_field_id'] : null;

        if ($name !== '') {
            try {
                $stmt = $db->prepare("INSERT INTO platforms (name, code, cover_field_id) VALUES (?, ?, ?)");
                $stmt->execute([$name, $code, $cover_field_id]);
                $new_id = (int)$db->lastInsertId();
                sync_platform_fields($db, $new_id, $field_ids);
                $new_has = get_platform_fields($db, $new_id)[0];
                enable_builtin_kpis($db, $new_id, default_kpi_keys_for($new_has));
                header("Location: ?platform=" . $new_id);
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $_SESSION['flash_message'] = "Error: A platform named '$name' already exists.";
                } else {
                    $_SESSION['flash_message'] = "Database Error: " . $e->getMessage();
                }
            }
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Edit Platform
    if ($action === 'edit_platform') {
        $id = (int)$_POST['platform_id'];
        $name = trim($_POST['name'] ?? '');
        $field_ids = array_map('intval', (array)($_POST['fields'] ?? []));
        $cover_field_id = !empty($_POST['cover_field_id']) ? (int)$_POST['cover_field_id'] : null;

        try {
            $stmt = $db->prepare("UPDATE platforms SET name = ?, cover_field_id = ? WHERE id = ?");
            $stmt->execute([$name, $cover_field_id, $id]);
            sync_platform_fields($db, $id, $field_ids);
            $_SESSION['flash_message'] = "Platform settings updated.";
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Error: " . $e->getMessage();
        }
        header("Location: ?platform=" . $id);
        exit;
    }

    // Delete Platform
    if ($action === 'delete_platform') {
        $id = (int)$_POST['platform_id'];
        try {
            $db->prepare("DELETE FROM game_field_values WHERE game_id IN (SELECT id FROM games WHERE platform_id = ?)")->execute([$id]);
            $db->prepare("DELETE FROM platform_fields WHERE platform_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM games WHERE platform_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM platforms WHERE id = ?")->execute([$id]);
        } catch (Exception $e) {}
        header("Location: index.php");
        exit;
    }

    // Add Game
    if ($action === 'add_game') {
        $platform_id = (int)$_POST['platform_id'];
        $title = trim($_POST['title'] ?? '');
        $release_no = !empty($_POST['release_no']) ? (int)$_POST['release_no'] : null;
        $line_series = trim($_POST['line_series'] ?? '');
        $is_legacy = isset($_POST['is_legacy']) ? (int)$_POST['is_legacy'] : 0;
        $is_owned = isset($_POST['is_owned']) ? (int)$_POST['is_owned'] : 0;
        $is_cib = isset($_POST['is_cib']) ? (int)$_POST['is_cib'] : 0;
        $region = trim($_POST['region'] ?? 'EUR (PAL)');
        $media_type = trim($_POST['media_type'] ?? 'Physical');
        $notes = trim($_POST['notes'] ?? '');

        // Title is optional - a platform like Vinyls may use dedicated Artist/Album
        // fields instead and have no use for it. An empty string still satisfies
        // the NOT NULL column, so this is safe to save as-is.
        try {
            $image_path = handle_cover_upload($_FILES['cover_image'] ?? null);
            $stmt = $db->prepare("INSERT INTO games (platform_id, release_no, title, line_series, is_legacy, is_owned, is_cib, region, media_type, notes, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$platform_id, $release_no, $title, $line_series, $is_legacy, $is_owned, $is_cib, $region, $media_type, $notes, $image_path]);
            $new_game_id = (int)$db->lastInsertId();

            $pf = get_platform_fields($db, $platform_id);
            save_custom_values($db, $new_game_id, $pf[1], $_POST['cf'] ?? []);
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Error saving title: " . $e->getMessage();
        }
        header("Location: ?platform=" . $platform_id);
        exit;
    }

    // Edit Existing Game
    // Only the fields that are enabled on the platform are written, so editing a title
    // never wipes data belonging to a field that has been switched off or removed.
    if ($action === 'edit_game') {
        $game_id = (int)$_POST['game_id'];
        $platform_id = (int)$_POST['platform_id'];
        $title = trim($_POST['title'] ?? '');
        $release_no = !empty($_POST['release_no']) ? (int)$_POST['release_no'] : null;
        $line_series = trim($_POST['line_series'] ?? '');
        $is_legacy = isset($_POST['is_legacy']) ? (int)$_POST['is_legacy'] : 0;
        $is_owned = isset($_POST['is_owned']) ? (int)$_POST['is_owned'] : 0;
        $is_cib = isset($_POST['is_cib']) ? (int)$_POST['is_cib'] : 0;
        $region = trim($_POST['region'] ?? 'EUR (PAL)');
        $media_type = trim($_POST['media_type'] ?? 'Physical');
        $notes = trim($_POST['notes'] ?? '');

        // Title is optional (see add_game) - an empty string is a valid save, not
        // a rejected one.
        try {
            $curr_stmt = $db->prepare("SELECT image_path FROM games WHERE id = ?");
            $curr_stmt->execute([$game_id]);
            $existing_image = $curr_stmt->fetchColumn() ?: null;

            $image_path = $existing_image;
            if (!empty($_POST['remove_cover'])) {
                delete_cover_file($existing_image);
                $image_path = null;
            } elseif (isset($_FILES['cover_image']) && !empty($_FILES['cover_image']['tmp_name'])) {
                $image_path = handle_cover_upload($_FILES['cover_image'], $existing_image);
            }

            $pf = get_platform_fields($db, $platform_id);
            $has_f = $pf[0];
            $custom_f = $pf[1];

            $sets = ['title = ?', 'is_owned = ?', 'image_path = ?'];
            $params = [$title, $is_owned, $image_path];
            $column_map = [
                'release_no'  => ['release_no', $release_no],
                'line_series' => ['line_series', $line_series],
                'legacy'      => ['is_legacy', $is_legacy],
                'cib'         => ['is_cib', $is_cib],
                'region'      => ['region', $region],
                'media_type'  => ['media_type', $media_type],
                'notes'       => ['notes', $notes],
            ];
            foreach ($column_map as $key => $pair) {
                if (!empty($has_f[$key])) {
                    $sets[] = $pair[0] . ' = ?';
                    $params[] = $pair[1];
                }
            }
            $params[] = $game_id;
            $params[] = $platform_id;

            $stmt = $db->prepare("UPDATE games SET " . implode(', ', $sets) . " WHERE id = ? AND platform_id = ?");
            $stmt->execute($params);

            save_custom_values($db, $game_id, $custom_f, $_POST['cf'] ?? []);
            $_SESSION['flash_message'] = "Title updated successfully.";
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Error updating title: " . $e->getMessage();
        }
        header("Location: ?platform=" . $platform_id);
        exit;
    }

    // Bulk Delete Games
    if ($action === 'bulk_delete_games') {
        $platform_id = (int)$_POST['platform_id'];
        $game_ids = $_POST['selected_games'] ?? [];

        if (!empty($game_ids) && is_array($game_ids)) {
            try {
                // Delete associated cover images from disk
                $img_stmt = $db->prepare("SELECT image_path FROM games WHERE id = ? AND platform_id = ?");
                foreach ($game_ids as $gid) {
                    $img_stmt->execute([(int)$gid, $platform_id]);
                    $cover = $img_stmt->fetchColumn();
                    if ($cover) delete_cover_file($cover);
                }

                $db->beginTransaction();
                $del_vals = $db->prepare("DELETE FROM game_field_values WHERE game_id IN (SELECT id FROM games WHERE id = ? AND platform_id = ?)");
                $stmt = $db->prepare("DELETE FROM games WHERE id = ? AND platform_id = ?");
                foreach ($game_ids as $gid) {
                    $del_vals->execute([(int)$gid, $platform_id]);
                    $stmt->execute([(int)$gid, $platform_id]);
                }
                $db->commit();
                $_SESSION['flash_message'] = count($game_ids) . " title(s) deleted successfully.";
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['flash_message'] = "Bulk delete error: " . $e->getMessage();
            }
        }
        header("Location: ?platform=" . $platform_id);
        exit;
    }

    // Create / Edit a Field (from the Manage Fields dialog)
    if ($action === 'save_field') {
        $return_platform = (int)($_POST['platform_id'] ?? 0);
        $field_id = (int)($_POST['field_id'] ?? 0);
        $label = str_cut(trim($_POST['label'] ?? ''), 40);
        $description = str_cut(trim($_POST['description'] ?? ''), 120);
        if ($description === '') $description = $label;
        $type = $_POST['field_type'] ?? 'text';
        if (!is_string($type) || !isset(FIELD_TYPES[$type])) $type = 'text';

        $opts = [];
        foreach (preg_split('/\r\n|\r|\n/', (string)($_POST['options'] ?? '')) as $line) {
            $line = str_cut(trim($line), 60);
            if ($line !== '' && !in_array($line, $opts, true)) $opts[] = $line;
        }

        $sortable = isset($_POST['sortable']) ? 1 : 0;
        $quick_toggle = isset($_POST['quick_toggle']) ? 1 : 0;
        $bold = isset($_POST['bold']) ? 1 : 0;

        try {
            $existing = null;
            if ($field_id > 0) {
                $st = $db->prepare("SELECT * FROM fields WHERE id = ?");
                $st->execute([$field_id]);
                $existing = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!$existing) throw new Exception("That field no longer exists.");
            }
            if ($label === '') throw new Exception("Please enter a column name for the field.");
            if (strtolower($label) === 'actions') throw new Exception("\"$label\" is reserved. Please choose another column name.");

            $st = $db->prepare("SELECT COUNT(*) FROM fields WHERE LOWER(label) = LOWER(?) AND id != ?");
            $st->execute([$label, $field_id]);
            if ($st->fetchColumn() > 0) throw new Exception("A field with the column name \"$label\" already exists.");

            // Quick-change only ever makes sense on Yes/No and Choice-list fields
            $effective_type = ($existing && $existing['is_builtin']) ? $existing['field_type'] : $type;
            if (!in_array($effective_type, ['yesno', 'select'], true)) $quick_toggle = 0;
            // Bold emphasis only makes sense on plain text/number cells - yesno and
            // select already stand out as colored badges.
            if (!in_array($effective_type, ['text', 'number'], true)) $bold = 0;

            // Per-choice colors (choice list fields only): {choice text => color key},
            // built from whatever the browser posted, kept only for choices that are
            // actually valid for this field and colors that actually exist.
            $colors_json = null;
            if ($effective_type === 'select') {
                $valid_choices = ($existing && $existing['is_builtin'])
                    ? (BUILTIN_CHOICES[$existing['field_key']] ?? [])
                    : $opts;
                $posted_colors = json_decode((string)($_POST['colors_json'] ?? ''), true);
                $colors = [];
                if (is_array($posted_colors)) {
                    foreach ($posted_colors as $choice => $color) {
                        $choice = str_cut((string)$choice, 60);
                        if (in_array($choice, $valid_choices, true) && isset(CHOICE_COLORS[$color])) {
                            $colors[$choice] = $color;
                        }
                    }
                }
                if (!empty($colors)) $colors_json = json_encode($colors, JSON_UNESCAPED_UNICODE);
            }

            if ($existing && $existing['is_builtin']) {
                // Built-in fields keep their type/behaviour; the names, sortability,
                // quick-change and (for choice lists) colors can still be changed
                $db->prepare("UPDATE fields SET label = ?, description = ?, sortable = ?, quick_toggle = ?, colors = ?, bold = ? WHERE id = ?")
                   ->execute([$label, $description, $sortable, $quick_toggle, $colors_json, $bold, $field_id]);
                $_SESSION['flash_message'] = "Field \"$label\" updated.";
            } else {
                if ($type === 'select' && count($opts) === 0) throw new Exception("A choice list needs at least one choice (one per line).");
                $options_json = ($type === 'select') ? json_encode($opts, JSON_UNESCAPED_UNICODE) : null;

                if ($existing) {
                    $db->prepare("UPDATE fields SET label = ?, description = ?, field_type = ?, options = ?, sortable = ?, quick_toggle = ?, colors = ?, bold = ? WHERE id = ?")
                       ->execute([$label, $description, $type, $options_json, $sortable, $quick_toggle, $colors_json, $bold, $field_id]);
                    $_SESSION['flash_message'] = "Field \"$label\" updated.";
                } else {
                    $order = (int)$db->query("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM fields")->fetchColumn();
                    $key = 'custom_' . bin2hex(random_bytes(4));
                    $db->prepare("INSERT INTO fields (field_key, label, description, field_type, options, is_builtin, sort_order, sortable, quick_toggle, colors, bold) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)")
                       ->execute([$key, $label, $description, $type, $options_json, $order, $sortable, $quick_toggle, $colors_json, $bold]);
                    $new_field_id = (int)$db->lastInsertId();

                    if (isset($_POST['enable_here']) && $return_platform > 0) {
                        $db->prepare("INSERT OR IGNORE INTO platform_fields (platform_id, field_id) VALUES (?, ?)")->execute([$return_platform, $new_field_id]);
                    }
                    $_SESSION['flash_message'] = "Field \"$label\" created.";
                }
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Field not saved: " . $e->getMessage();
        }
        header("Location: ?platform=" . $return_platform . "&manage_fields=1");
        exit;
    }

    // Delete a Field (removes it from every platform)
    if ($action === 'delete_field') {
        $return_platform = (int)($_POST['platform_id'] ?? 0);
        $field_id = (int)($_POST['field_id'] ?? 0);
        try {
            $db->beginTransaction();
            $db->prepare("DELETE FROM game_field_values WHERE field_id = ?")->execute([$field_id]);
            $db->prepare("DELETE FROM platform_fields WHERE field_id = ?")->execute([$field_id]);
            $db->prepare("DELETE FROM fields WHERE id = ?")->execute([$field_id]);
            $db->commit();
            $_SESSION['flash_message'] = "Field deleted.";
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $_SESSION['flash_message'] = "Error deleting field: " . $e->getMessage();
        }
        header("Location: ?platform=" . $return_platform . "&manage_fields=1");
        exit;
    }

    // Restore any deleted built-in fields
    if ($action === 'restore_builtin_fields') {
        $return_platform = (int)($_POST['platform_id'] ?? 0);
        try {
            $n = seed_builtin_fields($db);
            $_SESSION['flash_message'] = $n > 0
                ? "Restored $n built-in field(s). Tick them under Platform Fields & Settings to show them again."
                : "All built-in fields are already present.";
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Error restoring fields: " . $e->getMessage();
        }
        header("Location: ?platform=" . $return_platform . "&manage_fields=1");
        exit;
    }

    // Create / Edit a KPI (from the Manage KPIs dialog)
    if ($action === 'save_kpi') {
        $return_platform = (int)($_POST['platform_id'] ?? 0);
        $kpi_id = (int)($_POST['kpi_id'] ?? 0);
        $label = str_cut(trim($_POST['label'] ?? ''), 40);
        $color = $_POST['color'] ?? 'slate';
        if (!isset(CHOICE_COLORS[$color])) $color = 'slate';
        $calc_type = in_array($_POST['calc_type'] ?? '', ['percentage', 'top_value'], true) ? $_POST['calc_type'] : 'count';

        $read_cond = function($prefix) {
            $field = (string)($_POST[$prefix . '_field'] ?? '');
            $op = (string)($_POST[$prefix . '_op'] ?? '');
            $value = str_cut(trim((string)($_POST[$prefix . '_value'] ?? '')), 200);
            if ($field === '') return ['', '', ''];
            return [$field, $op, $value];
        };
        [$n1f, $n1o, $n1v] = $read_cond('n1');
        [$n2f, $n2o, $n2v] = $read_cond('n2');
        [$d1f, $d1o, $d1v] = $calc_type === 'percentage' ? $read_cond('d1') : ['', '', ''];
        [$d2f, $d2o, $d2v] = $calc_type === 'percentage' ? $read_cond('d2') : ['', '', ''];
        $group_field = $calc_type === 'top_value' ? (string)($_POST['group_field'] ?? '') : '';

        try {
            if ($label === '') throw new Exception("Please enter a name for the KPI.");
            if ($n1f === '' && $calc_type === 'count') throw new Exception("Choose what this KPI counts (or use \"All Titles\" explicitly by leaving the value blank isn't needed - Catalog Total already covers that).");
            if ($calc_type === 'top_value' && $group_field === '') throw new Exception("Choose which field to group by (e.g. Artist).");

            if ($kpi_id > 0) {
                $db->prepare("UPDATE kpis SET label=?, color=?, calc_type=?, n1_field=?, n1_op=?, n1_value=?, n2_field=?, n2_op=?, n2_value=?, d1_field=?, d1_op=?, d1_value=?, d2_field=?, d2_op=?, d2_value=?, group_field=? WHERE id=?")
                   ->execute([$label, $color, $calc_type, $n1f, $n1o, $n1v, $n2f, $n2o, $n2v, $d1f, $d1o, $d1v, $d2f, $d2o, $d2v, $group_field, $kpi_id]);
                $_SESSION['flash_message'] = "KPI \"$label\" updated.";
            } else {
                $order = (int)$db->query("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM kpis")->fetchColumn();
                $db->prepare("INSERT INTO kpis (label, color, calc_type, n1_field, n1_op, n1_value, n2_field, n2_op, n2_value, d1_field, d1_op, d1_value, d2_field, d2_op, d2_value, group_field, is_builtin, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)")
                   ->execute([$label, $color, $calc_type, $n1f, $n1o, $n1v, $n2f, $n2o, $n2v, $d1f, $d1o, $d1v, $d2f, $d2o, $d2v, $group_field, $order]);
                $kpi_id = (int)$db->lastInsertId();
                $_SESSION['flash_message'] = "KPI \"$label\" created.";
            }

            if (isset($_POST['enable_here']) && $return_platform > 0) {
                $db->prepare("INSERT OR IGNORE INTO platform_kpis (platform_id, kpi_id) VALUES (?, ?)")->execute([$return_platform, $kpi_id]);
            } elseif ($return_platform > 0) {
                $db->prepare("DELETE FROM platform_kpis WHERE platform_id = ? AND kpi_id = ?")->execute([$return_platform, $kpi_id]);
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "KPI not saved: " . $e->getMessage();
        }
        header("Location: ?platform=" . $return_platform . "&manage_kpis=1");
        exit;
    }

    // Delete a KPI (removes it from every platform)
    if ($action === 'delete_kpi') {
        $return_platform = (int)($_POST['platform_id'] ?? 0);
        $kpi_id = (int)($_POST['kpi_id'] ?? 0);
        try {
            $db->beginTransaction();
            $db->prepare("DELETE FROM platform_kpis WHERE kpi_id = ?")->execute([$kpi_id]);
            $db->prepare("DELETE FROM kpis WHERE id = ?")->execute([$kpi_id]);
            $db->commit();
            $_SESSION['flash_message'] = "KPI deleted.";
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $_SESSION['flash_message'] = "Error deleting KPI: " . $e->getMessage();
        }
        header("Location: ?platform=" . $return_platform . "&manage_kpis=1");
        exit;
    }

    // Restore any deleted built-in KPIs
    if ($action === 'restore_builtin_kpis') {
        $return_platform = (int)($_POST['platform_id'] ?? 0);
        try {
            $n = seed_builtin_kpis($db);
            $_SESSION['flash_message'] = $n > 0
                ? "Restored $n built-in KPI(s). Tick them below to show them again."
                : "All built-in KPIs are already present.";
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Error restoring KPIs: " . $e->getMessage();
        }
        header("Location: ?platform=" . $return_platform . "&manage_kpis=1");
        exit;
    }

    // Toggle a KPI on/off for the active platform (checkbox in Manage KPIs)
    if ($action === 'toggle_kpi_platform') {
        header('Content-Type: application/json');
        $platform_id = (int)($_POST['platform_id'] ?? 0);
        $kpi_id = (int)($_POST['kpi_id'] ?? 0);
        try {
            if (!empty($_POST['on'])) {
                $db->prepare("INSERT OR IGNORE INTO platform_kpis (platform_id, kpi_id) VALUES (?, ?)")->execute([$platform_id, $kpi_id]);
            } else {
                $db->prepare("DELETE FROM platform_kpis WHERE platform_id = ? AND kpi_id = ?")->execute([$platform_id, $kpi_id]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Save KPI display order (arrows in the Manage KPIs dialog) - same
    // merge-safe pattern as Reorder Columns: anything not in the posted list
    // (a KPI not shown on this platform) keeps its previous relative position.
    if ($action === 'save_kpi_order') {
        header('Content-Type: application/json');
        try {
            $known = array_map('intval', $db->query("SELECT id FROM kpis")->fetchAll(PDO::FETCH_COLUMN));
            $posted = isset($_POST['order']) && is_array($_POST['order']) ? array_map('intval', $_POST['order']) : [];
            $new_order = array_values(array_intersect($posted, $known));
            foreach ($known as $id) {
                if (!in_array($id, $new_order, true)) $new_order[] = $id;
            }
            $db->beginTransaction();
            $upd = $db->prepare("UPDATE kpis SET sort_order = ? WHERE id = ?");
            $step = 10;
            foreach ($new_order as $id) {
                $upd->execute([$step, $id]);
                $step += 10;
            }
            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Update Note Inline AJAX
    if ($action === 'update_note_ajax') {
        header('Content-Type: application/json');
        $game_id = (int)$_POST['game_id'];
        $notes = trim($_POST['notes'] ?? '');
        try {
            $stmt = $db->prepare("UPDATE games SET notes = ? WHERE id = ?");
            $stmt->execute([$notes, $game_id]);
            if ($stmt->rowCount() === 0) {
                // Nothing was actually updated (bad/stale id) - don't report success.
                $check = $db->prepare("SELECT 1 FROM games WHERE id = ?");
                $check->execute([$game_id]);
                if (!$check->fetchColumn()) {
                    throw new Exception("This title no longer exists. Reload the page and try again.");
                }
                // Row exists but value was already identical - that's fine.
            }
            echo json_encode(['success' => true, 'notes' => $notes]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Save Column Order ("Reorder Columns" dialog). $_POST['order'][] is the
    // list of columns as currently arranged on screen (only the ones visible
    // on whichever platform the admin was looking at); anything not included
    // (a column not shown on that platform, e.g. a custom field that lives on
    // a different one) is appended at the end, in its previous position, so
    // it doesn't silently fall out of every other platform's table.
    if ($action === 'save_column_order') {
        header('Content-Type: application/json');
        try {
            $order_platform_id = (int)($_POST['platform_id'] ?? 0);
            if ($order_platform_id <= 0) throw new Exception("Missing platform.");
            $order_key = 'column_order_' . $order_platform_id;

            $known_cols = array_keys(BUILTIN_FIELDS);
            foreach ($db->query("SELECT id FROM fields WHERE is_builtin = 0") as $r) {
                $known_cols[] = 'cf' . (int)$r['id'];
            }
            $known_cols = array_flip($known_cols);

            $posted = isset($_POST['order']) && is_array($_POST['order']) ? array_map('strval', $_POST['order']) : [];
            $new_order = [];
            foreach (array_unique($posted) as $k) {
                if (isset($known_cols[$k])) $new_order[] = $k;
            }

            // Preserve anything not in the posted list (previous order for this
            // platform, else built-in default).
            $prev_stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
            $prev_stmt->execute([$order_key]);
            $prev = json_decode((string)$prev_stmt->fetchColumn(), true);
            $prev_stmt->closeCursor();
            $fallback = is_array($prev) ? $prev : array_keys($known_cols);
            foreach ($fallback as $k) {
                if (isset($known_cols[$k]) && !in_array($k, $new_order, true)) $new_order[] = $k;
            }
            foreach (array_keys($known_cols) as $k) {
                if (!in_array($k, $new_order, true)) $new_order[] = $k;
            }

            $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)")->execute([$order_key, json_encode($new_order)]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Remember the recent sorts picked on a platform (header clicks and/or the
    // Quick Sort dropdown), so a compound sort ("Artist, then Owned") comes
    // back the same way next time that platform is opened, instead of only
    // the single most recent pick surviving. $_POST['history_json'] is the
    // client's full stack (oldest first, most recent/dominant last) - see
    // sortHistory in the page script.
    if ($action === 'save_sort_ajax') {
        header('Content-Type: application/json');
        try {
            $sort_platform_id = (int)($_POST['platform_id'] ?? 0);
            if ($sort_platform_id <= 0) throw new Exception("Missing platform.");
            $posted_history = json_decode((string)($_POST['history_json'] ?? ''), true);
            if (!is_array($posted_history)) throw new Exception("Invalid sort history.");
            $history = [];
            foreach ($posted_history as $entry) {
                if (!is_array($entry)) continue;
                $col = (string)($entry['col'] ?? '');
                if (!preg_match('/^(cf\d+|[a-zA-Z_]+)$/', $col)) continue;
                $dir = strtolower((string)($entry['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
                $numeric = !empty($entry['numeric']) ? 1 : 0;
                $history[] = ['col' => $col, 'dir' => $dir, 'numeric' => $numeric];
                if (count($history) >= 4) break; // small stack - primary/secondary/tertiary is plenty
            }
            $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)")
               ->execute(['sort_' . $sort_platform_id, json_encode(['history' => $history])]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Toggle Owned AJAX
    if ($action === 'toggle_owned_ajax') {
        header('Content-Type: application/json');
        $game_id = (int)$_POST['game_id'];
        try {
            $stmt = $db->prepare("SELECT is_owned FROM games WHERE id = ?");
            $stmt->execute([$game_id]);
            $curr = $stmt->fetchColumn();
            $next = $curr ? 0 : 1;
            $db->prepare("UPDATE games SET is_owned = ? WHERE id = ?")->execute([$next, $game_id]);
            echo json_encode(['success' => true, 'is_owned' => $next]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Cycle a quick-change field's value right from the table (Legacy, Region,
    // Media Format, and any custom Yes/No or Choice-list field that has "quick
    // change" turned on in Manage Fields). $_POST['field_ref'] is 'b:<key>' for
    // a built-in field or 'c:<field_id>' for a custom one.
    if ($action === 'cycle_field_ajax') {
        header('Content-Type: application/json');
        $game_id = (int)($_POST['game_id'] ?? 0);
        $field_ref = (string)($_POST['field_ref'] ?? '');
        try {
            if (strpos($field_ref, 'b:') === 0) {
                $key = substr($field_ref, 2);
                $col_map = ['legacy' => 'is_legacy', 'region' => 'region', 'media_type' => 'media_type', 'status' => 'is_owned', 'cib' => 'is_cib'];
                if (!isset($col_map[$key])) throw new Exception('Unknown field.');

                $fst = $db->prepare("SELECT * FROM fields WHERE field_key = ? AND is_builtin = 1");
                $fst->execute([$key]);
                $frow = $fst->fetch(PDO::FETCH_ASSOC);
                if (!$frow || !field_is_quick_toggle($frow)) throw new Exception('Quick-change is off for this field.');

                $col = $col_map[$key];
                $stmt = $db->prepare("SELECT $col FROM games WHERE id = ?");
                $stmt->execute([$game_id]);
                $curr = $stmt->fetchColumn();
                if ($curr === false) throw new Exception('Title not found.');

                if ($frow['field_type'] === 'yesno') {
                    $next = $curr ? 0 : 1;
                } else {
                    $choices = field_choice_list($frow);
                    if (empty($choices)) throw new Exception('No choices configured for this field.');
                    $idx = array_search((string)$curr, $choices, true);
                    $next = $choices[($idx === false ? 0 : $idx + 1) % count($choices)];
                }
                $db->prepare("UPDATE games SET $col = ? WHERE id = ?")->execute([$next, $game_id]);
                echo json_encode(['success' => true, 'value' => $next]);
            } elseif (strpos($field_ref, 'c:') === 0) {
                $fid = (int)substr($field_ref, 2);
                $fst = $db->prepare("SELECT * FROM fields WHERE id = ?");
                $fst->execute([$fid]);
                $frow = $fst->fetch(PDO::FETCH_ASSOC);
                if (!$frow || !field_is_quick_toggle($frow)) throw new Exception('Quick-change is off for this field.');

                $vst = $db->prepare("SELECT value FROM game_field_values WHERE game_id = ? AND field_id = ?");
                $vst->execute([$game_id, $fid]);
                $curr = $vst->fetchColumn();

                if ($frow['field_type'] === 'yesno') {
                    $next = ($curr === '1') ? '0' : '1';
                } else {
                    $choices = field_choice_list($frow);
                    if (empty($choices)) throw new Exception('No choices configured for this field.');
                    $idx = array_search((string)$curr, $choices, true);
                    $next = $choices[($idx === false ? 0 : $idx + 1) % count($choices)];
                }

                if ($next === '' || $next === '0') {
                    $db->prepare("DELETE FROM game_field_values WHERE game_id = ? AND field_id = ?")->execute([$game_id, $fid]);
                } else {
                    $db->prepare("INSERT OR REPLACE INTO game_field_values (game_id, field_id, value) VALUES (?, ?, ?)")->execute([$game_id, $fid, $next]);
                }
                echo json_encode(['success' => true, 'value' => $next]);
            } else {
                throw new Exception('Invalid field reference.');
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }



    // Delete Single Game
    if ($action === 'delete_game') {
        $game_id = (int)$_POST['game_id'];
        $platform_id = (int)$_POST['platform_id'];
        try {
            $img_stmt = $db->prepare("SELECT image_path FROM games WHERE id = ?");
            $img_stmt->execute([$game_id]);
            $cover = $img_stmt->fetchColumn();
            if ($cover) delete_cover_file($cover);

            $db->prepare("DELETE FROM game_field_values WHERE game_id = ?")->execute([$game_id]);
            $db->prepare("DELETE FROM games WHERE id = ?")->execute([$game_id]);
            $_SESSION['flash_message'] = "Title deleted.";
        } catch (Exception $e) {}
        header("Location: ?platform=" . $platform_id);
        exit;
    }

    // Set Cover Field (AJAX) — radio-style: selecting the same field again clears it
    if ($action === 'set_cover_field_ajax') {
        header('Content-Type: application/json');
        $platform_id = (int)($_POST['platform_id'] ?? 0);
        $field_id    = (int)($_POST['field_id'] ?? 0);
        try {
            // Read current value
            $stmt = $db->prepare("SELECT cover_field_id FROM platforms WHERE id = ?");
            $stmt->execute([$platform_id]);
            $current = (int)$stmt->fetchColumn();

            // Toggle: if clicking the already-selected field, clear it (set to NULL)
            $new_value = ($current === $field_id) ? null : $field_id;

            $db->prepare("UPDATE platforms SET cover_field_id = ? WHERE id = ?")
               ->execute([$new_value, $platform_id]);

            echo json_encode(['success' => true, 'cover_field_id' => $new_value]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
