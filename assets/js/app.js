// ============================================================
// ENTERPRISE HRMS - MAIN APPLICATION
// ============================================================

let currentUser  = null;
let currentPage  = 'dashboard';
let currentData  = {};  // Page-level state

// ── NAV MENU DEFINITION ──────────────────────────────────────
const NAV_MENU = [
  { group: 'Main' },
  { id: 'dashboard',    icon: 'gauge',            label: 'Dashboard',         perm: null },
  { id: 'profile',      icon: 'user-circle',       label: 'My Profile',        perm: null },

  { group: 'People' },
  { id: 'employees',    icon: 'users',             label: 'Employees',         perm: 'employees.view' },
  { id: 'departments',  icon: 'sitemap',           label: 'Departments',       perm: 'employees.view' },
  { id: 'org-chart',    icon: 'diagram-project',   label: 'Org Chart',         perm: 'employees.view' },

  { group: 'Workforce' },
  { id: 'attendance',   icon: 'clock',             label: 'Attendance',        perm: 'attendance.view' },
  { id: 'leave',        icon: 'calendar-xmark',    label: 'Leave Management',  perm: 'leave.view' },

  { group: 'Payroll' },
  { id: 'payroll',      icon: 'money-bill-wave',   label: 'Payroll',           perm: 'payroll.view' },
  { id: 'my-payslips',  icon: 'file-invoice-dollar',label: 'My Payslips',      perm: null },

  { group: 'Talent' },
  { id: 'recruitment',  icon: 'user-plus',         label: 'Recruitment',       perm: 'recruitment.view' },
  { id: 'performance',  icon: 'chart-line',        label: 'Performance',       perm: 'performance.view' },
  { id: 'training',     icon: 'graduation-cap',    label: 'Training',          perm: null },

  { group: 'Analytics' },
  { id: 'reports',      icon: 'chart-pie',         label: 'Reports',           perm: 'reports.view' },
];

// ── INIT ─────────────────────────────────────────────────────
(async function init() {
  const token = localStorage.getItem('hrms_token');
  if (!token) { window.location.href = 'index.html'; return; }

  const res = await apiGet('auth/me');
  if (!res?.success) { window.location.href = 'index.html'; return; }

  currentUser = res.data;
  setupUI();
  loadNotifications();
  navigate(location.hash.slice(1) || 'dashboard');
})();

// ── SETUP UI ──────────────────────────────────────────────────
function setupUI() {
  const u    = currentUser.user;
  const emp  = u.employee;
  const name = emp ? `${emp.first_name} ${emp.last_name}` : u.email;
  const role = u.role_name || '';

  // Sidebar
  document.getElementById('companyName').textContent  = 'TechVision Pvt Ltd';
  document.getElementById('sidebarUserName').textContent = name;
  document.getElementById('sidebarUserRole').textContent = role;
  document.getElementById('sidebarAvatar').textContent   = name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();

  // Header
  document.getElementById('headerUserName').textContent = name.split(' ')[0];
  document.getElementById('headerAvatar').textContent   = name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
  document.getElementById('menuUserName').textContent   = name;
  document.getElementById('menuUserEmail').textContent  = u.email;

  // Render nav
  buildNav();
}

function buildNav() {
  const perms  = currentUser.permissions || [];
  const nav    = document.getElementById('sidebarNav');
  const isSA   = currentUser.user.role === 'super_admin';
  let html = '';

  NAV_MENU.forEach(item => {
    if (item.group) {
      html += `<div class="nav-group-label">${item.group}</div>`;
      return;
    }
    if (item.perm && !isSA && !perms.includes(item.perm)) return;
    html += `<button class="nav-item" data-page="${item.id}" onclick="navigate('${item.id}')">
      <i class="fa-solid fa-${item.icon} w-5 text-center text-sm"></i>
      <span>${item.label}</span>
    </button>`;
  });

  nav.innerHTML = html;
}

function setActiveNav(page) {
  document.querySelectorAll('.nav-item[data-page]').forEach(el => {
    el.classList.toggle('active', el.dataset.page === page);
  });
}

// ── NAVIGATION ────────────────────────────────────────────────
function navigate(page) {
  currentPage = page;
  setActiveNav(page);
  location.hash = page;

  const titles = {
    dashboard: 'Dashboard', employees: 'Employees', departments: 'Departments',
    'org-chart': 'Organization Chart', attendance: 'Attendance', leave: 'Leave Management',
    payroll: 'Payroll Management', 'my-payslips': 'My Payslips', recruitment: 'Recruitment & ATS',
    performance: 'Performance Management', training: 'Learning & Development',
    reports: 'Reports & Analytics', settings: 'Settings', profile: 'My Profile',
  };

  document.getElementById('pageTitle').textContent = titles[page] || capitalizeWords(page);
  document.getElementById('breadcrumb').textContent = 'Home / ' + (titles[page] || capitalizeWords(page));

  const content = document.getElementById('mainContent');
  content.innerHTML = `<div class="flex items-center justify-center h-64"><div class="text-center"><i class="fa-solid fa-spinner fa-spin text-indigo-600 text-3xl mb-3"></i><p class="text-gray-500">Loading...</p></div></div>`;

  switch (page) {
    case 'dashboard':   loadDashboard(); break;
    case 'employees':   loadEmployees(); break;
    case 'departments': loadDepartments(); break;
    case 'org-chart':   loadOrgChart(); break;
    case 'attendance':  loadAttendance(); break;
    case 'leave':       loadLeave(); break;
    case 'payroll':     loadPayroll(); break;
    case 'my-payslips': loadMyPayslips(); break;
    case 'recruitment': loadRecruitment(); break;
    case 'performance': loadPerformance(); break;
    case 'training':    loadTraining(); break;
    case 'reports':     loadReports(); break;
    case 'settings':    loadSettings(); break;
    case 'profile':     loadProfile(); break;
    default:            content.innerHTML = `<div class="empty-state"><i class="fa-solid fa-wrench"></i><p class="font-medium">Coming Soon</p><p class="text-sm">This module is under development</p></div>`;
  }
}

// ═══════════════════════════════════════════════════════════════
// DASHBOARD
// ═══════════════════════════════════════════════════════════════
async function loadDashboard() {
  const res = await apiGet('dashboard');
  if (!res?.success) return;
  const d = res.data;
  const isEmp = currentUser.user.role === 'employee';

  if (isEmp) renderEmployeeDashboard(d);
  else renderHRDashboard(d);
}

function renderHRDashboard(d) {
  const o   = d.employee_overview || {};
  const att = d.today_attendance  || {};
  const content = document.getElementById('mainContent');

  content.innerHTML = `
  <!-- KPI Row -->
  <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-5 gap-4 mb-6">
    ${statCard('Total Employees', o.total || 0, 'users', '#4F46E5', `Active: ${o.active||0} | Probation: ${o.probation||0}`)}
    ${statCard('Present Today', att.present || 0, 'user-check', '#10B981', `Late: ${att.late||0} | WFH: ${att.wfh||0}`)}
    ${statCard('On Leave', att.on_leave || 0, 'calendar-xmark', '#F59E0B', `Pending approvals: ${d.pending_leaves||0}`)}
    ${statCard('Open Positions', d.open_jobs || 0, 'user-plus', '#8B5CF6', 'Active job postings')}
    ${statCard('New This Month', o.new_this_month || 0, 'user-plus', '#06B6D4', `Exits: ${o.exits_this_month||0}`)}
  </div>

  <!-- Alerts Row -->
  ${d.pending_leaves > 0 ? `<div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 flex items-center gap-3">
    <i class="fa-solid fa-bell text-amber-500"></i>
    <span class="text-sm text-amber-800"><strong>${d.pending_leaves} leave request(s)</strong> pending your approval</span>
    <button onclick="navigate('leave')" class="ml-auto btn btn-sm btn-outline">Review</button>
  </div>` : ''}

  <!-- Charts Row -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <!-- Headcount trend -->
    <div class="card p-5 lg:col-span-2">
      <div class="section-header"><h3 class="section-title">Headcount Trend (Last 6 Months)</h3></div>
      <div style="height:220px"><canvas id="headcountChart"></canvas></div>
    </div>
    <!-- Dept breakdown -->
    <div class="card p-5">
      <div class="section-header"><h3 class="section-title">By Department</h3></div>
      <div style="height:220px"><canvas id="deptChart"></canvas></div>
    </div>
  </div>

  <!-- Bottom Row -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Today Attendance -->
    <div class="card p-5">
      <h3 class="section-title mb-4">Today's Attendance</h3>
      ${attBar('Present',  att.present  || 0, att.total_employees || 1, '#10B981')}
      ${attBar('Absent',   att.absent   || 0, att.total_employees || 1, '#EF4444')}
      ${attBar('Late',     att.late     || 0, att.total_employees || 1, '#F59E0B')}
      ${attBar('WFH',      att.wfh      || 0, att.total_employees || 1, '#3B82F6')}
      ${attBar('On Leave', att.on_leave || 0, att.total_employees || 1, '#8B5CF6')}
      <button onclick="navigate('attendance')" class="mt-3 w-full btn btn-secondary btn-sm">View Details</button>
    </div>

    <!-- Birthdays & Anniversaries -->
    <div class="card p-5">
      <h3 class="section-title mb-3">Birthdays & Anniversaries</h3>
      ${(d.upcoming_birthdays||[]).map(b=>`
        <div class="flex items-center gap-3 py-2 border-b border-gray-50 last:border-0">
          ${avatarHtml(b.display_name || (b.first_name+' '+b.last_name), b.profile_photo, 7)}
          <div class="min-w-0">
            <p class="text-sm font-medium truncate">${b.first_name} ${b.last_name}</p>
            <p class="text-xs text-gray-500"><i class="fa-solid fa-birthday-cake text-pink-400 mr-1"></i>${formatDate(b.date_of_birth,'short')}</p>
          </div>
        </div>`).join('') || '<p class="text-sm text-gray-400 text-center py-4">No upcoming birthdays</p>'}

      ${(d.anniversaries||[]).slice(0,3).map(a=>`
        <div class="flex items-center gap-3 py-2 border-b border-gray-50 last:border-0">
          ${avatarHtml(a.first_name+' '+a.last_name, a.profile_photo, 7)}
          <div class="min-w-0">
            <p class="text-sm font-medium truncate">${a.first_name} ${a.last_name}</p>
            <p class="text-xs text-gray-500"><i class="fa-solid fa-star text-amber-400 mr-1"></i>${a.years_completed} year(s)</p>
          </div>
        </div>`).join('')}
    </div>

    <!-- Recent Payroll & Recruitment -->
    <div class="card p-5">
      <h3 class="section-title mb-3">Quick Overview</h3>
      <div class="space-y-3">
        <div class="bg-blue-50 rounded-lg p-3">
          <div class="text-xs text-blue-600 font-semibold uppercase mb-2">Latest Payroll</div>
          ${(d.recent_payroll||[]).slice(0,2).map(p=>`
            <div class="flex justify-between text-sm py-1">
              <span>${monthName(p.month)} ${p.year}</span>
              <span class="font-medium">${formatCurrency(p.total_net)}</span>
            </div>`).join('') || '<p class="text-xs text-gray-400">No payroll data</p>'}
        </div>
        <div class="bg-purple-50 rounded-lg p-3">
          <div class="text-xs text-purple-600 font-semibold uppercase mb-2">Open Positions</div>
          ${(d.open_recruitment||[]).slice(0,3).map(j=>`
            <div class="flex justify-between text-sm py-1">
              <span class="truncate">${j.title}</span>
              <span class="text-gray-500 text-xs">${j.applications} apps</span>
            </div>`).join('') || '<p class="text-xs text-gray-400">No open positions</p>'}
        </div>
      </div>
    </div>
  </div>`;

  // Render charts after DOM update
  setTimeout(() => {
    const labels  = (d.headcount_trend||[]).map(h => h.month);
    const joins   = (d.headcount_trend||[]).map(h => h.joinings);
    createLineChart('headcountChart', labels, [{
      label: 'New Joinings', data: joins,
      borderColor: '#4F46E5', backgroundColor: 'rgba(79,70,229,0.1)', fill: true,
    }]);

    const depts = (d.dept_headcount||[]).map(d => d.name);
    const counts= (d.dept_headcount||[]).map(d => d.count);
    createDoughnutChart('deptChart', depts, counts,
      ['#4F46E5','#7C3AED','#10B981','#F59E0B','#EF4444','#06B6D4','#F97316','#84CC16']);
  }, 50);
}

