<div class="p-3.5 sm:p-6 md:p-8 max-w-[1200px] mx-auto flex flex-col gap-6 sm:gap-8 font-sans">
  
  <!-- Header Section -->
  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end border-b border-slate-200 pb-5 sm:pb-6 gap-4">
    <div>
      <div style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; background-color: #f1f5f9; color: #475569; font-size: 12px; font-weight: 700; margin-bottom: 10px;">
        <i data-lucide="shield-alert" style="width: 14px; height: 14px; color: #6366f1;"></i>
        <span>Restricted Area</span>
      </div>
      <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-slate-900 m-0 tracking-tight">Data Requests Center</h1>
      <p class="m-0 text-slate-500 text-xs sm:text-sm md:text-base font-medium max-w-[600px] leading-relaxed mt-1.5">
        Review, verify, and approve incoming enterprise data access requests. Approved requests will automatically transmit secure data payloads via email.
      </p>
    </div>
  </div>

  <!-- Table Card -->
  <div class="bg-white rounded-2xl sm:rounded-[20px] border border-slate-200 shadow-sm overflow-hidden">
    <!-- Top Card Header -->
    <div class="p-4 sm:p-5 border-b border-slate-100 flex justify-between items-center bg-white">
      <h2 style="font-size: 17px; sm:font-size: 18px; font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 10px;">
        <div style="width: 32px; height: 32px; border-radius: 8px; background: #e0e7ff; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <i data-lucide="inbox" style="width: 18px; height: 18px; color: #4f46e5;"></i>
        </div>
        <span>Pending Access Requests</span>
      </h2>
      <div style="display: flex; align-items: center; gap: 10px;">
        <span id="requests-count-badge" class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 font-mono">--</span>
      </div>
    </div>

    <!-- Dynamic Bulk Actions Bar (Appears when 1+ requests are selected) -->
    <div id="bulk-actions-bar" style="display: none; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 18px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; border-bottom: 2px solid #00bfa5; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.25);">
      <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <span id="bulk-selected-count" style="display: inline-flex; align-items: center; justify-content: center; min-width: 26px; height: 26px; padding: 0 8px; border-radius: 999px; background: #00bfa5; color: #ffffff; font-size: 13px; font-weight: 800;">0</span>
        <span id="bulk-selected-text" style="font-size: 13.5px; font-weight: 700; color: #f8fafc;">0 requests selected</span>
        <button type="button" onclick="deselectAllRequests()" style="background: transparent; border: none; color: #94a3b8; font-size: 13px; font-weight: 600; text-decoration: underline; cursor: pointer; padding: 2px 4px;">Deselect all</button>
      </div>
      <div style="display: flex; align-items: center; gap: 8px;">
        <button type="button" onclick="openBulkDeleteModal()" style="display: inline-flex; align-items: center; gap: 7px; padding: 8px 16px; border-radius: 10px; border: none; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 10px rgba(239, 68, 68, 0.4);">
          <i data-lucide="trash-2" style="width: 15px; height: 15px;"></i>
          <span id="bulk-delete-btn-label">Delete Selected</span>
        </button>
      </div>
    </div>

    <!-- Mobile Subheader & Selection Controls (Hidden: desktop table view is used everywhere) -->
    <div id="mobile-selection-bar" style="display: none !important;">
      <input type="checkbox" id="select-all-mobile" style="display: none;">
      <span id="mobile-selected-counter" style="display: none;"></span>
    </div>

    <!-- Mobile Native Card View (Hidden: desktop table view is used everywhere) -->
    <div id="requests-mobile-cards" style="display: none !important;"></div>
    
    <!-- Desktop Table View (visible on all screens to maintain desktop layout) -->
    <div class="block overflow-x-auto w-full">
      <table style="width: 100%; min-width: 860px; border-collapse: collapse; text-align: left;">
        <thead>
          <tr style="background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
            <th style="padding: 14px 12px 14px 20px; width: 44px; text-align: center;">
              <input type="checkbox" id="select-all-desktop" onchange="toggleSelectAll(this.checked)" style="width: 18px; height: 18px; border-radius: 5px; accent-color: #ef4444; cursor: pointer;" title="Select all requesters to delete">
            </th>
            <th style="padding: 14px 16px; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
              <span>Requester Profile</span>
            </th>
            <th style="padding: 14px 20px; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;">
              <div style="display: flex; align-items: center; gap: 6px;">
                <i data-lucide="calendar-clock" style="width: 14px; height: 14px; color: #00bfa5;"></i>
                <span>Requested Date &amp; Time</span>
              </div>
            </th>
            <th style="padding: 14px 20px; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Context &amp; Verification</th>
            <th style="padding: 14px 20px; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Dataset Scope</th>
            <th style="padding: 14px 20px; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Status</th>
            <th style="padding: 14px 20px; text-align: right; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Administrative Action</th>
          </tr>
        </thead>
        <tbody id="requests-tbody">
          <tr>
            <td colspan="7" style="padding: 60px 40px; text-align: center;">
              <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;">
                <i data-lucide="loader-2" class="lucide-spin" style="width: 32px; height: 32px; color: #00bfa5; animation: spin 1s linear infinite;"></i>
                <span style="color: #64748b; font-size: 15px; font-weight: 500;">Fetching secure requests...</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Premium Confirmation Modal -->
