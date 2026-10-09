<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set("session.use_strict_mode", "1");
    session_set_cookie_params([
        "httponly" => true,
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "samesite" => "Lax",
    ]);
    session_start();
}

require_once __DIR__ . "/../config/database.php";

const KISII_AREAS = [
    "Kisii Town",
    "Suneka",
    "Ogembo",
    "Nyamache",
    "Nyanchwa",
    "Mosocho",
    "Marani",
    "Daraja Mbili",
    "Keumbu",
    "Ichuni",
    "Gesusu",
    "Riana",
    "Nyamagwa",
    "Nyacheki",
    "Masimba",
    "Tabaka",
    "Etago",
    "Moticho",
    "Nyakoe",
    "Birongo",
];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function normalize_closed_days(mixed $input): ?array
{
    if (!is_array($input)) {
        return $input === null ? [] : null;
    }

    $validDays = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
    foreach ($input as $day) {
        if (!is_string($day) || !in_array($day, $validDays, true)) {
            return null;
        }
    }

    return array_values(array_unique($input));
}

function valid_time_input(string $time): bool
{
    return $time === "" || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time) === 1;
}

function find_service(mysqli $conn, string $slug): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, slug, name, description, supports_delivery
         FROM services WHERE slug = ? AND active = 1 LIMIT 1"
    );
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $service = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $service;
}

function csrf_token(): string
{
    if (!isset($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function verify_csrf(): void
{
    $token = (string) ($_POST["csrf_token"] ?? "");
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit("Invalid form submission. Reload the page and try again.");
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function uploaded_provider_photos(array $upload): array
{
    if (!isset($upload["name"]) || !is_array($upload["name"])) {
        return [];
    }
    if (count($upload["name"]) > 5) {
        throw new RuntimeException("Upload no more than five photos.");
    }

    $photos = [];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $extensions = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
    foreach ($upload["name"] as $index => $_name) {
        $error = $upload["error"][$index] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException("A photo could not be uploaded. Please try again.");
        }
        $temporaryPath = $upload["tmp_name"][$index] ?? "";
        $size = (int) ($upload["size"][$index] ?? 0);
        $mimeType = $temporaryPath !== "" && is_uploaded_file($temporaryPath)
            ? $finfo->file($temporaryPath)
            : false;
        $image = $temporaryPath !== "" && is_uploaded_file($temporaryPath) ? getimagesize($temporaryPath) : false;
        if (
            $size < 1 || $size > 5 * 1024 * 1024
            || !isset($extensions[$mimeType])
            || !$image || $image["mime"] !== $mimeType
        ) {
            throw new RuntimeException("Photos must be JPG, PNG, or WEBP images no larger than 5 MB each.");
        }
        $photos[] = [
            "temporary_path" => $temporaryPath,
            "extension" => $extensions[$mimeType],
        ];
    }

    return $photos;
}

function store_provider_photos(mysqli $conn, int $providerId, array $photos): void
{
    if (!$photos) {
        return;
    }
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM provider_photos WHERE provider_id = ?");
    $stmt->bind_param("i", $providerId);
    $stmt->execute();
    $existingCount = (int) $stmt->get_result()->fetch_assoc()["total"];
    $stmt->close();
    if ($existingCount + count($photos) > 5) {
        throw new RuntimeException("A provider can have no more than five photos.");
    }
    $uploadDirectory = __DIR__ . "/../uploads/providers";
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException("The provider photo folder could not be created.");
    }

    $stmt = $conn->prepare("INSERT INTO provider_photos (provider_id, file_path) VALUES (?, ?)");
    foreach ($photos as $photo) {
        $filename = bin2hex(random_bytes(16)) . "." . $photo["extension"];
        if (!move_uploaded_file($photo["temporary_path"], $uploadDirectory . "/" . $filename)) {
            throw new RuntimeException("A photo could not be saved. Please try again.");
        }
        $relativePath = "uploads/providers/" . $filename;
        $stmt->bind_param("is", $providerId, $relativePath);
        $stmt->execute();
    }
    $stmt->close();
}

function redirect_to(string $url): never
{
    header("Location: " . $url, true, 303);
    exit;
}

function provider_account_id(): ?int
{
    return isset($_SESSION["provider_account_id"]) ? (int) $_SESSION["provider_account_id"] : null;
}

function require_provider_account(): int
{
    $accountId = provider_account_id();
    if (!$accountId) {
        $scriptName = (string) ($_SERVER["SCRIPT_NAME"] ?? "");
        redirect_to(str_contains($scriptName, "/admin/") ? "../provider-sign-in.php" : "provider-sign-in.php");
    }

    return $accountId;
}

function require_admin_account(): int
{
    $accountId = isset($_SESSION["admin_account_id"]) ? (int) $_SESSION["admin_account_id"] : null;
    if (!$accountId) {
        redirect_to("index.php");
    }

    return $accountId;
}