function renderEmployeeDashboard(d) {
  const att = d.today_attendance || {};
  const m   = d.month_attendance || {};
  document.getElementById('mainContent').innerHTML = `
  <!-- Top Row -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    ${statCard('Today', att.check_in ? formatTime(att.check_in) : 'Not Checked In', 'clock', att.check_in ? '#10B981' : '#6B7280', att.check_out ? `Out: ${formatTime(att.check_out)}` : (att.check_in ? 'Currently In' : 'Mark attendance below'))}
    ${statCard('Present This Month', m.present || 0, 'calendar-check', '#4F46E5', `Late: ${m.late||0}`)}
    ${statCard('Leave Balance', (d.leave_balances||[]).find(l=>l.code==='CL')?.balance || 0, 'calendar', '#F59E0B', 'Casual Leave remaining')}
    ${statCard('Pending Requests', d.pending_leaves || 0, 'hourglass-half', '#8B5CF6', 'Awaiting approval')}
  </div>

  <!-- Check-In / Check-Out Widget -->
  <div class="card p-6 mb-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div>
        <h3 class="font-semibold text-gray-800 mb-1">Today's Attendance</h3>
        <p class="text-sm text-gray-500">${new Date().toLocaleDateString('en-IN', {weekday:'long', year:'numeric', month:'long', day:'numeric'})}</p>
        <div class="mt-2">${att.status ? attStatusBadge(att.status) : '<span class="badge badge-gray">Not Marked</span>'}</div>
      </div>
      <div class="flex gap-3">
        ${!att.check_in ? `<button onclick="checkIn()" class="btn btn-primary"><i class="fa-solid fa-right-to-bracket"></i>Check In</button>` :
          !att.check_out ? `<button onclick="checkOut()" class="btn btn-success"><i class="fa-solid fa-right-from-bracket"></i>Check Out</button>` :
          `<div class="text-center"><p class="text-sm text-gray-500">Checked out at ${formatTime(att.check_out)}</p><p class="text-xs text-gray-400">Total: ${att.total_hours || 0}h</p></div>`}
      </div>
    </div>
  </div>

  <!-- Middle Row -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <!-- Leave Balances -->
    <div class="card p-5">
      <div class="section-header"><h3 class="section-title">Leave Balances</h3>
        <button onclick="navigate('leave')" class="text-xs text-indigo-600">View All</button></div>
      <div class="space-y-3">
        ${(d.leave_balances||[]).slice(0,5).map(lb => `
        <div>
          <div class="flex justify-between text-xs text-gray-600 mb-1">
            <span><span class="w-2 h-2 rounded-full inline-block mr-1.5" style="background:${lb.color}"></span>${lb.name}</span>
            <span class="font-medium">${lb.balance} / ${lb.allocated} days</span>
          </div>
          <div class="progress-bar">
            <div class="progress-fill" style="width:${lb.allocated > 0 ? Math.min((lb.used/lb.allocated)*100,100) : 0}%;background:${lb.color}"></div>
          </div>
        </div>`).join('')}
      </div>
      <button onclick="applyLeaveModal()" class="mt-4 w-full btn btn-outline btn-sm">+ Apply Leave</button>
    </div>

    <!-- My Goals -->
    <div class="card p-5">
      <div class="section-header"><h3 class="section-title">My Goals</h3>
        <button onclick="navigate('performance')" class="text-xs text-indigo-600">View All</button></div>
      ${(d.my_goals||[]).length ? d.my_goals.map(g => `
        <div class="border-b border-gray-50 py-2.5 last:border-0">
          <div class="flex justify-between items-start gap-2 mb-1">
            <p class="text-sm font-medium text-gray-800 leading-tight">${g.title}</p>
            <span class="badge ${g.status==='active'?'badge-green':g.status==='completed'?'badge-blue':'badge-gray'} flex-shrink-0 text-xs">${g.status}</span>
          </div>
          ${g.achievement != null ? `<div class="progress-bar mt-1"><div class="progress-fill bg-indigo-500" style="width:${Math.min(g.achievement,100)}%"></div></div>` : ''}
          <p class="text-xs text-gray-400 mt-1">Due: ${formatDate(g.due_date)}</p>
        </div>`).join('') : '<div class="empty-state"><i class="fa-solid fa-bullseye"></i><p class="text-sm">No goals set yet</p></div>'}
    </div>

    <!-- Notifications -->
    <div class="card p-5">
      <div class="section-header"><h3 class="section-title">Notifications</h3>
        <span class="badge badge-indigo">${(d.notifications||[]).filter(n=>!n.is_read).length} new</span></div>
      <div class="space-y-2">
        ${(d.notifications||[]).slice(0,6).map(n => `
        <div class="flex gap-3 p-2 rounded-lg ${n.is_read ? 'bg-gray-50' : 'bg-indigo-50 border border-indigo-100'} text-sm">
          <i class="fa-solid fa-bell mt-0.5 ${n.is_read ? 'text-gray-400' : 'text-indigo-500'} flex-shrink-0 text-xs"></i>
          <div class="min-w-0">
            <p class="font-medium text-xs ${n.is_read ? 'text-gray-600' : 'text-indigo-700'} leading-tight">${n.title}</p>
            <p class="text-xs text-gray-500 mt-0.5 leading-tight">${truncate(n.message, 60)}</p>
            <p class="text-xs text-gray-400 mt-0.5">${timeAgo(n.created_at)}</p>
          </div>
        </div>`).join('') || '<p class="text-sm text-gray-400 text-center py-4">No notifications</p>'}
      </div>
    </div>
  </div>`;
}

// ═══════════════════════════════════════════════════════════════
// EMPLOYEES
// ═══════════════════════════════════════════════════════════════
let empPage = 1, empFilters = {};

async function loadEmployees() {
  document.getElementById('mainContent').innerHTML = `
  <div class="card p-4 mb-4">
    <div class="flex flex-wrap items-center gap-3">
      <div class="flex-1 min-w-48 relative">
        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="empSearch" placeholder="Search by name, ID, email..." class="form-input pl-9" oninput="debounce(searchEmployees, 400)(this.value)">
      </div>
      <select id="empDeptFilter" class="form-input w-44" onchange="filterEmployees()">
        <option value="">All Departments</option>
      </select>
      <select id="empStatusFilter" class="form-input w-40" onchange="filterEmployees()">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="probation">Probation</option>
        <option value="notice">Notice</option>
        <option value="terminated">Terminated</option>
      </select>
      <button onclick="openAddEmployee()" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i>Add Employee
      </button>
    </div>
  </div>

  <!-- Stats -->
  <div id="empStats" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4"></div>

  <!-- Table -->
  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="data-table">
        <thead><tr>
          <th>Employee</th><th>Department</th><th>Designation</th>
          <th>Type</th><th>Status</th><th>Joined</th><th>Actions</th>
        </tr></thead>
        <tbody id="empTableBody"><tr><td colspan="7" class="text-center py-8 text-gray-500"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading...</td></tr></tbody>
      </table>
    </div>
    <div class="px-4 py-3 border-t bg-gray-50" id="empPagination"></div>
  </div>`;

  await loadEmpStats();
  await loadDeptOptions('empDeptFilter');
  await fetchEmployees();
}

async function loadEmpStats() {
  const res = await apiGet('employees/stats');
  if (!res?.success) return;
  const o = res.data.overview;
  document.getElementById('empStats').innerHTML = [
    statCard('Total', o.total, 'users', '#4F46E5', ''),
    statCard('Active', o.active, 'user-check', '#10B981', ''),
    statCard('On Probation', o.probation, 'hourglass', '#F59E0B', ''),
    statCard('Contracts/Interns', +o.contract + +o.interns, 'user-clock', '#8B5CF6', ''),
  ].join('');
}

async function fetchEmployees(page = 1) {
  empPage = page;
  const params = { page, limit: 20, ...empFilters };
  const res = await apiGet('employees', params);
  if (!res?.success) return;

  const tbody = document.getElementById('empTableBody');
  if (!res.data.length) {
    tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-users"></i><p>No employees found</p></div></td></tr>`;
    return;
  }

  tbody.innerHTML = res.data.map(e => `
    <tr>
      <td>
        <div class="flex items-center gap-3">
          ${avatarHtml(`${e.first_name} ${e.last_name}`, e.profile_photo, 8)}
          <div>
            <p class="font-medium text-gray-800 text-sm">${e.first_name} ${e.last_name}</p>
            <p class="text-xs text-gray-500">${e.employee_id} · ${e.work_email}</p>
          </div>
        </div>
      </td>
      <td class="text-sm">${e.department_name || '—'}</td>
      <td class="text-sm">${e.designation_title || '—'}</td>
      <td>${e.employment_type ? `<span class="badge badge-blue">${capitalizeWords(e.employment_type)}</span>` : '—'}</td>
      <td>${empStatusBadge(e.employment_status)}</td>
      <td class="text-sm text-gray-600">${formatDate(e.date_joined)}</td>
      <td>
        <div class="flex gap-1">
          <button onclick="viewEmployee(${e.id})" class="btn btn-secondary btn-sm" title="View">
            <i class="fa-solid fa-eye text-gray-500"></i>
          </button>
          <button onclick="editEmployee(${e.id})" class="btn btn-secondary btn-sm" title="Edit">
            <i class="fa-solid fa-pen text-indigo-500"></i>
          </button>
        </div>
      </td>
    </tr>`).join('');

  renderPagination('empPagination', res.pagination, 'fetchEmployees');
}

async function loadDeptOptions(selectId, selectedId = '') {
  const res = await apiGet('departments');
  if (!res?.success) return;
  const sel = document.getElementById(selectId);
  if (!sel) return;
  const existing = sel.options[0]?.text || '';
  sel.innerHTML  = `<option value="">${existing || 'All Departments'}</option>` +
    res.data.map(d => `<option value="${d.id}" ${d.id==selectedId?'selected':''}>${d.name}</option>`).join('');
}

function searchEmployees(val) { empFilters.search = val; fetchEmployees(1); }
function filterEmployees() {
  empFilters.department_id      = document.getElementById('empDeptFilter')?.value || '';
  empFilters.employment_status  = document.getElementById('empStatusFilter')?.value || '';
  fetchEmployees(1);
}

