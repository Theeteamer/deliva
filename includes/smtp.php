<?php

$mailConfig = require __DIR__ . "/../config/mail.php";

function smtp_read_response($socket): string
{
    $response = "";
    do {
        $line = fgets($socket, 1024);
        if ($line === false) {
            throw new RuntimeException("SMTP server closed the connection.");
        }
        $response .= $line;
    } while (isset($line[3]) && $line[3] === "-");

    return $response;
}

function smtp_expect($socket, array $expectedCodes): string
{
    $response = smtp_read_response($socket);
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException("SMTP command failed with response: " . trim($response));
    }

    return $response;
}

function smtp_command($socket, string $command, array $expectedCodes): string
{
    if (fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException("Unable to write to the SMTP server.");
    }

    return smtp_expect($socket, $expectedCodes);
}

function smtp_configuration_is_valid(): bool
{
    global $mailConfig;
    $host = trim((string) ($mailConfig["host"] ?? ""));
    $port = (int) ($mailConfig["port"] ?? 587);
    $security = strtolower(trim((string) ($mailConfig["security"] ?? "starttls")));
    $username = (string) ($mailConfig["username"] ?? "");
    $password = (string) getenv("DELIVA_SMTP_PASSWORD");
    $fromAddress = trim((string) ($mailConfig["from_address"] ?? ""));

    return $host !== ""
        && $port >= 1
        && $port <= 65535
        && in_array($security, ["starttls", "ssl"], true)
        && filter_var($fromAddress, FILTER_VALIDATE_EMAIL) !== false
        && (($username === "") === ($password === ""));
}

function send_smtp_mail(string $recipient, string $subject, string $body): void
{
    global $mailConfig;
    $host = trim((string) ($mailConfig["host"] ?? ""));
    $port = (int) ($mailConfig["port"] ?? 587);
    $security = strtolower(trim((string) ($mailConfig["security"] ?? "starttls")));
    $username = (string) ($mailConfig["username"] ?? "");
    $password = (string) getenv("DELIVA_SMTP_PASSWORD");
    $fromAddress = trim((string) ($mailConfig["from_address"] ?? ""));
    $fromName = trim((string) ($mailConfig["from_name"] ?? "Deliva"));

    if ($host === "" || $fromAddress === "" || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("SMTP host and a valid sender address must be configured.");
    }
    if ($port < 1 || $port > 65535 || !in_array($security, ["starttls", "ssl"], true)) {
        throw new RuntimeException("SMTP port or security mode is invalid.");
    }
    if (($username === "") !== ($password === "")) {
        throw new RuntimeException("Both SMTP username and password must be configured together.");
    }
    if (preg_match('/[\r\n]/', $recipient . $subject . $fromName)) {
        throw new RuntimeException("Mail headers contain invalid line breaks.");
    }

    $transport = $security === "ssl" ? "ssl://" : "";
    $context = stream_context_create([
        "ssl" => [
            "verify_peer" => true,
            "verify_peer_name" => true,
        ],
    ]);
    $socket = @stream_socket_client(
        $transport . $host . ":" . $port,
        $errorCode,
        $errorMessage,
        15,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if (!$socket) {
        throw new RuntimeException("SMTP connection failed ({$errorCode}): {$errorMessage}");
    }

    stream_set_timeout($socket, 15);
    try {
        smtp_expect($socket, [220]);
        $hostname = (string) ($mailConfig["helo"] ?? "localhost");
        smtp_command($socket, "EHLO " . $hostname, [250]);

        if ($security === "starttls") {
            smtp_command($socket, "STARTTLS", [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException("SMTP TLS negotiation failed.");
            }
            smtp_command($socket, "EHLO " . $hostname, [250]);
        }

        if ($username !== "") {
            smtp_command($socket, "AUTH LOGIN", [334]);
            smtp_command($socket, base64_encode($username), [334]);
            smtp_command($socket, base64_encode($password), [235]);
        }

        smtp_command($socket, "MAIL FROM:<" . $fromAddress . ">", [250]);
        smtp_command($socket, "RCPT TO:<" . $recipient . ">", [250, 251]);
        smtp_command($socket, "DATA", [354]);

        $encodedName = "=?UTF-8?B?" . base64_encode($fromName) . "?=";
        $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        $message = implode("\r\n", [
            "From: {$encodedName} <{$fromAddress}>",
            "To: <{$recipient}>",
            "Subject: {$encodedSubject}",
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
            "",
            str_replace(["\r\n", "\r", "\n"], "\r\n", $body),
        ]);
        $message = preg_replace('/(?m)^\./', '..', $message);
        if (fwrite($socket, $message . "\r\n.\r\n") === false) {
            throw new RuntimeException("Unable to send the email message.");
        }
        smtp_expect($socket, [250]);
        smtp_command($socket, "QUIT", [221]);
    } finally {
        fclose($socket);
    }
}
