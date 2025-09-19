<?php
// =========================================================================
// == BOOTSTRAP MINIMAL & SECURITY
// =========================================================================
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
// This is a kiosk page, we only include the essentials to avoid conflicts
require_once __DIR__ . '../../../../config/config.php';
require_once __DIR__ . '../../../../includes/auth.php';
require_once __DIR__ . '../../../../includes/functions.php';

redirectIfNotHR();

// =========================================================================
// == DATA FETCHING & INITIALIZATION
// =========================================================================
// Using a prepared statement is better practice
$settings_stmt = $db->prepare("SELECT company_name, logo_path, attendance_method, scan_mode FROM company_settings WHERE id = ?");
$settings_stmt->execute([1]);
$settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);

$company_name = $settings['company_name'] ?? 'SN-TECH';
$logo_path = $settings['logo_path'] ?? null;
$attendance_method = $settings['attendance_method'] ?? 'qrcode';
$scan_mode = $settings['scan_mode'] ?? 'keyboard';
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Kiosque Pointage - <?= htmlspecialchars($company_name) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --kiosk-bg: #1a1a1a;
            --card-bg: rgba(35, 35, 35, 0.6);
            --card-border: rgba(255, 255, 255, 0.1);
            --text-primary: #ffffff;
            --text-secondary: #adb5bd;
            --accent-green: #20c997;
            --accent-red: #fd7e14;
            --accent-neutral: #495057;
        }

        html, body {
            height: 100%;
            overflow: hidden; /* Prevent scrolling on kiosk */
        }

        body {
            background-color: var(--kiosk-bg);
            background-image: linear-gradient(45deg, rgba(255, 255, 255, 0.02) 25%, transparent 25%),
                              linear-gradient(-45deg, rgba(255, 255, 255, 0.02) 25%, transparent 25%),
                              linear-gradient(45deg, transparent 75%, rgba(255, 255, 255, 0.02) 75%),
                              linear-gradient(-45deg, transparent 75%, rgba(255, 255, 255, 0.02) 75%);
            background-size: 20px 20px;
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
        }

        .kiosk-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            padding: 2rem;
            gap: 1.5rem;
        }
        
        .kiosk-header {
            text-align: center;
        }
        
        .kiosk-main-grid {
            flex-grow: 1;
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            gap: 1.5rem;
            height: 100%;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 1rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px 0 rgba(0, 0, 0, 0.45);
        }

        .card-header-custom {
            padding: 1rem 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-header-green { background-color: var(--accent-green); color: #fff; }
        .card-header-red { background-color: var(--accent-red); color: #fff; }
        .card-header-neutral { background-color: var(--accent-neutral); color: #fff; }

        .card-body {
            padding: 1rem;
            overflow-y: auto;
            flex-grow: 1;
        }

        .card-body::-webkit-scrollbar { width: 6px; }
        .card-body::-webkit-scrollbar-track { background: transparent; }
        .card-body::-webkit-scrollbar-thumb { background-color: rgba(255, 255, 255, 0.2); border-radius: 10px; }

        .employee-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 0.75rem;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            animation: popIn 0.5s ease-out;
        }
        
        .employee-card img {
            width: 44px; height: 44px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1rem;
            border: 2px solid var(--card-border);
        }
        .employee-info strong { font-size: 0.9rem; font-weight: 500; }
        .employee-info span { font-size: 0.8rem; color: var(--text-secondary); }

        #scan-result-area {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            gap: 1.5rem;
        }

        .scan-prompt-icon {
            font-size: 8rem;
            color: var(--text-secondary);
            animation: pulse 2s infinite ease-in-out;
        }
        
        .card-footer { border-top: 1px solid var(--card-border); }

        #qr-reader { border: none; border-radius: 0.75rem; }

        @keyframes popIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.7; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(1); opacity: 0.7; }
        }
    </style>
</head>
<body>

