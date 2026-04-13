<?php require_once 'config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | WhatsApp Lead Grabber CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #25D366;
            --secondary: #128C7E;
            --dark: #075E54;
            --light: #ECE5DD;
            --accent: #34B7F1;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #075E54 0%, #128C7E 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 12px;
            border-radius: 10px;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary);
            color: #fff;
            box-shadow: none;
        }
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: #1eb954;
            transform: translateY(-2px);
        }
        .nav-tabs {
            border: none;
            margin-bottom: 30px;
        }
        .nav-link {
            color: rgba(255,255,255,0.6);
            border: none !important;
            font-weight: 600;
        }
        .nav-link.active {
            background: transparent !important;
            color: var(--primary) !important;
            border-bottom: 2px solid var(--primary) !important;
        }
        .alert {
            display: none;
            margin-top: 20px;
            border-radius: 10px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <h2 class="fw-bold">Lead Grabber</h2>
        <p class="text-white-50">Manage your WhatsApp leads with ease</p>
    </div>

    <ul class="nav nav-tabs nav-justified" id="authTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button">Login</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button">Register</button>
        </li>
    </ul>

    <div class="tab-content" id="authTabsContent">
        <!-- Login Form -->
        <div class="tab-pane fade show active" id="login">
            <form id="loginForm">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Sign In</button>
            </form>
        </div>

        <!-- Register Form -->
        <div class="tab-pane fade" id="register">
            <form id="registerForm">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="John Doe" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Choose a username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Create a password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Create Account</button>
            </form>
        </div>
    </div>

    <div id="authAlert" class="alert alert-danger" role="alert"></div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    function showAlert(msg, type = 'danger') {
        $('#authAlert').text(msg).removeClass('alert-danger alert-success').addClass('alert-' + type).fadeIn();
        setTimeout(() => $('#authAlert').fadeOut(), 3000);
    }

    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        $.post('api/login.php', $(this).serialize(), function(res) {
            const data = JSON.parse(res);
            if (data.status === 'success') {
                showAlert(data.message, 'success');
                setTimeout(() => {
                    if (data.role === 'superadmin') {
                        window.location.href = 'admin/superadmin/dashboard.php';
                    } else if (data.role === 'admin') {
                        window.location.href = 'admin/dashboard.php';
                    } else {
                        window.location.href = 'dashboard/staff.php';
                    }
                }, 1000);
            } else {
                showAlert(data.message);
            }
        });
    });

    $('#registerForm').on('submit', function(e) {
        e.preventDefault();
        $.post('api/register.php', $(this).serialize(), function(res) {
            const data = JSON.parse(res);
            if (data.status === 'success') {
                showAlert(data.message, 'success');
                $('#login-tab').click();
            } else {
                showAlert(data.message);
            }
        });
    });
});
</script>

</body>
</html>