async function viewEmployee(id) {
  const res = await apiGet(`employees/${id}`);
  if (!res?.success) { toast('Failed to load employee', 'error'); return; }
  const e = res.data;

  openModal(`${e.first_name} ${e.last_name} — ${e.employee_id}`, `
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Left: Photo + basic -->
      <div class="text-center">
        ${avatarHtml(`${e.first_name} ${e.last_name}`, e.profile_photo, 20, 'xl')}
        <h3 class="font-bold text-lg mt-3">${e.first_name} ${e.last_name}</h3>
        <p class="text-gray-500 text-sm">${e.designation_title || '—'}</p>
        <p class="text-gray-400 text-xs mt-1">${e.department_name || '—'}</p>
        <div class="mt-2">${empStatusBadge(e.employment_status)}</div>
        <div class="mt-4 space-y-1 text-sm text-left">
          <div class="flex gap-2"><i class="fa-solid fa-envelope text-gray-400 w-4"></i><span class="text-gray-700">${e.work_email}</span></div>
          ${e.work_phone ? `<div class="flex gap-2"><i class="fa-solid fa-phone text-gray-400 w-4"></i><span>${e.work_phone}</span></div>` : ''}
          ${e.location_name ? `<div class="flex gap-2"><i class="fa-solid fa-location-dot text-gray-400 w-4"></i><span>${e.location_name}</span></div>` : ''}
        </div>
      </div>
      <!-- Middle: Details -->
      <div class="md:col-span-2">
        <div class="tab-nav" id="empDetailTabs">
          <button class="tab-btn active" onclick="switchTab('empDetailTabs','empTab','personal')">Personal</button>
          <button class="tab-btn" onclick="switchTab('empDetailTabs','empTab','employment')">Employment</button>
          <button class="tab-btn" onclick="switchTab('empDetailTabs','empTab','bank')">Bank & Tax</button>
          <button class="tab-btn" onclick="switchTab('empDetailTabs','empTab','leaves')">Leave Balance</button>
          <button class="tab-btn" onclick="switchTab('empDetailTabs','empTab','assets')">Assets</button>
        </div>

        <div class="mt-4">
          <div id="empTab-personal" class="tab-panel active">
            ${detailGrid([
              ['Employee ID', e.employee_id],['Gender', capitalizeWords(e.gender)],
              ['Date of Birth', formatDate(e.date_of_birth)],['Marital Status', capitalizeWords(e.marital_status)],
              ['Blood Group', e.blood_group],['Nationality', e.nationality],
              ['Personal Email', e.personal_email],['Personal Phone', e.personal_phone],
              ['Emergency Contact', e.emergency_contact_name || '—'],['Emergency Phone', e.emergency_contact_phone || '—'],
            ])}
          </div>
          <div id="empTab-employment" class="tab-panel">
            ${detailGrid([
              ['Department', e.department_name],['Designation', e.designation_title],
              ['Location', e.location_name],['Manager', e.manager_name || '—'],
              ['Employment Type', capitalizeWords(e.employment_type)],['Date Joined', formatDate(e.date_joined)],
              ['Confirmation Date', formatDate(e.confirmation_date)],['Probation End', formatDate(e.probation_end_date)],
              ['PAN Number', e.pan_number || '—'],['PF Number', e.pf_number || '—'],
              ['UAN Number', e.uan_number || '—'],['ESI Number', e.esi_number || '—'],
            ])}
          </div>
          <div id="empTab-bank" class="tab-panel">
            ${detailGrid([
              ['Bank Name', e.bank_name || '—'],['Account Number', e.bank_account_number ? '••••' + e.bank_account_number.slice(-4) : '—'],
              ['IFSC Code', e.bank_ifsc_code || '—'],['Branch', e.bank_branch || '—'],
            ])}
          </div>
          <div id="empTab-leaves" class="tab-panel">
            <div class="space-y-2">
              ${(e.leave_balances||[]).map(lb => `
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                  <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full" style="background:${lb.color}"></span>
                    <span class="text-sm font-medium">${lb.leave_type_name}</span>
                  </div>
                  <div class="text-right">
                    <span class="text-sm font-bold">${lb.balance}</span>
                    <span class="text-xs text-gray-500"> / ${lb.allocated} days</span>
                  </div>
                </div>`).join('') || '<p class="text-gray-400 text-sm text-center py-4">No leave data</p>'}
            </div>
          </div>
          <div id="empTab-assets" class="tab-panel">
            ${(e.assets||[]).length ? `<table class="data-table"><thead><tr><th>Tag</th><th>Asset</th><th>Category</th><th>Assigned</th></tr></thead><tbody>
              ${e.assets.map(a=>`<tr><td>${a.asset_tag}</td><td>${a.name}</td><td>${capitalizeWords(a.category)}</td><td>${formatDate(a.assigned_at)}</td></tr>`).join('')}
            </tbody></table>` : '<div class="empty-state"><i class="fa-solid fa-laptop"></i><p class="text-sm">No assets assigned</p></div>'}
          </div>
        </div>
      </div>
    </div>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Close</button>
     <button class="btn btn-primary" onclick="closeModal(); editEmployee(${e.id})"><i class="fa-solid fa-pen"></i>Edit</button>`,
    'lg');
}

function openAddEmployee() {
  openModal('Add New Employee', buildEmployeeForm({}),
    `<button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
     <button class="btn btn-primary" onclick="submitAddEmployee()"><i class="fa-solid fa-save"></i>Save Employee</button>`,
    'lg');
}

async function editEmployee(id) {
  const res = await apiGet(`employees/${id}`);
  if (!res?.success) return;
  openModal(`Edit: ${res.data.first_name} ${res.data.last_name}`, buildEmployeeForm(res.data),
    `<button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
     <button class="btn btn-primary" onclick="submitEditEmployee(${id})"><i class="fa-solid fa-save"></i>Update</button>`,
    'lg');
}

function buildEmployeeForm(e) {
  return `<form id="empForm">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      ${formField('first_name','First Name','text',e.first_name,'required')}
      ${formField('last_name','Last Name','text',e.last_name,'required')}
      ${formField('work_email','Work Email','email',e.work_email,'required')}
      ${!e.id ? formField('password','Password','password','Admin@123','required') : ''}
      ${formSelect('gender','Gender',['','male','female','other'],e.gender,'required')}
      ${formField('date_of_birth','Date of Birth','date',e.date_of_birth)}
      ${formField('personal_phone','Personal Phone','text',e.personal_phone)}
      ${formField('date_joined','Date Joined','date',e.date_joined,'required')}
      ${formField('department_id','Department','text',e.department_id)}
      ${formField('designation_id','Designation','text',e.designation_id)}
      ${formSelect('employment_type','Employment Type',['full_time','part_time','contract','intern','consultant'],e.employment_type)}
      ${formSelect('employment_status','Status',['active','probation','notice','terminated'],e.employment_status)}
      ${formField('pan_number','PAN Number','text',e.pan_number)}
      ${formField('bank_name','Bank Name','text',e.bank_name)}
      ${formField('bank_account_number','Account Number','text',e.bank_account_number)}
      ${formField('bank_ifsc_code','IFSC Code','text',e.bank_ifsc_code)}
    </div>
  </form>`;
}

async function submitAddEmployee() {
  const data = formToObject(document.getElementById('empForm'));
  const res  = await apiPost('employees', data);
  if (res?.success) { closeModal(); toast('Employee created!'); fetchEmployees(1); }
  else toast(res?.message || 'Failed to create', 'error');
}

async function submitEditEmployee(id) {
  const data = formToObject(document.getElementById('empForm'));
  const res  = await apiPut(`employees/${id}`, data);
  if (res?.success) { closeModal(); toast('Employee updated!'); fetchEmployees(empPage); }
  else toast(res?.message || 'Failed to update', 'error');
}

// ═══════════════════════════════════════════════════════════════
// ATTENDANCE
// ═══════════════════════════════════════════════════════════════
async function loadAttendance() {
  const isEmp = currentUser.user.role === 'employee';
  document.getElementById('mainContent').innerHTML = `
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4" id="attStats"></div>
    <div class="card p-4 mb-4">
      <div class="flex flex-wrap gap-3 items-center">
        ${!isEmp ? `<select id="attDeptFilter" class="form-input w-44" onchange="fetchAttendance()">
          <option value="">All Departments</option></select>` : ''}
        <input type="month" id="attMonth" class="form-input w-44" value="${new Date().toISOString().slice(0,7)}" onchange="fetchAttendance()">
        <select id="attStatusFilter" class="form-input w-36" onchange="fetchAttendance()">
          <option value="">All Status</option>
          <option value="present">Present</option><option value="absent">Absent</option>
          <option value="late">Late</option><option value="wfh">WFH</option>
          <option value="leave">Leave</option>
        </select>
        ${isEmp ? `<button onclick="checkInModal()" class="btn btn-primary"><i class="fa-solid fa-right-to-bracket"></i>Check In/Out</button>` : ''}
      </div>
    </div>
    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr>
            ${!isEmp ? '<th>Employee</th>' : ''}
            <th>Date</th><th>Check In</th><th>Check Out</th>
            <th>Hours</th><th>Status</th><th>Remarks</th>
          </tr></thead>
          <tbody id="attTableBody"></tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t bg-gray-50" id="attPagination"></div>
    </div>`;

  await loadTodayAttStats();
  if (!isEmp) loadDeptOptions('attDeptFilter');
  await fetchAttendance();
}

async function loadTodayAttStats() {
  const res = await apiGet('attendance/today');
  if (!res?.success) return;
  const s = res.data.summary;
  document.getElementById('attStats').innerHTML = [
    statCard('Present', s.present || 0, 'user-check', '#10B981', 'Currently in office'),
    statCard('Absent', s.absent || 0, 'user-xmark', '#EF4444', 'Not checked in'),
    statCard('Late', s.late || 0, 'clock', '#F59E0B', 'After grace period'),
    statCard('WFH', s.wfh || 0, 'house-laptop', '#3B82F6', 'Work from home'),
  ].join('');
}

async function fetchAttendance(page = 1) {
  const month  = document.getElementById('attMonth')?.value || new Date().toISOString().slice(0,7);
  const deptId = document.getElementById('attDeptFilter')?.value || '';
  const status = document.getElementById('attStatusFilter')?.value || '';
  const isEmp  = currentUser.user.role === 'employee';

  const params = { page, limit: 25, month };
  if (deptId) params.department_id = deptId;
  if (status) params.status = status;

  const res = await apiGet('attendance', params);
  if (!res?.success) return;

  const tbody = document.getElementById('attTableBody');
  tbody.innerHTML = res.data.map(a => `
    <tr>
      ${!isEmp ? `<td><div class="flex items-center gap-2">${avatarHtml(a.employee_name, null, 7)}<div><p class="text-sm font-medium">${a.employee_name}</p><p class="text-xs text-gray-400">${a.emp_code}</p></div></div></td>` : ''}
      <td class="text-sm font-medium">${formatDate(a.attendance_date)}</td>
      <td class="text-sm">${a.check_in ? formatTime(a.check_in) : '—'}</td>
      <td class="text-sm">${a.check_out ? formatTime(a.check_out) : (a.check_in ? '<span class="text-green-500 text-xs font-medium">ACTIVE</span>' : '—')}</td>
      <td class="text-sm">${a.total_hours ? `${a.total_hours}h` : '—'}${a.overtime_hours > 0 ? `<span class="text-xs text-orange-500 ml-1">+${a.overtime_hours}OT</span>` : ''}</td>
      <td>${attStatusBadge(a.status)}</td>
      <td class="text-xs text-gray-500">${truncate(a.remarks, 30)}</td>
    </tr>`).join('') || `<tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-calendar"></i><p>No records found</p></div></td></tr>`;

  renderPagination('attPagination', res.pagination, 'fetchAttendance');
}

