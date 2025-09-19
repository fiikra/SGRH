<?php
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

// The session is already started by the router (index.php), so the block for session handling has been removed.

redirectIfNotHR();

if (!isset($_GET['nin'])) {
    header("Location: " . route('employees_list'));
    exit();
}

$nin = sanitize($_GET['nin']);

// Vérifier si l'employé existe
$stmt = $db->prepare("SELECT * FROM employees WHERE nin = ?");
$stmt->execute([$nin]);
$employee = $stmt->fetch();

if (!$employee) {
    $_SESSION['error'] = "Employé non trouvé";
    header("Location: " . route('employees_list'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Supprimer les documents associés
        $documents = $db->prepare("SELECT file_path FROM employee_documents WHERE employee_nin = ?");
        $documents->execute([$nin]);
        
        while ($doc = $documents->fetch()) {
            @unlink(__DIR__ . '/../../' . $doc['file_path']);
        }
        
        // Supprimer les enregistrements liés
        $db->prepare("DELETE FROM employee_documents WHERE employee_nin = ?")->execute([$nin]);
        $db->prepare("DELETE FROM leave_requests WHERE employee_nin = ?")->execute([$nin]);
        $db->prepare("DELETE FROM certificates WHERE employee_nin = ?")->execute([$nin]);
        
        // Supprimer la photo si elle existe
        if (!empty($employee['photo_path'])) {
            @unlink(__DIR__ . '/../../' . $employee['photo_path']);
        }
        
        // Supprimer l'employé
        $db->prepare("DELETE FROM employees WHERE nin = ?")->execute([$nin]);
        
        $db->commit();
        
        $_SESSION['success'] = "Employé supprimé avec succès";
        header("Location: " . route('employees_list'));
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Erreur lors de la suppression: " . $e->getMessage();
        header("Location: " . route('employees_view', ['nin' => $nin]));
        exit();
    }
}

$pageTitle = "Supprimer Employé";
include '../../includes/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-body">
            <h2 class="card-title">Confirmer la suppression</h2>
            <p>Êtes-vous sûr de vouloir supprimer définitivement l'employé suivant ?</p>
            
            <div class="alert alert-danger">
                <strong><?= htmlspecialchars($employee['first_name']) ?> <?= htmlspecialchars($employee['last_name']) ?></strong><br>
                NIN: <?= htmlspecialchars($employee['nin']) ?><br>
                Poste: <?= htmlspecialchars($employee['position']) ?>
            </div>
            
            <p class="text-danger">Cette action est irréversible et supprimera également tous les documents et historiques associés.</p>
            
            <form action="<?= route('employees_delete', ['nin' => $nin]) ?>" method="post">
                <?php csrf_input(); // ✅ Correct: Just call the function here ?>
            <div class="d-flex justify-content-end gap-2">
                    <a href="<?= route('employees_view', ['nin' => $nin]) ?>" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Annuler
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Confirmer la suppression
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>