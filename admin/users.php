<?php
require_once __DIR__ . '/../forms/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$page_title = 'Users';
$active_nav = 'users';
include __DIR__ . '/includes/header.php';
?>
<div class="top-bar">
    <div>
        <h1>Users</h1>
        <p>Manage all platform users. Changes apply in real time — no page reload needed.</p>
    </div>
</div>

<!-- Toast notification -->
<div id="toast" style="display:none;position:fixed;top:20px;right:20px;z-index:9999;padding:14px 20px;border-radius:10px;font-size:0.92rem;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,0.15);transition:opacity 0.3s;"></div>

<div class="card">
    <h2>Filters</h2>
    <form id="filterForm" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>Search</label>
            <input type="text" id="f_search" placeholder="Name, email, phone" autocomplete="off">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Role</label>
            <select id="f_role">
                <option value="">All roles</option>
                <option value="driver">Driver</option>
                <option value="mechanic">Mechanic</option>
                <option value="pending">Pending</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Account Status</label>
            <select id="f_status">
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="deleted">🗑 Deleted (Audit)</option>
                <option value="all">All Accounts</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Profile</label>
            <select id="f_profile">
                <option value="">Any</option>
                <option value="yes">Completed</option>
                <option value="no">Incomplete</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Sort</label>
            <select id="f_sort">
                <option value="created_desc">Newest first</option>
                <option value="created_asc">Oldest first</option>
                <option value="name_asc">Name A–Z</option>
                <option value="name_desc">Name Z–A</option>
                <option value="role_asc">By role</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Per page</label>
            <select id="f_per_page">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
        <div class="flex" style="gap:8px;">
            <button type="reset" class="btn btn-secondary" onclick="setTimeout(loadUsers,50)">Clear</button>
        </div>
    </form>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <h2>Results <span id="resultCount" style="color:#64748b;font-weight:400;"></span></h2>
        <div id="loadingSpinner" style="display:none;color:#64748b;font-size:0.9rem;"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
    </div>

    <div style="overflow-x:auto;">
        <table id="usersTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Profile</th>
                    <th>Status</th>
                    <th>Garage / Availability</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="usersBody">
                <tr><td colspan="9" class="empty-state"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div id="pagination" style="margin-top:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;"></div>
</div>

<script>
// ---------- State ----------
let currentPage  = 1;
let currentCsrf  = '<?php echo csrf_token(); ?>';
let debounceTimer;

// ---------- Fetch & Render ----------
function loadUsers(page) {
    page = page || 1;
    currentPage = page;

    const params = new URLSearchParams({
        search   : document.getElementById('f_search').value,
        role     : document.getElementById('f_role').value,
        status   : document.getElementById('f_status').value,
        profile  : document.getElementById('f_profile').value,
        sort     : document.getElementById('f_sort').value,
        per_page : document.getElementById('f_per_page').value,
        page     : page,
    });

    document.getElementById('loadingSpinner').style.display = 'block';

    fetch('/mechanics_tracer/admin/api/users_api.php?' + params)
        .then(r => r.json())
        .then(data => {
            document.getElementById('loadingSpinner').style.display = 'none';
            currentCsrf = data.csrf;
            renderUsers(data);
        })
        .catch(() => {
            document.getElementById('loadingSpinner').style.display = 'none';
            document.getElementById('usersBody').innerHTML =
                `<tr><td colspan="9" class="empty-state" style="color:#dc2626"><i class="fas fa-exclamation-triangle"></i> Failed to load users. Please refresh.</td></tr>`;
        });
}