<div id="admin-confirm-modal" style="display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; padding: 16px; background-color: rgba(15, 23, 42, 0.7); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);">
  <div id="admin-confirm-backdrop" onclick="closeConfirmModal()" style="position: absolute; inset: 0;"></div>
  
  <div style="position: relative; z-index: 10; width: 100%; max-width: 440px; background-color: #ffffff; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.4), 0 0 0 1px rgba(226, 232, 240, 0.5); overflow: hidden; font-family: var(--font-sans); color: #0f172a; animation: modal-pop 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
    
    <div style="padding: 32px 32px 24px; text-align: center;">
      <div style="width: 64px; height: 64px; border-radius: 20px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 8px 16px rgba(34, 197, 94, 0.15); transform: rotate(-5deg);">
        <i data-lucide="send" style="width: 28px; height: 28px; color: #16a34a; transform: rotate(5deg); margin-left: -2px;"></i>
      </div>
      <h3 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 12px; letter-spacing: -0.02em;">Authorize Data Release</h3>
      <p style="font-size: 15px; color: #64748b; line-height: 1.6; margin: 0 0 20px;">
        By approving this request, the system will securely compile the requested dataset as a CSV and dispatch it to the user's email.
      </p>

      <div id="confirm-requester-info" style="display: none; margin-bottom: 24px;"></div>
      
      <div style="display: flex; gap: 12px;">
        <button type="button" onclick="closeConfirmModal()" style="flex: 1; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background-color: #ffffff; color: #64748b; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s ease;" onmouseenter="this.style.backgroundColor='#f8fafc'; this.style.borderColor='#cbd5e1';" onmouseleave="this.style.backgroundColor='#ffffff'; this.style.borderColor='#e2e8f0';">
          Cancel
        </button>
        <button id="confirm-approve-btn" style="flex: 1; padding: 12px 16px; border-radius: 12px; border: none; background: linear-gradient(135deg, #00bfa5 0%, #009984 100%); color: white; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(0, 191, 165, 0.3);" onmouseenter="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 16px rgba(0, 191, 165, 0.4)';" onmouseleave="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(0, 191, 165, 0.3)';">
          Confirm Approval
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Premium Delete Confirmation Modal -->
<div id="admin-delete-modal" style="display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; padding: 16px; background-color: rgba(15, 23, 42, 0.7); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);">
  <div id="admin-delete-backdrop" onclick="closeDeleteModal()" style="position: absolute; inset: 0;"></div>
  
  <div style="position: relative; z-index: 10; width: 100%; max-width: 440px; background-color: #ffffff; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.4), 0 0 0 1px rgba(226, 232, 240, 0.5); overflow: hidden; font-family: var(--font-sans); color: #0f172a; animation: modal-pop 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
    
    <div style="padding: 32px 32px 24px; text-align: center;">
      <div style="width: 64px; height: 64px; border-radius: 20px; background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 8px 16px rgba(239, 68, 68, 0.15); transform: rotate(-5deg);">
        <i data-lucide="trash-2" style="width: 28px; height: 28px; color: #ef4444; transform: rotate(5deg);"></i>
      </div>
      <h3 id="delete-modal-title" style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0 0 12px; letter-spacing: -0.02em;">Delete Data Request</h3>
      <p id="delete-modal-desc" style="font-size: 14.5px; color: #64748b; line-height: 1.6; margin: 0 0 28px;">
        Are you sure you want to permanently delete this request? This record will be removed from both the local database and real-time cloud storage.
      </p>
      
      <div style="display: flex; gap: 12px;">
        <button type="button" onclick="closeDeleteModal()" style="flex: 1; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background-color: #ffffff; color: #64748b; font-size: 14.5px; font-weight: 700; cursor: pointer; transition: all 0.2s ease;" onmouseenter="this.style.backgroundColor='#f8fafc'; this.style.borderColor='#cbd5e1';" onmouseleave="this.style.backgroundColor='#ffffff'; this.style.borderColor='#e2e8f0';">
          Cancel
        </button>
        <button id="confirm-delete-btn" type="button" style="flex: 1; padding: 12px 16px; border-radius: 12px; border: none; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; font-size: 14.5px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);" onmouseenter="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 16px rgba(239, 68, 68, 0.4)';" onmouseleave="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(239, 68, 68, 0.3)';">
          Confirm Delete
        </button>
      </div>
    </div>
  </div>
</div>
<!-- Toast Notification Container -->
<div id="admin-toast-container" style="position: fixed; bottom: 24px; right: 24px; z-index: 10000; display: flex; flex-direction: column; gap: 12px; pointer-events: none;"></div>

