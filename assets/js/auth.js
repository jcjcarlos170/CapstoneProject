// ================================================================
//  OPTICANA — auth.js
//  Login, register, logout — backed by PHP API endpoints.
//  API base: /api/auth/  (relative, works with any sub-path)
// ================================================================

// ── Login ────────────────────────────────────────────────────────
function _showLoginError(msg) {
  const errEl  = document.getElementById('login-error')
  const errMsg = document.getElementById('login-error-msg')
  errMsg.textContent  = msg
  errEl.style.display = 'flex'
}

async function handleLogin() {
  const email    = document.getElementById('login-email').value.trim()
  const pass     = document.getElementById('login-password').value
  const remember = document.getElementById('login-remember')?.checked || false
  document.getElementById('login-error').style.display = 'none'

  if (!email || !pass) {
    _showLoginError(
      !email && !pass ? 'Please fill in your email and password.'
    : !email          ? 'Please enter your email address.'
    :                   'Please enter your password.'
    )
    return
  }

  const btn = document.getElementById('login-btn')
  if (btn) { btn.disabled = true; btn.textContent = 'Signing in…'; btn.style.opacity = '0.8' }

  try {
    const res  = await fetch('api/auth/login.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ email, password: pass, remember }),
    })
    const data = await res.json()

    if (!data.success) {
      if (data.needsVerification) {
        showEmailVerify(data.email)
        if (btn) { btn.disabled = false; btn.textContent = 'Sign In'; btn.style.opacity = '1' }
        return
      }
      _showLoginError(data.message || 'The email or password you entered is incorrect. Please try again.')
      _shakeCard()
      document.getElementById('login-password').value = ''
      if (btn) { btn.disabled = false; btn.textContent = 'Sign In'; btn.style.opacity = '1' }
      return
    }

    // Remember (or forget) the email for next time, based on the checkbox —
    // separate from the session-length extension login.php already applies.
    try {
      if (remember) localStorage.setItem('opticana_remembered_email', email)
      else localStorage.removeItem('opticana_remembered_email')
    } catch (_) { /* localStorage unavailable (private mode, etc.) — non-critical */ }

    _bootAfterAuth(data.role, data.user)
  } catch (_) {
    // Real network/server failure — surface it instead of silently
    // logging in against mock data (which would hide a broken backend).
    _showLoginError('Unable to reach the server. Please check your connection and try again.')
    _shakeCard()
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = 'Sign In'; btn.style.opacity = '1' }
  }
}

// ── Logout ───────────────────────────────────────────────────────
async function logout() {
  try { await fetch('api/auth/logout.php', { method: 'POST' }) } catch (_) {}

  if (window.closeModal) window.closeModal()

  window.state.role            = null
  window.state.user            = null
  window.state.page            = null
  window.state.params          = {}
  window.state.filter          = 'all'
  window.state.selectedRole    = 'admin'
  window.state.sidebarCollapsed = false

  document.getElementById('app-shell').style.display   = 'none'
  document.getElementById('login-screen').style.display = 'flex'
  document.getElementById('login-email').value          = ''
  document.getElementById('login-password').value       = ''
  document.getElementById('login-error').style.display  = 'none'
  const rememberEl = document.getElementById('login-remember')
  if (rememberEl) rememberEl.checked = false
  _prefillRememberedEmail()
}

// ── Register ─────────────────────────────────────────────────────
async function handleRegister() {
  const first   = document.getElementById('reg-first').value.trim()
  const last    = document.getElementById('reg-last').value.trim()
  const dob     = document.getElementById('reg-dob').value
  const gender  = document.getElementById('reg-gender').value
  const address = document.getElementById('reg-address').value.trim()
  const contact = document.getElementById('reg-contact').value.trim()
  const blood   = document.getElementById('reg-blood').value
  const email   = document.getElementById('reg-email').value.trim()
  const pass    = document.getElementById('reg-password').value
  const confirm = document.getElementById('reg-confirm').value

  const errEl  = document.getElementById('reg-error')
  const errMsg = document.getElementById('reg-error-msg')
  errEl.style.display = 'none'

  const required = [first, last, dob, gender, address, contact, email, pass, confirm]
  if (required.some(v => !v)) {
    errMsg.textContent = 'Please complete all required fields before proceeding.'
    errEl.style.display = 'flex'; return
  }
  if (!document.getElementById('reg-terms-agree')?.checked) {
    errMsg.textContent = 'Please read and agree to the Terms & Conditions and Data Privacy Act before registering.'
    errEl.style.display = 'flex'; return
  }
  if (pass !== confirm) {
    errMsg.textContent = 'The passwords you entered do not match. Please re-enter your password.'
    errEl.style.display = 'flex'; return
  }
  if (pass.length < 8) {
    errMsg.textContent = 'Password must be at least 8 characters.'
    errEl.style.display = 'flex'; return
  }

  const regBtn = document.getElementById('reg-submit-btn')
  if (regBtn) { regBtn.disabled = true; regBtn.textContent = 'Creating account…' }

  try {
    const res  = await fetch('api/auth/register.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ firstName: first, lastName: last, dob, gender, address, contact, bloodType: blood, email, password: pass }),
    })
    const data = await res.json()

    if (!data.success) {
      errMsg.textContent  = data.message || 'Registration failed. Please try again.'
      errEl.style.display = 'flex'
      return
    }

    showEmailVerify(data.email, true)
  } catch (_) {
    errMsg.textContent  = 'Network error. Please check your connection and try again.'
    errEl.style.display = 'flex'
  } finally {
    if (regBtn) { regBtn.disabled = false; regBtn.textContent = 'Create Account' }
  }
}

