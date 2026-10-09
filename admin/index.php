<?php
require_once __DIR__ . "/../includes/bootstrap.php";

$error = "";
$adminId = isset($_SESSION["admin_account_id"]) ? (int) $_SESSION["admin_account_id"] : null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = (string) ($_POST["action"] ?? "");

    if ($action === "login") {
        $email = strtolower(trim((string) ($_POST["email"] ?? "")));
        $password = (string) ($_POST["password"] ?? "");
        $stmt = $conn->prepare("SELECT id, password_hash FROM admin_accounts WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$account || !password_verify($password, $account["password_hash"])) {
            $error = "Email or password is incorrect.";
        } else {
            session_regenerate_id(true);
            unset($_SESSION["provider_account_id"]);
            $_SESSION["admin_account_id"] = (int) $account["id"];
            redirect_to("index.php");
        }
    } elseif ($action === "logout") {
        unset($_SESSION["admin_account_id"]);
        session_regenerate_id(true);
        redirect_to("../index.php");
    } else {
        $adminId = require_admin_account();

        if ($action === "add_service") {
            $name = strtolower(trim((string) ($_POST["name"] ?? "")));
            $description = trim((string) ($_POST["description"] ?? ""));
            $sortOrder = filter_input(INPUT_POST, "sort_order", FILTER_VALIDATE_INT);
            $requirements = [
                !empty($_POST["requires_location"]) ? 1 : 0,
                !empty($_POST["requires_business_name"]) ? 1 : 0,
                !empty($_POST["requires_photos"]) ? 1 : 0,
                !empty($_POST["requires_owner_name"]) ? 1 : 0,
                !empty($_POST["requires_owner_phone"]) ? 1 : 0,
                !empty($_POST["supports_delivery"]) ? 1 : 0,
                !empty($_POST["online_only"]) ? 1 : 0,
            ];
            if ($requirements[6]) {
                $requirements[0] = 0;
            }
            $normalized = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $name);
            $slug = strtolower(trim((string) preg_replace("/[^a-z0-9]+/", "-", $normalized), "-"));

            if ($name === "" || strlen($name) > 100 || $slug === "" || strlen($description) > 255) {
                $error = "Enter a valid service name and optional description.";
            } else {
                $stmt = $conn->prepare(
                    "SELECT id FROM services WHERE slug = ? OR name = ? LIMIT 1"
                );
                $stmt->bind_param("ss", $slug, $name);
                $stmt->execute();
                $exists = (bool) $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($exists) {
                    $error = "A service with that name already exists.";
                } else {
                    $order = $sortOrder === false || $sortOrder === null ? 0 : max(0, $sortOrder);
                    $stmt = $conn->prepare(
                        "INSERT INTO services
                         (slug, name, description, sort_order, requires_location, requires_business_name,
                          requires_photos, requires_owner_name, requires_owner_phone, supports_delivery, online_only)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param(
                        "sssiiiiiiii",
                        $slug,
                        $name,
                        $description,
                        $order,
                        $requirements[0],
                        $requirements[1],
                        $requirements[2],
                        $requirements[3],
                        $requirements[4],
                        $requirements[5],
                        $requirements[6]
                    );
                    $stmt->execute();
                    $stmt->close();
                    redirect_to("index.php?saved=service");
                }
            }
        } elseif ($action === "update_service_requirements") {
            $serviceId = filter_input(INPUT_POST, "service_id", FILTER_VALIDATE_INT) ?: 0;
            $requirements = [
                !empty($_POST["requires_location"]) ? 1 : 0,
                !empty($_POST["requires_business_name"]) ? 1 : 0,
                !empty($_POST["requires_photos"]) ? 1 : 0,
                !empty($_POST["requires_owner_name"]) ? 1 : 0,
                !empty($_POST["requires_owner_phone"]) ? 1 : 0,
                !empty($_POST["supports_delivery"]) ? 1 : 0,
                !empty($_POST["online_only"]) ? 1 : 0,
            ];
            if ($requirements[6]) {
                $requirements[0] = 0;
            }
            $stmt = $conn->prepare(
                "UPDATE services SET requires_location = ?, requires_business_name = ?,
                 requires_photos = ?, requires_owner_name = ?, requires_owner_phone = ?,
                 supports_delivery = ?, online_only = ? WHERE id = ?"
            );
            $stmt->bind_param(
                "iiiiiiii",
                $requirements[0],
                $requirements[1],
                $requirements[2],
                $requirements[3],
                $requirements[4],
                $requirements[5],
                $requirements[6],
                $serviceId
            );
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();
            if ($changed === 0) {
                $stmt = $conn->prepare("SELECT id FROM services WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $serviceId);
                $stmt->execute();
                $exists = (bool) $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$exists) {
                    http_response_code(404);
                    exit("Service not found.");
                }
            }
            if (!$requirements[5]) {
                $stmt = $conn->prepare("UPDATE providers SET delivery_available = 0 WHERE service_id = ?");
                $stmt->bind_param("i", $serviceId);
                $stmt->execute();
                $stmt->close();
            }
            redirect_to("index.php?saved=service&tab=edit-services&open_service=" . $serviceId);
        } elseif ($action === "toggle_service") {
            $serviceId = filter_input(INPUT_POST, "service_id", FILTER_VALIDATE_INT) ?: 0;
            $returnTab = (string) ($_POST["active_tab"] ?? "edit-services");
            if (!in_array($returnTab, ["edit-services", "providers"], true)) {
                $returnTab = "edit-services";
            }
            $stmt = $conn->prepare("UPDATE services SET active = 1 - active WHERE id = ?");
            $stmt->bind_param("i", $serviceId);
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();
            if ($changed !== 1) {
                http_response_code(404);
                exit("Service not found.");
            }
            redirect_to("index.php?saved=service&tab=" . $returnTab . "&open_service=" . $serviceId);
        } elseif ($action === "add_provider") {
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
                $error = "Enter a valid provider name, location, and owner name.";
            } elseif ($error === "" && (strlen($address) > 255 || strlen($phone) > 40)) {
                $error = "One or more provider fields exceed their maximum length.";
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

                if (!$serviceRequirements) {
                    $error = "Choose an active service.";
                } elseif (
                    $serviceRequirements["requires_location"]
                    && !$serviceRequirements["online_only"]
                    && $location !== ""
                    && !in_array($location, KISII_AREAS, true)
                ) {
                    $error = "Choose an area in Kisii County.";
                } else {
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
                    if (
                        ($serviceRequirements["requires_business_name"] && $name === "")
                        || ($serviceRequirements["requires_location"] && !$serviceRequirements["online_only"] && $location === "")
                        || ($serviceRequirements["requires_owner_name"] && $ownerName === "")
                        || ($serviceRequirements["requires_owner_phone"] && $phone === "")
                        || ($serviceRequirements["slug"] === "photography" && !in_array($photographyType, ["indoor", "outdoor"], true))
                        || ($serviceRequirements["requires_photos"] && !$photos)
                    ) {
                        $error = "Complete all required fields and upload at least one photo for this service.";
                    }
                }

                if ($error === "") {
                    $name = $name !== "" ? $name : "Provider";
                    $deliveryAvailable = $serviceRequirements["supports_delivery"] ? $deliveryAvailable : 0;
                    $providerLocation = $serviceRequirements["online_only"] ? "" : $location;
                    $closedDaysValue = implode(",", $closedDays);
                    $stmt = $conn->prepare(
                        "INSERT INTO providers
                         (service_id, owner_name, photography_type, name, location, address, phone, description, opening_hours,
                          opening_time, closing_time, closed_days, delivery_available, active, approval_status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?, ?, ?, 1, 'approved')"
                    );
                    $stmt->bind_param(
                        "issssssssssi",
                        $serviceId,
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
                    $providerId = $conn->insert_id;
                    $stmt->close();
                    store_provider_photos($conn, $providerId, $photos);
                    redirect_to("index.php?saved=provider&tab=add-provider");
                }
            }
        } elseif ($action === "review_provider") {
            $providerId = filter_input(INPUT_POST, "provider_id", FILTER_VALIDATE_INT) ?: 0;
            $decision = (string) ($_POST["decision"] ?? "");
            if (!in_array($decision, ["approved", "rejected"], true)) {
                http_response_code(400);
                exit("Invalid approval decision.");
            }
            $active = $decision === "approved" ? 1 : 0;
            $stmt = $conn->prepare(
                "UPDATE providers SET approval_status = ?, active = ?
                 WHERE id = ? AND owner_account_id IS NOT NULL"
            );
            $stmt->bind_param("sii", $decision, $active, $providerId);
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();
            if ($changed === 0) {
                $stmt = $conn->prepare(
                    "SELECT id FROM providers WHERE id = ? AND owner_account_id IS NOT NULL LIMIT 1"
                );
                $stmt->bind_param("i", $providerId);
                $stmt->execute();
                $exists = (bool) $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$exists) {
                    http_response_code(404);
                    exit("Provider listing not found.");
                }
            }
            redirect_to("index.php?saved=review&tab=pending");
        } elseif ($action === "restore_provider") {
            $providerId = filter_input(INPUT_POST, "provider_id", FILTER_VALIDATE_INT) ?: 0;
            $stmt = $conn->prepare(
                "UPDATE providers SET active = 1
                 WHERE id = ? AND owner_account_id IS NOT NULL
                   AND approval_status = 'approved' AND active = 0"
            );
            $stmt->bind_param("i", $providerId);
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();
            if ($changed !== 1) {
                http_response_code(409);
                exit("This listing cannot be restored.");
            }
            redirect_to("index.php?saved=review&tab=providers");
        } else {
            http_response_code(400);
            exit("Invalid form action.");
        }
    }
}

