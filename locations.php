<?php
require_once __DIR__ . "/includes/bootstrap.php";

$service = find_service($conn, (string) ($_GET["service"] ?? ""));
if (!$service) {
    http_response_code(404);
    $pageTitle = "Service not found";
    require __DIR__ . "/includes/header.php";
    ?>
    <section class="empty-state">
        <h1>Service not found</h1>
        <p>Choose one of the services currently available.</p>
        <a class="button" href="index.php">services</a>
    </section>
    <?php
    require __DIR__ . "/includes/footer.php";
    exit;
}

$stmt = $conn->prepare(
    "SELECT DISTINCT location
     FROM providers
     WHERE service_id = ? AND active = 1 AND approval_status = 'approved' AND location <> ''
     ORDER BY location"
);
$stmt->bind_param("i", $service["id"]);
$stmt->execute();
$locations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = "Choose your area";
require __DIR__ . "/includes/header.php";
?>
<nav class="breadcrumbs" aria-label="Progress">
    <a href="index.php">Services</a><span> / </span><strong><?= h($service["name"]) ?></strong><span> / Area</span>
</nav>
<section class="page-heading location-page-heading">
    <p class="eyebrow"><?= h($service["name"]) ?></p>
    <p>find available <?= h(strtolower($service["name"])) ?> providers.</p>
</section>

<?php if ($locations): ?>
    <div class="choice-list" aria-label="Available areas">
        <?php foreach ($locations as $row): ?>
            <?php $providersUrl = "providers.php?service=" . rawurlencode($service["slug"]) . "&location=" . rawurlencode($row["location"]); ?>
            <div class="location-entry">
                <a class="choice-card" href="<?= h($providersUrl) ?>">
                    <?= h($row["location"]) ?>
                </a>
                <a class="details-button location-details-button" href="<?= h($providersUrl) ?>">details</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <section class="empty-state">
        <h2>No areas listed yet</h2>
        <p>There are no <?= h(strtolower($service["name"])) ?> providers listed yet. Please check back soon.</p>
        <a class="button button-secondary" href="index.php">service</a>
    </section>
<?php endif; ?>
<?php require __DIR__ . "/includes/footer.php"; ?>
