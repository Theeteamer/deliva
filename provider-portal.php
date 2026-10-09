<?php
require_once __DIR__ . "/includes/bootstrap.php";

$error = "";
$accountId = provider_account_id();
if (!$accountId) {
    redirect_to("provider-sign-in.php");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = (string) ($_POST["action"] ?? "");

    if ($action === "logout") {
        unset($_SESSION["provider_account_id"]);
        session_regenerate_id(true);
        redirect_to("index.php");
    } elseif ($action === "save_listing") {
        $accountId = require_provider_account();
        $providerId = filter_input(INPUT_POST, "provider_id", FILTER_VALIDATE_INT) ?: 0;
        $serviceId = filter_input(INPUT_POST, "service_id", FILTER_VALIDATE_INT) ?: 0;
        $name = trim((string) ($_POST["name"] ?? ""));
        $photographyType = trim((string) ($_POST["photography_type"] ?? ""));
        $location = trim((string) ($_POST["location"] ?? ""));
        $address = "";
        $phone = trim((string) ($_POST["phone"] ?? ""));
        $ownerName = trim((string) ($_POST["owner_name"] ?? ""));
        $description = trim((string) ($_POST["description"] ?? ""));
        $openingTime = trim((string) ($_POST["opening_time"] ?? ""));
        $closingTime = trim((string) ($_POST["closing_time"] ?? ""));
        $closedDays = normalize_closed_days($_POST["closed_days"] ?? []);
        $deliveryAvailable = isset($_POST["delivery_available"]) ? 1 : 0;
        try {
            $photos = uploaded_provider_photos($_FILES["photos"] ?? []);
        } catch (RuntimeException $exception) {
            $photos = [];
            $error = $exception->getMessage();
        }

        if ($error === "" && (strlen($name) > 150 || strlen($location) > 150 || strlen($ownerName) > 150)) {
            $error = "Provider name, location, and owner name must be within their stated limits.";
        } elseif ($error === "" && (strlen($address) > 255 || strlen($phone) > 40)) {
            $error = "One or more fields exceed their maximum length.";
        } elseif (
            $error === ""
            && (
                $closedDays === null
                || !valid_time_input($openingTime)
                || !valid_time_input($closingTime)
                || (($openingTime === "") !== ($closingTime === ""))
            )
        ) {
            $error = "Enter valid opening and closing times, or leave both blank, and choose valid closed days.";
        } elseif ($error === "" && $serviceId < 1) {
            $error = "Choose an available service.";
        } elseif ($error === "") {
            $stmt = $conn->prepare(
                "SELECT id, slug, requires_location, requires_business_name, requires_photos,
                        requires_owner_name, requires_owner_phone, supports_delivery, online_only
                 FROM services WHERE id = ? AND active = 1 LIMIT 1"
            );
            $stmt->bind_param("i", $serviceId);
            $stmt->execute();
            $serviceRequirements = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($serviceRequirements) {
                if ($serviceRequirements["slug"] !== "photography") {
                    $photographyType = "";
                }
                if (!$serviceRequirements["requires_business_name"]) {
                    $name = "";
                }
                if (!$serviceRequirements["requires_location"] || $serviceRequirements["online_only"]) {
                    $location = "";
                }
                if (!$serviceRequirements["requires_owner_name"]) {
                    $ownerName = "";
                }
                if (!$serviceRequirements["requires_owner_phone"]) {
                    $phone = "";
                }
                if (!$serviceRequirements["supports_delivery"]) {
                    $deliveryAvailable = 0;
                }
            }

            if (!$serviceRequirements) {
                $error = "Choose an available service.";
            } elseif (
                $serviceRequirements["requires_location"]
                && !$serviceRequirements["online_only"]
                && $location !== ""
                && !in_array($location, KISII_AREAS, true)
            ) {
                $error = "Choose an area in Kisii County.";
            } elseif (
                ($serviceRequirements["requires_business_name"] && $name === "")
                || ($serviceRequirements["requires_location"] && !$serviceRequirements["online_only"] && $location === "")
                || ($serviceRequirements["requires_owner_name"] && $ownerName === "")
                || ($serviceRequirements["requires_owner_phone"] && $phone === "")
                || ($serviceRequirements["slug"] === "photography" && !in_array($photographyType, ["indoor", "outdoor"], true))
            ) {
                $error = "Complete all required fields for the selected service.";
            } elseif ($serviceRequirements["requires_photos"] && !$photos && $providerId === 0) {
                $error = "Upload at least one photo for the selected service.";
            } elseif ($providerId > 0) {
                $stmt = $conn->prepare(
                    "SELECT COUNT(pp.id) AS total
                     FROM providers p
                     LEFT JOIN provider_photos pp ON pp.provider_id = p.id
                     WHERE p.id = ? AND p.owner_account_id = ?
                     GROUP BY p.id"
                );
                $stmt->bind_param("ii", $providerId, $accountId);
                $stmt->execute();
                $photoCount = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$photoCount) {
                    http_response_code(404);
                    exit("Listing not found.");
                }
                $existingPhotoCount = (int) $photoCount["total"];
                if ($existingPhotoCount + count($photos) > 5) {
                    $error = "A provider can have no more than five photos.";
                } elseif ($serviceRequirements["requires_photos"] && !$photos && $existingPhotoCount === 0) {
                    $error = "Upload at least one photo for the selected service.";
                }
                if ($error !== "") {
                    $providerId = 0;
                }
            }

            if ($error === "" && $providerId > 0) {
                $name = $name !== "" ? $name : "Provider";
                $deliveryAvailable = $serviceRequirements["supports_delivery"] ? $deliveryAvailable : 0;
                $providerLocation = !$serviceRequirements["requires_location"] || $serviceRequirements["online_only"] ? "" : $location;
                $closedDaysValue = implode(",", $closedDays);
                $preserveLegacyHours = $openingTime === "" && $closingTime === "" ? 1 : 0;
                $stmt = $conn->prepare(
                    "UPDATE providers
                     SET service_id = ?, owner_name = ?, photography_type = ?, name = ?, location = ?, address = ?, phone = ?,
                         description = ?, opening_hours = CASE WHEN ? = 1 THEN opening_hours ELSE '' END,
                         opening_time = ?, closing_time = ?, closed_days = ?,
                         delivery_available = ?,
                         active = 0, approval_status = 'pending'
                     WHERE id = ? AND owner_account_id = ?"
                );
                $stmt->bind_param(
                    "isssssssisssiii",
                    $serviceId,
                    $ownerName,
                    $photographyType,
                    $name,
                    $providerLocation,
                    $address,
                    $phone,
                    $description,
                    $preserveLegacyHours,
                    $openingTime,
                    $closingTime,
                    $closedDaysValue,
                    $deliveryAvailable,
                    $providerId,
                    $accountId
                );
                $stmt->execute();
                $updated = $stmt->affected_rows;
                $stmt->close();
                if ($updated === 0) {
                    $stmt = $conn->prepare(
                        "SELECT id FROM providers WHERE id = ? AND owner_account_id = ? LIMIT 1"
                    );
                    $stmt->bind_param("ii", $providerId, $accountId);
                    $stmt->execute();
                    $owned = (bool) $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$owned) {
                        http_response_code(404);
                        exit("Listing not found.");
                    }
                }
                store_provider_photos($conn, $providerId, $photos);
                redirect_to("provider-portal.php?saved=1");
            } elseif ($error === "") {
                $name = $name !== "" ? $name : "Provider";
                $deliveryAvailable = $serviceRequirements["supports_delivery"] ? $deliveryAvailable : 0;
                $providerLocation = !$serviceRequirements["requires_location"] || $serviceRequirements["online_only"] ? "" : $location;
                $closedDaysValue = implode(",", $closedDays);
                $stmt = $conn->prepare(
                    "INSERT INTO providers
                     (service_id, owner_account_id, owner_name, photography_type, name, location, address, phone, description,
                      opening_hours, opening_time, closing_time, closed_days, delivery_available, active, approval_status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?, ?, ?, 0, 'pending')"
                );
                $stmt->bind_param(
                    "iissssssssssi",
                    $serviceId,
                    $accountId,
                    $ownerName,
                    $photographyType,
                    $name,
                    $providerLocation,
                    $address,
                    $phone,
                    $description,
                    $openingTime,
                    $closingTime,
                    $closedDaysValue,
                    $deliveryAvailable
                );
                $stmt->execute();
                $newProviderId = $conn->insert_id;
                $stmt->close();
                store_provider_photos($conn, $newProviderId, $photos);
                redirect_to("provider-portal.php?saved=1");
            }
        }
    } elseif ($action === "remove_listing") {
        $accountId = require_provider_account();
        $providerId = filter_input(INPUT_POST, "provider_id", FILTER_VALIDATE_INT) ?: 0;
        $stmt = $conn->prepare(
            "UPDATE providers SET active = 0
             WHERE id = ? AND owner_account_id = ?"
        );
        $stmt->bind_param("ii", $providerId, $accountId);
        $stmt->execute();
        $removed = $stmt->affected_rows;
        $stmt->close();
        if ($removed === 0) {
            http_response_code(404);
            exit("Listing not found.");
        }
        redirect_to("provider-portal.php");
    } else {
        http_response_code(400);
        exit("Invalid form action.");
    }
}