// ── Terms & Conditions / Data Privacy Act modal ────────────────────
// The "I Agree" checkbox on the register form starts disabled — it can
// only be checked by reading the terms here and scrolling to the bottom,
// which is how this enforces an actual read-through instead of a
// rubber-stamp checkbox.
const TERMS_AND_PRIVACY_HTML = `
  <h4>1. Acceptance of Terms</h4>
  <p>By creating a patient account with Cana Optical Clinic ("the Clinic"), you agree to be bound by these Terms &amp; Conditions and the Data Privacy Notice below. If you do not agree, please do not proceed with registration.</p>

  <h4>2. Account Registration</h4>
  <p>You must provide accurate, current, and complete information during registration. You are responsible for keeping your password confidential and for all activity under your account. Notify the Clinic immediately if you suspect unauthorized use.</p>

  <h4>3. Your Patient QR Code</h4>
  <p>Upon registration, a unique QR code is generated and linked to your patient record. Present this QR code at the clinic for fast, accurate check-in. Do not share it with anyone else — it provides access to your health information.</p>

  <h4>4. Appointments</h4>
  <p>Appointment requests submitted through this system are subject to confirmation by clinic staff based on doctor availability. The Clinic reserves the right to reschedule or decline requests when necessary.</p>

  <h4>5. Use of the Platform</h4>
  <p>You agree to use this system only for legitimate healthcare purposes related to your own care. Misuse — including attempting to access another patient's records or interfering with the system's normal operation — may result in account suspension.</p>

  <h4>6. Data Privacy Act Notice (Republic Act No. 10173)</h4>
  <p>In compliance with the Data Privacy Act of 2012 (RA 10173) and its Implementing Rules and Regulations, the Clinic collects the personal and sensitive personal information you provide during registration (e.g. name, date of birth, address, contact details) and through your subsequent care (e.g. examination results, diagnoses, prescriptions). This information is collected and processed solely for:</p>
  <ul>
    <li>Creating and maintaining your patient record</li>
    <li>Scheduling and managing appointments</li>
    <li>Providing optical examination, diagnosis, and treatment</li>
    <li>Generating your patient identification QR code</li>
    <li>Communicating with you regarding your account, appointments, or care</li>
    <li>Complying with legal and regulatory requirements</li>
  </ul>

  <h4>7. Storage and Security</h4>
  <p>Your data is stored on secured servers with access restricted to authorized clinic personnel (admin, staff, and your attending doctor) who need it to perform their duties. We apply reasonable organizational, physical, and technical safeguards to protect your information against unauthorized access, alteration, disclosure, or destruction.</p>

  <h4>8. Data Sharing</h4>
  <p>The Clinic does not sell or rent your personal information. Your data may only be shared with third parties when required by law, when necessary to provide your care (e.g. referrals), or with your explicit consent.</p>

  <h4>9. Data Retention</h4>
  <p>Your personal and health records are retained for as long as your account is active, and for the period required by applicable healthcare record-keeping regulations afterward, after which they are securely disposed of.</p>

  <h4>10. Your Rights as a Data Subject</h4>
  <p>Under the Data Privacy Act, you have the right to:</p>
  <ul>
    <li>Be informed of how your data is collected and processed</li>
    <li>Access the personal data the Clinic holds about you</li>
    <li>Request correction of inaccurate or outdated data</li>
    <li>Object to or withdraw consent for processing, subject to legal or contractual restrictions</li>
    <li>Request deletion of your data, where applicable</li>
    <li>File a complaint with the National Privacy Commission (NPC)</li>
  </ul>
  <p>To exercise any of these rights, please contact the clinic directly using the contact details on our website.</p>

  <h4>11. Consent</h4>
  <p>By checking "I agree" and completing registration, you acknowledge that you have read and understood this notice, and you consent to the collection, use, storage, and processing of your personal and sensitive personal information as described above, for the purpose of receiving care from Cana Optical Clinic — and you are entrusting your credentials and personal information to the Clinic on that basis.</p>

  <h4>12. Changes to this Notice</h4>
  <p>The Clinic may update these Terms &amp; Conditions and this Data Privacy Notice from time to time. Continued use of your account after changes are posted constitutes acceptance of the revised terms.</p>
`

function openTermsModal() {
  window.showModal(`
    <div class="modal-header">
      <div class="modal-title">${window.icon ? window.icon('shield','icon-sm') : ''} Terms &amp; Conditions and Data Privacy Act</div>
      <button class="modal-close" onclick="window.closeModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div class="terms-body" id="terms-scroll-body" onscroll="window._checkTermsScroll()">
        ${TERMS_AND_PRIVACY_HTML}
      </div>
    </div>
    <div class="modal-footer">
      <span class="terms-scroll-hint" id="terms-scroll-hint">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
        Scroll to the bottom to continue
      </span>
      <button class="btn-secondary" onclick="window.closeModal()">Cancel</button>
      <button class="btn-primary" id="terms-agree-btn" disabled onclick="window.acceptTerms()">I Agree &amp; Continue</button>
    </div>`, 'modal-lg')

  // If the content already fits without scrolling (tall viewport / small
  // text), don't trap the user behind an unreachable scroll requirement.
  setTimeout(() => window._checkTermsScroll(), 50)
}
window.openTermsModal = openTermsModal

function _checkTermsScroll() {
  const body = document.getElementById('terms-scroll-body')
  const btn  = document.getElementById('terms-agree-btn')
  if (!body || !btn) return
  const reachedBottom = body.scrollTop + body.clientHeight >= body.scrollHeight - 24
  if (reachedBottom && btn.disabled) {
    btn.disabled = false
    const hint = document.getElementById('terms-scroll-hint')
    if (hint) hint.style.display = 'none'
  }
}
window._checkTermsScroll = _checkTermsScroll

function acceptTerms() {
  // Defense in depth — the button's native `disabled` attribute already
  // blocks this click, but don't trust that alone for something gating
  // consent to data collection.
  const btn = document.getElementById('terms-agree-btn')
  if (btn && btn.disabled) return

  const cb = document.getElementById('reg-terms-agree')
  if (cb) { cb.disabled = false; cb.checked = true }
  const hint = document.getElementById('reg-terms-hint')
  if (hint) hint.style.display = 'none'
  window.closeModal()
}
window.acceptTerms = acceptTerms

// ── Multi-step registration wizard ────────────────────────────────
window._regStep = 1

function _regGoToStep(n) {
  const prev = window._regStep
  const dir  = (prev === 0 || n > prev) ? 1 : -1

  for (let i = 1; i <= 3; i++) {
    const p = document.getElementById('reg-panel-' + i)
    if (p) p.style.display = i === n ? '' : 'none'
  }

  // Clear error banner and any field highlights on every step transition
  const errEl = document.getElementById('reg-error')
  if (errEl) errEl.style.display = 'none'
  document.querySelectorAll('#register-screen .reg-input.error').forEach(el => el.classList.remove('error'))

  if (n === 3) {
    const h = document.getElementById('reg-hidden-email')
    if (h) h.value = document.getElementById('reg-email')?.value || ''
  }

  if (prev !== 0) {
    const card = document.querySelector('#register-screen .auth-split-card')
    if (card) {
      card.classList.remove('auth-card-step-in')
      void card.offsetWidth
      card.classList.add('auth-card-step-in')
    }
  }

  for (let i = 1; i <= 3; i++) {
    const dot = document.getElementById('rstep-dot-' + i)
    const num = document.getElementById('rstep-num-' + i)
    if (!dot) continue
    if (i < n) {
      dot.className = 'reg-step-item reg-step-done'
      if (num) num.textContent = '✓'
    } else if (i === n) {
      dot.className = 'reg-step-item reg-step-active'
      if (num) num.textContent = String(i)
    } else {
      dot.className = 'reg-step-item'
      if (num) num.textContent = String(i)
    }
    const line = document.getElementById('rstep-line-' + i)
    if (line) line.className = 'reg-step-line' + (i < n ? ' reg-step-line-done' : '')
  }
  const backBtn   = document.getElementById('reg-nav-back')
  const nextBtn   = document.getElementById('reg-nav-next')
  const submitBtn = document.getElementById('reg-submit-btn')
  if (backBtn) {
    const svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><polyline points="15 18 9 12 15 6"/></svg>'
    backBtn.innerHTML = svg + (n === 1 ? ' Back to Login' : ' Back')
  }
  if (nextBtn)   nextBtn.style.display   = n < 3 ? '' : 'none'
  if (submitBtn) submitBtn.style.display = n === 3 ? '' : 'none'
  window._regStep = n
}

