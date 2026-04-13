<?php
require_once '../../config/config.php';
checkAuth('superadmin');

// Fetch Tenants with their active subscription
$sql = "SELECT u.id, u.username, u.full_name, u.created_at, 
               p.name as plan_name, s.status as sub_status, s.end_date 
        FROM users u 
        LEFT JOIN subscriptions s ON u.id = s.tenant_id AND s.status = 'active'
        LEFT JOIN plans p ON s.plan_id = p.id
        WHERE u.role = 'admin'
        ORDER BY u.created_at DESC";
$tenants = $conn->query($sql);

// Handle Manual Subscription Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $tenant_id = $_POST['tenant_id'];
    
    if ($_POST['action'] === 'add_sub') {
        $plan_id = $_POST['plan_id'];
        $months = (int)($_POST['months'] ?? 1);
        
        // Deactivate previous
        $conn->query("UPDATE subscriptions SET status = 'expired' WHERE tenant_id = $tenant_id");
        
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+$months months"));
        
        // Add new
        $stmt = $conn->prepare("INSERT INTO subscriptions (tenant_id, plan_id, start_date, end_date, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("iiss", $tenant_id, $plan_id, $start_date, $end_date);
        $stmt->execute();
    } elseif ($_POST['action'] === 'suspend') {
        $conn->query("UPDATE subscriptions SET status = 'suspended' WHERE tenant_id = $tenant_id AND status = 'active'");
    } elseif ($_POST['action'] === 'activate') {
        $conn->query("UPDATE subscriptions SET status = 'active' WHERE tenant_id = $tenant_id AND status = 'suspended'");
    }
    
    header("Location: tenants.php");
    exit();
}

$all_plans = $conn->query("SELECT id, name FROM plans");
$plans_array = [];
while($p = $all_plans->fetch_assoc()) $plans_array[] = $p;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Tenants | Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --primary: #25D366; --dark: #075E54; }
        body { font-family: 'Outfit', sans-serif; background: #f4f7f6; }
        .sidebar { height: 100vh; background: var(--dark); color: white; position: fixed; width: 250px; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 15px 25px; }
        .sidebar .nav-link.active { background: #128C7E; color: white; }
        .main-content { margin-left: 250px; padding: 40px; }
        .tenant-card { border-radius: 15px; border: none; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="p-4"><h4 class="fw-bold"><i class="bi bi-shield-check"></i> Super Panel</h4></div>
    <nav class="nav flex-column mt-4">
        <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
        <a class="nav-link" href="plans.php"><i class="bi bi-box me-2"></i> Manage Plans</a>
        <a class="nav-link active" href="tenants.php"><i class="bi bi-people me-2"></i> Monitor Tenants</a>
        <a class="nav-link" href="../../api/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
    </nav>
</div>

<div class="main-content">
    <h2 class="fw-bold mb-5">Tenant Monitoring</h2>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Tenant</th>
                            <th>Current Plan</th>
                            <th>Status</th>
                            <th>Expiry Date</th>
                            <th>Joined</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($t = $tenants->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold"><?php echo $t['full_name']; ?></div>
                                <div class="text-muted small">@<?php echo $t['username']; ?></div>
                            </td>
                            <td>
                                <?php if($t['plan_name']): ?>
                                    <span class="badge bg-primary-subtle text-primary px-3"><?php echo $t['plan_name']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">No Plan</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($t['sub_status'] === 'active'): ?>
                                    <span class="badge bg-success px-2"><i class="bi bi-check-circle me-1"></i> Active</span>
                                <?php elseif($t['sub_status'] === 'suspended'): ?>
                                    <span class="badge bg-warning text-dark px-2"><i class="bi bi-pause-circle me-1"></i> Suspended</span>
                                <?php else: ?>
                                    <span class="badge bg-danger px-2">Inactive/Expired</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $t['end_date'] ? date('M d, Y', strtotime($t['end_date'])) : 'N/A'; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <button class="btn btn-outline-primary btn-sm rounded-pill px-3" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#subModal" 
                                            data-id="<?php echo $t['id']; ?>"
                                            data-name="<?php echo $t['full_name']; ?>">
                                        <i class="bi bi-plus-circle me-1"></i> Assign Plan
                                    </button>
                                    <?php if($t['sub_status'] === 'active'): ?>
                                        <form method="POST" style="display:inline" class="ms-2">
                                            <input type="hidden" name="action" value="suspend">
                                            <input type="hidden" name="tenant_id" value="<?php echo $t['id']; ?>">
                                            <button class="btn btn-outline-warning btn-sm rounded-pill px-3"><i class="bi bi-pause-fill"></i> Suspend</button>
                                        </form>
                                    <?php elseif($t['sub_status'] === 'suspended'): ?>
                                        <form method="POST" style="display:inline" class="ms-2">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="tenant_id" value="<?php echo $t['id']; ?>">
                                            <button class="btn btn-outline-success btn-sm rounded-pill px-3"><i class="bi bi-play-fill"></i> Activate</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Assign Subscription Modal -->
<div class="modal fade" id="subModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <form method="POST">
                <div class="modal-header border-0 p-4">
                    <h5 class="modal-title fw-bold">Manual Plan Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <input type="hidden" name="action" value="add_sub">
                    <input type="hidden" name="tenant_id" id="tenantId">
                    <div class="mb-3 text-muted">Assigning plan to: <strong id="tenantName"></strong></div>
                    <div class="mb-3">
                        <label class="form-label">Select Plan</label>
                        <select name="plan_id" class="form-select rounded-3" required>
                            <?php foreach($plans_array as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo $p['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Duration (Months)</label>
                        <input type="number" name="months" class="form-control rounded-3" value="1" min="1" max="60" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3">Confirm Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$('#subModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    $('#tenantId').val(button.data('id'));
    $('#tenantName').text(button.data('name'));
});
</script>

</body>
</html>
