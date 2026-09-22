    <script>
        let sortDirections = {};
        const IS_ADMIN = <?= $is_admin ? 'true' : 'false' ?>;
        const TITLE_FIELD_ID = <?= (int)$title_field_id ?>;
        const SAVED_SORT_HISTORY = <?= json_encode($saved_sort_history, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        // Opens the Line/Series suggestion list the instant the field is clicked/focused,
        // instead of waiting for the user to start typing (Chrome/Edge support this natively;
        // other browsers just fall back to their normal datalist behavior). The call is
        // deferred a tick because calling showPicker() synchronously inside the very click
        // that also focuses the field (or that opened its parent modal) is unreliable —
        // it silently no-ops the first time and only works from the second click onward.
        function showSeriesSuggestions(el) {
            if (typeof el.showPicker !== 'function') return;
            setTimeout(() => {
                if (document.activeElement !== el) return;
                try { el.showPicker(); } catch (e) {}
            }, 0);
        }

        // ---- Column order (applies to admin and guest alike; only admin can change it) ----
        const COLUMN_ORDER = <?= json_encode($column_order, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        // Rearranges the already-rendered header + body cells of #gamesTable to
        // match COLUMN_ORDER, by moving each cell (identified by data-col) into
        // place right before the row's fixed trailing cell (data-col-fixed="end",
        // i.e. Actions in admin mode). A column missing from a given row (not
        // enabled on this platform, or a guest row with no Actions anchor) is
        // simply skipped - nothing about its own rendering changes.
        function applyColumnOrder() {
            if (!Array.isArray(COLUMN_ORDER) || COLUMN_ORDER.length === 0) return;
            document.querySelectorAll('#gamesTable thead tr, #gamesTable tbody tr').forEach(row => {
                const endAnchor = row.querySelector('[data-col-fixed="end"]') || null;
                COLUMN_ORDER.forEach(key => {
                    const cell = row.querySelector('[data-col="' + key.replace(/"/g, '') + '"]');
                    if (cell) row.insertBefore(cell, endAnchor);
                });
            });
        }
        applyColumnOrder();

        // ---- Clear ("x") buttons on text fields, matching the one on Search ----
        // Wraps every plain text input inside the Add/Edit Title modals (built-in
        // fields like Title/Line-Series/Notes, plus any custom text field) with a
        // small clear button that shows only once the field has a value.
        function wrapClearable(input) {
            if (!input || input.dataset.clearWrapped) return;
            input.dataset.clearWrapped = '1';
            const wrap = document.createElement('div');
            wrap.className = 'relative';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);
            input.classList.add('pr-8');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.tabIndex = -1;
            btn.title = 'Clear';
            btn.className = 'hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs sm:text-sm';
            btn.innerHTML = '<i class="fa-solid fa-circle-xmark"></i>';
            const toggle = () => btn.classList.toggle('hidden', input.value.length === 0);
            btn.addEventListener('click', () => {
                input.value = '';
                toggle();
                input.focus();
            });
            input.addEventListener('input', toggle);
            wrap.appendChild(btn);
            toggle();
        }
        function initClearableInputs(container) {
            (container || document).querySelectorAll('input[type="text"]').forEach(wrapClearable);
        }
        function refreshClearButtons(container) {
            (container || document).querySelectorAll('input[type="text"][data-clear-wrapped]').forEach(el => {
                const btn = el.nextElementSibling;
                if (btn && btn.tagName === 'BUTTON') btn.classList.toggle('hidden', el.value.length === 0);
            });
        }
        initClearableInputs(document.getElementById('addGameModal'));
        initClearableInputs(document.getElementById('editGameModal'));

        // ---- Save an edit without reloading the page ----
        // A plain form submit navigates the browser to the server's redirect,
        // which reloads the whole page at the top - no restore trick can beat
        // simply never navigating away in the first place. Instead we submit the
        // form in the background, fetch() transparently follows the server's
        // redirect and hands back the freshly-rendered page's HTML, and we swap
        // in just the parts that can change (the games table and the KPI cards)
        // without touching window.location or scroll position at all.
        function showToast(message, isError) {
            let toast = document.getElementById('ajaxToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'ajaxToast';
                toast.style.transition = 'opacity 0.3s ease';
                document.body.appendChild(toast);
            }
            toast.textContent = message;
            toast.className = 'fixed bottom-4 right-4 z-[100] max-w-sm px-4 py-2.5 rounded-xl text-sm font-medium shadow-lg border '
                + (isError ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-indigo-50 border-indigo-200 text-indigo-700');
            toast.style.opacity = '1';
            clearTimeout(toast._hideTimer);
            toast._hideTimer = setTimeout(() => { toast.style.opacity = '0'; }, 2200);
        }

        // ---- Table Sorting System ----
        const SORT_ATTR_OVERRIDE = { cib: 'cib-sort', status: 'owned' };

        let sortHistory = [];
        try {
            const sessSort = JSON.parse(sessionStorage.getItem('sort_history_' + '<?= (int)$active_platform_id ?>'));
            if (Array.isArray(sessSort) && sessSort.length > 0) {
                sortHistory = sessSort.slice(-4);
            }
        } catch (e) {}
        if (!sortHistory.length && Array.isArray(SAVED_SORT_HISTORY)) {
            sortHistory = SAVED_SORT_HISTORY.slice(-4);
        }

        function applySort(columnKey, dir, isNumeric) {
            sortDirections[columnKey] = dir;

            const tbody = document.getElementById('gamesTableBody');
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('tr[data-id]'));
            const attrName = SORT_ATTR_OVERRIDE[columnKey] || columnKey.replace(/_/g, '-');

            rows.sort((a, b) => {
                let valA = a.getAttribute(`data-${attrName}`) || '';
                let valB = b.getAttribute(`data-${attrName}`) || '';

                if (isNumeric) {
                    valA = parseFloat(valA) || 0;
                    valB = parseFloat(valB) || 0;
                    return dir === 'asc' ? valA - valB : valB - valA;
                } else {
                    if (!valA && valB) return dir === 'asc' ? 1 : -1;
                    if (valA && !valB) return dir === 'asc' ? -1 : 1;
                    return dir === 'asc'
                        ? valA.localeCompare(valB, undefined, { numeric: true, sensitivity: 'base' })
                        : valB.localeCompare(valA, undefined, { numeric: true, sensitivity: 'base' });
                }
            });

            rows.forEach(row => tbody.appendChild(row));

            const sortSelect = document.getElementById('sortSelect');
            if (sortSelect) {
                const targetVal = `${columnKey}-${dir}`;
                for (let option of sortSelect.options) {
                    if (option.value === targetVal) {
                        sortSelect.value = targetVal;
                        break;
                    }
                }
            }
        }

        function saveSortAjax() {
            if (!IS_ADMIN) return;
            const formData = new FormData();
            formData.append('action', 'save_sort_ajax');
            formData.append('platform_id', '<?= (int)$active_platform_id ?>');
            formData.append('history_json', JSON.stringify(sortHistory));
            fetch(window.location.href, { method: 'POST', body: formData, credentials: 'same-origin' }).catch(() => {});
        }

        function recordSortHistory(columnKey, dir, isNumeric) {
            sortHistory = sortHistory.filter(item => item.col !== columnKey);
            sortHistory.push({ col: columnKey, dir, numeric: isNumeric ? 1 : 0 });
            if (sortHistory.length > 4) sortHistory = sortHistory.slice(-4);
            try {
                sessionStorage.setItem('sort_history_' + '<?= (int)$active_platform_id ?>', JSON.stringify(sortHistory));
            } catch (e) {}
            saveSortAjax();
        }

        function sortTableByColumn(columnKey, isNumeric = false) {
            const currentDir = sortDirections[columnKey] === 'asc' ? 'desc' : 'asc';
            applySort(columnKey, currentDir, isNumeric);
            recordSortHistory(columnKey, currentDir, isNumeric);
        }

        function applyQuickSort(val) {
            const lastDash = val.lastIndexOf('-');
            if (lastDash === -1) return;
            const columnKey = val.slice(0, lastDash);
            const dir = val.slice(lastDash + 1);
            const normDir = dir === 'desc' ? 'desc' : 'asc';
            const isNumeric = (columnKey === 'release_no');
            applySort(columnKey, normDir, isNumeric);
            recordSortHistory(columnKey, normDir, isNumeric);
        }

        function reapplyCurrentSort() {
            if (Array.isArray(sortHistory) && sortHistory.length > 0) {
                sortHistory.forEach(item => {
                    if (item && item.col) applySort(item.col, item.dir === 'desc' ? 'desc' : 'asc', !!item.numeric);
                });
            }
        }

        function ajaxSubmitEditForm(e) {
            e.preventDefault();
            const form = e.target;
            const gameId = form.querySelector('[name="game_id"]').value;
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            fetch(window.location.href, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
                .then(r => r.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');

                    const newTbody = doc.getElementById('gamesTableBody');
                    const curTbody = document.getElementById('gamesTableBody');
                    if (newTbody && curTbody) {
                        // Fresh server HTML has every checkbox unchecked - preserve
                        // whatever bulk-selection the user already had going.
                        const checkedIds = Array.from(curTbody.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);

                        curTbody.innerHTML = newTbody.innerHTML;
                        applyColumnOrder();
                        reapplyCurrentSort();
                        if (typeof filterTable === 'function') filterTable();

                        if (checkedIds.length) {
                            checkedIds.forEach(id => {
                                const cb = curTbody.querySelector('.row-checkbox[value="' + CSS.escape(id) + '"]');
                                if (cb) cb.checked = true;
                            });
                            if (typeof updateSelectionCount === 'function') updateSelectionCount();
                        }
                    }

                    const newKpi = doc.getElementById('kpiCardsRow');
                    const curKpi = document.getElementById('kpiCardsRow');
                    if (newKpi && curKpi) curKpi.innerHTML = newKpi.innerHTML;

                    const newFlash = doc.getElementById('flashBanner');
                    if (newFlash) {
                        const span = newFlash.querySelector('span');
                        showToast(span ? span.textContent : 'Saved.', newFlash.dataset.flashType === 'red');
                    }

                    document.getElementById('editGameModal').classList.add('hidden');

                    // Briefly highlight the row that was just saved so there's
                    // feedback that something happened, without scrolling to it -
                    // the page never navigated, so the scroll position was never
                    // touched in the first place.
                    const row = document.querySelector('#gamesTable tbody tr[data-id="' + CSS.escape(gameId) + '"]');
                    if (row) {
                        row.classList.add('bg-indigo-50');
                        setTimeout(() => row.classList.remove('bg-indigo-50'), 900);
                    }
                })
                .catch(() => {
                    showToast('Could not save - check your connection and try again.', true);
                })
                .finally(() => {
                    if (submitBtn) submitBtn.disabled = false;
                });

            return false;
        }

        function ajaxSubmitAddForm(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            fetch(window.location.href, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
                .then(r => r.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');

                    const newTbody = doc.getElementById('gamesTableBody');
                    const curTbody = document.getElementById('gamesTableBody');
                    if (newTbody && curTbody) {
                        curTbody.innerHTML = newTbody.innerHTML;
                        applyColumnOrder();
                        reapplyCurrentSort();
                        if (typeof filterTable === 'function') filterTable();
                    }

                    const newKpi = doc.getElementById('kpiCardsRow');
                    const curKpi = document.getElementById('kpiCardsRow');
                    if (newKpi && curKpi) curKpi.innerHTML = newKpi.innerHTML;

                    const newFlash = doc.getElementById('flashBanner');
                    showToast(newFlash ? newFlash.textContent.trim() : 'Title added successfully.', false);

                    document.getElementById('addGameModal').classList.add('hidden');
                    form.reset();
                    const previewCont = document.getElementById('add_cover_preview_container');
                    if (previewCont) previewCont.classList.add('hidden');
                    const nameLabel = document.getElementById('add_cover_name');
                    if (nameLabel) nameLabel.textContent = 'No file chosen';
                })
                .catch(() => {
                    showToast('Could not save - check your connection and try again.', true);
                })
                .finally(() => {
                    if (submitBtn) submitBtn.disabled = false;
                });

            return false;
        }

        // ---- Column order, now reordered right inside Manage Fields (the
        // arrows on each enabled field's row) instead of a separate dialog ----
        let fieldOrderDirty = false;
        function moveFieldRow(btn, dir) {
            const row = btn.closest('[data-field-order-key]');
            if (!row) return;
            const sib = dir < 0 ? row.previousElementSibling : row.nextElementSibling;
            if (!sib || !sib.hasAttribute('data-field-order-key')) return;
            if (dir < 0) row.parentElement.insertBefore(row, sib);
            else row.parentElement.insertBefore(sib, row);

            fieldOrderDirty = true;
            const keys = Array.from(document.querySelectorAll('#fieldManagerList [data-field-order-key]')).map(el => el.dataset.fieldOrderKey);
            const formData = new FormData();
            formData.append('action', 'save_column_order');
            formData.append('platform_id', '<?= (int)$active_platform_id ?>');
            keys.forEach(k => formData.append('order[]', k));
            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => { if (!data.success) alert('Failed to save column order' + (data.error ? ': ' + data.error : '.')); })
            .catch(() => alert('Failed to save column order. Ensure admin privileges and write permissions.'));
        }

        // ---- Manage KPIs dialog ----
        const KPI_FIELDS = <?= $is_admin ? json_encode($kpi_fields, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '{}' ?>;
        const KPI_OPERATORS = <?= $is_admin ? json_encode(KPI_OPERATORS, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '{}' ?>;
        let ALL_KPIS = <?= $is_admin ? json_encode(array_map(function($k) use ($kpi_fields) { $k['summary'] = kpi_summary($k, $kpi_fields); return $k; }, $all_kpis), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '[]' ?>;
        let ENABLED_KPI_IDS = <?= $is_admin ? json_encode($enabled_kpi_ids) : '[]' ?>;
        let kpiListDirty = false;

        let otherKpisExpanded = false;

        function openKpiManager() {
            kpiListDirty = false;
            otherKpisExpanded = false;
            renderKpiList();
            document.getElementById('kpiFormWrap').classList.add('hidden');
            document.getElementById('kpiManagerModal').classList.remove('hidden');
        }
        function closeKpiManager() {
            document.getElementById('kpiManagerModal').classList.add('hidden');
            if (kpiListDirty) location.reload();
        }

        function kpiRowHtml(k, enabled) {
            return `<div class="flex items-center justify-between gap-2 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2" data-kpi-id="${k.id}">
                <label class="flex items-center gap-2 min-w-0 cursor-pointer flex-1">
                    <input type="checkbox" ${enabled ? 'checked' : ''} onchange="toggleKpiPlatform(${k.id}, this.checked)" class="h-4 w-4 text-indigo-600 rounded flex-shrink-0">
                    <span class="w-2.5 h-2.5 rounded-full bg-${k.color}-500 flex-shrink-0"></span>
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-slate-700 truncate">${escapeHtml(k.label)}</span>
                        <span class="block text-[11px] text-slate-400 truncate">${escapeHtml(k.summary)}</span>
                    </span>
                </label>
                <span class="flex items-center gap-0.5 flex-shrink-0">
                    ${enabled ? `
                    <button type="button" onclick="moveKpiRow(this, -1)" title="Move up" class="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-indigo-600 rounded"><i class="fa-solid fa-chevron-up"></i></button>
                    <button type="button" onclick="moveKpiRow(this, 1)" title="Move down" class="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-indigo-600 rounded"><i class="fa-solid fa-chevron-down"></i></button>` : ''}
                    <button type="button" onclick="openKpiForm(ALL_KPIS.find(x => x.id === ${k.id}))" title="Edit" class="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-indigo-600 rounded"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" onclick="deleteKpi(${k.id})" title="Delete" class="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-rose-600 rounded"><i class="fa-solid fa-trash-can"></i></button>
                </span>
            </div>`;
        }

        // Enabled KPIs (this platform) stay on top, in display order, with
        // arrows to reorder them. Everything else sits collapsed under
        // "Other KPIs" - expand it to switch more on.
        function renderKpiList() {
            const enabledList = document.getElementById('kpiEnabledList');
            const otherList = document.getElementById('kpiOtherList');
            const otherToggle = document.getElementById('kpiOtherToggle');
            const otherLabel = document.getElementById('kpiOtherToggleLabel');
            const otherChevron = document.getElementById('kpiOtherChevron');

            const enabled = ALL_KPIS.filter(k => ENABLED_KPI_IDS.includes(k.id));
            const other = ALL_KPIS.filter(k => !ENABLED_KPI_IDS.includes(k.id));

            enabledList.innerHTML = enabled.length
                ? enabled.map(k => kpiRowHtml(k, true)).join('')
                : '<p class="text-xs text-slate-400 italic">No KPIs enabled here yet - turn one on below, or use "New KPI".</p>';

            otherList.innerHTML = other.map(k => kpiRowHtml(k, false)).join('');

            if (other.length) {
                otherToggle.classList.remove('hidden');
                otherLabel.textContent = 'Other KPIs (' + other.length + ')';
            } else {
                otherToggle.classList.add('hidden');
                otherList.classList.add('hidden');
                othersExpandedReset();
            }
            otherList.classList.toggle('hidden', !otherKpisExpanded);
            otherChevron.classList.toggle('rotate-90', otherKpisExpanded);
        }
        function othersExpandedReset() { otherKpisExpanded = false; }

        function toggleOtherKpis() {
            otherKpisExpanded = !otherKpisExpanded;
            document.getElementById('kpiOtherList').classList.toggle('hidden', !otherKpisExpanded);
            document.getElementById('kpiOtherChevron').classList.toggle('rotate-90', otherKpisExpanded);
        }

        function toggleKpiPlatform(kpiId, on) {
            kpiListDirty = true;
            if (on) { if (!ENABLED_KPI_IDS.includes(kpiId)) ENABLED_KPI_IDS.push(kpiId); }
            else { ENABLED_KPI_IDS = ENABLED_KPI_IDS.filter(id => id !== kpiId); }
            otherKpisExpanded = true; // keep the panel open through the re-render below
            renderKpiList();

            const formData = new FormData();
            formData.append('action', 'toggle_kpi_platform');
            formData.append('platform_id', '<?= (int)$active_platform_id ?>');
            formData.append('kpi_id', kpiId);
            if (on) formData.append('on', '1');
            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => { if (!data.success) alert('Failed to update KPI' + (data.error ? ': ' + data.error : '.')); })
            .catch(() => alert('Failed to update KPI. Ensure admin privileges and write permissions.'));
        }

        function moveKpiRow(btn, dir) {
            const row = btn.closest('[data-kpi-id]');
            if (!row) return;
            if (dir < 0 && row.previousElementSibling) {
                row.parentElement.insertBefore(row, row.previousElementSibling);
            } else if (dir > 0 && row.nextElementSibling) {
                row.parentElement.insertBefore(row.nextElementSibling, row);
            } else {
                return;
            }
            kpiListDirty = true;
            // Order posted is: enabled KPIs in their new order, then every
            // other KPI unchanged - save_kpi_order keeps anything left off
            // the list in its previous relative position anyway, but this
            // way the enabled ones always sort first server-side too.
            const enabledIds = Array.from(document.querySelectorAll('#kpiEnabledList [data-kpi-id]')).map(el => parseInt(el.dataset.kpiId, 10));
            const otherIds = Array.from(document.querySelectorAll('#kpiOtherList [data-kpi-id]')).map(el => parseInt(el.dataset.kpiId, 10));
            const ids = enabledIds.concat(otherIds);
            const formData = new FormData();
            formData.append('action', 'save_kpi_order');
            ids.forEach(id => formData.append('order[]', id));
            fetch('', { method: 'POST', body: formData }).catch(() => {});
        }

        function deleteKpi(kpiId) {
            const kpi = ALL_KPIS.find(x => x.id === kpiId);
            if (!confirm('Delete the KPI "' + (kpi ? kpi.label : '') + '"? This removes it from every platform.')) return;
            const formData = new FormData();
            formData.append('action', 'delete_kpi');
            formData.append('platform_id', '<?= (int)$active_platform_id ?>');
            formData.append('kpi_id', kpiId);
            fetch('', { method: 'POST', body: formData }).then(() => location.reload());
        }

        // ---- KPI add/edit form ----
        function kpiFieldTypeOf(key) {
            return (KPI_FIELDS[key] && KPI_FIELDS[key].type) || 'text';
        }

        function buildKpiConditionRow(prefix, allowNone) {
            const noneLabel = allowNone ? '— AND (none) —' : '— All Titles —';
            const opts = ['<option value="">' + noneLabel + '</option>']
                .concat(Object.keys(KPI_FIELDS).map(k => `<option value="${k}">${escapeHtml(KPI_FIELDS[k].label)}</option>`));
            return `<div class="flex flex-wrap items-center gap-1.5">
                <select name="${prefix}_field" onchange="onKpiFieldChange('${prefix}')" class="border border-slate-300 rounded-lg px-2 py-1.5 text-xs flex-1 min-w-[130px]">${opts.join('')}</select>
                <select name="${prefix}_op" id="${prefix}_op" disabled class="border border-slate-300 rounded-lg px-2 py-1.5 text-xs"></select>
                <span id="${prefix}_value_wrap" class="flex-1 min-w-[100px]"></span>
            </div>`;
        }

        function onKpiFieldChange(prefix) {
            const form = document.getElementById('kpiForm');
            const key = form.querySelector(`[name="${prefix}_field"]`).value;
            const opSel = document.getElementById(prefix + '_op');
            const valWrap = document.getElementById(prefix + '_value_wrap');
            if (!key) {
                opSel.innerHTML = '';
                opSel.disabled = true;
                valWrap.innerHTML = '';
                return;
            }
            opSel.disabled = false;
            const ops = KPI_OPERATORS[kpiFieldTypeOf(key)] || KPI_OPERATORS.text;
            opSel.innerHTML = Object.keys(ops).map(o => `<option value="${o}">${escapeHtml(ops[o])}</option>`).join('');
            opSel.onchange = () => onKpiOpChange(prefix);
            onKpiOpChange(prefix);
        }

        function onKpiOpChange(prefix) {
            const form = document.getElementById('kpiForm');
            const key = form.querySelector(`[name="${prefix}_field"]`).value;
            const op = document.getElementById(prefix + '_op').value;
            const valWrap = document.getElementById(prefix + '_value_wrap');
            if (['true', 'false', 'empty', 'not_empty'].includes(op)) {
                valWrap.innerHTML = '';
                return;
            }
            const f = KPI_FIELDS[key];
            if (f && f.type === 'select' && f.choices && f.choices.length) {
                valWrap.innerHTML = `<select name="${prefix}_value" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-xs">`
                    + f.choices.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('') + `</select>`;
            } else if (f && f.type === 'number') {
                valWrap.innerHTML = `<input type="number" step="any" name="${prefix}_value" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-xs">`;
            } else {
                valWrap.innerHTML = `<input type="text" name="${prefix}_value" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-xs">`;
            }
        }

        function onKpiCalcTypeChange() {
            const form = document.getElementById('kpiForm');
            const calcType = form.querySelector('[name="calc_type"]:checked').value;
            const isPct = calcType === 'percentage';
            const isTop = calcType === 'top_value';
            document.getElementById('kf_denominator_wrap').classList.toggle('hidden', !isPct);
            document.getElementById('kf_group_field_wrap').classList.toggle('hidden', !isTop);
            document.getElementById('kf_num_label').textContent = isPct ? 'Percentage where' : (isTop ? 'Among titles where (optional)' : 'Count where');
        }

        function renderKpiGroupFieldOptions(current) {
            const sel = document.getElementById('kf_group_field');
            sel.innerHTML = Object.keys(KPI_FIELDS).map(k => `<option value="${k}">${escapeHtml(KPI_FIELDS[k].label)}</option>`).join('');
            sel.value = current || '';
        }

        function renderKpiColorSwatches(current) {
            const wrap = document.getElementById('kf_color_swatches');
            wrap.innerHTML = Object.keys(COLOR_OPTIONS).map(key =>
                `<button type="button" onclick="selectKpiColor('${key}')" data-color="${key}" title="${escapeHtml(COLOR_OPTIONS[key])}" class="w-6 h-6 rounded-full border-2 ${key === current ? 'border-slate-800' : 'border-white'} bg-${key}-500 shadow-sm"></button>`
            ).join('');
            document.getElementById('kf_color').value = current;
        }
        function selectKpiColor(key) {
            document.getElementById('kf_color').value = key;
            document.querySelectorAll('#kf_color_swatches button').forEach(b => {
                b.classList.toggle('border-slate-800', b.dataset.color === key);
                b.classList.toggle('border-white', b.dataset.color !== key);
            });
        }

        function openKpiForm(kpi) {
            kpiListDirty = true; // any save from here changes the definitions list
            const form = document.getElementById('kpiForm');
            form.reset();
            document.getElementById('kf_kpi_id').value = kpi ? kpi.id : 0;
            document.getElementById('kf_label').value = kpi ? kpi.label : '';
            document.getElementById('kf_enable_here').checked = kpi ? ENABLED_KPI_IDS.includes(kpi.id) : true;
            renderKpiColorSwatches(kpi ? kpi.color : 'slate');

            document.getElementById('kf_numerator').innerHTML = buildKpiConditionRow('n1', false) + buildKpiConditionRow('n2', true);
            document.getElementById('kf_denominator').innerHTML = buildKpiConditionRow('d1', false) + buildKpiConditionRow('d2', true);
            renderKpiGroupFieldOptions(kpi ? kpi.group_field : '');

            const calcType = kpi ? kpi.calc_type : 'count';
            form.querySelector(`[name="calc_type"][value="${calcType}"]`).checked = true;
            onKpiCalcTypeChange();

            ['n1', 'n2', 'd1', 'd2'].forEach(p => {
                const val = kpi ? (kpi[p + '_field'] || '') : '';
                if (!val) { onKpiFieldChange(p); return; }
                form.querySelector(`[name="${p}_field"]`).value = val;
                onKpiFieldChange(p);
                const opSel = document.getElementById(p + '_op');
                opSel.value = kpi[p + '_op'] || '';
                onKpiOpChange(p);
                const valInput = form.querySelector(`[name="${p}_value"]`);
                if (valInput) valInput.value = kpi[p + '_value'] || '';
            });

            document.getElementById('kpiFormWrap').classList.remove('hidden');
            document.getElementById('kpiFormWrap').scrollIntoView({ block: 'nearest' });
        }

        function closeKpiForm() {
            document.getElementById('kpiFormWrap').classList.add('hidden');
        }

        // ---- Manage Fields dialog ----
        const FIELDS = <?= $is_admin ? json_encode($fields_js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '{}' ?>;

        // ---- Platform templates ----
        const TEMPLATES = <?= $is_admin ? json_encode($templates_js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '{}' ?>;

        let selectedTemplateId = null;

        // Always opens "Add New Platform" with a clean slate — no leftover picks
        // from a template applied (or a form left half-filled) the last time it was open.
        function openAddPlatformModal() {
            if (typeof closePlatformDropdown === 'function') closePlatformDropdown();
            const modal = document.getElementById('platformModal');
            if (!modal) return;
            const form = modal.querySelector('form[method="POST"]');
            if (form) form.reset();
            const select = document.getElementById('platformTemplateSelect');
            if (select) select.value = '';
            const updateBtn = document.getElementById('updateTemplateBtn');
            if (updateBtn) updateBtn.classList.add('hidden');
            selectedTemplateId = null;
            modal.classList.remove('hidden');
            setTimeout(() => {
                const el = modal.querySelector('input[name="name"]');
                if (el) el.focus();
            }, 50);
        }

        function applyPlatformTemplate(id) {
            const modal = document.getElementById('platformModal');
            if (!modal) return;
            const checkboxes = modal.querySelectorAll('input[name="fields[]"]');
            const nameInput = modal.querySelector('input[name="name"]');
            const updateBtn = document.getElementById('updateTemplateBtn');

            selectedTemplateId = id || null;

            if (!id) {
                // "Start from scratch": clear everything back to a blank platform,
                // except Title - the one built-in that makes sense on by default.
                if (updateBtn) updateBtn.classList.add('hidden');
                if (nameInput) nameInput.value = '';
                checkboxes.forEach(cb => { cb.checked = TITLE_FIELD_ID > 0 && parseInt(cb.value, 10) === TITLE_FIELD_ID; });
                return;
            }
            const t = TEMPLATES[id];
            if (!t) return;
            checkboxes.forEach(cb => {
                cb.checked = t.field_ids.includes(parseInt(cb.value, 10));
            });
            if (nameInput && t.default_name) nameInput.value = t.default_name;
            if (updateBtn) {
                updateBtn.innerHTML = '<i class="fa-solid fa-rotate mr-1"></i>Update "' + escapeHtml(t.name) + '"';
                updateBtn.classList.remove('hidden');
            }
        }

        // Fills in and submits the hidden template form: templateId 0 creates a new
        // template, a positive id overwrites that existing one (built-in or custom)
        function submitTemplateForm(name, templateId) {
            const modal = document.getElementById('platformModal');
            const nameInput = modal.querySelector('input[name="name"]');
            const form = document.getElementById('saveTemplateForm');

            document.getElementById('st_name').value = name;
            document.getElementById('st_default_name').value = (nameInput && nameInput.value.trim()) ? nameInput.value.trim() : '';
            document.getElementById('st_template_id').value = templateId || '0';

            form.querySelectorAll('input[name="fields[]"]').forEach(el => el.remove());
            modal.querySelectorAll('input[name="fields[]"]:checked').forEach(cb => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'fields[]';
                hidden.value = cb.value;
                form.appendChild(hidden);
            });
            form.submit();
        }

        function saveCurrentAsTemplate() {
            const modal = document.getElementById('platformModal');
            const nameInput = modal.querySelector('input[name="name"]');
            const suggested = (nameInput && nameInput.value.trim()) ? nameInput.value.trim() : '';
            const name = prompt('Name this template (e.g. "Evercade", "Retro Console"):', suggested);
            if (!name || !name.trim()) return;
            submitTemplateForm(name.trim(), 0);
        }

        function updateCurrentTemplate() {
            if (!selectedTemplateId) return;
            const t = TEMPLATES[selectedTemplateId];
            if (!t) return;
            if (!confirm(`Update the template "${t.name}" with the current setup?`)) return;
            submitTemplateForm(t.name, selectedTemplateId);
        }

        function deleteTemplate(id, name) {
            if (!confirm(`Delete the template "${name}"?`)) return;
            document.getElementById('delete_template_id').value = id;
            document.getElementById('deleteTemplateForm').submit();
        }
        const COLOR_OPTIONS = <?= $is_admin ? json_encode(CHOICE_COLORS, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '{}' ?>;
        let ffLockedChoices = null;  // fixed choice list of a locked (built-in) choice-list field being edited
        let ffColors = {};          // choice text => color key, for the field being added/edited

        function openFieldManager() {
            const modal = document.getElementById('fieldManagerModal');
            if (!modal) return;
            ['platformModal', 'editPlatformModal'].forEach(id => {
                const m = document.getElementById(id);
                if (m) m.classList.add('hidden');
            });
            resetFieldForm();
            modal.classList.remove('hidden');
        }

        function closeFieldManager() {
            const modal = document.getElementById('fieldManagerModal');
            if (modal) modal.classList.add('hidden');
            if (fieldOrderDirty) { fieldOrderDirty = false; location.reload(); }
        }

        function currentChoiceList() {
            if (ffLockedChoices) return ffLockedChoices;
            return document.getElementById('ff_options').value.split('\n').map(s => s.trim()).filter(Boolean);
        }

        function escapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        // ---- Export Titles (CSV) field picker ----
        function setCsvFields(on) {
            document.querySelectorAll('.csv-field-cb').forEach(cb => { cb.checked = on; });
        }

        // Rebuilds the per-choice color pickers from the current choice list, and
        // keeps the hidden colors_json field (what actually gets submitted) in sync.
        function renderChoiceColors() {
            const type = document.getElementById('ff_type').value;
            const wrap = document.getElementById('ff_colors_wrap');
            const list = document.getElementById('ff_colors_list');
            const choices = (type === 'select') ? currentChoiceList() : [];

            if (choices.length === 0) {
                wrap.classList.add('hidden');
                list.innerHTML = '';
                document.getElementById('ff_colors_json').value = '{}';
                return;
            }

            wrap.classList.remove('hidden');
            const trimmed = {};
            list.innerHTML = choices.map(choice => {
                const current = ffColors[choice] || 'slate';
                trimmed[choice] = current;
                const opts = Object.keys(COLOR_OPTIONS).map(key =>
                    `<option value="${key}" ${key === current ? 'selected' : ''}>${escapeHtml(COLOR_OPTIONS[key])}</option>`
                ).join('');
                return '<div class="flex items-center gap-2">'
                     + '<span class="flex-1 text-sm text-slate-700 truncate">' + escapeHtml(choice) + '</span>'
                     + '<select data-choice="' + escapeHtml(choice) + '" onchange="updateChoiceColor(this)" class="border border-slate-300 rounded-lg px-2 py-1 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">' + opts + '</select>'
                     + '</div>';
            }).join('');
            ffColors = trimmed;
            document.getElementById('ff_colors_json').value = JSON.stringify(trimmed);
        }

        function updateChoiceColor(sel) {
            ffColors[sel.dataset.choice] = sel.value;
            document.getElementById('ff_colors_json').value = JSON.stringify(ffColors);
        }

        function onFieldTypeChange() {
            const type = document.getElementById('ff_type').value;
            const locked = document.getElementById('ff_type').disabled;
            document.getElementById('ff_options_wrap').classList.toggle('hidden', type !== 'select' || locked);

            const quickWrap = document.getElementById('ff_quick_wrap');
            const showQuick = (type === 'yesno' || type === 'select');
            quickWrap.classList.toggle('hidden', !showQuick);
            quickWrap.classList.toggle('flex', showQuick);
            if (!showQuick) document.getElementById('ff_quick_toggle').checked = false;

            // Bold only makes sense for plain text/number cells - yesno and select
            // already stand out as colored badges.
            const boldWrap = document.getElementById('ff_bold_wrap');
            const showBold = (type === 'text' || type === 'number');
            boldWrap.classList.toggle('hidden', !showBold);
            boldWrap.classList.toggle('flex', showBold);
            if (!showBold) document.getElementById('ff_bold').checked = false;

            renderChoiceColors();
        }

        function resetFieldForm() {
            const form = document.getElementById('fieldForm');
            if (!form) return;
            form.reset();
            document.getElementById('ff_id').value = '';
            document.getElementById('ff_type').disabled = false;
            document.getElementById('ffTitle').textContent = 'Add a new field';
            document.getElementById('ffSubmit').textContent = 'Add Field';
            document.getElementById('ffCancelEdit').classList.add('hidden');
            document.getElementById('ff_builtin_note').classList.add('hidden');
            const enableWrap = document.getElementById('ff_enable_wrap');
            if (enableWrap) enableWrap.classList.remove('hidden');
            ffLockedChoices = null;
            ffColors = {};
            onFieldTypeChange();
        }

        function editField(id) {
            const f = FIELDS[id];
            if (!f) return;
            resetFieldForm();
            const locked = f.builtin === 1;

            document.getElementById('ff_id').value = f.id;
            document.getElementById('ff_label').value = f.label;
            document.getElementById('ff_desc').value = f.description;
            document.getElementById('ff_type').value = f.type;
            document.getElementById('ff_options').value = f.options || '';
            document.getElementById('ff_type').disabled = locked;
            document.getElementById('ff_sortable').checked = f.sortable == 1;
            document.getElementById('ff_quick_toggle').checked = f.quick_toggle == 1;
            document.getElementById('ff_bold').checked = f.bold == 1;

            ffLockedChoices = (locked && f.type === 'select') ? f.choices : null;
            ffColors = Object.assign({}, f.colors || {});

            onFieldTypeChange();
            if (locked) document.getElementById('ff_options_wrap').classList.add('hidden');

            document.getElementById('ff_builtin_note').classList.toggle('hidden', !locked);
            const enableWrap = document.getElementById('ff_enable_wrap');
            if (enableWrap) enableWrap.classList.add('hidden');

            document.getElementById('ffTitle').textContent = 'Edit field';
            document.getElementById('ffSubmit').textContent = 'Save Changes';
            document.getElementById('ffCancelEdit').classList.remove('hidden');
            document.getElementById('fieldForm').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            document.getElementById('ff_label').focus();
        }

        function deleteField(id) {
            const f = FIELDS[id];
            if (!f) return;
            let msg = `Delete the field "${f.label}"?\n\nIt will be removed from ${f.used} platform(s).`;
            if (f.builtin === 1) {
                msg += `\n\nThis is a built-in field: the data already saved stays in the database and reappears if you restore the built-in fields later.`;
            } else {
                msg += `\n\nEvery value entered for this field will be permanently deleted.`;
            }
            if (!confirm(msg)) return;
            document.getElementById('delete_field_id').value = id;
            document.getElementById('deleteFieldForm').submit();
        }

        function setCoverField(fieldId) {
            const formData = new FormData();
            formData.append('action', 'set_cover_field_ajax');
            formData.append('platform_id', '<?= (int)$active_platform_id ?>');
            formData.append('field_id', fieldId);

            // Disable all cover buttons while saving
            document.querySelectorAll('.cover-field-btn').forEach(b => b.disabled = true);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        alert(data.error || 'Failed');
                        document.querySelectorAll('.cover-field-btn').forEach(b => b.disabled = false);
                        return;
                    }
                    // Reload page with manage_fields open so the table updates
                    // and the dialog stays visible
                    const url = new URL(window.location.href);
                    url.searchParams.set('manage_fields', '1');
                    window.location.href = url.toString();
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                    document.querySelectorAll('.cover-field-btn').forEach(b => b.disabled = false);
                });
        }

        // Drop ?manage_fields=1 from the address bar so a refresh doesn't reopen the dialog
        if (location.search.indexOf('manage_fields=1') !== -1) {
            const cleanUrl = new URL(location.href);
            cleanUrl.searchParams.delete('manage_fields');
            history.replaceState(null, '', cleanUrl.toString());
        }

        // Same for ?manage_kpis=1 - also populate the list, since (unlike the
        // Manage Fields checklist) it's built by JS rather than rendered server-side.
        if (location.search.indexOf('manage_kpis=1') !== -1) {
            renderKpiList();
            const cleanUrl = new URL(location.href);
            cleanUrl.searchParams.delete('manage_kpis');
            history.replaceState(null, '', cleanUrl.toString());
        }


        // Initial replay of saved sorts when the page loads
        reapplyCurrentSort();

        // Selection & Mass Delete
        function toggleSelectAll(masterCheckbox) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (row.style.display !== 'none') {
                    cb.checked = masterCheckbox.checked;
                }
            });
            updateSelectionCount();
        }

        function updateSelectionCount() {
            const selected = document.querySelectorAll('.row-checkbox:checked');
            const bar = document.getElementById('bulkActionBar');
            const badge = document.getElementById('selectedCountBadge');

            if (bar) {
                if (selected.length > 0) {
                    bar.classList.remove('hidden');
                    badge.innerText = selected.length;
                } else {
                    bar.classList.add('hidden');
                }
            }
        }

        function clearAllSelections() {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
            const master = document.getElementById('selectAllCheckbox');
            if (master) master.checked = false;
            updateSelectionCount();
        }

        function confirmBulkDelete() {
            const count = document.querySelectorAll('.row-checkbox:checked').length;
            if (count === 0) return false;
            return confirm(`Are you sure you want to permanently delete ${count} selected title(s)?`);
        }

        function deleteSingleGame(gameId, title) {
            const label = title ? `"${title}"` : 'this title';
            if (confirm(`Delete ${label} from collection?`)) {
                document.getElementById('delete_game_id').value = gameId;
                document.getElementById('singleDeleteForm').submit();
            }
        }

        // Reload the page, but first wait for any inline note edit that's
        // still saving — otherwise a quick-toggle reload can win the race
        // and cancel the note's save before it reaches the server.
        function reloadAfterPendingSave() {
            if (pendingNoteSave) {
                pendingNoteSave.finally(() => location.reload());
            } else {
                location.reload();
            }
        }

        // Toggle Status AJAX
        function toggleOwned(gameId, btn) {
            const formData = new FormData();
            formData.append('action', 'toggle_owned_ajax');
            formData.append('game_id', gameId);

            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    reloadAfterPendingSave();
                } else if (data.error) {
                    alert(data.error);
                }
            })
            .catch(err => {
                alert('Action failed. Ensure admin privileges and write permissions.');
            });
        }

        // Generic quick-change: cycles a Yes/No or Choice-list field's value for one
        // row, for any field with "Quick change in table" turned on in Manage Fields.
        function quickToggleField(gameId, fieldRef, btn) {
            const formData = new FormData();
            formData.append('action', 'cycle_field_ajax');
            formData.append('game_id', gameId);
            formData.append('field_ref', fieldRef);

            if (btn) btn.disabled = true;
            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    reloadAfterPendingSave();
                } else if (data.error) {
                    alert(data.error);
                    if (btn) btn.disabled = false;
                }
            })
            .catch(err => {
                alert('Action failed. Ensure admin privileges and write permissions.');
                if (btn) btn.disabled = false;
            });
        }



        // Clicking a button gives it default focus, and if the button isn't
        // fully on screen (e.g. right at the bottom edge of the viewport), the
        // browser auto-scrolls it fully into view as part of that - a native
        // behavior that happens before our click handler even runs, so it can't
        // be "restored" after the fact, only pre-empted. rememberScrollBeforeEdit
        // grabs the true position on mousedown (just before the jump happens);
        // openEditModal snaps straight back to it in the same tick, so the jump
        // never actually gets painted.
        let scrollYBeforeEdit = null;
        function rememberScrollBeforeEdit() {
            scrollYBeforeEdit = window.scrollY || window.pageYOffset || 0;
        }

        function openEditModal(game) {
            if (scrollYBeforeEdit !== null) {
                window.scrollTo(0, scrollYBeforeEdit);
                scrollYBeforeEdit = null;
            }

            document.getElementById('edit_game_id').value = game.id;
            const titleField = document.getElementById('edit_title');
            if (titleField) titleField.value = game.title || '';
            
            const relInput = document.getElementById('edit_release_no');
            if (relInput) relInput.value = (game.release_no !== null && game.release_no !== undefined) ? game.release_no : '';

            const seriesInput = document.getElementById('edit_line_series');
            if (seriesInput) seriesInput.value = game.line_series || '';

            const regionInput = document.getElementById('edit_region');
            if (regionInput) regionInput.value = game.region || 'EUR (PAL)';

            const mediaInput = document.getElementById('edit_media_type');
            if (mediaInput) mediaInput.value = game.media_type || 'Physical';

            const cibYes = document.getElementById('edit_cib_yes');
            const cibNo = document.getElementById('edit_cib_no');
            if (cibYes && cibNo) {
                if (game.is_cib == 1) cibYes.checked = true;
                else cibNo.checked = true;
            }

            const legacyCheck = document.getElementById('edit_is_legacy');
            if (legacyCheck) legacyCheck.checked = game.is_legacy == 1;

            const ownedCheck = document.getElementById('edit_is_owned');
            if (ownedCheck) {
                if (ownedCheck.type === 'checkbox') ownedCheck.checked = game.is_owned == 1;
                else ownedCheck.value = game.is_owned;
            }

            // Custom fields
            document.querySelectorAll('#editGameModal .edit-cf').forEach(el => {
                const raw = (game.cf && game.cf[el.dataset.fieldId] !== undefined) ? String(game.cf[el.dataset.fieldId]) : '';
                if (el.type === 'checkbox') {
                    el.checked = raw === '1';
                } else if (el.tagName === 'SELECT') {
                    el.querySelectorAll('option[data-extra]').forEach(o => o.remove());
                    if (raw !== '' && !Array.from(el.options).some(o => o.value === raw)) {
                        // Keep a value whose choice was later removed from the list, so saving doesn't erase it
                        const extra = new Option(raw, raw);
                        extra.dataset.extra = '1';
                        el.add(extra);
                    }
                    el.value = raw;
                } else {
                    el.value = raw;
                }
            });

            const notesInput = document.getElementById('edit_notes');
            if (notesInput) notesInput.value = game.notes || '';

            // Existing cover artwork handling
            const currCoverContainer = document.getElementById('edit_current_cover_container');
            const currCoverImg = document.getElementById('edit_current_cover_img');
            const removeCoverCb = document.getElementById('edit_remove_cover');
            const editCoverInput = document.getElementById('edit_cover_input');
            const editCoverName = document.getElementById('edit_cover_name');
            const editCoverPreviewContainer = document.getElementById('edit_cover_preview_container');
            const editUploadBtnLabel = document.getElementById('edit_upload_btn_label');

            if (editCoverInput) editCoverInput.value = '';
            if (editCoverName) editCoverName.textContent = 'No new file chosen';
            if (editCoverPreviewContainer) editCoverPreviewContainer.classList.add('hidden');
            if (removeCoverCb) {
                removeCoverCb.checked = false;
                toggleRemoveCover(removeCoverCb);
            }

            if (game.image_path) {
                if (currCoverContainer) currCoverContainer.classList.remove('hidden');
                if (currCoverImg) {
                    currCoverImg.src = game.image_path;
                    currCoverImg.classList.remove('opacity-30', 'grayscale');
                }
                if (editUploadBtnLabel) editUploadBtnLabel.textContent = 'Replace cover image...';
            } else {
                if (currCoverContainer) currCoverContainer.classList.add('hidden');
                if (currCoverImg) currCoverImg.src = '';
                if (editUploadBtnLabel) editUploadBtnLabel.textContent = 'Upload cover image...';
            }

            refreshClearButtons(document.getElementById('editGameModal'));

            document.getElementById('editGameModal').classList.remove('hidden');
            setTimeout(() => {
                const titleInput = document.getElementById('edit_title');
                if (titleInput) { titleInput.focus(); titleInput.select(); }
            }, 0);
        }

        function toggleSearchClear() {
            const input = document.getElementById('searchInput');
            const btn = document.getElementById('searchClearBtn');
            if (input && btn) btn.classList.toggle('hidden', input.value.length === 0);
        }

        function clearSearch() {
            const input = document.getElementById('searchInput');
            if (!input) return;
            input.value = '';
            filterTable();
            toggleSearchClear();
            input.focus();
        }

        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const ownedFilter = document.getElementById('ownedFilter') ? document.getElementById('ownedFilter').value : 'ALL';
            const cibFilter = document.getElementById('cibFilter') ? document.getElementById('cibFilter').value : 'ALL';
            const regionFilter = document.getElementById('regionFilter') ? document.getElementById('regionFilter').value : 'ALL';
            const mediaFilter = document.getElementById('mediaFilter') ? document.getElementById('mediaFilter').value : 'ALL';
            const cfFilters = document.querySelectorAll('.cf-filter');
            const rows = document.querySelectorAll('#gamesTable tbody tr');

            rows.forEach(row => {
                const title = row.getAttribute('data-title') || '';
                const notes = row.getAttribute('data-notes') || '';
                const lineSeries = row.getAttribute('data-line-series') || '';
                const region = row.getAttribute('data-region') || '';
                const media = row.getAttribute('data-media-type') || '';
                const owned = row.getAttribute('data-owned') || '';
                const cib = row.getAttribute('data-cib') || '';

                const cfSearch = row.getAttribute('data-cf-search') || '';
                const matchesSearch = title.includes(search) || notes.includes(search) || lineSeries.includes(search) || region.toLowerCase().includes(search) || cfSearch.includes(search);
                const matchesOwned = (ownedFilter === 'ALL') || (owned === ownedFilter);
                const matchesCib = (cibFilter === 'ALL') || (cib === cibFilter);
                const matchesRegion = (regionFilter === 'ALL') || (region.includes(regionFilter));
                const matchesMedia = (mediaFilter === 'ALL') || (media === mediaFilter);
                let matchesCustom = true;
                cfFilters.forEach(sel => {
                    if (sel.value !== 'ALL' && (row.getAttribute('data-cf' + sel.dataset.field) || '') !== sel.value) matchesCustom = false;
                });

                if (matchesSearch && matchesOwned && matchesCib && matchesRegion && matchesMedia && matchesCustom) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Platform Dropdown Functions
        function togglePlatformDropdown(e) {
            e.stopPropagation();
            const menu = document.getElementById('platformDropdownMenu');
            const chevron = document.getElementById('platformChevron');
            const isHidden = menu.classList.contains('hidden');
            
            if (isHidden) {
                menu.classList.remove('hidden');
                if (chevron) chevron.classList.add('rotate-180');
                const input = document.getElementById('platformFilterInput');
                if (input) {
                    input.value = '';
                    filterPlatformList();
                    setTimeout(() => input.focus(), 50);
                }
            } else {
                closePlatformDropdown();
            }
        }

        function closePlatformDropdown() {
            const menu = document.getElementById('platformDropdownMenu');
            const chevron = document.getElementById('platformChevron');
            if (menu) menu.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-180');
        }

        function filterPlatformList() {
            const term = (document.getElementById('platformFilterInput')?.value || '').toLowerCase();
            const items = document.querySelectorAll('.platform-item');
            items.forEach(item => {
                const name = (item.querySelector('.platform-name')?.innerText || '').toLowerCase();
                item.style.display = name.includes(term) ? 'flex' : 'none';
            });
        }

        // Close dropdown when clicking outside
        window.addEventListener('click', function(e) {
            const dropdown = document.getElementById('platformDropdownMenu');
            if (dropdown && !dropdown.contains(e.target)) {
                closePlatformDropdown();
            }
        });


        // Inline Notes Auto-save AJAX
        //
        // Tracked globally so any other action that reloads the page (quick
        // toggles, etc.) can wait for a note save that's still in flight
        // instead of racing it — otherwise the reload cancels the pending
        // fetch and the note is silently lost.
        let pendingNoteSave = null;

        function saveInlineNote(gameId, inputElem) {
            const newNote = inputElem.value;
            // Use an attribute-contains selector instead of an escaped class
            // selector: the raw "/" in "group/note" makes ".group\/note"
            // an easy target for a broken escape (a bare "\/" inside a JS
            // string collapses to "/", producing the invalid selector
            // ".group/note" and throwing before the save request is ever
            // sent). Matching on the class attribute's text sidesteps that.
            const container = inputElem.closest('[class*="group/note"]') || inputElem.parentElement;
            const icon = container ? container.querySelector('.note-status-icon') : null;

            const formData = new FormData();
            formData.append('action', 'update_note_ajax');
            formData.append('game_id', gameId);
            formData.append('notes', newNote);

            const savePromise = fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update data-notes attribute on parent <tr> for live search filtering
                    const row = inputElem.closest('tr');
                    if (row) row.setAttribute('data-notes', newNote.toLowerCase());

                    if (icon) {
                        icon.classList.remove('opacity-0');
                        setTimeout(() => icon.classList.add('opacity-0'), 1500);
                    }
                } else if (data.error) {
                    alert('Failed to save note: ' + data.error);
                }
            })
            .catch(err => {
                alert('Failed to save note. Ensure you are logged in as admin.');
            })
            .finally(() => {
                if (pendingNoteSave === savePromise) pendingNoteSave = null;
            });

            pendingNoteSave = savePromise;
            return savePromise;
        }

        // ---- Add Game Modal clean opener ----
        function openAddGameModal() {
            const modal = document.getElementById('addGameModal');
            if (!modal) return;
            const input = modal.querySelector('input[name="cover_image"]');
            if (input) input.value = '';
            const nameEl = document.getElementById('add_cover_name');
            if (nameEl) nameEl.textContent = 'No file chosen';
            const prevContainer = document.getElementById('add_cover_preview_container');
            if (prevContainer) prevContainer.classList.add('hidden');
            const prevImg = document.getElementById('add_cover_preview');
            if (prevImg) prevImg.src = '';
            modal.classList.remove('hidden');
            setTimeout(() => {
                const el = document.getElementById('add_title');
                if (el) el.focus();
            }, 0);
        }

        // ---- Cover Image Popup / Lightbox ----
        function openImagePopup(src, title) {
            if (!src) return;
            const modal = document.getElementById('imagePreviewModal');
            const img = document.getElementById('imagePreviewSrc');
            const titleEl = document.getElementById('imagePreviewTitle');
            const downloadLink = document.getElementById('imagePreviewDownload');
            if (!modal || !img) return;

            img.src = src;
            if (titleEl) titleEl.textContent = title || 'Cover Artwork';
            if (downloadLink) {
                downloadLink.href = src;
                const safeName = (title ? title.replace(/[^a-zA-Z0-9_-]+/g, '_') : 'cover') + '.jpg';
                downloadLink.setAttribute('download', safeName);
            }
            modal.classList.remove('hidden');
        }

        function closeImagePopup() {
            const modal = document.getElementById('imagePreviewModal');
            if (modal) modal.classList.add('hidden');
            const img = document.getElementById('imagePreviewSrc');
            if (img) img.src = '';
        }

        // Close image popup on Escape key
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('imagePreviewModal');
                if (modal && !modal.classList.contains('hidden')) {
                    closeImagePopup();
                }
            }
        });

        // Client-side instant preview for selected cover image file
        function previewCoverImage(input, previewImgId, containerId, nameLabelId) {
            const file = input.files && input.files[0];
            const nameEl = document.getElementById(nameLabelId);
            const container = document.getElementById(containerId);
            const img = document.getElementById(previewImgId);

            if (!file) {
                if (nameEl) nameEl.textContent = 'No file chosen';
                if (container) container.classList.add('hidden');
                if (img) img.src = '';
                return;
            }

            if (nameEl) nameEl.textContent = file.name;

            const reader = new FileReader();
            reader.onload = function(e) {
                if (img) img.src = e.target.result;
                if (container) container.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }

        function toggleRemoveCover(cb) {
            const img = document.getElementById('edit_current_cover_img');
            if (img) {
                img.classList.toggle('opacity-30', cb.checked);
                img.classList.toggle('grayscale', cb.checked);
            }
        }

        // Database wipe confirmation helpers
        function checkWipeInput(val) {
            const btn = document.getElementById('wipeDbBtn');
            if (!btn) return;
            const match = (val || '').trim().toUpperCase() === 'WIPE';
            btn.disabled = !match;
        }

        function confirmWipeDb() {
            const input = document.getElementById('wipe_confirm_input');
            if (!input || input.value.trim().toUpperCase() !== 'WIPE') {
                alert('Please type WIPE to confirm.');
                return false;
            }
            const confirmed = confirm(
                "⚠️ FINAL WARNING: COMPLETE RESET ⚠️\n\n" +
                "Are you absolutely sure you want to proceed?\n\n" +
                "• ALL platforms and titles will be erased.\n" +
                "• ALL cover artwork images in uploads/covers will be deleted.\n" +
                "• ALL custom fields and templates will be reset.\n\n" +
                "Click OK to permanently wipe everything."
            );
            if (!confirmed) return false;
            setTimeout(() => {
                const modal = document.getElementById('backupModal');
                if (modal) modal.classList.add('hidden');
            }, 0);
            return true;
        }

    </script>
