<!-- Auth Modal Backdrop -->
<div id="auth-modal" style="display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; padding: 16px; background-color: rgba(15, 23, 42, 0.65); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);">
  
  <div id="auth-backdrop-close" onclick="closeAuthModal()" style="position: absolute; inset: 0;"></div>

  <!-- Modal Window -->
  <div style="position: relative; z-index: 10; width: 100%; max-width: 440px; background-color: #ffffff; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25), 0 0 0 1px rgba(226, 232, 240, 0.8); overflow: hidden; font-family: var(--font-sans); color: #0f172a; animation: modal-pop 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
    
    <!-- Top Brand Accent Bar -->
    <div style="height: 4px; width: 100%; background: linear-gradient(90deg, #00bfa5 0%, #0284c7 50%, #6366f1 100%);"></div>

    <!-- Close Button -->
    <button onclick="closeAuthModal()" style="position: absolute; top: 16px; right: 16px; width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e2e8f0; background-color: #f8fafc; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s ease;" onmouseenter="this.style.backgroundColor='#f1f5f9'; this.style.color='#0f172a';" onmouseleave="this.style.backgroundColor='#f8fafc'; this.style.color='#64748b';">
      <i data-lucide="x" style="width: 16px; height: 16px;"></i>
    </button>

    <div style="padding: 26px 28px 24px;">
      <!-- Header info -->
      <div style="text-align: center; margin-bottom: 20px;">
        <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; font-size: 11.5px; font-weight: 700; letter-spacing: 0.02em; margin-bottom: 10px;">
          <i data-lucide="sparkles" style="width: 13px; height: 13px; color: #16a34a;"></i>
          <span>Dashboard Access</span>
        </div>
        <h2 id="auth-title" style="font-size: 22px; font-weight: 800; color: #0f172a; letter-spacing: -0.03em; margin: 0 0 6px;">Welcome Back</h2>
        <p id="auth-desc" style="font-size: 13px; color: #64748b; line-height: 1.45; margin: 0;">Sign in to view real-time live sensor telemetry, historical analysis, and predictive forecasts.</p>
      </div>

      <div id="auth-social-block">
        <!-- Google Button (Mocked) -->
        <button id="google-login-btn" onclick="mockLogin()" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; padding: 11px 16px; border-radius: 14px; border: 1.5px solid #cbd5e1; background-color: #ffffff; color: #1e293b; font-size: 13.5px; font-weight: 700; cursor: pointer; transition: all 0.15s ease; box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);" onmouseenter="this.style.backgroundColor='#f8fafc'; this.style.borderColor='#94a3b8';" onmouseleave="this.style.backgroundColor='#ffffff'; this.style.borderColor='#cbd5e1';">
          <svg width="18" height="18" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
          </svg>
          <span>Continue with Google</span>
        </button>

        <!-- Divider -->
        <div style="display: flex; align-items: center; gap: 12px; margin: 18px 0 14px;">
          <div style="flex: 1; height: 1px; background-color: #e2e8f0;"></div>
          <span style="font-size: 11.5px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em;">or with email</span>
          <div style="flex: 1; height: 1px; background-color: #e2e8f0;"></div>
        </div>

        <!-- Mode Switcher Tabs -->
        <div style="display: flex; background-color: #f1f5f9; border-radius: 12px; padding: 3px; margin-bottom: 16px;">
          <button id="tab-register" onclick="switchAuthMode('register')" style="flex: 1; padding: 7px 12px; border-radius: 10px; border: none; font-size: 12.5px; font-weight: 700; cursor: pointer; background-color: transparent; color: #64748b; transition: all 0.15s ease;">Register</button>
          <button id="tab-login" onclick="switchAuthMode('login')" style="flex: 1; padding: 7px 12px; border-radius: 10px; border: none; font-size: 12.5px; font-weight: 700; cursor: pointer; background-color: #ffffff; color: #0f172a; box-shadow: 0 2px 6px rgba(15,23,42,0.06); transition: all 0.15s ease;">Sign In</button>
        </div>
      </div>

      <div id="auth-error-msg" style="display: none; align-items: center; gap: 8px; padding: 9px 12px; border-radius: 10px; background-color: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; font-size: 12px; font-weight: 600; margin-bottom: 14px;">
        <i data-lucide="alert-circle" style="width: 15px; height: 15px; flex-shrink: 0;"></i>
        <span id="auth-error-text"></span>
      </div>

      <!-- Email Form -->
      <form onsubmit="handleAuthSubmit(event)" style="display: flex; flex-direction: column; gap: 12px;">
        
        <div id="field-name" style="display: none;">
          <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 5px;">Full Name (Optional)</label>
          <div style="position: relative;">
            <i data-lucide="user" style="position: absolute; left: 12px; top: 12px; color: #94a3b8; width: 15px; height: 15px;"></i>
            <input type="text" placeholder="e.g. John Doe" style="width: 100%; padding: 9px 12px 9px 36px; border-radius: 11px; border: 1.5px solid #cbd5e1; font-size: 13px; outline: none; box-sizing: border-box; transition: border-color 0.15s ease;" onfocus="this.style.borderColor='#00bfa5';" onblur="this.style.borderColor='#cbd5e1';">
          </div>
        </div>

        <div id="field-email">
          <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 5px;">Email Address</label>
          <div style="position: relative;">
            <i data-lucide="mail" style="position: absolute; left: 12px; top: 12px; color: #94a3b8; width: 15px; height: 15px;"></i>
            <input id="input-email" type="text" required placeholder="name@example.com or admin" style="width: 100%; padding: 9px 12px 9px 36px; border-radius: 11px; border: 1.5px solid #cbd5e1; font-size: 13px; outline: none; box-sizing: border-box; transition: border-color 0.15s ease;" onfocus="this.style.borderColor='#00bfa5';" onblur="this.style.borderColor='#cbd5e1';">
          </div>
        </div>

        <div>
          <label id="label-password" style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 5px;">Password</label>
          <div style="position: relative;">
            <i data-lucide="lock" style="position: absolute; left: 12px; top: 12px; color: #94a3b8; width: 15px; height: 15px;"></i>
            <input id="input-password" type="password" required placeholder="Enter password" style="width: 100%; padding: 9px 36px 9px 36px; border-radius: 11px; border: 1.5px solid #cbd5e1; font-size: 13px; outline: none; box-sizing: border-box; transition: border-color 0.15s ease;" onfocus="this.style.borderColor='#00bfa5';" onblur="this.style.borderColor='#cbd5e1';">
            <button type="button" onclick="togglePasswordVisibility('input-password')" style="position: absolute; right: 10px; top: 10px; background: none; border: none; cursor: pointer; color: #94a3b8; padding: 2px;">
              <i data-lucide="eye" style="width: 15px; height: 15px;"></i>
            </button>
          </div>
        </div>

        <div id="field-confirm" style="display: none;">
          <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 5px;">Confirm Password</label>
          <div style="position: relative;">
            <i data-lucide="lock" style="position: absolute; left: 12px; top: 12px; color: #94a3b8; width: 15px; height: 15px;"></i>
            <input type="password" placeholder="Repeat password" style="width: 100%; padding: 9px 12px 9px 36px; border-radius: 11px; border: 1.5px solid #cbd5e1; font-size: 13px; outline: none; box-sizing: border-box; transition: border-color 0.15s ease;" onfocus="this.style.borderColor='#00bfa5';" onblur="this.style.borderColor='#cbd5e1';">
          </div>
        </div>

        <button type="submit" id="auth-submit-btn" style="margin-top: 6px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 11px 16px; border-radius: 12px; border: none; background-color: #00bfa5; color: #ffffff; font-size: 13.5px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 14px rgba(0, 191, 165, 0.35); transition: all 0.15s ease;">
          <span>Sign In & Unlock Dashboard</span>
        </button>
      </form>

      <div style="margin-top: 16px; text-align: center; font-size: 12.5px; color: #64748b;">
        <span id="auth-bottom-text">Need an account? </span>
        <button type="button" id="auth-bottom-btn" onclick="switchAuthMode('register')" style="background: none; border: none; padding: 0; color: #00bfa5; font-weight: 700; cursor: pointer; text-decoration: underline;">Create Account</button>
      </div>

      <div id="auth-bypass-btn-wrap" style="margin-top: 16px; text-align: center;">
        <button type="button" onclick="switchAuthMode('admin')" style="background: none; border: none; padding: 4px 8px; color: #94a3b8; font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: underline;">Developer Admin Bypass</button>
      </div>

    </div>
  </div>
