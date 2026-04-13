<?php
require_once '../../config/config.php';
checkAuth('superadmin');

// Fetch Metrics
$total_tenants = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
$active_subs = $conn->query("SELECT COUNT(*) as count FROM subscriptions WHERE status = 'active'")->fetch_assoc()['count'];
$total_plans = $conn->query("SELECT COUNT(*) as count FROM plans")->fetch_assoc()['count'];
$recent_tenants = $conn->query("SELECT username, full_name, created_at FROM users WHERE role = 'admin' ORDER BY created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin | <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #25D366;
            --secondary: #128C7E;
            --dark: #075E54;
            --light: #f8f9fa;
        }
        body { font-family: 'Outfit', sans-serif; background: #f4f7f6; }
        .sidebar { height: 100vh; background: var(--dark); color: white; position: fixed; width: 250px; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 15px 25px; }
        .sidebar .nav-link.active { background: var(--secondary); color: white; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); }
        .main-content { margin-left: 250px; padding: 40px; }
        .stat-card { border-radius: 15px; border: none; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .icon-box { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="p-4">
        <h4 class="fw-bold"><i class="bi bi-shield-check"></i> Super Panel</h4>
    </div>
    <nav class="nav flex-column mt-4">
        <a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
        <a class="nav-link" href="plans.php"><i class="bi bi-box me-2"></i> Manage Plans</a>
        <a class="nav-link" href="tenants.php"><i class="bi bi-people me-2"></i> Monitor Tenants</a>
        <a class="nav-link" href="../../api/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
    </nav>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold">Welcome, Super Admin</h2>
            <p class="text-muted">Global overview of your WhatsApp CRM Platform</p>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card stat-card shadow-sm p-3">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-primary text-white me-3">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0">Total Tenants</h6>
                        <h3 class="fw-bold mb-0"><?php echo $total_tenants; ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card shadow-sm p-3">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-success text-white me-3">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0">Active Subscriptions</h6>
                        <h3 class="fw-bold mb-0"><?php echo $active_subs; ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card shadow-sm p-3">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-info text-white me-3">
                        <i class="bi bi-layers"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0">Plan Tiers</h6>
                        <h3 class="fw-bold mb-0"><?php echo $total_plans; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white p-4 border-0">
            <h5 class="fw-bold mb-0">Recent Registrations</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Username</th>
                            <th>Full Name</th>
                            <th>Signed Up</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($tenant = $recent_tenants->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4"><strong><?php echo $tenant['username']; ?></strong></td>
                            <td><?php echo $tenant['full_name']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($tenant['created_at'])); ?></td>
                            <td><span class="badge bg-success-subtle text-success px-3">Active</span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