<script>
function showToast(message, type = 'success') {
    const container = document.getElementById('admin-toast-container');
    const toast = document.createElement('div');
    const isError = type === 'error';
    
    toast.style.cssText = `
        pointer-events: auto;
        background-color: ${isError ? '#fef2f2' : '#f0fdf4'};
        border: 1px solid ${isError ? '#fecaca' : '#bbf7d0'};
        color: ${isError ? '#991b1b' : '#15803d'};
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        gap: 12px;
        font-family: var(--font-sans);
        font-size: 14px;
        font-weight: 600;
        transform: translateX(120%);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
        opacity: 0;
    `;
    
    const iconStr = isError ? 'alert-circle' : 'check-circle';
    const iconColor = isError ? '#ef4444' : '#10b981';
    
    toast.innerHTML = `
        <i data-lucide="${iconStr}" style="width: 20px; height: 20px; color: ${iconColor}; flex-shrink: 0;"></i>
        <div style="line-height: 1.4;">${message}</div>
        <button style="margin-left: auto; background: transparent; border: none; padding: 4px; cursor: pointer; color: inherit; opacity: 0.6; display: flex;" onclick="this.parentElement.style.opacity='0'; setTimeout(()=>this.parentElement.remove(), 300)">
            <i data-lucide="x" style="width: 14px; height: 14px;"></i>
        </button>
    `;
    
    container.appendChild(toast);
    lucide.createIcons();
    
    // Animate in
    requestAnimationFrame(() => {
        toast.style.transform = 'translateX(0)';
        toast.style.opacity = '1';
    });
    
    // Auto dismiss
    setTimeout(() => {
        toast.style.transform = 'translateX(120%)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

var getFbDbUrl = () => (window.ENV_CONFIG && window.ENV_CONFIG.firebaseDatabaseUrl) 
    ? window.ENV_CONFIG.firebaseDatabaseUrl.replace(/\/$/, '') 
    : 'https://smart-air-net-default-rtdb.firebaseio.com';

function loadRequests(isSilent = false) {
    const tbody = document.getElementById('requests-tbody');
    const mobileCards = document.getElementById('requests-mobile-cards');
    const countBadge = document.getElementById('requests-count-badge');
    
    if (!isSilent) {
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="7" style="padding: 60px 40px; text-align: center;"><div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;"><i data-lucide="loader-2" class="lucide-spin" style="width: 32px; height: 32px; color: #00bfa5; animation: spin 1s linear infinite;"></i><span style="color: #64748b; font-size: 15px; font-weight: 500;">Fetching secure requests...</span></div></td></tr>`;
        }
        if (mobileCards) {
            mobileCards.innerHTML = `<div style="padding: 40px 20px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px; color: #64748b;"><i data-lucide="loader-2" class="lucide-spin" style="width: 28px; height: 28px; color: #00bfa5; animation: spin 1s linear infinite;"></i><span style="font-size: 14px; font-weight: 500;">Fetching secure requests...</span></div>`;
        }
        if (window.lucide) lucide.createIcons();
    }
    
    Promise.all([
        fetch('api/get_requests.php').then(res => res.json()).catch(() => []),
        fetch(`${getFbDbUrl()}/requests.json`).then(res => res.json()).catch(() => ({}))
    ])
    .then(([backendDataRaw, firebaseData]) => {
          let firebaseReqs = [];
          if (firebaseData && typeof firebaseData === 'object') {
              firebaseReqs = Object.keys(firebaseData).map(k => ({...firebaseData[k], _fbKey: k}));
          }
          
          firebaseReqs.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
          
          const backendData = Array.isArray(backendDataRaw) ? backendDataRaw : [];
          const allData = [...firebaseReqs, ...backendData];
          const uniqueDataMap = new Map();
          allData.forEach(item => uniqueDataMap.set(item.id, item));
          const finalData = Array.from(uniqueDataMap.values());
          window.__lastLoadedRequests = finalData;
          
          if (countBadge) {
              countBadge.innerText = `${finalData.length} request${finalData.length === 1 ? '' : 's'}`;
          }
          
          if (finalData.length === 0) {
              const emptyHtml = `<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; color: #94a3b8;"><i data-lucide="inbox" style="width: 32px; height: 32px; color: #94a3b8;"></i><span style="font-size: 14px; font-weight: 500; color: #64748b;">No dataset requests found.</span></div>`;
              if (tbody) tbody.innerHTML = `<tr><td colspan="7" style="padding: 60px 40px; text-align: center; border: none;">${emptyHtml}</td></tr>`;
              if (mobileCards) mobileCards.innerHTML = `<div style="padding: 40px 20px; text-align: center;">${emptyHtml}</div>`;
              lucide.createIcons();
              return;
          }
          
          if (tbody) tbody.innerHTML = '';
          if (mobileCards) mobileCards.innerHTML = '';
          
          finalData.forEach(req => {
              const reqId = req.id || req._fbKey || '';
              const fbKey = req._fbKey || '';
              const isApproved = req.status === 'approved';
              const statusBadge = isApproved 
                ? `<span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; font-size: 11.5px; font-weight: 700;"><i data-lucide="check-circle" style="width:13px;height:13px;"></i> Approved</span>`
                : `<span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; background-color: #fffbeb; border: 1px solid #fde68a; color: #b45309; font-size: 11.5px; font-weight: 700;"><i data-lucide="clock" style="width:13px;height:13px;"></i> Pending</span>`;
                
              // Format date & time of when it was requested
              let formattedDate = 'Recently';
              let formattedTime = '';
              const rawTime = req.created_at || req.timestamp || req.date || req.createdAt || '';
              if (rawTime) {
                  let d = new Date(rawTime);
                  if (isNaN(d.getTime()) && typeof rawTime === 'string' && rawTime.includes(' ')) {
                      d = new Date(rawTime.replace(' ', 'T'));
                  }
                  if (!isNaN(d.getTime())) {
                      formattedDate = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'Asia/Kolkata' });
                      formattedTime = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Kolkata' });
                  } else {
                      formattedDate = String(rawTime);
                  }
              }

              // 1. Populate Desktop Table Row
              if (tbody) {
                  const desktopActionBtn = isApproved
                    ? `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                         <span style="display: inline-flex; align-items: center; gap: 5px; color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; white-space: nowrap;"><i data-lucide="mail-check" style="width: 14px; height: 14px;"></i> Dispatched</span>
                         <button type="button" onclick="openDeleteModal('${reqId}', '${fbKey}')" aria-label="Delete Request" style="background-color: #fef2f2; border: 1px solid #fee2e2; color: #ef4444; cursor: pointer; min-width: 38px; min-height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all 0.2s;" onmouseenter="this.style.backgroundColor='#fee2e2'; this.style.borderColor='#fca5a5';" onmouseleave="this.style.backgroundColor='#fef2f2'; this.style.borderColor='#fee2e2';" title="Delete Request"><i data-lucide="trash-2" style="width: 16px; height: 16px;"></i></button>
                       </div>`
                    : `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                         <button type="button" onclick="approveRequest('${reqId}')" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 16px; border-radius: 10px; border: none; background: linear-gradient(135deg, #00bfa5 0%, #00a693 100%); color: white; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(0, 191, 165, 0.25); white-space: nowrap;"><i data-lucide="send" style="width:14px;height:14px;"></i> Authorize</button>
                         <button type="button" onclick="openDeleteModal('${reqId}', '${fbKey}')" aria-label="Delete Request" style="background-color: #fef2f2; border: 1px solid #fee2e2; color: #ef4444; cursor: pointer; min-width: 38px; min-height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all 0.2s;" onmouseenter="this.style.backgroundColor='#fee2e2'; this.style.borderColor='#fca5a5';" onmouseleave="this.style.backgroundColor='#fef2f2'; this.style.borderColor='#fee2e2';" title="Delete Request"><i data-lucide="trash-2" style="width: 16px; height: 16px;"></i></button>
                       </div>`;

                  const tr = document.createElement('tr');
                  tr.id = `request-row-${reqId}`;
                  tr.style.borderBottom = '1px solid #f1f5f9';
                  tr.style.transition = 'all 0.2s ease';
                  tr.style.cursor = 'pointer';
                  tr.onmouseenter = function() { if (!selectedRequests.has(reqId)) this.style.backgroundColor = '#f8fafc'; };
                  tr.onmouseleave = function() { if (!selectedRequests.has(reqId)) this.style.backgroundColor = 'transparent'; };
                  tr.onclick = function(e) {
                      if (e.target.closest('button') || e.target.closest('a') || e.target.closest('.lucide')) return;
                      if (e.target.classList.contains('request-checkbox')) return;
                      const isNowChecked = !selectedRequests.has(reqId);
                      handleItemCheckboxChange(reqId, fbKey, isNowChecked);
                  };
                  
                  tr.innerHTML = `
                    <td style="padding: 16px 12px 16px 20px; text-align: center;">
                      <label style="display: flex; align-items: center; justify-content: center; cursor: pointer; padding: 4px; margin: -4px;">
                        <input type="checkbox" class="request-checkbox" data-id="${reqId}" data-fbkey="${fbKey}" onchange="handleItemCheckboxChange('${reqId}', '${fbKey}', this.checked)" style="width: 18px; height: 18px; border-radius: 5px; accent-color: #ef4444; cursor: pointer;" title="Select requester to delete">
                      </label>
                    </td>
                    <td style="padding: 16px 16px;">
                      <div style="font-weight: 800; color: #0f172a; font-size: 14px; margin-bottom: 2px; display: flex; align-items: center; gap: 8px;">
                        <div style="width: 28px; height: 28px; border-radius: 50%; background: #fee2e2; color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700;">${(req.name || 'U').charAt(0).toUpperCase()}</div>
                        ${escapeHtml(req.name || 'Anonymous')}
                      </div>
                      <div style="color: #64748b; font-size: 12.5px; margin-left: 36px;">${escapeHtml(req.email || '--')}</div>
                    </td>
                    <td style="padding: 16px 20px; white-space: nowrap;">
                      <div style="font-weight: 700; color: #0f172a; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="calendar" style="width: 14px; height: 14px; color: #00bfa5;"></i>
                        <span>${formattedDate}</span>
                      </div>
                      ${formattedTime ? `
                      <div style="color: #64748b; font-size: 11.5px; font-family: var(--font-mono); margin-top: 3px; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="clock" style="width: 12px; height: 12px; color: #94a3b8;"></i>
                        <span>${formattedTime}</span>
                      </div>` : ''}
                    </td>
                    <td style="padding: 16px 20px;">
                      <div style="font-weight: 700; color: #1e293b; font-size: 13.5px; margin-bottom: 3px;">${escapeHtml(req.org || 'N/A')}</div>
                      <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                        <span style="display: inline-block; padding: 2px 7px; border-radius: 6px; background-color: #f1f5f9; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">${escapeHtml(req.industry || 'General')}</span>
                      </div>
                      <div style="margin-top: 8px;">
                        <a href="api/view_request.php?id=${encodeURIComponent(reqId)}" target="_blank" onclick="event.stopPropagation()" style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 8px; background-color: #eff6ff; border: 1.5px solid #bfdbfe; color: #1d4ed8; font-size: 12px; font-weight: 700; text-decoration: none; transition: all 0.15s; box-shadow: 0 1px 3px rgba(37,99,235,0.08);" onmouseenter="this.style.backgroundColor='#dbeafe'; this.style.borderColor='#93c5fd';" onmouseleave="this.style.backgroundColor='#eff6ff'; this.style.borderColor='#bfdbfe';" title="Open application details, full description, and official ID proof in another window">
                          <i data-lucide="external-link" style="width: 14px; height: 14px; color: #2563eb;"></i>
                          <span>View ID Proof &amp; Description</span>
                        </a>
                      </div>
                    </td>
                    <td style="padding: 16px 20px; max-width: 280px;">
                      <div style="font-weight: 600; color: #1e293b; font-size: 13px; line-height: 1.45; margin-bottom: 3px;">${escapeHtml(req.scope || '--')}</div>
                      <div style="color: #94a3b8; font-size: 11.5px; font-family: var(--font-mono);">Req ID: #${(reqId || '').substring(0,8)}</div>
                    </td>
                    <td style="padding: 16px 20px;">${statusBadge}</td>
                    <td style="padding: 16px 20px;">${desktopActionBtn}</td>
                  `;
                  tbody.appendChild(tr);
              }

              // 2. Populate Mobile Native Card
              if (mobileCards) {
                  const card = document.createElement('div');
                  card.id = `request-card-${reqId}`;
                  card.className = "p-4 flex flex-col gap-3.5 bg-white transition-all request-card-item cursor-pointer";
                  card.onclick = function(e) {
                      if (e.target.closest('button') || e.target.closest('a') || e.target.closest('.lucide')) return;
                      if (e.target.classList.contains('request-checkbox')) return;
                      const isNowChecked = !selectedRequests.has(reqId);
                      handleItemCheckboxChange(reqId, fbKey, isNowChecked);
                  };
                  
                  const mobileActionBtn = isApproved
                    ? `<span style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 10px 14px; border-radius: 12px; font-size: 12.5px; font-weight: 700; flex: 1; min-height: 44px;"><i data-lucide="mail-check" style="width: 15px; height: 15px;"></i> Dispatched via Email</span>`
                    : `<button type="button" onclick="approveRequest('${reqId}')" style="display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 10px 14px; border-radius: 12px; border: none; background: linear-gradient(135deg, #00bfa5 0%, #00a693 100%); color: white; font-size: 13px; font-weight: 700; cursor: pointer; flex: 1; min-height: 44px; box-shadow: 0 2px 8px rgba(0, 191, 165, 0.25);"><i data-lucide="send" style="width: 15px; height: 15px;"></i> Authorize &amp; Dispatch</button>`;

                  card.innerHTML = `
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;">
                      <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                        <label style="display: flex; align-items: center; justify-content: center; cursor: pointer; padding: 4px; margin: -4px;">
                          <input type="checkbox" class="request-checkbox" data-id="${reqId}" data-fbkey="${fbKey}" onchange="handleItemCheckboxChange('${reqId}', '${fbKey}', this.checked)" style="width: 18px; height: 18px; border-radius: 5px; accent-color: #ef4444; cursor: pointer;" title="Select requester to delete">
                        </label>
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: #fee2e2; color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; flex-shrink: 0;">
                          ${(req.name || 'U').charAt(0).toUpperCase()}
                        </div>
                        <div style="min-width: 0;">
                          <div style="font-weight: 800; color: #0f172a; font-size: 14.5px; line-height: 1.2; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">${escapeHtml(req.name || 'Anonymous')}</div>
                          <div style="color: #64748b; font-size: 12.5px; margin-top: 2px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">${escapeHtml(req.email || '--')}</div>
                        </div>
                      </div>
                      <div style="flex-shrink: 0;">
                        ${statusBadge}
                      </div>
                    </div>

                    <div style="background-color: #f8fafc; border-radius: 12px; padding: 12px; border: 1px solid #f1f5f9; display: flex; flex-direction: column; gap: 8px;">
                      <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span style="font-weight: 700; color: #1e293b; font-size: 13px;">${escapeHtml(req.org || 'Organization')}</span>
                        <span style="padding: 2px 7px; border-radius: 6px; background-color: #ffffff; border: 1px solid #e2e8f0; color: #475569; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">${escapeHtml(req.industry || 'General')}</span>
                      </div>
                      <div>
                        <a href="api/view_request.php?id=${encodeURIComponent(reqId)}" target="_blank" onclick="event.stopPropagation()" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 8px; background-color: #eff6ff; border: 1.5px solid #bfdbfe; color: #1d4ed8; font-size: 12px; font-weight: 700; text-decoration: none;">
                          <i data-lucide="external-link" style="width: 14px; height: 14px; color: #2563eb;"></i>
                          <span>View ID Proof &amp; Description &rarr;</span>
                        </a>
                      </div>
                      <div style="font-size: 12.5px; color: #475569; line-height: 1.5; font-weight: 500;">
                        ${escapeHtml(req.scope || 'Telemetry Data')}
                      </div>
                      <div style="font-size: 11px; color: #94a3b8; font-family: var(--font-mono); margin-top: 2px;">
                        Req ID: #${(reqId || '').substring(0, 8)}
                      </div>
                    </div>
                      <div style="font-size: 11px; color: #94a3b8; font-family: var(--font-mono); margin-top: 2px;">
                        Req ID: #${(reqId || '').substring(0, 8)}
                      </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 2px;">
                      ${mobileActionBtn}
                      <button type="button" onclick="openDeleteModal('${reqId}', '${fbKey}')" aria-label="Delete Request" style="width: 44px; height: 44px; border-radius: 12px; background-color: #fef2f2; border: 1px solid #fee2e2; color: #ef4444; display: flex; align-items: center; justify-content: center; flex-shrink: 0; cursor: pointer; transition: all 0.15s;" title="Delete Request">
                        <i data-lucide="trash-2" style="width: 17px; height: 17px;"></i>
                      </button>
                    </div>
                  `;
                  mobileCards.appendChild(card);
              }
          });
          updateSelectionUI();
          lucide.createIcons();
      })
      .catch(err => {
          if (isSilent) {
              console.warn("Silent admin sync failed:", err);
              return;
          }
          const errHtml = `<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; color: #ef4444;"><i data-lucide="alert-triangle" style="width: 30px; height: 30px;"></i><span style="font-size: 14px; font-weight: 600;">Failed to synchronize requests. Please try again.</span></div>`;
          if (tbody) tbody.innerHTML = `<tr><td colspan="7" style="padding: 60px 40px; text-align: center;">${errHtml}</td></tr>`;
          if (mobileCards) mobileCards.innerHTML = `<div style="padding: 40px 20px; text-align: center;">${errHtml}</div>`;
          lucide.createIcons();
      });
}

