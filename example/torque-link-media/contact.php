<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function clean_header_value(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function clean_text(string $value): string
{
    return trim(str_replace(["\0"], '', $value));
}

function silently_accept(): void
{
    respond(200, ['message' => 'Thanks. Your enquiry was sent.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['message' => 'Method not allowed.']);
}

$configPath = __DIR__ . '/contact-config.php';

if (!is_file($configPath)) {
    respond(500, ['message' => 'Contact form is not configured yet.']);
}

$config = require $configPath;
$requiredConfig = [
    'recipient_email',
    'from_email',
];

foreach ($requiredConfig as $key) {
    if (empty($config[$key]) || str_starts_with((string) $config[$key], 'REPLACE_WITH_')) {
        respond(500, ['message' => 'Contact form is not configured yet.']);
    }
}

$recipientEmail = clean_header_value((string) $config['recipient_email']);
$fromEmail = clean_header_value((string) $config['from_email']);
$fromName = clean_header_value((string) ($config['from_name'] ?? 'Website'));
$subjectPrefix = clean_header_value((string) ($config['subject_prefix'] ?? 'Website enquiry'));

if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    respond(500, ['message' => 'Contact form email settings are invalid.']);
}

$name = clean_text((string) ($_POST['name'] ?? ''));
$email = strtolower(clean_header_value((string) ($_POST['email'] ?? '')));
$company = clean_text((string) ($_POST['company'] ?? ''));
$category = clean_text((string) ($_POST['product-category'] ?? ''));
$message = clean_text((string) ($_POST['message'] ?? ''));
$honeypot = clean_text((string) ($_POST['website'] ?? ''));
$startedAt = (int) ($_POST['started_at'] ?? 0);

if ($honeypot !== '') {
    silently_accept();
}

$minimumSubmitSeconds = (int) ($config['minimum_submit_seconds'] ?? 3);
$elapsedSeconds = $startedAt > 0 ? (time() - (int) floor($startedAt / 1000)) : 0;

if ($minimumSubmitSeconds > 0 && $elapsedSeconds > 0 && $elapsedSeconds < $minimumSubmitSeconds) {
    silently_accept();
}

if ($name === '') {
    respond(422, ['message' => 'Please enter your name.']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, ['message' => 'Please enter a valid email address.']);
}


$subject = sprintf('%s: %s', $subjectPrefix, clean_header_value($company !== '' ? $company : $name));
$submittedAt = gmdate('Y-m-d H:i:s') . ' UTC';
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '-';

$plainTextLines = [
    'New enquiry from the TorqueLink Media website.',
    '',
    'Name: ' . $name,
    'Email: ' . $email,
    'Company: ' . ($company !== '' ? $company : '-'),
    'Product category: ' . ($category !== '' ? $category : '-'),
    '',
    'Campaign notes:',
    $message !== '' ? $message : '-',
    '',
    'Submitted: ' . $submittedAt,
    'IP address: ' . $ipAddress,
];

$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safeCompany = htmlspecialchars($company !== '' ? $company : '-', ENT_QUOTES, 'UTF-8');
$safeCategory = htmlspecialchars($category !== '' ? $category : '-', ENT_QUOTES, 'UTF-8');
$safeMessage = nl2br(htmlspecialchars($message !== '' ? $message : '-', ENT_QUOTES, 'UTF-8'));
$safeSubmittedAt = htmlspecialchars($submittedAt, ENT_QUOTES, 'UTF-8');
$safeIpAddress = htmlspecialchars($ipAddress, ENT_QUOTES, 'UTF-8');

$htmlBody = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TorqueLink Media Website Enquiry</title>
</head>
<body style="margin:0; padding:0; background:#f3f7fb; color:#12385a; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; background:#f3f7fb; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; max-width:680px; background:#ffffff; border-radius:22px; overflow:hidden; box-shadow:0 20px 60px rgba(18,56,90,0.14);">
                    <tr>
                        <td style="padding:28px 32px; background:#071d36;">
                            <div style="font-size:12px; line-height:1.4; letter-spacing:0.16em; text-transform:uppercase; color:#7fb3ff; font-weight:700;">TorqueLink Media</div>
                            <h1 style="margin:10px 0 0; color:#ffffff; font-size:28px; line-height:1.2; font-weight:800;">New website enquiry</h1>
                            <p style="margin:10px 0 0; color:#c9d8e8; font-size:15px; line-height:1.6;">A potential client submitted the contact form.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 32px 10px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding:0 0 18px; width:50%; vertical-align:top;">
                                        <div style="font-size:12px; color:#6f7f91; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Name</div>
                                        <div style="margin-top:6px; font-size:18px; line-height:1.4; color:#071d36; font-weight:700;">$safeName</div>
                                    </td>
                                    <td style="padding:0 0 18px; width:50%; vertical-align:top;">
                                        <div style="font-size:12px; color:#6f7f91; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Email</div>
                                        <div style="margin-top:6px; font-size:18px; line-height:1.4; color:#071d36; font-weight:700;"><a href="mailto:$safeEmail" style="color:#2167ab; text-decoration:none;">$safeEmail</a></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:0 0 18px; width:50%; vertical-align:top;">
                                        <div style="font-size:12px; color:#6f7f91; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Company</div>
                                        <div style="margin-top:6px; font-size:16px; line-height:1.5; color:#12385a;">$safeCompany</div>
                                    </td>
                                    <td style="padding:0 0 18px; width:50%; vertical-align:top;">
                                        <div style="font-size:12px; color:#6f7f91; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Product category</div>
                                        <div style="margin-top:6px; font-size:16px; line-height:1.5; color:#12385a;">$safeCategory</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 28px;">
                            <div style="background:#eef5fc; border-radius:18px; padding:22px 24px;">
                                <div style="font-size:12px; color:#2167ab; text-transform:uppercase; letter-spacing:0.08em; font-weight:800;">Campaign notes</div>
                                <div style="margin-top:10px; font-size:16px; line-height:1.7; color:#12385a;">$safeMessage</div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 32px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-top:1px solid #d7e3ef;">
                                <tr>
                                    <td style="padding-top:18px; font-size:13px; line-height:1.6; color:#6f7f91;">
                                        Submitted: <strong style="color:#12385a;">$safeSubmittedAt</strong><br>
                                        IP address: <strong style="color:#12385a;">$safeIpAddress</strong>
                                    </td>
                                    <td align="right" style="padding-top:18px;">
                                        <a href="mailto:$safeEmail" style="display:inline-block; background:#2167ab; color:#ffffff; text-decoration:none; border-radius:999px; padding:12px 18px; font-size:14px; font-weight:700;">Reply to enquiry</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

$boundary = 'torquelink-' . bin2hex(random_bytes(16));

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    'From: ' . $fromName . ' <' . $fromEmail . '>',
    'Reply-To: ' . $email,
];

$emailBody = implode("\r\n", [
    '--' . $boundary,
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    '',
    implode("\n", $plainTextLines),
    '--' . $boundary,
    'Content-Type: text/html; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    '',
    $htmlBody,
    '--' . $boundary . '--',
]);

$sent = mail(
    $recipientEmail,
    $subject,
    $emailBody,
    implode("\r\n", $headers)
);

if (!$sent) {
    respond(502, ['message' => 'The enquiry could not be sent. Please email info@torquelinkmedia.com directly.']);
}

respond(200, ['message' => 'Thanks. Your enquiry was sent.']);
