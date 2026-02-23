<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Attendance Scanner - SAM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .scanner-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2em;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1em;
        }

        .scan-area {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 60px 40px;
            margin: 30px 0;
            position: relative;
            overflow: hidden;
        }

        .scan-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .scan-text {
            color: white;
            font-size: 1.5em;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .scan-instruction {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1em;
        }

        #rfidInput {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .status-message {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 1.1em;
            font-weight: 600;
            display: none;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .status-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        .status-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 2px solid #bee5eb;
        }

        .student-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            display: none;
        }

        .student-info h3 {
            color: #667eea;
            margin-bottom: 15px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #666;
        }

        .info-value {
            color: #333;
        }

        .recent-scans {
            margin-top: 30px;
            text-align: left;
        }

        .recent-scans h3 {
            color: #333;
            margin-bottom: 15px;
        }

        .scan-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #667eea;
        }

        .scan-item.time-in {
            border-left-color: #28a745;
        }

        .scan-item.time-out {
            border-left-color: #dc3545;
        }

        .scan-name {
            font-weight: 600;
            color: #333;
        }

        .scan-time {
            color: #666;
            font-size: 0.9em;
        }

        .scan-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-in {
            background: #d4edda;
            color: #155724;
        }

        .badge-out {
            background: #f8d7da;
            color: #721c24;
        }

        .loading {
            display: none;
            margin: 20px 0;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .sound-effect {
            display: none;
        }

        .admin-link {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            color: #667eea;
            font-weight: 600;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
        }

        .admin-link:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>
<body>
    <a href="Admin_dashboard.php" class="admin-link">🏠 Back to Dashboard</a>

    <div class="scanner-container">
        <div class="logo">📱</div>
        <h1>SAM Attendance Scanner</h1>
        <p class="subtitle">Smart Attendance Monitoring System</p>

        <div class="scan-area" id="scanArea">
            <div class="scan-icon">💳</div>
            <div class="scan-text">Ready to Scan</div>
            <div class="scan-instruction">Tap your RFID card on the reader</div>
        </div>

        <!-- Hidden input for RFID scanner -->
        <input type="text" id="rfidInput" autocomplete="off" autofocus>

        <div class="loading" id="loading">
            <div class="spinner"></div>
        </div>

        <div class="status-message" id="statusMessage"></div>

        <div class="student-info" id="studentInfo">
            <h3>Student Information</h3>
            <div class="info-row">
                <span class="info-label">Name:</span>
                <span class="info-value" id="studentName">-</span>
            </div>
            <div class="info-row">
                <span class="info-label">Action:</span>
                <span class="info-value" id="studentAction">-</span>
            </div>
            <div class="info-row">
                <span class="info-label">Time:</span>
                <span class="info-value" id="studentTime">-</span>
            </div>
            <div class="info-row">
                <span class="info-label">Date:</span>
                <span class="info-value" id="studentDate">-</span>
            </div>
        </div>

        <div class="recent-scans">
            <h3>Recent Scans</h3>
            <div id="recentScansList"></div>
        </div>

        <!-- Sound effects -->
        <audio id="successSound" class="sound-effect">
            <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBjiK1vLTgjMGHXDJ8N+UPwoXabzr67VZFQg3mNzyvmAa" type="audio/wav">
        </audio>
        <audio id="errorSound" class="sound-effect">
            <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgQ==" type="audio/wav">
        </audio>
    </div>

    <script>
        const rfidInput = document.getElementById('rfidInput');
        const statusMessage = document.getElementById('statusMessage');
        const studentInfo = document.getElementById('studentInfo');
        const scanArea = document.getElementById('scanArea');
        const loading = document.getElementById('loading');
        const recentScansList = document.getElementById('recentScansList');
        const successSound = document.getElementById('successSound');
        const errorSound = document.getElementById('errorSound');

        let recentScans = [];
        let lastScanTime = 0;
        const SCAN_COOLDOWN = 3000; // 3 seconds

        // Keep focus on RFID input
        document.addEventListener('click', () => {
            rfidInput.focus();
        });

        window.addEventListener('blur', () => {
            setTimeout(() => rfidInput.focus(), 100);
        });

        // Handle RFID scan
        rfidInput.addEventListener('keypress', async (e) => {
            if (e.key === 'Enter') {
                const rfidUid = rfidInput.value.trim().toUpperCase();
                rfidInput.value = '';

                if (!rfidUid) return;

                // Check cooldown
                const now = Date.now();
                if (now - lastScanTime < SCAN_COOLDOWN) {
                    showStatus('Please wait before scanning again', 'info');
                    return;
                }
                lastScanTime = now;

                await processRFID(rfidUid);
            }
        });

        async function processRFID(rfidUid) {
            showLoading(true);
            hideStatus();
            studentInfo.style.display = 'none';

            try {
                // Try different possible paths
                let response;
                const apiKey = 'SAM_RFID_2024_SecureKey_12345'; // CHANGE THIS!
                
                try {
                    response = await fetch('backend/rfid_process.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-API-KEY': apiKey
                        },
                        body: JSON.stringify({ rfid_uid: rfidUid })
                    });
                } catch (e) {
                    // Try absolute path if relative fails
                    response = await fetch('/SAM_system/backend/rfid_process.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-API-KEY': apiKey
                        },
                        body: JSON.stringify({ rfid_uid: rfidUid })
                    });
                }

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('HTTP Error:', response.status, errorText);
                    throw new Error(`Server error (${response.status}): ${errorText.substring(0, 100)}`);
                }

                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    const text = await response.text();
                    console.error('Not JSON response:', text);
                    throw new Error('Server returned invalid response. Check backend/rfid_process.php');
                }

                const data = await response.json();

                showLoading(false);

                if (data.success) {
                    // Success
                    successSound.play().catch(() => {});
                    showStatus(data.message, 'success');
                    
                    // Display student info
                    document.getElementById('studentName').textContent = data.student_name || '-';
                    document.getElementById('studentAction').textContent = data.action === 'time_in' ? 'Time In' : 'Time Out';
                    document.getElementById('studentTime').textContent = data.time || '-';
                    document.getElementById('studentDate').textContent = data.date || '-';
                    studentInfo.style.display = 'block';

                    // Add to recent scans
                    addRecentScan(data);

                    // Visual feedback
                    scanArea.style.background = 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)';
                    setTimeout(() => {
                        scanArea.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                    }, 2000);

                } else {
                    // Error
                    errorSound.play().catch(() => {});
                    showStatus(data.message || 'Scan failed', 'error');

                    // Visual feedback
                    scanArea.style.background = 'linear-gradient(135deg, #eb3349 0%, #f45c43 100%)';
                    setTimeout(() => {
                        scanArea.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                    }, 2000);
                }

            } catch (error) {
                showLoading(false);
                errorSound.play().catch(() => {});
                
                // Better error message
                let errorMsg = 'Connection error. ';
                if (error.message.includes('Failed to fetch')) {
                    errorMsg += 'Cannot find rfid_process.php file. Check if backend folder exists.';
                } else if (error.message.includes('404')) {
                    errorMsg += 'File not found (404). Check file path.';
                } else if (error.message.includes('401')) {
                    errorMsg += 'Unauthorized. Check API key.';
                } else {
                    errorMsg += error.message;
                }
                
                showStatus(errorMsg, 'error');
                console.error('Full Error:', error);
                console.error('Tried paths:', 
                    'backend/rfid_process.php', 
                    '/SAM_system/backend/rfid_process.php'
                );
            }
        }

        function showStatus(message, type) {
            statusMessage.textContent = message;
            statusMessage.className = `status-message status-${type}`;
            statusMessage.style.display = 'block';

            setTimeout(() => {
                statusMessage.style.display = 'none';
            }, 5000);
        }

        function hideStatus() {
            statusMessage.style.display = 'none';
        }

        function showLoading(show) {
            loading.style.display = show ? 'block' : 'none';
        }

        function addRecentScan(data) {
            const scan = {
                name: data.student_name,
                action: data.action,
                time: data.time,
                timestamp: new Date().getTime()
            };

            recentScans.unshift(scan);
            if (recentScans.length > 5) recentScans.pop();

            updateRecentScans();
        }

        function updateRecentScans() {
            if (recentScans.length === 0) {
                recentScansList.innerHTML = '<p style="color: #999; text-align: center;">No recent scans</p>';
                return;
            }

            recentScansList.innerHTML = recentScans.map(scan => `
                <div class="scan-item ${scan.action === 'time_in' ? 'time-in' : 'time-out'}">
                    <div>
                        <div class="scan-name">${scan.name}</div>
                        <div class="scan-time">${scan.time}</div>
                    </div>
                    <span class="scan-badge ${scan.action === 'time_in' ? 'badge-in' : 'badge-out'}">
                        ${scan.action === 'time_in' ? 'Time In' : 'Time Out'}
                    </span>
                </div>
            `).join('');
        }

        // Initialize
        rfidInput.focus();
        updateRecentScans();

        // Auto-refresh focus every 5 seconds
        setInterval(() => {
            if (document.activeElement !== rfidInput) {
                rfidInput.focus();
            }
        }, 5000);
    </script>
</body>
</html>