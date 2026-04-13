<?php 
require_once '../config/config.php';
checkAuth('admin');
$tenant_id = getTenantId();

// Initial Data
$stats = $conn->query("SELECT 
    (SELECT COUNT(*) FROM leads WHERE tenant_id = $tenant_id) as total_leads,
    (SELECT COUNT(*) FROM whatsapp_sessions WHERE tenant_id = $tenant_id) as total_sessions,
    (SELECT COUNT(*) FROM users WHERE tenant_id = $tenant_id AND role = 'staff') as total_staff
")->fetch_assoc();

$plan = getTenantPlan($tenant_id);
if (!$plan) {
    $plan = [
        'name' => 'Free/Basic',
        'max_sessions' => 1,
        'max_staff' => 3,
        'end_date' => null
    ];
}
$sess_limit = $plan['max_sessions'];
$staff_limit = $plan['max_staff'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Lead Grabber</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #25D366; --dark: #121212; --card-bg: #1e1e1e; }
        body { font-family: 'Outfit', sans-serif; background: var(--dark); color: #fff; margin-bottom: 50px; }
        .sidebar { background: #181818; min-height: 100vh; position: fixed; width: 250px; border-right: 1px solid #333; }
        .main-content { margin-left: 250px; padding: 40px; }
        .stat-card { background: var(--card-bg); border-radius: 15px; padding: 25px; border: 1px solid #333; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 2.5rem; color: var(--primary); opacity: 0.8; }
        .session-card { background: var(--card-bg); border-radius: 15px; padding: 20px; border: 1px solid #333; margin-bottom: 20px; }
        .qr-placeholder { background: #fff; width: 200px; height: 200px; margin: 20px auto; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #000; overflow: hidden; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .status-connected { background: rgba(37, 211, 102, 0.1); color: #25D366; }
        .status-disconnected { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .table { color: #fff; }
        .table thead th { border-color: #333; color: #888; text-transform: uppercase; font-size: 0.75rem; }
        .table tbody td { border-color: #333; vertical-align: middle; }
        .btn-primary { background: var(--primary); border: none; font-weight: 600; }
        .modal-content { background: var(--card-bg); color: #fff; border: 1px solid #333; }
        .form-control { background: #2a2a2a; border: 1px solid #444; color: #fff; }
        .form-control:focus { background: #333; color: #fff; border-color: var(--primary); box-shadow: none; }
        .suspended-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9); z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            text-align: center; backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>

<?php if ($plan && ($plan['status'] === 'suspended' || $plan['status'] === 'expired')): ?>
<div class="suspended-overlay">
    <div class="p-5 bg-dark rounded-4 border border-<?= $plan['status'] === 'suspended' ? 'warning' : 'danger' ?> shadow-lg" style="max-width: 500px;">
        <i class="bi bi-exclamation-triangle-fill text-<?= $plan['status'] === 'suspended' ? 'warning' : 'danger' ?> mb-4" style="font-size: 5rem;"></i>
        <h2 class="fw-bold mb-3">Account <?= ucfirst($plan['status']) ?></h2>
        <p class="text-secondary mb-4">
            <?php if ($plan['status'] === 'suspended'): ?>
                Your account has been suspended by the administrator. Please contact support to resolve this issue.
            <?php else: ?>
                Your subscription has expired. Please renew your plan to continue using the WhatsApp Lead Grabber.
            <?php endif; ?>
        </p>
        <a href="../api/logout.php" class="btn btn-outline-light px-4 py-2 rounded-pill">Logout & Contact Admin</a>
    </div>
</div>
<?php endif; ?>

<div class="sidebar d-none d-lg-block">
    <div class="p-4 text-center">
        <h3 class="fw-bold text-primary">Grabber</h3>
    </div>
    <div class="list-group list-group-flush mt-4">
        <a href="#" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3 active">
            <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
        <a href="#sessions" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3">
            <i class="bi bi-whatsapp me-2"></i> WhatsApp Sessions
        </a>
        <a href="leads_v2.php" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3">
            <i class="bi bi-people me-2"></i> Leads
        </a>
        <a href="#staff" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3">
            <i class="bi bi-person-badge me-2"></i> Staff
        </a>
        <a href="../api/logout.php" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3">
            <i class="bi bi-box-arrow-left me-2"></i> Logout
        </a>
    </div>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h1 class="fw-bold mb-1">Overview</h1>
            <p class="text-secondary">Welcome back, <?= $_SESSION['username'] ?></p>
        </div>
        <button class="btn btn-primary px-4 py-2" data-bs-toggle="modal" data-bs-target="#addSessionModal">
            <i class="bi bi-plus-lg me-2"></i> New Session
        </button>
    </div>

    <!-- Plan Usage -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="stat-card bg-primary bg-opacity-10 border-primary border-opacity-25">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-star-fill me-2"></i> Current Plan: <?= $plan['name'] ?? 'Free/Basic' ?></h5>
                    <?php if($plan['end_date']): ?>
                        <small class="text-secondary">Renews: <?= date('M d, Y', strtotime($plan['end_date'])) ?></small>
                    <?php endif; ?>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-secondary d-block mb-1">WhatsApp Sessions (<?= $stats['total_sessions'] ?>/<?= $sess_limit ?>)</small>
                        <div class="progress bg-dark" style="height: 6px;">
                            <div class="progress-bar bg-primary" style="width: <?= ($stats['total_sessions']/$sess_limit)*100 ?>%"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-secondary d-block mb-1">Staff Members (<?= $stats['total_staff'] ?>/<?= $staff_limit ?>)</small>
                        <div class="progress bg-dark" style="height: 6px;">
                            <div class="progress-bar bg-info" style="width: <?= ($stats['total_staff']/$staff_limit)*100 ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center">
                <div class="stat-icon me-4"><i class="bi bi-person-plus"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?= $stats['total_leads'] ?></h3>
                    <p class="text-secondary mb-0">Total Leads</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center">
                <div class="stat-icon me-4 text-info"><i class="bi bi-whatsapp"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?= $stats['total_sessions'] ?></h3>
                    <p class="text-secondary mb-0">Active Sessions</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center">
                <div class="stat-icon me-4 text-warning"><i class="bi bi-people"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?= $stats['total_staff'] ?></h3>
                    <p class="text-secondary mb-0">Staff Members</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Sessions Section -->
    <div id="sessions" class="mb-5">
        <h4 class="fw-bold mb-4">WhatsApp Instances</h4>
        <div class="row" id="sessionList">
            <!-- Sessions will be loaded here via Ajax -->
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
        </div>
    </div>

    <!-- Staff Section -->
    <div id="staff" class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">Manage Staff</h4>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStaffModal">Add Staff</button>
        </div>
        <div class="stat-card">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="staffTableBody">
                    <!-- Staff list -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Session -->
<div class="modal fade" id="addSessionModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="addSessionForm">
            <div class="modal-header border-0">
                <h5 class="modal-title">Create New Session</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Session Name</label>
                    <input type="text" name="session_name" class="form-control" placeholder="Marketing Bot 1" required>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" class="btn btn-primary w-100">Initialize Worker</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add Staff -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="addStaffForm">
            <div class="modal-header border-0">
                <h5 class="modal-title">Add Staff Member</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" class="btn btn-primary w-100">Create Staff Account</button>
            </div>
        </form>
    </div>
</div>
    <style>
        #socket-debug-console {
            position: fixed;
            bottom: 0;
            right: 0;
            width: 350px;
            height: 200px;
            background: #1e1e1e;
            color: #00ff00;
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            padding: 10px;
            overflow-y: auto;
            border-top-left-radius: 8px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.5);
            z-index: 9999;
            opacity: 0.9;
            display: none;
        }
        #socket-debug-console.active { display: block; }
        .log-entry { margin-bottom: 2px; border-bottom: 1px solid #333; padding-bottom: 2px; }
        .log-time { color: #888; margin-right: 5px; }
        .log-error { color: #ff5555; }
        .log-success { color: #55ff55; }
        .log-warn { color: #ffff55; }
        
        .status-badge { font-weight: 600; text-transform: uppercase; font-size: 0.7rem; padding: 4px 8px; border-radius: 4px; }
        .status-connected { background: #d1fae5; color: #065f46; }
        .status-disconnected { background: #fee2e2; color: #991b1b; }
        .status-initializing { background: #fef3c7; color: #92400e; }
    </style>

    <div id="socket-debug-console">
        <div class="d-flex justify-content-between align-items-center mb-2 border-bottom border-secondary pb-1">
            <span class="fw-bold">Socket Debugger</span>
            <button class="btn btn-close btn-close-white" style="font-size: 10px;" onclick="$('#socket-debug-console').removeClass('active')"></button>
        </div>
        <div id="debug-logs"></div>
    </div>

    <button class="btn btn-dark position-fixed bottom-0 end-0 m-3 rounded-circle shadow" style="width: 40px; height: 40px; z-index: 9998;" onclick="$('#socket-debug-console').toggleClass('active')" title="Toggle Socket Debugger">
        <i class="bi bi-terminal"></i>
    </button>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.socket.io/4.5.4/socket.io.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.0/build/qrcode.min.js"></script>

<script>
function logDebug(msg, type = 'info') {
    const time = new Date().toLocaleTimeString();
    const colorClass = type === 'error' ? 'log-error' : (type === 'success' ? 'log-success' : (type === 'warn' ? 'log-warn' : ''));
    $('#debug-logs').prepend(`<div class="log-entry ${colorClass}"><span class="log-time">[${time}]</span> ${msg}</div>`);
    console.log(`[SocketDebug] ${msg}`);
}

// Global init function
window.initSession = function(sessionId) {
    logDebug(`Manually triggering init for ${sessionId}...`, 'info');
    const btn = $(`#btn-init-${sessionId}`);
    const originalHtml = btn.html();
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Starting...');
    
    // We reuse create-session.php since it's already set up to hit the backend
    $.post('../api/create-session.php', { force_init: true, session_id: sessionId }, function(res) {
        logDebug(`Init response for ${sessionId}: ${typeof res === 'object' ? JSON.stringify(res) : res}`, 'success');
        // Handle both string and object responses
        const data = typeof res === 'object' ? res : JSON.parse(res);
        if (data.status === 'success' || data.success) {
            logDebug(`Session ${sessionId} initialization signal sent successfully.`, 'success');
        } else {
            alert('Error: ' + (data.message || data.error || 'Unknown error'));
            btn.prop('disabled', false).html(originalHtml);
        }
    }).fail(function(err) {
        logDebug(`Init FAILED for ${sessionId}`, 'error');
        btn.prop('disabled', false).html(originalHtml);
    });
};

// Socket Events
logDebug('Connecting to Socket.io: <?= BACKEND_URL ?>', 'info');
const socket = io('<?= BACKEND_URL ?>', { 
    transports: ['polling', 'websocket'],
    reconnectionAttempts: 10,
    timeout: 20000
});

socket.on('connect', () => {
    logDebug('Socket Connected! ID: ' + socket.id, 'success');
    loadSessions(); // Reload to ensure rooms are joined and subscriptions are fresh
});

socket.on('connect_error', (err) => {
    logDebug('Socket Connection Error: ' + err.message, 'error');
});

function loadSessions() {
    $.get('../api/get-sessions.php', function(res) {
        const sessions = typeof res === 'object' ? res : JSON.parse(res);
        let html = '';
        sessions.forEach(s => {
            html += `
                <div class="col-md-4">
                    <div class="session-card" id="session-${s.session_id}">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 fw-bold">${s.session_id.split('_').pop()}</h5>
                            <div class="d-flex align-items-center gap-2">
                                <span class="status-badge status-${s.status}" id="status-${s.session_id}">${s.status}</span>
                                <button class="btn btn-link text-danger p-0 border-0" onclick="deleteSession('${s.session_id}')" title="Delete Session">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="qr-placeholder" id="qr-container-${s.session_id}">
                            ${s.status === 'connected' ? '<i class="bi bi-check-circle text-success fs-1"></i>' : '<p class="mb-0 text-muted">Awaiting QR...</p>'}
                        </div>
                        <div class="mt-3">
                            ${s.status !== 'connected' ? `
                                <button class="btn btn-primary w-100 btn-sm" onclick="initSession('${s.session_id}')" id="btn-init-${s.session_id}">
                                    <i class="bi bi-play-circle"></i> Initialize / Refresh QR
                                </button>
                            ` : `
                                <button class="btn btn-outline-secondary w-100 btn-sm" disabled>
                                    <i class="bi bi-check2-all"></i> Active
                                </button>
                            `}
                        </div>
                    </div>
                </div>
            `;
            // Subscribe to this session's events
            logDebug('Subscribing to room: ' + s.session_id);
            socket.emit('subscribe', s.session_id);
        });
        $('#sessionList').html(html || '<div class="col-12 text-center text-muted">No sessions yet</div>');
    });
}

function loadStaff() {
    $.get('../api/get-staff.php', function(res) {
        const staff = typeof res === 'object' ? res : JSON.parse(res);
        let html = '';
        staff.forEach(u => {
            html += `
                <tr>
                    <td>${u.full_name}</td>
                    <td>${u.username}</td>
                    <td>${u.created_at}</td>
                    <td><button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button></td>
                </tr>
            `;
        });
        $('#staffTableBody').html(html);
    });
}

socket.on('qr', function(data) {
    logDebug('QR Received for ' + data.sessionId, 'success');
    const container = $(`#qr-container-${data.sessionId}`);
    if (container.length) {
        QRCode.toCanvas(data.qr, { width: 180 }, function (error, canvas) {
            if (!error) {
                container.empty().append(canvas);
            }
        });
    }
    $(`#status-${data.sessionId}`).text('Awaiting Scan').removeClass('status-connected').addClass('status-disconnected');
});

socket.on('status', function(data) {
    logDebug(`Status update for ${data.sessionId}: ${data.status}`, 'warn');
    const badge = $(`#status-${data.sessionId}`);
    badge.text(data.status);
    if (data.status === 'connected') {
        badge.removeClass('status-disconnected status-initializing').addClass('status-connected');
        $(`#qr-container-${data.sessionId}`).html('<i class="bi bi-check-circle text-success fs-1"></i>');
        $(`#btn-init-${data.sessionId}`).parent().html('<button class="btn btn-outline-secondary w-100 btn-sm" disabled><i class="bi bi-check2-all"></i> Active</button>');
    } else if (data.status === 'initializing') {
        badge.removeClass('status-disconnected status-connected').addClass('status-initializing');
        $(`#qr-container-${data.sessionId}`).html('<div class="spinner-border spinner-border-sm text-primary"></div><p class="mt-2 mb-0 small">Connecting Baileys...</p>');
    } else {
        badge.removeClass('status-connected status-initializing').addClass('status-disconnected');
    }
});

function deleteSession(sessionId) {
    if (!confirm('Are you sure you want to delete this session? This will log out the WhatsApp account and remove all local data.')) return;
    
    $.post('../api/delete-session.php', { session_id: sessionId }, function(res) {
        const data = typeof res === 'object' ? res : JSON.parse(res);
        if (data.status === 'success') {
            loadSessions();
        } else {
            alert(data.message);
        }
    });
}

$('#addSessionForm').on('submit', function(e) {
    e.preventDefault();
    $.post('../api/create-session.php', $(this).serialize(), function(res) {
        const data = typeof res === 'object' ? res : JSON.parse(res);
        if (data.status === 'success') {
            $('#addSessionModal').modal('hide');
            loadSessions();
        } else {
            alert(data.message);
        }
    });
});

$('#addStaffForm').on('submit', function(e) {
    e.preventDefault();
    $.post('../api/add-staff.php', $(this).serialize(), function(res) {
        const data = typeof res === 'object' ? res : JSON.parse(res);
        if (data.status === 'success') {
            $('#addStaffModal').modal('hide');
            loadStaff();
        } else {
            alert(data.message);
        }
    });
});

$(document).ready(function() {
    // Note: loadSessions() is now called inside socket.on('connect') to ensure subscription is fresh
    loadStaff();
});
</script>

</body>
</html>
