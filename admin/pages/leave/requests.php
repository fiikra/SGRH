<?php
// --- Security Headers: Set before any output ---

// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();

$pageTitle = "Demandes de Congé";
include __DIR__.'../../../../includes/header.php';

// Statut de filtre
$status = isset($_GET['status']) ? sanitize($_GET['status']) : 'pending';

// Requête de base
$query = "SELECT l.*, e.first_name, e.last_name, e.nin 
          FROM leave_requests l 
          JOIN employees e ON l.employee_nin = e.nin";

// Ajouter le filtre de statut (inclut 'cancelled')
if (in_array($status, ['pending', 'approved', 'rejected', 'paused', 'cancelled'])) {
    $query .= " WHERE l.status = ?";
    $params = [$status];
} else {
    // Default to 'pending' if status is invalid
    $status = 'pending';
    $query .= " WHERE l.status = ?";
    $params = [$status];
}

$query .= " ORDER BY l.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();
?>

<div class="container">
    <h1 class="mb-4">Demandes de Congé</h1>
    
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?= $status === 'pending' ? 'active' : '' ?>" href="<?= route('leave_requests', ['status' => 'pending']) ?>">
                            En Attente
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $status === 'approved' ? 'active' : '' ?>" href="<?= route('leave_requests', ['status' => 'approved']) ?>">
                            Approuvés
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $status === 'rejected' ? 'active' : '' ?>" href="<?= route('leave_requests', ['status' => 'rejected']) ?>">
                            Rejetés
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $status === 'paused' ? 'active' : '' ?>" href="<?= route('leave_requests', ['status' => 'paused']) ?>">
                            Suspendus
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $status === 'cancelled' ? 'active' : '' ?>" href="<?= route('leave_requests', ['status' => 'cancelled']) ?>">
                            Annulés
                        </a>
                    </li>
                </ul>
                
                <a href="<?= route('leave_report') ?>" class="btn btn-secondary">
                    <i class="bi bi-file-earmark-text"></i> Rapport
                </a>
            </div>
        </div>
    </div>
    
    <?php if (count($requests) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Employé</th>
                        <th>Type</th>
                        <th>Période</th>
                        <th>Durée</th>
                        <th>Statut</th>
                        <th>Date Demande</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?= htmlspecialchars($request['first_name']) ?> <?= htmlspecialchars($request['last_name']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($request['leave_type'])) ?></td>
                            <td>
                                <?= formatDate($request['start_date']) ?> - 
                                <?= formatDate($request['end_date']) ?>
                            </td>
                            <td><?= htmlspecialchars($request['days_requested']) ?> jour(s)</td>
                            <td>
                                <?php
                                    // Logic for status badge class and text
                                    $badgeClass = 'warning'; // Default for pending
                                    $badgeText = 'En Attente'; // Default for pending

                                    if ($request['status'] === 'approved') {
                                        $badgeClass = 'success';
                                        $badgeText = 'Approuvé';
                                    } elseif ($request['status'] === 'rejected') {
                                        $badgeClass = 'danger';
                                        $badgeText = 'Rejeté';
                                    } elseif ($request['status'] === 'paused') {
                                        $badgeClass = 'info';
                                        $badgeText = 'Suspendu';
                                    } elseif ($request['status'] === 'cancelled') {
                                        $badgeClass = 'secondary';
                                        $badgeText = 'Annulé';
                                    }
                                ?>
                                <span class="badge bg-<?= $badgeClass ?>">
                                    <?= htmlspecialchars($badgeText) ?>
                                </span>
                            </td>
                            <td><?= formatDate($request['created_at'], 'd/m/Y H:i') ?></td>
                            <td>
                                <a href="<?= route('leave_view', ['id' => $request['id']]) ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye"></i> Voir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">Aucune demande de congé trouvée pour le statut "<?= htmlspecialchars($status) ?>".</div>
    <?php endif; ?>
</div>

<?php include __DIR__. '../../../../includes/footer.php'; ?>