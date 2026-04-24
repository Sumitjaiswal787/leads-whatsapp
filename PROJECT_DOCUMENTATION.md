# WhatsApp Lead Grabber CRM Documentation

## 1. Project Overview
The **WhatsApp Lead Grabber CRM** is a multi-tenant platform designed to capture, manage, and assign leads coming from WhatsApp. It allows business owners (Admins) to connect their WhatsApp accounts via QR code, automatically capture incoming messages as leads, and distribute them among staff members using a Round-Robin assignment logic.

## 2. Key Features
- **Multi-Tenancy**: Support for Superadmins, Admins, and Staff.
- **WhatsApp Integration**: Connect accounts using Baileys (Node.js library) with QR code scanning.
- **Lead Capture**: Automatic capturing of WhatsApp messages and converting them into actionable leads.
- **Staff Management**: Admins can add staff and assign leads.
- **Round-Robin Assignment**: Automatic distribution of new leads to active staff members.
- **Chat History**: Complete history of messages per lead.
- **Subscription Plans**: Tiered access (Basic, Pro, Premium) with session and staff limits.
- **Payment Integration**: Razorpay integration for subscription management.
- **Real-time Status**: Live connection status of WhatsApp sessions.

## 3. Technology Stack
### Frontend / Web Server (PHP)
- **Language**: PHP 7.4+
- **Styling**: Vanilla CSS, Bootstrap (inferred from common CRM patterns)
- **Database**: MySQL
- **Environment**: XAMPP (Local), Hostinger (Production)

### Messaging Backend (Node.js)
- **Language**: Node.js (ES Modules)
- **Library**: `@whiskeysockets/baileys` (WhatsApp Web API)
- **Framework**: Express.js (API), Socket.io (Real-time updates)
- **Loggying**: Pino

## 4. System Architecture
The system follows an split-architecture:
1.  **PHP Web App**: Handles user authentication, dashboard UI, subscription management, and CRUD operations for leads/staff.
2.  **Node.js Worker**: A standalone service that maintains persistent WhatsApp socket connections. It communicates with the PHP app via webhooks/callbacks and an API secret.
3.  **MySQL Database**: Shared storage for both systems to ensure data consistency.

## 5. Database Schema (whatsapp_crm)
| Table | Description |
|---|---|
| `users` | Stores accounts for Superadmins, Admins, and Staff. |
| `plans` | Defines subscription tiers and their limits. |
| `subscriptions`| Links tenants (Admins) to specific plans. |
| `whatsapp_sessions`| Stores WhatsApp session metadata and connection status. |
| `leads` | Individual lead records captured from WhatsApp. |
| `whatsapp_messages`| Complete message logs for every lead. |
| `assign_queue` | Holds state for the Round-Robin lead assignment. |
| `auto_reply_settings`| Configuration for automated welcome messages. |
| `payment_transactions`| Logs for Razorpay payments. |

## 6. Folder Structure
```text
/
├── admin/              # Admin dashboard and lead management UI
├── api/                # PHP backend endpoints (REST-like)
├── backend/            # PHP backend logic / helpers
├── config/             # Configuration files (DB, API keys)
├── dashboard/          # Additional dashboard components
├── includes/           # Shared PHP components (header, footer)
├── leads-whatsapp-backend/ # Node.js WhatsApp Worker service
├── sessions/           # WhatsApp session data storage
├── scripts/            # Database migration and utility scripts
├── uploads/            # Media/file uploads
├── schema.sql          # Primary database schema
└── index.php           # Landing / Login page
```

## 7. Setup Instructions

### Prerequisites
- PHP 7.4+ & MySQL
- Node.js 18+
- Composer (Optional)

### Local Environment
1.  **Database**: Create a database named `whatsapp_crm` and import `schema.sql`.
2.  **PHP Config**: Update `config/config.php` with your local DB credentials.
3.  **Node.js Backend**:
    - Navigate to `leads-whatsapp-backend/`.
    - Run `npm install`.
    - Create a `.env` file or rely on `config.php` constants.
    - Run `npm start`.
4.  **Web Server**: Point your local server (e.g., Apache/NGINX) to the project root.

### Production (Hostinger/Railway)
- The PHP app is typically hosted on Hostinger.
- The Node.js backend is configured to run on Railway (as seen in `config.php`).
- Ensure `WORKER_API_SECRET` matches across both environments.

## 8. Lead Lifecycle
1.  **Incoming Event**: A user sends a message to the connected WhatsApp number.
2.  **Webhook Trigger**: The Node.js worker receives the message and POSTs it to `api/callback.php`.
3.  **Authentication**: `callback.php` verifies the `WORKER_API_SECRET`.
4.  **Lead Identification**: The system checks if the sender (phone number/JID) already exists as a lead for that tenant.
5.  **Assignment (New Leads)**:
    - If the lead is new, the system fetches all active staff members for the tenant.
    - It uses the `assign_queue` to find the next staff member in sequence (Round-Robin).
    - The lead is created and assigned to that staff member.
6.  **Auto-Reply**: If enabled, the system sends a welcome message back to the Node.js worker to be delivered to the user.
7.  **History Logging**: Every incoming and outgoing message is logged in the `whatsapp_messages` table.

## 9. Communication Flow
- **QR Code Generation**: PHP (Frontend) Request -> Node.js API -> Socket.io Broadcast -> Browser Display.
- **Messenger Updates**: Node.js Worker -> HTTP POST (Webhook) -> PHP `api/callback.php`.
- **Outgoing Messages**: PHP (Staff Action) -> HTTP POST -> Node.js API -> Baileys Socket.
- **Real-time UI**: The frontend uses standard polling or periodic refreshes to show new leads (Vite/React patterns observed in some sibling projects, though this looks like standard PHP/jQuery here).
