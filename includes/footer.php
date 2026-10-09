</main>
<footer class="site-footer">
    <div class="footer-inner">
        <?php if (basename((string) ($_SERVER["SCRIPT_NAME"] ?? "")) === "index.php" && !str_contains((string) ($_SERVER["SCRIPT_NAME"] ?? ""), "/admin/")): ?>
            <a class="provide-service-link" href="provider-sign-up.php">provide service</a>
        <?php endif; ?>
        <p class="footer-legal">
            <span>By using this website, you agree to our</span>
            <span><a href="<?= h($siteBase) ?>terms.php">Terms &amp; Conditions</a> and <a href="<?= h($siteBase) ?>privacy.php">Privacy Policy</a></span>
        </p>
    </div>
</footer>
<script>
document.querySelectorAll(".form-success[data-auto-dismiss]").forEach((message) => {
    window.setTimeout(() => {
        message.classList.add("is-dismissing");
        window.setTimeout(() => message.remove(), 300);
    }, 5000);
});
</script>
</body>
</html>
