<?php

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}


if (!isset($_GET['nin'])) {
    header("Location: " . route('employees_list'));
    exit();
}

$nin = sanitize($_GET['nin']);

// Récupérer les informations de l'employé
$stmt = $db->prepare("SELECT * FROM employees WHERE nin = ?");
$stmt->execute([$nin]);
$employee = $stmt->fetch();

if (!$employee) {
    $_SESSION['error'] = "Employé non trouvé";
    header("Location: " . route('employees_list'));
    exit();
}

// Récupérer les documents de l'employé
$documents = $db->prepare("SELECT * FROM employee_documents WHERE employee_nin = ? ORDER BY upload_date DESC");
$documents->execute([$nin]);

// Ajouter un document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document_file'])) {
    try {
        $filePath = uploadFile($_FILES['document_file']);
        
        $stmt = $db->prepare("INSERT INTO employee_documents 
            (employee_nin, document_type, title, file_path, issue_date, expiry_date, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $nin,
            sanitize($_POST['document_type']),
            sanitize($_POST['title']),
            $filePath,
            !empty($_POST['issue_date']) ? sanitize($_POST['issue_date']) : null,
            !empty($_POST['expiry_date']) ? sanitize($_POST['expiry_date']) : null,
            sanitize($_POST['notes'] ?? null)
        ]);
        
        $_SESSION['success'] = "Document ajouté avec succès!";
        // Redirect to the current page using the route function
        header("Location: " . route('employees_documents', ['nin' => $nin]));
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
}

$pageTitle = "Documents de " . htmlspecialchars($employee['first_name']) . " " . htmlspecialchars($employee['last_name']);
include __DIR__.'../../../../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Documents de <?= htmlspecialchars($employee['first_name']) ?> <?= htmlspecialchars($employee['last_name']) ?></h1>
        <a href="<?= route('employees_list') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">Ajouter un Document</h5>
                </div>
                <div class="card-body">
                    <form action="<?= route('employees_documents', ['nin' => $nin]) ?>" method="post" enctype="multipart/form-data">
                        <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                    <div class="mb-3">
                            <label class="form-label">Type de Document</label>
                            <select name="document_type" class="form-select" required>
                                <option value="cv">CV</option>
                                <option value="diplome">Diplôme</option>
                                <option value="contrat">Contrat</option>
                                <option value="cin">CIN</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Titre</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Fichier (PDF, JPEG, PNG - Max 5MB)</label>
                            <input type="file" name="document_file" class="form-control" required accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date d'Émission</label>
                                    <input type="date" name="issue_date" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date d'Expiration</label>
                                    <input type="date" name="expiry_date" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-upload"></i> Uploader
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Documents Archivés</h5>
                </div>
                <div class="card-body">
                    <?php if ($documents->rowCount() > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Titre</th>
                                        <th>Date Upload</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($doc = $documents->fetch()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(ucfirst($doc['document_type'])) ?></td>
                                            <td><?= htmlspecialchars($doc['title']) ?></td>
                                            <td><?= formatDate($doc['upload_date'], 'd/m/Y H:i') ?></td>
                                            <td>
                                                <a href="/<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-success" title="Voir">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="#" class="btn btn-sm btn-danger" title="Supprimer" onclick="confirmDelete(<?= $doc['id'] ?>, '<?= route('employees_document_delete', ['nin' => $nin]) ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">Aucun document archivé pour cet employé.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// The function now accepts the base URL generated by the route() function
function confirmDelete(docId, baseUrl) {
    if (confirm("Êtes-vous sûr de vouloir supprimer ce document ?")) {
        // Appends the specific document ID to the base URL for the final redirect
        window.location.href = `${baseUrl}&id=${docId}`;
    }
}
</script>

<?php include __DIR__. '../../../../includes/footer.php'; ?>