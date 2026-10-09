<?php
require_once __DIR__ . "/includes/bootstrap.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$provider = null;
if ($id && $id > 0) {
    $stmt = $conn->prepare(
        "SELECT p.id, p.name, p.owner_name, p.location, p.phone, p.description, p.photography_type, p.is_demo,
                p.opening_hours, p.opening_time, p.closing_time, p.closed_days,
                p.delivery_available, s.supports_delivery, s.name AS service_name,
                s.slug AS service_slug
         FROM providers p
         INNER JOIN services s ON s.id = p.service_id
         WHERE p.id = ? AND p.active = 1 AND p.approval_status = 'approved' AND s.active = 1
         LIMIT 1"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $provider = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$provider) {
    http_response_code(404);
    $pageTitle = "Provider not found";
    require __DIR__ . "/includes/header.php";
    ?>
    <section class="empty-state">
        <h1>Provider not found</h1>
        <p>This listing may have been removed or is no longer available.</p>
        <a class="button" href="index.php">Browse services</a>
    </section>
    <?php
    require __DIR__ . "/includes/footer.php";
    exit;
}

$displayName = trim($provider["name"]);
if ($displayName === "" || strcasecmp($displayName, "Provider") === 0) {
    $displayName = trim($provider["owner_name"]) !== "" ? $provider["owner_name"] : $provider["name"];
}
$pageTitle = $displayName;
$phoneNumber = preg_replace('/[^0-9+]/', '', $provider["phone"]);
$whatsAppNumber = preg_replace('/\D/', '', $provider["phone"]);
if (str_starts_with($whatsAppNumber, "0")) {
    $whatsAppNumber = "254" . substr($whatsAppNumber, 1);
}
$stmt = $conn->prepare("SELECT file_path FROM provider_photos WHERE provider_id = ? ORDER BY id");
$stmt->bind_param("i", $id);
$stmt->execute();
$providerPhotos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
require __DIR__ . "/includes/header.php";
?>
<nav class="breadcrumbs" aria-label="Progress">
    <a href="index.php">Services</a><span> / </span>
    <a href="locations.php?service=<?= rawurlencode($provider["service_slug"]) ?>"><?= h($provider["service_name"]) ?></a><span> / </span>
    <a href="providers.php?service=<?= rawurlencode($provider["service_slug"]) ?>&amp;location=<?= rawurlencode($provider["location"] ?: "online") ?>"><?= h($provider["location"] ?: "online") ?></a><span> / Details</span>
</nav>
<article class="detail-card">
<div class="detail-tabs" role="tablist" aria-label="Provider information">
    <button class="detail-tab" id="service-tab" type="button" role="tab" aria-selected="true" aria-controls="service-panel" tabindex="0">
        <?= h(strtolower($provider["service_name"])) ?>
    </button>
    <button class="detail-tab" id="about-tab" type="button" role="tab" aria-selected="false" aria-controls="about-panel" tabindex="-1">
        about
    </button>
</div>

<section class="detail-panel" id="service-panel" role="tabpanel" aria-labelledby="service-tab">
    <table class="provider-detail-table">
        <tbody>
            <tr class="provider-active-row">
                <th scope="row">
                    <span class="provider-check" aria-hidden="true">✓</span>
                    <?= h($displayName) ?>
                </th>
                <?php if (($provider["opening_time"] && $provider["closing_time"]) || $provider["opening_hours"]): ?>
                    <td class="provider-hours-cell"><span class="hours-badge"><?= $provider["opening_time"] && $provider["closing_time"]
                        ? h(substr($provider["opening_time"], 0, 5) . "–" . substr($provider["closing_time"], 0, 5))
                        : h($provider["opening_hours"]) ?></span></td>
                <?php endif; ?>
            </tr>
            <tr>
                <th scope="row">area</th>
                <td><?= h($provider["location"] ?: "online") ?></td>
            </tr>
            <?php if ($provider["closed_days"] !== ""): ?>
                <tr><th scope="row">closed</th><td><?= h(str_replace(",", ", ", $provider["closed_days"])) ?></td></tr>
            <?php endif; ?>
            <?php if ($provider["photography_type"] !== ""): ?>
                <tr><th scope="row">photography</th><td><?= h($provider["photography_type"]) ?></td></tr>
            <?php endif; ?>
            <?php if ($provider["phone"]): ?>
                <tr>
                    <th scope="row">phone</th>
                    <td class="provider-contact-cell">
                        <?php if ($provider["is_demo"]): ?>
                            <span><?= h($provider["phone"]) ?></span>
                        <?php else: ?>
                            <a href="tel:<?= h($phoneNumber) ?>"><?= h($provider["phone"]) ?></a>
                            <span class="provider-contact-actions">
                                <a class="contact-icon-link" href="tel:<?= h($phoneNumber) ?>" aria-label="Call <?= h($provider["name"]) ?>" title="Call">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.32.57 3.58.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.6 21 3 13.4 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.26.2 2.46.57 3.58a1 1 0 0 1-.24 1.02l-2.23 2.2Z"/></svg>
                                </a>
                                <a class="contact-icon-link whatsapp-link" href="https://wa.me/<?= h($whatsAppNumber) ?>" aria-label="WhatsApp <?= h($provider["name"]) ?>" title="WhatsApp" target="_blank" rel="noopener noreferrer">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.5 3.5A11.9 11.9 0 0 0 12.04 0C5.46 0 .1 5.36.1 11.94c0 2.1.55 4.15 1.6 5.96L0 24l6.25-1.64a11.9 11.9 0 0 0 5.79 1.47h.01C18.63 23.83 24 18.47 24 11.9c0-3.18-1.24-6.17-3.5-8.4ZM12.05 21.8a9.9 9.9 0 0 1-5.04-1.38l-.36-.21-3.71.97.99-3.62-.24-.37a9.9 9.9 0 1 1 8.36 4.61Zm5.43-7.42c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.39-1.48-.88-.78-1.48-1.75-1.65-2.05-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.38-.03-.53-.08-.15-.67-1.61-.92-2.2-.24-.57-.49-.49-.67-.5h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.87 1.22 3.07c.15.2 2.1 3.2 5.1 4.49.71.3 1.27.49 1.7.63.72.23 1.37.2 1.89.12.58-.09 1.75-.72 2-1.42.25-.7.25-1.3.18-1.42-.07-.13-.27-.2-.57-.35Z"/></svg>
                                </a>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php if ($provider["supports_delivery"] && $provider["delivery_available"]): ?>
                <tr><th scope="row">delivery</th><td>available</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if ($providerPhotos): ?>
        <div class="provider-public-gallery" aria-label="Provider photos">
            <?php foreach ($providerPhotos as $photo): ?>
                <div class="provider-photo-frame">
                    <img src="<?= h($photo["file_path"]) ?>" alt="<?= h($provider["name"]) ?> photo">
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="detail-panel" id="about-panel" role="tabpanel" aria-labelledby="about-tab" hidden>
    <?php if ($provider["description"]): ?>
        <div class="description">
            <h2>about this provider</h2>
            <p><?= nl2br(h($provider["description"])) ?></p>
        </div>
    <?php endif; ?>
</section>
</article>

<script>
const detailTabs = document.querySelectorAll(".detail-tab");
const detailTabList = document.querySelector(".detail-tabs");

function updateTabIndicator(activeTab) {
const tabListBounds = detailTabList.getBoundingClientRect();
const activeTabBounds = activeTab.getBoundingClientRect();
detailTabList.style.setProperty("--tab-indicator-x", `${activeTabBounds.left - tabListBounds.left}px`);
detailTabList.style.setProperty("--tab-indicator-width", `${activeTabBounds.width}px`);
}

detailTabs.forEach((tab, index) => {
tab.addEventListener("click", () => activateDetailTab(tab));
tab.addEventListener("keydown", (event) => {
    if (event.key === "ArrowLeft" || event.key === "ArrowRight") {
        event.preventDefault();
        const nextTab = detailTabs[(index + (event.key === "ArrowRight" ? 1 : detailTabs.length - 1)) % detailTabs.length];
        activateDetailTab(nextTab);
        nextTab.focus();
    }
});
});

function activateDetailTab(activeTab) {
detailTabs.forEach((tab) => {
    const selected = tab === activeTab;
    tab.setAttribute("aria-selected", String(selected));
    tab.tabIndex = selected ? 0 : -1;
    document.getElementById(tab.getAttribute("aria-controls")).hidden = !selected;
});
updateTabIndicator(activeTab);
}

updateTabIndicator(document.querySelector('.detail-tab[aria-selected="true"]'));
window.addEventListener("resize", () => {
    updateTabIndicator(document.querySelector('.detail-tab[aria-selected="true"]'));
});
</script>
<?php require __DIR__ . "/includes/footer.php"; ?>
