<?php
/**
 * Smart WeatherNet - Dataset Authorization & Approval Handler
 * Supports:
 *  1. GET requests from Gmail one-click approval links
 *  2. POST requests from Admin Portal AJAX calls
 */
date_default_timezone_set('Asia/Kolkata');
session_start();

require_once __DIR__ . '/send_approved_dataset.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Data file resolution
$data_dir = __DIR__ . '/../data';
$requests_file = $data_dir . '/requests.json';
if (!file_exists($requests_file)) {
    $temp_file = sys_get_temp_dir() . '/phpAQI_requests.json';
    if (file_exists($temp_file)) {
        $requests_file = $temp_file;
    }
}

$requests = [];
if (file_exists($requests_file)) {
    $requests = json_decode(file_get_contents($requests_file), true) ?: [];
}

// ─────────────────────────────────────────────────────────────
// Branch A: GET Request (Admin clicking "Accept Request" link in Gmail)
// ─────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $req_id = trim($_GET['id'] ?? '');
    $token  = trim($_GET['token'] ?? '');

    if (empty($req_id)) {
        renderApprovalHtml('Missing Request ID', 'No request identifier was specified in this link.', 'error');
        exit;
    }

    $target_index = -1;
    $target_req   = null;
    foreach ($requests as $idx => $r) {
        if (($r['id'] ?? '') === $req_id) {
            $target_index = $idx;
            $target_req   = $r;
            break;
        }
    }

    if (!$target_req) {
        renderApprovalHtml('Request Not Found', "Request ID <code>" . htmlspecialchars($req_id) . "</code> was not found or has already been removed.", 'error');
        exit;
    }

    // Token verification (prevents unauthorized callers)
    if (!empty($target_req['approve_token'])) {
        $token_valid = (!empty($token) && hash_equals($target_req['approve_token'], $token));
        $is_admin_session = !empty($_SESSION['admin_logged_in']);
        if (!$token_valid && !$is_admin_session) {
            renderApprovalHtml('Access Denied', 'This authorization link is invalid or has expired.', 'error');
            exit;
        }
    }

    $req_name   = htmlspecialchars($target_req['name'] ?? 'Requester');
    $req_email  = htmlspecialchars($target_req['email'] ?? 'Unknown');
    $target_date= htmlspecialchars($target_req['target_date'] ?? date('Y-m-d', strtotime('-1 day')));
    $type       = strtoupper($target_req['telemetry_type'] ?? 'AQI');

    // Case 1: Already Approved
    if (($target_req['status'] ?? '') === 'approved') {
        $approved_at = $target_req['approved_at'] ?? 'Previously';
        renderApprovalHtml(
            'Request Already Approved',
            "This request from <strong>{$req_name}</strong> ({$req_email}) was already authorized on <strong>{$approved_at}</strong>. The {$type} telemetry dataset has already been delivered to their email.",
            'already_done',
            $target_req
        );
        exit;
    }

    // Case 2: Process Approval
    $requests[$target_index]['status'] = 'approved';
    $requests[$target_index]['approved_at'] = date('Y-m-d H:i:s');
    @file_put_contents($requests_file, json_encode($requests, JSON_PRETTY_PRINT));

    // Dispatch the CSV telemetry dataset to the requester
    $dispatch_result = dispatchApprovedDatasetEmail($req_id);

    if ($dispatch_result['success']) {
        renderApprovalHtml(
            'Request Approved & Dataset Dispatched!',
            "The 24-hour calibrated {$type} dataset for <strong>{$target_date}</strong> has been successfully compiled and emailed directly to <strong>{$req_name}</strong> (<a href='mailto:{$req_email}' style='color: #0284c7; text-decoration: none;'>{$req_email}</a>).",
            'success',
            $requests[$target_index]
        );
    } else {
        renderApprovalHtml(
            'Status Approved (Email Alert)',
            "The request status was marked as <strong>Approved</strong>, but there was an issue dispatching the CSV attachment: <br><code style='color: #dc2626;'>" . htmlspecialchars($dispatch_result['error'] ?? 'Unknown dispatch error') . "</code>.<br>You can re-try dispatching from the Admin Portal.",
            'warning',
            $requests[$target_index]
        );
    }
    exit;
}

