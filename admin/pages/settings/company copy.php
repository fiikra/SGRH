<?php
// =========================================================================
// == BOOTSTRAP & SECURITY
// =========================================================================
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotHR();
// --- Production Error Handling ---
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// =========================================================================
// == DATA FETCHING & INITIALIZATION
// =========================================================================

// Fetch company settings (singleton row id=1)
$company = $db->query("SELECT * FROM company_settings WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch organizational data for the 'Structure' tab
$departements = $db->query("SELECT * FROM departements ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
$postes = $db->query("SELECT p.*, d.nom AS departement_nom FROM postes p LEFT JOIN departements d ON p.departement_id = d.id ORDER BY d.nom, p.nom")->fetchAll(PDO::FETCH_ASSOC);

// --- Define default values and fixed lists ---
$default_maternite_days = 98;
$paie_modes = [
    22 => "22 (5 jours/semaine)",
    26 => "26 (6 jours/semaine)",
    30 => "30 (paie au 30ème)",
];

// Initialize variables from DB or with defaults
$selectedWeekendDays = isset($company['weekend_days']) && !empty($company['weekend_days']) ? explode(',', $company['weekend_days']) : ['5', '6']; // Default Fri, Sat
$joursFeries = parse_json_field($company['jours_feries'] ?? '');
$paie_mode = $company['paie_mode'] ?? 26;
$maternite_leave_days = $company['maternite_leave_days'] ?? $default_maternite_days;


// =========================================================================
// == POST REQUEST HANDLER
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
            throw new Exception("Invalid CSRF token.");
        }

        // Handle file uploads
        $logoPath = $company['logo_path'] ?? null;
        if (!empty($_FILES['logo']['name'])) {
            $logoPath = uploadFile($_FILES['logo'], 'logo');
        }

        $signaturePath = $company['signature_path'] ?? null;
        if (!empty($_FILES['signature']['name'])) {
            $signaturePath = uploadFile($_FILES['signature'], 'signature');
        }

        // Process dynamic fields (weekend days, holidays)
        $weekendDays = isset($_POST['weekend_days']) && is_array($_POST['weekend_days']) ? implode(',', $_POST['weekend_days']) : '';
        $joursFeriesJson = process_dynamic_rows(
            $_POST['jours_feries_jour'] ?? [], // Array of 'jour' values
            $_POST['jours_feries_mois'] ?? [], // Array of 'mois' values
            $_POST['jours_feries_label'] ?? [], // Array of 'label' values
            ['jour', 'mois', 'label'] // Keys for the associative array
        );

        /**
         * Helper function to process dynamic rows from POST data into a JSON string.
         * @param array $dataArrays An array of arrays, each corresponding to a field (e.g., $_POST['field_name']).
         * @param array $keys An array of strings, representing the keys for the associative array for each row.
         * @return string JSON encoded string of the processed data.
         */
   

        // Prepare parameters for DB query
        $params = [
            // General Info
            'company_name' => sanitize($_POST['company_name']),
            'legal_form' => sanitize($_POST['legal_form']),
            'address' => sanitize($_POST['address']),
            'city' => sanitize($_POST['city']),
            'postal_code' => sanitize($_POST['postal_code']),
            'phone' => sanitize($_POST['phone']),
            'email' => sanitize($_POST['email']),
            'secteur_activite' => sanitize($_POST['secteur_activite'] ?? null),
            // Identifiers & Logos
            'tax_id' => sanitize($_POST['tax_id'] ?? null),
            'trade_register' => sanitize($_POST['trade_register'] ?? null),
            'article_imposition' => sanitize($_POST['article_imposition'] ?? null),
            'cnas_code' => sanitize($_POST['cnas_code'] ?? null),
            'casnos_code' => sanitize($_POST['casnos_code'] ?? null),
            'logo_path' => $logoPath,
            'signature_path' => $signaturePath,
            // HR & Payroll Settings
            'leave_policy' => sanitize($_POST['leave_policy'] ?? null),
            'work_hours_per_week' => intval($_POST['work_hours_per_week'] ?? 40),
            'maternite_leave_days' => intval($_POST['maternite_leave_days'] ?? $default_maternite_days),
            'min_salary' => filter_var($_POST['min_salary'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'paie_mode' => intval($_POST['paie_mode']),
            'weekend_days' => $weekendDays,
            'jours_feries' => $joursFeriesJson,
            // System Settings
            'exercice_start' => sanitize($_POST['exercice_start'] ?? null),
            'langue' => sanitize($_POST['langue'] ?? 'fr'),
            'devise' => sanitize($_POST['devise'] ?? 'DZD'),
            'timezone' => sanitize($_POST['timezone'] ?? 'Africa/Algiers'),
            'doc_reference_format' => sanitize($_POST['doc_reference_format'] ?? 'DOC-{{YYYY}}-{{NNNN}}'),
        ];
        
        // Use an UPSERT-like logic for the settings table
        if (!empty($company)) {
            $sql = "UPDATE company_settings SET company_name=:company_name, legal_form=:legal_form, address=:address, city=:city, postal_code=:postal_code, phone=:phone, email=:email, secteur_activite=:secteur_activite, tax_id=:tax_id, trade_register=:trade_register, article_imposition=:article_imposition, cnas_code=:cnas_code, casnos_code=:casnos_code, logo_path=:logo_path, signature_path=:signature_path, leave_policy=:leave_policy, work_hours_per_week=:work_hours_per_week, maternite_leave_days=:maternite_leave_days, min_salary=:min_salary, paie_mode=:paie_mode, weekend_days=:weekend_days, jours_feries=:jours_feries, exercice_start=:exercice_start, langue=:langue, devise=:devise, timezone=:timezone, doc_reference_format=:doc_reference_format WHERE id=1";
            $stmt = $db->prepare($sql);
        } else {
            $params['id'] = 1; // Set ID for insert
            $columns = implode(', ', array_keys($params));
            $placeholders = ':' . implode(', :', array_keys($params));
            $sql = "INSERT INTO company_settings (id, $columns) VALUES (:id, $placeholders)";
            $stmt = $db->prepare($sql);
        }

        $stmt->execute($params);

        flash('success', "Les paramètres de l'entreprise ont été mis à jour avec succès.");
        header("Location: " . route('settings_company'));
        exit();
    } catch (Exception $e) {
        error_log("Company Settings Error: " . $e->getMessage());
        flash('error', "Une erreur est survenue lors de la mise à jour : " . $e->getMessage());
    }
}

// =========================================================================
// == PAGE RENDERING
// =========================================================================
$pageTitle = "Paramètres de l'Entreprise";
include __DIR__ . '../../../../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0"><i class="bi bi-buildings-fill me-2"></i><?= htmlspecialchars($pageTitle) ?></h1>
    </div>

    <?php display_flash_messages(); ?>

    <form action="<?= route('settings_company') ?>" method="post" enctype="multipart/form-data" id="companySettingsForm">
        <?php csrf_input(); ?>

        <div class="card shadow-sm">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-pane" type="button" role="tab" aria-controls="general-pane" aria-selected="true"><i class="bi bi-info-circle me-1"></i> Informations Générales</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="hr-payroll-tab" data-bs-toggle="tab" data-bs-target="#hr-payroll-pane" type="button" role="tab" aria-controls="hr-payroll-pane" aria-selected="false"><i class="bi bi-cash-coin me-1"></i> RH & Paie</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="identifiers-tab" data-bs-toggle="tab" data-bs-target="#identifiers-pane" type="button" role="tab" aria-controls="identifiers-pane" aria-selected="false"><i class="bi bi-shield-check me-1"></i> Identifiants & Logos</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system-pane" type="button" role="tab" aria-controls="system-pane" aria-selected="false"><i class="bi bi-gear me-1"></i> Système</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="structure-tab" data-bs-toggle="tab" data-bs-target="#structure-pane" type="button" role="tab" aria-controls="structure-pane" aria-selected="false"><i class="bi bi-diagram-3 me-1"></i> Structure</button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content" id="settingsTabsContent">

                    <div class="tab-pane fade show active" id="general-pane" role="tabpanel" aria-labelledby="general-tab" tabindex="0">
                        <h5 class="card-title mb-4">Coordonnées de l'entreprise</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="company_name" class="form-label">Nom de l'Entreprise*</label>
                                <input type="text" id="company_name" name="company_name" class="form-control" value="<?= htmlspecialchars($company['company_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="legal_form" class="form-label">Forme Juridique*</label>
                                <input type="text" id="legal_form" name="legal_form" class="form-control" value="<?= htmlspecialchars($company['legal_form'] ?? '') ?>" required>
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label">Adresse*</label>
                                <textarea id="address" name="address" class="form-control" rows="2" required><?= htmlspecialchars($company['address'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label for="city" class="form-label">Ville*</label>
                                <input type="text" id="city" name="city" class="form-control" value="<?= htmlspecialchars($company['city'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="postal_code" class="form-label">Code Postal*</label>
                                <input type="text" id="postal_code" name="postal_code" class="form-control" value="<?= htmlspecialchars($company['postal_code'] ?? '') ?>" required>
                            </div>
                             <div class="col-md-6">
                                <label for="phone" class="form-label">Téléphone*</label>
                                <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($company['phone'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email*</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($company['email'] ?? '') ?>" required>
                            </div>
                             <div class="col-12">
                                <label for="secteur_activite" class="form-label">Secteur d'activité</label>
                                <input type="text" id="secteur_activite" name="secteur_activite" class="form-control" value="<?= htmlspecialchars($company['secteur_activite'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="hr-payroll-pane" role="tabpanel" aria-labelledby="hr-payroll-tab" tabindex="0">
                        <h5 class="card-title mb-4">Paramètres RH & Paie</h5>
                         <div class="row g-3">
                             <div class="col-md-4">
                                <label for="work_hours_per_week" class="form-label">Heures de travail / Semaine</label>
                                <input type="number" id="work_hours_per_week" name="work_hours_per_week" min="1" max="60" class="form-control" value="<?= htmlspecialchars($company['work_hours_per_week'] ?? 40) ?>">
                            </div>
                             <div class="col-md-4">
                                <label for="min_salary" class="form-label">Salaire minimum de base</label>
                                <input type="number" id="min_salary" name="min_salary" min="0" step="0.01" class="form-control" value="<?= htmlspecialchars($company['min_salary'] ?? '') ?>">
                            </div>
                             <div class="col-md-4">
                                <label for="maternite_leave_days" class="form-label">Congé Maternité (jours)</label>
                                <input type="number" id="maternite_leave_days" name="maternite_leave_days" class="form-control" value="<?= htmlspecialchars($maternite_leave_days) ?>" min="0">
                            </div>

                            <div class="col-md-4">
                                <label for="paie_mode_select" class="form-label">Mode de paie (base jours)</label>
                                <select name="paie_mode" id="paie_mode_select" class="form-select" required onchange="updateWeekendSelector()">
                                    <?php foreach ($paie_modes as $val => $label) : ?>
                                        <option value="<?= $val ?>" <?= ($paie_mode == $val) ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                             <div class="col-md-8">
                                <label for="weekend_days" class="form-label">Jours de weekend*</label>
                                <select name="weekend_days[]" id="weekend_days" class="form-select" multiple required>
                                    <option value="0" <?= in_array('0', $selectedWeekendDays) ? 'selected' : '' ?>>Dimanche</option>
                                    <option value="1" <?= in_array('1', $selectedWeekendDays) ? 'selected' : '' ?>>Lundi</option>
                                    <option value="2" <?= in_array('2', $selectedWeekendDays) ? 'selected' : '' ?>>Mardi</option>
                                    <option value="3" <?= in_array('3', $selectedWeekendDays) ? 'selected' : '' ?>>Mercredi</option>
                                    <option value="4" <?= in_array('4', $selectedWeekendDays) ? 'selected' : '' ?>>Jeudi</option>
                                    <option value="5" <?= in_array('5', $selectedWeekendDays) ? 'selected' : '' ?>>Vendredi</option>
                                    <option value="6" <?= in_array('6', $selectedWeekendDays) ? 'selected' : '' ?>>Samedi</option>
                                </select>
                                 <small class="text-muted" id="weekend_days_helper">Le nombre de jours de repos dépend du mode de paie.</small>
                            </div>
                            
                            <div class="col-12">
                                <label for="leave_policy" class="form-label">Politique de Congés (Texte libre)</label>
                                <textarea id="leave_policy" name="leave_policy" class="form-control" rows="4"><?= htmlspecialchars($company['leave_policy'] ?? '') ?></textarea>
                            </div>

                             <div class="col-12">
                                <label class="form-label">Jours fériés fixes (spécifiques)</label>
                                <div id="joursFeriesContainer">
                                    <?php if (empty($joursFeries)) : ?>
                                        <div class="input-group mb-2 dynamic-row">
                                            <input type="number" name="jours_feries_jour[]" class="form-control" placeholder="Jour" min="1" max="31">
                                            <select name="jours_feries_mois[]" class="form-select">
                                                <?php for ($m = 1; $m <= 12; $m++) : ?><option value="<?= $m ?>"><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option><?php endfor; ?>
                                            </select>
                                            <input type="text" name="jours_feries_label[]" class="form-control" placeholder="Libellé">
                                            <button type="button" class="btn btn-outline-danger" onclick="removeDynamicRow(this)" style="display: none;">-</button>
                                        </div>
                                    <?php else : foreach ($joursFeries as $jf) : ?>
                                        <div class="input-group mb-2 dynamic-row">
                                            <input type="number" name="jours_feries_jour[]" class="form-control" placeholder="Jour" value="<?= htmlspecialchars($jf['jour']) ?>" min="1" max="31">
                                            <select name="jours_feries_mois[]" class="form-select">
                                                <?php for ($m = 1; $m <= 12; $m++) : ?><option value="<?= $m ?>" <?= ($jf['mois'] == $m) ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option><?php endfor; ?>
                                            </select>
                                            <input type="text" name="jours_feries_label[]" class="form-control" placeholder="Libellé" value="<?= htmlspecialchars($jf['label']) ?>">
                                            <button type="button" class="btn btn-outline-danger" onclick="removeDynamicRow(this)">-</button>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                                <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="addDynamicRow()"><i class="bi bi-plus-circle me-1"></i> Ajouter un jour férié</button>
                            </div>
                         </div>
                    </div>

                    <div class="tab-pane fade" id="identifiers-pane" role="tabpanel" aria-labelledby="identifiers-tab" tabindex="0">
                        <h5 class="card-title mb-4">Informations Légales et Fichiers</h5>
                        <div class="row g-3">
                             <div class="col-md-6"><label class="form-label">N° Identifiant Fiscal (NIF)</label><input type="text" name="tax_id" class="form-control" value="<?= htmlspecialchars($company['tax_id'] ?? '') ?>"></div>
                             <div class="col-md-6"><label class="form-label">Registre du Commerce (RC)</label><input type="text" name="trade_register" class="form-control" value="<?= htmlspecialchars($company['trade_register'] ?? '') ?>"></div>
                             <div class="col-md-6"><label class="form-label">N° Article d'Imposition</label><input type="text" name="article_imposition" class="form-control" value="<?= htmlspecialchars($company['article_imposition'] ?? '') ?>"></div>
                             <div class="col-md-6"><label class="form-label">Code CNAS</label><input type="text" name="cnas_code" class="form-control" value="<?= htmlspecialchars($company['cnas_code'] ?? '') ?>"></div>
                             <div class="col-md-6"><label class="form-label">Code CASNOS</label><input type="text" name="casnos_code" class="form-control" value="<?= htmlspecialchars($company['casnos_code'] ?? '') ?>"></div>
                            
                            <div class="col-md-6">
                                <label for="logo" class="form-label">Logo de l'entreprise</label>
                                <input type="file" id="logo" name="logo" class="form-control" accept="image/jpeg,image/png">
                                <?php if (!empty($company['logo_path'])) : ?>
                                    <div class="mt-2"><img src="/<?= htmlspecialchars(ltrim($company['logo_path'], '/')) ?>" alt="Logo Actuel" class="img-thumbnail" style="max-height: 60px;"></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="signature" class="form-label">Signature (pour documents)</label>
                                <input type="file" id="signature" name="signature" class="form-control" accept="image/jpeg,image/png">
                                <?php if (!empty($company['signature_path'])) : ?>
                                    <div class="mt-2"><img src="/<?= htmlspecialchars(ltrim($company['signature_path'], '/')) ?>" alt="Signature Actuelle" class="img-thumbnail" style="max-height: 60px; background-color: #f8f9fa;"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="system-pane" role="tabpanel" aria-labelledby="system-tab" tabindex="0">
                         <h5 class="card-title mb-4">Paramètres Système</h5>
                         <div class="row g-3">
                            <div class="col-md-4">
                                <label for="exercice_start" class="form-label">Début de l'exercice social</label>
                                <input type="date" id="exercice_start" name="exercice_start" class="form-control" value="<?= htmlspecialchars($company['exercice_start'] ?? '') ?>">
                            </div>
                             <div class="col-md-4">
                                <label for="langue" class="form-label">Langue</label>
                                <select id="langue" name="langue" class="form-select">
                                    <option value="fr" <?= (($company['langue'] ?? 'fr') === 'fr') ? 'selected' : ''; ?>>Français</option>
                                    <option value="ar" <?= ($company['langue'] ?? '') === 'ar' ? 'selected' : ''; ?>>Arabe</option>
                                </select>
                            </div>
                             <div class="col-md-4">
                                <label for="timezone" class="form-label">Fuseau Horaire</label>
                                <input type="text" id="timezone" name="timezone" class="form-control" value="<?= htmlspecialchars($company['timezone'] ?? 'Africa/Algiers') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="devise" class="form-label">Devise</label>
                                <input type="text" id="devise" name="devise" class="form-control" value="<?= htmlspecialchars($company['devise'] ?? 'DZD') ?>">
                            </div>
                             <div class="col-md-6">
                                <label for="doc_reference_format" class="form-label">Format des références</label>
                                <input type="text" id="doc_reference_format" name="doc_reference_format" class="form-control" value="<?= htmlspecialchars($company['doc_reference_format'] ?? 'DOC-{{YYYY}}-{{NNNN}}') ?>">
                                <small class="text-muted">{{YYYY}} Année, {{MM}} Mois, {{NNNN}} Numéro</small>
                            </div>
                         </div>
                    </div>
                    
                    <div class="tab-pane fade" id="structure-pane" role="tabpanel" aria-labelledby="structure-tab" tabindex="0">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Structure Organisationnelle</h5>
                             <a href="<?= route('settings_personnel_organisation') ?>" class="btn btn-primary"><i class="bi bi-pencil-square me-1"></i> Gérer la Structure</a>
                        </div>
                        <p class="text-muted">Voici un aperçu de votre structure actuelle. Utilisez le bouton ci-dessus pour ajouter ou modifier des départements et des postes.</p>
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <h6>Départements</h6>
                                <div class="table-responsive">
                                <table class="table table-sm table-hover table-bordered">
                                    <thead class="table-light"><tr><th>Nom</th><th>Description</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($departements)): ?>
                                            <tr><td colspan="2">Aucun département trouvé.</td></tr>
                                        <?php else: foreach ($departements as $d): ?>
                                            <tr><td><?= htmlspecialchars($d['nom']) ?></td><td><?= htmlspecialchars($d['description']) ?></td></tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <h6>Postes</h6>
                                <div class="table-responsive">
                                <table class="table table-sm table-hover table-bordered">
                                    <thead class="table-light"><tr><th>Nom</th><th>Département</th><th>Code</th><th>Salaire Base</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($postes)): ?>
                                            <tr><td colspan="4">Aucun poste trouvé.</td></tr>
                                        <?php else: foreach ($postes as $p): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($p['nom']) ?></td>
                                                <td><?= htmlspecialchars($p['departement_nom'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($p['code_poste']) ?></td>
                                                <td><?= number_format($p['salaire_base'], 2, ',', ' ') ?> <?= htmlspecialchars($company['devise'] ?? 'DZD') ?></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer text-end border-top-0 pt-0">
                 <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save-fill me-2"></i> Enregistrer les Paramètres
                </button>
            </div>
        </div>
    </form>
</div>
<template id="jour-ferie-template">
    <div class="input-group mb-2 dynamic-row">
        <input type="number" name="jours_feries_jour[]" class="form-control" placeholder="Jour" min="1" max="31" required>
        <select name="jours_feries_mois[]" class="form-select" required>
            <?php for ($m = 1; $m <= 12; $m++) : ?><option value="<?= $m ?>"><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option><?php endfor; ?>
        </select>
        <input type="text" name="jours_feries_label[]" class="form-control" placeholder="Libellé" required>
        <button type="button" class="btn btn-outline-danger" onclick="removeDynamicRow(this)">-</button>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // Met à jour la visibilité des boutons "Supprimer"
    function updateRemoveButtonsState() {
        const rows = document.querySelectorAll('#joursFeriesContainer .dynamic-row');
        rows.forEach(row => {
            const removeBtn = row.querySelector('.btn-outline-danger');
            if (removeBtn) {
                // Cache le bouton "Supprimer" s'il n'y a qu'une seule ligne
                removeBtn.style.display = rows.length > 1 ? 'inline-block' : 'none';
            }
        });
    }

    // Ajoute une nouvelle ligne de jour férié
    window.addDynamicRow = function() {
        const template = document.getElementById('jour-ferie-template');
        // Affiche une erreur claire si le template est manquant
        if (!template) {
            console.error("Erreur critique : Le template HTML #jour-ferie-template est introuvable.");
            alert("Erreur de configuration de la page. Impossible d'ajouter une ligne.");
            return;
        }
        const container = document.getElementById('joursFeriesContainer');
        const newRowContent = template.content.cloneNode(true);
        container.appendChild(newRowContent);
        updateRemoveButtonsState();
    }

    // Supprime une ligne de jour férié
    window.removeDynamicRow = function(button) {
        // Supprime l'élément parent .dynamic-row
        button.closest('.dynamic-row').remove();
        // Met à jour l'état des boutons restants
        updateRemoveButtonsState();
    }

    // --- Initialisation de la page ---
    // S'assure qu'il y a au moins une ligne au chargement
    if (document.querySelectorAll('#joursFeriesContainer .dynamic-row').length === 0) {
        addDynamicRow();
    } else {
        updateRemoveButtonsState();
    }
});
</script>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>