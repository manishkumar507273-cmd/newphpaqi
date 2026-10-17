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

$proof_filename = '';
if (!empty($proof_file)) {
    $proof_filename = basename($proof_file);
} elseif (!empty($proof_name)) {
    $proof_filename = basename($proof_name);
}

$ext = strtolower(pathinfo($proof_filename, PATHINFO_EXTENSION));
$is_image = in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif']);
$is_pdf = ($ext === 'pdf');

$proof_url = !empty($proof_filename) ? "view_proof.php?file=" . urlencode($proof_filename) : "";
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
      <div class="brand-icon">
        <i data-lucide="shield-check" style="width: 22px; height: 22px;"></i>
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
          <img src="<?= $proof_url ?>" alt="Requester Official ID Card Proof" class="proof-img" id="preview-image" onerror="this.style.display='none'; document.getElementById('preview-fallback').style.display='block';">
          <div id="preview-fallback" style="display: none; padding: 30px; color: #64748b;">
            <p>Could not preview image directly. Click <strong>Open Raw File</strong> above to inspect.</p>
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

<script>
  lucide.createIcons();

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