function regNextStep() {
  const step   = window._regStep
  const errEl  = document.getElementById('reg-error')
  const errMsg = document.getElementById('reg-error-msg')
  if (errEl) errEl.style.display = 'none'

  // Clear all field errors for the current step panel
  const panel = document.getElementById('reg-panel-' + step)
  if (panel) panel.querySelectorAll('.reg-input.error').forEach(el => el.classList.remove('error'))

  const markError = id => document.getElementById(id)?.classList.add('error')

  if (step === 1) {
    const first  = (document.getElementById('reg-first')?.value  || '').trim()
    const last   = (document.getElementById('reg-last')?.value   || '').trim()
    const dob    = (document.getElementById('reg-dob')?.value    || '')
    const gender = (document.getElementById('reg-gender')?.value || '')
    if (!first) markError('reg-first')
    if (!last)  markError('reg-last')
    if (!dob)   markError('reg-dob')
    if (!gender) markError('reg-gender')
    if (!first || !last || !dob || !gender) {
      if (errMsg) errMsg.textContent = 'Please fill in all required fields.'
      if (errEl)  errEl.style.display = 'flex'; return
    }
    const dobDate = new Date(dob + 'T00:00:00')
    const today   = new Date()
    const minDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate())
    if (dobDate > minDate) {
      markError('reg-dob')
      if (errMsg) errMsg.textContent = 'You must be at least 18 years old to create an account. Patients under 18 may be registered by a parent or guardian at the clinic.'
      if (errEl)  errEl.style.display = 'flex'; return
    }
  } else if (step === 2) {
    const contact = (document.getElementById('reg-contact')?.value || '').trim()
    const email   = (document.getElementById('reg-email')?.value   || '').trim()
    const address = (document.getElementById('reg-address')?.value || '').trim()
    if (!contact) markError('reg-contact')
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) markError('reg-email')
    if (!address) markError('reg-address')
    if (!contact || !address) {
      if (errMsg) errMsg.textContent = 'Please fill in all required fields.'
      if (errEl)  errEl.style.display = 'flex'; return
    }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      if (errMsg) errMsg.textContent = !email ? 'Please fill in all required fields.' : 'Please enter a valid email address.'
      if (errEl)  errEl.style.display = 'flex'; return
    }
  }

  if (step < 3) _regGoToStep(step + 1)
}
window.regNextStep = regNextStep

function regPrevStep() {
  const step = window._regStep
  if (step <= 1) { showLogin(); return }
  _regGoToStep(step - 1)
}
window.regPrevStep = regPrevStep

function _checkRegTermsScroll() {
  const box = document.getElementById('reg-terms-box')
  const cb  = document.getElementById('reg-terms-agree')
  if (!box || !cb || !cb.disabled) return
  const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 40
  if (atBottom) {
    cb.disabled = false
    const hint = document.getElementById('reg-terms-hint')
    if (hint) hint.style.display = 'none'
  }
}
window._checkRegTermsScroll = _checkRegTermsScroll

// ── Session restore (called from index.html on page load) ─────────
async function restoreSession() {
  const loadingEl  = document.getElementById('loading-screen')
  const loginEl    = document.getElementById('login-screen')
  const _startedAt = Date.now()
  const MIN_SHOW   = 900 // ms — long enough to see the loader, short enough not to feel slow

  const hideLoader = () => {
    const elapsed = Date.now() - _startedAt
    const delay   = Math.max(0, MIN_SHOW - elapsed)
    setTimeout(() => { if (loadingEl) loadingEl.style.display = 'none' }, delay)
  }
  const showLogin = (delay = 0) => {
    setTimeout(() => {
      if (loginEl) loginEl.style.display = 'flex'
      if (window._openRegister) {
        window._openRegister = false
        if (typeof showRegister === 'function') showRegister()
      }
    }, delay)
  }

  try {
    const res  = await fetch('api/auth/me.php')
    const data = await res.json()
    if (!data.success) {
      hideLoader()
      showLogin(Math.max(0, MIN_SHOW - (Date.now() - _startedAt)))
      return
    }

    // Sync in-memory state with the logged-in user so pages.js works
    const { role, user } = data
    if (role === 'patient') {
      const idx = patients.findIndex(p => p.id === user.id)
      if (idx === -1) patients.push({ ...user, password: '' })
    }

    hideLoader()
    const elapsed = Date.now() - _startedAt
    setTimeout(() => _bootAfterAuth(role, user), Math.max(0, MIN_SHOW - elapsed))
  } catch (_) {
    // PHP not available — fall back to login screen
    hideLoader()
    showLogin(Math.max(0, MIN_SHOW - (Date.now() - _startedAt)))
  }
}

// Pre-fill the login email + check "Remember me" if a prior login left one
// saved — called on page load alongside restoreSession(). Independent of
// the session itself, since a remembered email is still useful after the
// session (even a 30-day one) has expired and the user has to sign in again.
function _prefillRememberedEmail() {
  try {
    const saved = localStorage.getItem('opticana_remembered_email')
    if (!saved) return
    const emailEl    = document.getElementById('login-email')
    const rememberEl = document.getElementById('login-remember')
    if (emailEl && !emailEl.value) emailEl.value = saved
    if (rememberEl) rememberEl.checked = true
  } catch (_) { /* localStorage unavailable — non-critical */ }
}
window._prefillRememberedEmail = _prefillRememberedEmail

// ── Screen switching ──────────────────────────────────────────────
function showRegister() {
  document.getElementById('login-screen').style.display    = 'none'
  document.getElementById('register-screen').style.display = 'flex'
  ;['reg-first','reg-last','reg-dob','reg-address','reg-contact','reg-email','reg-password','reg-confirm']
    .forEach(id => { const el = document.getElementById(id); if (el) el.value = '' })
  const dobEl = document.getElementById('reg-dob')
  if (dobEl) {
    const t = new Date()
    const maxDob = new Date(t.getFullYear() - 18, t.getMonth(), t.getDate())
    dobEl.max = maxDob.toISOString().slice(0, 10)
  }
  const genderEl = document.getElementById('reg-gender'); if (genderEl) genderEl.value = ''
  const bloodEl  = document.getElementById('reg-blood');  if (bloodEl)  bloodEl.value  = ''
  document.getElementById('reg-error').style.display = 'none'
  const termsCb = document.getElementById('reg-terms-agree')
  if (termsCb) { termsCb.checked = false; termsCb.disabled = true }
  const termsHint = document.getElementById('reg-terms-hint')
  if (termsHint) termsHint.style.display = 'flex'
  window._regStep = 0
  _regGoToStep(1)
}

function showLogin() {
  document.getElementById('register-screen').style.display  = 'none'
  document.getElementById('forgot-screen').style.display    = 'none'
  document.getElementById('verify-screen').style.display    = 'none'
  document.getElementById('login-screen').style.display     = 'flex'
  _evStopTimer()
}
window.showLogin = showLogin

function showForgotPassword() {
  document.getElementById('login-screen').style.display  = 'none'
  document.getElementById('forgot-screen').style.display = 'flex'
  fpUpdateDots(1)
  document.querySelectorAll('.fp-step').forEach(s => { s.classList.remove('active', 'reg-slide-in-right', 'reg-slide-in-left'); s.style.display = 'none'; s.style.opacity = '' })
  const s1 = document.getElementById('fp-step-1')
  s1.style.display = 'block'
  s1.classList.add('active')
  const emailEl = document.getElementById('fp-s1-email')
  if (emailEl) { emailEl.value = ''; emailEl.classList.remove('error') }
  document.getElementById('fp-s1-error').classList.remove('show')
  window._fpStep = 1
  window._fpEmail = ''
  clearInterval(window._fpResendCooldownInterval)
  window._fpResendCooldownLeft = 0
}