// Multi-Selection State & Handlers
const selectedRequests = new Map();
var pendingDeleteItems = [];

function updateSelectionUI() {
    const count = selectedRequests.size;
    
    // 1. Bulk Actions Bar & Header Button
    const bar = document.getElementById('bulk-actions-bar');
    if (bar) {
        bar.style.display = count > 0 ? 'flex' : 'none';
    }
    const countEl = document.getElementById('bulk-selected-count');
    if (countEl) countEl.innerText = count;
    const textEl = document.getElementById('bulk-selected-text');
    if (textEl) textEl.innerText = `${count} requester${count === 1 ? '' : 's'} selected to delete`;
    const btnLabel = document.getElementById('bulk-delete-btn-label');
    if (btnLabel) btnLabel.innerText = `Delete Selected (${count})`;
    
    // 2. Mobile selection counter
    const mobileCounter = document.getElementById('mobile-selected-counter');
    if (mobileCounter) {
        mobileCounter.innerText = `${count} selected to delete`;
        mobileCounter.style.color = count > 0 ? '#ef4444' : '#64748b';
    }

    // 3. Highlight rows & cards and update checkboxes
    const allCheckboxes = document.querySelectorAll('.request-checkbox');
    const uniqueIds = new Set();
    allCheckboxes.forEach(cb => {
        const id = cb.getAttribute('data-id');
        if (id) uniqueIds.add(id);
        const isChecked = selectedRequests.has(id);
        cb.checked = isChecked;
        
        const row = document.getElementById(`request-row-${id}`);
        if (row) {
            row.style.backgroundColor = isChecked ? '#fff1f2' : '';
            row.style.borderLeft = isChecked ? '3px solid #ef4444' : 'none';
        }
        const card = document.getElementById(`request-card-${id}`);
        if (card) {
            card.style.backgroundColor = isChecked ? '#fff1f2' : '#ffffff';
            card.style.borderLeft = isChecked ? '4px solid #ef4444' : 'none';
        }
    });

    // 4. Select All Checkboxes (Desktop + Mobile)
    const totalUnique = uniqueIds.size;
    const allSelected = totalUnique > 0 && count >= totalUnique;
    const isIndeterminate = count > 0 && !allSelected;

    const selectAllDesktop = document.getElementById('select-all-desktop');
    const selectAllMobile = document.getElementById('select-all-mobile');
    [selectAllDesktop, selectAllMobile].forEach(cb => {
        if (cb) {
            cb.checked = allSelected;
            cb.indeterminate = isIndeterminate;
        }
    });

    if (window.lucide) {
        lucide.createIcons();
    }
}

