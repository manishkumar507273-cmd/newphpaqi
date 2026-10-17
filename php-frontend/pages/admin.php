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
    <div class="w-full sm:w-auto flex justify-end">
      <button onclick="loadRequests()" class="w-full sm:w-auto min-h-[44px] justify-center" style="display: flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 12px; background-color: #ffffff; border: 1px solid #e2e8f0; color: #0f172a; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.05);" onmouseenter="this.style.backgroundColor='#f8fafc'; this.style.borderColor='#cbd5e1';" onmouseleave="this.style.backgroundColor='#ffffff'; this.style.borderColor='#e2e8f0';">
        <i data-lucide="refresh-cw" style="width: 16px; height: 16px; color: #64748b;"></i>
        <span>Sync Requests</span>
      </button>
    </div>
  </div>

  <!-- Table Card -->
  <div class="bg-white rounded-2xl sm:rounded-[20px] border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-4 sm:p-5 border-b border-slate-100 flex justify-between items-center bg-white">
      <h2 style="font-size: 17px; sm:font-size: 18px; font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 10px;">
        <div style="width: 32px; height: 32px; border-radius: 8px; background: #e0e7ff; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <i data-lucide="inbox" style="width: 18px; height: 18px; color: #4f46e5;"></i>
        </div>
        Pending Access Requests
      </h2>
    </div>

    <!-- Mobile swipe indicator -->
    <div class="block sm:hidden px-4 py-2 bg-slate-50 border-b border-slate-100 text-[11px] text-slate-500 font-medium flex items-center justify-between">
      <span>👈 Swipe horizontally to view all request columns</span>
      <span class="font-mono text-[10px] bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded font-bold">REQUESTS</span>
    </div>
    
    <div style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%;">
      <table style="width: 100%; min-width: 820px; border-collapse: collapse; text-align: left;">
        <thead>
          <tr style="background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
            <th style="padding: 14px 20px; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Requester Profile</th>
            <th style="padding: 14px 20px; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Context / Industry</th>
            <th style="padding: 14px 20px; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Dataset Scope</th>
            <th style="padding: 14px 20px; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Status</th>
            <th style="padding: 14px 20px; text-align: right; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Administrative Action</th>
          </tr>
        </thead>
        <tbody id="requests-tbody">
          <tr>
            <td colspan="5" style="padding: 60px 40px; text-align: center;">
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
      <p style="font-size: 15px; color: #64748b; line-height: 1.6; margin: 0 0 28px;">
        By approving this request, the system will securely compile the requested dataset as a CSV and dispatch it to the user's corporate email.
      </p>
      
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

var getFbDbUrl = () => (window.ENV_CONFIG && window.ENV_CONFIG.firebaseDatabaseUrl) 
    ? window.ENV_CONFIG.firebaseDatabaseUrl.replace(/\/$/, '') 
    : 'https://smart-air-net-default-rtdb.firebaseio.com';