<div class="kiosk-container">
    <header class="kiosk-header">
        <?php if ($logo_path && file_exists(__DIR__ . '/../../../../' . $logo_path)): ?>
            <img src="/<?= htmlspecialchars($logo_path) ?>" alt="<?= htmlspecialchars($company_name) ?> Logo" style="max-height: 50px; margin-bottom: 0.5rem;">
        <?php else: ?>
            <h1 class="display-6"><?= htmlspecialchars($company_name) ?></h1>
        <?php endif; ?>
        <p class="lead text-body-secondary" id="current-date"></p>
    </header>

    <?php if ($attendance_method !== 'qrcode'): ?>
        <div class="alert alert-warning text-center w-50 mx-auto">
            <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Mode Biométrique Activé</h4>
            <p>Cette interface de pointage par QR Code est désactivée car le système est configuré pour utiliser un scanner biométrique.</p>
        </div>
    <?php else: ?>
    <main class="kiosk-main-grid">
        <div class="card">
            <div class="card-header-custom card-header-green"><i class="bi bi-box-arrow-in-right"></i>Entrées Récentes</div>
            <div class="card-body" id="check-in-feed">
                <p class="text-center text-body-secondary mt-4">En attente d'entrées...</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header-custom card-header-neutral"><i class="bi bi-qr-code-scan"></i>Panneau de Pointage</div>
            <div class="card-body" id="scan-result-area">
                <!-- UI will be injected here -->
            </div>
            <div class="card-footer bg-transparent py-3">
                <div id="clock" class="fs-2 fw-bold text-center"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header-custom card-header-red"><i class="bi bi-box-arrow-right"></i>Sorties Récentes</div>
            <div class="card-body" id="check-out-feed">
                <p class="text-center text-body-secondary mt-4">En attente de sorties...</p>
            </div>
        </div>
    </main>
    
    <form id="scan-form" style="height: 0; overflow: hidden; opacity: 0;">
        <input type="text" id="nin-input" autocomplete="off" autofocus>
    </form>
    <?php endif; ?>
</div>

<audio id="success-sound" src="<?= APP_LINK ?>/assets/sounds/success.mp3" preload="auto"></audio>
<audio id="error-sound" src="<?= APP_LINK ?>/assets/sounds/error.mp3" preload="auto"></audio>

