<?php
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

require_once __DIR__ . "/../includes/bootstrap.php";

$profiles = [
    ["Akinyi", "Odhiambo", KISII_AREAS[0]],
    ["Wanjiku", "Mwangi", KISII_AREAS[1]],
    ["Mwende", "Mutua", KISII_AREAS[2]],
    ["Chebet", "Kiptoo", KISII_AREAS[3]],
    ["Achieng", "Otieno", KISII_AREAS[4]],
    ["Wambui", "Kamau", KISII_AREAS[5]],
    ["Naliaka", "Wekesa", KISII_AREAS[6]],
    ["Muthoni", "Njoroge", KISII_AREAS[7]],
    ["Atieno", "Omondi", KISII_AREAS[8]],
    ["Jebet", "Rotich", KISII_AREAS[9]],
    ["Nyambura", "Githinji", KISII_AREAS[10]],
    ["Moraa", "Onyango", KISII_AREAS[11]],
    ["Kendi", "Muriuki", KISII_AREAS[12]],
    ["Njeri", "Kariuki", KISII_AREAS[13]],
    ["Adhiambo", "Ochieng", KISII_AREAS[14]],
    ["Wairimu", "Maina", KISII_AREAS[15]],
    ["Mumbi", "Karanja", KISII_AREAS[16]],
    ["Chepkemoi", "Langat", KISII_AREAS[17]],
    ["Auma", "Abdalla", KISII_AREAS[18]],
    ["Wanjala", "Barasa", KISII_AREAS[19]],
];

$serviceLabels = [
    "laundry" => "Laundry Care",
    "gas-delivery" => "Gas Services",
    "water" => "Water Supply",
    "food" => "Kitchen",
    "photography" => "Photography",
    "mama-fua" => "Mama Fua",
    "house-party" => "Events",
];

$photoDirectory = __DIR__ . "/../uploads/providers";
if (!is_dir($photoDirectory) && !mkdir($photoDirectory, 0750, true) && !is_dir($photoDirectory)) {
    fwrite(STDERR, "The provider photo folder could not be created.\n");
    exit(1);
}
for ($photoIndex = 1; $photoIndex <= 20; $photoIndex++) {
    $photoPath = $photoDirectory . "/demo-" . str_pad((string) $photoIndex, 2, "0", STR_PAD_LEFT) . ".jpg";
    if (!is_file($photoPath)) {
        $imageNumber = str_pad((string) $photoIndex, 2, "0", STR_PAD_LEFT);
        $curl = curl_init("https://picsum.photos/seed/deliva-kenya-demo-{$imageNumber}/900/650");
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $imageData = curl_exec($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $downloadError = curl_error($curl);
        curl_close($curl);
        $imageInfo = is_string($imageData) ? getimagesizefromstring($imageData) : false;
        if (
            $imageData === false || $httpStatus !== 200 || !str_starts_with($contentType, "image/")
            || strlen($imageData) > 5 * 1024 * 1024 || !$imageInfo
        ) {
            fwrite(STDERR, "Could not download a valid demo photo #{$imageNumber}: {$downloadError}\n");
            exit(1);
        }
        if (file_put_contents($photoPath, $imageData, LOCK_EX) === false) {
            fwrite(STDERR, "Could not save demo photo: {$photoPath}\n");
            exit(1);
        }
    }
}

$services = $conn->query(
    "SELECT id, slug, name, supports_delivery
     FROM services WHERE active = 1 ORDER BY sort_order, name"
)->fetch_all(MYSQLI_ASSOC);
if (!$services) {
    fwrite(STDERR, "No active services are available for demo listings.\n");
    exit(1);
}

$insertProvider = $conn->prepare(
    "INSERT INTO providers
     (service_id, photography_type, owner_name, is_demo, name, location, address, phone, description,
      opening_time, closing_time, closed_days, delivery_available, active, approval_status)
     VALUES (?, ?, ?, 1, ?, ?, '', ?, ?, ?, ?, ?, ?, 1, 'approved')"
);
$insertPhoto = $conn->prepare(
    "INSERT INTO provider_photos (provider_id, file_path) VALUES (?, ?)"
);

$addedTotal = 0;
$conn->begin_transaction();
try {
    foreach ($services as $service) {
        $serviceId = (int) $service["id"];
        $slug = (string) $service["slug"];
        $serviceLabel = $serviceLabels[$slug] ?? ucfirst((string) $service["name"]);

        $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM providers WHERE service_id = ? AND is_demo = 1");
        $countStmt->bind_param("i", $serviceId);
        $countStmt->execute();
        $existingCount = (int) $countStmt->get_result()->fetch_assoc()["total"];
        $countStmt->close();

        if ($existingCount >= 20) {
            fwrite(STDOUT, "{$slug}: already has {$existingCount} demo listings; skipped.\n");
            continue;
        }

        for ($profileIndex = $existingCount; $profileIndex < 20; $profileIndex++) {
            [$firstName, $lastName, $location] = $profiles[$profileIndex];
            $ownerName = $firstName . " " . $lastName;
            $providerName = $firstName . " " . $serviceLabel;
            $phone = "+254 000 " . str_pad((string) ($serviceId * 100 + $profileIndex + 1), 3, "0", STR_PAD_LEFT)
                . " " . str_pad((string) ($profileIndex + 1), 3, "0", STR_PAD_LEFT);
            $description = "Reliable local {$serviceLabel} services available in {$location}. Contact us for availability and details.";
            $openingTime = sprintf("%02d:00:00", 7 + ($profileIndex % 4));
            $closingTime = sprintf("%02d:00:00", 17 + ($profileIndex % 5));
            $closedDay = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"][$profileIndex % 7];
            $photographyType = $slug === "photography"
                ? ($profileIndex % 2 === 0 ? "indoor" : "outdoor")
                : "";
            $deliveryAvailable = !empty($service["supports_delivery"]) && $profileIndex % 2 === 0 ? 1 : 0;

            $insertProvider->bind_param(
                "isssssssssi",
                $serviceId,
                $photographyType,
                $ownerName,
                $providerName,
                $location,
                $phone,
                $description,
                $openingTime,
                $closingTime,
                $closedDay,
                $deliveryAvailable
            );
            $insertProvider->execute();
            $providerId = $conn->insert_id;

            $photoName = "uploads/providers/demo-" . str_pad((string) ($profileIndex + 1), 2, "0", STR_PAD_LEFT) . ".jpg";
            $insertPhoto->bind_param("is", $providerId, $photoName);
            $insertPhoto->execute();
            $addedTotal++;
        }

        fwrite(STDOUT, "{$slug}: added " . (20 - $existingCount) . " demo listings.\n");
    }

    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    fwrite(STDERR, "Demo listings were not added: " . $exception->getMessage() . "\n");
    exit(1);
} finally {
    $insertProvider->close();
    $insertPhoto->close();
}

fwrite(STDOUT, "Added {$addedTotal} provider listings.\n");
