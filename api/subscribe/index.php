<?php

require_once __DIR__ . '/../lib/common.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$redirectUrl = $_POST['redirect_url'] ?? WIKIZEIT_PATH;

// Check honeypot field
if (!empty($_POST['email_confirmation'])) {
    header('Location: ' . buildRedirectUrl($redirectUrl, 'subscribe_error'));
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    header('Location: ' . buildRedirectUrl($redirectUrl, 'subscribe_error'));
    exit;
}

// Check if email already exists
$stmt = $db->prepare('SELECT verified FROM subscribers WHERE email = :email');
$stmt->bindValue(':email', $email, SQLITE3_TEXT);
$result = $stmt->execute();
$existing = $result->fetchArray(SQLITE3_ASSOC);

if ($existing) {
    if ($existing['verified'] == 1) {
        // Already verified
        header('Location: ' . buildRedirectUrl($redirectUrl, 'subscribe_info'));
    } else {
        // Already registered but not verified
        header('Location: ' . buildRedirectUrl($redirectUrl, 'subscribe_info'));
    }
    exit;
}

// Generate token and save to database
$token = generateToken();
$stmt = $db->prepare('INSERT INTO subscribers (email, token) VALUES (:email, :token)');
$stmt->bindValue(':email', $email, SQLITE3_TEXT);
$stmt->bindValue(':token', $token, SQLITE3_TEXT);

if ($stmt->execute()) {
    // Send confirmation email
    $verifyUrl = WIKIZEIT_URL . "api/verify/?token={$token}";

    $emailMessage = $mustache->render('email-confirmation', [
        'verifyUrl' => $verifyUrl,
        'year' => date('Y')
    ]);

    if (sendEmail($email, 'Potwierdź subskrypcję WikiZEIT', $emailMessage)) {
        header('Location: ' . buildRedirectUrl($redirectUrl, 'subscribe_success'));
    } else {
        header('Location: ' . buildRedirectUrl($redirectUrl, 'subscribe_error'));
    }
} else {
    header('Location: ' . buildRedirectUrl($redirectUrl, 'subscribe_error'));
}
exit;
