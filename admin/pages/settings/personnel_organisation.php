<?php
// --- Security Headers: Set before any output ---
/**
 * Page: Company Settings
 *
 * Manages all global settings for the company, including legal information,
 * HR policies, SMTP, and organizational structure.
 */

// =========================================================================
// == BOOTSTRAP & SECURITY
// =========================================================================
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotHR();

// Ajouter un département
if (isset($_POST['add_departement'])) {
    $nom = sanitize($_POST['departement_nom']);
    $desc = sanitize($_POST['departement_desc']);
    if ($nom) {
        $db->prepare("INSERT INTO departements (nom, description) VALUES (?, ?)")->execute([$nom, $desc]);
        $_SESSION['success'] = "Département ajouté.";
    }
    header('Location: personnel_organisation.php');
    exit;
}

// Supprimer un département (avec gestion de la suppression en cascade ou refus si des postes existent)
if (isset($_POST['delete_departement_id'])) {
    $id = intval($_POST['delete_departement_id']);
    // Vérifie s'il y a des postes liés
    $count = $db->prepare("SELECT count(*) FROM postes WHERE departement_id=?");
    $count->execute([$id]);
    if ($count->fetchColumn() > 0) {
        $_SESSION['error'] = "Impossible de supprimer le département, des postes y sont associés.";
    } else {
        $db->prepare("DELETE FROM departements WHERE id=?")->execute([$id]);
        $_SESSION['success'] = "Département supprimé.";
    }
    header('Location: personnel_organisation.php');
    exit;
}

// Ajouter un poste
if (isset($_POST['add_poste'])) {
    $nom = sanitize($_POST['poste_nom']);
    $desc = sanitize($_POST['poste_desc']);
    $missions = sanitize($_POST['poste_missions']);
    $competences = sanitize($_POST['poste_competences']);
    $departement_id = intval($_POST['poste_departement_id']);
    $hierarchie = sanitize($_POST['poste_hierarchie']);
    $code_poste = sanitize($_POST['poste_code']);
    $salaire_base = floatval($_POST['poste_salaire_base']);
    if ($nom && $departement_id) {
        $db->prepare("INSERT INTO postes (nom, description, missions, competences, departement_id, hierarchie, code_poste, salaire_base) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$nom, $desc, $missions, $competences, $departement_id, $hierarchie, $code_poste, $salaire_base]);
        $_SESSION['success'] = "Poste ajouté.";
    }
    header('Location: personnel_organisation.php');
    exit;
}

// Supprimer un poste
if (isset($_POST['delete_poste_id'])) {
    $id = intval($_POST['delete_poste_id']);
    $db->prepare("DELETE FROM postes WHERE id=?")->execute([$id]);
    $_SESSION['success'] = "Poste supprimé.";
    header('Location: personnel_organisation.php');
    exit;
}

// Récupérer les départements et les postes
$departements = $db->query("SELECT * FROM departements ORDER BY nom ASC")->fetchAll();
$postes = $db->query("SELECT p.*, d.nom AS departement_nom FROM postes p LEFT JOIN departements d ON p.departement_id=d.id ORDER BY d.nom, p.nom")->fetchAll();

$pageTitle = "Organisation RH : Départements & Postes";
include __DIR__. '../../../../includes/header.php';
?>
<div class="container">
    <h1 class="mb-4"><?= $pageTitle ?></h1>
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-5">
            <h5>Départements</h5>
            <table class="table table-bordered table-sm mb-2">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Description</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($departements as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['nom']) ?></td>
                        <td><?= htmlspecialchars($d['description']) ?></td>
                        <td>
                            <form method="post" class="d-inline" onsubmit="return confirm('Supprimer ce département ?');">
                               <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                            <input type="hidden" name="delete_departement_id" value="<?= $d['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Supprimer"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDepartementModal">
                Ajouter un département
            </button>
        </div>
        <div class="col-md-7">
            <h5>Postes (Fiches de poste)</h5>
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Département</th>
                        <th>Code</th>
                        <th>Salaire Base</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($postes as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nom']) ?></td>
                        <td><?= htmlspecialchars($p['departement_nom']) ?></td>
                        <td><?= htmlspecialchars($p['code_poste']) ?></td>
                        <td><?= number_format($p['salaire_base'],2,',',' ') ?> DA</td>
                        <td>
                            <form method="post" class="d-inline" onsubmit="return confirm('Supprimer ce poste ?');">
                               <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                            <input type="hidden" name="delete_poste_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Supprimer"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPosteModal">
                Ajouter un poste
            </button>
        </div>
    </div>
</div>

<!-- Modal Ajout Département -->
<div class="modal fade" id="addDepartementModal" tabindex="-1" aria-labelledby="addDepartementModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post">
      <?php csrf_input(); // ✅ Correct: Just call the function here ?>
    <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addDepartementModalLabel">Ajouter un département</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nom</label>
            <input type="text" class="form-control" name="departement_nom" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="departement_desc"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary" name="add_departement">Ajouter</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Modal Ajout Poste -->
<div class="modal fade" id="addPosteModal" tabindex="-1" aria-labelledby="addPosteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post">
      <?php csrf_input(); // ✅ Correct: Just call the function here ?>
    <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addPosteModalLabel">Ajouter un poste</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nom du poste</label>
            <input type="text" class="form-control" name="poste_nom" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Département</label>
            <select name="poste_departement_id" class="form-select" required>
              <option value="">--Sélectionner--</option>
              <?php foreach ($departements as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Code du poste</label>
            <input type="text" class="form-control" name="poste_code" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="poste_desc"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Missions</label>
            <textarea class="form-control" name="poste_missions"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Compétences</label>
            <textarea class="form-control" name="poste_competences"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Rattachement hiérarchique</label>
            <input type="text" class="form-control" name="poste_hierarchie">
          </div>
          <div class="mb-3">
            <label class="form-label">Salaire de base (DA)</label>
            <input type="number" class="form-control" name="poste_salaire_base" min="0" step="0.01">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary" name="add_poste">Ajouter</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php include __dir__. '../../includes/footer.php'; ?>