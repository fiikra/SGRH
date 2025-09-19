<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Kiosque Pointage - <?= htmlspecialchars($company_name) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        /* Le CSS du fichier scanner.php original est placé ici, sans modification */
        :root { --kiosk-bg: #1a1a1a; --card-bg: rgba(35, 35, 35, 0.6); --card-border: rgba(255, 255, 255, 0.1); --text-primary: #ffffff; --text-secondary: #adb5bd; --accent-green: #20c997; --accent-red: #fd7e14; --accent-neutral: #495057; }
        html, body { height: 100%; overflow: hidden; }
        body { background-color: var(--kiosk-bg); font-family: 'Inter', sans-serif; color: var(--text-primary); display: flex; flex-direction: column; }
        .kiosk-container { flex-grow: 1; display: flex; flex-direction: column; padding: 2rem; gap: 1.5rem; }
        .kiosk-header { text-align: center; }
        .kiosk-main-grid { flex-grow: 1; display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 1.5rem; height: 100%; }
        .card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 1rem; backdrop-filter: blur(10px); display: flex; flex-direction: column; }
        .card-header-custom { padding: 1rem 1.5rem; font-weight: 600; border-bottom: 1px solid var(--card-border); display: flex; align-items: center; gap: 0.75rem; }
        .card-header-green { background-color: var(--accent-green); color: #fff; }
        .card-header-red { background-color: var(--accent-red); color: #fff; }
        .card-header-neutral { background-color: var(--accent-neutral); color: #fff; }
        .card-body { padding: 1rem; overflow-y: auto; flex-grow: 1; }
        .employee-card { display: flex; align-items: center; justify-content: space-between; background-color: rgba(255, 255, 255, 0.05); border-radius: 0.75rem; padding: 0.75rem; margin-bottom: 0.75rem; animation: popIn 0.5s ease-out; }
        .employee-card img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; margin-right: 1rem; border: 2px solid var(--card-border); }
        #scan-result-area { display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; gap: 1.5rem; }
        .scan-prompt-icon { font-size: 8rem; color: var(--text-secondary); animation: pulse 2s infinite ease-in-out; }
        .card-footer { border-top: 1px solid var(--card-border); }
        #qr-reader { border: none; border-radius: 0.75rem; }
        @keyframes popIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        @keyframes pulse { 0% { transform: scale(1); opacity: 0.7; } 50% { transform: scale(1.05); opacity: 1; } 100% { transform: scale(1); opacity: 0.7; } }
    </style>
</head>
<body>

<div class="kiosk-container">
    <header class="kiosk-header">
        <?php if ($logo_path && file_exists(APP_ROOT . '/' . $logo_path)): ?>
            <img src="/<?= htmlspecialchars($logo_path) ?>" alt="Logo" style="max-height: 50px; margin-bottom: 0.5rem;">
        <?php else: ?>
            <h1 class="display-6"><?= htmlspecialchars($company_name) ?></h1>
        <?php endif; ?>
        <p class="lead text-body-secondary" id="current-date"></p>
    </header>

    <?php if ($attendance_method !== 'qrcode'): ?>
        <div class="alert alert-warning text-center w-50 mx-auto">
            <h4><i class="bi bi-exclamation-triangle-fill"></i> Mode Biométrique Activé</h4>
            <p>Cette interface est désactivée car le système est configuré pour un scanner biométrique.</p>
        </div>
    <?php else: ?>
    <main class="kiosk-main-grid">
        <div class="card"><div class="card-header-custom card-header-green"><i class="bi bi-box-arrow-in-right"></i>Entrées Récentes</div><div class="card-body" id="check-in-feed"></div></div>
        <div class="card"><div class="card-header-custom card-header-neutral"><i class="bi bi-qr-code-scan"></i>Panneau de Pointage</div><div class="card-body" id="scan-result-area"></div><div class="card-footer bg-transparent py-3"><div id="clock" class="fs-2 fw-bold text-center"></div></div></div>
        <div class="card"><div class="card-header-custom card-header-red"><i class="bi bi-box-arrow-right"></i>Sorties Récentes</div><div class="card-body" id="check-out-feed"></div></div>
    </main>
    <form id="scan-form" class="visually-hidden"><input type="text" id="nin-input" autocomplete="off" autofocus></form>
    <?php endif; ?>
</div>

<audio id="success-sound" src="/assets/sounds/success.mp3" preload="auto"></audio>
<audio id="error-sound" src="/assets/sounds/error.mp3" preload="auto"></audio>

<?php if ($attendance_method === 'qrcode'): ?>
<script src="https://unpkg.com/html5-qrcode/html5-qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const SCAN_MODE = '<?= $scan_mode ?>';
    const resultContainer = document.getElementById('scan-result-area');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    let isProcessing = false;

    // ... (Le JavaScript du fichier scanner.php original est placé ici) ...
    // Seules les URLs des fetch sont modifiées.

    function processScan(scannedText) {
        if (isProcessing) return;
        isProcessing = true;
        // ... (Logique UI de chargement) ...

        fetch('<?= route("scanner_handler") ?>', { // URL MISE À JOUR
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json', 
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken // Jeton CSRF ajouté
            },
            body: JSON.stringify({ nin: scannedText.trim() })
        })
        .then(response => response.json())
        .then(data => {
            // ... (logique d'affichage du résultat, identique à l'original) ...
             if (data.status === 'success') {
                 document.getElementById('success-sound').play();
                 updateSideFeeds();
             } else {
                 document.getElementById('error-sound').play();
             }
             resultContainer.innerHTML = `<div class="text-center"><i class="bi bi-${data.status === 'success' ? 'check-circle-fill text-success' : 'x-octagon-fill text-danger'}" style="font-size: 6rem;"></i><h3 class="mt-3">${data.message}</h3>${data.employeeName ? `<p class="fs-4">${data.employeeName}</p>` : ''}</div>`;
        })
        .catch(error => {
            console.error("Fetch error:", error);
            document.getElementById('error-sound').play();
        })
        .finally(() => {
            setTimeout(() => { isProcessing = false; initializeScanner(); }, 4000);
        });
    }

    async function updateSideFeeds() {
        try {
            const response = await fetch('<?= route("scanner_feed") ?>'); // URL MISE À JOUR
            const data = await response.json();
            
            const createCard = emp => `<div class="employee-card"><img src="/${emp.photo_path || 'assets/images/default-avatar.png'}"><div class="d-flex flex-column"><strong>${emp.first_name} ${emp.last_name}</strong></div><span class="badge text-bg-light">${emp.scan_time}</span></div>`;

            document.getElementById('check-in-feed').innerHTML = data.last_check_ins.map(createCard).join('') || '<p class="text-center text-muted">Aucune entrée récente.</p>';
            document.getElementById('check-out-feed').innerHTML = data.last_check_outs.map(createCard).join('') || '<p class="text-center text-muted">Aucune sortie récente.</p>';
        } catch (error) {
            console.error('Could not update feeds:', error);
        }
    }
    
    // Le reste du code JS (updateClock, initializeScanner, event listeners) reste identique.
    // Il faut s'assurer que la fonction `initializeScanner` existe et gère les modes 'keyboard' et 'camera'
    // comme dans votre fichier original.
    function initializeScanner() {
        if(isProcessing) return;
        if (SCAN_MODE === 'keyboard') {
             resultContainer.innerHTML = `<i class="bi bi-upc-scan scan-prompt-icon"></i><h3>Veuillez scanner un badge...</h3>`;
             document.getElementById('nin-input').focus();
        } else {
            // Logique du mode caméra...
        }
    }
    
    document.getElementById('scan-form').addEventListener('submit', e => {
        e.preventDefault();
        processScan(document.getElementById('nin-input').value);
        document.getElementById('nin-input').value = '';
    });

    setInterval(() => document.getElementById('clock').textContent = new Date().toLocaleTimeString('fr-FR'), 1000);
    document.getElementById('current-date').textContent = new Date().toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    updateSideFeeds();
    setInterval(updateSideFeeds, 10000);
    initializeScanner();
});
</script>
<?php endif; ?>

</body>
</html>