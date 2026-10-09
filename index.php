<?php
require_once __DIR__ . "/includes/bootstrap.php";

$services = $conn->query(
    "SELECT id, slug, name FROM services WHERE active = 1 ORDER BY sort_order, name"
)->fetch_all(MYSQLI_ASSOC);
$selectedServiceSlug = trim((string) ($_GET["service"] ?? ""));
$selectedService = null;
foreach ($services as $service) {
    if ($service["slug"] === $selectedServiceSlug) {
        $selectedService = $service;
        break;
    }
}

$locationsByService = [];
$locationRows = $conn->query(
    "SELECT DISTINCT service_id, location
     FROM providers
     WHERE active = 1 AND approval_status = 'approved' AND location <> ''
     ORDER BY location"
)->fetch_all(MYSQLI_ASSOC);
foreach ($locationRows as $row) {
    $locationsByService[(int) $row["service_id"]][] = $row["location"];
}
$onlineRows = $conn->query(
    "SELECT DISTINCT p.service_id
     FROM providers p
     INNER JOIN services s ON s.id = p.service_id
     WHERE p.active = 1 AND p.approval_status = 'approved'
       AND s.active = 1 AND p.location = ''"
)->fetch_all(MYSQLI_ASSOC);
foreach ($onlineRows as $row) {
    $serviceId = (int) $row["service_id"];
    if (!in_array("online", $locationsByService[$serviceId] ?? [], true)) {
        $locationsByService[$serviceId][] = "online";
    }
}

$pageTitle = "Find a local service";
require __DIR__ . "/includes/header.php";
?>
<section class="hero">
    <h1>Request a service</h1>
</section>

<section aria-label="Browse providers">
    <form class="service-search-form" method="get" action="index.php" id="service-search-form">
        <label for="service-search">Service</label>
        <div class="service-search-controls">
            <select id="service-search" name="service" required aria-describedby="service-select-hint">
                <option value="">Choose a service</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?= h($service["slug"]) ?>" <?= $selectedServiceSlug === $service["slug"] ? "selected" : "" ?>>
                        <?= h(strtolower($service["name"])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <span id="service-select-hint" class="sr-only">Available areas appear when you choose a service.</span>
    </form>

    <?php if ($selectedService): ?>
        <?php $serviceLocations = $locationsByService[(int) $selectedService["id"]] ?? []; ?>
        <section class="service-search-results" aria-live="polite">
            <h2>Available areas for <?= h(strtolower($selectedService["name"])) ?></h2>
            <?php if ($serviceLocations): ?>
                <div class="service-directory-locations">
                    <?php foreach ($serviceLocations as $location): ?>
                        <a href="providers.php?service=<?= rawurlencode($selectedService["slug"]) ?>&amp;location=<?= rawurlencode($location) ?>">
                            <?= h($location) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="service-directory-empty">No areas listed for this service yet.</p>
            <?php endif; ?>
        </section>
    <?php elseif ($selectedServiceSlug !== ""): ?>
        <p class="form-message form-error" role="alert">Choose an available service.</p>
    <?php endif; ?>
</section>
<script>
document.getElementById("service-search")?.addEventListener("change", (event) => {
    event.currentTarget.form.requestSubmit();
});
</script>
<?php require __DIR__ . "/includes/footer.php"; ?>