function renderUsers(data) {
    const tbody = document.getElementById('usersBody');
    const count = document.getElementById('resultCount');
    const pag   = document.getElementById('pagination');

    count.textContent = `(${data.total} user${data.total !== 1 ? 's' : ''})`;

    if (!data.users || data.users.length === 0) {
        tbody.innerHTML = `
            <tr>
              <td colspan="9" class="empty-state" style="padding:40px;text-align:center;">
                <div style="font-size:2.5rem;margin-bottom:12px;">🔍</div>
                <div style="font-size:1rem;font-weight:600;color:#334155;">No users found</div>
                <div style="color:#94a3b8;font-size:0.9rem;margin-top:6px;">Try changing the filters above.</div>
              </td>
            </tr>`;
        pag.innerHTML = '';
        return;
    }

    tbody.innerHTML = data.users.map(u => {
        const profileDone = parseInt(u.profile_completed) ? 'Yes' : 'No';
        const statusBadge = statusBadgeHtml(u.status);
        const roleBadge   = `<span class="badge badge-${esc(u.role)}">${esc(u.role)}</span>`;
        const garageTxt   = u.mechanic_id
            ? `${esc(u.garage_name || '—')} &nbsp;<span class="badge ${u.mechanic_available == 1 ? 'badge-completed' : 'badge-cancelled'}">${u.mechanic_available == 1 ? 'Online' : 'Offline'}</span>`
            : '—';

        return `<tr id="row-${u.id}">
            <td>${u.id}</td>
            <td><a href="user_view.php?id=${u.id}" style="color:#0f172a;font-weight:600;text-decoration:none;">${esc(u.full_name)}</a></td>
            <td>${esc(u.email)}</td>
            <td>${esc(u.phone || '—')}</td>
            <td>${roleBadge}</td>
            <td>${profileDone}</td>
            <td>${statusBadge}</td>
            <td>${garageTxt}</td>
            <td>${actionsHtml(u)}</td>
        </tr>`;
    }).join('');

    // Pagination
    let pagHtml = `<span style="color:#64748b;font-size:0.9rem;">Page ${data.page} of ${data.total_pages}</span><div class="flex" style="gap:6px;">`;
    if (data.page > 1) pagHtml += `<button class="btn btn-sm btn-secondary" onclick="loadUsers(${data.page - 1})"><i class="fas fa-chevron-left"></i> Prev</button>`;
    if (data.page < data.total_pages) pagHtml += `<button class="btn btn-sm btn-secondary" onclick="loadUsers(${data.page + 1})">Next <i class="fas fa-chevron-right"></i></button>`;
    pagHtml += '</div>';
    pag.innerHTML = pagHtml;
}

function statusBadgeHtml(status) {
    const map = {
        active    : ['badge-completed', 'Active'],
        suspended : ['badge-pending',   'Suspended'],
        deleted   : ['badge-cancelled', 'Deleted'],
    };
    const [cls, label] = map[status] || ['badge-pending', status];
    return `<span class="badge ${cls}">${label}</span>`;
}

function actionsHtml(u) {
    let html = `<div style="display:flex;gap:5px;flex-wrap:wrap;">
        <a href="user_view.php?id=${u.id}" class="btn btn-sm btn-secondary" title="View full profile"><i class="fas fa-eye"></i></a>`;

    if (u.status === 'deleted') {
        html += actionBtn(u.id, 'restore_user',  '#3b82f6', 'fa-undo', 'Restore');
    } else {
        if (u.status === 'active') {
            html += actionBtn(u.id, 'suspend_user', '#f59e0b', 'fa-ban', 'Suspend',
                'Suspend this account? The user will be blocked from logging in.');
        } else {
            html += actionBtn(u.id, 'activate_user', '#10b981', 'fa-check-circle', 'Activate',
                'Activate this account?');
        }
        html += actionBtn(u.id, 'delete_user', '#ef4444', 'fa-trash-alt', 'Delete',
            'Soft-delete this user? All data is preserved for audit. You can restore them later.');

        if (u.mechanic_id) {
            if (u.mechanic_available == 1) {
                html += actionBtn(u.id, 'mark_unavailable', '#64748b', 'fa-eye-slash', 'Set Offline',
                    'Mark mechanic as UNAVAILABLE for bookings?');
            } else {
                html += actionBtn(u.id, 'mark_available', '#0ea5e9', 'fa-eye', 'Set Online',
                    'Mark mechanic as AVAILABLE for bookings?');
            }
        }
    }

    html += '</div>';
    return html;
}

function actionBtn(uid, action, color, icon, label, confirmMsg) {
    // Single onclick: guard with confirm first, then doAction
    const guard = confirmMsg
        ? `if(!confirm(\`${confirmMsg.replace(/`/g, '\\`')}\`)) return;`
        : '';
    return `<button class="btn btn-sm" style="background:${color};color:#fff;border:none;" title="${label}"
        onclick="${guard}doAction(${uid},'${action}',this)"><i class="fas ${icon}"></i> ${label}</button>`;
}

// ---------- Perform Action ----------
function doAction(uid, action, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    const fd = new FormData();
    fd.append('user_id', uid);
    fd.append('action',  action);
    fd.append('_csrf',   currentCsrf);

    fetch('/mechanics_tracer/admin/api/users_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            showToast(res.msg, res.ok ? '#10b981' : '#dc2626');
            if (res.ok) loadUsers(currentPage); // refresh current page
        })
        .catch(() => {
            showToast('Network error. Please try again.', '#dc2626');
            btn.disabled = false;
        });
}

// ---------- Toast ----------
function showToast(msg, color) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.background = color;
    t.style.color = '#fff';
    t.style.display = 'block';
    t.style.opacity = '1';
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => { t.style.display = 'none'; }, 400); }, 3500);
}

// ---------- Escape HTML ----------
function esc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

// ---------- Wire up filters ----------
['f_role','f_status','f_profile','f_sort','f_per_page'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => loadUsers(1));
});
document.getElementById('f_search').addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadUsers(1), 350);
});

// Initial load
loadUsers(1);
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