// ─────────────────────────────────────────────────────────────
// Branch B: POST Request (Admin Portal AJAX Call)
// ─────────────────────────────────────────────────────────────
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Request Data: id is required']);
    exit;
}

$req_id = $input['id'];
$target_index = -1;
$target_req   = null;

foreach ($requests as $idx => $r) {
    if (($r['id'] ?? '') === $req_id) {
        $target_index = $idx;
        $target_req   = $r;
        break;
    }
}

if (!$target_req) {
    http_response_code(404);
    echo json_encode(['error' => 'Request ID not found']);
    exit;
}

// Mark approved
$requests[$target_index]['status'] = 'approved';
$requests[$target_index]['approved_at'] = date('Y-m-d H:i:s');
@file_put_contents($requests_file, json_encode($requests, JSON_PRETTY_PRINT));

// Dispatch dataset
$dispatch_res = dispatchApprovedDatasetEmail($req_id);
$target_email = $target_req['email'] ?? '';

if ($dispatch_res['success']) {
    echo json_encode([
        'success' => true,
        'message' => "Request approved! The dataset (last 24h) has been compiled and emailed via SMTP to {$target_email}."
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => "Request marked approved, but email delivery noted: " . ($dispatch_res['error'] ?? 'check server logs')
    ]);
}
exit;


