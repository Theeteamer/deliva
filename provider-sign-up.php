<?php
require_once __DIR__ . "/includes/bootstrap.php";

if (provider_account_id()) {
    redirect_to("provider-portal.php");
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST["email"] ?? "")));
    $password = (string) ($_POST["password"] ?? "");
    $confirmation = (string) ($_POST["password_confirmation"] ?? "");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $error = "Enter a valid email address.";
    } elseif (strlen($password) < 12) {
        $error = "Use a password with at least 12 characters.";
    } elseif ($password !== $confirmation) {
        $error = "The passwords do not match.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM provider_accounts WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $exists = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $error = "An account already exists for that email.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO provider_accounts (email, password_hash) VALUES (?, ?)");
            $stmt->bind_param("ss", $email, $passwordHash);
            $stmt->execute();
            $accountId = (int) $conn->insert_id;
            $stmt->close();
            session_regenerate_id(true);
            unset($_SESSION["admin_account_id"]);
            $_SESSION["provider_account_id"] = $accountId;
            redirect_to("provider-portal.php");
        }
    }
}

$pageTitle = "Create provider account";
require __DIR__ . "/includes/header.php";
?>
<section class="page-heading"><h1>create provider account</h1></section>
<?php if ($error !== ""): ?><p class="form-message form-error" role="alert"><?= h($error) ?></p><?php endif; ?>
<form class="form-card form-narrow" method="post">
    <?= csrf_field() ?>
    <label>Email <input type="email" name="email" maxlength="254" autocomplete="email" required></label>
    <label>Password <input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
    <label>Confirm password <input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
    <button class="form-submit" type="submit">create account</button>
    <p class="auth-switch">Already registered? <a href="provider-sign-in.php">sign in</a>.</p>
</form>
<?php require __DIR__ . "/includes/footer.php"; ?>
