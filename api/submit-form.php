<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

const MAX_ATTACHMENT_SIZE = 8388608;

$forms = [
    'vendor-partnership' => [
        'to' => 'partners@thekcsoft.com',
        'subject' => 'Vendor Partnership Inquiry',
        'required' => ['first-name', 'last-name', 'company', 'email', 'partnership-type', 'message', 'privacy-acknowledgement'],
        'file' => null,
    ],
    'submit-requirement' => [
        'to' => 'vendors@thekcsoft.com',
        'cc' => 'partners@thekcsoft.com',
        'subject' => 'New Staffing Requirement',
        'required' => ['company', 'contact-name', 'email', 'job-title-skill', 'job-description', 'privacy-acknowledgement'],
        'file' => 'attachment',
    ],
    'talent-network' => [
        'to' => 'recruiting@thekcsoft.com',
        'subject' => 'New Talent Network Submission',
        'required' => ['full-name', 'email', 'primary-skill', 'privacy-acknowledgement'],
        'file' => 'resume',
        'file_required' => true,
    ],
    'contact' => [
        'to' => 'info@thekcsoft.com',
        'subject' => 'Website Contact Inquiry',
        'required' => ['name', 'email', 'message', 'privacy-acknowledgement'],
        'file' => null,
    ],
];

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function clean_value(string $value): string
{
    return trim(str_replace(["\r", "\0"], '', $value));
}

function field(string $name): string
{
    return isset($_POST[$name]) ? clean_value((string) $_POST[$name]) : '';
}

function safe_header(string $value): string
{
    return preg_replace('/[^A-Za-z0-9@._+\-\s]/', '', $value) ?? '';
}

function safe_filename(string $name): string
{
    $name = basename($name);
    return preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'attachment';
}

function format_label(string $name): string
{
    return ucwords(str_replace('-', ' ', $name));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

if (field('bot-field') !== '') {
    respond(200, ['success' => true]);
}

$formName = field('form-name');
if (!isset($forms[$formName])) {
    respond(400, ['success' => false, 'message' => 'Unknown form.']);
}

$config = $forms[$formName];
foreach ($config['required'] as $required) {
    if (field($required) === '') {
        respond(422, ['success' => false, 'message' => format_label($required) . ' is required.']);
    }
}

$email = field('email');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, ['success' => false, 'message' => 'A valid email address is required.']);
}

$attachment = null;
$fileField = $config['file'];
if ($fileField && !empty($config['file_required']) && (!isset($_FILES[$fileField]) || !is_array($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE)) {
    respond(422, ['success' => false, 'message' => 'Resume upload is required.']);
}
if ($fileField && isset($_FILES[$fileField]) && is_array($_FILES[$fileField]) && $_FILES[$fileField]['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
        respond(422, ['success' => false, 'message' => 'The uploaded file could not be processed.']);
    }
    if ((int) $_FILES[$fileField]['size'] > MAX_ATTACHMENT_SIZE) {
        respond(422, ['success' => false, 'message' => 'Attachment must be under 8 MB.']);
    }

    $allowedExtensions = $fileField === 'resume' ? ['pdf', 'doc', 'docx'] : ['pdf', 'doc', 'docx', 'txt'];
    $originalName = (string) $_FILES[$fileField]['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        respond(422, ['success' => false, 'message' => 'Unsupported attachment type.']);
    }

    $attachment = [
        'path' => (string) $_FILES[$fileField]['tmp_name'],
        'name' => safe_filename($originalName),
        'type' => mime_content_type((string) $_FILES[$fileField]['tmp_name']) ?: 'application/octet-stream',
    ];
}

$bodyFields = [];
foreach ($_POST as $key => $value) {
    if (in_array($key, ['form-name', 'bot-field', 'privacy-acknowledgement'], true)) {
        continue;
    }
    $bodyFields[] = format_label((string) $key) . ': ' . clean_value((string) $value);
}
if ($attachment) {
    $bodyFields[] = 'Attachment: ' . $attachment['name'];
}

$messageText = implode("\n", $bodyFields);
$fromName = safe_header(field('company') ?: field('name') ?: field('full-name') ?: field('contact-name') ?: 'Website Visitor');
$replyTo = safe_header($email);
$headers = [
    'From: Kairos Covenant Website <info@thekcsoft.com>',
    'Reply-To: ' . $fromName . ' <' . $replyTo . '>',
    'X-Mailer: PHP/' . phpversion(),
];
if (!empty($config['cc'])) {
    $headers[] = 'Cc: ' . $config['cc'];
}

if ($attachment) {
    $boundary = 'kcsoft_' . bin2hex(random_bytes(12));
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

    $fileContent = chunk_split(base64_encode((string) file_get_contents($attachment['path'])));
    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $messageText . "\r\n\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: " . $attachment['type'] . '; name="' . $attachment['name'] . "\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename=\"" . $attachment['name'] . "\"\r\n\r\n";
    $message .= $fileContent . "\r\n";
    $message .= "--{$boundary}--";
} else {
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $message = $messageText;
}

$subject = $config['subject'] . ' - thekcsoft.com';
$sent = mail($config['to'], $subject, $message, implode("\r\n", $headers));
if (!$sent) {
    respond(500, ['success' => false, 'message' => 'Submission could not be delivered.']);
}

respond(200, ['success' => true]);
