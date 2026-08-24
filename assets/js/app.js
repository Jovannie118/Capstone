/* =====================================================================
   Paracale SMS - shared front-end helpers (plain JavaScript)
   ===================================================================== */

const API = 'api/';

/** GET a JSON endpoint. */
async function apiGet(path) {
  const res = await fetch(API + path, { credentials: 'same-origin' });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || 'Request failed.');
  return data;
}

/** POST JSON. */
async function apiPost(path, body) {
  const res = await fetch(API + path, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body || {}),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || 'Request failed.');
  return data;
}

/** POST a FormData (file uploads). Returns { ok, data }. */
async function apiUpload(path, formData) {
  const res = await fetch(API + path, { method: 'POST', credentials: 'same-origin', body: formData });
  const data = await res.json().catch(() => ({}));
  return { ok: res.ok, data };
}

const DOC_TYPES = [
  'Registration Form',
  'School ID with 3 Signatures',
  'Barangay Indigency',
  'Certificate of Grade (COG)',
];

const PUBLIC_NAV = [
  ['index.html', 'Home'],
  ['apply.html', 'Apply'],
  ['status.html', 'My application'],
  ['login.html', 'Admin sign in'],
];

const ADMIN_NAV = [
  ['dashboard.html', 'Dashboard'],
  ['applications.html', 'Applications'],
  ['verification.html', 'Verification'],
  ['approvals.html', 'Approvals'],
  ['reports.html', 'Reports'],
  ['notifications.html', 'Notifications'],
];

function esc(value) {
  return String(value == null ? '' : value).replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

function peso(n) {
  return '\u20B1' + Number(n || 0).toLocaleString();
}

function fmtDate(value) {
  if (!value) return '-';
  const d = new Date(String(value).replace(' ', 'T'));
  return isNaN(d) ? value : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function badgeClass(status) {
  const map = {
    approved: 'badge-approved', verified: 'badge-verified',
    submitted: 'badge-submitted', pending: 'badge-pending',
    under_verification: 'badge-verification', waitlisted: 'badge-waitlisted',
    rejected: 'badge-rejected', ranked: 'badge-ranked',
  };
  return 'badge ' + (map[status] || 'badge-submitted');
}

function statusBadge(status, label) {
  return '<span class="' + badgeClass(status) + '">' + esc(label || status) + '</span>';
}

/**
 * Renders the top bar and, for admin pages, blocks the page until the
 * PHP session says we are signed in.
 * @param {{active:string, admin?:boolean}} options
 */
async function initShell(options) {
  const active = options.active;
  const isAdminPage = !!options.admin;

  let session = { signedIn: false };
  try {
    session = await apiGet('auth.php?action=session');
  } catch (e) { /* offline / not configured */ }

  const links = session.signedIn ? ADMIN_NAV : PUBLIC_NAV;
  const nav = links
    .map(([href, label]) => '<a href="' + href + '"' + (href === active ? ' class="active"' : '') + '>' + label + '</a>')
    .join('');

  const signOut = session.signedIn
    ? '<button class="btn btn-gold btn-sm" id="signOutBtn" type="button">Sign out</button>'
    : '';

  const bar = document.createElement('header');
  bar.className = 'topbar';
  bar.innerHTML =
    '<div class="topbar-inner">' +
      '<a class="brand" href="' + (session.signedIn ? 'dashboard.html' : 'index.html') + '">' +
        '<span class="brand-mark">P</span>' +
        '<span>Paracale Scholarships<small>Scholarship Management System</small></span>' +
      '</a>' +
      '<nav class="nav">' + nav + '</nav>' + signOut +
    '</div>';
  document.body.prepend(bar);

  const btn = document.getElementById('signOutBtn');
  if (btn) {
    btn.addEventListener('click', async () => {
      await apiPost('auth.php?action=logout');
      location.href = 'index.html';
    });
  }

  if (isAdminPage && !session.signedIn) {
    const main = document.querySelector('main');
    if (main) {
      main.innerHTML =
        '<div class="panel center-card">' +
          '<h2>Staff area</h2>' +
          '<p class="muted small">Sign in with an administrator account to open the dashboard, applications, verification, approvals and reports.</p>' +
          '<a class="btn btn-primary" href="login.html">Go to admin sign in</a>' +
        '</div>';
    }
    return null;
  }

  return session;
}

function footer(text) {
  const el = document.createElement('div');
  el.className = 'footer';
  el.textContent = text || 'Paracale Scholarship Management System - demo build';
  document.body.appendChild(el);
}