// ── Forgot Password flow ──────────────────────────────────────────
window._fpStep = 1
window._fpEmail = ''
window._fpResetToken = ''
window._fpTimerInterval = null
window._fpTimeLeft = 300
window._fpResendCooldownInterval = null
window._fpResendCooldownLeft = 0

function fpGoToStep(step) {
  const prev  = window._fpStep
  const dir   = (step > prev) ? 1 : -1
  const curId = prev === 3.5 ? 'fp-step-3b' : 'fp-step-' + prev
  const cur   = document.getElementById(curId)
  if (cur) { cur.classList.remove('active'); cur.style.display = 'none'; cur.style.opacity = '' }

  const nextId = step === 3.5 ? 'fp-step-3b' : 'fp-step-' + step
  const next   = document.getElementById(nextId)
  if (!next) return
  next.style.display = 'block'
  next.style.opacity = ''
  window._fpStep = step

  if (step === 4) {
    const h = document.getElementById('fp-s4-hidden-email')
    if (h) h.value = window._fpEmail || ''
  }
  if (step === 5) {
    const c = document.getElementById('fp-success-circle')
    if (c) { c.classList.remove('fp-success-anim'); void c.offsetWidth; c.classList.add('fp-success-anim') }
  }
  if (step === 3) fpStartTimer()
  fpUpdateDots(step)

  const card = document.querySelector('#forgot-screen .auth-split-card')
  if (card) {
    card.classList.remove('auth-card-step-in')
    void card.offsetWidth
    card.classList.add('auth-card-step-in')
  }
  next.classList.add('active')
}

function fpUpdateDots(step) {
  const wrap = document.getElementById('fp-progress-dots')
  if (!wrap) return
  // hide dots on terminal/error steps
  wrap.style.display = (step === 5 || step === 3.5) ? 'none' : 'flex'
  // step → which dot is active (1-based)
  const active = { 1:1, 2:2, 3:2, 4:3, 5:4 }[step] || 1
  for (let i = 1; i <= 4; i++) {
    const d = document.getElementById('fp-dot-' + i)
    if (!d) continue
    d.className = 'fp-dot' + (i === active ? ' active' : i < active ? ' done' : '')
  }
}

async function fpS1Submit() {
  const emailEl = document.getElementById('fp-s1-email')
  const errEl   = document.getElementById('fp-s1-error')
  const btn     = document.getElementById('fp-s1-btn')
  emailEl.classList.remove('error'); errEl.classList.remove('show')
  if (!emailEl.value.trim()) { emailEl.classList.add('error'); errEl.classList.add('show'); emailEl.focus(); return }
  window._fpEmail = emailEl.value.trim()
  btn.disabled = true; btn.innerHTML = '<div class="fp-spinner"></div> Sending…'
  try {
    const res  = await fetch('api/auth/forgot-password.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: window._fpEmail }),
    })
    const data = await res.json()
    if (!data.success) {
      emailEl.classList.add('error')
      errEl.textContent = data.message || 'Failed to send email. Please try again.'
      errEl.classList.add('show')
      return
    }
    document.getElementById('fp-s2-email').textContent = window._fpEmail
    document.getElementById('fp-s3-email').textContent = window._fpEmail
    fpGoToStep(2)
  } catch (_) {
    errEl.textContent = 'Network error. Please check your connection.'
    errEl.classList.add('show')
  } finally {
    btn.disabled = false; btn.innerHTML = 'Submit'
  }
}

async function fpS3Submit() {
  const inputs = document.querySelectorAll('#fp-otp-row .otp-input')
  const errEl  = document.getElementById('fp-s3-error')
  const btn    = document.getElementById('fp-s3-btn')
  const code   = [...inputs].map(i => i.value).join('')
  inputs.forEach(i => i.classList.remove('error')); errEl.classList.remove('show')
  if (code.length < 6) { inputs.forEach(i => { if (!i.value) i.classList.add('error') }); errEl.classList.add('show'); return }
  btn.disabled = true; btn.innerHTML = '<div class="fp-spinner"></div> Verifying…'
  try {
    const res  = await fetch('api/auth/verify-otp.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: window._fpEmail, otp: code }),
    })
    const data = await res.json()
    if (!data.success) {
      btn.disabled = false; btn.innerHTML = 'Verify OTP'
      fpShowWrongOTP(!!data.locked, data.attemptsLeft ?? null, !!data.banned)
      fpGoToStep(3.5)
      return
    }
    window._fpResetToken = data.token
    clearInterval(window._fpTimerInterval)
    document.querySelectorAll('#fp-otp-row .otp-input').forEach(i => { i.value = ''; i.classList.remove('error') })
    document.getElementById('fp-s3-error').classList.remove('show')
    btn.disabled = false; btn.innerHTML = 'Verify OTP'
    fpGoToStep(4)
  } catch (_) {
    errEl.textContent = 'Network error. Please try again.'
    errEl.classList.add('show')
    btn.disabled = false; btn.innerHTML = 'Verify OTP'
  }
}

async function fpS4Submit() {
  const pw1  = document.getElementById('fp-s4-pw1')
  const pw2  = document.getElementById('fp-s4-pw2')
  const err1 = document.getElementById('fp-s4-err1')
  const err2 = document.getElementById('fp-s4-err2')
  const btn  = document.getElementById('fp-s4-btn')
  pw1.classList.remove('error'); pw2.classList.remove('error'); err1.classList.remove('show'); err2.classList.remove('show')
  let valid = true
  if (!pw1.value) { pw1.classList.add('error'); err1.textContent = 'Please enter a new password.'; err1.classList.add('show'); valid = false }
  else if (pw1.value.length < 8) { pw1.classList.add('error'); err1.textContent = 'Password must be at least 8 characters.'; err1.classList.add('show'); valid = false }
  if (!pw2.value) { pw2.classList.add('error'); err2.textContent = 'Please confirm your password.'; err2.classList.add('show'); valid = false }
  else if (pw1.value && pw2.value !== pw1.value) { pw2.classList.add('error'); err2.textContent = 'Passwords do not match.'; err2.classList.add('show'); valid = false }
  if (!valid) return
  btn.disabled = true; btn.innerHTML = '<div class="fp-spinner"></div> Saving…'
  try {
    const res  = await fetch('api/auth/reset-password.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: window._fpResetToken, password: pw1.value }),
    })
    const data = await res.json()
    if (!data.success) {
      err1.textContent = data.message || 'Password reset failed. Please start over.'
      err1.classList.add('show')
      pw1.classList.add('error')
      return
    }
    fpGoToStep(5)
  } catch (_) {
    err1.textContent = 'Network error. Please try again.'
    err1.classList.add('show')
  } finally {
    btn.disabled = false; btn.innerHTML = 'Reset Password'
  }
}

