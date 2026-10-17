<?php
/**
 * Asynchronous Background Worker: Send Admin Notification Email
 * Triggered when a new dataset access request is submitted.
 */
date_default_timezone_set('Asia/Kolkata');

$request_id = $argv[1] ?? ($_GET['id'] ?? '');

if (empty($request_id)) {
    exit("No request ID provided.\n");
}

$data_dir = __DIR__ . '/../data';
$requests_file = $data_dir . '/requests.json';
if (!is_dir($data_dir) || !is_writable($data_dir)) {
    $requests_file = sys_get_temp_dir() . '/phpAQI_requests.json';
}

if (!file_exists($requests_file)) {
    exit("Requests file not found.\n");
}

$requests = json_decode(file_get_contents($requests_file), true) ?: [];
$target_req = null;
foreach ($requests as $r) {
    if (($r['id'] ?? '') === $request_id) {
        $target_req = $r;
        break;
    }
}

if (!$target_req) {
    exit("Request ID {$request_id} not found in requests.json\n");
}

// Load SMTP settings from .env
$env_paths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../../.env',
    __DIR__ . '/../../../.env',
    __DIR__ . '/../../php-backend/.env',
    __DIR__ . '/../../backend/.env'
];
$smtp_host = 'smtp.gmail.com';
$smtp_port = 465;
$smtp_user = '';
$smtp_pass = '';
$sender_name = 'Smart WeatherNet Access Portal';

foreach ($env_paths as $env_path) {
    if (file_exists($env_path)) {
        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if ($name === 'SMTP_HOST') $smtp_host = $value;
                if ($name === 'SMTP_PORT') $smtp_port = intval($value);
                if ($name === 'SMTP_USER' && empty($smtp_user)) $smtp_user = $value;
                if ($name === 'SMTP_PASSWORD' && empty($smtp_pass)) $smtp_pass = $value;
                if ($name === 'SENDER_NAME') $sender_name = $value;
            }
        }
        if ($smtp_user && $smtp_pass) break;
    }
}

if (empty($smtp_user)) $smtp_user = getenv('SMTP_USER') ?: '';
if (empty($smtp_pass)) $smtp_pass = getenv('SMTP_PASSWORD') ?: '';
if (empty($smtp_host)) $smtp_host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
$admin_email = getenv('ADMIN_EMAIL') ?: ($smtp_user ?: 'mb507273@gmail.com');

if (empty($smtp_user) || empty($smtp_pass)) {
    exit("SMTP credentials not configured.\n");
}