$pageTitle = "Provider account";
require __DIR__ . "/includes/header.php";
?>
<?php if ($error !== ""): ?>
    <p class="form-message form-error" role="alert"><?= h($error) ?></p>
<?php endif; ?>

<?php
    $accountId = require_provider_account();
    $stmt = $conn->prepare("SELECT email FROM provider_accounts WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$account) {
        unset($_SESSION["provider_account_id"]);
        redirect_to("provider-portal.php");
    }

    $stmt = $conn->prepare(
        "SELECT p.id, p.service_id, p.name, p.owner_name, p.photography_type, p.location, p.address, p.phone, p.description,
                p.opening_hours, p.opening_time, p.closing_time, p.closed_days,
                p.delivery_available, p.active, p.approval_status,
                s.name AS service_name, s.slug AS service_slug
         FROM providers p
         INNER JOIN services s ON s.id = p.service_id
         WHERE p.owner_account_id = ?
         ORDER BY p.created_at DESC"
    );
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $services = $conn->query(
        "SELECT id, slug, name, requires_location, requires_business_name, requires_photos,
                requires_owner_name, requires_owner_phone, supports_delivery, online_only
         FROM services WHERE active = 1 ORDER BY sort_order, name"
    )
        ->fetch_all(MYSQLI_ASSOC);
    $editId = filter_input(INPUT_GET, "edit", FILTER_VALIDATE_INT) ?: 0;
    $editListing = null;
    foreach ($listings as $listing) {
        if ((int) $listing["id"] === $editId) {
            $editListing = $listing;
            break;
        }
    }
    $listingFormData = $error !== "" && ($_POST["action"] ?? "") === "save_listing" ? $_POST : [];
    ?>
    <section class="page-heading">
        <h1>provider dashboard</h1>
        <p>Signed in as <span class="account-email"><?= h($account["email"]) ?></span></p>
    </section>
    <form class="inline-form" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="logout">
        <button class="small-button" type="submit">sign out</button>
    </form>

    <?php if (isset($_GET["saved"])): ?>
        <p class="form-message form-success" role="status" data-auto-dismiss>Listing saved and sent for admin approval.</p>
    <?php endif; ?>

    <section class="portal-section provider-listing-section">
        <h2><?= $editListing ? "edit listing" : "add a service listing" ?></h2>
        <?php if (!$services): ?>
            <p>There are no active services available yet.</p>
        <?php else: ?>
            <form class="form-card" method="post" enctype="multipart/form-data" id="provider-listing-form">
                <?= csrf_field() ?>
                <input type="hidden" name="active_tab" value="add-provider">
                <input type="hidden" name="action" value="save_listing">
                <input type="hidden" name="provider_id" value="<?= (int) ($editListing["id"] ?? 0) ?>">
                <label>Service
                    <select name="service_id" id="provider-listing-service" required>
                        <option value="">Choose a service</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= (int) $service["id"] ?>"
                                data-requirements="<?= h(json_encode([
                                    "requires_location" => (bool) $service["requires_location"],
                                    "requires_business_name" => (bool) $service["requires_business_name"],
                                    "requires_photos" => (bool) $service["requires_photos"],
                                    "requires_owner_name" => (bool) $service["requires_owner_name"],
                                    "requires_owner_phone" => (bool) $service["requires_owner_phone"],
                                    "supports_delivery" => (bool) $service["supports_delivery"],
                                    "online_only" => (bool) $service["online_only"],
                                    "photography" => $service["slug"] === "photography",
                                ], JSON_THROW_ON_ERROR)) ?>"
                                <?= $listingFormData
                                    ? ((int) ($listingFormData["service_id"] ?? 0) === (int) $service["id"] ? "selected" : "")
                                    : ($editListing && (int) $editListing["service_id"] === (int) $service["id"] ? "selected" : "") ?>>
                                <?= h(strtolower($service["name"])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label data-provider-field="photography_type">Photography type
                    <select name="photography_type">
                        <option value="">Choose indoor or outdoor</option>
                        <option value="indoor" <?= ($listingFormData["photography_type"] ?? $editListing["photography_type"] ?? "") === "indoor" ? "selected" : "" ?>>indoor</option>
                        <option value="outdoor" <?= ($listingFormData["photography_type"] ?? $editListing["photography_type"] ?? "") === "outdoor" ? "selected" : "" ?>>outdoor</option>
                    </select>
                </label>
                <label data-provider-field="business_name">Provider or business name <input name="name" maxlength="150" value="<?= h((string) ($listingFormData["name"] ?? $editListing["name"] ?? "")) ?>"></label>
                <label data-provider-field="location">Location / area in Kisii County
                    <select name="location">
                        <option value="">Choose an area</option>
                        <?php foreach (KISII_AREAS as $area): ?>
                            <option value="<?= h($area) ?>" <?= ($listingFormData["location"] ?? $editListing["location"] ?? "") === $area ? "selected" : "" ?>><?= h($area) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label data-provider-field="owner_name">Owner name <input name="owner_name" maxlength="150" value="<?= h((string) ($listingFormData["owner_name"] ?? $editListing["owner_name"] ?? "")) ?>"></label>
                <label data-provider-field="owner_phone">Owner phone <input name="phone" maxlength="40" value="<?= h((string) ($listingFormData["phone"] ?? $editListing["phone"] ?? "")) ?>"></label>
                <fieldset class="schedule-fields">
                    <legend>Opening schedule</legend>
                    <div class="schedule-time-fields">
                        <label>Opens at
                            <input type="time" name="opening_time" value="<?= h(substr((string) ($listingFormData["opening_time"] ?? $editListing["opening_time"] ?? ""), 0, 5)) ?>">
                        </label>
                        <label>Closes at
                            <input type="time" name="closing_time" value="<?= h(substr((string) ($listingFormData["closing_time"] ?? $editListing["closing_time"] ?? ""), 0, 5)) ?>">
                        </label>
                    </div>
                    <span class="schedule-days-label">Closed on</span>
                    <div class="schedule-days">
                        <?php
                        $selectedClosedDays = $listingFormData
                            ? (normalize_closed_days($listingFormData["closed_days"] ?? []) ?? [])
                            : array_filter(explode(",", (string) ($editListing["closed_days"] ?? "")));
                        ?>
                        <?php foreach (["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"] as $day): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="closed_days[]" value="<?= h($day) ?>" <?= in_array($day, $selectedClosedDays, true) ? "checked" : "" ?>>
                                <?= h($day) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!$listingFormData && $editListing && $editListing["opening_hours"] !== ""): ?>
                        <span class="schedule-legacy-note">Previously entered hours: <?= h($editListing["opening_hours"]) ?>. Enter a time range above to replace them.</span>
                    <?php endif; ?>
                </fieldset>
                <label>Service details <textarea name="description" rows="4"><?= h((string) ($listingFormData["description"] ?? $editListing["description"] ?? "")) ?></textarea></label>
                <label data-provider-field="photos">Provider photos <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
                    <?php if ($editListing): ?>
                        <?php
                        $stmt = $conn->prepare("SELECT file_path FROM provider_photos WHERE provider_id = ? ORDER BY id");
                        $stmt->bind_param("i", $editListing["id"]);
                        $stmt->execute();
                        $editPhotos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $stmt->close();
                        ?>
                        <?php if ($editPhotos): ?><span><?= count($editPhotos) ?> existing photo(s) attached</span><?php endif; ?>
                    <?php endif; ?>
                </label>
                <label class="checkbox-label" data-provider-field="delivery"><input type="checkbox" name="delivery_available" value="1" <?= !empty($listingFormData["delivery_available"] ?? $editListing["delivery_available"] ?? false) ? "checked" : "" ?>> delivery available</label>
                <div class="provider-listing-actions">
                    <button class="form-submit" type="submit"><?= $editListing ? "save changes for approval" : "submit listing for approval" ?></button>
                    <?php if ($editListing): ?><a class="small-button cancel-link" href="provider-portal.php">cancel edit</a><?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </section>
    <script>
    const providerServiceSelect = document.getElementById("provider-listing-service");
    if (providerServiceSelect) {
        function updateListingRequirements() {
            const option = providerServiceSelect.selectedOptions[0];
            const requirements = option && option.dataset.requirements ? JSON.parse(option.dataset.requirements) : {};
            document.querySelectorAll("#provider-listing-form [data-provider-field]").forEach((field) => {
                const fieldName = field.dataset.providerField;
                const requirementKey = "requires_" + fieldName;
                const visible = fieldName === "photography_type"
                    ? Boolean(requirements.photography)
                    : fieldName === "delivery"
                        ? Boolean(requirements.supports_delivery)
                        : fieldName === "location"
                            ? Boolean(requirements.requires_location) && !requirements.online_only
                            : Boolean(requirements[requirementKey]);
                field.hidden = !visible;
                const input = field.querySelector("input, select");
                if (input) {
                    input.required = visible && fieldName !== "photos" && fieldName !== "delivery";
                    if (!visible) {
                        if (input.type === "checkbox") {
                            input.checked = false;
                        } else if (input.tagName === "SELECT") {
                            input.value = "";
                        } else {
                            input.value = "";
                        }
                    }
                }
            });
        }
        providerServiceSelect.addEventListener("change", updateListingRequirements);
        updateListingRequirements();
    }
    </script>

    <section class="portal-section">
        <h2>your listings</h2>
        <?php if (!$listings): ?>
            <p>You have not submitted any listings yet.</p>
        <?php else: ?>
            <div class="portal-list">
                <?php foreach ($listings as $listing): ?>
                    <article class="portal-listing">
                        <div>
                            <strong><?= h($listing["name"]) ?></strong>
                            <span><?= h(strtolower($listing["service_name"])) ?> · <?= h($listing["location"]) ?></span>
                            <?php if ($listing["opening_time"] && $listing["closing_time"]): ?>
                                <span><?= h(substr($listing["opening_time"], 0, 5) . "–" . substr($listing["closing_time"], 0, 5)) ?><?= $listing["closed_days"] !== "" ? " · closed " . h(str_replace(",", ", ", $listing["closed_days"])) : "" ?></span>
                            <?php elseif ($listing["opening_hours"] !== ""): ?>
                                <span><?= h($listing["opening_hours"]) ?></span>
                            <?php endif; ?>
                            <span class="listing-status status-<?= h($listing["approval_status"]) ?>">
                                <?= h($listing["approval_status"] === "pending" ? "pending approval" : ($listing["active"] ? $listing["approval_status"] : "inactive")) ?>
                            </span>
                        </div>
                        <div class="listing-actions">
                            <a class="small-button" href="provider-portal.php?edit=<?= (int) $listing["id"] ?>">edit</a>
                            <?php if ($listing["active"]): ?>
                                <form method="post" onsubmit="return confirm('Remove this listing from the directory?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove_listing">
                                    <input type="hidden" name="provider_id" value="<?= (int) $listing["id"] ?>">
                                    <button class="small-button" type="submit">remove</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php require __DIR__ . "/includes/footer.php"; ?>