async function checkIn() {
  const res = await apiPost('attendance/checkin', {});
  if (res?.success) { toast(res.data.message || 'Checked in!'); loadDashboard(); }
  else toast(res?.message || 'Check-in failed', 'error');
}

async function checkOut() {
  const res = await apiPost('attendance/checkout', {});
  if (res?.success) { toast(res.data.message || 'Checked out!'); loadDashboard(); }
  else toast(res?.message || 'Check-out failed', 'error');
}

// ═══════════════════════════════════════════════════════════════
// LEAVE MANAGEMENT
// ═══════════════════════════════════════════════════════════════
async function loadLeave() {
  document.getElementById('mainContent').innerHTML = `
    <div class="tab-nav mb-4">
      <button class="tab-btn active" onclick="switchTab('leaveMainTabs','leaveSection','requests')">Requests</button>
      <button class="tab-btn" onclick="switchTab('leaveMainTabs','leaveSection','calendar')">Calendar</button>
      <button class="tab-btn" onclick="switchTab('leaveMainTabs','leaveSection','balance')">Balances</button>
    </div>
    <div id="leaveSection-requests" class="tab-panel active"></div>
    <div id="leaveSection-calendar" class="tab-panel"></div>
    <div id="leaveSection-balance" class="tab-panel"></div>`;

  await loadLeaveRequests();
}

async function loadLeaveRequests() {
  const panel = document.getElementById('leaveSection-requests');
  panel.innerHTML = `
    <div class="card p-4 mb-4">
      <div class="flex flex-wrap gap-3 items-center">
        <select id="leaveStatusFilter" class="form-input w-36" onchange="fetchLeaves()">
          <option value="">All Status</option>
          <option value="pending">Pending</option><option value="approved">Approved</option>
          <option value="rejected">Rejected</option><option value="cancelled">Cancelled</option>
        </select>
        <select id="leaveTypeFilter" class="form-input w-40" onchange="fetchLeaves()">
          <option value="">All Types</option>
        </select>
        <button onclick="applyLeaveModal()" class="ml-auto btn btn-primary">
          <i class="fa-solid fa-plus"></i>Apply Leave
        </button>
      </div>
    </div>
    <div class="card overflow-hidden">
      <table class="data-table">
        <thead><tr>
          <th>Employee</th><th>Type</th><th>From</th><th>To</th>
          <th>Days</th><th>Status</th><th>Applied On</th><th>Actions</th>
        </tr></thead>
        <tbody id="leaveTableBody"></tbody>
      </table>
      <div class="px-4 py-3 border-t bg-gray-50" id="leavePagination"></div>
    </div>`;

  await loadLeaveTypeOptions('leaveTypeFilter');
  await fetchLeaves();
}

async function loadLeaveTypeOptions(selectId) {
  const res = await apiGet('leaves/types');
  if (!res?.success) return;
  const sel = document.getElementById(selectId);
  if (sel) sel.innerHTML = `<option value="">All Types</option>` + res.data.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
}

async function fetchLeaves(page = 1) {
  const params = { page, limit: 20,
    status: document.getElementById('leaveStatusFilter')?.value || '',
    leave_type_id: document.getElementById('leaveTypeFilter')?.value || '',
  };
  const res = await apiGet('leaves', params);
  if (!res?.success) return;

  const canApprove = currentUser.permissions?.includes('leave.approve') || currentUser.user.role === 'super_admin';
  const tbody = document.getElementById('leaveTableBody');

  tbody.innerHTML = res.data.map(l => `
    <tr>
      <td><div class="flex items-center gap-2">${avatarHtml(l.employee_name, l.profile_photo, 7)}<div><p class="text-sm font-medium">${l.employee_name}</p><p class="text-xs text-gray-400">${l.emp_code}</p></div></div></td>
      <td><span class="badge" style="background:${l.leave_color}20;color:${l.leave_color}">${l.leave_type_name}</span></td>
      <td class="text-sm">${formatDate(l.from_date)}</td>
      <td class="text-sm">${formatDate(l.to_date)}</td>
      <td class="text-sm font-medium">${l.total_days} day(s)</td>
      <td>${leaveStatusBadge(l.status)}</td>
      <td class="text-xs text-gray-500">${formatDate(l.applied_on)}</td>
      <td>
        <div class="flex gap-1">
          ${canApprove && l.status === 'pending' ? `
            <button onclick="approveLeave(${l.id},'approved')" class="btn btn-sm btn-success" title="Approve"><i class="fa-solid fa-check"></i></button>
            <button onclick="approveLeave(${l.id},'rejected')" class="btn btn-sm btn-danger" title="Reject"><i class="fa-solid fa-xmark"></i></button>` : ''}
          ${l.status === 'pending' ? `<button onclick="cancelLeave(${l.id})" class="btn btn-sm btn-secondary" title="Cancel"><i class="fa-solid fa-ban text-gray-500"></i></button>` : ''}
        </div>
      </td>
    </tr>`).join('') || `<tr><td colspan="8"><div class="empty-state"><i class="fa-solid fa-calendar-xmark"></i><p>No leave requests found</p></div></td></tr>`;

  renderPagination('leavePagination', res.pagination, 'fetchLeaves');
}

function applyLeaveModal() {
  openModal('Apply for Leave', `
    <form id="leaveForm" class="space-y-4">
      <div class="form-group"><label class="form-label">Leave Type *</label>
        <select name="leave_type_id" class="form-input" id="leaveTypeSelect" required></select></div>
      <div class="grid grid-cols-2 gap-3">
        <div class="form-group"><label class="form-label">From Date *</label>
          <input type="date" name="from_date" class="form-input" required min="${new Date().toISOString().split('T')[0]}"></div>
        <div class="form-group"><label class="form-label">To Date *</label>
          <input type="date" name="to_date" class="form-input" required min="${new Date().toISOString().split('T')[0]}"></div>
      </div>
      <div class="form-group"><label class="form-label">Day Type</label>
        <select name="day_type" class="form-input">
          <option value="full_day">Full Day</option>
          <option value="first_half">First Half</option>
          <option value="second_half">Second Half</option>
        </select></div>
      <div class="form-group"><label class="form-label">Reason *</label>
        <textarea name="reason" class="form-input" rows="3" placeholder="Please provide reason for leave..." required minlength="10"></textarea></div>
    </form>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
     <button class="btn btn-primary" onclick="submitLeaveApplication()"><i class="fa-solid fa-paper-plane"></i>Submit</button>`);
  loadLeaveTypeOptions('leaveTypeSelect');
}

async function submitLeaveApplication() {
  const data = formToObject(document.getElementById('leaveForm'));
  const res  = await apiPost('leaves', data);
  if (res?.success) { closeModal(); toast('Leave application submitted!'); fetchLeaves(1); }
  else toast(res?.message || 'Submission failed', 'error');
}

async function approveLeave(id, action) {
  const remarks = action === 'rejected' ? prompt('Rejection reason (required):') : '';
  if (action === 'rejected' && !remarks) return;
  const res = await apiPut(`leaves/${id}/approve`, { action, remarks });
  if (res?.success) { toast(`Leave ${action}!`); fetchLeaves(); }
  else toast(res?.message || 'Action failed', 'error');
}

async function cancelLeave(id) {
  const res = await apiPut(`leaves/${id}/cancel`);
  if (res?.success) { toast('Leave cancelled'); fetchLeaves(); }
  else toast(res?.message || 'Failed', 'error');
}

// ═══════════════════════════════════════════════════════════════
// PAYROLL
// ═══════════════════════════════════════════════════════════════
async function loadPayroll() {
  document.getElementById('mainContent').innerHTML = `
    <div class="tab-nav mb-4">
      <button class="tab-btn active" onclick="switchTab('payrollTabs','payrollSection','runs')">Payroll Runs</button>
      <button class="tab-btn" onclick="switchTab('payrollTabs','payrollSection','structures')">Salary Structures</button>
      <button class="tab-btn" onclick="switchTab('payrollTabs','payrollSection','analytics')">Analytics</button>
    </div>
    <div id="payrollSection-runs" class="tab-panel active"></div>
    <div id="payrollSection-structures" class="tab-panel"></div>
    <div id="payrollSection-analytics" class="tab-panel"></div>`;
  await loadPayrollRuns();
}

async function loadPayrollRuns() {
  const res = await apiGet('payroll/runs');
  if (!res?.success) return;

  document.getElementById('payrollSection-runs').innerHTML = `
    <div class="flex justify-between items-center mb-4">
      <h3 class="font-semibold">Payroll Runs</h3>
      <button onclick="createPayrollRun()" class="btn btn-primary"><i class="fa-solid fa-plus"></i>New Payroll Run</button>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      ${res.data.map(r => `
        <div class="card p-5 cursor-pointer hover:shadow-md transition" onclick="viewPayrollRun(${r.id})">
          <div class="flex justify-between items-start mb-3">
            <div>
              <h4 class="font-semibold">${monthName(r.month)} ${r.year}</h4>
              <p class="text-xs text-gray-500">${r.total_employees || 0} employees</p>
            </div>
            <span class="badge ${r.status==='paid'?'badge-green':r.status==='approved'?'badge-blue':r.status==='draft'?'badge-gray':'badge-yellow'}">${capitalizeWords(r.status)}</span>
          </div>
          <div class="space-y-1">
            <div class="flex justify-between text-sm"><span class="text-gray-500">Gross</span><span class="font-medium">${formatCurrency(r.total_gross)}</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Deductions</span><span class="text-red-500">${formatCurrency(r.total_deductions)}</span></div>
            <div class="flex justify-between text-sm border-t pt-1 mt-1"><span class="font-medium">Net Pay</span><span class="font-bold text-green-600">${formatCurrency(r.total_net)}</span></div>
          </div>
          ${r.status === 'draft' ? `<button onclick="event.stopPropagation(); approvePayrollRun(${r.id})" class="mt-3 w-full btn btn-sm btn-primary">Approve Run</button>` : ''}
        </div>`).join('') || '<div class="card p-8 text-center text-gray-400 col-span-3"><i class="fa-solid fa-money-bill text-4xl mb-3 block opacity-30"></i>No payroll runs yet. Create your first run.</div>'}
    </div>`;
}

function createPayrollRun() {
  const now = new Date();
  openModal('Create Payroll Run', `
    <form id="payrollRunForm" class="space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div><label class="form-label">Month *</label>
          <select name="month" class="form-input" required>
            ${Array.from({length:12},(_,i)=>`<option value="${i+1}" ${i+1===now.getMonth()+1?'selected':''}>${monthName(i+1)}</option>`).join('')}
          </select></div>
        <div><label class="form-label">Year *</label>
          <input type="number" name="year" class="form-input" value="${now.getFullYear()}" min="2020" max="2030" required></div>
      </div>
      <div class="bg-blue-50 p-3 rounded-lg text-sm text-blue-700">
        <i class="fa-solid fa-info-circle mr-2"></i>This will auto-calculate payslips for all active employees based on their salary structures and attendance.
      </div>
    </form>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
     <button class="btn btn-primary" onclick="submitPayrollRun()"><i class="fa-solid fa-play"></i>Generate</button>`);
}

