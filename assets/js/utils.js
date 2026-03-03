// ============================================================
// ENTERPRISE HRMS - UTILITY FUNCTIONS
// ============================================================

const API_BASE = 'api';

// ── HTTP CLIENT ──────────────────────────────────────────────
async function api(endpoint, options = {}) {
  const token = localStorage.getItem('hrms_token');
  const defaults = {
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
    },
  };
  const config = { ...defaults, ...options, headers: { ...defaults.headers, ...(options.headers || {}) } };
  const url = endpoint.startsWith('http') ? endpoint : `${API_BASE}/${endpoint.replace(/^\//, '')}`;

  try {
    const res = await fetch(url, config);

    // Handle 401 -> redirect to login
    if (res.status === 401) {
      localStorage.removeItem('hrms_token');
      localStorage.removeItem('hrms_user');
      window.location.href = 'index.html';
      return null;
    }
    return await res.json();
  } catch (e) {
    console.error('API Error:', e);
    toast('Network error. Check server connection.', 'error');
    return null;
  }
}

async function apiGet(endpoint, params = {}) {
  const qs = Object.keys(params).length ? '?' + new URLSearchParams(params).toString() : '';
  return api(`${endpoint}${qs}`, { method: 'GET' });
}

async function apiPost(endpoint, body) {
  return api(endpoint, { method: 'POST', body: JSON.stringify(body) });
}

async function apiPut(endpoint, body = {}) {
  return api(endpoint, { method: 'PUT', body: JSON.stringify(body) });
}

async function apiDelete(endpoint) {
  return api(endpoint, { method: 'DELETE' });
}

// ── TOAST NOTIFICATIONS ──────────────────────────────────────
function toast(message, type = 'success', duration = 3500) {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const icons = { success: 'circle-check', error: 'circle-exclamation', warning: 'triangle-exclamation', info: 'circle-info' };
  const colors = { success: '#10B981', error: '#EF4444', warning: '#F59E0B', info: '#3B82F6' };

  const t = document.createElement('div');
  t.className = 'toast';
  t.innerHTML = `
    <i class="fa-solid fa-${icons[type] || icons.info}" style="color:${colors[type] || colors.info};flex-shrink:0"></i>
    <span class="flex-1">${message}</span>
    <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white ml-2">
      <i class="fa-solid fa-xmark text-xs"></i>
    </button>`;
  container.appendChild(t);
  setTimeout(() => t.remove(), duration);
}

// ── FORMATTING ───────────────────────────────────────────────
function formatDate(dateStr, format = 'short') {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  if (isNaN(d)) return '—';
  const opts = {
    short: { day: '2-digit', month: 'short', year: 'numeric' },
    long:  { day: '2-digit', month: 'long', year: 'numeric' },
    time:  { hour: '2-digit', minute: '2-digit', hour12: true },
    datetime: { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true },
  };
  return d.toLocaleDateString('en-IN', opts[format] || opts.short);
}

function formatTime(datetime) {
  if (!datetime) return '—';
  const d = new Date(datetime);
  return isNaN(d) ? '—' : d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
}

