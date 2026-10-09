<?php
$siteBase = str_contains((string) ($_SERVER["SCRIPT_NAME"] ?? ""), "/admin/") ? "../" : "";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7f3">
    <title><?= h($pageTitle) ?> · Deliva</title>
    <link rel="stylesheet" href="<?= h($siteBase) ?>css/styles.css?v=<?= (int) filemtime(__DIR__ . "/../css/styles.css") ?>">
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="<?= h($siteBase) ?>index.php" aria-label="Deliva home">
            <?php if (is_file(__DIR__ . "/../img/logo.png")): ?>
                <img class="brand-logo" src="<?= h($siteBase) ?>img/logo.png" alt="Deliva">
            <?php endif; ?>
        </a>
        <span class="header-note">Services around you</span>
    </div>
</header>
<main class="container">
