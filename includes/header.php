<?php
/**
 * Layout header – included at the top of every protected page.
 *
 * Expects (set before including this file):
 *   $pageTitle  string  – displayed in <title> and topbar
 *   $activeNav  string  – key matching nav item (e.g. 'dashboard')
 */
$pageTitle = $pageTitle ?? 'Inventra';
$activeNav = $activeNav ?? '';
$userName  = $_SESSION['user_name']  ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$avatarInitials = strtoupper(substr($userName, 0, 1));

$nav = [
    ['key' => 'dashboard',   'href' => 'dashboard.php',   'icon' => 'layout-dashboard',  'label' => 'Dashboard'],
    ['key' => 'products',    'href' => 'products.php',    'icon' => 'package',            'label' => 'Products'],
    ['section' => 'Operations'],
    ['key' => 'receipts',    'href' => 'receipts.php',    'icon' => 'inbox',              'label' => 'Receipts'],
    ['key' => 'deliveries',  'href' => 'deliveries.php',  'icon' => 'truck',              'label' => 'Deliveries'],
    ['key' => 'transfers',   'href' => 'transfers.php',   'icon' => 'arrow-left-right',   'label' => 'Transfers'],
    ['key' => 'adjustments', 'href' => 'adjustments.php', 'icon' => 'sliders-horizontal', 'label' => 'Adjustments'],
    ['section' => 'Reports'],
    ['key' => 'stock',       'href' => 'stock.php',       'icon' => 'boxes',              'label' => 'Stock'],
    ['key' => 'history',     'href' => 'history.php',     'icon' => 'clock',              'label' => 'Move History'],
    ['section' => 'Settings'],
    ['key' => 'warehouses',      'href' => 'warehouses.php',      'icon' => 'warehouse',          'label' => 'Warehouses'],
    ['key' => 'categories',      'href' => 'categories.php',      'icon' => 'tag',                'label' => 'Categories'],
    ['key' => 'suppliers',       'href' => 'suppliers.php',       'icon' => 'truck',              'label' => 'Suppliers'],
    ['key' => 'customers',       'href' => 'customers.php',       'icon' => 'users',              'label' => 'Customers'],
    ['key' => 'stock_settings',  'href' => 'stock_settings.php',  'icon' => 'settings-2',         'label' => 'Stock Settings'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> – Inventra</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-layout">

<!-- ── Sidebar ─────────────────────────────────── -->
<aside class="sidebar" id="sidebar">

  <a href="dashboard.php" class="sidebar-logo">
    <div class="sidebar-logo-icon" style="background:none;padding:0;">
      <img src="assets/images/logo.png" alt="Inventra logo" style="width:38px;height:38px;border-radius:var(--r-lg);object-fit:cover;">
    </div>
    <div>
      <div class="sidebar-logo-name">Inventra</div>
      <div class="sidebar-logo-sub">StockWare</div>
    </div>
  </a>

  <nav class="sidebar-nav">
    <?php foreach ($nav as $item): ?>
      <?php if (isset($item['section'])): ?>
        <div class="sidebar-section-label"><?= e($item['section']) ?></div>
      <?php else: ?>
        <a href="<?= e($item['href']) ?>"
           class="sidebar-item<?= $activeNav === $item['key'] ? ' active' : '' ?>">
          <i data-lucide="<?= e($item['icon']) ?>"></i>
          <?= e($item['label']) ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <a href="profile.php" class="sidebar-item<?= $activeNav === 'profile' ? ' active' : '' ?>">
      <i data-lucide="user-circle"></i> Profile
    </a>
    <a href="logout.php" class="sidebar-item" onclick="return confirm('Sign out?')">
      <i data-lucide="log-out"></i> Sign Out
    </a>
  </div>

</aside><!-- /sidebar -->

<!-- ── Main wrapper ─────────────────────────────── -->
<div class="main-wrap">

  <!-- Topbar -->
  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title"><?= e($pageTitle) ?></span>
    </div>
    <div class="topbar-right">
      <a href="profile.php" class="topbar-user">
        <div class="user-avatar"><?= $avatarInitials ?></div>
        <span class="user-name"><?= e($userName) ?></span>
      </a>
    </div>
  </header>

  <!-- Page content -->
  <main class="main-content">
