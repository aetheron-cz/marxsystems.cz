<?php
declare(strict_types=1);

// Requires PHP 7.4+ and a working hosting mail() transport.
const RECIPIENT = 'info@marxsystems.cz';
const SITE_DOMAIN = 'marxsystems.cz';
const RETURN_URL = 'kontakt.html';

function respond(int $status, string $message): void
{
    http_response_code($status);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => $status === 200, 'message' => $message], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $back = htmlspecialchars(RETURN_URL, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="cs"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Kontaktní formulář</title><body><main><h1>Kontaktní formulář</h1><p>' . $safeMessage . '</p><p><a href="' . $back . '">Zpět na formulář</a></p></main></body></html>';
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, 'Použijte prosím kontaktní formulář.');
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
    respond(413, 'Zpráva je příliš dlouhá. Zkraťte ji prosím.');
}

$fields = [];
foreach (['name' => 200, 'email' => 254, 'subject' => 200, 'company' => 200, 'phone' => 80, 'message' => 10000, 'website' => 200] as $key => $limit) {
    $value = $_POST[$key] ?? '';
    if (!is_string($value) || strlen($value) > $limit || preg_match('//u', $value) !== 1 || strpos($value, "\0") !== false) {
        respond(422, 'Zkontrolujte prosím vyplněné údaje a délku zprávy.');
    }
    $fields[$key] = trim($value);
}
if ($fields['website'] !== '') {
    respond(422, 'Zprávu se nepodařilo odeslat. Kontaktujte nás prosím e-mailem.');
}
if ($fields['name'] === '' || $fields['message'] === '' || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
    respond(422, 'Vyplňte prosím jméno, platný e-mail a zprávu.');
}
foreach (['name', 'email', 'subject', 'company', 'phone'] as $key) {
    if (preg_match('/[\r\n]/', $fields[$key])) {
        respond(422, 'Zkontrolujte prosím vyplněné údaje.');
    }
}

// A session cooldown prevents repeated clicks from sending duplicate mail.
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
if (isset($_SESSION['last_contact_sent']) && time() - $_SESSION['last_contact_sent'] < 60) {
    header('Retry-After: 60');
    respond(429, 'Před odesláním další zprávy prosím počkejte jednu minutu.');
}

$subject = 'Kontaktní formulář ' . SITE_DOMAIN;
if ($fields['subject'] !== '') {
    $subject .= ': ' . $fields['subject'];
}
$body = "Nová zpráva z kontaktního formuláře " . SITE_DOMAIN . "\n\n";
foreach (['name' => 'Jméno', 'email' => 'E-mail', 'company' => 'Společnost', 'phone' => 'Telefon', 'subject' => 'Předmět'] as $key => $label) {
    if ($fields[$key] !== '') {
        $body .= $label . ': ' . $fields[$key] . "\n";
    }
}
$body .= "\nZpráva:\n" . $fields['message'] . "\n";
$headers = [
    'From: ' . SITE_DOMAIN . ' <noreply@' . SITE_DOMAIN . '>',
    'Reply-To: ' . $fields['email'],
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
];
$sent = @mail(RECIPIENT, '=?UTF-8?B?' . base64_encode($subject) . '?=', chunk_split(base64_encode($body)), implode("\r\n", $headers));
if (!$sent) {
    error_log('Contact form mail transport failed for ' . SITE_DOMAIN);
    respond(503, 'Zprávu se nepodařilo odeslat. Zkuste to později nebo napište na ' . RECIPIENT . '.');
}
$_SESSION['last_contact_sent'] = time();
respond(200, 'Děkujeme, zpráva byla odeslána. Ozveme se Vám co nejdříve.');