$pageTitle = "Admin";
require __DIR__ . "/../includes/header.php";
?>
<?php if ($error !== ""): ?>
    <p class="form-message form-error" role="alert"><?= h($error) ?></p>
<?php endif; ?>

<?php if (!$adminId): ?>
    <section class="page-heading"><h1>admin sign in</h1></section>
    <form class="form-card form-narrow" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">
        <label>Email <input type="email" name="email" maxlength="254" autocomplete="username" required></label>
        <label>Password <input type="password" name="password" autocomplete="current-password" required></label>
        <button class="form-submit" type="submit">sign in</button>
        <a class="auth-link" href="password-reset.php">forgot password?</a>
    </form>
<?php else: ?>
    <?php
    $adminId = require_admin_account();
    $services = $conn->query(
        "SELECT id, slug, name, active, sort_order, requires_location, requires_business_name,
                requires_photos, requires_owner_name, requires_owner_phone, supports_delivery, online_only
         FROM services ORDER BY sort_order, name"
    )->fetch_all(MYSQLI_ASSOC);
    $providers = $conn->query(
        "SELECT p.id, p.name, p.owner_name, p.photography_type, p.location, p.address, p.phone, p.description,
                p.opening_hours, p.opening_time, p.closing_time, p.closed_days,
                p.delivery_available, p.active, p.approval_status,
                s.name AS service_name, s.supports_delivery AS service_supports_delivery,
                a.email AS owner_email
         FROM providers p
         INNER JOIN services s ON s.id = p.service_id
         LEFT JOIN provider_accounts a ON a.id = p.owner_account_id
         ORDER BY (p.approval_status = 'pending') DESC, p.created_at DESC"
    )->fetch_all(MYSQLI_ASSOC);
    $providerPhotos = [];
    $photoRows = $conn->query(
        "SELECT pp.provider_id, pp.file_path FROM provider_photos pp
         INNER JOIN providers p ON p.id = pp.provider_id
         ORDER BY pp.id"
    )->fetch_all(MYSQLI_ASSOC);
    foreach ($photoRows as $photoRow) {
        $providerPhotos[(int) $photoRow["provider_id"]][] = $photoRow["file_path"];
    }
    $providersByService = [];
    foreach ($providers as $provider) {
        if ($provider["approval_status"] !== "pending") {
            $providersByService[$provider["service_name"]][] = $provider;
        }
    }
    $pendingProviders = array_values(array_filter(
        $providers,
        static fn(array $provider): bool => $provider["approval_status"] === "pending"
    ));
    $activeTab = (string) ($_POST["active_tab"] ?? $_GET["tab"] ?? "add-service");
    $validTabs = ["add-service", "edit-services", "add-provider", "providers", "pending"];
    if (!in_array($activeTab, $validTabs, true)) {
        $activeTab = "add-service";
    }
    $providerFormData = $error !== "" && ($_POST["action"] ?? "") === "add_provider" ? $_POST : [];
    ?>
    <section class="page-heading">
        <h1>admin dashboard</h1>
        <p>Manage services, provider listings, and approval requests.</p>
    </section>
    <form class="inline-form" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="logout">
        <button class="small-button" type="submit">sign out</button>
    </form>

    <?php if (isset($_GET["saved"])): ?>
        <p class="form-message form-success" role="status" data-auto-dismiss>Changes saved.</p>
    <?php endif; ?>

    <div class="admin-tabs" role="tablist" aria-label="Admin sections">
        <?php
        $tabLabels = [
            "add-service" => "add service",
            "edit-services" => "edit services",
            "add-provider" => "add provider",
            "providers" => "list of providers",
            "pending" => "pending approvals",
        ];
        foreach ($tabLabels as $tabId => $tabLabel):
        ?>
            <button class="admin-tab" id="tab-<?= h($tabId) ?>" type="button" role="tab"
                    aria-selected="<?= $activeTab === $tabId ? "true" : "false" ?>"
                    aria-controls="panel-<?= h($tabId) ?>" tabindex="<?= $activeTab === $tabId ? "0" : "-1" ?>">
                <?= h($tabLabel) ?><?= $tabId === "pending" ? " (" . count($pendingProviders) . ")" : "" ?>
            </button>
        <?php endforeach; ?>
    </div>

    <section class="admin-tab-panel" id="panel-add-service" role="tabpanel" aria-labelledby="tab-add-service" <?= $activeTab !== "add-service" ? "hidden" : "" ?>>
        <h2>add service</h2>
        <form class="form-card" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="active_tab" value="add-service">
            <input type="hidden" name="action" value="add_service">
            <label>Service name <input name="name" maxlength="100" required></label>
            <label>Description <input name="description" maxlength="255"></label>
            <label>Display order <input type="number" name="sort_order" min="0" value="0"></label>
            <fieldset class="requirement-options">
                <legend>Provider listing requirements</legend>
                <label class="checkbox-label"><input type="checkbox" name="requires_location" value="1" checked> require location</label>
                <label class="checkbox-label"><input type="checkbox" name="requires_business_name" value="1" checked> require business name</label>
                <label class="checkbox-label"><input type="checkbox" name="requires_photos" value="1"> require provider photos</label>
                <label class="checkbox-label"><input type="checkbox" name="requires_owner_name" value="1"> require owner name</label>
                <label class="checkbox-label"><input type="checkbox" name="requires_owner_phone" value="1"> require owner phone</label>
                <label class="checkbox-label"><input type="checkbox" name="supports_delivery" value="1" checked> service offers delivery</label>
                <label class="checkbox-label"><input type="checkbox" name="online_only" value="1"> service is purely online</label>
            </fieldset>
            <button class="form-submit" type="submit">add service</button>
        </form>
    </section>

    <section class="admin-tab-panel" id="panel-edit-services" role="tabpanel" aria-labelledby="tab-edit-services" <?= $activeTab !== "edit-services" ? "hidden" : "" ?>>
        <h2>edit services</h2>
        <?php if (!$services): ?><p>No services have been added.</p><?php endif; ?>
        <div class="portal-list">
            <?php foreach ($services as $service): ?>
                <details class="service-editor" <?= (int) ($_GET["open_service"] ?? 0) === (int) $service["id"] ? "open" : "" ?>>
                    <summary class="service-editor-heading">
                        <strong><?= h(strtolower($service["name"])) ?></strong>
                    </summary>
                    <form class="form-card" method="post" id="service-requirements-<?= (int) $service["id"] ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="active_tab" value="edit-services">
                        <input type="hidden" name="action" value="update_service_requirements">
                        <input type="hidden" name="service_id" value="<?= (int) $service["id"] ?>">
                        <input type="hidden" name="open_service_id" value="<?= (int) $service["id"] ?>">
                        <p class="service-current-status">status: <strong><?= $service["active"] ? "active" : "inactive" ?></strong></p>
                        <fieldset class="requirement-options">
                            <legend>Provider listing requirements</legend>
                            <label class="checkbox-label"><input type="checkbox" name="requires_location" value="1" <?= $service["requires_location"] ? "checked" : "" ?>> require location</label>
                            <label class="checkbox-label"><input type="checkbox" name="requires_business_name" value="1" <?= $service["requires_business_name"] ? "checked" : "" ?>> require business name</label>
                            <label class="checkbox-label"><input type="checkbox" name="requires_photos" value="1" <?= $service["requires_photos"] ? "checked" : "" ?>> require provider photos</label>
                            <label class="checkbox-label"><input type="checkbox" name="requires_owner_name" value="1" <?= $service["requires_owner_name"] ? "checked" : "" ?>> require owner name</label>
                            <label class="checkbox-label"><input type="checkbox" name="requires_owner_phone" value="1" <?= $service["requires_owner_phone"] ? "checked" : "" ?>> require owner phone</label>
                            <label class="checkbox-label"><input type="checkbox" name="supports_delivery" value="1" <?= $service["supports_delivery"] ? "checked" : "" ?>> service offers delivery</label>
                            <label class="checkbox-label"><input type="checkbox" name="online_only" value="1" <?= $service["online_only"] ? "checked" : "" ?>> service is purely online</label>
                        </fieldset>
                    </form>
                    <div class="service-editor-actions">
                        <button class="small-button" type="submit" form="service-requirements-<?= (int) $service["id"] ?>">save</button>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="active_tab" value="edit-services">
                        <input type="hidden" name="action" value="toggle_service">
                        <input type="hidden" name="service_id" value="<?= (int) $service["id"] ?>">
                        <input type="hidden" name="open_service_id" value="<?= (int) $service["id"] ?>">
                        <button class="small-button" type="submit"><?= $service["active"] ? "deactivate" : "activate" ?></button>
                    </form>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admin-tab-panel" id="panel-add-provider" role="tabpanel" aria-labelledby="tab-add-provider" <?= $activeTab !== "add-provider" ? "hidden" : "" ?>>
        <h2>add provider</h2>
        <?php if (!$services): ?>
            <p>Add a service before creating its provider listing.</p>
        <?php else: ?>
            <form class="form-card" method="post" enctype="multipart/form-data" id="admin-provider-form">
                <?= csrf_field() ?>
                <input type="hidden" name="active_tab" value="add-provider">
                <input type="hidden" name="action" value="add_provider">
                <label>Service
                    <select name="service_id" id="admin-provider-service" required>
                        <option value="">Choose a service</option>
                        <?php foreach ($services as $service): if (!$service["active"]) { continue; } ?>
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
                                <?= (int) ($providerFormData["service_id"] ?? 0) === (int) $service["id"] ? "selected" : "" ?>>
                                <?= h(strtolower($service["name"])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label data-provider-field="photography_type">Photography type
                    <select name="photography_type">
                        <option value="">Choose indoor or outdoor</option>
                        <option value="indoor" <?= ($providerFormData["photography_type"] ?? "") === "indoor" ? "selected" : "" ?>>indoor</option>
                        <option value="outdoor" <?= ($providerFormData["photography_type"] ?? "") === "outdoor" ? "selected" : "" ?>>outdoor</option>
                    </select>
                </label>
                <label data-provider-field="business_name">Provider or business name <input name="name" maxlength="150" value="<?= h((string) ($providerFormData["name"] ?? "")) ?>"></label>
                <label data-provider-field="location">Location / area in Kisii County
                    <select name="location">
                        <option value="">Choose an area</option>
                        <?php foreach (KISII_AREAS as $area): ?>
                            <option value="<?= h($area) ?>" <?= ($providerFormData["location"] ?? "") === $area ? "selected" : "" ?>><?= h($area) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label data-provider-field="owner_name">Owner name <input name="owner_name" maxlength="150" value="<?= h((string) ($providerFormData["owner_name"] ?? "")) ?>"></label>
                <label data-provider-field="owner_phone">Owner phone <input name="phone" maxlength="40" value="<?= h((string) ($providerFormData["phone"] ?? "")) ?>"></label>
                <fieldset class="schedule-fields">
                    <legend>Opening schedule</legend>
                    <div class="schedule-time-fields">
                        <label>Opens at
                            <input type="time" name="opening_time" value="<?= h((string) ($providerFormData["opening_time"] ?? "")) ?>">
                        </label>
                        <label>Closes at
                            <input type="time" name="closing_time" value="<?= h((string) ($providerFormData["closing_time"] ?? "")) ?>">
                        </label>
                    </div>
                    <span class="schedule-days-label">Closed on</span>
                    <div class="schedule-days">
                        <?php $selectedClosedDays = normalize_closed_days($providerFormData["closed_days"] ?? []) ?? []; ?>
                        <?php foreach (["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"] as $day): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="closed_days[]" value="<?= h($day) ?>" <?= in_array($day, $selectedClosedDays, true) ? "checked" : "" ?>>
                                <?= h($day) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <label>Service details <textarea name="description" rows="4"><?= h((string) ($providerFormData["description"] ?? "")) ?></textarea></label>
                <label data-provider-field="photos">Provider photos <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label>
                <label class="checkbox-label" data-provider-field="delivery"><input type="checkbox" name="delivery_available" value="1" <?= isset($providerFormData["delivery_available"]) ? "checked" : "" ?>> delivery available</label>
                <button class="form-submit" type="submit">add approved provider</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="admin-tab-panel" id="panel-providers" role="tabpanel" aria-labelledby="tab-providers" <?= $activeTab !== "providers" ? "hidden" : "" ?>>
        <h2>providers by service</h2>
        <?php if (!$providersByService): ?><p>No non-pending provider listings.</p><?php endif; ?>
        <?php foreach ($providersByService as $serviceName => $serviceProviders): ?>
            <section class="provider-category">
                <h3><?= h(strtolower($serviceName)) ?></h3>
                <div class="portal-list">
                    <?php foreach ($serviceProviders as $provider): ?>
                        <article class="portal-listing">
                            <div>
                                <strong><?= h($provider["name"]) ?></strong>
                                <span><?= h($provider["location"] ?: "online") ?> · <?= h($provider["owner_email"] ?? "added by admin") ?></span>
                                <?php if ($provider["opening_time"] && $provider["closing_time"]): ?>
                                    <span><?= h(substr($provider["opening_time"], 0, 5)) ?>–<?= h(substr($provider["closing_time"], 0, 5)) ?><?= $provider["closed_days"] !== "" ? " · closed " . h(str_replace(",", ", ", $provider["closed_days"])) : "" ?></span>
                                <?php elseif ($provider["opening_hours"] !== ""): ?>
                                    <span><?= h($provider["opening_hours"]) ?></span>
                                <?php endif; ?>
                                <?php if ($provider["photography_type"] !== ""): ?><span><?= h($provider["photography_type"]) ?> photography</span><?php endif; ?>
                                <span class="listing-status status-<?= h($provider["approval_status"]) ?>"><?= h($provider["active"] ? $provider["approval_status"] : "inactive") ?></span>
                            </div>
                            <?php if (!$provider["active"] && $provider["owner_email"]): ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="active_tab" value="providers">
                                    <input type="hidden" name="action" value="restore_provider">
                                    <input type="hidden" name="provider_id" value="<?= (int) $provider["id"] ?>">
                                    <button class="small-button" type="submit">restore</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </section>

    <section class="admin-tab-panel" id="panel-pending" role="tabpanel" aria-labelledby="tab-pending" <?= $activeTab !== "pending" ? "hidden" : "" ?>>
        <h2>pending approvals</h2>
        <?php if (!$pendingProviders): ?><p>No provider listings are waiting for approval.</p><?php endif; ?>
        <div class="portal-list">
            <?php foreach ($pendingProviders as $provider): ?>
                <article class="portal-listing portal-review-listing">
                    <div>
                        <strong><?= h($provider["name"]) ?></strong>
                        <span><?= h(strtolower($provider["service_name"])) ?> · <?= h($provider["location"] ?: "online") ?></span>
                        <?php if ($provider["photography_type"] !== ""): ?><span><?= h($provider["photography_type"]) ?> photography</span><?php endif; ?>
                        <span>owner: <?= h($provider["owner_name"] ?: "not provided") ?></span>
                        <span><?= h($provider["owner_email"] ?? "provider account unavailable") ?></span>
                        <span class="listing-status status-pending">pending approval</span>
                    </div>
                    <dl class="provider-review-details">
                        <div><dt>address</dt><dd><?= h($provider["address"] ?: "not provided") ?></dd></div>
                        <div><dt>owner phone</dt><dd><?= h($provider["phone"] ?: "not provided") ?></dd></div>
                        <?php if ($provider["photography_type"] !== ""): ?><div><dt>photography</dt><dd><?= h($provider["photography_type"]) ?></dd></div><?php endif; ?>
                        <div><dt>opening hours</dt><dd><?= $provider["opening_time"] && $provider["closing_time"]
                            ? h(substr($provider["opening_time"], 0, 5) . "–" . substr($provider["closing_time"], 0, 5))
                            : h($provider["opening_hours"] ?: "not provided") ?></dd></div>
                        <div><dt>closed days</dt><dd><?= h($provider["closed_days"] !== "" ? str_replace(",", ", ", $provider["closed_days"]) : "not specified") ?></dd></div>
                        <div><dt>delivery</dt><dd><?= $provider["service_supports_delivery"] && $provider["delivery_available"] ? "available" : "not available" ?></dd></div>
                        <div class="review-description"><dt>service details</dt><dd><?= $provider["description"] !== "" ? nl2br(h($provider["description"])) : "not provided" ?></dd></div>
                        <?php if (!empty($providerPhotos[(int) $provider["id"]])): ?>
                            <div class="review-description">
                                <dt>photos</dt>
                                <dd class="provider-photo-gallery">
                                    <?php foreach ($providerPhotos[(int) $provider["id"]] as $photoPath): ?>
                                        <a href="../<?= h($photoPath) ?>" target="_blank" rel="noopener noreferrer">
                                            <img src="../<?= h($photoPath) ?>" alt="<?= h($provider["name"]) ?> photo">
                                        </a>
                                    <?php endforeach; ?>
                                </dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                    <div class="listing-actions">
                        <?php foreach (["approved" => "approve", "rejected" => "reject"] as $decision => $label): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="active_tab" value="pending">
                                <input type="hidden" name="action" value="review_provider">
                                <input type="hidden" name="provider_id" value="<?= (int) $provider["id"] ?>">
                                <input type="hidden" name="decision" value="<?= h($decision) ?>">
                                <button class="small-button" type="submit"><?= h($label) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <script>
    const adminTabs = [...document.querySelectorAll(".admin-tab")];
    function activateAdminTab(tab, updateUrl = true) {
        adminTabs.forEach((candidate) => {
            const active = candidate === tab;
            candidate.setAttribute("aria-selected", String(active));
            candidate.tabIndex = active ? 0 : -1;
            document.getElementById(candidate.getAttribute("aria-controls")).hidden = !active;
        });
        if (updateUrl) {
            const url = new URL(window.location.href);
            url.searchParams.set("tab", tab.id.replace("tab-", ""));
            history.replaceState(null, "", url);
        }
    }
    adminTabs.forEach((tab, index) => {
        tab.addEventListener("click", () => activateAdminTab(tab));
        tab.addEventListener("keydown", (event) => {
            if (event.key === "ArrowLeft" || event.key === "ArrowRight") {
                event.preventDefault();
                const step = event.key === "ArrowRight" ? 1 : -1;
                const nextTab = adminTabs[(index + step + adminTabs.length) % adminTabs.length];
                activateAdminTab(nextTab);
                nextTab.focus();
            }
        });
    });
    document.querySelectorAll(".service-editor").forEach((details) => {
        details.addEventListener("toggle", () => {
            if (details.open) {
                document.querySelectorAll(".service-editor[open]").forEach((otherDetails) => {
                    if (otherDetails !== details) {
                        otherDetails.open = false;
                    }
                });
            }
        });
    });
    document.querySelectorAll(".requirement-options").forEach((group) => {
        const onlineOnly = group.querySelector('input[name="online_only"]');
        const requiresLocation = group.querySelector('input[name="requires_location"]');
        if (!onlineOnly || !requiresLocation) {
            return;
        }
        const updateLocationRequirement = () => {
            if (onlineOnly.checked) {
                requiresLocation.checked = false;
            }
            requiresLocation.disabled = onlineOnly.checked;
        };
        onlineOnly.addEventListener("change", updateLocationRequirement);
        updateLocationRequirement();
    });
    const providerServiceSelect = document.getElementById("admin-provider-service");
    if (providerServiceSelect) {
        function updateProviderRequirements() {
            const option = providerServiceSelect.selectedOptions[0];
            const requirements = option && option.dataset.requirements ? JSON.parse(option.dataset.requirements) : {};
            document.querySelectorAll("[data-provider-field]").forEach((field) => {
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
                    input.required = visible && fieldName !== "delivery" && fieldName !== "photos";
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
        providerServiceSelect.addEventListener("change", updateProviderRequirements);
        updateProviderRequirements();
    }
    </script>
<?php endif; ?>
<?php require __DIR__ . "/../includes/footer.php"; ?>
