<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= ae($pageTitle ?? 'Admin') ?> — Konektor</title>
<link rel="stylesheet" href="<?= adminPath('inc/shadcn.css') ?>">
<!-- Alpine.js CDN — no npm needed -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<style>
  [x-cloak] { display: none !important; }
</style>
</head>
<body>
<?php include __DIR__ . '/icons.php'; ?>
