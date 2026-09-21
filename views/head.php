<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($app_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .row-owned { background-color: #f0fdf4; }
        .row-legacy-missing { background-color: #fef2f2; }
        .row-wishlist { background-color: #f8fafc; }
        .row-neutral { background-color: #ffffff; }
        th.sortable { cursor: pointer; user-select: none; transition: background-color 0.15s; }
        th.sortable:hover { background-color: #1e293b; color: #818cf8; }
        .table-scroll-area { -webkit-overflow-scrolling: touch; }

        /* KPI cards: one fixed, compact size everywhere - mobile, desktop,
           admin and guest alike. Not gated by role or breakpoint on purpose. */
        .kpi-card { padding: 0.3rem 0.55rem; }
        .kpi-label { font-size: 0.8rem; }
        .kpi-value { font-size: 0.8rem; }

        /* Desktop only (both admin and guest): denser search box, filters and
           table so more of the table is visible without scrolling. Below this
           breakpoint, phones keep their own separate compact mobile sizing
           (set via inline Tailwind classes) untouched. */
        @media (min-width: 640px) {
            .toolbar-box { padding: 0.5rem; }
            #searchInput { padding-top: 0.3rem; padding-bottom: 0.3rem; font-size: 0.75rem; }
            #sortSelect,
            #ownedFilter,
            #cibFilter,
            #regionFilter,
            #mediaFilter,
            .cf-filter { padding: 0.3rem 0.5rem; font-size: 0.75rem; }
            #gamesTable.dense-table th,
            #gamesTable.dense-table td { padding-top: 0.375rem; padding-bottom: 0.375rem; padding-left: 0.6rem; padding-right: 0.6rem; }
            #gamesTable.dense-table thead th { padding-top: 0.5rem; padding-bottom: 0.5rem; font-size: 0.65rem; }
        }
    </style>
</head>
