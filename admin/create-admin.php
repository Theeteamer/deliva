<?php
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

require_once __DIR__ . "/../config/database.php";

$adminCount = (int) $conn->query("SELECT COUNT(*) AS total FROM admin_accounts")->fetch_assoc()["total"];
if ($adminCount > 0) {
    fwrite(STDERR, "An admin account already exists. This setup command only creates the first admin.\n");
    exit(1);
}

fwrite(STDOUT, "First admin email: ");
$email = strtolower(trim((string) fgets(STDIN)));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    fwrite(STDERR, "Enter a valid email address.\n");
    exit(1);
}

fwrite(STDOUT, "Password (at least 12 characters): ");
$password = rtrim((string) fgets(STDIN), "\r\n");
fwrite(STDOUT, "Confirm password: ");
$confirmation = rtrim((string) fgets(STDIN), "\r\n");
if (strlen($password) < 12 || !hash_equals($password, $confirmation)) {
    fwrite(STDERR, "Passwords must match and contain at least 12 characters.\n");
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO admin_accounts (email, password_hash) VALUES (?, ?)");
$stmt->bind_param("ss", $email, $passwordHash);
$stmt->execute();
$stmt->close();
fwrite(STDOUT, "Admin account created for {$email}. Remove or retain this setup script securely.\n");
