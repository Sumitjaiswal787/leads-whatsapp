require('dotenv').config();
const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode');
const qrcodeTerminal = require('qrcode-terminal');
const express = require('express');
const bodyParser = require('body-parser');
const axios = require('axios');
const path = require('path');
const fs = require('fs');
const puppeteer = require('puppeteer');
const http = require('http');
const { Server } = require('socket.io');
const metaHandler = require('./metaHandler');

const app = express();
const httpServer = http.createServer(app);
const io = new Server(httpServer, {
    cors: {
        origin: ["https://whatsapp.tezikaro.com", "http://localhost:3000", "http://localhost:8080"],
        methods: ["GET", "POST"],
        credentials: true
    }
});

io.on('connection', (socket) => {
    console.log(`[Socket] New client connected: ${socket.id}`);
    
    socket.on('subscribe', (sessionId) => {
        socket.join(sessionId);
        console.log(`[Socket] Client ${socket.id} subscribed to room: ${sessionId}`);
    });
    
    socket.on('disconnect', () => {
        console.log(`[Socket] Client disconnected: ${socket.id}`);
    });
});
const port = process.env.PORT || 3001;
const SHARED_SECRET = process.env.SHARED_SECRET || "a_secure_shared_secret_here";
const PHP_CALLBACK_URL = process.env.PHP_CALLBACK_URL || "http://localhost:8000/api/callback.php";

app.use(bodyParser.json());

// Meta Webhook Routes
app.get('/webhooks/meta', (req, res) => metaHandler.verifyWebhook(req, res));
app.post('/webhooks/meta', (req, res) => metaHandler.handleWebhook(req, res));

/**
 * Mock WhatsApp Client for Bypassing Authentication
 */
class MockClient {
    constructor(sessionId, userId) {
        this.sessionId = sessionId;
        this.userId = userId;
        this.info = { wid: { user: '917324838976' }, pushname: 'Test Account (Bypassed)' };
        this.events = {};
    }

    on(event, callback) { this.events[event] = callback; }
    
    async initialize() {
        console.log(`[${this.sessionId}] Mock Client Initializing...`);
        // Simulate real-time connection events
        setTimeout(() => {
            if (this.events['ready']) this.events['ready']();
        }, 3000);
    }

    async getChats() { return []; }
    async destroy() { console.log(`[${this.sessionId}] Mock Client Destroyed.`); }
    async logout() { console.log(`[${this.sessionId}] Mock Client Logged Out.`); }
    async getContactById(id) { return { name: 'Test Contact' }; }
}

// Map to store active WhatsApp clients
const clients = new Map();

/**
 * Find Chrome Executable on Windows
 */
function getChromePath() {
    if (process.env.PUPPETEER_EXECUTABLE_PATH) {
        return process.env.PUPPETEER_EXECUTABLE_PATH;
    }

    const paths = [
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Users\\' + process.env.USERNAME + '\\AppData\\Local\\Google\\Chrome\\Application\\chrome.exe'
    ];

    for (const p of paths) {
        if (fs.existsSync(p)) return p;
    }
    return puppeteer.executablePath();
}

/**
 * Helper: Send Status Update to PHP
 */
async function updatePHPStatus(sessionId, updateData) {
    try {
        await axios.post(`${PHP_CALLBACK_URL}?secret=${SHARED_SECRET}`, {
            action: 'update_status',
            sessionId,
            ...updateData
        });
    } catch (error) {
        console.error(`[${sessionId}] Failed to update PHP status:`, error.message);
    }
}

function broadcastStatus(sessionId, status) {
    console.log(`[Socket] Broadcasting status: ${status} for session: ${sessionId}`);
    io.to(sessionId).emit('status', { sessionId, status });
}

/**
 * Initialize a WhatsApp Client
 */
