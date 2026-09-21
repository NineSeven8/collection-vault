
    <!-- Hidden Single Delete Form -->
    <form id="singleDeleteForm" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete_game">
        <input type="hidden" name="game_id" id="delete_game_id">
        <input type="hidden" name="platform_id" value="<?= $active_platform_id ?>">
    </form>

    <!-- Modal: Rename Application (Admin Only) -->
    <?php if ($is_admin): ?>
    <div id="appNameModal" class="hidden fixed inset-0 bg-slate-900/60 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-pen text-indigo-600"></i> Rename Application
                </h3>
                <button onclick="document.getElementById('appNameModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_app_name">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">App Name</label>
                    <input type="text" name="app_name" value="<?= htmlspecialchars($app_name) ?>" required placeholder="e.g. My Retro Vault" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none font-medium">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('appNameModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">Save Name</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal: Admin Login -->
    <div id="loginModal" class="<?= $login_error ? '' : 'hidden' ?> fixed inset-0 bg-slate-900/60 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-lock text-indigo-600"></i> Admin Sign In
                </h3>
                <button onclick="document.getElementById('loginModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <?php if ($login_error): ?>
                <div class="bg-rose-50 text-rose-700 text-xs px-3 py-2 rounded-lg mb-3 font-medium"><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="login">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Username</label>
                    <input type="text" name="username" required placeholder="admin" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Password</label>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <div class="text-[11px] text-slate-400 mt-1 italic">Default credentials: <strong>admin</strong> / <strong>admin123</strong></div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('loginModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">Log In</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Change Password -->
    <?php if ($is_admin): ?>
    <div id="passwordModal" class="hidden fixed inset-0 bg-slate-900/60 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-900">Change Admin Password</h3>
                <button onclick="document.getElementById('passwordModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="change_password">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">New Password (Min 6 chars)</label>
                    <input type="password" name="new_password" required minlength="6" placeholder="Enter new strong password" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('passwordModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">Update</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal: Database Backup (Export / Import) -->
    <?php if ($is_admin): ?>
    <div id="backupModal" class="hidden fixed inset-0 bg-slate-900/60 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2"><i class="fa-solid fa-database text-indigo-600"></i> Database Backup</h3>
                <button onclick="document.getElementById('backupModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>

            <div class="space-y-5">
                <div>
                    <div class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Export</div>
                    <p class="text-xs text-slate-500 mb-3">Downloads a full backup — every platform, title, field and setting — as a single file you keep.</p>
                    <form method="POST" onsubmit="setTimeout(() => document.getElementById('backupModal').classList.add('hidden'), 0);">
                        <input type="hidden" name="action" value="export_db">
                        <button type="submit" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">
                            <i class="fa-solid fa-download mr-1.5"></i> Download Backup
                        </button>
                    </form>
                </div>

                <div class="border-t border-slate-200 pt-5">
                    <div class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Import</div>
                    <p class="text-xs text-rose-600 mb-3"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Replaces everything currently in the database with the uploaded file. The current database is kept as a dated backup on the server, but only do this if you're sure.</p>
                    <form method="POST" enctype="multipart/form-data" onsubmit="if (!confirm('This will REPLACE all current platforms, titles and settings with the uploaded backup. Continue?')) return false; setTimeout(() => document.getElementById('backupModal').classList.add('hidden'), 0);">
                        <input type="hidden" name="action" value="import_db">
                        <input type="file" name="backup_file" accept=".db,.sqlite,.sqlite3" required class="w-full text-sm text-slate-600 border border-slate-300 rounded-lg px-3 py-2 mb-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <button type="submit" class="w-full px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-sm font-medium">
                            <i class="fa-solid fa-upload mr-1.5"></i> Upload &amp; Restore
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal: Export Titles (CSV) -->
    <?php if ($is_admin): ?>
    <div id="exportCsvModal" class="hidden fixed inset-0 bg-slate-900/60 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2"><i class="fa-solid fa-file-csv text-indigo-600"></i> Export Titles (CSV)</h3>
                <button onclick="document.getElementById('exportCsvModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>

            <?php if (!$current_platform): ?>
                <p class="text-sm text-slate-400 italic">Select a platform first.</p>
            <?php else:
                $csv_fields = array_values(array_filter($all_fields, function($f) use ($checked_field_ids) {
                    return !empty($checked_field_ids[(int)$f['id']]);
                }));
                $csv_column_rank = array_flip($column_order);
                usort($csv_fields, function($a, $b) use ($csv_column_rank) {
                    $ka = $a['is_builtin'] ? $a['field_key'] : ('cf' . (int)$a['id']);
                    $kb = $b['is_builtin'] ? $b['field_key'] : ('cf' . (int)$b['id']);
                    return ($csv_column_rank[$ka] ?? PHP_INT_MAX) <=> ($csv_column_rank[$kb] ?? PHP_INT_MAX);
                });
            ?>
                <p class="text-xs text-slate-500 mb-3">Downloads the titles in <strong><?= h($current_platform['name']) ?></strong> as a spreadsheet-ready CSV. Pick which fields to include.</p>
                <?php if (empty($csv_fields)): ?>
                    <p class="text-xs text-slate-400 italic mb-3">No fields enabled on this platform yet.</p>
                <?php else: ?>
                    <form method="POST" id="csvExportForm">
                        <input type="hidden" name="action" value="export_csv">
                        <input type="hidden" name="platform_id" value="<?= (int)$active_platform_id ?>">
                        <div class="flex justify-between items-center mb-1.5">
                            <span class="text-[11px] text-slate-400">Fields to include</span>
                            <span class="flex gap-2 text-[11px] font-semibold">
                                <button type="button" onclick="setCsvFields(true)" class="text-indigo-600 hover:text-indigo-800">All</button>
                                <button type="button" onclick="setCsvFields(false)" class="text-slate-400 hover:text-slate-600">None</button>
                            </span>
                        </div>
                        <div class="border border-slate-200 rounded-xl divide-y divide-slate-200 mb-3 max-h-64 overflow-y-auto">
                            <?php foreach ($csv_fields as $f): ?>
                                <label class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-slate-50">
                                    <input type="checkbox" name="fields[]" value="<?= (int)$f['id'] ?>" checked class="csv-field-cb h-4 w-4 text-indigo-600 rounded">
                                    <span class="text-sm text-slate-700"><?= h($f['label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" onclick="if (!document.querySelectorAll('.csv-field-cb:checked').length) { alert('Choose at least one field.'); return false; } setTimeout(() => document.getElementById('exportCsvModal').classList.add('hidden'), 0);" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">
                            <i class="fa-solid fa-download mr-1.5"></i> Export CSV
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal: Add Game -->
    <div id="addGameModal" class="hidden fixed inset-0 bg-slate-900/60 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-900">Add Title to <?= htmlspecialchars($current_platform['name']) ?></h3>
                <button onclick="document.getElementById('addGameModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_game">
                <input type="hidden" name="platform_id" value="<?= $active_platform_id ?>">
                
                <?php if ($has['title']): ?>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1"><?= h($lbl['title']) ?> <span class="font-normal text-slate-400">(optional)</span></label>
                    <input type="text" name="title" id="add_title" placeholder="e.g. Sonic the Hedgehog - leave blank if you don't need one" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php if ($has['release_no']): ?>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1"><?= h($lbl['release_no']) ?></label>
                            <input type="number" name="release_no" placeholder="e.g. 89" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                    <?php endif; ?>

                    <?php if ($has['line_series']): ?>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1"><?= h($lbl['line_series']) ?></label>
                            <input type="text" name="line_series" list="series_list" placeholder="e.g. Arcade, Console" onclick="showSeriesSuggestions(this)" onfocus="showSeriesSuggestions(this)" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                    <?php endif; ?>

                    <?php if ($has['region']): ?>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1"><?= h($lbl['region']) ?></label>
                            <select name="region" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                <option value="EUR (PAL)" selected>EUR (PAL)</option>
                                <option value="USA (NTSC)">USA (NTSC)</option>
                                <option value="JPN (NTSC-J)">JPN (NTSC-J)</option>
                                <option value="Region Free">Region Free</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($has['media_type']): ?>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1"><?= h($lbl['media_type']) ?></label>
                            <select name="media_type" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                <option value="Physical" selected>Physical (Cartridge/Disc)</option>
                                <option value="Digital">Digital (eShop/Download)</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($custom_fields as $cf) { if ($cf['field_type'] !== 'yesno') echo render_custom_input($cf, 'add'); } ?>
                </div>

                <?php if ($has['cib']): ?>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5"><?= h($lbl['cib']) ?></label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input type="radio" name="is_cib" value="1" checked class="text-indigo-600 focus:ring-indigo-500">
                                <span class="font-medium text-slate-800">CIB (Complete in Box)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input type="radio" name="is_cib" value="0" class="text-indigo-600 focus:ring-indigo-500">
                                <span class="text-slate-600">NOT CIB (Loose / Cart only)</span>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($has['legacy']): ?>
                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" id="add_is_legacy" name="is_legacy" value="1" class="h-4 w-4 text-indigo-600 rounded">
                        <label for="add_is_legacy" class="text-sm text-slate-700 font-medium">Discontinued / Legacy Title</label>
                    </div>
                <?php endif; ?>

                <?php if ($has['status']): ?>
                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" id="add_is_owned" name="is_owned" value="1" checked class="h-4 w-4 text-indigo-600 rounded">
                        <label for="add_is_owned" class="text-sm text-slate-700 font-medium">Currently Owned in Collection</label>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="is_owned" value="1">
                <?php endif; ?>

                <?php foreach ($custom_fields as $cf) { if ($cf['field_type'] === 'yesno') echo render_custom_input($cf, 'add'); } ?>

                <?php if ($has['notes']): ?>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1"><?= h($lbl['notes']) ?></label>
                        <input type="text" name="notes" placeholder="e.g. Mint condition" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                <?php endif; ?>

                <div class="flex justify-end gap-3 pt-3">
                    <button type="button" onclick="document.getElementById('addGameModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">Save Title</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Game -->
    <div id="editGameModal" class="hidden fixed inset-0 bg-slate-900/60 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-900">Edit Title Details</h3>
                <button onclick="document.getElementById('editGameModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form method="POST" id="editGameForm" class="space-y-4" onsubmit="return ajaxSubmitEditForm(event)">
                <input type="hidden" name="action" value="edit_game">
                <input type="hidden" name="game_id" id="edit_game_id">
                <input type="hidden" name="platform_id" value="<?= $active_platform_id ?>">

                <?php if ($has['title']): ?>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1"><?= h($lbl['title']) ?> <span class="font-normal text-slate-400">(optional)</span></label>
                    <input type="text" name="title" id="edit_title" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php if ($has['release_no']): ?>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1"><?= h($lbl['release_no']) ?></label>
                            <input type="number" name="release_no" id="edit_release_no" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                    <?php endif; ?>

                    <?php if ($has['line_series']): ?>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1"><?= h($lbl['line_series']) ?></label>
                            <input type="text" name="line_series" id="edit_line_series" list="series_list" onclick="showSeriesSuggestions(this)" onfocus="showSeriesSuggestions(this)" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                    <?php endif; ?>

                    <?php if ($has['region']): ?>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1"><?= h($lbl['region']) ?></label>
                            <select name="region" id="edit_region" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                <option value="EUR (PAL)">EUR (PAL)</option>
                                <option value="USA (NTSC)">USA (NTSC)</option>
                                <option value="JPN (NTSC-J)">JPN (NTSC-J)</option>
                                <option value="Region Free">Region Free</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($has['media_type']): ?>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1"><?= h($lbl['media_type']) ?></label>
                            <select name="media_type" id="edit_media_type" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                <option value="Physical">Physical (Cartridge/Disc)</option>
                                <option value="Digital">Digital (eShop/Download)</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($custom_fields as $cf) { if ($cf['field_type'] !== 'yesno') echo render_custom_input($cf, 'edit'); } ?>
                </div>

                <?php if ($has['cib']): ?>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5"><?= h($lbl['cib']) ?></label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input type="radio" name="is_cib" id="edit_cib_yes" value="1" class="text-indigo-600 focus:ring-indigo-500">
                                <span class="font-medium text-slate-800">CIB (Complete in Box)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input type="radio" name="is_cib" id="edit_cib_no" value="0" class="text-indigo-600 focus:ring-indigo-500">
                                <span class="text-slate-600">NOT CIB (Loose / Cart only)</span>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($has['legacy']): ?>
                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" id="edit_is_legacy" name="is_legacy" value="1" class="h-4 w-4 text-indigo-600 rounded">
                        <label for="edit_is_legacy" class="text-sm text-slate-700 font-medium">Discontinued / Legacy Title</label>
                    </div>
                <?php endif; ?>

                <?php if ($has['status']): ?>
                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" id="edit_is_owned" name="is_owned" value="1" class="h-4 w-4 text-indigo-600 rounded">
                        <label for="edit_is_owned" class="text-sm text-slate-700 font-medium">Currently Owned in Collection</label>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="is_owned" id="edit_is_owned" value="1">
                <?php endif; ?>

                <?php foreach ($custom_fields as $cf) { if ($cf['field_type'] === 'yesno') echo render_custom_input($cf, 'edit'); } ?>

                <?php if ($has['notes']): ?>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1"><?= h($lbl['notes']) ?></label>
                        <input type="text" name="notes" id="edit_notes" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                <?php endif; ?>

                <div class="flex justify-end gap-3 pt-3">
                    <button type="button" onclick="document.getElementById('editGameModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">Update Title</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Datalist for Evercade Series -->
    <datalist id="series_list">
        <option value="Console (Red)">
        <option value="Arcade (Purple)">
        <option value="Computer (Blue)">
    </datalist>

    <!-- Modal: Add Platform -->
    <div id="platformModal" class="hidden fixed inset-0 bg-slate-900/60 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-900">Add New Platform</h3>
                <button onclick="document.getElementById('platformModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_platform">

                <?php if (!empty($platform_templates)): ?>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Start from a template <span class="font-normal text-slate-400">(optional)</span></label>
                        <select id="platformTemplateSelect" onchange="applyPlatformTemplate(this.value)" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="">Start from scratch</option>
                            <?php foreach ($platform_templates as $t): ?>
                                <option value="<?= (int)$t['id'] ?>"><?= h($t['name']) ?><?= $t['is_builtin'] ? ' (built-in)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php $custom_templates = array_filter($platform_templates, fn($t) => !$t['is_builtin']); ?>
                        <?php if (!empty($custom_templates)): ?>
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                <?php foreach ($custom_templates as $t): ?>
                                    <span class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                        <?= h($t['name']) ?>
                                        <button type="button" onclick="deleteTemplate(<?= (int)$t['id'] ?>, <?= h(json_encode($t['name'])) ?>)" class="text-slate-400 hover:text-rose-600 px-1" title="Delete template"><i class="fa-solid fa-xmark"></i></button>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Platform Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Nintendo Switch, PlayStation 5" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div class="bg-slate-50 p-4 rounded-xl space-y-3 border border-slate-200">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs font-bold text-slate-700 uppercase tracking-wider">Configure Fields for this Platform</div>
                        <button type="button" onclick="openFieldManager()" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold"><i class="fa-solid fa-list-check mr-1"></i>Manage Fields</button>
                    </div>

                    <?= render_field_checklist($all_fields, $title_field_id ? [$title_field_id => true] : [], false) ?>

                    <div class="flex flex-wrap items-center gap-4 pt-1">
                        <button type="button" onclick="saveCurrentAsTemplate()" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold"><i class="fa-solid fa-floppy-disk mr-1"></i>Save as new template</button>
                        <button type="button" id="updateTemplateBtn" onclick="updateCurrentTemplate()" class="hidden text-xs text-indigo-600 hover:text-indigo-800 font-semibold"><i class="fa-solid fa-rotate mr-1"></i>Update template</button>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('platformModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">Create Platform</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden forms for platform templates -->
    <form id="saveTemplateForm" method="POST" class="hidden">
        <input type="hidden" name="action" value="save_platform_template">
        <input type="hidden" name="platform_id" value="<?= (int)$active_platform_id ?>">
        <input type="hidden" name="name" id="st_name" value="">
        <input type="hidden" name="default_name" id="st_default_name" value="">
        <input type="hidden" name="template_id" id="st_template_id" value="0">
    </form>
    <form id="deleteTemplateForm" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete_platform_template">
        <input type="hidden" name="platform_id" value="<?= (int)$active_platform_id ?>">
        <input type="hidden" name="template_id" id="delete_template_id" value="">
    </form>

    <?php if ($current_platform && $is_admin): ?>
    <!-- Modal: Edit Platform -->
    <div id="editPlatformModal" class="hidden fixed inset-0 bg-slate-900/60 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-900">Edit <?= htmlspecialchars($current_platform['name']) ?> Fields</h3>
                <button onclick="document.getElementById('editPlatformModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_platform">
                <input type="hidden" name="platform_id" value="<?= $active_platform_id ?>">
                
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Platform Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($current_platform['name']) ?>" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div class="bg-slate-50 p-4 rounded-xl space-y-3 border border-slate-200">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs font-bold text-slate-700 uppercase tracking-wider">Enabled Fields</div>
                        <button type="button" onclick="openFieldManager()" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold"><i class="fa-solid fa-list-check mr-1"></i>Manage Fields</button>
                    </div>


                    <?= render_field_checklist($all_fields, $checked_field_ids, false) ?>
                </div>

                <div class="flex justify-between items-center pt-2">
                    <button type="submit" form="deletePlatformForm" class="text-rose-600 text-sm font-medium hover:underline bg-transparent border-0 p-0 cursor-pointer">
                        Delete Platform
                    </button>
                    <div class="flex gap-2">
                        <button type="button" onclick="document.getElementById('editPlatformModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">Save Changes</button>
                    </div>
                </div>
            </form>
            <form id="deletePlatformForm" method="POST" onsubmit="return confirm('Delete platform and all its games? This cannot be undone.');">
                <input type="hidden" name="action" value="delete_platform">
                <input type="hidden" name="platform_id" value="<?= $active_platform_id ?>">
            </form>
        </div>
    </div>
    <?php endif; ?>

<?php if ($is_admin): ?>
    <!-- Modal: Manage Fields (create / edit / delete) -->
    <div id="fieldManagerModal" class="<?= !empty($_GET['manage_fields']) ? '' : 'hidden' ?> fixed inset-0 bg-slate-900/60 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-1">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-indigo-600"></i> Manage Fields
                </h3>
                <button type="button" onclick="closeFieldManager()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <?php
                $show_all_fields = !empty($_GET['all_fields']);
                $fields_to_manage = $show_all_fields
                    ? $all_fields
                    : array_values(array_filter($all_fields, function($f) use ($checked_field_ids) {
                        return !empty($checked_field_ids[(int)$f['id']]);
                    }));
                $hidden_field_count = count($all_fields) - count($fields_to_manage);
                $manage_fields_toggle_url = '?platform=' . (int)$active_platform_id . '&manage_fields=1' . ($show_all_fields ? '' : '&all_fields=1');

                // Keep this list in the same order as the actual table columns
                // ("Reorder Columns"), so reordering columns is the one place
                // that controls both. A field with no column-order entry (e.g.
                // a custom field not enabled on this platform, only visible in
                // "show all fields" mode) sorts after every field that has one.
                $column_rank = array_flip($column_order);
                usort($fields_to_manage, function($a, $b) use ($column_rank) {
                    $ka = $a['is_builtin'] ? $a['field_key'] : ('cf' . (int)$a['id']);
                    $kb = $b['is_builtin'] ? $b['field_key'] : ('cf' . (int)$b['id']);
                    $ra = $column_rank[$ka] ?? PHP_INT_MAX;
                    $rb = $column_rank[$kb] ?? PHP_INT_MAX;
                    return $ra <=> $rb;
                });
            ?>
            <p class="text-xs text-slate-500 mb-4">
                <?php if ($show_all_fields): ?>
                    Fields are shared by every platform. Create or delete them here, then tick the ones each platform should use under <strong>Platform Fields &amp; Settings</strong>.
                <?php else: ?>
                    Showing fields enabled on <strong><?= h($current_platform['name'] ?? 'this platform') ?></strong>. Fields are shared by every platform, so a new one is also available to tick on for others under <strong>Platform Fields &amp; Settings</strong>.
                <?php endif; ?>
                The <strong>column name</strong> is the header shown in the table; the <strong>description</strong> is what you see in the Enabled Fields list. Use the arrows to set the table's column order for this platform.
            </p>

            <!-- All fields (or just this platform's, by default) -->
            <div id="fieldManagerList" class="border border-slate-200 rounded-xl divide-y divide-slate-200 mb-2">
                <?php if (empty($fields_to_manage)): ?>
                    <div class="px-4 py-4 text-sm text-slate-400 italic"><?= empty($all_fields) ? 'No fields yet. Add one below.' : 'No fields enabled on this platform yet.' ?></div>
                <?php else: foreach ($fields_to_manage as $f):
                    $fkey = $f['is_builtin'] ? $f['field_key'] : ('cf' . (int)$f['id']);
                    $is_column_field = !empty($checked_field_ids[(int)$f['id']]);
                ?>
                    <div class="flex items-start justify-between gap-3 px-4 py-3"<?= $is_column_field ? ' data-field-order-key="' . h($fkey) . '"' : '' ?>>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-sm text-slate-900"><?= h($f['label']) ?></span>
                                <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-semibold uppercase"><?= h(FIELD_TYPES[$f['field_type']] ?? 'Text') ?></span>
                                <?php if ($f['is_builtin']): ?>
                                    <span class="px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-600 text-[10px] font-semibold uppercase">Built-in</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5"><?= h($f['description']) ?></div>
                            <div class="text-[11px] text-slate-400 mt-0.5">Used on <?= (int)($fields_usage[(int)$f['id']] ?? 0) ?> platform(s)</div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <?php if ($is_column_field): ?>
                                <button type="button" onclick="moveFieldRow(this, -1)" class="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-indigo-600 rounded" title="Move column earlier"><i class="fa-solid fa-chevron-up"></i></button>
                                <button type="button" onclick="moveFieldRow(this, 1)" class="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-indigo-600 rounded" title="Move column later"><i class="fa-solid fa-chevron-down"></i></button>
                            <?php endif; ?>
                            <button type="button" onclick="editField(<?= (int)$f['id'] ?>)" class="text-slate-400 hover:text-indigo-600 transition p-1.5" title="Edit field">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button type="button" onclick="deleteField(<?= (int)$f['id'] ?>)" class="text-slate-400 hover:text-rose-600 transition p-1.5" title="Delete field">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="mb-5">
                <?php if ($show_all_fields): ?>
                    <a href="<?= h($manage_fields_toggle_url) ?>" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold">
                        <i class="fa-solid fa-filter mr-1"></i>Show only fields enabled on <?= h($current_platform['name'] ?? 'this platform') ?>
                    </a>
                <?php elseif ($hidden_field_count > 0): ?>
                    <a href="<?= h($manage_fields_toggle_url) ?>" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold">
                        <i class="fa-solid fa-list mr-1"></i>Show all fields (<?= $hidden_field_count ?> more used on other platforms)
                    </a>
                <?php endif; ?>
            </div>

            <!-- Add / edit form -->
            <form method="POST" id="fieldForm" class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-3">
                <input type="hidden" name="action" value="save_field">
                <input type="hidden" name="platform_id" value="<?= (int)$active_platform_id ?>">
                <input type="hidden" name="field_id" id="ff_id" value="">

                <div class="flex items-center justify-between">
                    <div id="ffTitle" class="text-xs font-bold text-slate-700 uppercase tracking-wider">Add a new field</div>
                    <button type="button" id="ffCancelEdit" onclick="resetFieldForm()" class="hidden text-xs text-slate-500 hover:text-slate-700 font-semibold">Cancel edit</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Column name * <span class="font-normal text-slate-400">(header in the table)</span></label>
                        <input type="text" name="label" id="ff_label" required maxlength="40" placeholder="e.g. Condition" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                        <select name="field_type" id="ff_type" onchange="onFieldTypeChange()" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <?php foreach (FIELD_TYPES as $type_key => $type_name): ?>
                                <option value="<?= h($type_key) ?>"><?= h($type_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Description * <span class="font-normal text-slate-400">(shown in Enabled Fields)</span></label>
                    <input type="text" name="description" id="ff_desc" required maxlength="120" placeholder="e.g. Condition of the item (Mint, Good, Poor)" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div id="ff_options_wrap" class="hidden">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Choices * <span class="font-normal text-slate-400">(one per line)</span></label>
                    <textarea name="options" id="ff_options" rows="4" placeholder="Mint&#10;Good&#10;Poor" oninput="renderChoiceColors()" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                </div>

                <div id="ff_colors_wrap" class="hidden">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Colors <span class="font-normal text-slate-400">(one per choice, shown as a badge in the table)</span></label>
                    <input type="hidden" name="colors_json" id="ff_colors_json" value="{}">
                    <div id="ff_colors_list" class="space-y-1.5"></div>
                </div>

                <div class="flex flex-wrap items-center gap-4 pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="sortable" id="ff_sortable" value="1" checked class="h-4 w-4 text-indigo-600 rounded">
                        <span class="text-sm text-slate-700">Sortable column</span>
                    </label>
                    <label id="ff_quick_wrap" class="hidden items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="quick_toggle" id="ff_quick_toggle" value="1" class="h-4 w-4 text-indigo-600 rounded">
                        <span class="text-sm text-slate-700">Quick-change right in the table <span class="font-normal text-slate-400">(like Owned / Wanted)</span></span>
                    </label>
                    <label id="ff_bold_wrap" class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="bold" id="ff_bold" value="1" class="h-4 w-4 text-indigo-600 rounded">
                        <span class="text-sm text-slate-700">Bold text <span class="font-normal text-slate-400">(strong emphasis, like Title)</span></span>
                    </label>
                </div>

                <?php if ($current_platform): ?>
                    <label id="ff_enable_wrap" class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="enable_here" value="1" checked class="h-4 w-4 text-indigo-600 rounded">
                        <span class="text-sm text-slate-700">Enable on <strong><?= h($current_platform['name']) ?></strong> right away</span>
                    </label>
                <?php endif; ?>

                <p id="ff_builtin_note" class="hidden text-[11px] text-slate-500 italic">Built-in field: you can rename and re-describe it, and change the options below. Its type stays the same.</p>

                <div class="flex justify-end">
                    <button type="submit" id="ffSubmit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">Add Field</button>
                </div>
            </form>

            <?php if ($missing_builtin_count > 0): ?>
                <form method="POST" class="mt-4 flex items-center justify-between gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                    <input type="hidden" name="action" value="restore_builtin_fields">
                    <input type="hidden" name="platform_id" value="<?= (int)$active_platform_id ?>">
                    <span class="text-xs text-amber-800"><?= (int)$missing_builtin_count ?> built-in field(s) have been deleted.</span>
                    <button type="submit" class="text-xs font-semibold text-amber-800 hover:underline">Restore built-in fields</button>
                </form>
            <?php endif; ?>

            <form id="deleteFieldForm" method="POST" class="hidden">
                <input type="hidden" name="action" value="delete_field">
                <input type="hidden" name="platform_id" value="<?= (int)$active_platform_id ?>">
                <input type="hidden" name="field_id" id="delete_field_id">
            </form>
        </div>
    </div>

    <!-- Modal: Manage KPIs -->
    <div id="kpiManagerModal" class="<?= !empty($_GET['manage_kpis']) ? '' : 'hidden' ?> fixed inset-0 bg-slate-900/60 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-1">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-chart-simple text-indigo-600"></i> Manage KPIs
                </h3>
                <button onclick="closeKpiManager()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <p class="text-xs text-slate-500 mb-4">Shown for <strong><?= h($current_platform['name'] ?? '') ?></strong>. Tick to show, untick to hide, arrows to reorder.</p>

            <div id="kpiEnabledList" class="space-y-1.5 mb-2"></div>

            <button type="button" id="kpiOtherToggle" onclick="toggleOtherKpis()" class="text-xs text-slate-500 hover:text-indigo-600 font-semibold flex items-center gap-1 mb-4">
                <i class="fa-solid fa-chevron-right text-[10px] transition-transform" id="kpiOtherChevron"></i>
                <span id="kpiOtherToggleLabel">Other KPIs</span>
            </button>
            <div id="kpiOtherList" class="hidden space-y-1.5 mb-4"></div>

            <div class="flex justify-between items-center pt-3 border-t border-slate-100">
                <form method="POST" onsubmit="return confirm('Restore any built-in KPIs you\'ve deleted?');">
                    <input type="hidden" name="action" value="restore_builtin_kpis">
                    <input type="hidden" name="platform_id" value="<?= (int)$active_platform_id ?>">
                    <button type="submit" class="text-xs text-slate-400 hover:text-indigo-600 font-medium">Restore built-ins</button>
                </form>
                <button type="button" onclick="openKpiForm(null)" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition shadow-sm font-medium">
                    <i class="fa-solid fa-plus mr-1"></i> New KPI
                </button>
            </div>

            <!-- Add / edit KPI form -->
            <div id="kpiFormWrap" class="hidden mt-4 pt-4 border-t border-slate-200">
                <form id="kpiForm" method="POST">
                    <input type="hidden" name="action" value="save_kpi">
                    <input type="hidden" name="platform_id" value="<?= (int)$active_platform_id ?>">
                    <input type="hidden" name="kpi_id" id="kf_kpi_id" value="0">
                    <input type="hidden" name="color" id="kf_color" value="slate">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                            <input type="text" name="label" id="kf_label" required placeholder="e.g. Wishlist" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Color</label>
                            <div id="kf_color_swatches" class="flex flex-wrap gap-1.5 pt-1.5"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Shows</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-1.5 text-sm text-slate-700"><input type="radio" name="calc_type" value="count" checked onchange="onKpiCalcTypeChange()" class="text-indigo-600"> a Count</label>
                            <label class="flex items-center gap-1.5 text-sm text-slate-700"><input type="radio" name="calc_type" value="percentage" onchange="onKpiCalcTypeChange()" class="text-indigo-600"> a Percentage</label>
                            <label class="flex items-center gap-1.5 text-sm text-slate-700"><input type="radio" name="calc_type" value="top_value" onchange="onKpiCalcTypeChange()" class="text-indigo-600"> a Most Common value</label>
                        </div>
                    </div>

                    <div id="kf_group_field_wrap" class="hidden mb-3">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Group by</label>
                        <select name="group_field" id="kf_group_field" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"></select>
                    </div>

                    <div class="mb-3">
                        <label class="block text-xs font-semibold text-slate-600 mb-1"><span id="kf_num_label">Count where</span></label>
                        <div id="kf_numerator" class="space-y-1.5"></div>
                    </div>

                    <div id="kf_denominator_wrap" class="hidden mb-3">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Percentage of (leave blank for "% of all titles")</label>
                        <div id="kf_denominator" class="space-y-1.5"></div>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer mb-4">
                        <input type="checkbox" name="enable_here" id="kf_enable_here" checked class="h-4 w-4 text-indigo-600 rounded">
                        <span class="text-sm text-slate-700">Show on <?= h($current_platform['name'] ?? 'this platform') ?></span>
                    </label>

                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeKpiForm()" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg font-medium transition">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition shadow font-medium">Save KPI</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

