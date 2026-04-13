<?php 
require_once '../config/config.php';
checkAuth('admin');
$tenant_id = getTenantId();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leads | Lead Grabber</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #25D366; --dark: #121212; --card-bg: #1e1e1e; }
        body { font-family: 'Outfit', sans-serif; background: var(--dark); color: #fff; }
        .sidebar { background: #181818; min-height: 100vh; position: fixed; width: 250px; border-right: 1px solid #333; }
        .main-content { margin-left: 250px; padding: 40px; }
        .stat-card { background: var(--card-bg); border-radius: 15px; padding: 25px; border: 1px solid #333; }
        .table { color: #fff; }
        .table thead th { border-color: #333; color: #888; text-transform: uppercase; font-size: 0.75rem; }
        .table tbody td { border-color: #333; vertical-align: middle; }
        .td-number { font-family: 'Courier New', monospace; font-size: 0.95rem; color: var(--primary); letter-spacing: 0.5px; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .status-New { background: rgba(37, 211, 102, 0.1); color: #25D366; }
        .status-Contacted { background: rgba(0, 123, 255, 0.1); color: #007bff; }
        .status-Closed { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
        .modal-content { background: var(--card-bg); color: #fff; border: 1px solid #333; }
        .form-select { background: #2a2a2a; border: 1px solid #444; color: #fff; }
    </style>
</head>
<body>

<div class="sidebar d-none d-lg-block">
    <div class="p-4 text-center">
        <h3 class="fw-bold text-primary">Grabber</h3>
    </div>
    <div class="list-group list-group-flush mt-4">
        <a href="dashboard.php" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3">
            <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
        <a href="dashboard.php#sessions" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3">
            <i class="bi bi-whatsapp me-2"></i> WhatsApp Sessions
        </a>
        <a href="leads.php" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3 active">
            <i class="bi bi-people me-2"></i> Leads
        </a>
        <a href="dashboard.php#staff" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3">
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
            <h1 class="fw-bold mb-1">Lead Management</h1>
            <p class="text-secondary">View and assign captured leads to your team</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
            <tr>
                <th width="150">Captured At</th>
                <th width="150">Customer Name</th>
                <th width="180">WhatsApp Number</th>
                <th>Last Message</th>
                <th width="100">Status</th>
                <th width="150">Assigned To</th>
                <th width="100">Actions</th>
            </tr>
        </thead>
                <tbody id="leadsTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="assignForm">
            <input type="hidden" name="lead_id" id="modal_lead_id">
            <div class="modal-header border-0">
                <h5 class="modal-title">Assign Lead</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select Staff Member</label>
                    <select name="staff_id" id="staffSelect" class="form-select" required>
                        <option value="">Choose Staff...</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" class="btn btn-primary w-100">Confirm Assignment</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
let staffs = [];

function loadStaffOptions() {
    $.get('../api/get-staff.php', function(res) {
        staffs = JSON.parse(res);
        let options = '<option value="">Unassigned</option>';
        staffs.forEach(s => {
            options += `<option value="${s.id}">${s.full_name}</option>`;
        });
        $('#staffSelect').html(options);
    });
}

function loadLeads() {
    $.get('../api/get-leads-admin.php', function(res) {
        const leads = JSON.parse(res);
        console.log('DEBUG: Leads received:', leads); // Debug log
        let html = '';
        leads.forEach(l => {
            html += `
                <tr>
                    <td>${l.created_at}</td>
                    <td>${l.name}</td>
                    <td class="td-number" title="Raw: ${l.number}">${l.number}</td>
                    <td title="${l.last_message}">${l.last_message.substring(0, 30)}${l.last_message.length > 30 ? '...' : ''}</td>
                    <td><span class="status-badge status-${l.status}">${l.status}</span></td>
                    <td>${l.staff_name || '<span class="text-warning">Unassigned</span>'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="openAssignModal(${l.id}, ${l.assigned_to || 'null'})" title="Assign">
                            <i class="bi bi-person-check"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteLead(${l.id})" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        $('#leadsTableBody').html(html || '<tr><td colspan="7" class="text-center py-5">No leads found</td></tr>');
    });
}

function openAssignModal(leadId, staffId) {
    $('#modal_lead_id').val(leadId);
    $('#staffSelect').val(staffId || "");
    $('#assignModal').modal('show');
}

$('#assignForm').on('submit', function(e) {
    e.preventDefault();
    $.post('../api/assign-lead.php', $(this).serialize(), function(res) {
        const data = JSON.parse(res);
        if (data.status === 'success') {
            $('#assignModal').modal('hide');
            loadLeads();
        } else {
            alert(data.message);
        }
    });
});

function deleteLead(leadId) {
    if (!confirm('Are you sure you want to delete this lead? This will also remove the chat history.')) return;
    
    $.post('../api/delete-lead.php', { lead_id: leadId }, function(res) {
        const data = JSON.parse(res);
        if (data.status === 'success') {
            loadLeads();
        } else {
            alert(data.message);
        }
    });
}

$(document).ready(function() {
    loadStaffOptions();
    loadLeads();
});
</script>

</body>
</html>
