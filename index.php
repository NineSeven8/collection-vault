<?php
/**
 * Retro Game & Cartridge Collection Manager
 * PHP + SQLite application with Admin Authentication & Custom Platform Fields
 *
 * This file just wires the pieces together, in the order each one needs:
 *   1. config.php     - session + DB connection (creates collection.db from
 *                        scratch automatically if it doesn't exist yet)
 *   2. constants.php   - built-in field/KPI definitions
 *   3. helpers.php     - pure functions used by everything below
 *   4. schema.php      - CREATE TABLE/ALTER TABLE + one-time data migrations
 *   5. actions.php     - handles the request if it's a POST (may exit)
 *   6. render_data.php - loads everything the page below needs to render
 * Then views/*.php render the actual HTML/JS, in page order.
 */

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/constants.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/schema.php';
require __DIR__ . '/includes/actions.php';
require __DIR__ . '/includes/render_data.php';
?>
<?php include __DIR__ . '/views/head.php'; ?>
<?php include __DIR__ . '/views/header.php'; ?>
<?php include __DIR__ . '/views/main.php'; ?>
<?php include __DIR__ . '/views/modals.php'; ?>
<?php include __DIR__ . '/views/scripts.php'; ?>
</body>
</html>
