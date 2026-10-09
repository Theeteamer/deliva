<?php
require_once __DIR__ . "/includes/bootstrap.php";

$service = find_service($conn, (string) ($_GET["service"] ?? ""));
$location = trim((string) ($_GET["location"] ?? ""));
if (!$service || $location === "" || strlen($location) > 150) {
    http_response_code(404);
    $pageTitle = "Listings not found";
    require __DIR__ . "/includes/header.php";
    ?>
    <section class="empty-state">
        <h1>not found</h1>
        <a class="button" href="index.php">services</a>
    </section>
    <?php
    require __DIR__ . "/includes/footer.php";
    exit;
}

$stmt = $conn->prepare(
    "SELECT id, name, location, address, description, delivery_available
     FROM providers
     WHERE service_id = ? AND location = ? AND active = 1 AND approval_status = 'approved'
     ORDER BY name"
);
$stmt->bind_param("is", $service["id"], $location);
$stmt->execute();
$providers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = "Providers in " . $location;
require __DIR__ . "/includes/header.php";
?>
<nav class="breadcrumbs" aria-label="Progress">
    <a href="index.php">Services</a><span> / </span>
    <a href="locations.php?service=<?= rawurlencode($service["slug"]) ?>"><?= h($service["name"]) ?></a><span> / </span>
    <strong><?= h($location) ?></strong>
</nav>
<section class="page-heading">
    <p class="eyebrow"><?= h($service["name"]) ?> · <?= h($location) ?></p>
    <p><?= count($providers) ?> <?= count($providers) === 1 ? "provider" : "providers" ?> listed</p>
</section>

<?php if ($providers): ?>
    <div class="choice-list" aria-label="Service providers">
        <?php foreach ($providers as $provider): ?>
            <div class="provider-entry">
                <a class="provider-card" href="provider.php?id=<?= (int) $provider["id"] ?>">
                    <span class="provider-copy">
                        <strong><?= h($provider["name"]) ?></strong>
                        <span><?= h($provider["address"] ?: $provider["location"]) ?></span>
                        <?php if ($service["supports_delivery"] && $provider["delivery_available"]): ?>
                            <span class="tag">Delivery available</span>
                        <?php endif; ?>
                    </span>
                </a>
                <a class="details-button" href="provider.php?id=<?= (int) $provider["id"] ?>">details</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <section class="empty-state">
        <h2>no providers</h2>
        <a class="button button-secondary" href="locations.php?service=<?= rawurlencode($service["slug"]) ?>">area</a>
    </section>
<?php endif; ?>
<?php require __DIR__ . "/includes/footer.php"; ?>