function loadRequests() {
    const tbody = document.getElementById('requests-tbody');
    tbody.innerHTML = `<tr><td colspan="5" style="padding: 60px 40px; text-align: center;"><div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;"><i data-lucide="loader-2" class="lucide-spin" style="width: 32px; height: 32px; color: #00bfa5; animation: spin 1s linear infinite;"></i><span style="color: #64748b; font-size: 15px; font-weight: 500;">Fetching secure requests...</span></div></td></tr>`;
    lucide.createIcons();
    
    Promise.all([
        fetch('api/get_requests.php').then(res => res.json()).catch(() => []),
        fetch(`${getFbDbUrl()}/requests.json`).then(res => res.json()).catch(() => ({}))
    ])
    .then(([backendDataRaw, firebaseData]) => {
          // Parse Firebase data from object map to array
          let firebaseReqs = [];
          if (firebaseData && typeof firebaseData === 'object') {
              firebaseReqs = Object.keys(firebaseData).map(k => ({...firebaseData[k], _fbKey: k}));
          }
          
          // Sort by newest first
          firebaseReqs.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
          
          // Combine and remove duplicates by ID
          const backendData = Array.isArray(backendDataRaw) ? backendDataRaw : [];
          const allData = [...firebaseReqs, ...backendData];
          const uniqueDataMap = new Map();
          allData.forEach(item => uniqueDataMap.set(item.id, item));
          const finalData = Array.from(uniqueDataMap.values());
          
          if (finalData.length === 0) {
              tbody.innerHTML = `<tr><td colspan="5" style="padding: 60px 40px; text-align: center; border: none;"><div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; color: #94a3b8;"><i data-lucide="server" style="width: 32px; height: 32px; color: #94a3b8;"></i><span style="font-size: 15px; font-weight: 500; color: #64748b;">No dataset requests found.</span></div></td></tr>`;
              lucide.createIcons();
              
              // On mobile, hide the table header if empty to match screenshot exactly
              if (window.innerWidth < 768) {
                  const thead = document.querySelector('thead');
                  if (thead) thead.style.display = 'none';
              }
              return;
          }
          
          // If we have data, ensure the header is visible
          const thead = document.querySelector('thead');
          if (thead) thead.style.display = '';
          
          tbody.innerHTML = '';
          finalData.forEach(req => {
              const isApproved = req.status === 'approved';
              const statusBadge = isApproved 
                ? `<span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; font-size: 12.5px; font-weight: 700;"><i data-lucide="check-circle" style="width:14px;height:14px;"></i> Approved</span>`
                : `<span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; background-color: #fffbeb; border: 1px solid #fde68a; color: #b45309; font-size: 12.5px; font-weight: 700;"><i data-lucide="clock" style="width:14px;height:14px;"></i> Pending Review</span>`;
                
              const actionBtn = isApproved
                ? `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                     <span style="display: inline-flex; align-items: center; gap: 5px; color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; white-space: nowrap;"><i data-lucide="mail-check" style="width: 14px; height: 14px;"></i> Dispatched</span>
                     <button type="button" onclick="deleteRequest('${req.id}')" aria-label="Delete Request" style="background-color: #fef2f2; border: 1px solid #fee2e2; color: #ef4444; cursor: pointer; min-width: 38px; min-height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all 0.2s;" onmouseenter="this.style.backgroundColor='#fee2e2'; this.style.borderColor='#fca5a5';" onmouseleave="this.style.backgroundColor='#fef2f2'; this.style.borderColor='#fee2e2';" title="Delete Request"><i data-lucide="trash-2" style="width: 16px; height: 16px;"></i></button>
                   </div>`
                : `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                     <button type="button" onclick="approveRequest('${req.id}')" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 16px; border-radius: 10px; border: none; background: linear-gradient(135deg, #00bfa5 0%, #00a693 100%); color: white; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(0, 191, 165, 0.25); white-space: nowrap;"><i data-lucide="send" style="width:14px;height:14px;"></i> Authorize</button>
                     <button type="button" onclick="deleteRequest('${req.id}')" aria-label="Delete Request" style="background-color: #fef2f2; border: 1px solid #fee2e2; color: #ef4444; cursor: pointer; min-width: 38px; min-height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all 0.2s;" onmouseenter="this.style.backgroundColor='#fee2e2'; this.style.borderColor='#fca5a5';" onmouseleave="this.style.backgroundColor='#fef2f2'; this.style.borderColor='#fee2e2';" title="Delete Request"><i data-lucide="trash-2" style="width: 16px; height: 16px;"></i></button>
                   </div>`;
                
              const tr = document.createElement('tr');
              tr.style.borderBottom = '1px solid #f1f5f9';
              tr.style.transition = 'background-color 0.2s ease';
              tr.onmouseenter = function() { this.style.backgroundColor = '#f8fafc'; };
              tr.onmouseleave = function() { this.style.backgroundColor = 'transparent'; };
              
              tr.innerHTML = `
                <td style="padding: 20px 24px;">
                  <div style="font-weight: 800; color: #0f172a; font-size: 14px; margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #475569; font-size: 12px;"><i data-lucide="user" style="width: 14px; height: 14px;"></i></div>
                    ${req.name}
                  </div>
                  <div style="color: #64748b; font-size: 13px; margin-left: 36px;">${req.email}</div>
                </td>
                <td style="padding: 20px 24px;">
                  <div style="font-weight: 700; color: #1e293b; font-size: 14px; margin-bottom: 4px;">${req.org}</div>
                  <div style="display: inline-block; padding: 2px 8px; border-radius: 6px; background-color: #f1f5f9; color: #475569; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">${req.industry}</div>
                </td>
                <td style="padding: 20px 24px;">
                  <div style="font-weight: 600; color: #334155; font-size: 13.5px; line-height: 1.5; margin-bottom: 6px; max-width: 260px;">${req.scope}</div>
                  <div style="color: #94a3b8; font-size: 12px; font-family: var(--font-mono);">Req ID: #${req.id.substring(0,8)}</div>
                </td>
                <td style="padding: 20px 24px;">${statusBadge}</td>
                <td style="padding: 20px 24px;">${actionBtn}</td>
              `;
              tbody.appendChild(tr);
          });
          lucide.createIcons();
      })
      .catch(err => {
          tbody.innerHTML = `<tr><td colspan="5" style="padding: 60px 40px; text-align: center;"><div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; color: #ef4444;"><i data-lucide="alert-triangle" style="width: 32px; height: 32px;"></i><span style="font-size: 15px; font-weight: 600;">Failed to synchronize requests. Please try again.</span></div></td></tr>`;
          lucide.createIcons();
      });
}

async function deleteRequest(id) {
    if (!confirm("Are you sure you want to delete this request?")) return;
    
    try {
        // 1. Delete from backend JSON storage
        const backendDeletePromise = fetch('api/delete_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        }).catch(err => console.warn('Backend delete warning:', err));

        // 2. Delete from Firebase Realtime Database
        const firebaseDeletePromise = fetch(`${getFbDbUrl()}/requests.json`)
            .then(res => res.json())
            .then(async data => {
                if (data && typeof data === 'object') {
                    const deletePromises = [];
                    for (let key in data) {
                        if (data[key].id === id) {
                            deletePromises.push(
                                fetch(`${getFbDbUrl()}/requests/${key}.json`, { method: 'DELETE' })
                            );
                        }
                    }
                    await Promise.all(deletePromises);
                }
            })
            .catch(err => console.warn('Firebase delete warning:', err));

        // Wait for both backend and Firebase deletion to finish
        await Promise.all([backendDeletePromise, firebaseDeletePromise]);
        showToast("Request deleted successfully.", "success");
    } catch (e) {
        console.error("Error deleting request:", e);
        showToast("Error deleting request.", "error");
    } finally {
        loadRequests();
    }
}

var pendingApprovalId = null;

function approveRequest(id) {
    pendingApprovalId = id;
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
                  if (fbData[key].id === pendingApprovalId) {
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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        loadRequests();
    });
} else {
    loadRequests();
}
</script>