function handleItemCheckboxChange(id, fbKey, checked) {
    if (checked) {
        selectedRequests.set(id, { id, fbKey });
    } else {
        selectedRequests.delete(id);
    }
    updateSelectionUI();
}

function toggleSelectAll(checked) {
    const allCheckboxes = document.querySelectorAll('.request-checkbox');
    allCheckboxes.forEach(cb => {
        const id = cb.getAttribute('data-id');
        const fbKey = cb.getAttribute('data-fbkey') || '';
        if (id) {
            if (checked) {
                selectedRequests.set(id, { id, fbKey });
            } else {
                selectedRequests.delete(id);
            }
        }
    });
    updateSelectionUI();
}

function deselectAllRequests() {
    selectedRequests.clear();
    updateSelectionUI();
}

function openDeleteModal(id, fbKey = '') {
    pendingDeleteItems = [{ id, fbKey }];
    showDeleteModalForItems(pendingDeleteItems);
}

function openBulkDeleteModal() {
    if (selectedRequests.size === 0) return;
    pendingDeleteItems = Array.from(selectedRequests.values());
    showDeleteModalForItems(pendingDeleteItems);
}

function showDeleteModalForItems(items) {
    const modal = document.getElementById('admin-delete-modal');
    const titleEl = document.getElementById('delete-modal-title');
    const descEl = document.getElementById('delete-modal-desc');
    const confirmBtn = document.getElementById('confirm-delete-btn');
    
    const count = items.length;
    if (count > 1) {
        if (titleEl) titleEl.innerText = `Delete ${count} Requests`;
        if (descEl) descEl.innerHTML = `Are you sure you want to permanently delete the <strong>${count} selected requester profiles & requests</strong>? This record will be removed from both the local database and real-time cloud storage.`;
        if (confirmBtn) confirmBtn.innerHTML = `Delete ${count} Requests`;
    } else {
        if (titleEl) titleEl.innerText = "Delete Data Request";
        if (descEl) descEl.innerHTML = "Are you sure you want to permanently delete this request? This record will be removed from both the local database and real-time cloud storage.";
        if (confirmBtn) confirmBtn.innerHTML = "Confirm Delete";
    }
    
    if (modal) {
        modal.style.display = 'flex';
        lucide.createIcons();
    } else {
        if (confirm(`Are you sure you want to permanently delete ${count} request(s)?`)) {
            executeDelete(items);
        }
    }
}