async function submitPayrollRun() {
  const data = formToObject(document.getElementById('payrollRunForm'));
  const res  = await apiPost('payroll/runs', data);
  if (res?.success) { closeModal(); toast('Payroll run created and payslips generated!'); loadPayrollRuns(); }
  else toast(res?.message || 'Failed to create run', 'error');
}

async function approvePayrollRun(id) {
  const res = await apiPut(`payroll/runs/${id}/approve`);
  if (res?.success) { toast('Payroll approved and marked as paid!'); loadPayrollRuns(); }
  else toast(res?.message || 'Failed', 'error');
}

async function viewPayrollRun(id) {
  const res = await apiGet(`payroll/runs/${id}`);
  if (!res?.success) return;
  const r = res.data;

  openModal(`Payroll — ${monthName(r.month)} ${r.year}`, `
    <div class="grid grid-cols-3 gap-3 mb-4">
      <div class="bg-green-50 p-3 rounded-lg text-center"><p class="text-xs text-green-600 font-semibold">GROSS</p><p class="text-xl font-bold text-green-700">${formatCurrency(r.total_gross)}</p></div>
      <div class="bg-red-50 p-3 rounded-lg text-center"><p class="text-xs text-red-600 font-semibold">DEDUCTIONS</p><p class="text-xl font-bold text-red-700">${formatCurrency(r.total_deductions)}</p></div>
      <div class="bg-blue-50 p-3 rounded-lg text-center"><p class="text-xs text-blue-600 font-semibold">NET PAY</p><p class="text-xl font-bold text-blue-700">${formatCurrency(r.total_net)}</p></div>
    </div>
    <div class="overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Employee</th><th>Dept</th><th>Days Paid</th><th>Gross</th><th>Deductions</th><th>Net</th></tr></thead>
        <tbody>
          ${(r.payslips||[]).map(p=>`
            <tr>
              <td><div><p class="text-sm font-medium">${p.employee_name}</p><p class="text-xs text-gray-400">${p.emp_code}</p></div></td>
              <td class="text-xs text-gray-600">${p.department_name||'—'}</td>
              <td class="text-sm">${p.paid_days}/${p.working_days}</td>
              <td class="text-sm">${formatCurrency(p.gross_salary)}</td>
              <td class="text-sm text-red-500">-${formatCurrency(p.total_deductions)}</td>
              <td class="text-sm font-bold text-green-600">${formatCurrency(p.net_salary)}</td>
            </tr>`).join('')}
        </tbody>
      </table>
    </div>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Close</button>
     ${r.status === 'draft' ? `<button class="btn btn-primary" onclick="closeModal();approvePayrollRun(${r.id})">Approve & Pay</button>` : ''}`,
    'lg');
}

async function loadMyPayslips() {
  const res = await apiGet('payroll/my-payslips');
  if (!res?.success) return;

  document.getElementById('mainContent').innerHTML = `
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      ${res.data.map(p => `
        <div class="card p-5 cursor-pointer hover:shadow-md transition" onclick="viewPayslip(${p.id})">
          <div class="flex justify-between items-center mb-3">
            <h4 class="font-semibold">${monthName(p.month)} ${p.year}</h4>
            <span class="badge ${p.status==='paid'?'badge-green':'badge-gray'}">${p.status}</span>
          </div>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Days Paid</span><span>${p.paid_days}/${p.working_days}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Gross</span><span>${formatCurrency(p.gross_salary)}</span></div>
            <div class="flex justify-between border-t pt-1 mt-1"><span class="font-medium">Net Pay</span><span class="font-bold text-green-600">${formatCurrency(p.net_salary)}</span></div>
          </div>
          <div class="mt-3 flex justify-end">
            <button class="btn btn-secondary btn-sm"><i class="fa-solid fa-download text-gray-500"></i>Download</button>
          </div>
        </div>`).join('') || '<div class="col-span-3 card p-8 text-center text-gray-400"><i class="fa-solid fa-file-invoice text-4xl mb-3 block opacity-30"></i>No payslips available yet</div>'}
    </div>`;
}

async function viewPayslip(id) {
  const res = await apiGet(`payroll/payslip/${id}`);
  if (!res?.success) return;
  const p = res.data;

  openModal(`Payslip — ${monthName(p.month)} ${p.year}`, `
    <div class="bg-gray-50 rounded-xl p-5 text-sm">
      <div class="flex justify-between items-start mb-4">
        <div><h3 class="font-bold text-xl">TechVision Pvt Ltd</h3><p class="text-gray-500">Pay Slip for ${monthName(p.month)} ${p.year}</p></div>
        <span class="badge ${p.status==='paid'?'badge-green':'badge-gray'}">${p.status}</span>
      </div>
      <div class="grid grid-cols-2 gap-4 mb-4 pb-4 border-b">
        <div>${detailGrid([['Employee Name',p.employee_name],['Employee ID',p.emp_code],['Designation',p.designation_title],['Department',p.department_name]])}</div>
        <div>${detailGrid([['PAN Number',p.pan_number||'—'],['PF Number',p.pf_number||'—'],['UAN Number',p.uan_number||'—'],['Days Paid',`${p.paid_days} / ${p.working_days}`]])}</div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <h4 class="font-semibold text-green-700 mb-2">Earnings</h4>
          ${earningRow('Basic Salary', p.basic)}
          ${earningRow('HRA', p.hra)}
          ${earningRow('Special Allowance', p.special_allowance)}
          <div class="flex justify-between font-bold border-t pt-2 mt-2"><span>Gross Salary</span><span class="text-green-600">${formatCurrency(p.gross_salary)}</span></div>
        </div>
        <div>
          <h4 class="font-semibold text-red-700 mb-2">Deductions</h4>
          ${earningRow('PF (Employee)', p.pf_deduction, true)}
          ${earningRow('ESI', p.esi_deduction, true)}
          ${earningRow('TDS / Income Tax', p.tds_deduction, true)}
          ${earningRow('Professional Tax', p.professional_tax, true)}
          <div class="flex justify-between font-bold border-t pt-2 mt-2"><span>Total Deductions</span><span class="text-red-600">-${formatCurrency(p.total_deductions)}</span></div>
        </div>
      </div>
      <div class="mt-4 bg-indigo-600 text-white rounded-lg p-3 flex justify-between items-center">
        <span class="font-semibold">Net Salary</span>
        <span class="text-2xl font-bold">${formatCurrency(p.net_salary)}</span>
      </div>
    </div>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Close</button>
     <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i>Print</button>`, 'md');
}

// ═══════════════════════════════════════════════════════════════
// RECRUITMENT
// ═══════════════════════════════════════════════════════════════
async function loadRecruitment() {
  document.getElementById('mainContent').innerHTML = `
    <div class="tab-nav mb-4">
      <button class="tab-btn active" onclick="switchTab('recTabs','recSection','jobs')">Job Postings</button>
      <button class="tab-btn" onclick="switchTab('recTabs','recSection','pipeline')">Pipeline</button>
      <button class="tab-btn" onclick="switchTab('recTabs','recSection','candidates')">Candidates</button>
      <button class="tab-btn" onclick="switchTab('recTabs','recSection','stats')">Analytics</button>
    </div>
    <div id="recSection-jobs" class="tab-panel active"></div>
    <div id="recSection-pipeline" class="tab-panel"><div class="empty-state"><i class="fa-solid fa-columns"></i><p>Select a job to view pipeline</p></div></div>
    <div id="recSection-candidates" class="tab-panel"></div>
    <div id="recSection-stats" class="tab-panel"></div>`;
  await loadJobPostings();
}

async function loadJobPostings() {
  const res = await apiGet('recruitment/jobs', { limit: 20 });
  if (!res?.success) return;

  document.getElementById('recSection-jobs').innerHTML = `
    <div class="flex justify-between items-center mb-4">
      <div class="flex gap-2">
        <input type="text" placeholder="Search jobs..." class="form-input w-64">
        <select class="form-input w-36">
          <option value="">All Status</option>
          <option value="open">Open</option><option value="draft">Draft</option><option value="closed">Closed</option>
        </select>
      </div>
      <button onclick="openCreateJobModal()" class="btn btn-primary"><i class="fa-solid fa-plus"></i>Post Job</button>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      ${res.data.map(j => `
        <div class="card p-5">
          <div class="flex justify-between items-start mb-3">
            <div class="flex-1">
              <h4 class="font-semibold text-gray-800 leading-tight">${j.title}</h4>
              <p class="text-xs text-gray-500 mt-0.5">${j.requisition_number}</p>
            </div>
            ${jobStatusBadge(j.status)}
          </div>
          <div class="space-y-1 text-xs text-gray-600 mb-3">
            ${j.department_name ? `<div><i class="fa-solid fa-sitemap mr-2 text-gray-400"></i>${j.department_name}</div>` : ''}
            ${j.location_name   ? `<div><i class="fa-solid fa-location-dot mr-2 text-gray-400"></i>${j.location_name}</div>` : ''}
            <div><i class="fa-solid fa-users mr-2 text-gray-400"></i>${j.openings} opening(s) · ${j.application_count||0} applications</div>
            ${j.closing_date ? `<div><i class="fa-solid fa-calendar mr-2 text-gray-400"></i>Closes: ${formatDate(j.closing_date)}</div>` : ''}
          </div>
          <div class="flex gap-2">
            <button onclick="viewJobApplications(${j.id},'${j.title}')" class="flex-1 btn btn-outline btn-sm">
              <i class="fa-solid fa-users"></i>Pipeline
            </button>
            <button onclick="editJob(${j.id})" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i></button>
          </div>
        </div>`).join('') || '<div class="col-span-3 card p-8 text-center text-gray-400"><i class="fa-solid fa-briefcase text-4xl mb-3 block opacity-30"></i>No job postings yet</div>'}
    </div>`;
}

async function viewJobApplications(jobId, jobTitle) {
  switchTab('recTabs', 'recSection', 'pipeline');
  const res = await apiGet(`recruitment/jobs/${jobId}/applications`);
  if (!res?.success) return;

  const stages = ['applied','screening','shortlisted','phone_screen','assessment','technical_round','hr_round','final_round','offered','accepted'];
  const stageLabels = { applied:'Applied', screening:'Screening', shortlisted:'Shortlisted', phone_screen:'Phone Screen', assessment:'Assessment', technical_round:'Technical', hr_round:'HR Round', final_round:'Final Round', offered:'Offered', accepted:'Accepted' };

  const byStage = {};
  stages.forEach(s => byStage[s] = []);
  (res.data.applications||[]).forEach(a => { if (byStage[a.stage]) byStage[a.stage].push(a); });

  document.getElementById('recSection-pipeline').innerHTML = `
    <h3 class="font-semibold mb-4">${jobTitle} — Application Pipeline</h3>
    <div class="flex gap-3 overflow-x-auto pb-4">
      ${stages.map(s => `
        <div class="kanban-col">
          <div class="flex justify-between items-center mb-2">
            <span class="text-xs font-semibold text-gray-600 uppercase">${stageLabels[s]}</span>
            <span class="badge badge-gray text-xs">${byStage[s].length}</span>
          </div>
          ${byStage[s].map(a => `
            <div class="kanban-card">
              <p class="font-medium text-sm">${a.first_name} ${a.last_name}</p>
              <p class="text-xs text-gray-500">${a.current_company || 'N/A'} · ${a.experience_years}y exp</p>
              ${a.expected_ctc ? `<p class="text-xs text-gray-500 mt-1">Expected: ${formatCurrency(a.expected_ctc)}</p>` : ''}
              <div class="flex gap-1 mt-2">
                <button onclick="updateAppStage(${a.id})" class="btn btn-sm btn-secondary text-xs flex-1">Move Stage</button>
                <button onclick="scheduleInterviewModal(${a.id})" class="btn btn-sm btn-primary text-xs">
                  <i class="fa-solid fa-calendar"></i>
                </button>
              </div>
            </div>`).join('') || `<div class="text-xs text-gray-400 text-center py-4">Empty</div>`}
        </div>`).join('')}
    </div>`;
}

