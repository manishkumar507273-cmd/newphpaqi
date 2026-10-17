<?php
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['email']) || !isset($input['scope'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Request Data']);
    exit;
}

$industry   = trim($input['industry'] ?? '');
$id_number  = trim($input['id_number'] ?? '');
$proof_name = trim($input['proof_name'] ?? '');
$proof_data = $input['proof_data'] ?? '';

// Mandatory Official Verification: ID Proof document required for Industry categories
if (!empty($industry)) {
    if (empty($proof_data)) {
        http_response_code(422);
        echo json_encode(['error' => 'Official ID card or proof document upload is strictly mandatory.']);
        exit;
    }
}

// Mandatory Minimum 50 letters / characters for Intended Use Case
$use_case = trim($input['use_case'] ?? '');
if (mb_strlen($use_case) < 50) {
    http_response_code(422);
    echo json_encode(['error' => 'Intended use case must contain at least 50 letters/characters describing your requirements.']);
    exit;
}

$data_dir = __DIR__ . '/../data';
$proofs_dir = $data_dir . '/proofs';
$requests_file = $data_dir . '/requests.json';

if (!is_dir($data_dir)) {
    @mkdir($data_dir, 0777, true);
}
if (!is_dir($proofs_dir)) {
    @mkdir($proofs_dir, 0777, true);
}

if (!is_dir($data_dir) || !is_writable($data_dir)) {
    $requests_file = sys_get_temp_dir() . '/phpAQI_requests.json';
    $proofs_dir = sys_get_temp_dir() . '/phpAQI_proofs';
    if (!is_dir($proofs_dir)) {
        @mkdir($proofs_dir, 0777, true);
    }
}

$new_id = uniqid('req_');
$proof_file_path = '';
$clean_name = '';

// Decode and save proof document securely
if (!empty($proof_data) && !empty($proof_name)) {
    if (strpos($proof_data, ';base64,') !== false) {
        list(, $data_part) = explode(';base64,', $proof_data);
    } else {
        $data_part = $proof_data;
    }
    
    $binary = base64_decode($data_part);
    if ($binary !== false) {
        $clean_name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $proof_name);
        $unique_name = $new_id . '_' . $clean_name;
        $save_path = $proofs_dir . '/' . $unique_name;
        if (file_put_contents($save_path, $binary)) {
            $proof_file_path = 'data/proofs/' . $unique_name;
        }
    }
}

$requests = [];
if (file_exists($requests_file)) {
    $requests = json_decode(file_get_contents($requests_file), true) ?: [];
}

$new_request = [
    'id'             => $new_id,
    'name'           => $input['name'] ?? 'Unknown',
    'email'          => $input['email'],
    'org'            => $input['org'] ?? 'Unknown',
    'industry'       => $industry ?: 'Unknown',
    'id_number'      => $id_number,
    'proof_file'     => $proof_file_path,
    'proof_name'     => $clean_name ?: $proof_name,
    'use_case'       => $use_case,
    'description'    => $use_case,
    'scope'          => $input['scope'],
    'target_date'    => $input['target_date'] ?? date('Y-m-d', strtotime('-1 day')),
    'telemetry_type' => $input['telemetry_type'] ?? 'aqi',
    'status'         => 'pending',
    'created_at'     => !empty($input['created_at']) ? $input['created_at'] : date('c'),
    'user_id'        => $_SESSION['user_id'] ?? 'anonymous'
];

array_unshift($requests, $new_request);

$saved = file_put_contents($requests_file, json_encode($requests, JSON_PRETTY_PRINT));

if (!$saved) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save request']);
    exit;
}

// --- Dispatch Admin Notification Email Asynchronously (Zero Requester Wait Time) ---
$admin_notified = false;
$bg_spawned = false;
$script = __DIR__ . '/send_admin_notify.php';

$php_bin = 'C:\\xampp\\php\\php.exe';
if (defined('PHP_BINARY') && !empty(PHP_BINARY) && is_executable(PHP_BINARY)) {
    $php_bin = PHP_BINARY;
} elseif (!file_exists($php_bin)) {
    $php_bin = 'php';
}

if (file_exists($script)) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cmd = sprintf('cmd /c start /B "" "%s" "%s" %s', $php_bin, $script, escapeshellarg($new_id));
        $handle = @popen($cmd, 'r');
        if ($handle) {
            @pclose($handle);
            $bg_spawned = true;
            $admin_notified = true;
        }
    } else {
        $cmd = sprintf('"%s" "%s" %s > /dev/null 2>&1 &', $php_bin, $script, escapeshellarg($new_id));
        @exec($cmd);
        $bg_spawned = true;
        $admin_notified = true;
    }
}

// Fallback to inline fast send if background process spawning was unavailable
if (!$bg_spawned) {
    try {
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

        if (!empty($smtp_user) && !empty($smtp_pass) && !empty($admin_email)) {
            require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
            require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
            require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->Port       = 465;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPAuth   = true;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Timeout    = 5;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            $mail->setFrom($smtp_user, 'Smart WeatherNet Access Portal');
            $mail->addAddress($admin_email, 'Administrator');
            if (!empty($new_request['email'])) {
                $mail->addReplyTo($new_request['email'], $new_request['name']);
            }

            if (!empty($save_path) && file_exists($save_path)) {
                $mail->addAttachment($save_path, $clean_name ?: basename($save_path));
            }

            $safe_name = htmlspecialchars($new_request['name']);
            $safe_email = htmlspecialchars($new_request['email']);
            $safe_org = htmlspecialchars($new_request['org']);
            $safe_ind = htmlspecialchars($new_request['industry']);
            $safe_scope = htmlspecialchars($new_request['scope']);
            $safe_desc = nl2br(htmlspecialchars($use_case));
            $proof_attached_note = (!empty($save_path) && file_exists($save_path)) 
                ? "<strong style='color: #16a34a;'>Attached: " . htmlspecialchars($clean_name ?: basename($save_path)) . "</strong>"
                : "<span style='color: #94a3b8;'>No file attached</span>";

            $mail->isHTML(true);
            $mail->Subject = "New Dataset Request: {$new_request['name']} - {$new_request['industry']}";
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
                    Smart WeatherNet Automated Operations &bull; Request ID: {$new_id}
                </p>
            </div>
            ";

            $mail->AltBody = "New Dataset Access Request Received\n\nRequester: {$new_request['name']}\nEmail: {$new_request['email']}\nOrg: {$new_request['org']}\nIndustry: {$new_request['industry']}\nScope: {$new_request['scope']}\n\nIntended Use Case / Description:\n{$use_case}\n\nOfficial ID proof is attached to this email.\n\nReview & authorize at: http://localhost:8001/?page=admin";

            $mail->send();
            $admin_notified = true;
        }
    } catch (\Exception $e) {
        $admin_notified = false;
        error_log("Admin notification email fallback error: " . $e->getMessage());
    }
}

echo json_encode([
    'success'        => true,
    'request_id'     => $new_request['id'],
    'proof_file'     => $proof_file_path,
    'proof_name'     => $new_request['proof_name'],
    'use_case'       => $use_case,
    'description'    => $use_case,
    'admin_notified' => $admin_notified,
    'id_number'      => $id_number
]);