require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $smtp_host;
    $mail->Port       = 465;
    $mail->Username   = $smtp_user;
    $mail->Password   = $smtp_pass;
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Timeout    = 6;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    $mail->setFrom($smtp_user, 'Smart WeatherNet Access Portal');
    $mail->addAddress($admin_email, 'Administrator');
    if (!empty($target_req['email'])) {
        $mail->addReplyTo($target_req['email'], $target_req['name'] ?? 'Requester');
    }

    // Attach ID Proof document if present
    $proof_rel = $target_req['proof_file'] ?? '';
    $clean_name = $target_req['proof_name'] ?? 'ID_Proof';
    if (!empty($proof_rel)) {
        $proof_abs = __DIR__ . '/../' . ltrim($proof_rel, '/\\');
        if (!file_exists($proof_abs)) {
            $proof_abs = sys_get_temp_dir() . '/phpAQI_proofs/' . basename($proof_rel);
        }
        if (file_exists($proof_abs)) {
            $mail->addAttachment($proof_abs, $clean_name ?: basename($proof_abs));
        }
    }

    $safe_name = htmlspecialchars($target_req['name'] ?? 'Unknown');
    $safe_email = htmlspecialchars($target_req['email'] ?? '');
    $safe_org = htmlspecialchars($target_req['org'] ?? 'Unknown');
    $safe_ind = htmlspecialchars($target_req['industry'] ?? 'Unknown');
    $safe_scope = htmlspecialchars($target_req['scope'] ?? 'Historical Dataset');
    $use_case = $target_req['use_case'] ?? ($target_req['description'] ?? '');
    $safe_desc = nl2br(htmlspecialchars($use_case));
    $proof_attached_note = (!empty($proof_abs) && file_exists($proof_abs)) 
        ? "<strong style='color: #16a34a;'>Attached: " . htmlspecialchars($clean_name) . "</strong>"
        : "<span style='color: #94a3b8;'>No file attached</span>";

    $mail->isHTML(true);
    $mail->Subject = "New Dataset Request: {$target_req['name']} - {$target_req['industry']}";
    $mail->Body = "
    <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 620px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff; color: #0f172a;'>
        <div style='border-bottom: 2px solid #00bfa5; padding-bottom: 16px; margin-bottom: 20px;'>
            <h2 style='color: #0f172a; margin: 0 0 6px; font-size: 22px; font-weight: 800;'>New Dataset Access Request</h2>
            <p style='color: #64748b; font-size: 13.5px; margin: 0; line-height: 1.5;'>A new historical dataset release request has been submitted and is awaiting your authorization.</p>
        </div>
        
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 14px;'>
            <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #64748b; width: 150px; font-weight: 600;'>Requester Name</td><td style='padding: 10px 0; color: #0f172a; font-weight: 700;'>{$safe_name}</td></tr>
            <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #64748b; font-weight: 600;'>Email Address</td><td style='padding: 10px 0; color: #0f172a;'><a href='mailto:{$safe_email}' style='color: #0284c7; text-decoration: none;'>{$safe_email}</a></td></tr>
            <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #64748b; font-weight: 600;'>Organization</td><td style='padding: 10px 0; color: #0f172a;'>{$safe_org}</td></tr>
            <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #64748b; font-weight: 600;'>Industry / Category</td><td style='padding: 10px 0; color: #0f172a;'><span style='background: #f1f5f9; padding: 3px 8px; border-radius: 6px; font-weight: 700; text-transform: uppercase; font-size: 12px;'>{$safe_ind}</span></td></tr>
            <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 10px 0; color: #64748b; font-weight: 600;'>Dataset Scope</td><td style='padding: 10px 0; color: #0f172a; font-weight: 600;'>{$safe_scope}</td></tr>
            <tr><td style='padding: 10px 0; color: #64748b; font-weight: 600;'>Official ID Proof</td><td style='padding: 10px 0;'>{$proof_attached_note}</td></tr>
        </table>

        <div style='background-color: #f8fafc; border-left: 4px solid #00bfa5; padding: 16px; border-radius: 8px; margin-bottom: 24px;'>
            <div style='font-size: 12px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;'>Requester Description &amp; Intended Use Case:</div>
            <div style='font-size: 14px; color: #334155; line-height: 1.6; white-space: pre-line;'>{$safe_desc}</div>
        </div>

        <div style='background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px; text-align: center;'>
            <p style='margin: 0 0 12px; font-size: 13.5px; color: #166534; font-weight: 500;'>
                The requester's ID proof has been attached to this email for verification. You can authorize or delete this request directly in the Admin Panel:
            </p>
            <a href='http://localhost:8001/?page=admin' style='display: inline-block; background: linear-gradient(135deg, #00bfa5 0%, #009984 100%); color: #ffffff; padding: 10px 22px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 13.5px; box-shadow: 0 3px 10px rgba(0, 191, 165, 0.3);'>
                Open Admin Dashboard &rarr;
            </a>
        </div>
        
        <p style='margin-top: 20px; font-size: 11.5px; color: #94a3b8; text-align: center;'>
            Smart WeatherNet Automated Operations &bull; Request ID: {$request_id}
        </p>
    </div>
    ";

    $mail->AltBody = "New Dataset Access Request Received\n\nRequester: {$target_req['name']}\nEmail: {$target_req['email']}\nOrg: {$target_req['org']}\nIndustry: {$target_req['industry']}\nScope: {$target_req['scope']}\n\nIntended Use Case / Description:\n{$use_case}\n\nOfficial ID proof is attached to this email.\n\nReview & authorize at: http://localhost:8001/?page=admin";

    $mail->send();
    echo "SUCCESS: Admin notification email sent for {$request_id}\n";
} catch (\Exception $e) {
    error_log("Async Admin notification email error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
}