function closeDeleteModal() {
    const modal = document.getElementById('admin-delete-modal');
    if (modal) modal.style.display = 'none';
    pendingDeleteItems = [];
}

async function executeDelete(items) {
    if (!items || items.length === 0) return;
    
    // 1. Optimistic UI Removal of all selected rows and cards
    items.forEach(item => {
        const targetRow = document.getElementById(`request-row-${item.id}`);
        const targetCard = document.getElementById(`request-card-${item.id}`);
        
        [targetRow, targetCard].forEach(el => {
            if (el) {
                el.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                el.style.opacity = '0';
                el.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    if (el.parentNode) el.remove();
                }, 300);
            }
        });
    });

    // Remove deleted items from selectedRequests map
    items.forEach(item => selectedRequests.delete(item.id));
    updateSelectionUI();

    // After animation, update remaining count
    setTimeout(() => {
        const remainingRows = document.querySelectorAll('#requests-tbody tr').length;
        const remainingCards = document.querySelectorAll('#requests-mobile-cards > div.request-card-item').length;
        const countBadge = document.getElementById('requests-count-badge');
        if (countBadge) {
            const remaining = Math.max(remainingRows, remainingCards);
            countBadge.innerText = `${remaining} request${remaining === 1 ? '' : 's'}`;
        }
        if (remainingRows === 0 && remainingCards === 0) {
            const emptyHtml = `<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; color: #94a3b8;"><i data-lucide="inbox" style="width: 32px; height: 32px; color: #94a3b8;"></i><span style="font-size: 14px; font-weight: 500; color: #64748b;">No dataset requests found.</span></div>`;
            const tbody = document.getElementById('requests-tbody');
            const mobileCards = document.getElementById('requests-mobile-cards');
            if (tbody) tbody.innerHTML = `<tr><td colspan="6" style="padding: 60px 40px; text-align: center; border: none;">${emptyHtml}</td></tr>`;
            if (mobileCards) mobileCards.innerHTML = `<div style="padding: 40px 20px; text-align: center;">${emptyHtml}</div>`;
            lucide.createIcons();
        }
    }, 320);

    try {
        // 2. Call backend PHP delete API (supports both single and array of items)
        const backendDeletePromise = fetch('api/delete_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: items })
        }).then(res => res.json()).catch(err => console.warn('Backend delete warning:', err));

        // 3. Client-side Firebase delete as redundant fallback
        const fbUrl = getFbDbUrl();
        const clientDeletePromises = [];
        items.forEach(item => {
            if (item.fbKey) {
                clientDeletePromises.push(
                    fetch(`${fbUrl}/requests/${item.fbKey}.json`, { method: 'DELETE' }).catch(() => {})
                );
            }
        });
        
        const targetIds = items.map(i => i.id).filter(Boolean);
        const targetKeys = items.map(i => i.fbKey).filter(Boolean);

        clientDeletePromises.push(
            fetch(`${fbUrl}/requests.json`)
                .then(res => res.json())
                .then(data => {
                    if (data && typeof data === 'object') {
                        const delP = [];
                        for (let key in data) {
                            const entry = data[key];
                            const entryId = entry && entry.id ? entry.id : '';
                            if (targetKeys.includes(key) || targetIds.includes(key) || (entryId && targetIds.includes(entryId))) {
                                delP.push(fetch(`${fbUrl}/requests/${key}.json`, { method: 'DELETE' }).catch(() => {}));
                            }
                        }
                        return Promise.all(delP);
                    }
                })
                .catch(() => {})
        );

        await Promise.all([backendDeletePromise, ...clientDeletePromises]);
        const msg = items.length > 1
            ? `${items.length} requests permanently deleted.`
            : "Request permanently deleted.";
        showToast(msg, "success");
    } catch (e) {
        console.error("Error deleting request:", e);
        showToast("Error deleting request.", "error");
    }
}

