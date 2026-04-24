<?php 
require_once '../config/config.php';
checkAuth();
$user_id = $_SESSION['user_id'];
$tenant_id = getTenantId();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Panel | Lead Grabber</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #25D366; --dark: #121212; --card-bg: #1e1e1e; }
        body { font-family: 'Outfit', sans-serif; background: var(--dark); color: #fff; height: 100vh; overflow: hidden; }
        .main-wrapper { display: flex; height: 100vh; position: relative; }
        
        .leads-sidebar { 
            width: 350px; 
            background: #181818; 
            border-right: 1px solid #333; 
            display: flex; 
            flex-direction: column; 
            transition: all 0.3s;
            z-index: 10;
        }
        
        .chat-area { 
            flex-grow: 1; 
            display: flex; 
            flex-direction: column; 
            background: #0b141a; 
            position: relative;
        }

        /* Mobile View Logic */
        @media (max-width: 767.98px) {
            .leads-sidebar {
                width: 100%;
                position: absolute;
                left: 0;
                top: 0;
            }
            .chat-area {
                width: 100%;
                position: absolute;
                left: 0;
                top: 0;
                display: none; /* Hidden by default on mobile until lead selected */
            }
            .chat-area.show-mobile {
                display: flex !important;
            }
            .leads-sidebar.hide-mobile {
                display: none !important;
            }
        }

        .leads-list { overflow-y: auto; flex-grow: 1; }
        .lead-item { padding: 15px 20px; border-bottom: 1px solid #222; cursor: pointer; transition: background 0.2s; }
        .lead-item:hover { background: #252525; }
        .lead-item.active { background: #2d2d2d; border-left: 4px solid var(--primary); }
        .lead-name { font-weight: 600; margin-bottom: 2px; }
        .lead-last-msg { font-size: 0.85rem; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .chat-header { padding: 10px 15px; background: #202c33; border-bottom: 1px solid #333; display: flex; justify-content: space-between; align-items: center; }
        .chat-messages { flex-grow: 1; overflow-y: auto; padding: 25px; display: flex; flex-direction: column; gap: 10px; }
        .msg { max-width: 85%; padding: 10px 15px; border-radius: 10px; font-size: 0.95rem; line-height: 1.4; }
        .msg-in { align-self: flex-start; background: #202c33; color: #e9edef; border-bottom-left-radius: 0; }
        .msg-out { align-self: flex-end; background: #005c4b; color: #e9edef; border-bottom-right-radius: 0; }
        .chat-input { padding: 15px; background: #202c33; border-top: 1px solid #333; }
        .form-control { background: #2a3942; border: none; color: #fff; border-radius: 25px; padding: 10px 20px; }
        .form-control:focus { background: #33434d; color: #fff; box-shadow: none; }
        .badge-hot { background: #dc3545; }
        .badge-warm { background: #ffc107; color: #000; }
        .badge-cold { background: #0dcaf0; color: #000; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        
        .back-btn { display: none; }
        @media (max-width: 767.98px) {
            .back-btn { display: block; margin-right: 10px; }
        }
    </style>
</head>
<body>

<div class="main-wrapper">
    <!-- Leads Sidebar -->
    <div class="leads-sidebar" id="leadsSidebar">
        <div class="p-3 border-bottom border-secondary d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-primary">My Leads</h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary d-md-none" onclick="loadLeads()"><i class="bi bi-arrow-clockwise"></i></button>
                <a href="../api/logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-left"></i></a>
            </div>
        </div>
        <div class="p-2">
            <input type="text" class="form-control form-control-sm" placeholder="Search leads...">
        </div>
        <div class="leads-list" id="leadsList">
            <!-- Leads will be loaded here -->
        </div>
    </div>

    <!-- Chat Area -->
    <div class="chat-area" id="chatArea">
        <div id="noChatSelected" class="h-100 d-flex flex-column align-items-center justify-content-center text-center p-5">
            <div class="mb-4" style="font-size: 5rem; opacity: 0.2;"><i class="bi bi-whatsapp"></i></div>
            <h4>WhatsApp Lead Manager</h4>
            <p class="text-secondary">Select a lead from the sidebar to start chatting.<br>Messages will be sent directly via WhatsApp.</p>
        </div>

        <div id="chatContent" class="h-100 flex-column" style="display: none !important;">
            <div class="chat-header">
                <div class="d-flex align-items-center">
                    <button class="btn text-white p-0 back-btn" onclick="backToList()">
                        <i class="bi bi-arrow-left fs-4"></i>
                    </button>
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2 me-md-3" style="width: 40px; height: 40px;">
                        <i class="bi bi-person text-white fs-5"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold" id="chatLeadName">Name</h6>
                        <small class="text-secondary" style="font-size: 0.7rem;" id="chatLeadNumber">Number</small>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm bg-dark text-white border-secondary" id="updateStatus">
                        <option value="new">New</option>
                        <option value="contacted">Contacted</option>
                        <option value="closed">Closed</option>
                    </select>
                    <select class="form-select form-select-sm bg-dark text-white border-secondary" id="updateTag">
                        <option value="none">No Tag</option>
                        <option value="hot">Hot</option>
                        <option value="warm">Warm</option>
                        <option value="cold">Cold</option>
                    </select>
                </div>
            </div>

            <div class="chat-messages" id="chatMessages">
                <!-- Messages -->
            </div>

            <div class="chat-input">
                <form id="sendMessageForm" class="d-flex gap-2">
                    <input type="hidden" name="lead_id" id="currentLeadId">
                    <input type="text" name="message" class="form-control" placeholder="Type a message..." required autocomplete="off">
                    <button type="submit" class="btn btn-primary rounded-circle" style="width: 45px; height: 45px;">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.socket.io/4.5.4/socket.io.min.js"></script>

<script>
let currentLeadId = null;
const socket = io('<?= BACKEND_URL ?>');

function loadLeads() {
    $.get('../api/get-leads.php', function(res) {
        const leads = JSON.parse(res);
        let html = '';
        leads.forEach(l => {
            const activeClass = (l.id == currentLeadId) ? 'active' : '';
            const tagBadge = l.tag !== 'none' ? `<span class="badge badge-${l.tag} float-end">${l.tag}</span>` : '';
            html += `
                <div class="lead-item ${activeClass}" onclick="selectLead(${l.id}, '${l.name}', '${l.number}', '${l.status}', '${l.tag}')">
                    ${tagBadge}
                    <div class="lead-name">${l.name || l.number}</div>
                    <div class="lead-last-msg">${l.last_message || 'No messages yet'}</div>
                </div>
            `;
        });
        $('#leadsList').html(html || '<div class="p-4 text-center text-muted">No leads assigned</div>');
    });
}

function selectLead(id, name, number, status, tag) {
    currentLeadId = id;
    $('.lead-item').removeClass('active');
    $(`[onclick*="selectLead(${id}"]`).addClass('active');

    $('#noChatSelected').attr('style', 'display: none !important');
    $('#chatContent').attr('style', 'display: flex !important');
    
    // Mobile toggle
    if ($(window).width() < 768) {
        $('#leadsSidebar').addClass('hide-mobile');
        $('#chatArea').addClass('show-mobile');
    }

    $('#chatLeadName').text(name || number);
    $('#chatLeadNumber').text('+' + number);
    $('#currentLeadId').val(id);
    $('#updateStatus').val(status);
    $('#updateTag').val(tag);

    loadChatHistory(id);
}

function backToList() {
    $('#leadsSidebar').removeClass('hide-mobile');
    $('#chatArea').removeClass('show-mobile');
    currentLeadId = null;
    $('.lead-item').removeClass('active');
}

function loadChatHistory(id) {
    $.get(`../api/get-chat-history.php?lead_id=${id}`, function(res) {
        const messages = JSON.parse(res);
        let html = '';
        messages.forEach(m => {
            const side = m.direction === 'incoming' ? 'in' : 'out';
            html += `<div class="msg msg-${side}">${m.message}</div>`;
        });
        const container = $('#chatMessages');
        container.html(html);
        container.scrollTop(container[0].scrollHeight);
    });
}

$('#sendMessageForm').on('submit', function(e) {
    e.preventDefault();
    const form = $(this);
    const msg = form.find('input[name="message"]').val();
    $.post('../api/send-message.php', form.serialize(), function(res) {
        const data = JSON.parse(res);
        if (data.status === 'success') {
            form.find('input[name="message"]').val('');
            loadChatHistory(currentLeadId);
        } else {
            alert(data.message);
        }
    });
});

$('#updateStatus, #updateTag').on('change', function() {
    $.post('../api/update-lead.php', {
        lead_id: currentLeadId,
        status: $('#updateStatus').val(),
        tag: $('#updateTag').val()
    }, function() {
        loadLeads();
    });
});

// Real-time: Refresh if new lead or message arrives
// In a production app, we would join a room for this tenant
socket.on('status', function(data) {
    // Refresh leads list on status updates or new messages
    loadLeads();
    if (currentLeadId) loadChatHistory(currentLeadId);
});

$(document).ready(function() {
    loadLeads();
    // Auto refresh every 30s as fallback
    setInterval(loadLeads, 30000);
});
</script>

</body>
</html>
