<body class="bg-slate-100 text-slate-800 min-h-screen sm:h-screen sm:h-dvh sm:overflow-hidden sm:flex sm:flex-col">

    <!-- Top Navigation Bar -->
    <header class="bg-slate-900 text-white shadow-md sticky top-0 z-30 flex-shrink-0">
        <div class="max-w-7xl mx-auto px-4 py-3 sm:py-3.5 flex flex-col sm:flex-row sm:flex-wrap justify-between items-stretch sm:items-center gap-3 sm:gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <i class="fa-solid fa-gamepad text-indigo-400 text-2xl flex-shrink-0"></i>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-bold tracking-wide truncate"><?= htmlspecialchars($app_name) ?></h1>
                        <?php if ($is_admin): ?>
                            <button onclick="document.getElementById('appNameModal').classList.remove('hidden')" class="text-slate-400 hover:text-indigo-300 transition text-xs p-1 flex-shrink-0" title="Rename App">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    <span class="text-xs text-slate-400 flex items-center gap-1.5">
                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0 <?= $is_admin ? 'bg-emerald-400' : 'bg-amber-400' ?>"></span>
                        Mode: <strong class="<?= $is_admin ? 'text-emerald-300' : 'text-amber-300' ?>"><?= $is_admin ? 'Admin (Full Access)' : 'Read-Only (Guest)' ?></strong>
                    </span>
                </div>
            </div>

            <!-- Platform Dropdown Selector & Add Button -->
            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <div class="relative flex-1 sm:flex-none min-w-0">
                    <button type="button" onclick="togglePlatformDropdown(event)" class="w-full sm:w-auto flex items-center gap-2.5 bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-xl text-sm font-semibold border border-slate-700 shadow-sm transition">
                        <i class="fa-solid fa-layer-group text-indigo-400 flex-shrink-0"></i>
                        <span class="truncate"><?= htmlspecialchars($current_platform['name'] ?? 'Select Platform') ?></span>
                        <?php if ($current_platform): ?>
                            <span class="bg-indigo-600/50 text-indigo-200 text-xs px-2 py-0.5 rounded-full font-bold flex-shrink-0"><?= $total_count ?></span>
                        <?php endif; ?>
                        <i class="fa-solid fa-chevron-down text-slate-400 text-xs ml-auto sm:ml-1 transition-transform flex-shrink-0" id="platformChevron"></i>
                    </button>

                    <!-- Dropdown Menu -->
                    <div id="platformDropdownMenu" class="hidden absolute left-0 mt-2 w-72 max-w-[90vw] bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl py-2 z-50">
                        <div class="px-3 pb-2 border-b border-slate-700">
                            <div class="relative">
                                <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2 text-slate-400 text-xs"></i>
                                <input type="text" id="platformFilterInput" onkeyup="filterPlatformList()" placeholder="Find platform..." class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-7 pr-3 py-1 text-xs text-white placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            </div>
                        </div>
                        
                        <div class="max-h-64 overflow-y-auto py-1 divide-y divide-slate-700/40" id="platformListContainer">
                            <?php if (empty($platforms)): ?>
                                <div class="px-4 py-3 text-xs text-slate-400 italic">No platforms added yet.</div>
                            <?php else: ?>
                                <?php foreach ($platforms as $p): ?>
                                    <a href="?platform=<?= $p['id'] ?>" class="platform-item flex items-center justify-between px-4 py-2.5 text-sm <?= $p['id'] == $active_platform_id ? 'bg-indigo-600 text-white font-bold' : 'text-slate-200 hover:bg-slate-700/70' ?> transition">
                                        <span class="platform-name"><?= htmlspecialchars($p['name']) ?></span>
                                        <?php if ($p['id'] == $active_platform_id): ?>
                                            <i class="fa-solid fa-check text-xs"></i>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($is_admin): ?>
                            <div class="pt-2 px-3 border-t border-slate-700">
                                <button type="button" onclick="closePlatformDropdown(); openAddPlatformModal();" class="w-full text-center text-xs text-indigo-400 hover:text-indigo-300 py-1 font-semibold flex items-center justify-center gap-1.5">
                                    <i class="fa-solid fa-plus"></i> Add New Platform
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($is_admin): ?>
                    <button type="button" onclick="openAddPlatformModal()" class="bg-slate-800 hover:bg-slate-700 text-indigo-300 hover:text-white px-3 py-2 rounded-xl text-sm font-semibold border border-slate-700 shadow-sm transition" title="Add New Platform">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Auth Buttons -->
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <?php if ($is_admin): ?>
                    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                        <button onclick="document.getElementById('backupModal').classList.remove('hidden')" class="flex-1 sm:flex-none px-3 py-1.5 text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition whitespace-nowrap" title="Backup / Restore Database">
                            <i class="fa-solid fa-database mr-1"></i> Backup/Restore
                        </button>
                        <button onclick="document.getElementById('exportCsvModal').classList.remove('hidden')" class="flex-1 sm:flex-none px-3 py-1.5 text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition whitespace-nowrap" title="Export Titles as CSV">
                            <i class="fa-solid fa-file-csv mr-1"></i> Export
                        </button>
                        <button onclick="document.getElementById('passwordModal').classList.remove('hidden')" class="flex-1 sm:flex-none px-3 py-1.5 text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition whitespace-nowrap" title="Change Admin Password">
                            <i class="fa-solid fa-key mr-1"></i> Password
                        </button>
                        <form method="POST" class="flex-1 sm:flex-none">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="w-full px-3 py-1.5 text-xs bg-rose-600/20 text-rose-300 hover:bg-rose-600 hover:text-white rounded-lg transition font-medium whitespace-nowrap">
                                <i class="fa-solid fa-right-from-bracket mr-1"></i> Logout
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <button onclick="document.getElementById('loginModal').classList.remove('hidden')" class="w-full sm:w-auto px-3.5 py-1.5 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition shadow font-medium">
                        <i class="fa-solid fa-lock mr-1.5"></i> Admin Login
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </header>
