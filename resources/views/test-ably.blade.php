<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ably WebSocket Test - Family Connect</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2563eb;
            text-align: center;
            margin-bottom: 30px;
        }
        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }
        .success {
            border-color: #10b981;
            background: #f0fdf4;
        }
        .error {
            border-color: #ef4444;
            background: #fef2f2;
        }
        .status {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .success .status {
            color: #10b981;
        }
        .error .status {
            color: #ef4444;
        }
        button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #1d4ed8;
        }
        pre {
            background: #f9fafb;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 14px;
        }
        .log {
            max-height: 200px;
            overflow-y: auto;
            background: #1f2937;
            color: #f9fafb;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
        }
        .timestamp {
            color: #9ca3af;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Ably WebSocket Test</h1>
        
        <div id="server-test" class="test-section">
            <div class="status">Server Broadcasting Test</div>
            <p>Testing Laravel → Ably broadcasting from server...</p>
            <button onclick="testServerBroadcast()">Send Test Event</button>
            <pre id="server-result">Click button to test</pre>
        </div>

        <div id="client-test" class="test-section">
            <div class="status">Client Connection Test</div>
            <p>Testing browser → Ably connection...</p>
            <button onclick="testClientConnection()">Test Connection</button>
            <div id="connection-status">Not connected</div>
        </div>

        <div class="test-section">
            <div class="status">Real-time Message Log</div>
            <div id="message-log" class="log">Waiting for messages...</div>
        </div>
    </div>

    <script src="https://cdn.ably.com/lib/ably.min-1.js"></script>
    <script>
        let ably = null;
        let channel = null;

        function log(message) {
            const logElement = document.getElementById('message-log');
            const timestamp = new Date().toLocaleTimeString();
            logElement.innerHTML += `<div><span class="timestamp">[${timestamp}]</span> ${message}</div>`;
            logElement.scrollTop = logElement.scrollHeight;
        }

        async function testServerBroadcast() {
            try {
                const response = await fetch('/test-ably-broadcast', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                });
                
                const result = await response.json();
                document.getElementById('server-result').textContent = JSON.stringify(result, null, 2);
                
                if (result.status === 'success') {
                    document.getElementById('server-test').className = 'test-section success';
                    log('✅ Server broadcast successful');
                } else {
                    document.getElementById('server-test').className = 'test-section error';
                    log('❌ Server broadcast failed');
                }
            } catch (error) {
                document.getElementById('server-result').textContent = 'Error: ' + error.message;
                document.getElementById('server-test').className = 'test-section error';
                log('❌ Server test error: ' + error.message);
            }
        }

        async function testClientConnection() {
            try {
                // Use the same Ably key from your Laravel config
                ably = new Ably.Realtime('{{ config('broadcasting.connections.ably.key') }}');
                
                ably.connection.on('connected', () => {
                    document.getElementById('connection-status').innerHTML = '✅ Connected to Ably';
                    document.getElementById('client-test').className = 'test-section success';
                    log('✅ Browser connected to Ably');
                    
                    // Subscribe to test channel
                    channel = ably.channels.get('test-channel');
                    channel.subscribe('test-event', (message) => {
                        log('📨 Received: ' + JSON.stringify(message.data));
                    });
                });
                
                ably.connection.on('failed', (error) => {
                    document.getElementById('connection-status').innerHTML = '❌ Connection failed: ' + error.message;
                    document.getElementById('client-test').className = 'test-section error';
                    log('❌ Connection failed: ' + error.message);
                });

            } catch (error) {
                document.getElementById('connection-status').innerHTML = '❌ Error: ' + error.message;
                document.getElementById('client-test').className = 'test-section error';
                log('❌ Client error: ' + error.message);
            }
        }

        // Initialize logging
        log('🔌 Page loaded, ready to test Ably WebSocket');
    </script>
</body>
</html>