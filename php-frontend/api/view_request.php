<?php
date_default_timezone_set('Asia/Kolkata');
// Secure Application & ID Proof Viewer - Opens in a dedicated window
session_start();

$req_id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($req_id)) {
    http_response_code(400);
    die("<h3>Invalid Request</h3><p>No Request ID specified.</p>");
}

$data_dir = __DIR__ . '/../data';
$requests_file = $data_dir . '/requests.json';
if (!is_dir($data_dir) || !is_writable($data_dir)) {
    $requests_file = sys_get_temp_dir() . '/phpAQI_requests.json';
}

$requests = [];
if (file_exists($requests_file)) {
    $requests = json_decode(file_get_contents($requests_file), true) ?: [];
}

$target_request = null;
foreach ($requests as $r) {
    if ((isset($r['id']) && $r['id'] === $req_id) || (isset($r['_fbKey']) && $r['_fbKey'] === $req_id)) {
        $target_request = $r;
        break;
    }
}

// Fallback to Firebase Realtime Database if not found locally
if (!$target_request) {
    $fb_url = "https://smart-air-net-default-rtdb.firebaseio.com/requests.json";
    $ch = @curl_init($fb_url);
    if ($ch) {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $fb_raw = @curl_exec($ch);
        curl_close($ch);
        if ($fb_raw) {
            $fb_data = @json_decode($fb_raw, true);
            if ($fb_data && is_array($fb_data)) {
                foreach ($fb_data as $k => $item) {
                    if ((isset($item['id']) && $item['id'] === $req_id) || $k === $req_id) {
                        $target_request = $item;
                        $target_request['_fbKey'] = $k;
                        break;
                    }
                }
            }
        }
    }
}

if (!$target_request) {
    http_response_code(404);
    die("<h3>Request Not Found</h3><p>The requested dataset access application was not found or has been removed.</p>");
}

$name = htmlspecialchars($target_request['name'] ?? 'Unknown Requester');
$email = htmlspecialchars($target_request['email'] ?? 'N/A');
$org = htmlspecialchars($target_request['org'] ?? 'N/A');
$industry = htmlspecialchars($target_request['industry'] ?? 'General');
$scope = htmlspecialchars($target_request['scope'] ?? 'Telemetry Dataset');
$use_case = trim($target_request['use_case'] ?? $target_request['description'] ?? '');
$status = strtolower($target_request['status'] ?? 'pending');
$created_at_raw = $target_request['created_at'] ?? '';
$created_at_display = 'Recently';
if (!empty($created_at_raw)) {
    try {
        $dt = new DateTime($created_at_raw);
        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
        $created_at_display = $dt->format('M d, Y \a\t h:i A');
    } catch (\Exception $e) {
        $created_at_display = htmlspecialchars($created_at_raw);
    }
}
$proof_file = $target_request['proof_file'] ?? '';
$proof_name = htmlspecialchars($target_request['proof_name'] ?? basename($proof_file) ?: 'Proof Document');
$proof_data = $target_request['proof_data'] ?? '';

$proof_filename = '';
if (!empty($proof_file)) {
    $proof_filename = basename($proof_file);
} elseif (!empty($proof_name)) {
    $proof_filename = basename($proof_name);
}

$ext = strtolower(pathinfo($proof_filename, PATHINFO_EXTENSION));
if (empty($ext) && !empty($proof_data)) {
    if (strpos($proof_data, 'data:image/png') !== false) $ext = 'png';
    elseif (strpos($proof_data, 'data:application/pdf') !== false) $ext = 'pdf';
    else $ext = 'jpg';
}
$is_image = in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif']);
$is_pdf = ($ext === 'pdf');

// Ensure proof directory exists
$proofs_dir = __DIR__ . '/../data/proofs';
if (!is_dir($proofs_dir)) {
    @mkdir($proofs_dir, 0777, true);
}

