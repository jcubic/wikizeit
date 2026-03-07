<?php

require_once __DIR__ . '/../lib/common.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: ' . buildRedirectUrl(WIKIZEIT_PATH, 'verify_error'));
    exit;
}

$stmt = $db->prepare('SELECT email, verified, created_at FROM subscribers WHERE token = :token');
$stmt->bindValue(':token', $token, SQLITE3_TEXT);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);

if ($row && $row['verified'] == 0) {
    // Check if token has expired (30 days)
    $createdAt = strtotime($row['created_at']);
    $expirationTime = $createdAt + (30 * 24 * 60 * 60); // 30 days in seconds
    $currentTime = time();

    if ($currentTime > $expirationTime) {
        // Token expired - delete the entry
        $stmt = $db->prepare('DELETE FROM subscribers WHERE token = :token');
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $stmt->execute();

        header('Location: ' . buildRedirectUrl(WIKIZEIT_PATH, 'verify_error'));
    } else {
        // Token valid - verify email
        $email = $row['email'];
        $stmt = $db->prepare('UPDATE subscribers SET verified = 1 WHERE token = :token');
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $stmt->execute();

        // Send notification to owner
        $notifyMessage = $mustache->render('email-notification', [
            'email' => htmlspecialchars($email),
            'verificationDate' => date('d.m.Y H:i:s')
        ]);
        sendEmail('jcubic@jcubic.pl', 'Nowy subskrybent WikiZEIT', $notifyMessage);

        header('Location: ' . buildRedirectUrl(WIKIZEIT_PATH, 'verify_success'));
    }
} elseif ($row && $row['verified'] == 1) {
    header('Location: ' . buildRedirectUrl(WIKIZEIT_PATH, 'verify_info'));
} else {
    header('Location: ' . buildRedirectUrl(WIKIZEIT_PATH, 'verify_error'));
}
exit;