</div>

<style>
@keyframes modal-pop {
  0% { opacity: 0; transform: scale(0.94) translateY(16px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}
</style>

<script type="module">
  import { initializeApp } from "https://www.gstatic.com/firebasejs/10.9.0/firebase-app.js";
  import { getAuth, signInWithPopup, GoogleAuthProvider, signInWithEmailAndPassword } from "https://www.gstatic.com/firebasejs/10.9.0/firebase-auth.js";

  const app = initializeApp(window.FIREBASE_ENV_CONFIG);
  const auth = getAuth(app);
  const googleProvider = new GoogleAuthProvider();

  window.firebaseLoginWithGoogle = async () => {
      const result = await signInWithPopup(auth, googleProvider);
      return result.user.displayName || result.user.email;
  };

  window.firebaseLoginWithEmail = async (email, pass) => {
      const result = await signInWithEmailAndPassword(auth, email, pass);
      return result.user.displayName || result.user.email;
  };
</script>

<script>
let currentAuthMode = 'login';

function openAuthModal() {
    document.getElementById('auth-modal').style.display = 'flex';
    lucide.createIcons();
}

function closeAuthModal() {
    document.getElementById('auth-modal').style.display = 'none';
}

function switchAuthMode(mode) {
    currentAuthMode = mode;
    document.getElementById('auth-error-msg').style.display = 'none';
    
    const tabReg = document.getElementById('tab-register');
    const tabLog = document.getElementById('tab-login');
    
    if (mode === 'register' || mode === 'login') {
        document.getElementById('auth-social-block').style.display = 'block';
        document.getElementById('field-email').style.display = 'block';
        document.getElementById('input-email').setAttribute('required', 'true');
        document.getElementById('auth-bypass-btn-wrap').style.display = 'block';
        document.getElementById('label-password').innerText = 'Password';
        document.getElementById('input-password').placeholder = mode === 'register' ? 'At least 6 characters' : 'Enter password';
        
        if (mode === 'register') {
            tabReg.style.backgroundColor = '#ffffff';
            tabReg.style.color = '#0f172a';
            tabReg.style.boxShadow = '0 2px 6px rgba(15,23,42,0.06)';
            
            tabLog.style.backgroundColor = 'transparent';
            tabLog.style.color = '#64748b';
            tabLog.style.boxShadow = 'none';
            
            document.getElementById('field-name').style.display = 'block';
            document.getElementById('field-confirm').style.display = 'block';
            document.getElementById('auth-title').innerText = 'Create Your Account';
            document.getElementById('auth-submit-btn').innerHTML = `<i data-lucide="check-circle-2" style="width: 16px; height: 16px;"></i><span>Create Account & Unlock Dashboard</span>`;
            
            document.getElementById('auth-bottom-text').innerText = 'Already registered? ';
            document.getElementById('auth-bottom-btn').innerText = 'Sign In';
            document.getElementById('auth-bottom-btn').onclick = () => switchAuthMode('login');
            document.getElementById('auth-bottom-btn').style.color = '#0284c7';
        } else {
            tabLog.style.backgroundColor = '#ffffff';
            tabLog.style.color = '#0f172a';
            tabLog.style.boxShadow = '0 2px 6px rgba(15,23,42,0.06)';
            
            tabReg.style.backgroundColor = 'transparent';
            tabReg.style.color = '#64748b';
            tabReg.style.boxShadow = 'none';
            
            document.getElementById('field-name').style.display = 'none';
            document.getElementById('field-confirm').style.display = 'none';
            document.getElementById('auth-title').innerText = 'Welcome Back';
            document.getElementById('auth-submit-btn').innerHTML = `<span>Sign In & Unlock Dashboard</span>`;
            
            document.getElementById('auth-bottom-text').innerText = 'Need an account? ';
            document.getElementById('auth-bottom-btn').innerText = 'Create Account';
            document.getElementById('auth-bottom-btn').onclick = () => switchAuthMode('register');
            document.getElementById('auth-bottom-btn').style.color = '#00bfa5';
        }
    } else if (mode === 'admin') {
        document.getElementById('auth-title').innerText = 'Developer Access';
        document.getElementById('auth-desc').innerText = 'Enter the bypass code to unlock local dashboard access without a Firebase account.';
        document.getElementById('auth-social-block').style.display = 'none';
        document.getElementById('field-email').style.display = 'none';
        document.getElementById('input-email').removeAttribute('required');
        document.getElementById('field-name').style.display = 'none';
        document.getElementById('field-confirm').style.display = 'none';
        document.getElementById('auth-bypass-btn-wrap').style.display = 'none';
        
        document.getElementById('label-password').innerText = 'Bypass Code';
        document.getElementById('input-password').placeholder = 'Enter admin code';
        
        document.getElementById('auth-submit-btn').innerHTML = `<i data-lucide="sparkles" style="width: 16px; height: 16px;"></i><span>Unlock Dashboard</span>`;
        
        document.getElementById('auth-bottom-text').innerText = '';
        document.getElementById('auth-bottom-btn').innerText = 'Return to Sign In';
        document.getElementById('auth-bottom-btn').onclick = () => switchAuthMode('login');
        document.getElementById('auth-bottom-btn').style.color = '#64748b';
    }
    
    lucide.createIcons();
}

function togglePasswordVisibility(id) {
    const el = document.getElementById(id);
    if (el.type === 'password') {
        el.type = 'text';
    } else {
        el.type = 'password';
    }
}

function mockLogin() {
    const btn = document.getElementById('google-login-btn');
    const errBlock = document.getElementById('auth-error-msg');
    const errText = document.getElementById('auth-error-text');
    
    errBlock.style.display = 'none';
    btn.innerHTML = `<i data-lucide="loader-2" class="lucide-spin" style="width: 16px; height: 16px; animation: spin 1s linear infinite;"></i> <span>Authenticating with Google...</span>`;
    btn.style.pointerEvents = 'none';
    lucide.createIcons();
    
    if (window.firebaseLoginWithGoogle) {
        window.firebaseLoginWithGoogle()
            .then((name) => {
                window.location.href = '?page=live&auth=true&name=' + encodeURIComponent(name);
            })
            .catch((err) => {
                btn.innerHTML = `<span>Continue with Google</span>`;
                btn.style.pointerEvents = 'auto';
                errText.innerText = err.message || 'Google Auth Failed';
                errBlock.style.display = 'flex';
                console.error("Firebase Auth Error", err);
            });
    } else {
        setTimeout(() => {
            window.location.href = '?page=live&auth=true&name=Google+User';
        }, 1200);
    }
}

function handleAuthSubmit(e) {
    e.preventDefault();
    const email = document.getElementById('input-email').value.trim();
    const pass = document.getElementById('input-password').value;
    const btn = document.getElementById('auth-submit-btn');
    const errBlock = document.getElementById('auth-error-msg');
    const errText = document.getElementById('auth-error-text');
    
    errBlock.style.display = 'none';
    
    if (currentAuthMode === 'admin') {
        if (pass === '1234') {
            btn.innerHTML = `<i data-lucide="loader-2" class="lucide-spin" style="width: 16px; height: 16px; animation: spin 1s linear infinite;"></i> <span>Unlocking Dashboard...</span>`;
            lucide.createIcons();
            setTimeout(() => {
                window.location.href = '?page=live&auth=true&name=Admin+Developer';
            }, 800);
        } else {
            errText.innerText = 'Incorrect Admin Password.';
            errBlock.style.display = 'flex';
        }
        return;
    }
    
    if (email.toLowerCase() === 'admin' && pass === '1234') {
        btn.innerHTML = `<i data-lucide="loader-2" class="lucide-spin" style="width: 16px; height: 16px; animation: spin 1s linear infinite;"></i> <span>Unlocking Dashboard...</span>`;
        lucide.createIcons();
        setTimeout(() => {
            window.location.href = '?page=live&auth=true&name=Admin+Developer';
        }, 800);
        return;
    }
    
    // Real Firebase Email Login
    btn.innerHTML = `<i data-lucide="loader-2" class="lucide-spin" style="width: 16px; height: 16px; animation: spin 1s linear infinite;"></i> <span>Signing in...</span>`;
    lucide.createIcons();
    
    if (window.firebaseLoginWithEmail && currentAuthMode === 'login') {
        window.firebaseLoginWithEmail(email, pass)
            .then((name) => {
                window.location.href = '?page=live&auth=true&name=' + encodeURIComponent(name);
            })
            .catch((err) => {
                btn.innerHTML = `<span>Sign In & Unlock Dashboard</span>`;
                errText.innerText = err.message || 'Login failed.';
                errBlock.style.display = 'flex';
                console.error("Firebase Email Auth Error", err);
            });
    } else {
        setTimeout(() => {
            let name = email.split('@')[0];
            name = name.charAt(0).toUpperCase() + name.slice(1);
            window.location.href = '?page=live&auth=true&name=' + encodeURIComponent(name);
        }, 1200);
    }
}
</script>