// Auto-restore file on disk from proof_data if missing
if (!empty($proof_data) && !empty($proof_filename) && !file_exists($proofs_dir . '/' . $proof_filename)) {
    $clean_b64 = (strpos($proof_data, ';base64,') !== false) ? explode(';base64,', $proof_data)[1] : $proof_data;
    $binary = @base64_decode($clean_b64);
    if ($binary !== false) {
        @file_put_contents($proofs_dir . '/' . $proof_filename, $binary);
    }
}

$proof_url = "view_proof.php?id=" . urlencode($req_id) . (!empty($proof_filename) ? "&file=" . urlencode($proof_filename) : "");

// Prioritize direct data URI if available for zero-latency, cross-platform rendering
$preview_src = "";
if (!empty($proof_data)) {
    if (strpos($proof_data, 'data:') === 0) {
        $preview_src = $proof_data;
    } else {
        $mime = ($ext === 'png') ? 'image/png' : (($ext === 'pdf') ? 'application/pdf' : 'image/jpeg');
        $preview_src = "data:{$mime};base64," . $proof_data;
    }
} else {
    $preview_src = $proof_url;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Application Review: <?= $name ?> &bull; Smart WeatherNet</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    :root {
      --primary: #00bfa5;
      --primary-dark: #009984;
      --bg: #f8fafc;
      --card-bg: #ffffff;
      --text: #0f172a;
      --text-muted: #64748b;
      --border: #e2e8f0;
      --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
      --font-mono: 'JetBrains Mono', monospace;
    }
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: var(--font-sans);
      background-color: var(--bg);
      color: var(--text);
      line-height: 1.5;
      padding: 24px 16px 80px;
    }
    .container {
      max-width: 960px;
      margin: 0 auto;
    }
    .top-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .brand-icon {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      background: linear-gradient(135deg, #00bfa5 0%, #00897b 100%);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 10px rgba(0, 191, 165, 0.25);
    }
    .brand-title {
      font-size: 17px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.01em;
    }
    .brand-sub {
      font-size: 12px;
      color: #64748b;
      font-weight: 500;
    }
    .top-actions {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: 10px;
      font-size: 13.5px;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s ease;
      border: 1px solid transparent;
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
    .btn-primary {
      background: linear-gradient(135deg, #00bfa5 0%, #009984 100%);
      color: white;
      box-shadow: 0 3px 10px rgba(0, 191, 165, 0.25);
    }
    .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 5px 14px rgba(0, 191, 165, 0.35);
    }
    .card {
      background: var(--card-bg);
      border-radius: 18px;
      border: 1px solid var(--border);
      box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
      padding: 24px;
      margin-bottom: 20px;
    }
    .card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 18px;
      padding-bottom: 14px;
      border-bottom: 1px solid #f1f5f9;
    }
    .card-title {
      font-size: 16px;
      font-weight: 800;
      color: #0f172a;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
    }
    .badge-pending {
      background: #fffbeb;
      border: 1px solid #fde68a;
      color: #b45309;
    }
    .badge-approved {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: #15803d;
    }
    .badge-industry {
      background: #f1f5f9;
      border: 1px solid #e2e8f0;
      color: #334155;
      text-transform: uppercase;
      font-size: 11px;
      letter-spacing: 0.05em;
    }
    .grid-details {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
    }
    .detail-item {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .detail-label {
      font-size: 12px;
      font-weight: 600;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .detail-val {
      font-size: 14.5px;
      font-weight: 700;
      color: #0f172a;
      word-break: break-word;
    }
    .use-case-box {
      background-color: #f8fafc;
      border: 1.5px solid #e2e8f0;
      border-left: 4px solid var(--primary);
      border-radius: 12px;
      padding: 20px;
      font-size: 15px;
      line-height: 1.65;
      color: #334155;
      white-space: pre-wrap;
      word-break: break-word;
    }
    .proof-container {
      background-color: #f8fafc;
      border: 1.5px solid #e2e8f0;
      border-radius: 14px;
      padding: 16px;
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 14px;
    }
    .proof-img {
      max-width: 100%;
      max-height: 600px;
      border-radius: 10px;
      box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
      border: 1px solid #e2e8f0;
      background: #ffffff;
      object-fit: contain;
      cursor: zoom-in;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      touch-action: pan-y pinch-zoom;
    }
    .proof-img:hover {
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
    }
    .proof-iframe {
      width: 100%;
      height: 650px;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      background: #ffffff;
    }
    .proof-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      width: 100%;
      padding: 10px 14px;
      background: #ffffff;
      border-radius: 10px;
      border: 1px solid #e2e8f0;
      flex-wrap: wrap;
      gap: 10px;
    }
    .fixed-footer {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(12px);
      border-top: 1px solid #e2e8f0;
      padding: 14px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      z-index: 100;
    }

    /* Mobile responsive optimizations */
    @media (max-width: 640px) {
      body {
        padding: 12px 10px 120px;
      }
      .card {
        padding: 16px;
        border-radius: 14px;
        margin-bottom: 14px;
      }
      .brand-title {
        font-size: 15px;
      }
      .brand-sub {
        font-size: 11px;
      }
      .proof-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }
      .proof-meta > div:last-child {
        width: 100%;
        display: flex;
        justify-content: flex-end;
      }
      .fixed-footer {
        padding: 10px 14px;
        flex-direction: column;
        gap: 8px;
      }
      .fixed-footer > div {
        width: 100%;
        justify-content: space-between;
      }
      .fixed-footer .btn-primary {
        flex: 1;
        justify-content: center;
      }
    }

    /* Lightbox Modal */
    #image-lightbox {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.92);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      z-index: 9999;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    #image-lightbox.active {
      display: flex;
    }
    .lightbox-content {
      position: relative;
      max-width: 95vw;
      max-height: 90vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    .lightbox-content img {
      max-width: 100%;
      max-height: 85vh;
      border-radius: 12px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.5);
      object-fit: contain;
      transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      touch-action: pan-x pan-y pinch-zoom;
    }
    .lightbox-bar {
      position: absolute;
      top: -46px;
      right: 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .lightbox-btn {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: rgba(255,255,255,0.2);
      color: white;
      border: 1px solid rgba(255,255,255,0.3);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      backdrop-filter: blur(4px);
    }
    .lightbox-btn:hover {
      background: rgba(255,255,255,0.35);
    }
    @media print {
      .top-actions, .fixed-footer {
        display: none !important;
      }
      body {
        padding: 0;
        background: #ffffff;
      }
      .card {
        box-shadow: none;
        border: 1px solid #cbd5e1;
      }
    }
  </style>
</head>
<body>

<div class="container">
  
  <!-- Top Bar -->
  <div class="top-bar">
    <div class="brand">
      <div class="brand-icon" style="background: transparent; border: none; padding: 0; width: 44px; height: 44px;">
        <img src="../assets/logo-mark.png" alt="Smart WeatherNet" style="width: 100%; height: 100%; object-fit: contain;">
      </div>
      <div>
        <div class="brand-title">Smart WeatherNet Access Portal</div>
        <div class="brand-sub">Requester Verification &amp; Dataset Access Dossier</div>
      </div>
    </div>
    
    <div class="top-actions">
      <button type="button" onclick="window.close()" title="Close Window" aria-label="Close" style="width: 38px; height: 38px; border-radius: 50%; background: #ffffff; border: 1.5px solid #cbd5e1; color: #475569; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 1px 4px rgba(0,0,0,0.05);" onmouseenter="this.style.backgroundColor='#fee2e2'; this.style.borderColor='#fca5a5'; this.style.color='#ef4444';" onmouseleave="this.style.backgroundColor='#ffffff'; this.style.borderColor='#cbd5e1'; this.style.color='#475569';">
        <i data-lucide="x" style="width: 20px; height: 20px;"></i>
      </button>
    </div>
  </div>

  <!-- Requester Profile Card -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <i data-lucide="user-check" style="width: 20px; height: 20px; color: var(--primary);"></i>
        <span>Requester Profile</span>
      </div>
      <div>
        <?php if ($status === 'approved'): ?>
          <span class="badge badge-approved"><i data-lucide="check-circle" style="width: 14px; height: 14px;"></i> Approved &amp; Dispatched</span>
        <?php else: ?>
          <span class="badge badge-pending"><i data-lucide="clock" style="width: 14px; height: 14px;"></i> Pending Review</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid-details">
      <div class="detail-item">
        <span class="detail-label">Full Name</span>
        <span class="detail-val"><?= $name ?></span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Email Address</span>
        <span class="detail-val"><a href="mailto:<?= $email ?>" style="color: #0284c7; text-decoration: none;"><?= $email ?></a></span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Organization</span>
        <span class="detail-val"><?= $org ?></span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Industry / Role</span>
        <span class="detail-val"><span class="badge badge-industry"><?= $industry ?></span></span>
      </div>
      <div class="detail-item" style="grid-column: 1 / -1;">
        <span class="detail-label">Requested Dataset Scope</span>
        <span class="detail-val" style="color: #0f172a; font-weight: 600;"><?= $scope ?></span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Request ID</span>
        <span class="detail-val" style="font-family: var(--font-mono); font-size: 13px; color: #64748b;">#<?= htmlspecialchars($req_id) ?></span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Submitted On</span>
        <span class="detail-val" style="font-size: 13.5px;"><?= $created_at_display ?></span>
      </div>
    </div>
  </div>

  <!-- Intended Use Case & Description Section -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <i data-lucide="file-text" style="width: 20px; height: 20px; color: var(--primary);"></i>
        <span>Intended Use Case &amp; Description</span>
      </div>
    </div>

    <p style="font-size: 13px; color: #64748b; margin-bottom: 12px;">
      The applicant provided the following operational or academic statement outlining their requirement for this dataset:
    </p>

    <div class="use-case-box"><?= nl2br(htmlspecialchars($use_case ?: 'No description was recorded for this request.')) ?></div>
  </div>

  <!-- Official ID Proof Document Section -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <i data-lucide="badge-check" style="width: 20px; height: 20px; color: #2563eb;"></i>
        <span>Official Identification Proof Document</span>
      </div>
      <?php if (!empty($proof_filename)): ?>
        <span class="badge" style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8;">
          <i data-lucide="file-check" style="width: 13px; height: 13px;"></i> Verified Upload
        </span>
      <?php else: ?>
        <span class="badge" style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;">
          No File Uploaded
        </span>
      <?php endif; ?>
    </div>

    <?php if (!empty($proof_filename) && !empty($proof_url)): ?>
      <div class="proof-container">
        
        <div class="proof-meta">
          <div style="display: flex; align-items: center; gap: 8px;">
            <i data-lucide="<?= $is_pdf ? 'file-text' : 'image' ?>" style="width: 18px; height: 18px; color: #2563eb;"></i>
            <span style="font-weight: 700; font-size: 14px; color: #1e293b;"><?= $proof_name ?></span>
          </div>
          <div style="display: flex; gap: 8px;">
            <a href="<?= $proof_url ?>" target="_blank" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">
              <i data-lucide="external-link" style="width: 13px; height: 13px;"></i>
              <span>Open Raw File</span>
            </a>
            <a href="<?= $proof_url ?>" download="<?= $proof_name ?>" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">
              <i data-lucide="download" style="width: 13px; height: 13px;"></i>
              <span>Download</span>
            </a>
          </div>
        </div>

        <?php if ($is_image): ?>
          <div style="position: relative; width: 100%; display: flex; flex-direction: column; align-items: center;">
            <div style="margin-bottom: 8px; font-size: 12px; color: #64748b; display: flex; align-items: center; gap: 6px;">
              <i data-lucide="zoom-in" style="width: 14px; height: 14px; color: #0284c7;"></i>
              <span>Tap photo to zoom &amp; inspect document</span>
            </div>
            <img src="<?= $preview_src ?>" alt="Requester Official ID Card Proof" class="proof-img" id="preview-image" onclick="openLightbox(this.src)" onerror="handlePreviewError(this)">
            <div id="preview-fallback" style="display: none; padding: 24px 16px; text-align: center; color: #64748b;">
              <i data-lucide="info" style="width: 28px; height: 28px; color: #0284c7; margin-bottom: 6px;"></i>
              <p style="font-weight: 700; color: #1e293b; font-size: 14px;">Official Proof Attached with Notification Email</p>
              <p style="font-size: 12.5px; margin-top: 4px;">Click <strong>Open Raw File</strong> above or download to inspect the document.</p>
            </div>
          </div>
        <?php elseif ($is_pdf): ?>
          <iframe src="<?= $proof_url ?>" class="proof-iframe" title="PDF ID Proof Document"></iframe>
        <?php else: ?>
          <div style="padding: 40px; text-align: center; color: #64748b;">
            <i data-lucide="file" style="width: 48px; height: 48px; color: #94a3b8; margin-bottom: 12px;"></i>
            <p style="font-size: 15px; font-weight: 600; color: #1e293b;">Binary Proof Document (<?= strtoupper($ext) ?>)</p>
            <p style="font-size: 13px; margin-top: 6px;">Click the button below to download and view this document locally.</p>
            <a href="<?= $proof_url ?>" download class="btn btn-primary" style="margin-top: 14px;">Download Document</a>
          </div>
        <?php endif; ?>

      </div>
    <?php else: ?>
      <div style="padding: 40px 20px; text-align: center; color: #94a3b8;">
        <i data-lucide="alert-circle" style="width: 36px; height: 36px; color: #cbd5e1; margin-bottom: 8px;"></i>
        <p style="font-size: 14.5px; font-weight: 600; color: #64748b;">No ID proof document was attached with this request.</p>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- Fixed Action Bar at Bottom -->
<div class="fixed-footer">
  <div style="display: flex; align-items: center; gap: 8px;">
    <span style="font-size: 13px; color: #64748b;">Request for:</span>
    <strong style="color: #0f172a; font-size: 13.5px;"><?= $name ?></strong>
    <span style="font-size: 12px; color: #94a3b8;">(<?= $email ?>)</span>
  </div>

  <div style="display: flex; align-items: center; gap: 10px;">
    <?php if ($status !== 'approved'): ?>
      <button type="button" id="authorize-btn" class="btn btn-primary" onclick="authorizeFromWindow('<?= htmlspecialchars($req_id) ?>', '<?= htmlspecialchars($email) ?>', '<?= htmlspecialchars($name) ?>')">
        <i data-lucide="send" style="width: 15px; height: 15px;"></i>
        <span>Authorize &amp; Dispatch Dataset</span>
      </button>
    <?php else: ?>
      <span class="btn btn-secondary" style="background: #f0fdf4; border-color: #bbf7d0; color: #15803d; cursor: default;">
        <i data-lucide="mail-check" style="width: 15px; height: 15px;"></i>
        <span>Dataset Dispatched</span>
      </span>
    <?php endif; ?>
    <button type="button" onclick="window.close()" title="Close Window" aria-label="Close" style="width: 38px; height: 38px; border-radius: 50%; background: #ffffff; border: 1.5px solid #cbd5e1; color: #475569; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 1px 4px rgba(0,0,0,0.05);" onmouseenter="this.style.backgroundColor='#fee2e2'; this.style.borderColor='#fca5a5'; this.style.color='#ef4444';" onmouseleave="this.style.backgroundColor='#ffffff'; this.style.borderColor='#cbd5e1'; this.style.color='#475569';">
      <i data-lucide="x" style="width: 20px; height: 20px;"></i>
    </button>
  </div>
</div>

<!-- Lightbox Zoom Modal for Mobile & Desktop -->
<div id="image-lightbox" onclick="closeLightbox(event)">
  <div class="lightbox-content" onclick="event.stopPropagation()">
    <div class="lightbox-bar">
      <button type="button" class="lightbox-btn" onclick="zoomLightbox(0.25)" title="Zoom In" aria-label="Zoom In">
        <i data-lucide="zoom-in" style="width: 18px; height: 18px;"></i>
      </button>
      <button type="button" class="lightbox-btn" onclick="zoomLightbox(-0.25)" title="Zoom Out" aria-label="Zoom Out">
        <i data-lucide="zoom-out" style="width: 18px; height: 18px;"></i>
      </button>
      <button type="button" class="lightbox-btn" onclick="resetLightboxZoom()" title="Reset Size" aria-label="Reset Size">
        <i data-lucide="rotate-ccw" style="width: 16px; height: 16px;"></i>
      </button>
      <button type="button" class="lightbox-btn" onclick="closeLightbox()" title="Close" aria-label="Close" style="background: rgba(239, 68, 68, 0.7); border-color: rgba(239, 68, 68, 0.8);">
        <i data-lucide="x" style="width: 18px; height: 18px;"></i>
      </button>
    </div>
    <img id="lightbox-img" src="" alt="Enlarged ID Proof Document">
  </div>
</div>

<script>
  lucide.createIcons();

  let currentZoom = 1;
  function openLightbox(src) {
    const lb = document.getElementById('image-lightbox');
    const img = document.getElementById('lightbox-img');
    if (!lb || !img) return;
    img.src = src;
    currentZoom = 1;
    img.style.transform = `scale(${currentZoom})`;
    lb.classList.add('active');
    document.body.style.overflow = 'hidden';
    lucide.createIcons();
  }

  function closeLightbox() {
    const lb = document.getElementById('image-lightbox');
    if (lb) lb.classList.remove('active');
    document.body.style.overflow = '';
  }

  function zoomLightbox(delta) {
    const img = document.getElementById('lightbox-img');
    if (!img) return;
    currentZoom = Math.max(0.6, Math.min(3.5, currentZoom + delta));
    img.style.transform = `scale(${currentZoom})`;
  }

  function resetLightboxZoom() {
    const img = document.getElementById('lightbox-img');
    if (!img) return;
    currentZoom = 1;
    img.style.transform = `scale(1)`;
  }

  function handlePreviewError(imgEl) {
    if (!imgEl.getAttribute('data-retried')) {
      imgEl.setAttribute('data-retried', '1');
      imgEl.src = '<?= $proof_url ?>';
      return;
    }
    imgEl.style.display = 'none';
    const fb = document.getElementById('preview-fallback');
    if (fb) fb.style.display = 'block';
  }

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeLightbox();
  });

  async function authorizeFromWindow(id, email, name) {
    const btn = document.getElementById('authorize-btn');
    if (!btn) return;

    if (!confirm(`Are you sure you want to authorize and email the requested dataset to ${name} (${email})?`)) {
      return;
    }

    const orig = btn.innerHTML;
    btn.innerHTML = `<i data-lucide="loader-2" style="width: 16px; height: 16px; animation: spin 1s linear infinite;"></i> <span>Dispatching Dataset...</span>`;
    btn.style.pointerEvents = 'none';
    lucide.createIcons();

    try {
      const res = await fetch('approve_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, email: email, name: name })
      });
      const data = await res.json();

      if (data.success) {
        btn.innerHTML = `<i data-lucide="check-circle" style="width: 16px; height: 16px;"></i> <span>Authorized &amp; Dispatched!</span>`;
        btn.style.background = '#15803d';
        btn.style.borderColor = '#15803d';
        lucide.createIcons();
        alert(data.message || "Request successfully authorized and dataset emailed!");
        if (window.opener && typeof window.opener.loadRequests === 'function') {
          window.opener.loadRequests(true);
        }
      } else {
        alert("Error: " + (data.error || "Failed to authorize request."));
        btn.innerHTML = orig;
        btn.style.pointerEvents = 'auto';
        lucide.createIcons();
      }
    } catch (e) {
      alert("Network error while connecting to authorization server.");
      btn.innerHTML = orig;
      btn.style.pointerEvents = 'auto';
      lucide.createIcons();
    }
  }
</script>

</body>
</html>