function openCreateJobModal() {
  openModal('Post New Job', `
    <form id="jobForm" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        ${formField('title','Job Title','text','','required')}
        ${formField('openings','Number of Openings','number','1','required')}
        ${formField('experience_min','Min Experience (years)','number','0')}
        ${formField('experience_max','Max Experience (years)','number','')}
        ${formField('salary_min','Min Salary (Annual)','number','')}
        ${formField('salary_max','Max Salary (Annual)','number','')}
        ${formSelect('employment_type','Type',['full_time','part_time','contract','intern'],'full_time')}
        ${formField('closing_date','Closing Date','date','')}
        ${formField('posted_date','Post Date','date',new Date().toISOString().split('T')[0])}
        ${formSelect('status','Status',['draft','open'],'open')}
      </div>
      <div class="form-group"><label class="form-label">Job Description *</label>
        <textarea name="description" class="form-input" rows="4" required placeholder="Describe the role, responsibilities, and requirements..."></textarea></div>
      <div class="form-group"><label class="form-label">Requirements</label>
        <textarea name="requirements" class="form-input" rows="3" placeholder="Experience, education, certifications required..."></textarea></div>
    </form>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
     <button class="btn btn-primary" onclick="submitCreateJob()"><i class="fa-solid fa-paper-plane"></i>Post Job</button>`, 'lg');
}

async function submitCreateJob() {
  const data = formToObject(document.getElementById('jobForm'));
  const res  = await apiPost('recruitment/jobs', data);
  if (res?.success) { closeModal(); toast('Job posted!'); loadJobPostings(); }
  else toast(res?.message || 'Failed', 'error');
}

async function updateAppStage(appId) {
  const stages = ['applied','screening','shortlisted','phone_screen','assessment','technical_round','hr_round','final_round','offered','accepted','rejected','on_hold'];
  openModal('Update Application Stage', `
    <form id="stageForm" class="space-y-3">
      <div><label class="form-label">New Stage *</label>
        <select name="stage" class="form-input" required>
          ${stages.map(s=>`<option value="${s}">${capitalizeWords(s)}</option>`).join('')}
        </select></div>
      <div><label class="form-label">Remarks</label>
        <textarea name="remarks" class="form-input" rows="2" placeholder="Optional notes..."></textarea></div>
    </form>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
     <button class="btn btn-primary" onclick="submitStageUpdate(${appId})">Update</button>`, 'sm');
}

async function submitStageUpdate(appId) {
  const data = formToObject(document.getElementById('stageForm'));
  const res  = await apiPut(`recruitment/applications/${appId}/stage`, data);
  if (res?.success) { closeModal(); toast('Stage updated!'); }
  else toast(res?.message || 'Failed', 'error');
}

function scheduleInterviewModal(appId) {
  openModal('Schedule Interview', `
    <form id="interviewForm" class="space-y-3">
      ${formSelect('interview_type','Interview Type',['phone','video','in_person','technical','hr','panel'],'in_person')}
      ${formField('scheduled_at','Date & Time','datetime-local','','required')}
      ${formField('duration_mins','Duration (minutes)','number','60')}
      ${formField('location','Location / Room','text','')}
      ${formField('meeting_link','Meeting Link (if video)','url','')}
    </form>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
     <button class="btn btn-primary" onclick="submitInterview(${appId})">Schedule</button>`, 'sm');
}

async function submitInterview(appId) {
  const data = formToObject(document.getElementById('interviewForm'));
  const res  = await apiPost(`recruitment/applications/${appId}/interview`, data);
  if (res?.success) { closeModal(); toast('Interview scheduled!'); }
  else toast(res?.message || 'Failed', 'error');
}

// ═══════════════════════════════════════════════════════════════
// PERFORMANCE
// ═══════════════════════════════════════════════════════════════
async function loadPerformance() {
  document.getElementById('mainContent').innerHTML = `
    <div class="tab-nav mb-4">
      <button class="tab-btn active" onclick="switchTab('perfTabs','perfSection','goals')">My Goals</button>
      <button class="tab-btn" onclick="switchTab('perfTabs','perfSection','reviews')">Reviews</button>
      <button class="tab-btn" onclick="switchTab('perfTabs','perfSection','cycles')">Cycles</button>
      <button class="tab-btn" onclick="switchTab('perfTabs','perfSection','stats')">Analytics</button>
    </div>
    <div id="perfSection-goals" class="tab-panel active"></div>
    <div id="perfSection-reviews" class="tab-panel"></div>
    <div id="perfSection-cycles" class="tab-panel"></div>
    <div id="perfSection-stats" class="tab-panel"></div>`;
  await loadGoals();
}

async function loadGoals() {
  const empId = currentUser.user.employee?.id;
  const res = await apiGet('performance/goals', empId ? { employee_id: empId } : {});
  if (!res?.success) return;

  document.getElementById('perfSection-goals').innerHTML = `
    <div class="flex justify-between items-center mb-4">
      <h3 class="font-semibold">Goals & OKRs</h3>
      <button onclick="openAddGoalModal()" class="btn btn-primary"><i class="fa-solid fa-plus"></i>Add Goal</button>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      ${res.data.map(g => `
        <div class="card p-5">
          <div class="flex items-start justify-between gap-2 mb-3">
            <div>
              <h4 class="font-medium text-gray-800">${g.title}</h4>
              <p class="text-xs text-gray-500 mt-0.5">${g.cycle_name||'No cycle'} · ${capitalizeWords(g.goal_type)}</p>
            </div>
            <span class="badge ${g.status==='active'?'badge-green':g.status==='completed'?'badge-blue':'badge-gray'} flex-shrink-0">${g.status}</span>
          </div>
          ${g.description ? `<p class="text-sm text-gray-600 mb-3">${truncate(g.description, 80)}</p>` : ''}
          <div class="space-y-2">
            ${g.target_value ? `<div class="flex justify-between text-xs text-gray-500"><span>Target</span><span class="font-medium text-gray-700">${g.target_value}</span></div>` : ''}
            ${g.actual_value ? `<div class="flex justify-between text-xs text-gray-500"><span>Actual</span><span class="font-medium text-green-600">${g.actual_value}</span></div>` : ''}
            ${g.achievement != null ? `
              <div>
                <div class="flex justify-between text-xs mb-1"><span>Achievement</span><span class="font-bold">${g.achievement}%</span></div>
                <div class="progress-bar">
                  <div class="progress-fill ${g.achievement >= 100 ? 'bg-green-500' : g.achievement >= 70 ? 'bg-blue-500' : 'bg-amber-500'}" style="width:${Math.min(g.achievement,100)}%"></div>
                </div>
              </div>` : ''}
          </div>
          <div class="flex justify-between items-center mt-3 pt-3 border-t">
            <span class="text-xs text-gray-400">Due: ${formatDate(g.due_date)}</span>
            <div class="flex gap-1">
              ${g.weight ? `<span class="badge badge-indigo text-xs">${g.weight}% weight</span>` : ''}
              <button onclick="editGoalModal(${g.id})" class="btn btn-sm btn-secondary"><i class="fa-solid fa-pen text-gray-500"></i></button>
            </div>
          </div>
        </div>`).join('') || '<div class="col-span-2 card p-8 text-center text-gray-400"><i class="fa-solid fa-bullseye text-4xl mb-3 block opacity-30"></i><p>No goals set. Start by adding your first goal.</p></div>'}
    </div>`;
}

function openAddGoalModal() {
  const empId = currentUser.user.employee?.id;
  openModal('Add New Goal', `
    <form id="goalForm" class="space-y-3">
      <input type="hidden" name="employee_id" value="${empId}">
      ${formField('title','Goal Title','text','','required')}
      <div><label class="form-label">Description</label>
        <textarea name="description" class="form-input" rows="2"></textarea></div>
      <div class="grid grid-cols-2 gap-3">
        ${formSelect('goal_type','Type',['kpi','okr','project','development'],'kpi')}
        ${formSelect('category','Category',['individual','team','department','company'],'individual')}
        ${formField('weight','Weight (%)', 'number','20')}
        ${formField('due_date','Due Date','date','')}
      </div>
      ${formField('target_value','Target / Metric','text','','')}
      ${formSelect('status','Status',['draft','active'],'active')}
    </form>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
     <button class="btn btn-primary" onclick="submitAddGoal()">Save Goal</button>`);
}

async function submitAddGoal() {
  const data = formToObject(document.getElementById('goalForm'));
  const res  = await apiPost('performance/goals', data);
  if (res?.success) { closeModal(); toast('Goal created!'); loadGoals(); }
  else toast(res?.message || 'Failed', 'error');
}

async function editGoalModal(id) {
  // We'd need to fetch the goal - simplified for now
  openModal('Update Goal Progress', `
    <form id="goalUpdateForm" class="space-y-3">
      ${formField('actual_value','Actual Value','text','')}
      ${formField('achievement','Achievement (%)','number','')}
      ${formSelect('status','Status',['draft','active','completed','cancelled'],'active')}
    </form>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
     <button class="btn btn-primary" onclick="submitUpdateGoal(${id})">Update</button>`, 'sm');
}

async function submitUpdateGoal(id) {
  const data = formToObject(document.getElementById('goalUpdateForm'));
  const res  = await apiPut(`performance/goals/${id}`, data);
  if (res?.success) { closeModal(); toast('Goal updated!'); loadGoals(); }
  else toast(res?.message || 'Failed', 'error');
}

// ═══════════════════════════════════════════════════════════════
// REPORTS
// ═══════════════════════════════════════════════════════════════
async function loadReports() {
  document.getElementById('mainContent').innerHTML = `
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      ${reportCard('Headcount Report', 'users', '#4F46E5', 'Employee distribution by dept, role, type', "loadHeadcountReport()")}
      ${reportCard('Attrition Analysis', 'door-open', '#EF4444', 'Monthly exits and turnover trends', "loadAttritionReport()")}
      ${reportCard('Attendance Summary', 'clock', '#10B981', 'Monthly attendance by employee', "loadAttendanceReport()")}
      ${reportCard('Leave Summary', 'calendar-xmark', '#F59E0B', 'Leave utilization and balances', "loadLeaveSummaryReport()")}
      ${reportCard('Payroll Summary', 'money-bill', '#8B5CF6', 'Salary cost analysis by month/dept', "loadPayrollReport()")}
      ${reportCard('Diversity Report', 'chart-pie', '#06B6D4', 'Gender, age, and tenure diversity', "loadDiversityReport()")}
      ${reportCard('Recruitment Metrics', 'user-plus', '#F97316', 'Hiring funnel and source analytics', "loadRecruitmentReport()")}
    </div>
    <div class="card p-6" id="reportViewer">
      <div class="empty-state"><i class="fa-solid fa-chart-bar"></i><p class="font-medium">Select a Report</p><p class="text-sm">Choose a report type above to view analytics</p></div>
    </div>`;
}

