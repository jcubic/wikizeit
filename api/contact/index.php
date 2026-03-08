<?php

require_once __DIR__ . '/../lib/common.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$redirectUrl = $_POST['redirect_url'] ?? WIKIZEIT_PATH . 'contact/';

// Honeypot check
if (!empty($_POST['name_confirmation'])) {
    header('Location: ' . buildRedirectUrl($redirectUrl, 'bot_error', 'contact'));
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validate required fields
if (empty($name) || !$email || empty($subject) || empty($message)) {
    header('Location: ' . buildRedirectUrl($redirectUrl, 'contact_error', null));
    exit;
}

// Build plain text email body (matching szkolenia format)
$body = "Wiadomość ze strony " . WIKIZEIT_URL . ":\n\n";
$body .= "From: " . $email . "\n";
$body .= "Name: " . $name . "\n";
$body .= "Message:\n" . $message;

if (sendPlainEmail('jcubic@jcubic.pl', 'WikiZEIT Kontakt: ' . $subject, $body, $email)) {
    header('Location: ' . buildRedirectUrl($redirectUrl, 'contact_success', null));
} else {
    header('Location: ' . buildRedirectUrl($redirectUrl, 'contact_error', null));
}
exit;