function fpShowWrongOTP(locked, attemptsLeft, banned = false) {
  const title   = document.getElementById('fp-3b-title')
  const msg     = document.getElementById('fp-3b-msg')
  const retry   = document.getElementById('fp-3b-retry')
  const newCode = document.getElementById('fp-3b-new-code')
  const backBtn = document.getElementById('fp-3b-back-login')
  if (!title || !msg || !retry || !newCode) return
  if (backBtn) backBtn.style.display = 'none'
  if (banned) {
    title.textContent     = 'Reset Temporarily Blocked'
    msg.textContent       = 'Too many failed OTP attempts. Please wait 1 hour before requesting a new password reset.'
    retry.style.display   = 'none'
    newCode.style.display = 'none'
    if (backBtn) backBtn.style.display = 'block'
  } else if (locked) {
    title.textContent        = 'Too Many Attempts'
    msg.textContent          = 'You\'ve used all 5 attempts. Please request a new code to try again.'
    retry.style.display      = 'none'
    newCode.style.display    = 'block'
  } else {
    const left   = attemptsLeft ?? 2
    const plural = left === 1 ? 'attempt' : 'attempts'
    title.textContent        = 'Invalid OTP Code'
    msg.textContent          = `The code you entered is incorrect. You have ${left} ${plural} remaining.`
    retry.style.display      = 'block'
    newCode.style.display    = 'none'
  }
}

function fpRestartFromLocked() {
  clearInterval(window._fpTimerInterval)
  clearInterval(window._fpResendCooldownInterval)
  window._fpResendCooldownLeft = 0
  window._fpTimeLeft = 0
  document.querySelectorAll('#fp-otp-row .otp-input').forEach(i => { i.value = ''; i.classList.remove('error') })
  const resendBtn = document.querySelector('.fp-resend-link')
  if (resendBtn) { resendBtn.disabled = false; resendBtn.textContent = 'Resend Code' }
  fpGoToStep(1)
}

function fpRetryOTP() {
  document.querySelectorAll('#fp-otp-row .otp-input').forEach(i => { i.value = ''; i.classList.remove('error') })
  document.getElementById('fp-s3-error').classList.remove('show')
  document.getElementById('fp-s3-btn').disabled = false
  document.getElementById('fp-s3-btn').innerHTML = 'Verify OTP'
  // Timer keeps running — don't reset it. The interval never stopped while on the
  // wrong-OTP screen, so the countdown and expired state are already correct.
  const cur = document.getElementById('fp-step-3b')
  cur.style.opacity = '0'
  setTimeout(() => {
    cur.classList.remove('active'); cur.style.display = 'none'; window._fpStep = 3
    const next = document.getElementById('fp-step-3')
    next.style.opacity = '0'; next.style.display = 'block'
    requestAnimationFrame(() => requestAnimationFrame(() => { next.style.transition = 'opacity 0.3s ease'; next.style.opacity = '1'; next.classList.add('active') }))
  }, 300)
}

async function fpResendOTP() {
  if (window._fpResendCooldownLeft > 0) return
  fpStartResendCooldown()
  document.getElementById('fp-otp-timer').classList.remove('expired')
  document.getElementById('fp-otp-timer').innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Expires in <span id="fp-timer-display">5:00</span>'
  document.querySelectorAll('#fp-otp-row .otp-input').forEach(i => { i.value = ''; i.classList.remove('error') })
  document.getElementById('fp-s3-error').classList.remove('show')
  try {
    const res  = await fetch('api/auth/forgot-password.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: window._fpEmail }),
    })
    const data = await res.json()
    if (!data.success && data.banned) {
      clearInterval(window._fpTimerInterval)
      fpShowWrongOTP(false, null, true)
      fpGoToStep(3.5)
      return
    }
  } catch (_) { /* fail silently — timer still restarts */ }
  document.querySelectorAll('#fp-otp-row .otp-input')[0].focus()
  fpStartTimer()
}

function fpStartTimer() {
  clearInterval(window._fpTimerInterval)
  window._fpTimeLeft = 300
  function tick() {
    const display = document.getElementById('fp-timer-display')
    const timerEl = document.getElementById('fp-otp-timer')
    if (!display) { clearInterval(window._fpTimerInterval); return }
    const m = Math.floor(window._fpTimeLeft / 60), s = window._fpTimeLeft % 60
    display.textContent = m + ':' + String(s).padStart(2, '0')
    if (window._fpTimeLeft <= 0) {
      clearInterval(window._fpTimerInterval)
      timerEl.classList.add('expired')
      timerEl.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Code expired'
    }
    window._fpTimeLeft--
  }
  tick()
  window._fpTimerInterval = setInterval(tick, 1000)
}

function fpStartResendCooldown() {
  const btn = document.querySelector('.fp-resend-link')
  if (!btn) return
  clearInterval(window._fpResendCooldownInterval)
  window._fpResendCooldownLeft = 60
  btn.disabled = true
  function tick() {
    if (!btn.isConnected) { clearInterval(window._fpResendCooldownInterval); window._fpResendCooldownLeft = 0; return }
    const s = window._fpResendCooldownLeft
    btn.textContent = 'Resend in 0:' + String(s).padStart(2, '0')
    if (s <= 0) {
      clearInterval(window._fpResendCooldownInterval)
      btn.disabled = false
      btn.textContent = 'Resend Code'
      return
    }
    window._fpResendCooldownLeft--
  }
  tick()
  window._fpResendCooldownInterval = setInterval(tick, 1000)
}

// ── Email Verification Screen ─────────────────────────────────────
let _evEmail = ''
let _evFromReg = false
let _evTimerInterval = null
let _evResendCooldownInterval = null
let _evResendCooldownLeft = 0

function showEmailVerify(email, fromRegistration = false) {
  _evEmail   = email
  _evFromReg = fromRegistration

  document.getElementById('login-screen').style.display    = 'none'
  document.getElementById('register-screen').style.display = 'none'
  document.getElementById('forgot-screen').style.display   = 'none'
  document.getElementById('verify-screen').style.display   = 'flex'

  document.getElementById('ev-email-display').textContent = email

  // Reset to step 1
  evShowStep(1)

  // Clear OTP inputs
  document.querySelectorAll('#ev-otp-row .otp-input').forEach(i => { i.value = ''; i.classList.remove('error') })
  document.getElementById('ev-error').style.display = 'none'

  // Reset resend button and any running cooldown
  clearInterval(_evResendCooldownInterval)
  _evResendCooldownInterval = null
  _evResendCooldownLeft = 0
  const resendBtn = document.getElementById('ev-resend-btn')
  if (resendBtn) { resendBtn.disabled = false; resendBtn.textContent = 'Resend Code' }

  // Start countdown
  _evStartTimer(300)

  // Focus first input
  setTimeout(() => {
    const first = document.querySelector('#ev-otp-row .otp-input')
    if (first) first.focus()
  }, 50)
}
window.showEmailVerify = showEmailVerify

function evShowStep(n) {
  document.querySelectorAll('#verify-screen .fp-step').forEach(s => {
    s.classList.remove('active')
    s.style.display = 'none'
    s.style.opacity = '0'
  })
  const step = document.getElementById('ev-step-' + n)
  if (!step) return
  step.style.display = 'block'
  requestAnimationFrame(() => requestAnimationFrame(() => {
    step.style.transition = 'opacity 0.3s ease'
    step.style.opacity    = '1'
    step.classList.add('active')
  }))
}
window.evShowStep = evShowStep

