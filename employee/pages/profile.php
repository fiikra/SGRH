<?php
// --- Security Headers: Set before any output ---
header('X-Frame-Options: DENY'); // Prevent clickjacking
header('X-Content-Type-Options: nosniff'); // Prevent MIME sniffing
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net;");
header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload'); // Only works over HTTPS
/*
// --- Force HTTPS if not already (optional, best to do in server config) ---
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $httpsUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $httpsUrl", true, 301);
    exit();
}
*/
// --- Harden Session Handling ---
session_set_cookie_params([
    'lifetime' => 3600, // 1 hour
    'path' => '/',
    'domain' => '', // Set to your production domain if needed
    'secure' => true,   // Only send cookie over HTTPS
    'httponly' => true, // JavaScript can't access
    'samesite' => 'Strict' // CSRF protection
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
session_regenerate_id(true);
// --- Generic error handler (don't leak errors to users in production) ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// --- Prevent session fixation ---
require_once  "../config/database.php";
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

redirectIfNotLoggedIn();

// Récupérer les informations de l'employé
$stmt = $db->prepare("SELECT e.* FROM employees e 
                     JOIN users u ON e.user_id = u.id 
                     WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

#if ($employee) {
    #$_SESSION['error'] = "Profil employé non trouvé";
   # header("Location: /auth/logout.php");
   # exit();
#}

$pageTitle = "Mon Profil";
 include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php'; 
?>

<div class="container">
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <?php if (!empty($employee['photo_path'])): ?>
                        <img src="/<?= $employee['photo_path'] ?>" class="rounded-circle mb-3" width="200" height="200" alt="Photo">
                    <?php else: ?>
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 200px; height: 200px; margin: 0 auto;">
                            <i class="bi bi-person" style="font-size: 5rem;"></i>
                        </div>
                    <?php endif; ?>
                    
                    <h3><?= $employee['first_name'] ?> <?= $employee['last_name'] ?></h3>
                    <h5 class="text-muted"><?= $employee['position'] ?></h5>
                    <p class="text-muted"><?= $employee['department'] ?></p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Coordonnées</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <i class="bi bi-envelope"></i> <?= $employee['email'] ?>
                        </li>
                        <li class="list-group-item">
                            <i class="bi bi-telephone"></i> <?= $employee['phone'] ?>
                        </li>
                        <li class="list-group-item">
                            <i class="bi bi-geo-alt"></i> <?= $employee['address'] ?>, <?= $employee['postal_code'] ?> <?= $employee['city'] ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#info">Informations</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#professional">Professionnel</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#career">Carrière & Rémunération</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="info">
                            <h5>Informations Personnelles</h5>
                            <dl class="row">
                                <dt class="col-sm-4">NIN</dt>
                                <dd class="col-sm-8"><?= $employee['nin'] ?></dd>
                                
                                <dt class="col-sm-4">NSS</dt>
                                <dd class="col-sm-8"><?= $employee['nss'] ?></dd>
                                
                                <dt class="col-sm-4">Date de Naissance</dt>
                                <dd class="col-sm-8"><?= formatDate($employee['birth_date']) ?> (<?= $employee['birth_place'] ?>)</dd>
                                
                                <dt class="col-sm-4">Situation Familiale</dt>
                                <dd class="col-sm-8"><?= 
                                    $employee['marital_status'] === 'single' ? 'Célibataire' : 
                                    ($employee['marital_status'] === 'married' ? 'Marié(e)' : 
                                    ($employee['marital_status'] === 'divorced' ? 'Divorcé(e)' : 'Veuf/Veuve'))
                                ?></dd>
                                
                                <dt class="col-sm-4">Personnes à charge</dt>
                                <dd class="col-sm-8"><?= $employee['dependents'] ?></dd>
                            </dl>
                        </div>
                        
                        <div class="tab-pane fade" id="professional">
                            <h5>Informations Professionnelles</h5>
                            <dl class="row">
                                <dt class="col-sm-4">Date d'Embauche</dt>
                                <dd class="col-sm-8"><?= formatDate($employee['hire_date']) ?></dd>
                                
                                <dt class="col-sm-4">Type de Contrat</dt>
                                <dd class="col-sm-8"><?= 
                                    $employee['contract_type'] === 'cdi' ? 'CDI' : 
                                    ($employee['contract_type'] === 'cdd' ? 'CDD' : 
                                    ($employee['contract_type'] === 'stage' ? 'Stage' : 'Intérim'))
                                ?></dd>
                                
                                <dt class="col-sm-4">Salaire</dt>
                                <dd class="col-sm-8"><?= number_format($employee['salary'], 2, ',', ' ') ?> MAD</dd>
                                
                                <dt class="col-sm-4">Statut</dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-<?= 
                                        $employee['status'] === 'active' ? 'success' : 
                                        ($employee['status'] === 'inactive' ? 'secondary' : 
                                        ($employee['status'] === 'suspended' ? 'warning' : 'danger'))
                                    ?>">
                                        <?= ucfirst($employee['status']) ?>
                                    </span>
                                </dd>
                            </dl>
                            
                            <?php if (!empty($employee['bank_name'])): ?>
                                <h5 class="mt-4">Coordonnées Bancaires</h5>
                                <dl class="row">
                                    <dt class="col-sm-4">Banque</dt>
                                    <dd class="col-sm-8"><?= $employee['bank_name'] ?></dd>
                                    
                                    <dt class="col-sm-4">Numéro de Compte</dt>
                                    <dd class="col-sm-8"><?= $employee['bank_account'] ?></dd>
                                </dl>
                            <?php endif; ?>
                            
                            <?php if (!empty($employee['emergency_contact'])): ?>
                                <h5 class="mt-4">Contact d'Urgence</h5>
                                <p>
                                    <?= $employee['emergency_contact'] ?> - <?= $employee['emergency_phone'] ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Onglet Carrière & Rémunération -->
                        <div class="tab-pane fade" id="career" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4>Évolution de Carrière & Rémunération</h4>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#promotionModal">
                                    <i class="bi bi-graph-up-arrow"></i> Enregistrer une Promotion/Augmentation
                                </button>
                            </div>

                            <h5>Décisions RH (Promotions / Augmentations)</h5>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Référence</th>
                                        <th>Date</th>
                                        <th>Type décision</th>
                                        <th>Nouveau poste</th>
                                        <th>Nouveau salaire</th>
                                        <th>PDF</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($career_decisions as $decision): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($decision['reference_number']) ?></td>
                                        <td><?= formatDate($decision['issue_date']) ?></td>
                                        <td>
                                            <?php
                                                if ($decision['decision_type'] === 'promotion') echo "Promotion sans augmentation";
                                                elseif ($decision['decision_type'] === 'promotion_salary') echo "Promotion avec augmentation";
                                                elseif ($decision['decision_type'] === 'salary_only') echo "Augmentation seule";
                                                else echo htmlspecialchars($decision['decision_type']);
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($decision['new_position']) ?></td>
                                        <td><?= $decision['new_salary'] ? number_format($decision['new_salary'],2,',',' ').' DA' : '' ?></td>
                                        <td>
                                            <?php if (!empty($decision['generated_pdf_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $decision['generated_pdf_path'])): ?>
                                                <a href="<?= htmlspecialchars($decision['generated_pdf_path']) ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-file-earmark-pdf"></i> Voir PDF
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <hr class="my-4">

                            <h5>Historique des Postes</h5>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Poste</th>
                                        <th>Département</th>
                                        <th>Date de Début</th>
                                        <th>Date de Fin</th>
                                        <th>Motif</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($position_history as $pos): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($pos['position_title']) ?></td>
                                        <td><?= htmlspecialchars($pos['department']) ?></td>
                                        <td><?= formatDate($pos['start_date']) ?></td>
                                        <td><?= $pos['end_date'] ? formatDate($pos['end_date']) : '<span class="badge bg-success">Actuel</span>' ?></td>
                                        <td><?= htmlspecialchars($pos['change_reason']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                        <td><?= formatDate($pos['start_date']) ?></td>
                                        <td><?= $pos['end_date'] ? formatDate($pos['end_date']) : '<span class="badge bg-success">Actuel</span>' ?></td>
                                        <td><?= htmlspecialchars($pos['change_reason']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <hr class="my-4">

                            <h5>Historique des Salaires</h5>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Salaire Brut</th>
                                        <th>Date d'effet</th>
                                        <th>Type de changement</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($salary_history as $sal): ?>
                                    <tr>
                                        <td><?= number_format($sal['gross_salary'], 2, ',', ' ') ?> DZD</td>
                                        <td><?= formatDate($sal['effective_date']) ?></td>
                                        <td><?= htmlspecialchars($sal['change_type']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>