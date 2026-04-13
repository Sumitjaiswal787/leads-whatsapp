<?php
require_once '../../config/config.php';
checkAuth('superadmin');

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $max_sessions = $_POST['max_sessions'] ?? 0;
    $max_staff = $_POST['max_staff'] ?? 0;
    $price = $_POST['price'] ?? 0;
    $duration_days = $_POST['duration_days'] ?? 30;

    if ($action === 'create') {
        $stmt = $conn->prepare("INSERT INTO plans (name, max_sessions, max_staff, price, duration_days) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("siidi", $name, $max_sessions, $max_staff, $price, $duration_days);
        $stmt->execute();
    } elseif ($action === 'update') {
        $stmt = $conn->prepare("UPDATE plans SET name=?, max_sessions=?, max_staff=?, price=?, duration_days=? WHERE id=?");
        $stmt->bind_param("siidii", $name, $max_sessions, $max_staff, $price, $duration_days, $id);
        $stmt->execute();
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM plans WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: plans.php");
    exit();
}

$plans = $conn->query("SELECT * FROM plans ORDER BY price ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Plans | Super Admin</title>
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
        .card { border-radius: 15px; border: none; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="p-4"><h4 class="fw-bold"><i class="bi bi-shield-check"></i> Super Panel</h4></div>
    <nav class="nav flex-column mt-4">
        <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
        <a class="nav-link active" href="plans.php"><i class="bi bi-box me-2"></i> Manage Plans</a>
        <a class="nav-link" href="tenants.php"><i class="bi bi-people me-2"></i> Monitor Tenants</a>
        <a class="nav-link" href="../../api/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
    </nav>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h2 class="fw-bold">Subscription Plans</h2>
        <button class="btn btn-primary px-4 py-2 rounded-3" data-bs-toggle="modal" data-bs-target="#planModal">
            <i class="bi bi-plus-lg me-2"></i> Create New Plan
        </button>
    </div>

    <div class="row g-4">
        <?php while($plan = $plans->fetch_assoc()): ?>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <h4 class="fw-bold mb-0"><?php echo $plan['name']; ?></h4>
                        <span class="badge bg-primary-subtle text-primary px-3 py-2">₹<?php echo $plan['price']; ?></span>
                    </div>
                    <ul class="list-unstyled mb-4">
                        <li class="mb-2"><i class="bi bi-whatsapp text-success me-2"></i> <?php echo $plan['max_sessions']; ?> WhatsApp Sessions</li>
                        <li class="mb-2"><i class="bi bi-people text-info me-2"></i> <?php echo $plan['max_staff']; ?> Staff Members</li>
                        <li class="mb-2"><i class="bi bi-calendar-event text-warning me-2"></i> <?php echo $plan['duration_days']; ?> Days</li>
                    </ul>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button class="btn btn-light btn-sm edit-btn" 
                                data-id="<?php echo $plan['id']; ?>"
                                data-name="<?php echo $plan['name']; ?>"
                                data-sessions="<?php echo $plan['max_sessions']; ?>"
                                data-staff="<?php echo $plan['max_staff']; ?>"
                                data-price="<?php echo $plan['price']; ?>"
                                data-duration="<?php echo $plan['duration_days']; ?>"
                                data-bs-toggle="modal" data-bs-target="#planModal">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <form method="POST" onsubmit="return confirm('Delete this plan?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $plan['id']; ?>">
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Plan Modal -->
<div class="modal fade" id="planModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <form method="POST">
                <div class="modal-header border-0 p-4">
                    <h5 class="modal-title fw-bold" id="modalTitle">Create New Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="planId">
                    <div class="mb-3">
                        <label class="form-label">Plan Name</label>
                        <input type="text" name="name" id="planName" class="form-control rounded-3" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Sessions</label>
                            <input type="number" name="max_sessions" id="planSessions" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Staff</label>
                            <input type="number" name="max_staff" id="planStaff" class="form-control rounded-3" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price (INR)</label>
                            <input type="number" step="0.01" name="price" id="planPrice" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration (Days)</label>
                            <input type="number" name="duration_days" id="planDuration" class="form-control rounded-3" value="30" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3">Save Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$('.edit-btn').click(function() {
    $('#modalTitle').text('Edit Plan');
    $('#formAction').val('update');
    $('#planId').val($(this).data('id'));
    $('#planName').val($(this).data('name'));
    $('#planSessions').val($(this).data('sessions'));
    $('#planStaff').val($(this).data('staff'));
    $('#planPrice').val($(this).data('price'));
    $('#planDuration').val($(this).data('duration'));
});
$('#planModal').on('hidden.bs.modal', function() {
    $('#modalTitle').text('Create New Plan');
    $('#formAction').val('create');
    $('#planId, #planName, #planSessions, #planStaff, #planPrice').val('');
    $('#planDuration').val('30');
});
</script>

</body>
</html>