function _evStartTimer(seconds) {
  _evStopTimer()
  let remaining = seconds
  const display  = document.getElementById('ev-timer-display')
  const timerEl  = document.getElementById('ev-timer')

  function tick() {
    if (!display || !display.isConnected) { _evStopTimer(); return }
    const m = Math.floor(remaining / 60)
    const s = remaining % 60
    display.textContent = m + ':' + String(s).padStart(2, '0')
    if (remaining <= 0) {
      _evStopTimer()
      if (timerEl) timerEl.classList.add('expired')
      display.textContent = 'Expired'
      return
    }
    remaining--
  }
  tick()
  _evTimerInterval = setInterval(tick, 1000)
}

function _evStopTimer() {
  if (_evTimerInterval) { clearInterval(_evTimerInterval); _evTimerInterval = null }
  clearInterval(_evResendCooldownInterval)
  _evResendCooldownInterval = null
  _evResendCooldownLeft = 0
}

function _evStartResendCooldown() {
  const btn = document.getElementById('ev-resend-btn')
  if (!btn) return
  clearInterval(_evResendCooldownInterval)
  _evResendCooldownLeft = 60
  btn.disabled = true
  function tick() {
    if (!btn.isConnected) { clearInterval(_evResendCooldownInterval); _evResendCooldownLeft = 0; return }
    if (_evResendCooldownLeft <= 0) {
      clearInterval(_evResendCooldownInterval)
      btn.disabled = false
      btn.textContent = 'Resend Code'
      return
    }
    btn.textContent = 'Resend in 0:' + String(_evResendCooldownLeft).padStart(2, '0')
    _evResendCooldownLeft--
  }
  tick()
  _evResendCooldownInterval = setInterval(tick, 1000)
}

async function evSubmitOTP() {
  const inputs = document.querySelectorAll('#ev-otp-row .otp-input')
  const otp    = [...inputs].map(i => i.value).join('')
  const errEl  = document.getElementById('ev-error')

  if (otp.length < 6) {
    errEl.textContent = 'Please enter the complete 6-digit code.'
    errEl.style.display = 'block'
    inputs.forEach(i => i.classList.add('error'))
    return
  }
  errEl.style.display = 'none'
  inputs.forEach(i => i.classList.remove('error'))

  const btn = document.getElementById('ev-submit-btn')
  if (btn) { btn.disabled = true; btn.textContent = 'Verifying…' }

  try {
    const res  = await fetch('api/auth/verify-email-otp.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ email: _evEmail, otp }),
    })
    const data = await res.json()

    if (!data.success) {
      const title   = document.getElementById('ev-2-title')
      const msg     = document.getElementById('ev-2-msg')
      const retry   = document.getElementById('ev-2-retry')
      const resend  = document.getElementById('ev-2-resend')

      if (data.banned) {
        if (title) title.textContent = 'Registration Blocked'
        if (msg)   msg.textContent   = 'Too many failed attempts. Please go back and register again with a valid email address.'
        if (retry)  retry.style.display  = 'none'
        if (resend) resend.style.display = 'none'
      } else if (data.locked) {
        if (title) title.textContent = 'Too Many Attempts'
        if (msg)   msg.textContent   = 'You\'ve entered the wrong code too many times. Request a new code to try again.'
        if (retry)  retry.style.display  = 'none'
        if (resend) resend.style.display = 'block'
      } else {
        if (title) title.textContent = 'Invalid OTP Code'
        if (msg)   msg.textContent   = data.message || 'The code you entered is incorrect. Please try again.'
        if (retry)  retry.style.display  = 'block'
        if (resend) resend.style.display = 'none'
      }

      inputs.forEach(i => { i.value = ''; i.classList.remove('error') })
      evShowStep(2)
      return
    }

    // Success — show success step briefly then boot
    _evStopTimer()
    evShowStep(3)

    // Push to patients array if this was a self-registration flow
    if (_evFromReg) {
      const p = data.user
      patients.push({
        id: p.id, firstName: p.firstName, lastName: p.lastName,
        name: p.name, gender: p.gender, dob: p.dob, age: p.age,
        contact: p.contact, email: p.email, password: '',
        address: p.address, bloodType: p.bloodType,
        registeredDate: p.registeredDate, lastVisit: '—',
        qrData: p.qrData, status: 'active', occupation: '',
        medicalHistory: '', opticalHistory: '',
        consultations: [], examinations: [], prescriptions: [],
      })
    }

    setTimeout(() => {
      _bootAfterAuth(data.role, data.user)
      if (_evFromReg) {
        setTimeout(() => window._showRegistrationQRModal(data.user, null, true), 200)
      }
    }, 900)

  } catch (_) {
    errEl.textContent   = 'Network error. Please check your connection and try again.'
    errEl.style.display = 'block'
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = 'Verify & Activate Account' }
  }
}
window.evSubmitOTP = evSubmitOTP

async function evResendCode() {
  if (_evResendCooldownLeft > 0) return

  const errEl = document.getElementById('ev-error')
  errEl.style.display = 'none'

  _evStartResendCooldown()
  const resendBtn = document.getElementById('ev-resend-btn')
  if (resendBtn) resendBtn.textContent = 'Sending…'

  try {
    const res  = await fetch('api/auth/send-email-verification.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ email: _evEmail }),
    })
    const data = await res.json()

    if (!data.success) {
      if (data.banned) {
        const title  = document.getElementById('ev-2-title')
        const msg    = document.getElementById('ev-2-msg')
        const retry  = document.getElementById('ev-2-retry')
        const resend = document.getElementById('ev-2-resend')
        if (title)  title.textContent  = 'Registration Blocked'
        if (msg)    msg.textContent    = 'Too many failed attempts. Please go back and register again with a valid email address.'
        if (retry)  retry.style.display  = 'none'
        if (resend) resend.style.display = 'none'
        evShowStep(2)
        return
      }
      errEl.textContent   = data.message || 'Failed to resend code. Please try again.'
      errEl.style.display = 'block'
      return
    }

    // Success — reset inputs and restart OTP expiry timer; cooldown already running
    document.querySelectorAll('#ev-otp-row .otp-input').forEach(i => { i.value = ''; i.classList.remove('error') })
    document.querySelector('#ev-otp-row .otp-input')?.focus()
    _evStartTimer(300)

  } catch (_) {
    errEl.textContent   = 'Network error. Please try again.'
    errEl.style.display = 'block'
  }
}
window.evResendCode = evResendCode

// Init OTP inputs for email verify screen
;(function() {
  function setupEvOTP() {
    const inputs = document.querySelectorAll('#ev-otp-row .otp-input')
    inputs.forEach((input, idx) => {
      input.addEventListener('input', e => {
        const val = e.target.value.replace(/\D/g, '')
        e.target.value = val ? val[0] : ''
        if (val && idx < 5) inputs[idx + 1].focus()
      })
      input.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !e.target.value && idx > 0) inputs[idx - 1].focus()
        if (e.key === 'Enter') evSubmitOTP()
      })
      input.addEventListener('paste', e => {
        e.preventDefault()
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '')
        ;[...pasted].slice(0, 6).forEach((ch, i) => { if (inputs[idx + i]) inputs[idx + i].value = ch })
        inputs[Math.min(idx + pasted.length, 5)].focus()
      })
    })
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupEvOTP)
  else setupEvOTP()
})()

