
    <?php if ($flash_message):
        $flash_classes = $flash_type === 'red'
            ? 'bg-rose-50 border-rose-200 text-rose-700'
            : 'bg-indigo-50 border-indigo-200 text-indigo-700';
        $flash_btn_classes = $flash_type === 'red' ? 'text-rose-400 hover:text-rose-600' : 'text-indigo-400 hover:text-indigo-600';
    ?>
        <div class="max-w-7xl mx-auto px-4 mt-4 w-full flex-shrink-0">
            <div id="flashBanner" data-flash-type="<?= h($flash_type) ?>" class="<?= $flash_classes ?> border px-4 py-2.5 rounded-xl text-sm font-medium flex items-center justify-between shadow-sm">
                <span><?= htmlspecialchars($flash_message) ?></span>
                <button onclick="this.parentElement.remove()" class="<?= $flash_btn_classes ?>"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
    <?php endif; ?>

    <main class="max-w-7xl mx-auto px-2 py-2 sm:px-4 sm:py-6 w-full sm:flex-1 sm:min-h-0 sm:flex sm:flex-col">
        <?php if ($current_platform): ?>
            <div>
            <!-- Platform Header & Settings -->
            <div class="flex flex-wrap justify-between items-center mb-2 sm:mb-6 gap-2 sm:gap-4 flex-shrink-0">
                <div class="min-w-0">
                    <h2 class="text-xl sm:text-2xl font-bold text-slate-900 flex flex-wrap items-center gap-2 sm:gap-3">
                        <span class="truncate max-w-[70vw] sm:max-w-none"><?= htmlspecialchars($current_platform['name']) ?></span>
                    </h2>
                </div>
                <?php if ($is_admin): ?>
                    <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
                        <button onclick="document.getElementById('editPlatformModal').classList.remove('hidden')" title="Platform Fields & Settings" class="px-3 py-1.5 text-sm bg-white border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition shadow-sm font-medium">
                            <i class="fa-solid fa-sliders text-slate-500 sm:mr-1"></i> <span class="hidden sm:inline">Platform Fields & Settings</span>
                        </button>
                        <button type="button" onclick="openFieldManager()" title="Manage Fields" class="px-3 py-1.5 text-sm bg-white border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition shadow-sm font-medium">
                            <i class="fa-solid fa-list-check text-slate-500 sm:mr-1"></i> <span class="hidden sm:inline">Manage Fields</span>
                        </button>
                        <button type="button" onclick="openKpiManager()" title="Manage KPI's" class="px-3 py-1.5 text-sm bg-white border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition shadow-sm font-medium">
                            <i class="fa-solid fa-chart-simple text-slate-500 sm:mr-1"></i> <span class="hidden sm:inline">Manage KPI's</span>
                        </button>
                        <button onclick="document.getElementById('addGameModal').classList.remove('hidden'); setTimeout(() => { const el = document.getElementById('add_title'); if (el) el.focus(); }, 0);" class="flex-1 sm:flex-none px-4 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition shadow font-medium">
                            <i class="fa-solid fa-plus mr-1"></i> Add Title
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- KPI Cards: one compact row, identical on mobile/desktop/admin/guest.
                 Built from whatever KPIs are enabled on this platform (Manage KPIs, admin only) -->
            <?php if (!empty($active_kpis)): ?>
                <div id="kpiCardsRow" class="flex flex-wrap gap-1.5 mb-2 flex-shrink-0">
                    <?php foreach ($active_kpis as $kpi): ?>
                        <div class="kpi-card bg-white rounded-lg shadow-sm border border-slate-200 flex items-baseline gap-1.5" title="<?= h(kpi_summary($kpi, $kpi_fields)) ?>">
                            <span class="kpi-label font-semibold text-slate-500 uppercase tracking-wide truncate"><?= h($kpi['label']) ?></span>
                            <span class="kpi-value font-bold text-<?= h($kpi['color']) ?>-700"><?= h($kpi['display']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Interactive Search & Organize Toolbar -->
            <div class="toolbar-box bg-white p-2 sm:p-4 rounded-xl shadow-sm border border-slate-200 mb-2 sm:mb-6 flex flex-wrap gap-2 sm:gap-4 items-center justify-between flex-shrink-0">
                <div class="relative w-full sm:flex-1 sm:min-w-[240px]">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs sm:text-base"></i>
                    <input type="text" id="searchInput" oninput="filterTable(); toggleSearchClear();" placeholder="Search title, notes, region, series..." class="w-full pl-8 sm:pl-9 pr-8 py-1.5 sm:py-2 border border-slate-300 rounded-lg text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <button type="button" id="searchClearBtn" onclick="clearSearch()" title="Clear search" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs sm:text-sm">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>

                <div class="flex flex-nowrap sm:flex-wrap items-center gap-2 sm:gap-3 overflow-x-auto sm:overflow-visible w-full sm:w-auto -mx-2 px-2 sm:mx-0 sm:px-0 pb-1 sm:pb-0">
                    <!-- Quick Sort Dropdown -->
                    <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
                        <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider" title="Sort"><i class="fa-solid fa-arrow-down-short-wide sm:mr-1"></i><span class="hidden sm:inline">Sort:</span></label>
                        <select id="sortSelect" onchange="applyQuickSort(this.value)" class="border border-slate-300 rounded-lg px-2 py-1.5 sm:px-3 sm:py-2 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none font-medium text-slate-700 max-w-[130px] sm:max-w-none">
                            <?php if ($has['release_no']): ?>
                                <option value="release_no-asc" <?= $sort_col === 'release_no' && $sort_dir === 'ASC' ? 'selected' : '' ?>><?= h($lbl['release_no']) ?> (Low → High)</option>
                                <option value="release_no-desc" <?= $sort_col === 'release_no' && $sort_dir === 'DESC' ? 'selected' : '' ?>><?= h($lbl['release_no']) ?> (High → Low)</option>
                            <?php endif; ?>
                            <?php if ($has['title']): ?>
                            <option value="title-asc" <?= $sort_col === 'title' && $sort_dir === 'ASC' ? 'selected' : '' ?>><?= h($lbl['title']) ?> (A → Z)</option>
                            <option value="title-desc" <?= $sort_col === 'title' && $sort_dir === 'DESC' ? 'selected' : '' ?>><?= h($lbl['title']) ?> (Z → A)</option>
                            <?php endif; ?>
                            <?php if ($has['line_series']): ?>
                                <option value="line_series-asc" <?= $sort_col === 'line_series' && $sort_dir === 'ASC' ? 'selected' : '' ?>><?= h($lbl['line_series']) ?> (A → Z)</option>
                            <?php endif; ?>
                            <?php if ($has['region']): ?>
                                <option value="region-asc" <?= $sort_col === 'region' && $sort_dir === 'ASC' ? 'selected' : '' ?>><?= h($lbl['region']) ?> (A → Z)</option>
                            <?php endif; ?>
                            <?php if ($has['media_type']): ?>
                                <option value="media_type-asc" <?= $sort_col === 'media_type' && $sort_dir === 'ASC' ? 'selected' : '' ?>><?= h($lbl['media_type']) ?> (Physical First)</option>
                            <?php endif; ?>
                            <?php if ($has['cib']): ?>
                                <option value="cib-desc" <?= $sort_col === 'cib' && $sort_dir === 'DESC' ? 'selected' : '' ?>><?= h($lbl['cib']) ?> (CIB First)</option>
                                <option value="cib-asc" <?= $sort_col === 'cib' && $sort_dir === 'ASC' ? 'selected' : '' ?>><?= h($lbl['cib']) ?> (Not CIB First)</option>
                            <?php endif; ?>
                            <?php if ($has['status']): ?>
                                <option value="owned-desc" <?= $sort_col === 'owned' && $sort_dir === 'DESC' ? 'selected' : '' ?>><?= h($lbl['status']) ?> (Owned First)</option>
                                <option value="owned-asc" <?= $sort_col === 'owned' && $sort_dir === 'ASC' ? 'selected' : '' ?>><?= h($lbl['status']) ?> (Wanted First)</option>
                            <?php endif; ?>
                            <?php foreach ($custom_fields as $cf): $cid = (int)$cf['id']; ?>
                                <?php if ($cf['field_type'] === 'number'): ?>
                                    <option value="cf<?= $cid ?>-asc"><?= h($cf['label']) ?> (Low → High)</option>
                                    <option value="cf<?= $cid ?>-desc"><?= h($cf['label']) ?> (High → Low)</option>
                                <?php elseif ($cf['field_type'] === 'yesno'): ?>
                                    <option value="cf<?= $cid ?>-desc"><?= h($cf['label']) ?> (Yes First)</option>
                                    <option value="cf<?= $cid ?>-asc"><?= h($cf['label']) ?> (No First)</option>
                                <?php else: ?>
                                    <option value="cf<?= $cid ?>-asc"><?= h($cf['label']) ?> (A → Z)</option>
                                    <option value="cf<?= $cid ?>-desc"><?= h($cf['label']) ?> (Z → A)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($has['status']): ?>
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
                            <select id="ownedFilter" onchange="filterTable()" class="border border-slate-300 rounded-lg px-2 py-1.5 sm:px-3 sm:py-2 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none font-medium text-slate-700 max-w-[130px] sm:max-w-none">
                                <option value="ALL">All Statuses</option>
                                <option value="YES">Owned Only</option>
                                <option value="NO">Wanted / Missing Only</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($has['cib']): ?>
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
                            <select id="cibFilter" onchange="filterTable()" class="border border-slate-300 rounded-lg px-2 py-1.5 sm:px-3 sm:py-2 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none font-medium text-slate-700 max-w-[130px] sm:max-w-none">
                                <option value="ALL">All Packaging</option>
                                <option value="CIB">CIB Only</option>
                                <option value="NOT CIB">NOT CIB Only</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($has['region']): ?>
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
                            <select id="regionFilter" onchange="filterTable()" class="border border-slate-300 rounded-lg px-2 py-1.5 sm:px-3 sm:py-2 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none font-medium text-slate-700 max-w-[130px] sm:max-w-none">
                                <option value="ALL">All Regions</option>
                                <option value="EUR">EUR / PAL</option>
                                <option value="USA">USA / NTSC</option>
                                <option value="JPN">JPN / NTSC-J</option>
                                <option value="FREE">Region Free</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($has['media_type']): ?>
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
                            <select id="mediaFilter" onchange="filterTable()" class="border border-slate-300 rounded-lg px-2 py-1.5 sm:px-3 sm:py-2 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none font-medium text-slate-700 max-w-[130px] sm:max-w-none">
                                <option value="ALL">All Formats</option>
                                <option value="Physical">Physical Only</option>
                                <option value="Digital">Digital Only</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($custom_fields as $cf): ?>
                        <?php if (!in_array($cf['field_type'], ['select', 'yesno'], true)) continue; ?>
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
                            <select onchange="filterTable()" data-field="<?= (int)$cf['id'] ?>" class="cf-filter border border-slate-300 rounded-lg px-2 py-1.5 sm:px-3 sm:py-2 text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none font-medium text-slate-700 max-w-[130px] sm:max-w-none">
                                <option value="ALL">All <?= h($cf['label']) ?></option>
                                <?php if ($cf['field_type'] === 'yesno'): ?>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                <?php else: foreach (field_options($cf) as $opt): ?>
                                    <option value="<?= h($opt) ?>"><?= h($opt) ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            </div>

            <!-- Form for Bulk Delete -->
            <form id="bulkDeleteForm" method="POST" onsubmit="return confirmBulkDelete();" class="sm:flex-1 sm:min-h-0 sm:flex sm:flex-col">
                <input type="hidden" name="action" value="bulk_delete_games">
                <input type="hidden" name="platform_id" value="<?= $active_platform_id ?>">

                <!-- Games Table -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden sm:flex-1 sm:min-h-0 sm:flex sm:flex-col">
                    <div class="table-scroll-area overflow-x-auto sm:overflow-auto sm:flex-1 sm:min-h-0 sm:pb-16">
                        <table class="w-full text-left text-[11px] sm:text-xs dense-table" id="gamesTable">
                            <thead class="bg-slate-900 text-white uppercase text-[10px] sm:text-xs font-semibold sm:sticky sm:top-0 sm:z-10">
                                <tr>
                                    <?php if ($is_admin): ?>
                                        <th class="px-2 py-2 sm:px-3 sm:py-3.5 text-center w-10 sm:w-12">
                                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="h-4 w-4 rounded text-indigo-600 focus:ring-indigo-500 cursor-pointer" title="Select All">
                                        </th>
                                    <?php endif; ?>

                                    <?php if ($has['release_no']): ?>
                                        <?= col_th($lbl['release_no'], 'release_no', true, true, bf_sortable($field_meta, 'release_no'), 'w-28') ?>
                                    <?php endif; ?>

                                    <?php if ($has['title']): ?>
                                        <?= col_th($lbl['title'], 'title', false, false, bf_sortable($field_meta, 'title')) ?>
                                    <?php endif; ?>

                                    <?php if ($has['line_series']): ?>
                                        <?= col_th($lbl['line_series'], 'line_series', false, false, bf_sortable($field_meta, 'line_series')) ?>
                                    <?php endif; ?>

                                    <?php if ($has['region']): ?>
                                        <?= col_th($lbl['region'], 'region', false, true, bf_sortable($field_meta, 'region')) ?>
                                    <?php endif; ?>

                                    <?php if ($has['media_type']): ?>
                                        <?= col_th($lbl['media_type'], 'media_type', false, true, bf_sortable($field_meta, 'media_type')) ?>
                                    <?php endif; ?>

                                    <?php if ($has['legacy']): ?>
                                        <?= col_th($lbl['legacy'], 'legacy', false, true, bf_sortable($field_meta, 'legacy')) ?>
                                    <?php endif; ?>

                                    <?php if ($has['cib']): ?>
                                        <?= col_th($lbl['cib'], 'cib', false, true, bf_sortable($field_meta, 'cib')) ?>
                                    <?php endif; ?>

                                    <?php if ($has['status']): ?>
                                        <?= col_th($lbl['status'], 'owned', false, true, bf_sortable($field_meta, 'status'), '', 'status') ?>
                                    <?php endif; ?>

                                    <?php foreach ($custom_fields as $cf): $cid = (int)$cf['id']; $cf_center = ($cf['field_type'] !== 'text'); ?>
                                        <?= col_th($cf['label'], 'cf' . $cid, $cf['field_type'] === 'number', $cf_center, !empty($cf['sortable'])) ?>
                                    <?php endforeach; ?>

                                    <?php if ($has['notes']): ?>
                                        <th class="px-2 py-2 sm:px-4 sm:py-3.5" data-col="notes"><?= h($lbl['notes']) ?></th>
                                    <?php endif; ?>

                                    <?php if ($is_admin): ?>
                                        <th class="px-2 py-2 sm:px-4 sm:py-3.5 text-right w-20 sm:w-24" data-col-fixed="end">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200" id="gamesTableBody">
                                <?php if (empty($games)): ?>
                                    <tr>
                                        <td colspan="14" class="text-center py-10 text-slate-400">No titles recorded for this platform yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($games as $g): 
                                        $rowClass = "row-neutral";
                                        if ($has['status']) {
                                            if ($g['is_owned']) {
                                                $rowClass = "row-owned";
                                            } elseif ($g['is_legacy'] && !$g['is_owned']) {
                                                $rowClass = "row-legacy-missing";
                                            } else {
                                                $rowClass = "row-wishlist";
                                            }
                                        }
                                        $cib_text = !empty($g['is_cib']) ? 'CIB' : 'NOT CIB';
                                        $region_val = $g['region'] ?: 'EUR (PAL)';
                                        $media_val = $g['media_type'] ?: 'Physical';

                                        // data-* attributes for custom fields (used for sorting, filtering and search)
                                        $cf_attrs = '';
                                        $cf_search = '';
                                        foreach ($custom_fields as $cfd) {
                                            $cv = $g['cf'][(int)$cfd['id']] ?? '';
                                            if ($cfd['field_type'] === 'number') {
                                                $sv = ($cv !== '') ? $cv : '999999999';
                                            } elseif ($cfd['field_type'] === 'yesno') {
                                                $sv = ($cv === '1') ? '1' : '0';
                                            } else {
                                                $sv = $cv;
                                            }
                                            $cf_attrs .= ' data-cf' . (int)$cfd['id'] . '="' . h($sv) . '"';
                                            if ($cfd['field_type'] !== 'yesno' && $cv !== '') $cf_search .= ' ' . strtolower($cv);
                                        }
                                        $cf_attrs .= ' data-cf-search="' . h(trim($cf_search)) . '"';
                                    ?>
                                    <tr class="<?= $rowClass ?> hover:bg-slate-50 transition group" 
                                        data-id="<?= $g['id'] ?>"
                                        data-release-no="<?= $g['release_no'] !== null ? $g['release_no'] : 999999 ?>"
                                        data-title="<?= strtolower(htmlspecialchars($g['title'])) ?>" 
                                        data-line-series="<?= strtolower(htmlspecialchars($g['line_series'] ?? '')) ?>"
                                        data-region="<?= htmlspecialchars($region_val) ?>"
                                        data-media-type="<?= htmlspecialchars($media_val) ?>"
                                        data-legacy="<?= $g['is_legacy'] ? '1' : '0' ?>"
                                        data-cib="<?= $cib_text ?>"
                                        data-cib-sort="<?= !empty($g['is_cib']) ? '1' : '0' ?>"
                                        data-owned="<?= $g['is_owned'] ? 'YES' : 'NO' ?>"
                                        data-notes="<?= strtolower(htmlspecialchars($g['notes'] ?? '')) ?>"<?= $cf_attrs ?>>
                                        
                                        <?php if ($is_admin): ?>
                                            <td class="px-2 py-2 sm:px-3 sm:py-3 text-center">
                                                <input type="checkbox" name="selected_games[]" value="<?= $g['id'] ?>" onchange="updateSelectionCount()" class="row-checkbox h-4 w-4 rounded text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                                            </td>
                                        <?php endif; ?>

                                        <?php if ($has['release_no']): ?>
                                            <td class="px-2 py-2 sm:px-4 sm:py-3 text-center font-bold text-slate-700" data-col="release_no"><?= $g['release_no'] !== null ? $g['release_no'] : '—' ?></td>
                                        <?php endif; ?>

                                        <?php if ($has['title']): ?>
                                        <td class="px-2 py-2 sm:px-4 sm:py-3 font-semibold text-slate-900" data-col="title"><?= $g['title'] !== '' ? htmlspecialchars($g['title']) : '<span class="text-slate-300 font-normal">—</span>' ?></td>
                                        <?php endif; ?>

                                        <?php if ($has['line_series']): ?>
                                            <td class="px-2 py-2 sm:px-4 sm:py-3 text-slate-600" data-col="line_series"><?= htmlspecialchars($g['line_series'] ?: '—') ?></td>
                                        <?php endif; ?>

                                        <?php if ($has['region']): ?>
                                            <td class="px-2 py-2 sm:px-4 sm:py-3 text-center" data-col="region">
                                                <?php
                                                    $region_colors = bf_colors($field_meta, 'region');
                                                    $region_color = $region_colors[$region_val] ?? 'slate';
                                                    $region_quick = $is_admin && bf_quick($field_meta, 'region');
                                                    echo badge_tag($region_quick, $g['id'], 'b:region', choice_badge_class($region_color), h($region_val), false);
                                                ?>
                                            </td>
                                        <?php endif; ?>

                                        <?php if ($has['media_type']): ?>
                                            <td class="px-2 py-2 sm:px-4 sm:py-3 text-center" data-col="media_type">
                                                <?php
                                                    $media_colors = bf_colors($field_meta, 'media_type');
                                                    $media_color = $media_colors[$media_val] ?? ($media_val === 'Digital' ? 'sky' : 'amber');
                                                    $media_quick = $is_admin && bf_quick($field_meta, 'media_type');
                                                    $media_icon = '<i class="fa-solid ' . ($media_val === 'Digital' ? 'fa-cloud-arrow-down' : 'fa-compact-disc') . ' text-[10px]"></i> ' . h($media_val);
                                                    echo badge_tag($media_quick, $g['id'], 'b:media_type', choice_badge_class($media_color), $media_icon);
                                                ?>
                                            </td>
                                        <?php endif; ?>

                                        <?php if ($has['legacy']): ?>
                                            <td class="px-2 py-2 sm:px-4 sm:py-3 text-center" data-col="legacy">
                                                <?php
                                                    $legacy_quick = $is_admin && bf_quick($field_meta, 'legacy');
                                                    if ($g['is_legacy']) {
                                                        echo badge_tag($legacy_quick, $g['id'], 'b:legacy', 'bg-rose-100 text-rose-700 border border-rose-200', 'LEGACY', false);
                                                    } else {
                                                        echo badge_tag($legacy_quick, $g['id'], 'b:legacy', 'bg-slate-100 text-slate-500 border border-slate-200', 'NO', false);
                                                    }
                                                ?>
                                            </td>
                                        <?php endif; ?>

                                        <?php if ($has['cib']): ?>
                                            <td class="px-2 py-2 sm:px-4 sm:py-3 text-center" data-col="cib">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold <?= !empty($g['is_cib']) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' ?>">
                                                    <i class="fa-solid <?= !empty($g['is_cib']) ? 'fa-box' : 'fa-box-open' ?> text-[10px]"></i>
                                                    <span><?= $cib_text ?></span>
                                                </span>
                                            </td>
                                        <?php endif; ?>

                                        <?php if ($has['status']): ?>
                                            <td class="px-2 py-2 sm:px-4 sm:py-3 text-center" data-col="status">
                                                <?php if ($is_admin): ?>
                                                    <button type="button" onclick="toggleOwned(<?= $g['id'] ?>, this)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold transition <?= $g['is_owned'] ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-200 text-slate-700 hover:bg-slate-300' ?>" title="Click to toggle status">
                                                        <i class="fa-solid <?= $g['is_owned'] ? 'fa-check' : 'fa-xmark' ?>"></i>
                                                        <span><?= $g['is_owned'] ? 'OWNED' : 'WANTED' ?></span>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold <?= $g['is_owned'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>">
                                                        <i class="fa-solid <?= $g['is_owned'] ? 'fa-check' : 'fa-xmark' ?>"></i>
                                                        <span><?= $g['is_owned'] ? 'OWNED' : 'WANTED' ?></span>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        
                                        <?php foreach ($custom_fields as $cf): $cv = $g['cf'][(int)$cf['id']] ?? ''; $cid = (int)$cf['id']; $cf_quick = $is_admin && field_is_quick_toggle($cf); ?>
                                            <?php if ($cf['field_type'] === 'yesno'): ?>
                                                <td class="px-2 py-2 sm:px-4 sm:py-3 text-center" data-col="cf<?= $cid ?>">
                                                    <?php if ($cv === '1'): ?>
                                                        <?= badge_tag($cf_quick, $g['id'], 'c:' . $cid, 'bg-emerald-100 text-emerald-800 border border-emerald-200', 'YES', false) ?>
                                                    <?php else: ?>
                                                        <?= badge_tag($cf_quick, $g['id'], 'c:' . $cid, 'bg-slate-100 text-slate-500 border border-slate-200', 'NO', false) ?>
                                                    <?php endif; ?>
                                                </td>
                                            <?php elseif ($cf['field_type'] === 'select'): ?>
                                                <td class="px-2 py-2 sm:px-4 sm:py-3 text-center" data-col="cf<?= $cid ?>">
                                                    <?php if ($cv !== ''):
                                                        $cf_colors = field_colors($cf);
                                                        $cf_color = $cf_colors[$cv] ?? 'slate';
                                                        echo badge_tag($cf_quick, $g['id'], 'c:' . $cid, choice_badge_class($cf_color), h($cv), false);
                                                    elseif ($cf_quick):
                                                        echo badge_tag(true, $g['id'], 'c:' . $cid, 'bg-slate-50 text-slate-400 border border-dashed border-slate-300', '—', false);
                                                    else: ?>
                                                        <span class="text-slate-400">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php elseif ($cf['field_type'] === 'number'): ?>
                                                <td class="px-2 py-2 sm:px-4 sm:py-3 text-center font-semibold <?= !empty($cf['bold']) ? 'text-slate-900' : 'text-slate-700' ?>" data-col="cf<?= $cid ?>"><?= $cv !== '' ? h($cv) : '—' ?></td>
                                            <?php else: ?>
                                                <td class="px-2 py-2 sm:px-4 sm:py-3 <?= !empty($cf['bold']) ? 'font-semibold text-slate-900' : 'text-slate-600' ?>" data-col="cf<?= $cid ?>"><?= $cv !== '' ? h($cv) : '—' ?></td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>

                                        <?php if ($has['notes']): ?>
                                            <td class="px-2 py-1.5 sm:px-4 sm:py-2 text-xs" data-col="notes">
                                                <?php if ($is_admin): ?>
                                                    <div class="relative flex items-center group/note">
                                                        <input type="text"
                                                               value="<?= htmlspecialchars($g['notes'] ?: '') ?>"
                                                               placeholder="Add notes..."
                                                               onblur="saveInlineNote(<?= $g['id'] ?>, this)"
                                                               onkeydown="if(event.key==='Enter') this.blur();"
                                                               class="inline-note-input w-full bg-transparent hover:bg-slate-100 focus:bg-white text-slate-700 text-xs px-2 py-1 rounded border border-transparent hover:border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition focus:outline-none italic placeholder:text-slate-300">
                                                        <span class="note-status-icon text-emerald-500 text-[11px] opacity-0 transition-opacity ml-1 pointer-events-none">
                                                            <i class="fa-solid fa-check"></i>
                                                        </span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-slate-500 italic"><?= htmlspecialchars($g['notes'] ?: '') ?></span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>

                                        <?php if ($is_admin): ?>
                                            <td class="px-2 py-2 sm:px-4 sm:py-3 text-right space-x-1" data-col-fixed="end">
                                                <button type="button" onmousedown="rememberScrollBeforeEdit()" onclick="openEditModal(<?= htmlspecialchars(json_encode($g)) ?>)" class="text-slate-400 hover:text-indigo-600 transition p-1.5" title="Edit Title">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button type="button" onclick="deleteSingleGame(<?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['title'])) ?>')" class="text-slate-400 hover:text-rose-600 transition p-1.5" title="Delete">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Floating Bulk Action Bar (Appears when 1+ rows are checked) -->
                <?php if ($is_admin): ?>
                    <div id="bulkActionBar" class="hidden fixed bottom-3 inset-x-3 sm:inset-x-auto sm:bottom-6 sm:left-1/2 sm:-translate-x-1/2 max-w-full sm:max-w-none bg-slate-900 text-white px-4 sm:px-6 py-3 sm:py-3.5 rounded-2xl shadow-2xl z-40 flex flex-wrap items-center justify-between gap-3 sm:gap-5 border border-slate-700">
                        <span class="text-sm font-semibold flex items-center gap-2">
                            <span id="selectedCountBadge" class="bg-indigo-600 px-2 py-0.5 rounded-full text-xs font-bold">0</span>
                            titles selected
                        </span>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="clearAllSelections()" class="text-xs text-slate-400 hover:text-white transition whitespace-nowrap">Deselect all</button>
                            <button type="submit" class="px-4 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-sm font-bold shadow transition flex items-center gap-1.5 whitespace-nowrap">
                                <i class="fa-solid fa-trash-can"></i> Delete Selected
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </main>