// Global alias for compatibility
window.deleteRequest = function(id, fbKey) {
    openDeleteModal(id, fbKey);
};

document.getElementById('confirm-delete-btn').addEventListener('click', async function() {
    if (!pendingDeleteItems || pendingDeleteItems.length === 0) return;
    
    const btn = this;
    const origText = btn.innerHTML;
    btn.innerHTML = `<i data-lucide="loader-2" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; animation: spin 1s linear infinite;"></i> <span style="vertical-align: middle; margin-left: 6px;">Deleting...</span>`;
    btn.style.pointerEvents = 'none';
    btn.style.opacity = '0.8';
    lucide.createIcons();
    
    const itemsToDelete = [...pendingDeleteItems];
    closeDeleteModal();
    btn.innerHTML = origText;
    btn.style.pointerEvents = 'auto';
    btn.style.opacity = '1';
    
    await executeDelete(itemsToDelete);
});

var pendingApprovalId = null;

function approveRequest(id) {
    pendingApprovalId = id;
    const targetReq = window.__lastLoadedRequests ? window.__lastLoadedRequests.find(r => (r.id === id || r._fbKey === id)) : null;
    const infoContainer = document.getElementById('confirm-requester-info');
    if (infoContainer) {
        if (targetReq) {
            infoContainer.innerHTML = `
                <div style="background-color: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 14px; text-align: left; font-size: 13px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <strong style="color: #0f172a; font-size: 14px;">${escapeHtml(targetReq.name || 'Requester')}</strong>
                        <span style="font-size: 10.5px; font-weight: 700; background: #e2e8f0; color: #334155; padding: 2px 7px; border-radius: 6px; text-transform: uppercase;">${escapeHtml(targetReq.industry || 'General')}</span>
                    </div>
                    <div style="color: #64748b; font-size: 12px; margin-bottom: 8px;">${escapeHtml(targetReq.email || '--')} &bull; ${escapeHtml(targetReq.org || '--')}</div>
                    
                    ${(targetReq.proof_file || targetReq.proof_name) ? `
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-top: 8px; border-top: 1px dashed #cbd5e1; margin-bottom: 8px;">
                            <span style="font-size: 12px; color: #64748b; font-weight: 600;">Official ID Proof:</span>
                            <a href="api/view_proof.php?file=${encodeURIComponent((targetReq.proof_file || '').split('/').pop() || targetReq.proof_name)}" target="_blank" style="font-weight: 700; color: #1d4ed8; background: #eff6ff; border: 1.5px solid #bfdbfe; padding: 4px 10px; border-radius: 8px; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;"><i data-lucide="file-check" style="width: 14px; height: 14px; color: #2563eb;"></i> View ID Proof &rarr;</a>
                        </div>
                    ` : ''}

                    <div style="margin-top: 10px; padding: 10px 12px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                        <span style="font-size: 12px; color: #64748b; font-weight: 600;">Dossier &amp; Description:</span>
                        <a href="api/view_request.php?id=${encodeURIComponent(targetReq.id || targetReq._fbKey || '')}" target="_blank" style="font-weight: 700; color: #1d4ed8; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                            <i data-lucide="external-link" style="width: 13px; height: 13px;"></i> Open in New Window &rarr;
                        </a>
                    </div>
                </div>
            `;
            infoContainer.style.display = 'block';
        } else {
            infoContainer.style.display = 'none';
        }
    }
    document.getElementById('admin-confirm-modal').style.display = 'flex';
    lucide.createIcons();
}

