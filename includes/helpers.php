<?php
/**
 * Pure helper functions: badge/formatting helpers, the KPI engine,
 * field-value helpers, and the render_* functions that build small
 * reusable bits of HTML (the Enabled Fields checklist, a custom-field
 * form control). No $_POST/$_SESSION access here - see actions.php
 * for request handling and render_data.php for page-level queries.
 */

function choice_badge_class($c) {
    if (!isset(CHOICE_COLORS[$c])) $c = 'slate';
    return "bg-$c-100 text-$c-800 border border-$c-200";
}

// Renders a badge: a clickable button when $quick is on (admin + quick-change enabled), else a plain span.
// $field_ref is 'b:<builtin key>' or 'c:<custom field id>' — see the cycle_field_ajax handler.
function badge_tag($quick, $game_id, $field_ref, $classes, $inner, $rounded_full = true) {
    $shape = $rounded_full ? 'rounded-full' : 'rounded';
    if ($quick) {
        return '<button type="button" onclick="quickToggleField(' . (int)$game_id . ', ' . h(json_encode($field_ref)) . ', this)" title="Click to change" class="inline-flex items-center gap-1 px-2.5 py-0.5 ' . $shape . ' text-xs font-semibold transition hover:brightness-95 hover:shadow-sm cursor-pointer ' . $classes . '">' . $inner . '</button>';
    }
    return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 ' . $shape . ' text-xs font-semibold ' . $classes . '">' . $inner . '</span>';
}

// Choice color map (option => color key) of a choice-list field
function field_colors(array $f) {
    $m = json_decode((string)($f['colors'] ?? ''), true);
    return is_array($m) ? $m : [];
}