async function loadHeadcountReport() {
  document.getElementById('reportViewer').innerHTML = `<div class="text-center py-8"><i class="fa-solid fa-spinner fa-spin text-indigo-600 text-2xl"></i></div>`;
  const res = await apiGet('reports/headcount');
  if (!res?.success) return;

  const byDept = {};
  res.data.forEach(r => { if (!byDept[r.department]) byDept[r.department] = 0; byDept[r.department] += +r.count; });

  document.getElementById('reportViewer').innerHTML = `
    <div class="section-header"><h3 class="section-title">Headcount Report</h3>
      <span class="text-xs text-gray-500">Total: ${res.data.reduce((a,b)=>a + +b.count,0)} employees</span></div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div><h4 class="text-sm font-medium text-gray-600 mb-3">By Department</h4>
        <div style="height:280px"><canvas id="headcountDeptChart"></canvas></div></div>
      <div><h4 class="text-sm font-medium text-gray-600 mb-3">Details</h4>
        <div class="overflow-x-auto max-h-64 overflow-y-auto">
          <table class="data-table"><thead><tr><th>Department</th><th>Type</th><th>Gender</th><th>Count</th></tr></thead>
          <tbody>${res.data.slice(0,30).map(r=>`<tr><td>${r.department||'N/A'}</td><td class="text-xs">${r.employment_type||'—'}</td><td class="text-xs">${r.gender||'—'}</td><td class="font-medium">${r.count}</td></tr>`).join('')}</tbody>
          </table></div></div></div>`;

  setTimeout(() => {
    const depts  = Object.keys(byDept);
    const counts = Object.values(byDept);
    createDoughnutChart('headcountDeptChart', depts, counts,
      ['#4F46E5','#7C3AED','#10B981','#F59E0B','#EF4444','#06B6D4','#F97316','#EC4899']);
  }, 50);
}

async function loadAttritionReport() {
  document.getElementById('reportViewer').innerHTML = `<div class="text-center py-8"><i class="fa-solid fa-spinner fa-spin text-indigo-600 text-2xl"></i></div>`;
  const res = await apiGet('reports/attrition', { year: new Date().getFullYear() });
  if (!res?.success) return;

  document.getElementById('reportViewer').innerHTML = `
    <h3 class="section-title mb-4">Attrition Analysis (${new Date().getFullYear()})</h3>
    <div style="height:280px"><canvas id="attritionChart"></canvas></div>
    <div class="mt-4 grid grid-cols-2 gap-4">
      <div><h4 class="text-sm font-medium mb-2">By Department</h4>
        ${(res.data.by_department||[]).map(d=>`<div class="flex justify-between py-1 border-b text-sm"><span>${d.department}</span><span class="font-medium">${d.exits}</span></div>`).join('')}
      </div></div>`;

  setTimeout(() => {
    const labels = (res.data.monthly||[]).map(m => monthName(m.month).substring(0,3));
    const data   = (res.data.monthly||[]).map(m => m.exits);
    createBarChart('attritionChart', labels, [{
      label: 'Exits', data, backgroundColor: '#EF4444',
    }]);
  }, 50);
}

async function loadAttendanceReport() {
  document.getElementById('reportViewer').innerHTML = `<div class="text-center py-8"><i class="fa-solid fa-spinner fa-spin text-indigo-600 text-2xl"></i></div>`;
  const res = await apiGet('reports/attendance-summary', { month: new Date().toISOString().slice(0,7) });
  if (!res?.success) return;

  document.getElementById('reportViewer').innerHTML = `
    <h3 class="section-title mb-4">Attendance Summary — ${new Date().toLocaleDateString('en',{month:'long',year:'numeric'})}</h3>
    <div class="overflow-x-auto">
      <table class="data-table"><thead><tr><th>Employee</th><th>Department</th>
        <th>Present</th><th>Absent</th><th>Late</th><th>WFH</th><th>Leave</th><th>Total Hours</th></tr></thead>
        <tbody>${res.data.map(r=>`<tr>
          <td class="font-medium">${r.name}</td><td class="text-xs text-gray-500">${r.department||'—'}</td>
          <td class="text-green-600 font-medium">${r.present||0}</td>
          <td class="text-red-500">${r.absent||0}</td>
          <td class="text-amber-500">${r.late||0}</td>
          <td class="text-blue-500">${r.wfh||0}</td>
          <td class="text-purple-500">${r.on_leave||0}</td>
          <td>${r.total_hours||0}h</td>
        </tr>`).join('')}</tbody>
      </table></div>`;
}

async function loadLeaveSummaryReport() {
  const res = await apiGet('reports/leave-summary', { year: new Date().getFullYear() });
  if (!res?.success) return;
  document.getElementById('reportViewer').innerHTML = `
    <h3 class="section-title mb-4">Leave Summary Report</h3>
    <div class="overflow-x-auto"><table class="data-table"><thead><tr>
      <th>Employee</th><th>Department</th><th>Leave Type</th>
      <th>Allocated</th><th>Used</th><th>Balance</th></tr></thead>
      <tbody>${res.data.map(r=>`<tr>
        <td class="font-medium">${r.name}</td><td class="text-xs text-gray-500">${r.department||'—'}</td>
        <td>${r.leave_type}</td>
        <td>${r.allocated}</td>
        <td class="text-red-500">${r.used}</td>
        <td class="font-bold text-green-600">${r.balance}</td>
      </tr>`).join('')}</tbody></table></div>`;
}

async function loadPayrollReport() {
  const res = await apiGet('reports/payroll-summary', { year: new Date().getFullYear() });
  if (!res?.success) return;

  document.getElementById('reportViewer').innerHTML = `
    <h3 class="section-title mb-4">Payroll Summary (${new Date().getFullYear()})</h3>
    <div style="height:260px mb-6"><canvas id="payrollChart"></canvas></div>
    <div class="mt-4 overflow-x-auto">
      <table class="data-table"><thead><tr><th>Month</th><th>Employees</th><th>Gross</th><th>Deductions</th><th>Net Pay</th><th>Status</th></tr></thead>
      <tbody>${(res.data.monthly||[]).map(r=>`<tr>
        <td class="font-medium">${monthName(r.month)} ${r.year}</td>
        <td>${r.total_employees}</td>
        <td>${formatCurrency(r.total_gross)}</td>
        <td class="text-red-500">${formatCurrency(r.total_deductions)}</td>
        <td class="font-bold text-green-600">${formatCurrency(r.total_net)}</td>
        <td>${r.status==='paid'?'<span class="badge badge-green">Paid</span>':'<span class="badge badge-gray">'+r.status+'</span>'}</td>
      </tr>`).join('')}</tbody></table></div>`;

  setTimeout(() => {
    const labels  = (res.data.monthly||[]).map(r => monthName(r.month).substring(0,3));
    const grossD  = (res.data.monthly||[]).map(r => r.total_gross/100000);
    const netD    = (res.data.monthly||[]).map(r => r.total_net/100000);
    createBarChart('payrollChart', labels, [
      { label: 'Gross (Lakhs)', data: grossD, backgroundColor: '#6366F1' },
      { label: 'Net (Lakhs)',   data: netD,   backgroundColor: '#10B981' },
    ]);
  }, 50);
}

async function loadDiversityReport() {
  const res = await apiGet('reports/diversity');
  if (!res?.success) return;

  document.getElementById('reportViewer').innerHTML = `
    <h3 class="section-title mb-4">Diversity & Inclusion Report</h3>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div><h4 class="text-sm font-medium text-gray-600 mb-2">Gender Distribution</h4>
        <div style="height:200px"><canvas id="genderChart"></canvas></div></div>
      <div><h4 class="text-sm font-medium text-gray-600 mb-2">Age Groups</h4>
        <div style="height:200px"><canvas id="ageChart"></canvas></div></div>
      <div><h4 class="text-sm font-medium text-gray-600 mb-2">Tenure Distribution</h4>
        <div style="height:200px"><canvas id="tenureChart"></canvas></div></div>
    </div>`;

  setTimeout(() => {
    // Gender
    const gLabels = (res.data.gender_by_department||[]).reduce((acc, g) => { if (!acc.includes(g.gender)) acc.push(g.gender); return acc; }, []);
    const gCounts = gLabels.map(g => (res.data.gender_by_department||[]).reduce((a,b) => b.gender===g?a+ +b.count:a, 0));
    createDoughnutChart('genderChart', gLabels.map(capitalizeWords), gCounts, ['#4F46E5','#EC4899','#10B981']);

    // Age
    createBarChart('ageChart', (res.data.age_groups||[]).map(a=>a.age_group), [{ label:'Count', data:(res.data.age_groups||[]).map(a=>a.count), backgroundColor:'#6366F1' }]);

    // Tenure
    createBarChart('tenureChart', (res.data.tenure_groups||[]).map(a=>a.tenure), [{ label:'Employees', data:(res.data.tenure_groups||[]).map(a=>a.count), backgroundColor:'#10B981' }]);
  }, 50);
}

async function loadRecruitmentReport() {
  const res = await apiGet('reports/recruitment');
  if (!res?.success) return;

  document.getElementById('reportViewer').innerHTML = `
    <h3 class="section-title mb-4">Recruitment Analytics</h3>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div><h4 class="text-sm font-medium mb-2">Application Funnel</h4>
        <div style="height:250px"><canvas id="funnelChart"></canvas></div></div>
      <div><h4 class="text-sm font-medium mb-2">Source Breakdown</h4>
        <div style="height:250px"><canvas id="sourceChart"></canvas></div></div>
    </div>`;

  setTimeout(() => {
    createBarChart('funnelChart', (res.data.funnel||[]).map(f=>capitalizeWords(f.stage)), [{ label:'Applicants', data:(res.data.funnel||[]).map(f=>f.count), backgroundColor:'#4F46E5' }]);
    createDoughnutChart('sourceChart', (res.data.source||[]).map(s=>capitalizeWords(s.source)), (res.data.source||[]).map(s=>s.count),
      ['#4F46E5','#7C3AED','#10B981','#F59E0B','#EF4444','#06B6D4']);
  }, 50);
}

// ═══════════════════════════════════════════════════════════════
// DEPARTMENTS
// ═══════════════════════════════════════════════════════════════
async function loadDepartments() {
  const res = await apiGet('departments');
  if (!res?.success) return;

  document.getElementById('mainContent').innerHTML = `
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      ${res.data.map(d => `
        <div class="card p-5">
          <div class="flex items-start justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
              <i class="fa-solid fa-building text-indigo-600"></i>
            </div>
            <span class="badge badge-gray text-xs">${d.code}</span>
          </div>
          <h4 class="font-semibold text-gray-800">${d.name}</h4>
          ${d.description ? `<p class="text-xs text-gray-500 mt-1">${d.description}</p>` : ''}
          <div class="mt-3 pt-3 border-t space-y-1">
            <div class="flex justify-between text-xs">
              <span class="text-gray-500">Employees</span>
              <span class="font-bold text-indigo-600">${d.employee_count || 0}</span>
            </div>
            ${d.head_name ? `<div class="flex justify-between text-xs"><span class="text-gray-500">Head</span><span class="font-medium">${d.head_name}</span></div>` : ''}
            ${d.cost_center ? `<div class="flex justify-between text-xs"><span class="text-gray-500">Cost Center</span><span>${d.cost_center}</span></div>` : ''}
          </div>
        </div>`).join('')}
    </div>`;
}