function closeConfirmModal() {
    document.getElementById('admin-confirm-modal').style.display = 'none';
    pendingApprovalId = null;
}

document.getElementById('confirm-approve-btn').addEventListener('click', function() {
    if(!pendingApprovalId) return;
    
    const btn = this;
    const originalText = btn.innerHTML;
    btn.innerHTML = `<i data-lucide="loader-2" style="width: 18px; height: 18px; display: inline-block; vertical-align: middle; animation: spin 1s linear infinite;"></i> <span style="vertical-align: middle; margin-left: 6px;">Processing...</span>`;
    btn.style.pointerEvents = 'none';
    btn.style.opacity = '0.8';
    lucide.createIcons();
    
    // First get the request from Firebase to extract email for the backend
    fetch(`${getFbDbUrl()}/requests.json`)
      .then(res => res.json())
      .then(fbData => {
          let reqData = null;
          let fbKey = null;
          if (fbData) {
              for (let key in fbData) {
                  if (fbData[key] && (fbData[key].id === pendingApprovalId || key === pendingApprovalId)) {
                      reqData = fbData[key];
                      fbKey = key;
                      break;
                  }
              }
          }
          
          return fetch('api/approve_request.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ 
                  id: pendingApprovalId,
                  email: reqData ? reqData.email : null,
                  name: reqData ? reqData.name : null
              })
          }).then(res => res.json()).then(data => ({data, fbKey}));
      })
      .then(({data, fbKey}) => {
          if(data && data.success) {
              // Update Firebase status only if email successfully sent
              if (fbKey) {
                  fetch(`${getFbDbUrl()}/requests/${fbKey}.json`, {
                      method: 'PATCH',
                      headers: { 'Content-Type': 'application/json' },
                      body: JSON.stringify({ status: 'approved' })
                  });
              }
              
              closeConfirmModal();
              setTimeout(loadRequests, 800);
              const msg = data.message || "Request approved! The dataset (last 24h) has been successfully emailed via SMTP.";
              showToast(msg, msg.includes('failed') ? "error" : "success");
          } else {
              showToast("Error: " + (data ? data.error || data.message || "Failed to send email" : "Unknown Error"), "error");
          }
      })
      .catch(err => {
          showToast("Failed to connect to secure server.", "error");
      })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.style.pointerEvents = 'auto';
        btn.style.opacity = '1';
    });
});

let adminAutoRefreshTimer = null;

function startAdminAutoRefresh() {
    if (adminAutoRefreshTimer) clearInterval(adminAutoRefreshTimer);
    adminAutoRefreshTimer = setInterval(() => {
        const modal = document.getElementById('admin-delete-modal');
        const isModalOpen = modal && modal.style.display !== 'none' && modal.style.display !== '';
        // Only refresh silently if page is visible, modal is closed, and user isn't actively selecting rows
        if (!document.hidden && !isModalOpen && selectedRequests.size === 0) {
            loadRequests(true);
        }
    }, 15000);
}

window.__cleanupCurrentPage = function() {
    if (adminAutoRefreshTimer) {
        clearInterval(adminAutoRefreshTimer);
        adminAutoRefreshTimer = null;
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        loadRequests(false);
        startAdminAutoRefresh();
    });
} else {
    loadRequests(false);
    startAdminAutoRefresh();
}
</script>