// Init OTP inputs for forgot-password
;(function() {
  function setupFpOTP() {
    const inputs = document.querySelectorAll('#fp-otp-row .otp-input')
    inputs.forEach((input, idx) => {
      input.addEventListener('input', e => {
        const val = e.target.value.replace(/\D/g, '')
        e.target.value = val ? val[0] : ''
        if (val && idx < 5) inputs[idx + 1].focus()
      })
      input.addEventListener('keydown', e => { if (e.key === 'Backspace' && !e.target.value && idx > 0) inputs[idx - 1].focus() })
      input.addEventListener('paste', e => {
        e.preventDefault()
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '')
        ;[...pasted].slice(0, 6).forEach((ch, i) => { if (inputs[idx + i]) inputs[idx + i].value = ch })
        inputs[Math.min(idx + pasted.length, 5)].focus()
      })
    })
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupFpOTP)
  else setupFpOTP()
})()

;(function() {
  function setupRegInputClear() {
    const screen = document.getElementById('register-screen')
    if (!screen) return
    screen.addEventListener('input', function(e) {
      if (e.target.classList.contains('reg-input')) e.target.classList.remove('error')
    })
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupRegInputClear)
  else setupRegInputClear()
})()

function fpTogglePw(inputId, iconId) {
  const EYE_OPEN   = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
  const EYE_CLOSED = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>'
  const input = document.getElementById(inputId)
  const icon  = document.getElementById(iconId)
  if (input.type === 'password') { input.type = 'text'; icon.innerHTML = EYE_CLOSED }
  else { input.type = 'password'; icon.innerHTML = EYE_OPEN }
}

// ── Private helpers ───────────────────────────────────────────────
function _bootAfterAuth(role, user) {
  window.state.role         = role
  window.state.user         = user
  window.state.selectedRole = role

  document.getElementById('login-screen').style.display    = 'none'
  document.getElementById('register-screen').style.display = 'none'
  document.getElementById('verify-screen').style.display   = 'none'
  document.getElementById('app-shell').style.display       = 'flex'
  _evStopTimer()

  window.bootShell(role, user)

  // Load real data from backend (non-blocking)
  _syncAppointments()
  _syncNotifications()
  _syncClinicSettings()
  _syncServices()
  if (role === 'admin') { _syncActivityLog(); _syncStaff(); _syncArchives() }
  if (role === 'staff') _syncStaff()
  if (['admin', 'staff', 'doctor'].includes(role)) _syncPatients()
  if (role === 'patient') _syncMyRecords()
  // Doctors array (incl. schedule/availability/blocked dates) — every role needs
  // a synced copy, not just staff: patients book against it, doctors see their
  // own panel. Patients hit the public endpoint since they're not authorized
  // for the admin/staff doctors/index.php route.
  _syncDoctors()
  if (['admin', 'staff'].includes(role)) _syncContactMessages()
}

// Pages that show appointments list — need full re-render when data changes
const _APPT_PAGES = new Set([
  'appointments','patient-appts','doctor-appointments'
])

window._apptPendingCount = 0

async function _syncAppointments(rerender = true) {
  try {
    const r = await fetch('api/appointments/index.php')
    if (!r.ok) return
    const d = await r.json()
    if (!d.success || !Array.isArray(d.appointments)) return
    // Replace the mock array in-place so all existing references stay valid
    appointments.splice(0, appointments.length, ...d.appointments)
    window._apptPendingCount = d.appointments.filter(a => a.status === 'pending').length
    if (window._updateSidebarBadges) window._updateSidebarBadges()
    const page = window.state?.page
    if (rerender && _APPT_PAGES.has(page)) { window.renderPage(); return }
    // Update dashboard stat cards and charts without a full re-render
    if (page === 'admin-dashboard' && window.updateAdminDashboard) window.updateAdminDashboard()
    if (page === 'staff-dashboard' && window.updateStaffDashboard) window.updateStaffDashboard()
    if (page === 'doctor-dashboard' && window.renderPage) window.renderPage()
    if (page === 'patient-dashboard' && window.renderPage) window.renderPage()
  } catch (_) { /* keep mock data on network failure */ }
}
window._syncAppointments = _syncAppointments

// ── Patients sync ────────────────────────────────────────────────
async function _syncPatients() {
  try {
    const r = await fetch('api/patients/index.php')
    if (!r.ok) return
    const d = await r.json()
    if (!d.success || !Array.isArray(d.patients)) return
    patients.splice(0, patients.length, ...d.patients)
    // Re-render page if it shows patients list
    const p = window.state?.page
    if (p === 'patient-list') { window.renderPage(); return }
    if (p === 'new-examination' && window.state?.params?.patientId) { window.renderPage(); return }
    // Update dashboard stat without full re-render
    if (p === 'admin-dashboard' && window.updateAdminDashboard) window.updateAdminDashboard()
    if (p === 'staff-dashboard' && window.updateStaffDashboard) window.updateStaffDashboard()
    if (p === 'admin-users' && window.renderPage) window.renderPage()
  } catch (_) {}
}
window._syncPatients = _syncPatients

// ── Patient own records sync (examinations, prescriptions, consultations) ────
async function _syncMyRecords() {
  if (window.state?.role !== 'patient') return
  try {
    const r = await fetch('api/patients/me.php')
    if (!r.ok) return
    const d = await r.json()
    if (!d.success) return
    const exams = d.examinations  || []
    const rxs   = d.prescriptions || []
    const cons  = d.consultations || []
    // Update state.user so patient-records page (reads user.examinations directly) works
    if (window.state.user) {
      window.state.user.examinations  = exams
      window.state.user.prescriptions = rxs
      window.state.user.consultations = cons
    }
    // Insert/update patient in patients[] so dashboard, exam-history, prescriptions pages work
    const uid = window.state.user?.id
    if (uid) {
      const idx = patients.findIndex(p => p.id === uid)
      if (idx >= 0) {
        patients[idx].examinations  = exams
        patients[idx].prescriptions = rxs
        patients[idx].consultations = cons
      } else {
        patients.push({ ...window.state.user, examinations: exams, prescriptions: rxs, consultations: cons })
      }
    }
    const page = window.state?.page
    const dataPages = new Set(['patient-dashboard','patient-records','patient-prescriptions','patient-exam-history'])
    if (dataPages.has(page)) window.renderPage()
  } catch (_) {}
}
window._syncMyRecords = _syncMyRecords

// ── Doctors sync ──────────────────────────────────────────────────
async function _syncDoctors() {
  try {
    // Patients aren't authorized for the staff/admin doctors/index.php route —
    // the public endpoint has everything the booking flow needs (schedule,
    // hours, photo, blocked dates) without staff-only fields like email.
    const endpoint = window.state?.role === 'patient' ? 'api/doctors/public.php' : 'api/doctors/index.php'
    const r = await fetch(endpoint)
    if (!r.ok) return
    const d = await r.json()
    if (!d.success || !Array.isArray(d.doctors)) return
    doctors.splice(0, doctors.length, ...d.doctors)
    const _dp = window.state?.page
    if (_dp === 'admin-dashboard' && window.updateAdminDashboard) window.updateAdminDashboard()
    if (_dp === 'staff-dashboard' && window.updateStaffDashboard) window.updateStaffDashboard()
    if (_dp === 'schedule' && window.renderPage) window.renderPage()
    if (_dp === 'admin-users' && window.renderPage) window.renderPage()
    if (['doctor-availability', 'patient-appts', 'patient-dashboard'].includes(_dp) && window.renderPage) window.renderPage()
  } catch (_) { /* keep mock data on network failure */ }
}
window._syncDoctors = _syncDoctors