<?php if ($attendance_method === 'qrcode'): ?>
<script src="<?= APP_LINK ?>/assets/js/Scanner.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Constants and Elements ---
    const SCAN_MODE = '<?= $scan_mode ?>';
    const resultContainer = document.getElementById('scan-result-area');
    const successSound = document.getElementById('success-sound');
    const errorSound = document.getElementById('error-sound');
    const clockElement = document.getElementById('clock');
    const dateElement = document.getElementById('current-date');
    const ninInput = document.getElementById('nin-input');
    
    let html5QrCode = null;
    let isProcessing = false; // Prevents double scans

    // --- UI Management ---
    const UI = {
        showLoading: (msg = "Chargement...") => {
            resultContainer.innerHTML = `<div class="text-center"><div class="spinner-border text-primary mb-3"></div><p>${msg}</p></div>`;
        },
        showKeyboardUI: () => {
            resultContainer.innerHTML = `<i class="bi bi-upc-scan scan-prompt-icon"></i><h3 class="fw-light">Veuillez scanner un badge...</h3>`;
            ninInput.focus();
        },
        showCameraPromptUI: () => {
            resultContainer.innerHTML = `<i class="bi bi-camera-video" style="font-size: 8rem;"></i><h3 class="mt-3 fw-light">Prêt à scanner avec la caméra</h3><button id="start-camera-btn" class="btn btn-primary btn-lg mt-3"><i class="bi bi-camera me-2"></i>Démarrer la Caméra</button>`;
            document.getElementById('start-camera-btn').addEventListener('click', startCamera);
        },
        showPermissionDeniedUI: () => {
            resultContainer.innerHTML = `<div class="alert alert-danger text-center"><i class="bi bi-exclamation-octagon-fill fs-1"></i><h4 class="mt-3">Accès à la caméra refusé</h4><p>Veuillez autoriser l'accès à la caméra dans les paramètres de votre navigateur, puis rafraîchissez la page.</p></div>`;
        },
        showCameraUI: () => {
            resultContainer.innerHTML = `<div id="qr-reader" style="width: 100%; max-width: 250px;"></div><button id="stop-camera-btn" class="btn btn-outline-danger mt-3">Arrêter le scan</button>`;
            document.getElementById('stop-camera-btn').addEventListener('click', stopCamera);
        },
        showScanResult: (html) => {
            resultContainer.innerHTML = html;
        }
    };

    // --- Core Functions ---
    function startCamera() {
        if (isProcessing || (html5QrCode && html5QrCode.isScanning)) return;

        UI.showLoading("Initialisation de la caméra...");
        
        // Use a slight delay to ensure the UI is rendered before starting the camera
        setTimeout(() => {
            UI.showCameraUI();
            html5QrCode = new Html5Qrcode("qr-reader");

            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 220, height: 220 } },
                (decodedText) => {
                    if (!isProcessing) {
                        isProcessing = true;
                        html5QrCode.pause();
                        processScan(decodedText);
                    }
                },
                (errorMessage) => { /* Ignore non-scans */ }
            ).catch(err => {
                console.error("Erreur de démarrage de la caméra:", err);
                UI.showPermissionDeniedUI();
            });
        }, 100);
    }

    function stopCamera() {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop()
                .then(() => {
                    isProcessing = false;
                    html5QrCode = null;
                    initializeScanner();
                })
                .catch(err => console.error("Erreur d'arrêt de la caméra:", err));
        } else {
             isProcessing = false;
             initializeScanner();
        }
    }

    function processScan(scannedText) {
        let nin = null;
        try {
            const url = new URL(scannedText);
            if (url.searchParams.has('nin')) {
                nin = url.searchParams.get('nin');
            }
        } catch (_) {
            nin = scannedText;
        }

        if(!nin || isProcessing) {
            if(!nin) console.error("NIN could not be extracted from scanned text:", scannedText);
            return;
        };

        isProcessing = true;
        UI.showLoading("Vérification en cours...");

        fetch('<?= route("attendance_scan_handler") ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ nin: nin })
        })
        .then(response => response.json())
        .then(data => {
            const isSuccess = data.status === 'success';
            const sound = isSuccess ? successSound : errorSound;
            sound.play();

            let icon = isSuccess ? 'check-circle-fill text-success' : 'x-octagon-fill text-danger';
            let html = `<div class="text-center" style="animation: popIn 0.5s ease;"><i class="bi bi-${icon}" style="font-size: 8rem;"></i><h2 class="mt-3">${data.message}</h2>`;

            if (isSuccess && data.employeeName) {
                html += `<p class="display-6 mb-0">${data.employeeName}</p><p class="text-body-secondary fs-4">${data.scanTime}</p>`;
                updateSideFeeds();
            }
            html += `</div>`;
            UI.showScanResult(html);
        })
        .catch(error => {
            console.error("Fetch error:", error);
            errorSound.play();
            UI.showScanResult(`<div class="alert alert-danger">Une erreur de communication avec le serveur est survenue.</div>`);
        })
        .finally(() => {
            if (SCAN_MODE === 'keyboard') {
                ninInput.value = '';
            }
            setTimeout(() => {
                isProcessing = false;
                if (SCAN_MODE === 'keyboard') {
                    initializeScanner();
                } else if (html5QrCode) {
                    try {
                        html5QrCode.resume();
                    } catch(e) {
                        initializeScanner();
                    }
                }
            }, 4000);
        });
    }

    function createEmployeeCard(emp) {
        const photo = `<?= APP_LINK ?>${emp.photo_path || '/assets/images/default-avatar.png'}`;
        return `<div class="employee-card"><div class="d-flex align-items-center"><img src="${photo}" onerror="this.onerror=null; this.src='<?= APP_LINK ?>/assets/images/default-avatar.png';"><div class="employee-info d-flex flex-column"><strong>${emp.first_name} ${emp.last_name}</strong><span>${emp.department || 'N/A'}</span></div></div><span class="badge text-bg-light fw-bold">${emp.scan_time}</span></div>`;
    }

    async function updateSideFeeds() {
        try {
            const response = await fetch('<?= Proute("api_public_feed") ?>');
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();
            
            const checkInFeed = document.getElementById('check-in-feed');
            const checkOutFeed = document.getElementById('check-out-feed');

            checkInFeed.innerHTML = data.last_check_ins && data.last_check_ins.length > 0 ? data.last_check_ins.map(createEmployeeCard).join('') : '<p class="text-center text-body-secondary mt-4">En attente d\'entrées...</p>';
            checkOutFeed.innerHTML = data.last_check_outs && data.last_check_outs.length > 0 ? data.last_check_outs.map(createEmployeeCard).join('') : '<p class="text-center text-body-secondary mt-4">En attente de sorties...</p>';
        } catch (error) {
            console.error('Could not update side feeds:', error);
        }
    }

    function updateClock() {
        clockElement.textContent = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    function initializeScanner() {
        if (SCAN_MODE === 'keyboard') {
            UI.showKeyboardUI();
        } else if (SCAN_MODE === 'camera') {
            if (navigator.permissions && navigator.permissions.query) {
                navigator.permissions.query({ name: 'camera' }).then(permissionStatus => {
                    if (permissionStatus.state === 'granted') {
                        startCamera();
                    } else if (permissionStatus.state === 'prompt') {
                        UI.showCameraPromptUI();
                    } else { // denied
                        UI.showPermissionDeniedUI();
                    }
                    permissionStatus.onchange = () => initializeScanner();
                });
            } else {
                UI.showCameraPromptUI();
            }
        }
    }

    // --- Initial Setup ---
    document.getElementById('scan-form').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!isProcessing && SCAN_MODE === 'keyboard') {
            processScan(ninInput.value.trim());
        }
    });

    dateElement.textContent = new Date().toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    updateClock();
    updateSideFeeds();
    
    setInterval(updateClock, 1000);
    setInterval(updateSideFeeds, 10000); // Refresh feeds every 10 seconds
    
    initializeScanner();
});
</script>
<?php endif; ?>

</body>
</html>