// ─────────────────────────────────────────────────────────────
// Helper Function: Render Responsive Branded HTML Page
// ─────────────────────────────────────────────────────────────
function renderApprovalHtml($title, $message, $type = 'success', $req_data = null) {
    $icon_color = '#10b981';
    $badge_class = 'badge-success';
    $header_bg = 'linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%)';
    $border_color = '#a7f3d0';

    if ($type === 'error') {
        $icon_color = '#ef4444';
        $badge_class = 'badge-error';
        $header_bg = 'linear-gradient(135deg, #fef2f2 0%, #fff1f2 100%)';
        $border_color = '#fecaca';
    } elseif ($type === 'warning') {
        $icon_color = '#f59e0b';
        $badge_class = 'badge-warning';
        $header_bg = 'linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%)';
        $border_color = '#fde68a';
    } elseif ($type === 'already_done') {
        $icon_color = '#0284c7';
        $badge_class = 'badge-info';
        $header_bg = 'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)';
        $border_color = '#bae6fd';
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> &bull; Smart WeatherNet Operations</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    :root {
      --primary: #00bfa5;
      --bg: #f8fafc;
      --card: #ffffff;
      --text: #0f172a;
      --text-muted: #64748b;
      --border: #e2e8f0;
      --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
      --font-mono: 'JetBrains Mono', monospace;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: var(--font-sans);
      background-color: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
    }
    .wrapper {
      width: 100%;
      max-width: 620px;
    }
    .brand-header {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      margin-bottom: 24px;
    }
    .brand-logo {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      background: linear-gradient(135deg, #00bfa5 0%, #00897b 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(0, 191, 165, 0.25);
    }
    .brand-title {
      font-size: 20px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.02em;
    }
    .brand-sub {
      font-size: 12.5px;
      color: #64748b;
      font-weight: 500;
    }
    .card {
      background: var(--card);
      border-radius: 20px;
      border: 1px solid var(--border);
      box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.08);
      overflow: hidden;
      margin-bottom: 20px;
    }
    .card-banner {
      background: <?= $header_bg ?>;
      border-bottom: 1px solid <?= $border_color ?>;
      padding: 32px 24px;
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
    }
    .icon-circle {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      background: #ffffff;
      border: 2px solid <?= $border_color ?>;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 14px rgba(0,0,0,0.06);
    }
    .status-title {
      font-size: 22px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.02em;
    }
    .status-message {
      font-size: 14.5px;
      line-height: 1.6;
      color: #334155;
      max-width: 480px;
    }
    .card-body {
      padding: 24px;
    }
    .meta-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13.5px;
      margin-bottom: 20px;
    }
    .meta-table tr {
      border-bottom: 1px solid #f1f5f9;
    }
    .meta-table tr:last-child {
      border-bottom: none;
    }
    .meta-table td {
      padding: 10px 0;
    }
    .meta-label {
      color: #64748b;
      font-weight: 600;
      width: 140px;
      text-transform: uppercase;
      font-size: 11.5px;
      letter-spacing: 0.04em;
    }
    .meta-val {
      color: #0f172a;
      font-weight: 700;
      word-break: break-word;
    }
    .actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    .btn {
      flex: 1;
      min-width: 180px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 20px;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 700;
      text-decoration: none;
      transition: all 0.2s ease;
      cursor: pointer;
      border: 1px solid transparent;
    }
    .btn-primary {
      background: linear-gradient(135deg, #00bfa5 0%, #009984 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(0, 191, 165, 0.25);
    }
    .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(0, 191, 165, 0.35);
    }
    .btn-secondary {
      background: #ffffff;
      border-color: #cbd5e1;
      color: #475569;
    }
    .btn-secondary:hover {
      background: #f1f5f9;
      color: #0f172a;
    }
    .footer-note {
      text-align: center;
      font-size: 12px;
      color: #94a3b8;
    }
  </style>
</head>
<body>

<div class="wrapper">
  
  <div class="brand-header">
    <div class="brand-logo">
      <i data-lucide="cloud-sun" style="width: 26px; height: 26px; color: white;"></i>
    </div>
    <div>
      <div class="brand-title">Smart WeatherNet</div>
      <div class="brand-sub">Telemetry Access Administration Portal</div>
    </div>
  </div>

  <div class="card">
    <div class="card-banner">
      <div class="icon-circle">
        <?php if ($type === 'success'): ?>
          <i data-lucide="check-circle" style="width: 34px; height: 34px; color: <?= $icon_color ?>;"></i>
        <?php elseif ($type === 'already_done'): ?>
          <i data-lucide="info" style="width: 34px; height: 34px; color: <?= $icon_color ?>;"></i>
        <?php elseif ($type === 'warning'): ?>
          <i data-lucide="alert-triangle" style="width: 34px; height: 34px; color: <?= $icon_color ?>;"></i>
        <?php else: ?>
          <i data-lucide="x-circle" style="width: 34px; height: 34px; color: <?= $icon_color ?>;"></i>
        <?php endif; ?>
      </div>
      <h1 class="status-title"><?= htmlspecialchars($title) ?></h1>
      <p class="status-message"><?= $message ?></p>
    </div>

    <?php if ($req_data): ?>
      <div class="card-body">
        <div style="font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">
          Requisition Record:
        </div>
        <table class="meta-table">
          <tr>
            <td class="meta-label">Applicant</td>
            <td class="meta-val"><?= htmlspecialchars($req_data['name'] ?? 'N/A') ?></td>
          </tr>
          <tr>
            <td class="meta-label">Recipient Email</td>
            <td class="meta-val"><?= htmlspecialchars($req_data['email'] ?? 'N/A') ?></td>
          </tr>
          <tr>
            <td class="meta-label">Organization</td>
            <td class="meta-val"><?= htmlspecialchars($req_data['org'] ?? 'N/A') ?></td>
          </tr>
          <tr>
            <td class="meta-label">Telemetry Scope</td>
            <td class="meta-val"><?= htmlspecialchars($req_data['scope'] ?? '24-Hour Telemetry') ?> (<?= strtoupper($req_data['telemetry_type'] ?? 'AQI') ?>)</td>
          </tr>
          <tr>
            <td class="meta-label">Status</td>
            <td class="meta-val" style="color: #16a34a;">
              <span style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 800;">
                AUTHORIZED &amp; EMAILED
              </span>
            </td>
          </tr>
        </table>

        <div class="actions">
          <a href="../?page=admin" class="btn btn-primary">
            <i data-lucide="shield-check" style="width: 17px; height: 17px;"></i>
            <span>Open Admin Dashboard</span>
          </a>
          <a href="../" class="btn btn-secondary">
            <i data-lucide="home" style="width: 17px; height: 17px;"></i>
            <span>Return to WeatherNet</span>
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="card-body" style="text-align: center;">
        <div class="actions" style="justify-content: center;">
          <a href="../?page=admin" class="btn btn-primary" style="flex: initial;">
            <i data-lucide="shield-check" style="width: 17px; height: 17px;"></i>
            <span>Open Admin Dashboard</span>
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="footer-note">
    Smart WeatherNet Operations &bull; Shri Madhwa Vadiraja Institute of Technology &amp; Management (SMVITM), Udupi
  </div>

</div>

<script>
  lucide.createIcons();
</script>
</body>
</html>
    <?php
}
