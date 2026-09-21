<?php
/**
 * GET-side page data: platforms list, the active platform and its
 * enabled fields, the games table (with custom field values and
 * summary counts), column order, sort state, and the KPI data the
 * view renders. Runs after actions.php so a redirecting POST never
 * reaches here.
 */

$platforms = [];
try {
    $platforms = $db->query("SELECT * FROM platforms ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Active Platform Selection
$active_platform_id = isset($_GET['platform']) ? (int)$_GET['platform'] : ($platforms[0]['id'] ?? 1);
$current_platform = null;
if (!empty($platforms)) {
    foreach ($platforms as $p) {
        if ($p['id'] == $active_platform_id) {
            $current_platform = $p;
            break;
        }
    }
    if (!$current_platform) {
        $current_platform = $platforms[0];
        $active_platform_id = $current_platform['id'];
    }
}

// Field library for this page
$all_fields = [];
$platform_field_ids = [];   // platform_id => [field_id => true]
try {
    $all_fields = $db->query("SELECT * FROM fields ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($db->query("SELECT platform_id, field_id FROM platform_fields") as $r) {
        $platform_field_ids[(int)$r['platform_id']][(int)$r['field_id']] = true;
    }
} catch (Exception $e) {}

// Title's field id, so a brand-new platform's "Add Platform" checklist starts
// with Title ticked (the one sensible default among the built-ins - everything
// else starts unticked until the admin picks it), matching what used to be a
// separate, always-checked "Show Title column" box.
$title_field_id = 0;
foreach ($all_fields as $f) {
    if ($f['field_key'] === 'title') { $title_field_id = (int)$f['id']; break; }
}

// Column names of the built-in fields (fall back to the defaults if one was deleted)
$lbl = [];
foreach (BUILTIN_FIELDS as $k => $d) {
    $lbl[$k] = $d['label'];
}
$present_builtin = [];
foreach ($all_fields as $f) {
    if ($f['is_builtin'] && isset($lbl[$f['field_key']])) {
        $lbl[$f['field_key']] = $f['label'];
        $present_builtin[$f['field_key']] = true;
    }
}
$missing_builtin_count = count(array_diff_key(BUILTIN_FIELDS, $present_builtin));

// Which fields are enabled on the active platform
$has = array_fill_keys(array_keys(BUILTIN_FIELDS), false);
$custom_fields = [];
$field_meta = [];
if ($current_platform) {
    $pf = get_platform_fields($db, (int)$active_platform_id);
    $has = $pf[0];
    $custom_fields = $pf[1];
    $field_meta = $pf[2];
}

// Column display order ("Reorder Columns", admin-only control, applies to
// everyone's view, per platform): a JSON array of column keys saved in
// settings['column_order_<platform_id>'], applied client-side by moving each
// already-rendered table cell into place - see applyColumnOrder() in the page
// script. Falls back to the original built-in layout, with custom fields kept
// right before Notes.
$default_column_order = array_merge(
    ['release_no', 'title', 'line_series', 'region', 'media_type', 'legacy', 'cib', 'status'],
    array_map(function($cf) { return 'cf' . (int)$cf['id']; }, $custom_fields),
    ['notes']
);
$column_order = $default_column_order;
try {
    $col_stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
    $col_stmt->execute(['column_order_' . (int)$active_platform_id]);
    if ($col_stmt) {
        $saved_order = $col_stmt->fetchColumn();
        $col_stmt->closeCursor();
        if ($saved_order) {
            $decoded_order = json_decode($saved_order, true);
            if (is_array($decoded_order) && $decoded_order) {
                $known_cols = array_flip($default_column_order);
                $column_order = array_values(array_filter($decoded_order, function($k) use ($known_cols) {
                    return isset($known_cols[$k]);
                }));
                foreach ($default_column_order as $k) {
                    if (!in_array($k, $column_order, true)) $column_order[] = $k;
                }
            }
        }
    }
} catch (Exception $e) {}
unset($col_stmt);

// Sortable/quick-toggle helpers for a built-in column, using its stored field row
// when available (falls back to "sortable, not quick-toggle" if not found)
function bf_sortable($field_meta, $key) {
    return !isset($field_meta[$key]) || !empty($field_meta[$key]['sortable']);
}
function bf_quick($field_meta, $key) {
    return isset($field_meta[$key]) && field_is_quick_toggle($field_meta[$key]);
}
function bf_colors($field_meta, $key) {
    return isset($field_meta[$key]) ? field_colors($field_meta[$key]) : [];
}
$checked_field_ids = $platform_field_ids[(int)$active_platform_id] ?? [];

// How many platforms use each field, and the data the Manage Fields dialog needs
$fields_usage = [];
foreach ($platform_field_ids as $pid => $set) {
    foreach ($set as $fid => $on) {
        $fields_usage[$fid] = ($fields_usage[$fid] ?? 0) + 1;
    }
}
$fields_js = [];
foreach ($all_fields as $f) {
    $choices = field_choice_list($f);
    $fields_js[(int)$f['id']] = [
        'id' => (int)$f['id'],
        'label' => $f['label'],
        'description' => $f['description'],
        'type' => $f['field_type'],
        'options' => implode("\n", field_options($f)),
        'builtin' => (int)$f['is_builtin'],
        'used' => (int)($fields_usage[(int)$f['id']] ?? 0),
        'sortable' => (int)$f['sortable'],
        'quick_toggle' => (int)$f['quick_toggle'],
        'bold' => (int)$f['bold'],
        'choices' => $choices,
        'colors' => field_colors($f),
    ];
}

// Platform templates ("start from a template" in the Add Platform dialog)
$platform_templates = [];
try {
    $platform_templates = $db->query("SELECT * FROM platform_templates ORDER BY is_builtin DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$templates_js = [];
foreach ($platform_templates as $t) {
    $templates_js[(int)$t['id']] = [
        'id' => (int)$t['id'],
        'name' => $t['name'],
        'field_ids' => array_map('intval', json_decode((string)$t['field_ids'], true) ?: []),
        'builtin' => (int)$t['is_builtin'],
        'default_name' => $t['default_name'] ?? '',
    ];
}

// Sorting parameters. $saved_sort_history is the recent stack of sorts the
// user actually picked on this platform (header clicks and/or the Quick Sort
// dropdown), oldest first, persisted via save_sort_ajax - replaying it in
// order (a stable sort each time) is what reconstructs a compound sort like
// "Artist, then Owned" exactly as it was left, instead of only the single
// most recent pick surviving. $saved_sort is just its last (dominant) entry,
// used below to avoid a visible reorder flash for the common built-in-column
// case; a custom field (cfN) isn't something the SQL ORDER BY can express, so
// the authoritative re-sort - full history included - happens client-side
// (SAVED_SORT_HISTORY in the page script).
$saved_sort_history = [];
if ($current_platform) {
    try {
        $ss_stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
        $ss_stmt->execute(['sort_' . (int)$active_platform_id]);
        $ss_raw = $ss_stmt->fetchColumn();
        $ss_stmt->closeCursor();
        if ($ss_raw) {
            $decoded_sort = json_decode($ss_raw, true);
            // Accept either the current {"history":[...]} shape, or a lone
            // {"col":...} object from before compound sorts were remembered.
            $raw_entries = is_array($decoded_sort) && isset($decoded_sort['history']) && is_array($decoded_sort['history'])
                ? $decoded_sort['history']
                : (is_array($decoded_sort) && !empty($decoded_sort['col']) ? [$decoded_sort] : []);
            foreach ($raw_entries as $entry) {
                if (!is_array($entry) || empty($entry['col'])) continue;
                $saved_sort_history[] = [
                    'col' => (string)$entry['col'],
                    'dir' => (($entry['dir'] ?? 'asc') === 'desc') ? 'desc' : 'asc',
                    'numeric' => !empty($entry['numeric']) ? 1 : 0,
                ];
            }
        }
    } catch (Exception $e) {}
}
$saved_sort = !empty($saved_sort_history) ? end($saved_sort_history) : null;

$sort_col = $_GET['sort'] ?? ($saved_sort['col'] ?? (($current_platform && $has['release_no']) ? 'release_no' : 'title'));
$sort_dir = strtolower($_GET['dir'] ?? ($saved_sort['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

// These are the "canonical" sort keys used everywhere sorting is triggered
// from - the Quick Sort dropdown's option values, a sortable column header's
// onclick, and each row's data-* attributes that the client-side re-sort
// reads. Where that differs from the real SQL column name (owned/cib/legacy
// vs. is_owned/is_cib/is_legacy), $sort_sql_col below maps it for the query.
$allowed_sorts = ['release_no', 'title', 'line_series', 'legacy', 'owned', 'cib', 'region', 'media_type', 'notes'];
if (!in_array($sort_col, $allowed_sorts)) {
    $sort_col = ($current_platform && $has['release_no']) ? 'release_no' : 'title';
}
$sort_sql_col = ['owned' => 'is_owned', 'cib' => 'is_cib', 'legacy' => 'is_legacy'][$sort_col] ?? $sort_col;

// Fetch Games for Active Platform
$games = [];
$total_count = 0;
$owned_count = 0;
$cib_count = 0;
$physical_count = 0;
$digital_count = 0;
$legacy_count = 0;
$legacy_owned = 0;
$legacy_missing = 0;
$completion = 0;

if ($current_platform) {
    if ($sort_col === 'release_no') {
        $order_clause = "CASE WHEN release_no IS NULL THEN 1 ELSE 0 END, release_no $sort_dir, title ASC";
    } else {
        $order_clause = "$sort_sql_col $sort_dir, title ASC";
    }

    try {
        $stmt = $db->prepare("SELECT * FROM games WHERE platform_id = ? ORDER BY $order_clause");
        $stmt->execute([$active_platform_id]);
        $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Attach custom field values to each game (['cf'][field_id] => value)
        $cf_values = [];
        if (!empty($custom_fields)) {
            $vs = $db->prepare("SELECT v.game_id, v.field_id, v.value FROM game_field_values v JOIN games g ON g.id = v.game_id WHERE g.platform_id = ?");
            $vs->execute([$active_platform_id]);
            foreach ($vs->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $cf_values[(int)$r['game_id']][(int)$r['field_id']] = (string)$r['value'];
            }
        }
        foreach ($games as &$game_ref) {
            $game_ref['cf'] = $cf_values[(int)$game_ref['id']] ?? [];
        }
        unset($game_ref);

        $total_count = count($games);
        foreach ($games as $g) {
            if ($g['is_owned']) $owned_count++;
            if (!empty($g['is_cib'])) $cib_count++;
            if (($g['media_type'] ?? 'Physical') === 'Digital') $digital_count++;
            else $physical_count++;

            if ($g['is_legacy']) {
                $legacy_count++;
                if ($g['is_owned']) $legacy_owned++;
                else $legacy_missing++;
            }
        }
        $completion = $total_count > 0 ? round(($owned_count / $total_count) * 100, 1) : 0;
    } catch (Exception $e) {}
}

// Fields a KPI condition can be built on: the built-ins every platform could
// plausibly have, plus this platform's currently-enabled custom fields.
// Keyed by field key for quick lookup (kpi_summary(), the JS field dropdown).
$kpi_fields = [
    'is_owned'  => ['label' => $lbl['status'] . ' (Owned)', 'type' => 'yesno'],
    'is_cib'    => ['label' => $lbl['cib'] . ' (CIB)', 'type' => 'yesno'],
    'is_legacy' => ['label' => $lbl['legacy'], 'type' => 'yesno'],
    'region'      => ['label' => $lbl['region'], 'type' => 'select', 'choices' => BUILTIN_CHOICES['region']],
    'media_type'  => ['label' => $lbl['media_type'], 'type' => 'select', 'choices' => BUILTIN_CHOICES['media_type']],
    'line_series' => ['label' => $lbl['line_series'], 'type' => 'text'],
    'notes'       => ['label' => $lbl['notes'], 'type' => 'text'],
    'title'       => ['label' => $lbl['title'], 'type' => 'text'],
    'release_no'  => ['label' => $lbl['release_no'], 'type' => 'number'],
];
foreach ($custom_fields as $cf) {
    $kpi_fields['cf' . (int)$cf['id']] = [
        'label' => $cf['label'],
        'type' => $cf['field_type'],
        'choices' => $cf['field_type'] === 'select' ? field_choice_list($cf) : [],
    ];
}

// This platform's active KPIs, in display order, with their computed values.
$active_kpis = [];
if ($current_platform) {
    try {
        $stmt = $db->prepare("SELECT k.* FROM kpis k JOIN platform_kpis pk ON pk.kpi_id = k.id WHERE pk.platform_id = ? ORDER BY k.sort_order ASC, k.id ASC");
        $stmt->execute([(int)$active_platform_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $kpi) {
            $result = kpi_compute($kpi, $games);
            $active_kpis[] = array_merge($kpi, $result);
        }
    } catch (Exception $e) {}
}

// All KPI definitions app-wide, for the Manage KPIs list (mirrors $all_fields).
$all_kpis = [];
try {
    $all_kpis = $db->query("SELECT * FROM kpis ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$enabled_kpi_ids = [];
if ($current_platform) {
    try {
        $stmt = $db->prepare("SELECT kpi_id FROM platform_kpis WHERE platform_id = ?");
        $stmt->execute([(int)$active_platform_id]);
        $enabled_kpi_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Exception $e) {}
}
