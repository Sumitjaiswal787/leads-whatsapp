<?php
require_once '../config/config.php';
checkAuth('admin');
$tenant_id = getTenantId();

// Handle Form Submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fb_page_id = $_POST['fb_page_id'] ?? '';
    $fb_access_token = $_POST['fb_access_token'] ?? '';

    $stmt = $conn->prepare("UPDATE users SET fb_page_id = ?, fb_access_token = ? WHERE id = ?");
    $stmt->bind_param("ssi", $fb_page_id, $fb_access_token, $tenant_id);
    
    if ($stmt->execute()) {
        $message = "Meta settings updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating settings: " . $conn->error;
        $message_type = "danger";
    }
}

// Fetch Current Settings
$stmt = $conn->prepare("SELECT fb_page_id, fb_access_token FROM users WHERE id = ?");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meta Settings | Lead Grabber</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #25D366; --dark: #121212; --card-bg: #1e1e1e; }
        body { font-family: 'Outfit', sans-serif; background: var(--dark); color: #fff; overflow-x: hidden; }
        
        .sidebar { 
            background: #181818; 
            min-height: 100vh; 
            position: fixed; 
            width: 250px; 
            border-right: 1px solid #333; 
            z-index: 1001;
            transition: all 0.3s;
        }
        
        .main-content { 
            margin-left: 250px; 
            padding: 40px; 
            transition: all 0.3s;
        }

        /* Mobile Adjustments */
        @media (max-width: 991.98px) {
            .sidebar {
                margin-left: -250px;
            }
            .sidebar.show {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
                padding-top: 80px;
            }
            .settings-card {
                padding: 15px;
            }
        }

        .settings-card { background: var(--card-bg); border-radius: 15px; padding: 30px; border: 1px solid #333; }
        .form-control { background: #2a2a2a; border: 1px solid #444; color: #fff; }
        .form-control:focus { background: #333; color: #fff; border-color: var(--primary); box-shadow: none; }
        .btn-primary { background: var(--primary); border: none; font-weight: 600; }
        .list-group-item.active { background-color: var(--primary); border-color: var(--primary); }

        /* Mobile Navbar */
        .mobile-nav {
            display: none;
            background: #181818;
            padding: 15px 20px;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            border-bottom: 1px solid #333;
        }

        @media (max-width: 991.98px) {
            .mobile-nav {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .sidebar-overlay.show {
            display: block;
        }
    </style>
</head>
<body>

<!-- Mobile Navbar -->
<div class="mobile-nav">
    <h4 class="fw-bold text-primary mb-0">Grabber</h4>
    <button class="btn text-white p-0" id="sidebarToggle">
        <i class="bi bi-list fs-2"></i>
    </button>
</div>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="p-4 text-center d-none d-lg-block">
        <h3 class="fw-bold text-primary">Grabber</h3>
    </div>
    <div class="list-group list-group-flush mt-4">
        <a href="dashboard.php" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3">
            <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
        <a href="meta_settings.php" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3 active">
            <i class="bi bi-facebook me-2"></i> Meta Settings
        </a>
        <a href="leads_v2.php" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3">
            <i class="bi bi-people me-2"></i> Leads
        </a>
        <a href="../api/logout.php" class="list-group-item list-group-item-action bg-transparent text-white border-0 p-3">
            <i class="bi bi-box-arrow-left me-2"></i> Logout
        </a>
    </div>
</div>

<div class="main-content">
    <div class="mb-5">
        <h1 class="fw-bold mb-1">Meta Lead Ads Integration</h1>
        <p class="text-secondary">Configure your Facebook Page and API Access Token to receive leads from Meta Ads.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="settings-card">
                <!-- Facebook Connect Helper Panel -->
                <div class="p-4 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 mb-4">
                    <h5 class="fw-bold text-primary mb-2"><i class="bi bi-facebook me-2"></i> Auto-Connect Facebook Page</h5>
                    <p class="text-secondary small mb-3">Instead of copying IDs and tokens manually, enter your Facebook App ID below and connect directly via Facebook Graph API.</p>
                    <div class="row g-3 align-items-end mb-1">
                        <div class="col-md-7">
                            <label class="form-label small text-secondary fw-bold">Facebook App ID</label>
                            <input type="text" id="fb_app_id" class="form-control" placeholder="e.g. 129384857692">
                        </div>
                        <div class="col-md-5">
                            <button type="button" class="btn btn-primary w-100 py-2 fw-bold" id="fb-login-btn">
                                <i class="bi bi-facebook me-2"></i> Connect Page
                            </button>
                        </div>
                    </div>
                    <div class="form-text text-secondary mt-2 small">Your App ID is stored locally in your browser for convenience.</div>
                </div>

                <form action="meta_settings.php" method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Facebook Page ID</label>
                        <input type="text" name="fb_page_id" class="form-control form-control-lg" 
                               value="<?= htmlspecialchars($settings['fb_page_id'] ?? '') ?>" 
                               placeholder="e.g. 1092837465092" required>
                        <div class="form-text text-secondary">You can find this in your Facebook Page "About" section or Business Settings.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Graph API User/Page Access Token</label>
                        <textarea name="fb_access_token" class="form-control" rows="4" 
                                  placeholder="EAAb... " required><?= htmlspecialchars($settings['fb_access_token'] ?? '') ?></textarea>
                        <div class="form-text text-secondary">Ensure this token has <code>pages_manage_ads</code>, <code>leads_retrieval</code>, and <code>pages_show_list</code> permissions. It is recommended to use a Long-Lived System User Token.</div>
                    </div>

                    <hr class="my-4 border-secondary">

                    <div class="mb-4">
                        <label class="form-label fw-bold">Webhook Configuration</label>
                        <div class="p-3 bg-dark rounded border border-secondary mb-2">
                            <p class="mb-1"><small class="text-secondary">Callback URL:</small></p>
                            <code class="text-info"><?= BACKEND_URL ?>/webhooks/meta</code>
                            <p class="mt-3 mb-1"><small class="text-secondary">Verify Token:</small></p>
                            <code class="text-info">whatsapp_crm_meta_v1</code>
                        </div>
                        <div class="form-text text-secondary">Copy these values into your Meta App's Webhook configuration (Page object, <code>leadgen</code> field).</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg px-5">Save Settings</button>
                </form>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="settings-card">
                <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2 text-info"></i> Setup Guide</h5>
                <ol class="small text-secondary ps-3">
                    <li class="mb-2">Create a Meta App (Type: Business).</li>
                    <li class="mb-2">Add the <b>Webhooks</b> product.</li>
                    <li class="mb-2">Subscribe to <b>Page</b> webhooks and select <code>leadgen</code>.</li>
                    <li class="mb-2">Set the Callback URL and Verify Token provided on this page.</li>
                    <li class="mb-2">Generate a System User token with Page access.</li>
                    <li class="mb-2">Save the Page ID and Token here.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Facebook Page Selection -->
<div class="modal fade" id="fbPageModal" tabindex="-1" aria-labelledby="fbPageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="fbPageModalLabel"><i class="bi bi-facebook text-primary me-2"></i> Select Facebook Page</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary small">Choose the Facebook Page you want to connect to receive lead forms advertisements information.</p>
                <div class="mb-3">
                    <label class="form-label fw-bold">Your Facebook Pages</label>
                    <select id="fb-page-select" class="form-select form-control">
                        <!-- Options dynamically loaded -->
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-confirm-page">Select and Import</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Load the Facebook SDK asynchronously
(function(d, s, id) {
    var js, fjs = d.getElementsByTagName(s)[0];
    if (d.getElementById(id)) return;
    js = d.createElement(s); js.id = id;
    js.src = "https://connect.facebook.net/en_US/sdk.js";
    fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));

$(document).ready(function() {
    // Load cached App ID from localStorage
    const cachedAppId = localStorage.getItem('fb_app_id');
    if (cachedAppId) {
        $('#fb_app_id').val(cachedAppId);
    }

    // Sidebar Toggle
    $('#sidebarToggle, #sidebarOverlay').on('click', function() {
        $('#sidebar, #sidebarOverlay').toggleClass('show');
    });

    // Close sidebar when clicking a link on mobile
    $('.sidebar .list-group-item').on('click', function() {
        if ($(window).width() < 992) {
            $('#sidebar, #sidebarOverlay').removeClass('show');
        }
    });

    // Initialize Facebook Login when the connect button is clicked
    $('#fb-login-btn').on('click', function() {
        const appId = $('#fb_app_id').val().trim();
        if (!appId) {
            alert('Please enter your Facebook App ID first to initialize Facebook Login.');
            return;
        }
        
        // Save App ID to localStorage for convenience
        localStorage.setItem('fb_app_id', appId);

        try {
            // Initialize the FB SDK
            FB.init({
                appId      : appId,
                cookie     : true,
                xfbml      : true,
                version    : 'v21.0'
            });

            // Launch FB Login dialog
            FB.login(function(response) {
                if (response.authResponse) {
                    const userAccessToken = response.authResponse.accessToken;
                    console.log('[FB] Successfully logged in with Facebook. User Token obtained.');
                    fetchUserPages(userAccessToken);
                } else {
                    alert('Facebook Login cancelled or not fully authorized.');
                }
            }, {
                scope: 'pages_show_list,pages_read_engagement,pages_manage_ads,leads_retrieval',
                return_scopes: true
            });
        } catch (err) {
            console.error('[FB] Init Error:', err);
            alert('Error initializing Facebook Login: ' + err.message);
        }
    });

    // Fetch Pages using User Access Token
    function fetchUserPages(userAccessToken) {
        $('#fb-login-btn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Loading Pages...');
        
        FB.api('/me/accounts', { access_token: userAccessToken }, function(response) {
            $('#fb-login-btn').prop('disabled', false).html('<i class="bi bi-facebook me-2"></i> Connect Page');
            
            if (response.error) {
                alert('Error fetching pages: ' + response.error.message);
                return;
            }

            const pages = response.data;
            if (!pages || pages.length === 0) {
                alert('No Facebook Pages found associated with your account. Make sure you are an administrator of at least one page.');
                return;
            }

            // Build a Page selection dropdown in a modal
            let pageOptionsHtml = '<option value="" disabled selected>-- Select a Facebook Page --</option>';
            pages.forEach(page => {
                pageOptionsHtml += `<option value="${page.id}" data-token="${page.access_token}">${page.name} (${page.category})</option>`;
            });

            $('#fb-page-select').html(pageOptionsHtml);
            
            // Show the page selection modal
            const pageModal = new bootstrap.Modal(document.getElementById('fbPageModal'));
            pageModal.show();
        });
    }

    // When a page is selected, populate the form inputs
    $('#btn-confirm-page').on('click', function() {
        const selectedOption = $('#fb-page-select option:selected');
        const pageId = selectedOption.val();
        const pageToken = selectedOption.data('token');

        if (!pageId) {
            alert('Please select a Facebook Page.');
            return;
        }

        $('input[name="fb_page_id"]').val(pageId);
        $('textarea[name="fb_access_token"]').val(pageToken);

        // Hide modal
        bootstrap.Modal.getInstance(document.getElementById('fbPageModal')).hide();
        
        alert('Successfully imported Facebook Page ID and Page Access Token! Click "Save Settings" below to save them permanently.');
    });
});
</script>
</body>
</html>
