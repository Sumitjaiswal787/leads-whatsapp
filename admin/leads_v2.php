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
    <title>Manage Leads v2 | Lead Grabber</title>
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
        .table thead th { border-color: #333; color: #888; text-transform: uppercase; font-size: 0.75rem; border-bottom: 2px solid #333; }
        .table tbody td { border-color: #333; vertical-align: middle; padding: 15px 10px; }
        .td-number { font-family: 'Courier New', monospace; font-size: 1rem; color: var(--primary); font-weight: 600; letter-spacing: 0.5px; min-width: 160px; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
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
        <h3 class="fw-bold text-primary">Grabber <small class="fs-6 text-secondary">v2</small></h3>
    </div>
    <div class="list-group list-group-flush mt-4">
        <a href="dashboard.php" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3">
            <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
        <a href="leads_v2.php" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3 active">
            <i class="bi bi-people me-2"></i> Leads
        </a>
        <a href="../api/logout.php" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3">
            <i class="bi bi-box-arrow-left me-2"></i> Logout
        </a>
    </div>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap">
        <div>
            <h1 class="fw-bold mb-1">Lead Management <span class="badge bg-primary fs-6">v2.0</span></h1>
            <p class="text-secondary">Filter by date and export your leads</p>
        </div>
        <div class="d-flex gap-2 align-items-end flex-wrap mt-3 mt-md-0">
            <div>
                <label class="form-label small text-secondary">Start Date</label>
                <input type="date" id="startDate" class="form-control form-control-sm bg-dark text-white border-secondary">
            </div>
            <div>
                <label class="form-label small text-secondary">End Date</label>
                <input type="date" id="endDate" class="form-control form-control-sm bg-dark text-white border-secondary">
            </div>
            <button class="btn btn-primary btn-sm" onclick="loadLeads()">
                <i class="bi bi-funnel"></i> Filter
            </button>
            <button class="btn btn-success btn-sm" onclick="exportToExcel()">
                <i class="bi bi-file-earmark-excel"></i> Excel
            </button>
            <button class="btn btn-danger btn-sm" onclick="exportToPDF()">
                <i class="bi bi-file-earmark-pdf"></i> PDF
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="location.reload(true)">
                <i class="bi bi-arrow-clockwise"></i> Clear
            </button>
        </div>
    </div>

    <div class="stat-card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th width="150">Captured At</th>
                        <th width="150">Customer Name</th>
                        <th width="200">WhatsApp Number</th>
                        <th>Last Message</th>
                        <th width="100">Status</th>
                        <th width="150">Assigned To</th>
                        <th width="120">Actions</th>
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
<!-- Export Libraries -->
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

<script>
let currentLeads = [];

function loadStaffOptions() {
    $.get('../api/get-staff.php', function(res) {
        try {
            const staffs = JSON.parse(res);
            let options = '<option value="">Unassigned</option>';
            staffs.forEach(s => {
                options += `<option value="${s.id}">${s.full_name}</option>`;
            });
            $('#staffSelect').html(options);
        } catch(e) { console.error("Error parsing staff:", e); }
    });
}

function loadLeads() {
    const startDate = $('#startDate').val();
    const endDate = $('#endDate').val();
    
    let url = '../api/get-leads-admin.php?v=' + new Date().getTime();
    if (startDate && endDate) {
        url += `&startDate=${startDate}&endDate=${endDate}`;
    }

    $.get(url, function(res) {
        try {
            const leads = JSON.parse(res);
            currentLeads = leads;
            console.log('DEBUG: Leads v2 received:', leads);
            let html = '';
            leads.forEach(l => {
                const safeNumber = l.number.toString();
                html += `
                    <tr>
                        <td class="small text-secondary">${l.created_at}</td>
                        <td class="fw-bold">${l.name || 'Anonymous'}</td>
                        <td class="td-number">${safeNumber}</td>
                        <td title="${l.last_message}">${l.last_message.substring(0, 40)}${l.last_message.length > 40 ? '...' : ''}</td>
                        <td><span class="status-badge status-${l.status}">${l.status}</span></td>
                        <td><small>${l.staff_name || 'Unassigned'}</small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary py-0" onclick="openAssignModal(${l.id}, ${l.staff_id || 'null'})" title="Assign">
                                <i class="bi bi-person-check"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger py-0" onclick="deleteLead(${l.id})" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            $('#leadsTableBody').html(html || '<tr><td colspan="7" class="text-center py-5">No leads found for this period</td></tr>');
        } catch(e) { console.error("Error parsing leads:", e); }
    });
}

function exportToExcel() {
    if (currentLeads.length === 0) {
        alert("No data to export");
        return;
    }

    const data = currentLeads.map(l => ({
        'Captured At': l.created_at,
        'Name': l.name || 'Anonymous',
        'Number': l.number,
        'Last Message': l.last_message,
        'Status': l.status,
        'Assigned To': l.staff_name || 'Unassigned'
    }));

    const worksheet = XLSX.utils.json_to_sheet(data);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Leads");
    
    // Auto-size columns
    const maxWidths = data.reduce((acc, row) => {
        Object.keys(row).forEach((key, i) => {
            const val = String(row[key]);
            acc[i] = Math.max(acc[i] || 0, val.length + 2);
        });
        return acc;
    }, []);
    worksheet['!cols'] = maxWidths.map(w => ({ wch: w }));

    XLSX.writeFile(workbook, `leads_export_${new Date().toISOString().split('T')[0]}.xlsx`);
}

function exportToPDF() {
    if (currentLeads.length === 0) {
        alert("No data to export");
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'mm', 'a4');

    doc.setFontSize(18);
    doc.text("WhatsApp Leads Report", 14, 20);
    doc.setFontSize(10);
    doc.text(`Generated on: ${new Date().toLocaleString()}`, 14, 28);

    const tableData = currentLeads.map(l => [
        l.created_at,
        l.name || 'Anonymous',
        l.number,
        l.last_message.substring(0, 50),
        l.status,
        l.staff_name || 'Unassigned'
    ]);

    doc.autoTable({
        startY: 35,
        head: [['Date', 'Name', 'Number', 'Last Message', 'Status', 'Assigned To']],
        body: tableData,
        theme: 'grid',
        headStyles: { fillColor: [37, 211, 102] },
        styles: { fontSize: 8, cellPadding: 2 },
        columnStyles: {
            0: { cellWidth: 35 },
            1: { cellWidth: 35 },
            2: { cellWidth: 40 },
            3: { cellWidth: 'auto' },
            4: { cellWidth: 20 },
            5: { cellWidth: 30 }
        }
    });

    doc.save(`leads_report_${new Date().toISOString().split('T')[0]}.pdf`);
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
    if (!confirm('Are you sure you want to delete this lead?')) return;
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
    setInterval(loadLeads, 15000); // Auto refresh every 15s
});
</script>

</body>
</html>