// ── Staff & Admin sync ────────────────────────────────────────────
async function _syncStaff() {
  try {
    const r = await fetch('api/staff/index.php')
    if (!r.ok) return
    const d = await r.json()
    if (!d.success) return
    if (Array.isArray(d.staff))  staff.splice(0,  staff.length,  ...d.staff)
    if (Array.isArray(d.admins)) admins.splice(0, admins.length, ...d.admins)
    // Re-render user management page if currently open so photos appear
    if (window.state?.page === 'admin-users' && window.renderPage) window.renderPage()
  } catch (_) {}
}
window._syncStaff = _syncStaff

// ── Archived records sync ─────────────────────────────────────────
async function _syncArchives() {
  try {
    const r = await fetch('api/archive/index.php')
    if (!r.ok) return
    const d = await r.json()
    if (!d.success || !Array.isArray(d.records)) return
    archivedRecords.splice(0, archivedRecords.length, ...d.records)
    if (window.state?.page === 'admin-settings' && window.state?.params?.filter === 'archives' && window.renderPage) window.renderPage()
  } catch (_) {}
}
window._syncArchives = _syncArchives

// ── Contact messages sync ──────────────────────────────────────────
window._contactUnreadCount = 0

async function _syncContactMessages() {
  try {
    const r = await fetch('api/contact/index.php')
    if (!r.ok) return
    const d = await r.json()
    if (!d.success || !Array.isArray(d.messages)) return
    contactMessages.splice(0, contactMessages.length, ...d.messages)
    window._contactUnreadCount = d.unread_count || 0
    if (window._updateSidebarBadges) window._updateSidebarBadges()
    if (window.state?.page === 'contact-messages' && window.renderPage) window.renderPage()
  } catch (_) {}
}
window._syncContactMessages = _syncContactMessages

// ── Clinic settings + services sync ─────────────────────────────────
const _CLINIC_SETTINGS_PAGES = new Set([
  'admin-settings', 'doctor-availability', 'patient-appts', 'patient-dashboard'
])

async function _syncClinicSettings() {
  try {
    const r = await fetch('api/clinic/settings.php')
    if (!r.ok) return
    const d = await r.json()
    if (!d.success || !d.settings) return
    const s = d.settings
    Object.assign(clinicInfo, {
      name: s.name, tagline: s.tagline, address: s.address, phone: s.phone,
      mobile: s.mobile, email: s.email, hours: s.hours, tinNo: s.tinNo, phicNo: s.phicNo,
      foundedYear: s.foundedYear ?? null,
      logoUrl: s.logoUrl, heroUrl: s.heroUrl ?? null,
      mapLat: s.mapLat ?? null, mapLng: s.mapLng ?? null, mapEmbedUrl: s.mapEmbedUrl ?? null,
      galleryMaxPhotos: s.galleryMaxPhotos ?? null
    })
    Object.assign(consultationSettings, {
      defaultDuration: s.defaultDuration, maxAdvanceBooking: s.maxAdvanceBooking,
      minAdvanceBooking: s.minAdvanceBooking, maxApptsPerDoctorPerDay: s.maxApptsPerDoctorPerDay,
      morningStart: s.morningStart, morningEnd: s.morningEnd,
      afternoonStart: s.afternoonStart, afternoonEnd: s.afternoonEnd,
      lunchBreak: s.lunchBreak, clinicDays: s.clinicDays
    })
    window._clinicName     = clinicInfo.name     || 'Cana Optical Clinic'
    window._clinicAddress  = clinicInfo.address  || ''
    try {
      if (clinicInfo.name) localStorage.setItem('_opticana_clinicName', clinicInfo.name)
    } catch(_) {}
    const _lsBrand = document.getElementById('ls-brand-name')
    if (_lsBrand && clinicInfo.name) _lsBrand.textContent = clinicInfo.name
    if (clinicInfo.name) document.querySelectorAll('.brand-logo-name').forEach(el => { el.textContent = clinicInfo.name })
    document.querySelectorAll('.brand-clinic-name').forEach(el => { el.textContent = window._clinicName })
    if (s.logoUrl) {
      window._clinicLogoUrl = s.logoUrl
      if (window.syncLogoImages) window.syncLogoImages(s.logoUrl)
    }
    document.querySelectorAll('.topbar-clinic-name').forEach(el => { el.textContent = window._clinicName })
    const _addrParts = (window._clinicAddress || '').split(',').map(s=>s.trim()).filter(Boolean)
    const _clinicCity = _addrParts.length >= 2 ? _addrParts.slice(-2).join(', ') : (window._clinicAddress || 'Carmona, Cavite')
    document.querySelectorAll('.topbar-clinic-sub').forEach(el => { el.textContent = _clinicCity })
    if (window.renderSidebar) window.renderSidebar()
    if (window.renderTopbar) window.renderTopbar()
    if (_CLINIC_SETTINGS_PAGES.has(window.state?.page) && window.renderPage) window.renderPage()
  } catch (_) {}
}
window._syncClinicSettings = _syncClinicSettings

async function _syncServices() {
  try {
    const r = await fetch('api/services/index.php')
    if (!r.ok) return
    const d = await r.json()
    if (!d.success || !Array.isArray(d.services)) return
    CLINIC_SERVICES.splice(0, CLINIC_SERVICES.length, ...d.services)
    _svcNextId = Math.max(0, ...d.services.map(s => s.id)) + 1
    if (_CLINIC_SETTINGS_PAGES.has(window.state?.page) && window.renderPage) window.renderPage()
  } catch (_) {}
}
window._syncServices = _syncServices

// ── Notifications sync ────────────────────────────────────────────
window._notifications = []
window._unreadCount   = 0

async function _syncNotifications() {
  try {
    const r = await fetch('api/notifications/index.php')
    if (!r.ok) return
    const d = await r.json()
    if (!d.success) return
    window._notifications = d.notifications || []
    window._unreadCount   = d.unread_count  || 0
    if (window._updateNotifUI) window._updateNotifUI()
  } catch (_) {}
}
window._syncNotifications = _syncNotifications

// ── Activity log sync ─────────────────────────────────────────────
async function _syncActivityLog() {
  try {
    const r = await fetch('api/activity/log.php')
    if (!r.ok) return
    const d = await r.json()
    if (!d.success || !Array.isArray(d.logs)) return
    activityLog.splice(0, activityLog.length, ...d.logs)
    const page = window.state?.page
    if (page === 'activity-log') window.renderPage()
    else if (page === 'admin-dashboard' && window.updateAdminDashboard) window.updateAdminDashboard()
  } catch (_) {}
}
window._syncActivityLog = _syncActivityLog

function _shakeCard() {
  const card = document.querySelector('.login-card')
  if (!card) return
  card.classList.remove('login-shake')
  void card.offsetWidth
  card.classList.add('login-shake')
  card.addEventListener('animationend', () => card.classList.remove('login-shake'), { once: true })
}

function toggleLoginPw() {
  const input = document.getElementById('login-password')
  const icon  = document.getElementById('login-eye-icon')
  const EYE_OPEN   = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
  const EYE_CLOSED = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>'
  if (input.type === 'password') { input.type = 'text'; icon.innerHTML = EYE_CLOSED }
  else { input.type = 'password'; icon.innerHTML = EYE_OPEN }
}
