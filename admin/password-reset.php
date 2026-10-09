<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_once __DIR__ . "/../includes/smtp.php";

global $mailConfig;
$error = "";
$notice = "";
$resetSucceeded = false;
$token = trim((string) ($_GET["token"] ?? $_POST["token"] ?? ""));
$validReset = false;
header("Referrer-Policy: no-referrer");
header("Cache-Control: no-store");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = (string) ($_POST["action"] ?? "");

    if ($action === "request_reset") {
        $email = strtolower(trim((string) ($_POST["email"] ?? "")));
        $baseUrl = rtrim(trim((string) ($mailConfig["base_url"] ?? "")), "/");
        $baseParts = $baseUrl === "" ? false : parse_url($baseUrl);
        $baseValid = is_array($baseParts)
            && in_array($baseParts["scheme"] ?? "", ["http", "https"], true)
            && isset($baseParts["host"]);

        if (!smtp_configuration_is_valid() || !$baseValid) {
            $error = "Password recovery email is not configured. Please contact the site administrator.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $error = "Enter a valid email address.";
        } else {
            $stmt = $conn->prepare("SELECT id FROM admin_accounts WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($account) {
                $accountId = (int) $account["id"];
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash("sha256", $rawToken);
                $expiresAt = (new DateTimeImmutable("+30 minutes"))->format("Y-m-d H:i:s");

                $stmt = $conn->prepare("DELETE FROM admin_password_resets WHERE account_id = ?");
                $stmt->bind_param("i", $accountId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    "INSERT INTO admin_password_resets (account_id, token_hash, expires_at)
                     VALUES (?, ?, ?)"
                );
                $stmt->bind_param("iss", $accountId, $tokenHash, $expiresAt);
                $stmt->execute();
                $resetId = (int) $conn->insert_id;
                $stmt->close();

                $resetUrl = $baseUrl . "/admin/password-reset.php?token=" . rawurlencode($rawToken);
                $message = "Use this link to reset your Deliva admin password. "
                    . "It expires in 30 minutes and can only be used once:\r\n\r\n"
                    . $resetUrl . "\r\n";
                try {
                    send_smtp_mail($email, "Reset your Deliva admin password", $message);
                } catch (Throwable $mailError) {
                    $stmt = $conn->prepare("DELETE FROM admin_password_resets WHERE id = ?");
                    $stmt->bind_param("i", $resetId);
                    $stmt->execute();
                    $stmt->close();
                    error_log("Deliva admin password reset email failed: " . $mailError->getMessage());
                    $error = "The reset email could not be delivered. Please try again later or contact the site administrator.";
                }
            }

            if ($error === "") {
                $notice = "If an admin account exists for that address, a password reset link has been sent.";
            }
        }
    } elseif ($action === "reset_password") {
        $password = (string) ($_POST["password"] ?? "");
        $confirmation = (string) ($_POST["password_confirmation"] ?? "");
        if (!preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
            $error = "This reset link is invalid or has expired.";
        } elseif (strlen($password) < 12) {
            $error = "Use a password with at least 12 characters.";
        } elseif ($password !== $confirmation) {
            $error = "The passwords do not match.";
        } else {
            $tokenHash = hash("sha256", $token);
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare(
                    "SELECT id, account_id FROM admin_password_resets
                     WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
                     LIMIT 1 FOR UPDATE"
                );
                $stmt->bind_param("s", $tokenHash);
                $stmt->execute();
                $reset = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$reset) {
                    $conn->rollback();
                    $error = "This reset link is invalid or has expired.";
                } else {
                    $accountId = (int) $reset["account_id"];
                    $resetId = (int) $reset["id"];
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE admin_accounts SET password_hash = ? WHERE id = ?");
                    $stmt->bind_param("si", $passwordHash, $accountId);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare(
                        "UPDATE admin_password_resets SET used_at = NOW() WHERE id = ?"
                    );
                    $stmt->bind_param("i", $resetId);
                    $stmt->execute();
                    $stmt->close();
                    $stmt = $conn->prepare(
                        "DELETE FROM admin_password_resets WHERE account_id = ? AND id <> ?"
                    );
                    $stmt->bind_param("ii", $accountId, $resetId);
                    $stmt->execute();
                    $stmt->close();
                    $conn->commit();
                    unset($_SESSION["admin_account_id"]);
                    session_regenerate_id(true);
                    $notice = "Your password has been changed. You can now sign in.";
                    $resetSucceeded = true;
                    $token = "";
                }
            } catch (Throwable $resetError) {
                $conn->rollback();
                throw $resetError;
            }
        }
    } else {
        http_response_code(400);
        exit("Invalid form action.");
    }
}

if ($token !== "" && preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
    $tokenHash = hash("sha256", $token);
    $stmt = $conn->prepare(
        "SELECT id FROM admin_password_resets
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1"
    );
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $validReset = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$validReset && $notice === "") {
        $error = "This reset link is invalid or has expired.";
    }
} elseif ($token !== "") {
    $error = "This reset link is invalid or has expired.";
}

$pageTitle = "Reset admin password";
require __DIR__ . "/../includes/header.php";
?>
<section class="page-heading"><h1>admin forgot password?</h1></section>
<?php if ($error !== ""): ?><p class="form-message form-error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($notice !== ""): ?><p class="form-message form-success" role="status"><?= h($notice) ?></p><?php endif; ?>

<?php if ($resetSucceeded): ?>
    <a class="form-submit form-submit-link" href="index.php">sign in</a>
<?php elseif ($validReset): ?>
    <form class="form-card form-narrow" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <label>New password <input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
        <label>Confirm new password <input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
        <button class="form-submit" type="submit">change password</button>
    </form>
<?php elseif ($token === ""): ?>
    <form class="form-card form-narrow" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="request_reset">
        <label>Email <input type="email" name="email" maxlength="254" autocomplete="email" required></label>
        <button class="form-submit" type="submit">send reset link</button>
        <p class="auth-switch">Remember your password? <a href="index.php">sign in</a>.</p>
    </form>
<?php endif; ?>
<?php require __DIR__ . "/../includes/footer.php"; ?>
