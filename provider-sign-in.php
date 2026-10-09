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
    $stmt = $conn->prepare("SELECT id, password_hash FROM provider_accounts WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$account || !password_verify($password, $account["password_hash"])) {
        $error = "Email or password is incorrect.";
    } else {
        session_regenerate_id(true);
        unset($_SESSION["admin_account_id"]);
        $_SESSION["provider_account_id"] = (int) $account["id"];
        redirect_to("provider-portal.php");
    }
}

$pageTitle = "Provider sign in";
require __DIR__ . "/includes/header.php";
?>
<section class="page-heading"><h1>provider sign in</h1></section>
<?php if ($error !== ""): ?><p class="form-message form-error" role="alert"><?= h($error) ?></p><?php endif; ?>
<form class="form-card form-narrow" method="post">
    <?= csrf_field() ?>
    <label>Email <input type="email" name="email" maxlength="254" autocomplete="username" required></label>
    <label>Password <input type="password" name="password" autocomplete="current-password" required></label>
    <button class="form-submit" type="submit">sign in</button>
    <a class="auth-link" href="provider-password-reset.php">forgot password?</a>
    <p class="auth-switch">New to Deliva? <a href="provider-sign-up.php">create an account</a>.</p>
</form>
<?php require __DIR__ . "/includes/footer.php"; ?>