function initWhatsAppClient(sessionId, userId, phoneNumber = null) {
    if (clients.has(sessionId)) return clients.get(sessionId);

    broadcastStatus(sessionId, 'initializing');

    // Bypassing / Mock Mode: phoneNumber === 'mock'
    if (phoneNumber === 'mock') {
        const client = new MockClient(sessionId, userId);
        client.on('ready', async () => {
            console.log(`[${sessionId}] Mock Client is ready!`);
            updatePHPStatus(sessionId, { 
                status: 'connected', 
                name: 'Test Account (Bypassed)', 
                number: '917324838976' 
            });
            broadcastStatus(sessionId, 'connected');
        });

        client.initialize();
        clients.set(sessionId, client);
        return client;
    }

    console.log(`[${sessionId}] Initializing client (Pairing: ${phoneNumber || 'QR'})...`);
    
    const clientOptions = {
        authStrategy: new LocalAuth({ clientId: sessionId }),
        puppeteer: {
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--single-process',
                '--disable-gpu'
            ],
            executablePath: getChromePath()
        },
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        authTimeoutMs: 60000,
        qrMaxRetries: 10
    };

    if (phoneNumber) {
        clientOptions.pairWithPhoneNumber = {
            phoneNumber: phoneNumber,
            showNotification: true
        };
    }

    const client = new Client(clientOptions);

    /**
     * Sync Recently Received Messages
     */
    async function syncChatHistory(client, sessionId, userId) {
        console.log(`[${sessionId}] Syncing message history...`);
        try {
            const chats = await client.getChats();
            const syncData = [];
            
            for (const chat of chats) {
                // Ignore groups and archived? For now just non-groups
                if (chat.isGroup) continue;
                
                const contact = await chat.getContact();
                const phone = contact.number || chat.id.user;
                const name = contact.pushname || contact.name || chat.name || 'Unknown';

                const messages = await chat.fetchMessages({ limit: 50 });
                for (const msg of messages) {
                    syncData.push({
                        messageId: msg.id._serialized,
                        phone: phone,
                        originalId: chat.id.user,
                        name: name,
                        body: msg.body,
                        sender: msg.fromMe ? 'me' : 'lead',
                        timestamp: msg.timestamp
                    });
                }
            }
            
            if (syncData.length > 0) {
                await axios.post(`${PHP_CALLBACK_URL}?secret=${SHARED_SECRET}`, {
                    action: 'sync_messages',
                    sessionId,
                    userId,
                    messages: syncData
                });
                console.log(`[${sessionId}] Synced ${syncData.length} historical messages.`);
            }
        } catch (e) {
            console.error(`[${sessionId}] History sync failed:`, e.message);
        }
    }

    client.on('qr', async (qr) => {
        if (phoneNumber) return; // Ignore QR if pairing by phone
        console.log(`[${sessionId}] QR received:`);
        qrcodeTerminal.generate(qr, { small: true });
        const qrBase64 = await qrcode.toDataURL(qr);
        updatePHPStatus(sessionId, { status: 'qr_ready', qr: qrBase64 });
        
        console.log(`[Socket] Broadcasting QR for session: ${sessionId}`);
        io.to(sessionId).emit('qr', { sessionId, qr: qr });
        broadcastStatus(sessionId, 'qr_ready');
    });

    client.on('code', async (code) => {
        console.log(`[${sessionId}] Pairing Code: ${code}`);
        updatePHPStatus(sessionId, { status: 'pairing_ready', pairingCode: code });
        broadcastStatus(sessionId, 'pairing_ready');
    });

    client.on('ready', async () => {
        console.log(`[${sessionId}] Client is ready!`);
        try {
            const info = client.info;
            const number = info.wid.user || client.info.me.user;
            const name = info.pushname || (await client.getContactById(info.wid._serialized)).name || 'WhatsApp User';
            
            console.log(`[${sessionId}] Connected as: ${name} (${number})`);
            
            updatePHPStatus(sessionId, { 
                status: 'connected', 
                name: name, 
                number: number 
            });
            broadcastStatus(sessionId, 'connected');

            // Start History Sync
            syncChatHistory(client, sessionId, userId);
            
        } catch (e) {
            console.error(`[${sessionId}] Error getting client info:`, e.message);
            updatePHPStatus(sessionId, { status: 'connected' });
            broadcastStatus(sessionId, 'connected');
        }
    });

    client.on('message', async (msg) => {
        if (msg.from.includes('@g.us')) return;

        const phone = msg.from.split('@')[0];
        const message = msg.body;
        const messageId = msg.id._serialized;

        console.log(`[${sessionId}] New message from ${phone}: ${message}`);
        
        try {
            const contact = await msg.getContact();
            const resolvedPhone = contact.number || phone;

            await axios.post(`${PHP_CALLBACK_URL}?secret=${SHARED_SECRET}`, {
                action: 'log_message',
                sessionId,
                phone: resolvedPhone,
                message,
                messageId
            });

            await axios.post(`${PHP_CALLBACK_URL}?secret=${SHARED_SECRET}`, {
                action: 'new_lead',
                sessionId,
                userId,
                phone: resolvedPhone,
                originalId: phone,
                name: contact.pushname || contact.name || 'Unknown',
                message,
                messageId
            });
            
        } catch (error) {
            console.error(`[${sessionId}] Failed to process message:`, error.message);
        }
    });

    client.on('disconnected', (reason) => {
        console.log(`[${sessionId}] Client disconnected:`, reason);
        updatePHPStatus(sessionId, { status: 'disconnected' });
        broadcastStatus(sessionId, 'disconnected');
        clients.delete(sessionId);
    });

    client.on('auth_failure', (msg) => {
        console.error(`[${sessionId}] Authentication failure:`, msg);
        updatePHPStatus(sessionId, { status: 'disconnected' });
        broadcastStatus(sessionId, 'disconnected');
    });

    client.initialize().catch(err => {
        console.error(`[${sessionId}] FATAL Initialization failed:`, err);
        updatePHPStatus(sessionId, { status: 'disconnected' });
        broadcastStatus(sessionId, 'disconnected');
    });

    clients.set(sessionId, client);
    return client;
}