// A table header cell: sortable (clickable, with the sort icon) or a plain label,
// depending on whether the field's "Sortable column" option is on. $col_key
// tags the cell with data-col="<key>" so the column-reorder feature can find
// and move it (defaults to $sort_key, which matches except for Status).
function col_th($label, $sort_key, $numeric, $center, $sortable, $extra_classes = '', $col_key = null) {
    $align = $center ? ' text-center' : '';
    $data_col = ' data-col="' . h($col_key !== null ? $col_key : $sort_key) . '"';
    if (!$sortable) {
        return '<th class="px-2 py-2 sm:px-4 sm:py-3.5' . $align . ' ' . $extra_classes . '"' . $data_col . '><span>' . h($label) . '</span></th>';
    }
    return '<th class="px-2 py-2 sm:px-4 sm:py-3.5' . $align . ' sortable ' . $extra_classes . '"' . $data_col . ' onclick="sortTableByColumn(' . h(json_encode((string)$sort_key)) . ', ' . ($numeric ? 'true' : 'false') . ')" title="Click to sort by ' . h($label) . '">'
         . '<div class="flex items-center' . ($center ? ' justify-center' : '') . ' gap-1.5"><span>' . h($label) . '</span><i class="fa-solid fa-sort text-slate-400 text-[11px]"></i></div></th>';
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function str_cut($s, $n) {
    return function_exists('mb_substr') ? mb_substr($s, 0, $n, 'UTF-8') : substr($s, 0, $n);
}

// Choice-list options of a field, as a plain array of strings
function field_options(array $f) {
    $o = json_decode((string)($f['options'] ?? ''), true);
    return is_array($o) ? array_values($o) : [];
}

// Inserts any missing built-in field definitions. Returns how many were added.
function seed_builtin_fields(PDO $db) {
    $added = 0;
    $st = $db->prepare("INSERT OR IGNORE INTO fields (field_key, label, description, field_type, options, is_builtin, sort_order, sortable, quick_toggle) VALUES (?, ?, ?, ?, NULL, 1, ?, 1, ?)");
    foreach (BUILTIN_FIELDS as $key => $d) {
        $st->execute([$key, $d['label'], $d['description'], $d['type'], $d['order'], $key === 'status' ? 1 : 0]);
        $added += $st->rowCount();
    }
    return $added;
}

// Inserts any missing built-in KPI definitions (identified by builtin_key, so
// re-running never duplicates or resurrects one the admin deleted on purpose -
// see the one-time upgrade in the bootstrap block, which is the only other
// caller). Returns how many were added.
function seed_builtin_kpis(PDO $db) {
    $added = 0;
    $st = $db->prepare("INSERT OR IGNORE INTO kpis
        (label, color, calc_type, n1_field, n1_op, n1_value, n2_field, n2_op, n2_value, d1_field, d1_op, d1_value, is_builtin, builtin_key, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '', '', '', 1, ?, ?)");
    foreach (BUILTIN_KPIS as $key => $d) {
        $st->execute([$d['label'], $d['color'], $d['calc_type'], $d['n1_field'], $d['n1_op'], $d['n1_value'], $d['n2_field'], $d['n2_op'], $d['n2_value'], $key, $d['order']]);
        $added += $st->rowCount();
    }
    return $added;
}

// A sensible set of built-in KPIs to enable by default for a newly-created
// platform, based only on which built-in fields it has - a one-off starting
// point, not an ongoing mode. Everything about which KPIs show, for any
// platform, is then fully in the admin's hands via "Manage KPIs".
function default_kpi_keys_for(array $has) {
    $keys = ['titles_total'];
    if (!empty($has['status'])) $keys = array_merge($keys, ['owned', 'completion']);
    if (!empty($has['legacy'])) $keys = array_merge($keys, ['legacy_total', 'legacy_owned', 'legacy_missing']);
    if (!empty($has['cib'])) $keys[] = 'cib';
    if (!empty($has['media_type'])) $keys = array_merge($keys, ['physical', 'digital']);
    return $keys;
}

// Enables the given built-in KPIs (by builtin_key) on a platform, ignoring any
// key that doesn't exist (e.g. an admin deleted that built-in).
function enable_builtin_kpis(PDO $db, $platform_id, array $keys) {
    if (empty($keys)) return;
    $ids = $db->query("SELECT id, builtin_key FROM kpis WHERE is_builtin = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
    $ins = $db->prepare("INSERT OR IGNORE INTO platform_kpis (platform_id, kpi_id) VALUES (?, ?)");
    foreach ($keys as $key) {
        $id = array_search($key, $ids, true);
        if ($id !== false) $ins->execute([(int)$platform_id, (int)$id]);
    }
}

// A single game's value for a KPI condition field: a built-in column, or
// 'cf<id>' for a custom field's value.
function kpi_field_value(array $g, $field) {
    switch ($field) {
        case 'is_owned':  return !empty($g['is_owned']) ? '1' : '0';
        case 'is_cib':    return !empty($g['is_cib']) ? '1' : '0';
        case 'is_legacy': return !empty($g['is_legacy']) ? '1' : '0';
        case 'region':      return (string)($g['region'] ?? '');
        case 'media_type':  return (string)($g['media_type'] ?? '');
        case 'line_series': return (string)($g['line_series'] ?? '');
        case 'notes':       return (string)($g['notes'] ?? '');
        case 'title':       return (string)($g['title'] ?? '');
        case 'release_no':  return $g['release_no'] !== null ? (string)$g['release_no'] : '';
    }
    if (strpos($field, 'cf') === 0 && ctype_digit(substr($field, 2))) {
        return (string)($g['cf'][(int)substr($field, 2)] ?? '');
    }
    return '';
}

// A single game's value for one field, formatted for CSV export (Yes/No
// instead of 1/0 for yes-no fields, a plain string for everything else).
function csv_export_value(array $g, array $f) {
    if (!empty($f['is_builtin'])) {
        switch ($f['field_key']) {
            case 'title':       return (string)($g['title'] ?? '');
            case 'release_no':  return $g['release_no'] !== null ? (string)$g['release_no'] : '';
            case 'line_series': return (string)($g['line_series'] ?? '');
            case 'legacy':      return !empty($g['is_legacy']) ? 'Yes' : 'No';
            case 'status':      return !empty($g['is_owned']) ? 'Yes' : 'No';
            case 'cib':         return !empty($g['is_cib']) ? 'Yes' : 'No';
            case 'region':      return (string)($g['region'] ?? '');
            case 'media_type':  return (string)($g['media_type'] ?? '');
            case 'notes':       return (string)($g['notes'] ?? '');
        }
        return '';
    }
    $v = (string)($g['cf'][(int)$f['id']] ?? '');
    if ($f['field_type'] === 'yesno') return $v === '1' ? 'Yes' : 'No';
    return $v;
}

// Whether one game matches a single KPI condition. An empty $field means "no
// filter" (always matches) - that's how a KPI's "all titles" case is expressed.
function kpi_condition_match(array $g, $field, $op, $value) {
    if ($field === '' || $field === null) return true;
    $v = kpi_field_value($g, $field);
    switch ($op) {
        case 'true':  return $v === '1';
        case 'false': return $v !== '1';
        case 'eq':    return strcasecmp($v, (string)$value) === 0;
        case 'neq':   return strcasecmp($v, (string)$value) !== 0;
        case 'contains':     return $value !== '' && stripos($v, (string)$value) !== false;
        case 'not_contains': return $value === '' || stripos($v, (string)$value) === false;
        case 'empty':     return trim($v) === '';
        case 'not_empty': return trim($v) !== '';
        case 'gt':  return is_numeric($v) && is_numeric($value) && (float)$v > (float)$value;
        case 'lt':  return is_numeric($v) && is_numeric($value) && (float)$v < (float)$value;
        case 'gte': return is_numeric($v) && is_numeric($value) && (float)$v >= (float)$value;
        case 'lte': return is_numeric($v) && is_numeric($value) && (float)$v <= (float)$value;
        default: return true;
    }
}

// Counts games matching BOTH of up to two AND'ed conditions (the second is
// optional - pass an empty field to skip it).
function kpi_count_matches(array $games, $f1, $op1, $v1, $f2, $op2, $v2) {
    $n = 0;
    foreach ($games as $g) {
        if (kpi_condition_match($g, $f1, $op1, $v1) && kpi_condition_match($g, $f2, $op2, $v2)) $n++;
    }
    return $n;
}

// Computes one KPI's display value against the current platform's game list.
// Returns ['count' => int, 'is_pct' => bool, 'display' => string].
function kpi_compute(array $kpi, array $games) {
    if ($kpi['calc_type'] === 'top_value') {
        // "Most Common": among games matching the optional n1/n2 filter, group
        // by group_field and report whichever value occurs most often. Ties
        // are broken alphabetically, for a stable, predictable result.
        $tally = [];
        foreach ($games as $g) {
            if (!kpi_condition_match($g, $kpi['n1_field'], $kpi['n1_op'], $kpi['n1_value'])) continue;
            if (!kpi_condition_match($g, $kpi['n2_field'], $kpi['n2_op'], $kpi['n2_value'])) continue;
            $v = trim(kpi_field_value($g, $kpi['group_field']));
            if ($v === '') continue;
            $tally[$v] = ($tally[$v] ?? 0) + 1;
        }
        if (empty($tally)) {
            return ['count' => 0, 'is_pct' => false, 'display' => '—'];
        }
        ksort($tally, SORT_NATURAL | SORT_FLAG_CASE);
        $top_value = null; $top_count = -1;
        foreach ($tally as $v => $c) {
            if ($c > $top_count) { $top_count = $c; $top_value = $v; }
        }
        return ['count' => $top_count, 'is_pct' => false, 'display' => $top_value . ' (' . $top_count . ')'];
    }
    if ($kpi['calc_type'] === 'percentage') {
        // "% of [denominator] that are [numerator]" - the numerator has to be
        // evaluated only among games that ALSO match the denominator (not
        // independently), or e.g. "% of Physical titles that are Owned" could
        // count a digital owned title into the numerator and read over 100%.
        $den = 0;
        $num = 0;
        foreach ($games as $g) {
            $in_den = kpi_condition_match($g, $kpi['d1_field'], $kpi['d1_op'], $kpi['d1_value'])
                && kpi_condition_match($g, $kpi['d2_field'], $kpi['d2_op'], $kpi['d2_value']);
            if (!$in_den) continue;
            $den++;
            if (kpi_condition_match($g, $kpi['n1_field'], $kpi['n1_op'], $kpi['n1_value'])
                && kpi_condition_match($g, $kpi['n2_field'], $kpi['n2_op'], $kpi['n2_value'])) {
                $num++;
            }
        }
        $pct = $den > 0 ? round(($num / $den) * 100, 1) : 0;
        return ['count' => $num, 'is_pct' => true, 'display' => $pct . '%'];
    }
    $num = kpi_count_matches($games, $kpi['n1_field'], $kpi['n1_op'], $kpi['n1_value'], $kpi['n2_field'], $kpi['n2_op'], $kpi['n2_value']);
    return ['count' => $num, 'is_pct' => false, 'display' => (string)$num];
}

// A short, human-readable summary of what a KPI counts, e.g. "Owned = Yes AND
// Legacy = Yes" or "% of Owned = Yes" - shown in the Manage KPIs list so an
// admin can tell KPIs apart without opening each one.
function kpi_condition_label(array $kpi_fields_by_key, $field, $op, $value) {
    if ($field === '') return 'All Titles';
    $f = $kpi_fields_by_key[$field] ?? null;
    $flabel = $f ? $f['label'] : $field;
    $ops = ['true' => 'is Yes', 'false' => 'is No', 'eq' => 'is', 'neq' => 'is not', 'contains' => 'contains', 'not_contains' => "doesn't contain", 'empty' => 'is empty', 'not_empty' => 'is not empty', 'gt' => '>', 'lt' => '<', 'gte' => '≥', 'lte' => '≤'];
    $opLabel = $ops[$op] ?? $op;
    if (in_array($op, ['true', 'false', 'empty', 'not_empty'], true)) return "$flabel $opLabel";
    return "$flabel $opLabel \"$value\"";
}
function kpi_summary(array $kpi, array $kpi_fields_by_key) {
    $n = kpi_condition_label($kpi_fields_by_key, $kpi['n1_field'], $kpi['n1_op'], $kpi['n1_value']);
    if ($kpi['n2_field'] !== '') $n .= ' AND ' . kpi_condition_label($kpi_fields_by_key, $kpi['n2_field'], $kpi['n2_op'], $kpi['n2_value']);
    if ($kpi['calc_type'] === 'top_value') {
        $gf = $kpi_fields_by_key[$kpi['group_field']] ?? null;
        $glabel = $gf ? $gf['label'] : ($kpi['group_field'] ?: '(no field chosen)');
        return $kpi['n1_field'] === '' ? "Most common $glabel" : "Most common $glabel among $n";
    }
    if ($kpi['calc_type'] !== 'percentage') return 'Count: ' . $n;
    $d = kpi_condition_label($kpi_fields_by_key, $kpi['d1_field'], $kpi['d1_op'], $kpi['d1_value']);
    if ($kpi['d2_field'] !== '') $d .= ' AND ' . kpi_condition_label($kpi_fields_by_key, $kpi['d2_field'], $kpi['d2_op'], $kpi['d2_value']);
    return "% of $d that are $n";
}

// Returns [ $has, $custom, $meta ]
//   $has    = built-in field key => bool (enabled on this platform?)
//   $custom = list of enabled custom field rows for this platform
//   $meta   = built-in field key => full field row (only for enabled built-ins)
function get_platform_fields(PDO $db, $platform_id) {
    $has = array_fill_keys(array_keys(BUILTIN_FIELDS), false);
    $custom = [];
    $meta = [];
    try {
        $st = $db->prepare("SELECT f.* FROM fields f JOIN platform_fields pf ON pf.field_id = f.id WHERE pf.platform_id = ? ORDER BY f.sort_order ASC, f.id ASC");
        $st->execute([(int)$platform_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
            if ($f['is_builtin']) {
                if (array_key_exists($f['field_key'], $has)) {
                    $has[$f['field_key']] = true;
                    $meta[$f['field_key']] = $f;
                }
            } else {
                $custom[] = $f;
            }
        }
    } catch (Exception $e) {}
    return [$has, $custom, $meta];
}

// The list of choices a field can cycle through: fixed choices for a built-in
// select field (region / media_type), or the stored options for a custom one.
function field_choice_list(array $f) {
    if (!empty($f['is_builtin']) && isset(BUILTIN_CHOICES[$f['field_key']])) {
        return BUILTIN_CHOICES[$f['field_key']];
    }
    return field_options($f);
}

// Whether a field is allowed to be quick-changed right in the table (admin-only,
// and only meaningful for Yes/No and Choice-list fields)
function field_is_quick_toggle(array $f) {
    return !empty($f['quick_toggle']) && in_array($f['field_type'], ['yesno', 'select'], true);
}

// Replaces the set of fields enabled on a platform
function sync_platform_fields(PDO $db, $platform_id, array $field_ids) {
    $valid = array_map('intval', $db->query("SELECT id FROM fields")->fetchAll(PDO::FETCH_COLUMN));
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM platform_fields WHERE platform_id = ?")->execute([(int)$platform_id]);
        $ins = $db->prepare("INSERT OR IGNORE INTO platform_fields (platform_id, field_id) VALUES (?, ?)");
        foreach (array_unique($field_ids) as $fid) {
            if (in_array($fid, $valid, true)) $ins->execute([(int)$platform_id, $fid]);
        }
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

// Cleans a posted value for a custom field ('' means "no value")
function normalize_field_value(array $f, $raw) {
    $raw = is_string($raw) ? trim($raw) : '';
    switch ($f['field_type']) {
        case 'yesno':  return $raw === '1' ? '1' : '0';
        case 'number': return preg_match('/^-?\d+(\.\d+)?$/', $raw) ? $raw : '';
        case 'select': return str_cut($raw, 200);
        default:       return str_cut($raw, 500);
    }
}

// Saves posted custom values ($posted = $_POST['cf']) for the enabled custom fields only
function save_custom_values(PDO $db, $game_id, array $custom_fields, $posted) {
    if (!is_array($posted)) return;
    $del = $db->prepare("DELETE FROM game_field_values WHERE game_id = ? AND field_id = ?");
    $put = $db->prepare("INSERT OR REPLACE INTO game_field_values (game_id, field_id, value) VALUES (?, ?, ?)");
    foreach ($custom_fields as $f) {
        $fid = (int)$f['id'];
        if (!array_key_exists($fid, $posted)) continue;
        $val = normalize_field_value($f, $posted[$fid]);
        if ($val === '') {
            $del->execute([(int)$game_id, $fid]);
        } else {
            $put->execute([(int)$game_id, $fid, $val]);
        }
    }
}

// The "Enabled Fields" checklist (description first, column name underneath)
function render_field_checklist(array $all_fields, array $checked_ids, $use_defaults) {
    if (empty($all_fields)) {
        return '<p class="text-xs text-slate-400 italic">No fields defined yet. Use “Manage Fields” to create one.</p>';
    }
    $accent = ['status' => 'text-emerald-700', 'cib' => 'text-indigo-700', 'region' => 'text-purple-700', 'media_type' => 'text-amber-700'];
    $out = '';
    foreach ($all_fields as $f) {
        $is_checked = $use_defaults ? in_array($f['field_key'], ['status', 'notes'], true) : !empty($checked_ids[(int)$f['id']]);
        $cls  = $accent[$f['field_key']] ?? 'text-slate-700';
        $type = FIELD_TYPES[$f['field_type']] ?? 'Text';
        $out .= '<label class="flex items-start gap-2 cursor-pointer">'
              . '<input type="checkbox" name="fields[]" value="' . (int)$f['id'] . '"' . ($is_checked ? ' checked' : '') . ' class="h-4 w-4 mt-0.5 text-indigo-600 rounded">'
              . '<span class="min-w-0">'
              . '<span class="block text-sm font-medium ' . $cls . '">' . h($f['description'] !== '' ? $f['description'] : $f['label']) . '</span>'
              . '<span class="block text-[11px] text-slate-400">Table column: <strong class="text-slate-500">' . h($f['label']) . '</strong> · ' . h($type) . '</span>'
              . '</span></label>';
    }
    return $out;
}

// Form control for one custom field ($mode is 'add' or 'edit')
function render_custom_input(array $f, $mode) {
    $id    = (int)$f['id'];
    $dom   = $mode . '_cf_' . $id;
    $name  = 'cf[' . $id . ']';
    $extra = ($mode === 'edit') ? ' edit-cf' : '';
    $data  = ' data-field-id="' . $id . '"';
    $label = h($f['label']);
    $tip   = h($f['description'] !== '' ? $f['description'] : $f['label']);
    $cls   = 'w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none';

    if ($f['field_type'] === 'yesno') {
        return '<div class="flex items-center gap-2 pt-1" title="' . $tip . '">'
             . '<input type="hidden" name="' . $name . '" value="0">'
             . '<input type="checkbox" id="' . $dom . '" name="' . $name . '" value="1" class="h-4 w-4 text-indigo-600 rounded' . $extra . '"' . $data . '>'
             . '<label for="' . $dom . '" class="text-sm text-slate-700 font-medium">' . $label . '</label>'
             . '</div>';
    }
    if ($f['field_type'] === 'select') {
        $control = '<select id="' . $dom . '" name="' . $name . '" class="' . $cls . $extra . '"' . $data . '><option value="">—</option>';
        foreach (field_options($f) as $opt) {
            $control .= '<option value="' . h($opt) . '">' . h($opt) . '</option>';
        }
        $control .= '</select>';
    } elseif ($f['field_type'] === 'number') {
        $control = '<input type="number" step="any" id="' . $dom . '" name="' . $name . '" class="' . $cls . $extra . '"' . $data . '>';
    } else {
        $control = '<input type="text" maxlength="500" id="' . $dom . '" name="' . $name . '" class="' . $cls . $extra . '"' . $data . '>';
    }
    return '<div title="' . $tip . '"><label for="' . $dom . '" class="block text-xs font-semibold text-slate-600 mb-1">' . $label . '</label>' . $control . '</div>';
}

// Handles an uploaded cover image file (validates mime/extension, moves to uploads/covers/,
// unlinks old file if replacing). Returns relative path or null.
function handle_cover_upload(?array $file, ?string $existing_path = null): ?string {
    if (!$file || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return $existing_path;
    }

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions)) {
        throw new Exception("Invalid image format. Allowed formats: JPG, PNG, WEBP, GIF.");
    }

    if ($file['size'] > 8 * 1024 * 1024) {
        throw new Exception("Image file is too large (max 8MB).");
    }

    $info = @getimagesize($file['tmp_name']);
    if (!$info) {
        throw new Exception("The uploaded file is not a valid image.");
    }

    $upload_dir = dirname(__DIR__) . '/uploads/covers';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    $filename = 'cover_' . uniqid('', true) . '.' . $ext;
    $target_file = $upload_dir . '/' . $filename;

    if (!copy($file['tmp_name'], $target_file)) {
        if (!move_uploaded_file($file['tmp_name'], $target_file)) {
            throw new Exception("Failed to save uploaded image.");
        }
    } else {
        @unlink($file['tmp_name']);
    }

    if ($existing_path) {
        delete_cover_file($existing_path);
    }

    return 'uploads/covers/' . $filename;
}

// Safely deletes a cover image file from disk
function delete_cover_file(?string $rel_path): void {
    if (!$rel_path) return;
    $clean_path = str_replace(['..', '\\'], ['', '/'], $rel_path);
    $full_path = dirname(__DIR__) . '/' . ltrim($clean_path, '/');
    if (file_exists($full_path) && is_file($full_path)) {
        @unlink($full_path);
    }
}

// Safely deletes all uploaded cover artwork files from disk
function wipe_all_cover_files(): int {
    $dir = dirname(__DIR__) . '/uploads/covers';
    $count = 0;
    if (is_dir($dir)) {
        $files = scandir($dir);
        if ($files) {
            foreach ($files as $f) {
                if ($f !== '.' && $f !== '..' && $f !== '.gitkeep') {
                    $full_path = $dir . '/' . $f;
                    if (is_file($full_path)) {
                        if (@unlink($full_path)) {
                            $count++;
                        }
                    }
                }
            }
        }
    }
    return $count;
}

// Renders the cover image thumbnail button for a table cell
function render_cover_thumbnail(?string $image_path, ?string $title): string {
    if (empty($image_path)) return '';
    $safe_src = h($image_path);
    $js_src = h(json_encode($image_path));
    $js_title = h(json_encode($title ?: 'Cover Artwork'));
    return '<button type="button" onclick="openImagePopup(' . $js_src . ', ' . $js_title . ')" class="relative group/cover flex-shrink-0 cursor-pointer focus:outline-none mr-2.5 inline-block align-middle" title="View cover artwork">'
         . '<img src="' . $safe_src . '" alt="Cover" class="w-8 h-8 rounded-lg object-cover shadow-sm border border-slate-200 group-hover/cover:ring-2 group-hover/cover:ring-indigo-500 group-hover/cover:scale-105 transition">'
         . '<span class="absolute inset-0 bg-black/35 rounded-lg opacity-0 group-hover/cover:opacity-100 flex items-center justify-center transition text-white text-[10px]"><i class="fa-solid fa-magnifying-glass-plus"></i></span>'
         . '</button>';
}