// ═══════════════════════════════════════════════════════════════
// ORG CHART
// ═══════════════════════════════════════════════════════════════
async function loadOrgChart() {
  const res = await apiGet('employees', { limit: 100, employment_status: 'active' });
  if (!res?.success) return;
  const emps = res.data;

  // Build tree
  const tree = buildOrgTree(emps);

  document.getElementById('mainContent').innerHTML = `
    <div class="card p-6 overflow-auto">
      <div id="orgChartRoot" class="flex justify-center"></div>
    </div>`;

  document.getElementById('orgChartRoot').innerHTML = renderOrgNode(tree);
}

function buildOrgTree(emps) {
  const map = {};
  emps.forEach(e => { map[e.id] = { ...e, children: [] }; });
  let root = null;
  emps.forEach(e => {
    if (e.manager_id && map[e.manager_id]) map[e.manager_id].children.push(map[e.id]);
    else if (!e.manager_id && !root) root = map[e.id];
  });
  return root;
}

function renderOrgNode(node, depth = 0) {
  if (!node) return '';
  return `
    <div class="flex flex-col items-center">
      <div class="card p-3 w-36 text-center cursor-pointer hover:border-indigo-300 border-2 border-transparent transition mb-2" onclick="viewEmployee(${node.id})">
        <div class="flex justify-center mb-1">${avatarHtml(`${node.first_name} ${node.last_name}`, node.profile_photo, 10)}</div>
        <p class="text-xs font-semibold leading-tight text-gray-800">${node.first_name} ${node.last_name}</p>
        <p class="text-xs text-gray-500 mt-0.5 leading-tight">${node.designation_title||'—'}</p>
      </div>
      ${node.children?.length ? `
        <div class="w-px h-4 bg-gray-300"></div>
        <div class="flex gap-4 relative">
          <div class="absolute top-0 left-0 right-0 h-px bg-gray-300 mx-8"></div>
          ${node.children.map(c => `<div class="flex flex-col items-center"><div class="w-px h-4 bg-gray-300"></div>${renderOrgNode(c, depth+1)}</div>`).join('')}
        </div>` : ''}
    </div>`;
}

// ═══════════════════════════════════════════════════════════════
// TRAINING
// ═══════════════════════════════════════════════════════════════
async function loadTraining() {
  document.getElementById('mainContent').innerHTML = `
    <div class="card p-8 text-center">
      <i class="fa-solid fa-graduation-cap text-indigo-500 text-5xl mb-4"></i>
      <h3 class="text-xl font-bold text-gray-800 mb-2">Learning & Development</h3>
      <p class="text-gray-500 mb-4">Training management module — Full implementation available</p>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 text-left">
        ${['React Advanced Patterns','Leadership Excellence','POSH Awareness','Agile & Scrum'].map(t=>`
          <div class="card p-4 border hover:border-indigo-300 transition">
            <div class="flex items-center gap-3 mb-2">
              <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center"><i class="fa-solid fa-book-open text-indigo-600 text-sm"></i></div>
              <h4 class="font-medium text-sm">${t}</h4>
            </div>
            <div class="flex gap-2 mt-3">
              <span class="badge badge-blue text-xs">Online</span>
              <span class="badge badge-green text-xs">Enrolled</span>
            </div>
          </div>`).join('')}
      </div>
    </div>`;
}

// ═══════════════════════════════════════════════════════════════
// SETTINGS
// ═══════════════════════════════════════════════════════════════
async function loadSettings() {
  const res = await apiGet('settings');
  if (!res?.success) return;
  const s = res.data;

  document.getElementById('mainContent').innerHTML = `
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="card p-6">
        <h3 class="section-title mb-4">Company Settings</h3>
        <div class="space-y-3">
          ${formFieldValue('company_name', 'Company Name', s.general?.company_name || '')}
          ${formFieldValue('company_email', 'Company Email', s.general?.company_email || '')}
          ${formFieldValue('company_phone', 'Phone', s.general?.company_phone || '')}
          ${formFieldValue('company_address', 'Address', s.general?.company_address || '')}
        </div>
        <button onclick="saveSettings()" class="mt-4 btn btn-primary">Save Changes</button>
      </div>
      <div class="card p-6">
        <h3 class="section-title mb-4">Payroll Settings</h3>
        <div class="space-y-3">
          ${formFieldValue('payroll_day', 'Payroll Processing Day', s.payroll?.payroll_day || '')}
          ${formFieldValue('currency', 'Currency', s.general?.currency || 'INR')}
        </div>
        <div class="mt-4 space-y-2">
          <label class="flex items-center gap-3 text-sm">
            <input type="checkbox" ${s.payroll?.pf_applicable ? 'checked' : ''} class="rounded text-indigo-600">
            PF Applicable
          </label>
          <label class="flex items-center gap-3 text-sm">
            <input type="checkbox" ${s.payroll?.esi_applicable ? 'checked' : ''} class="rounded text-indigo-600">
            ESI Applicable
          </label>
        </div>
      </div>
    </div>`;
}

async function saveSettings() {
  toast('Settings saved (demo mode)', 'info');
}

// ═══════════════════════════════════════════════════════════════
// PROFILE
// ═══════════════════════════════════════════════════════════════
async function loadProfile() {
  const empId = currentUser.user.employee?.id;
  if (!empId) { document.getElementById('mainContent').innerHTML = '<div class="card p-8 text-center text-gray-400">No employee profile linked to this account</div>'; return; }
  await viewEmployee(empId);
}

// ═══════════════════════════════════════════════════════════════
// NOTIFICATIONS
// ═══════════════════════════════════════════════════════════════
async function loadNotifications() {
  const res = await apiGet('notifications');
  if (!res?.success) return;

  const unread = res.data.filter(n => !n.is_read).length;
  document.getElementById('notifDot').style.display = unread > 0 ? 'block' : 'none';
  document.getElementById('notifList').innerHTML = res.data.length ? res.data.slice(0,10).map(n => `
    <div class="flex gap-3 p-3 ${n.is_read?'':'bg-indigo-50'} hover:bg-gray-50 transition border-b last:border-0">
      <div class="w-7 h-7 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
        <i class="fa-solid fa-bell text-indigo-600 text-xs"></i>
      </div>
      <div>
        <p class="text-xs font-medium text-gray-800">${n.title}</p>
        <p class="text-xs text-gray-500">${truncate(n.message, 50)}</p>
        <p class="text-xs text-gray-400 mt-0.5">${timeAgo(n.created_at)}</p>
      </div>
    </div>`).join('') : '<p class="text-center text-gray-400 text-sm p-4">No notifications</p>';
}

async function markAllRead() {
  await apiPut('notifications/read', {});
  document.getElementById('notifDot').style.display = 'none';
  loadNotifications();
}

// ═══════════════════════════════════════════════════════════════
// UI HELPERS
// ═══════════════════════════════════════════════════════════════
function statCard(label, value, icon, color, subtitle = '') {
  return `
    <div class="stat-card">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">${label}</span>
        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:${color}20">
          <i class="fa-solid fa-${icon} text-sm" style="color:${color}"></i>
        </div>
      </div>
      <p class="text-2xl font-bold text-gray-900">${value}</p>
      ${subtitle ? `<p class="text-xs text-gray-400 mt-1">${subtitle}</p>` : ''}
    </div>`;
}

function attBar(label, value, total, color) {
  const pct = total > 0 ? Math.round((value/total)*100) : 0;
  return `
    <div class="mb-3">
      <div class="flex justify-between text-xs text-gray-600 mb-1">
        <span>${label}</span>
        <span class="font-semibold">${value} <span class="text-gray-400">(${pct}%)</span></span>
      </div>
      <div class="progress-bar"><div class="progress-fill" style="width:${pct}%;background:${color}"></div></div>
    </div>`;
}

function reportCard(title, icon, color, desc, onclick) {
  return `
    <div class="card p-5 cursor-pointer hover:shadow-md transition" onclick="${onclick}">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3" style="background:${color}20">
        <i class="fa-solid fa-${icon} text-base" style="color:${color}"></i>
      </div>
      <h4 class="font-semibold text-sm text-gray-800">${title}</h4>
      <p class="text-xs text-gray-500 mt-1">${desc}</p>
    </div>`;
}

function detailGrid(rows) {
  return `<div class="grid grid-cols-2 gap-x-4 gap-y-2">
    ${rows.map(([label,value]) => `
      <div><p class="text-xs text-gray-400">${label}</p><p class="text-sm font-medium text-gray-800">${value || '—'}</p></div>
    `).join('')}
  </div>`;
}

function formField(name, label, type, value='', attrs='') {
  return `<div class="form-group"><label class="form-label">${label}${attrs.includes('required')?'<span class="text-red-500 ml-0.5">*</span>':''}</label>
    <input type="${type}" name="${name}" class="form-input" value="${value||''}" ${attrs}></div>`;
}

function formFieldValue(name, label, value) {
  return `<div class="form-group"><label class="form-label">${label}</label>
    <input type="text" id="${name}" class="form-input" value="${value}"></div>`;
}

function formSelect(name, label, options, value='', attrs='') {
  const opts = options.map(o => `<option value="${o}" ${o==value?'selected':''}>${capitalizeWords(String(o))}</option>`).join('');
  return `<div class="form-group"><label class="form-label">${label}${attrs.includes('required')?'<span class="text-red-500 ml-0.5">*</span>':''}</label>
    <select name="${name}" class="form-input" ${attrs}><option value="">Select...</option>${opts}</select></div>`;
}

function earningRow(label, amount, deduction = false) {
  if (!amount || parseFloat(amount) === 0) return '';
  return `<div class="flex justify-between py-1 text-sm border-b border-gray-50">
    <span class="text-gray-600">${label}</span>
    <span class="${deduction ? 'text-red-500' : 'text-gray-800'}">${deduction ? '-' : ''}${formatCurrency(amount)}</span>
  </div>`;
}

function monthName(m) {
  const names = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  return names[+m] || '—';
}

function switchTab(navId, panelPrefix, activeId) {
  const nav = document.getElementById(navId);
  if (nav) nav.querySelectorAll('.tab-btn').forEach((b, i) => {
    b.classList.toggle('active', b.getAttribute('onclick')?.includes(`'${activeId}'`));
  });
  document.querySelectorAll(`[id^="${panelPrefix}-"]`).forEach(el => {
    el.classList.toggle('active', el.id === `${panelPrefix}-${activeId}`);
  });
}

// ═══════════════════════════════════════════════════════════════
// HEADER ACTIONS
// ═══════════════════════════════════════════════════════════════
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
function toggleNotifications() {
  document.getElementById('notifPanel').classList.toggle('hidden');
  document.getElementById('userMenu').classList.add('hidden');
}
function toggleUserMenu() {
  document.getElementById('userMenu').classList.toggle('hidden');
  document.getElementById('notifPanel').classList.add('hidden');
}

function logout() {
  confirmAction('Are you sure you want to sign out?', () => {
    apiPost('auth/logout', {}).finally(() => {
      localStorage.removeItem('hrms_token');
      localStorage.removeItem('hrms_user');
      window.location.href = 'index.html';
    });
  }, 'Sign Out');
}