/**
 * API Endpoint: Initialize Session
 */
app.post('/api/sessions/init', (req, res) => {
    const { sessionId, phoneNumber, secret } = req.body;
    const userId = req.body.userId || req.body.tenantId || '1';
    
    console.log(`[INIT] Session request received - ID: ${sessionId}, User/Tenant: ${userId}, Phone: ${phoneNumber || 'None'}`);
    
    if (secret !== 'whatsapp_crm_secret_2026') {
        return res.status(401).json({ success: false, error: 'Unauthorized' });
    }

    if (!sessionId || !userId) {
        return res.status(400).json({ success: false, message: 'Missing required parameters: sessionId or tenantId' });
    }

    initWhatsAppClient(sessionId, userId, phoneNumber);
    res.json({ success: true, message: 'Initialization started' });
});

/**
 * API Endpoint: Delete Session
 */
app.post('/api/sessions/delete', async (req, res) => {
    const { sessionId, secret } = req.body;
    
    if (secret !== 'whatsapp_crm_secret_2026') {
        return res.status(401).json({ success: false, error: 'Unauthorized' });
    }

    if (!sessionId) {
        return res.status(400).json({ success: false, message: 'Missing required parameter: sessionId' });
    }

    if (clients.has(sessionId)) {
        const client = clients.get(sessionId);
        try {
            await client.logout();
            await client.destroy();
            clients.delete(sessionId);
            
            // Clean up session folder
            const sessionPath = path.join(__dirname, '.wwebjs_auth', `session-${sessionId}`);
            if (fs.existsSync(sessionPath)) {
                fs.rmSync(sessionPath, { recursive: true, force: true });
            }
        } catch (e) {
            console.error("Cleanup error", e);
        }
    }
    res.json({ success: true, message: 'Session deleted' });
});

/**
 * API Endpoint: Send Message
 */
app.post('/api/messages/send', async (req, res) => {
    const { sessionId, number, message, secret } = req.body;
    console.log(`[API] Received send message request for session: ${sessionId}, number: ${number}`);
    
    if (secret !== 'whatsapp_crm_secret_2026') {
        console.warn(`[API] Unauthorized send message attempt for session: ${sessionId}`);
        return res.status(401).json({ error: 'Unauthorized' });
    }

    if (!sessionId || !number || !message) {
        return res.status(400).json({ error: 'Missing required parameters: sessionId, number, or message' });
    }

    if (!clients.has(sessionId)) {
        return res.status(404).json({ success: false, error: 'Session not found or not active' });
    }

    try {
        const client = clients.get(sessionId);
        
        let formattedNumber = number;
        if (!formattedNumber.endsWith('@c.us')) {
            formattedNumber = `${formattedNumber}@c.us`;
        }

        await client.sendMessage(formattedNumber, message);
        res.json({ success: true, message: 'Message sent successfully' });
    } catch (error) {
        console.error(`[API] Send message failed for session ${sessionId}:`, error.message);
        res.status(500).json({ success: false, error: error.message });
    }
});

httpServer.listen(port, () => {
    console.log(`Worker service listening at http://localhost:${port}`);
});
