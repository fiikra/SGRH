<?php
// =========================================================================
// == BOOTSTRAP & SECURITY
// =========================================================================
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();

// =========================================================================
// == DATA FETCHING & INITIALIZATION
// =========================================================================
$company = $db->query("SELECT * FROM company_settings WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$departements = $db->query("SELECT * FROM departements ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
$postes = $db->query("SELECT p.*, d.nom AS departement_nom FROM postes p LEFT JOIN departements d ON p.departement_id = d.id ORDER BY d.nom, p.nom")->fetchAll(PDO::FETCH_ASSOC);

// --- Define default values ---
$default_maternite_days = 98;
$paie_modes = [22 => "22 (5 jours/semaine)", 26 => "26 (6 jours/semaine)", 30 => "30 (paie au 30ème)"];
$selectedWeekendDays = isset($company['weekend_days']) && !empty($company['weekend_days']) ? explode(',', $company['weekend_days']) : ['5', '6'];
$joursFeries = parse_json_field($company['jours_feries'] ?? '');
$paie_mode = $company['paie_mode'] ?? 26;
$maternite_leave_days = $company['maternite_leave_days'] ?? $default_maternite_days;

// =========================================================================
// == POST REQUEST HANDLER
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
            throw new Exception("Invalid CSRF token.");
        }

        $logoPath = $company['logo_path'] ?? null;
        if (!empty($_FILES['logo']['name'])) {
            $logoPath = uploadFile($_FILES['logo'], 'logo');
        }

        $signaturePath = $company['signature_path'] ?? null;
        if (!empty($_FILES['signature']['name'])) {
            $signaturePath = uploadFile($_FILES['signature'], 'signature');
        }

        $weekendDays = isset($_POST['weekend_days']) && is_array($_POST['weekend_days']) ? implode(',', $_POST['weekend_days']) : '';
        $joursFeriesJson = process_dynamic_rows($_POST['jours_feries_jour'] ?? [], $_POST['jours_feries_mois'] ?? [], $_POST['jours_feries_label'] ?? [], ['jour', 'mois', 'label']);

        // Prepare parameters for DB query
        $params = [
            'company_name' => sanitize($_POST['company_name']),
            'legal_form' => sanitize($_POST['legal_form']),
            'address' => sanitize($_POST['address']),
            'city' => sanitize($_POST['city']),
            'postal_code' => sanitize($_POST['postal_code']),
            'phone' => sanitize($_POST['phone']),
            'email' => sanitize($_POST['email']),
            'secteur_activite' => sanitize($_POST['secteur_activite'] ?? null),
            'tax_id' => sanitize($_POST['tax_id'] ?? null),
            'trade_register' => sanitize($_POST['trade_register'] ?? null),
            'article_imposition' => sanitize($_POST['article_imposition'] ?? null),
            'cnas_code' => sanitize($_POST['cnas_code'] ?? null),
            'casnos_code' => sanitize($_POST['casnos_code'] ?? null),
            'logo_path' => $logoPath,
            'signature_path' => $signaturePath,
            'leave_policy' => sanitize($_POST['leave_policy'] ?? null),
            'work_hours_per_week' => intval($_POST['work_hours_per_week'] ?? 40),
            'maternite_leave_days' => intval($_POST['maternite_leave_days'] ?? $default_maternite_days),
            'min_salary' => filter_var($_POST['min_salary'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'paie_mode' => intval($_POST['paie_mode']),
            'weekend_days' => $weekendDays,
            'jours_feries' => $joursFeriesJson,
            'lateness_grace_period' => intval($_POST['lateness_grace_period'] ?? 15), // NEW
            'attendance_method' => sanitize($_POST['attendance_method'] ?? 'qrcode'), // NEW
            'scan_mode' => sanitize($_POST['scan_mode'] ?? 'keyboard'), // NEW
            'exercice_start' => sanitize($_POST['exercice_start'] ?? null),
            'langue' => sanitize($_POST['langue'] ?? 'fr'),
            'devise' => sanitize($_POST['devise'] ?? 'DZD'),
            'timezone' => sanitize($_POST['timezone'] ?? 'Africa/Algiers'),
            'doc_reference_format' => sanitize($_POST['doc_reference_format'] ?? 'DOC-{{YYYY}}-{{NNNN}}'),
        ];
        
        // Refactored UPSERT logic
        $columns = array_keys($params);
        if (!empty($company)) {
            $set_clause = implode(', ', array_map(fn($col) => "$col = :$col", $columns));
            $sql = "UPDATE company_settings SET $set_clause WHERE id=1";
            $stmt = $db->prepare($sql);
        } else {
            $params['id'] = 1; // Set ID for the first insert
            $columns_with_id = implode(', ', array_keys($params));
            $placeholders = ':' . implode(', :', array_keys($params));
            $sql = "INSERT INTO company_settings ($columns_with_id) VALUES ($placeholders)";
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
                    <li class="nav-item" role="presentation"><button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-pane" type="button" role="tab"><i class="bi bi-info-circle me-1"></i> Général</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" id="hr-payroll-tab" data-bs-toggle="tab" data-bs-target="#hr-payroll-pane" type="button" role="tab"><i class="bi bi-cash-coin me-1"></i> RH & Paie</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" id="identifiers-tab" data-bs-toggle="tab" data-bs-target="#identifiers-pane" type="button" role="tab"><i class="bi bi-shield-check me-1"></i> Identifiants</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system-pane" type="button" role="tab"><i class="bi bi-gear me-1"></i> Système</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" id="structure-tab" data-bs-toggle="tab" data-bs-target="#structure-pane" type="button" role="tab"><i class="bi bi-diagram-3 me-1"></i> Structure</button></li>
                </ul>
            </div>

            <div class="card-body p-4">
                <div class="tab-content" id="settingsTabsContent">
                    <div class="tab-pane fade show active" id="general-pane" role="tabpanel">
                         <h5 class="card-title mb-4">Coordonnées de l'entreprise</h5>
                        <div class="row g-3">
                            <div class="col-md-6"><label for="company_name" class="form-label">Nom de l'Entreprise*</label><input type="text" id="company_name" name="company_name" class="form-control" value="<?= htmlspecialchars($company['company_name'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label for="legal_form" class="form-label">Forme Juridique*</label><input type="text" id="legal_form" name="legal_form" class="form-control" value="<?= htmlspecialchars($company['legal_form'] ?? '') ?>" required></div>
                            <div class="col-12"><label for="address" class="form-label">Adresse*</label><textarea id="address" name="address" class="form-control" rows="2" required><?= htmlspecialchars($company['address'] ?? '') ?></textarea></div>
                            <div class="col-md-6"><label for="city" class="form-label">Ville*</label><input type="text" id="city" name="city" class="form-control" value="<?= htmlspecialchars($company['city'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label for="postal_code" class="form-label">Code Postal*</label><input type="text" id="postal_code" name="postal_code" class="form-control" value="<?= htmlspecialchars($company['postal_code'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label for="phone" class="form-label">Téléphone*</label><input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($company['phone'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label for="email" class="form-label">Email*</label><input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($company['email'] ?? '') ?>" required></div>
                            <div class="col-12"><label for="secteur_activite" class="form-label">Secteur d'activité</label><input type="text" id="secteur_activite" name="secteur_activite" class="form-control" value="<?= htmlspecialchars($company['secteur_activite'] ?? '') ?>"></div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="hr-payroll-pane" role="tabpanel">
                        <h5 class="card-title mb-4">Paramètres RH & Paie</h5>
                        <div class="row g-3">
                            <div class="col-md-3"><label for="work_hours_per_week" class="form-label">Heures / Semaine</label><input type="number" id="work_hours_per_week" name="work_hours_per_week" min="1" max="60" class="form-control" value="<?= htmlspecialchars($company['work_hours_per_week'] ?? 40) ?>"></div>
                            <div class="col-md-3"><label for="min_salary" class="form-label">Salaire Min. de Base</label><input type="number" id="min_salary" name="min_salary" min="0" step="0.01" class="form-control" value="<?= htmlspecialchars($company['min_salary'] ?? '') ?>"></div>
                            <div class="col-md-3"><label for="maternite_leave_days" class="form-label">Congé Maternité (jours)</label><input type="number" id="maternite_leave_days" name="maternite_leave_days" class="form-control" value="<?= htmlspecialchars($maternite_leave_days) ?>" min="0"></div>
                            <div class="col-md-3"><label for="lateness_grace_period" class="form-label">Marge Retard (minutes)</label><input type="number" id="lateness_grace_period" name="lateness_grace_period" class="form-control" value="<?= htmlspecialchars($company['lateness_grace_period'] ?? 15) ?>" min="0" title="Intervalle de grâce pour le pointage du matin."></div>
                            <div class="col-md-4"><label for="paie_mode_select" class="form-label">Mode de paie</label><select name="paie_mode" id="paie_mode_select" class="form-select" required><?php foreach ($paie_modes as $val => $label) : ?><option value="<?= $val ?>" <?= ($paie_mode == $val) ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-8"><label for="weekend_days" class="form-label">Jours de weekend*</label><select name="weekend_days[]" id="weekend_days" class="form-select" multiple required><option value="0" <?= in_array('0', $selectedWeekendDays) ? 'selected' : '' ?>>Dimanche</option><option value="1" <?= in_array('1', $selectedWeekendDays) ? 'selected' : '' ?>>Lundi</option><option value="2" <?= in_array('2', $selectedWeekendDays) ? 'selected' : '' ?>>Mardi</option><option value="3" <?= in_array('3', $selectedWeekendDays) ? 'selected' : '' ?>>Mercredi</option><option value="4" <?= in_array('4', $selectedWeekendDays) ? 'selected' : '' ?>>Jeudi</option><option value="5" <?= in_array('5', $selectedWeekendDays) ? 'selected' : '' ?>>Vendredi</option><option value="6" <?= in_array('6', $selectedWeekendDays) ? 'selected' : '' ?>>Samedi</option></select></div>
                            <div class="col-12"><label class="form-label">Jours fériés fixes</label><div id="joursFeriesContainer"></div><button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="addDynamicRow()"><i class="bi bi-plus-circle me-1"></i> Ajouter</button></div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="identifiers-pane" role="tabpanel">
                        <h5 class="card-title mb-4">Informations Légales et Fichiers</h5>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">N° Identifiant Fiscal (NIF)</label><input type="text" name="tax_id" class="form-control" value="<?= htmlspecialchars($company['tax_id'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Registre du Commerce (RC)</label><input type="text" name="trade_register" class="form-control" value="<?= htmlspecialchars($company['trade_register'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">N° Article d'Imposition</label><input type="text" name="article_imposition" class="form-control" value="<?= htmlspecialchars($company['article_imposition'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Code CNAS</label><input type="text" name="cnas_code" class="form-control" value="<?= htmlspecialchars($company['cnas_code'] ?? '') ?>"></div>
                            <div class="col-md-6"><label for="logo" class="form-label">Logo de l'entreprise</label><input type="file" id="logo" name="logo" class="form-control" accept="image/jpeg,image/png"><?php if (!empty($company['logo_path'])) : ?><div class="mt-2"><img src="/<?= htmlspecialchars(ltrim($company['logo_path'], '/')) ?>" alt="Logo Actuel" class="img-thumbnail" style="max-height: 60px;"></div><?php endif; ?></div>
                            <div class="col-md-6"><label for="signature" class="form-label">Signature (pour documents)</label><input type="file" id="signature" name="signature" class="form-control" accept="image/jpeg,image/png"><?php if (!empty($company['signature_path'])) : ?><div class="mt-2"><img src="/<?= htmlspecialchars(ltrim($company['signature_path'], '/')) ?>" alt="Signature" class="img-thumbnail" style="max-height: 60px; background-color: #f8f9fa;"></div><?php endif; ?></div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="system-pane" role="tabpanel">
                        <h5 class="card-title mb-4">Paramètres de Pointage et Système</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Méthode de Pointage Principale</label>
                                <div class="form-check"><input class="form-check-input" type="radio" name="attendance_method" id="method_qrcode" value="qrcode" <?= ($company['attendance_method'] ?? 'qrcode') == 'qrcode' ? 'checked' : '' ?>><label class="form-check-label" for="method_qrcode">Pointage par QR Code</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="attendance_method" id="method_biometric" value="biometric" <?= ($company['attendance_method'] ?? '') == 'biometric' ? 'checked' : '' ?>><label class="form-check-label" for="method_biometric">Appareil Biométrique (ex: ZKTeco)</label></div>
                                <small class="text-muted">L'intégration biométrique nécessite une configuration technique spécifique.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Type de Scanner QR Code</label>
                                <div class="form-check"><input class="form-check-input" type="radio" name="scan_mode" id="scan_keyboard" value="keyboard" <?= ($company['scan_mode'] ?? 'keyboard') == 'keyboard' ? 'checked' : '' ?>><label class="form-check-label" for="scan_keyboard">Scanner Code-barres (type clavier)</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="scan_mode" id="scan_camera" value="camera" <?= ($company['scan_mode'] ?? '') == 'camera' ? 'checked' : '' ?>><label class="form-check-label" for="scan_camera">Caméra de l'appareil (ex: tablette, smartphone)</label></div>
                            </div>
                            <hr class="my-4">
                            <div class="col-md-4"><label for="exercice_start" class="form-label">Début de l'exercice social</label><input type="date" id="exercice_start" name="exercice_start" class="form-control" value="<?= htmlspecialchars($company['exercice_start'] ?? '') ?>"></div>
                            <div class="col-md-4"><label for="langue" class="form-label">Langue</label><select id="langue" name="langue" class="form-select"><option value="fr" <?= (($company['langue'] ?? 'fr') === 'fr') ? 'selected' : ''; ?>>Français</option></select></div>
                            <div class="col-md-4"><label for="timezone" class="form-label">Fuseau Horaire</label><input type="text" id="timezone" name="timezone" class="form-control" value="<?= htmlspecialchars($company['timezone'] ?? 'Africa/Algiers') ?>"></div>
                            <div class="col-md-6"><label for="devise" class="form-label">Devise</label><input type="text" id="devise" name="devise" class="form-control" value="<?= htmlspecialchars($company['devise'] ?? 'DZD') ?>"></div>
                            <div class="col-md-6"><label for="doc_reference_format" class="form-label">Format des références</label><input type="text" id="doc_reference_format" name="doc_reference_format" class="form-control" value="<?= htmlspecialchars($company['doc_reference_format'] ?? 'DOC-{{YYYY}}-{{NNNN}}') ?>"><small class="text-muted">{{YYYY}} Année, {{MM}} Mois, {{NNNN}} Numéro</small></div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="structure-pane" role="tabpanel">
                         <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Structure Organisationnelle</h5>
                             <a href="<?= route('settings_personnel_organisation') ?>" class="btn btn-primary"><i class="bi bi-pencil-square me-1"></i> Gérer la Structure</a>
                        </div>
                        <p class="text-muted">Voici un aperçu de votre structure actuelle. Utilisez le bouton ci-dessus pour ajouter ou modifier des départements et des postes.</p>
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <h6>Départements</h6>
                                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-sm table-hover table-bordered">
                                        <thead class="table-light"><tr><th>Nom</th><th>Description</th></tr></thead>
                                        <tbody>
                                            <?php if (empty($departements)): ?><tr><td colspan="2" class="text-center">Aucun département trouvé.</td></tr>
                                            <?php else: foreach ($departements as $d): ?><tr><td><?= htmlspecialchars($d['nom']) ?></td><td><?= htmlspecialchars($d['description']) ?></td></tr><?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <h6>Postes</h6>
                                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-sm table-hover table-bordered">
                                        <thead class="table-light"><tr><th>Nom</th><th>Département</th></tr></thead>
                                        <tbody>
                                            <?php if (empty($postes)): ?><tr><td colspan="2" class="text-center">Aucun poste trouvé.</td></tr>
                                            <?php else: foreach ($postes as $p): ?><tr><td><?= htmlspecialchars($p['nom']) ?></td><td><?= htmlspecialchars($p['departement_nom'] ?? 'N/A') ?></td></tr><?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end border-top-0 pt-3">
                 <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save-fill me-2"></i> Enregistrer les Paramètres</button>
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
    function updateRemoveButtonsState() {
        const rows = document.querySelectorAll('#joursFeriesContainer .dynamic-row');
        rows.forEach(row => {
            const removeBtn = row.querySelector('.btn-outline-danger');
            if (removeBtn) removeBtn.style.display = rows.length > 1 ? 'inline-block' : 'none';
        });
    }
    window.addDynamicRow = function() {
        const template = document.getElementById('jour-ferie-template');
        if (!template) { console.error("Template #jour-ferie-template not found."); return; }
        const container = document.getElementById('joursFeriesContainer');
        container.appendChild(template.content.cloneNode(true));
        updateRemoveButtonsState();
    }
    window.removeDynamicRow = function(button) {
        button.closest('.dynamic-row').remove();
        updateRemoveButtonsState();
    }
    const initialJoursFeries = <?= json_encode($joursFeries) ?>;
    const container = document.getElementById('joursFeriesContainer');
    if (initialJoursFeries && initialJoursFeries.length > 0) {
        initialJoursFeries.forEach(jf => {
            const template = document.getElementById('jour-ferie-template');
            const newRow = template.content.cloneNode(true);
            newRow.querySelector('[name="jours_feries_jour[]"]').value = jf.jour;
            newRow.querySelector('[name="jours_feries_mois[]"]').value = jf.mois;
            newRow.querySelector('[name="jours_feries_label[]"]').value = jf.label;
            container.appendChild(newRow);
        });
    } else {
        addDynamicRow();
    }
    updateRemoveButtonsState();
});
</script>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>