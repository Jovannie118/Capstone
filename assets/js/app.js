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

/* --------------------------- Document preview --------------------------- */

const PREVIEWABLE_IMAGE = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

function docModalEl() {
  let modal = document.getElementById('docModal');
  if (modal) return modal;

  modal = document.createElement('div');
  modal.id = 'docModal';
  modal.className = 'modal-overlay hidden';
  modal.innerHTML =
    '<div class="modal">' +
      '<div class="modal-head">' +
        '<h3 id="docModalTitle"></h3>' +
        '<button class="btn btn-outline btn-sm" id="docModalClose" type="button">Close</button>' +
      '</div>' +
      '<div class="modal-body" id="docModalBody"></div>' +
      '<div class="modal-foot">' +
        '<a id="docModalOpen" class="btn btn-primary btn-sm" target="_blank" rel="noopener">Open in new tab</a>' +
      '</div>' +
    '</div>';
  document.body.appendChild(modal);

  modal.addEventListener('click', (e) => { if (e.target === modal) closeDocModal(); });
  document.getElementById('docModalClose').addEventListener('click', closeDocModal);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDocModal(); });

  return modal;
}

function closeDocModal() {
  const modal = document.getElementById('docModal');
  if (!modal) return;
  modal.classList.add('hidden');
  document.getElementById('docModalBody').innerHTML = '';
}

/**
 * Opens an inline preview for a document.
 * @param {{id:number, doc_type:string, file_name:string}} doc
 */
function openDocModal(doc) {
  const modal = docModalEl();
  const url = API + 'view_document.php?id=' + doc.id;
  const ext = (doc.file_name.split('.').pop() || '').toLowerCase();
  const body = document.getElementById('docModalBody');

  document.getElementById('docModalTitle').textContent = doc.doc_type + ' \u2014 ' + doc.file_name;
  document.getElementById('docModalOpen').href = url;

  if (PREVIEWABLE_IMAGE.includes(ext)) {
    body.innerHTML = '<img src="' + url + '" alt="' + esc(doc.file_name) + '">';
  } else if (ext === 'pdf') {
    body.innerHTML = '<iframe src="' + url + '" title="' + esc(doc.file_name) + '"></iframe>';
  } else {
    body.innerHTML = '<p class="muted small">Preview isn\'t available for this file type. Use "Open in new tab" to view it.</p>';
  }

  modal.classList.remove('hidden');
}

function footer(text) {
  const el = document.createElement('div');
  el.className = 'footer';
  el.textContent = text || 'Paracale Scholarship Management System - demo build';
  document.body.appendChild(el);
}