function formatCurrency(amount, currency = '₹') {
  if (amount === null || amount === undefined || amount === '') return '—';
  const n = parseFloat(amount);
  if (isNaN(n)) return '—';
  return currency + n.toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function formatNumber(n, decimals = 0) {
  if (n === null || n === undefined) return '—';
  return parseFloat(n).toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

function timeAgo(dateStr) {
  const now  = new Date();
  const then = new Date(dateStr);
  const diff = Math.floor((now - then) / 1000);
  if (diff < 60)   return 'just now';
  if (diff < 3600) return `${Math.floor(diff/60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
  return `${Math.floor(diff/86400)}d ago`;
}

function capitalizeWords(str) {
  if (!str) return '';
  return str.split(/[\s_-]/).map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function truncate(str, len = 40) {
  if (!str) return '—';
  return str.length > len ? str.substring(0, len) + '…' : str;
}

// ── STATUS BADGES ─────────────────────────────────────────────
function empStatusBadge(status) {
  const map = {
    active: 'badge-green', probation: 'badge-yellow', notice: 'badge-orange',
    terminated: 'badge-red', resigned: 'badge-gray', retired: 'badge-purple',
  };
  return `<span class="badge ${map[status] || 'badge-gray'}">${capitalizeWords(status)}</span>`;
}

function leaveStatusBadge(status) {
  const map = {
    pending: 'badge-yellow', approved: 'badge-green', rejected: 'badge-red',
    cancelled: 'badge-gray', withdrawn: 'badge-gray',
  };
  return `<span class="badge ${map[status] || 'badge-gray'}">${capitalizeWords(status)}</span>`;
}

function attStatusBadge(status) {
  const map = {
    present: 'badge-green', absent: 'badge-red', late: 'badge-yellow',
    wfh: 'badge-blue', half_day: 'badge-orange', leave: 'badge-purple',
    holiday: 'badge-indigo', week_off: 'badge-gray', on_duty: 'badge-blue',
  };
  const labels = { wfh: 'WFH', half_day: 'Half Day', week_off: 'Week Off', on_duty: 'On Duty' };
  return `<span class="badge ${map[status] || 'badge-gray'}">${labels[status] || capitalizeWords(status)}</span>`;
}

function jobStatusBadge(status) {
  const map = { open: 'badge-green', draft: 'badge-gray', paused: 'badge-yellow', closed: 'badge-red', cancelled: 'badge-red' };
  return `<span class="badge ${map[status] || 'badge-gray'}">${capitalizeWords(status)}</span>`;
}

function appStageBadge(stage) {
  const map = {
    applied: 'badge-blue', screening: 'badge-indigo', shortlisted: 'badge-purple',
    phone_screen: 'badge-yellow', assessment: 'badge-yellow', technical_round: 'badge-orange',
    hr_round: 'badge-orange', final_round: 'badge-orange', offered: 'badge-green',
    accepted: 'badge-green', rejected: 'badge-red', withdrawn: 'badge-gray', on_hold: 'badge-gray',
  };
  return `<span class="badge ${map[stage] || 'badge-gray'}">${capitalizeWords(stage)}</span>`;
}

// ── AVATAR ────────────────────────────────────────────────────
const COLORS = ['#4F46E5','#7C3AED','#DB2777','#DC2626','#D97706','#059669','#0891B2','#0284C7'];
function avatarHtml(name, photo, size = 8, fontSize = 'xs') {
  if (photo) return `<img src="${photo}" class="w-${size} h-${size} rounded-full object-cover avatar">`;
  const initials = (name || '?').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
  const color    = COLORS[name.charCodeAt(0) % COLORS.length];
  return `<div class="w-${size} h-${size} rounded-full flex items-center justify-center text-${fontSize} font-bold text-white flex-shrink-0" style="background:${color}">${initials}</div>`;
}

// ── PAGINATION ────────────────────────────────────────────────
function renderPagination(containerId, pagination, onPageChange) {
  const el = document.getElementById(containerId);
  if (!el || !pagination) return;
  const { page, total_pages, total } = pagination;
  if (total_pages <= 1) { el.innerHTML = `<span class="text-xs text-gray-500">Showing all ${total} records</span>`; return; }

  let html = `<div class="flex items-center justify-between">
    <span class="text-xs text-gray-500">Page ${page} of ${total_pages} (${total} records)</span>
    <div class="pagination">`;
  html += `<button class="page-btn" onclick="${onPageChange}(${page-1})" ${page === 1 ? 'disabled' : ''}>
    <i class="fa-solid fa-chevron-left text-xs"></i></button>`;

  for (let i = Math.max(1, page-2); i <= Math.min(total_pages, page+2); i++) {
    html += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="${onPageChange}(${i})">${i}</button>`;
  }
  html += `<button class="page-btn" onclick="${onPageChange}(${page+1})" ${page === total_pages ? 'disabled' : ''}>
    <i class="fa-solid fa-chevron-right text-xs"></i></button>`;
  html += `</div></div>`;
  el.innerHTML = html;
}

// ── MODAL ─────────────────────────────────────────────────────
function openModal(title, body, footer = '', size = 'md') {
  const sizeMap = { sm: 'max-w-md', md: 'max-w-2xl', lg: 'max-w-4xl', xl: 'max-w-6xl', full: 'max-w-full mx-4' };
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalBody').innerHTML   = body;
  document.getElementById('modalFooter').innerHTML = footer;
  document.getElementById('modalBox').className    = `bg-white rounded-2xl shadow-2xl w-full ${sizeMap[size] || sizeMap.md} max-h-[90vh] flex flex-col`;
  document.getElementById('modal').classList.remove('hidden');
}

function closeModal() {
  document.getElementById('modal').classList.add('hidden');
  document.getElementById('modalBody').innerHTML   = '';
  document.getElementById('modalFooter').innerHTML = '';
}

window.addEventListener('click', e => {
  if (e.target === document.getElementById('modal')) closeModal();
});

// ── CONFIRM DIALOG ────────────────────────────────────────────
function confirmAction(message, onConfirm, title = 'Confirm Action') {
  openModal(title, `
    <div class="flex items-start gap-4">
      <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
        <i class="fa-solid fa-triangle-exclamation text-red-600 text-xl"></i>
      </div>
      <div>
        <p class="text-gray-700">${message}</p>
        <p class="text-xs text-gray-500 mt-1">This action cannot be undone.</p>
      </div>
    </div>`, `
    <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
    <button class="btn btn-danger" onclick="closeModal(); (${onConfirm.toString()})()">Confirm</button>`, 'sm');
}

// ── LOADING STATE ─────────────────────────────────────────────
function setLoading(elementId, loading = true) {
  const el = document.getElementById(elementId);
  if (!el) return;
  if (loading) {
    el.dataset.originalHtml = el.innerHTML;
    el.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Loading...`;
    el.disabled = true;
  } else {
    el.innerHTML = el.dataset.originalHtml || el.innerHTML;
    el.disabled = false;
  }
}

// ── FORM HELPERS ──────────────────────────────────────────────
function formToObject(formEl) {
  const data = {};
  new FormData(formEl).forEach((v, k) => {
    if (data[k] !== undefined) {
      data[k] = Array.isArray(data[k]) ? [...data[k], v] : [data[k], v];
    } else { data[k] = v; }
  });
  return data;
}

function buildSelectOptions(items, valueKey, labelKey, selectedValue = '') {
  return items.map(item =>
    `<option value="${item[valueKey]}" ${item[valueKey] == selectedValue ? 'selected' : ''}>${item[labelKey]}</option>`
  ).join('');
}

// ── DEBOUNCE ──────────────────────────────────────────────────
function debounce(fn, delay = 300) {
  let timer;
  return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
}

// ── CLOSE MENUS ON OUTSIDE CLICK ──────────────────────────────
function closeMenus() {
  document.getElementById('notifPanel')?.classList.add('hidden');
  document.getElementById('userMenu')?.classList.add('hidden');
}
document.addEventListener('click', (e) => {
  if (!e.target.closest('[onclick="toggleNotifications()"]') && !e.target.closest('#notifPanel')) {
    document.getElementById('notifPanel')?.classList.add('hidden');
  }
  if (!e.target.closest('[onclick="toggleUserMenu()"]') && !e.target.closest('#userMenu')) {
    document.getElementById('userMenu')?.classList.add('hidden');
  }
});

// ── CHART HELPERS ─────────────────────────────────────────────
function createDoughnutChart(canvasId, labels, data, colors) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  if (ctx._chart) ctx._chart.destroy();
  ctx._chart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }],
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '65%',
      plugins: { legend: { position: 'right', labels: { boxWidth: 10, padding: 12, font: { size: 11 } } } },
    },
  });
}

function createBarChart(canvasId, labels, datasets, options = {}) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  if (ctx._chart) ctx._chart.destroy();
  ctx._chart = new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: datasets.length > 1, labels: { font: { size: 11 } } } },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: { grid: { color: '#F1F5F9' }, ticks: { font: { size: 11 } }, beginAtZero: true },
      },
      ...options,
    },
  });
}

function createLineChart(canvasId, labels, datasets) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  if (ctx._chart) ctx._chart.destroy();
  ctx._chart = new Chart(ctx, {
    type: 'line',
    data: { labels, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: datasets.length > 1, labels: { font: { size: 11 } } } },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: { grid: { color: '#F1F5F9' }, ticks: { font: { size: 11 } }, beginAtZero: true },
      },
      elements: { line: { tension: 0.4 }, point: { radius: 3 } },
    },
  });
}
