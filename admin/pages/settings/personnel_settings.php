<?php
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

// Corrected Helper for array/JSON fields
function parse_json_field($field) {
    // If it's already a PHP array (e.g. from a default value or pre-decoded source)
    if (is_array($field)) {
        return $field;
    }
    // If it's empty (null, empty string, 0), or not a string, return an empty array.
    if (empty($field) || !is_string($field)) {
        return [];
    }
    
    // Attempt to decode the JSON string
    $arr = json_decode($field, true);
    
    // Return the decoded array, or an empty array if decoding failed or resulted in non-array
    return is_array($arr) ? $arr : [];
}

function encode_json_field($arr) {
    if (is_array($arr)) return json_encode($arr, JSON_UNESCAPED_UNICODE);
    return json_encode([]); // Return empty JSON array string for non-arrays
}

// Fetch personnel settings (singleton row id=1)
$settings_stmt_main = $db->query("SELECT * FROM personnel_settings WHERE id=1 LIMIT 1");
$settings = $settings_stmt_main->fetch(PDO::FETCH_ASSOC);

if (!$settings) { // Initialize settings if they don't exist for the first time
    try {
        // Ensure all relevant columns exist before inserting, or handle potential errors if columns are missing.
        // For simplicity, assuming table structure is correct as per DDL.
        $db->exec("INSERT INTO personnel_settings (id, postes, departements, types_contrat, work_hours_per_week, weekend_days, hs_policy, min_salaire_cat, matricule_pattern, exercice_start, anciennete_depart, docs_embauche, public_holidays, defined_absence_types) 
                   VALUES (1, '[]', '[]', '[]', 40, '5,6', '', '[]', '', NULL, 'hire_date', '[]', '[]', '[\"Congé Annuel\",\"Congé Maladie\",\"Absence Autorisée Payée\",\"Absence Autorisée Non Payée\",\"Absence Non Justifiée\",\"Maternité\",\"Formation\",\"Mission\"]')");
        $settings_stmt_main = $db->query("SELECT * FROM personnel_settings WHERE id=1 LIMIT 1"); // Re-fetch
        $settings = $settings_stmt_main->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Handle error if table or columns are not set up correctly.
        die("Erreur d'initialisation des paramètres du personnel: " . $e->getMessage() . "<br>Veuillez vérifier que la table 'personnel_settings' et toutes ses colonnes sont correctement créées.");
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Matrice des postes
        $postes = [];
        if (!empty($_POST['poste_nom']) && is_array($_POST['poste_nom'])) {
            foreach ($_POST['poste_nom'] as $k => $posteNom) {
                $desc = $_POST['poste_desc'][$k] ?? '';
                if (trim($posteNom)) {
                    $postes[] = [
                        'nom' => trim($posteNom),
                        'description' => trim($desc)
                    ];
                }
            }
        }
        if (!empty($_POST['modal_poste_nom'])) { 
            $postes[] = [
                'nom' => trim($_POST['modal_poste_nom']),
                'description' => trim($_POST['modal_poste_desc'])
            ];
        }
        $postesJson = encode_json_field(array_map("unserialize", array_unique(array_map("serialize", $postes))));


        // Départements
        $departements = [];
        if (!empty($_POST['departement_nom']) && is_array($_POST['departement_nom'])) {
            foreach ($_POST['departement_nom'] as $deptNom) {
                if (trim($deptNom)) $departements[] = trim($deptNom);
            }
        }
        if (!empty($_POST['modal_departement_nom'])) { 
             $departements[] = trim($_POST['modal_departement_nom']);
        }
        $departementsJson = encode_json_field(array_unique($departements));


        // Types de contrats
        $contrats = [];
        if (!empty($_POST['type_contrat']) && is_array($_POST['type_contrat'])) {
            foreach ($_POST['type_contrat'] as $type) {
                if (trim($type)) $contrats[] = trim($type);
            }
        }
        $contratsJson = encode_json_field(array_unique($contrats));

        $hs_policy = sanitize($_POST['hs_policy'] ?? '');

        $min_salaire = [];
        if (!empty($_POST['cat_nom']) && is_array($_POST['cat_nom'])) {
            foreach ($_POST['cat_nom'] as $k => $cat) {
                $val = $_POST['cat_salaire'][$k] ?? '';
                if (trim($cat) && is_numeric($val)) {
                    $min_salaire[] = [
                        'categorie' => trim($cat),
                        'min_salaire' => floatval($val)
                    ];
                }
            }
        }
        $minSalaireJson = encode_json_field(array_map("unserialize", array_unique(array_map("serialize", $min_salaire))));


        $docs_embauche = [];
        if (!empty($_POST['doc_embauche']) && is_array($_POST['doc_embauche'])) {
            foreach ($_POST['doc_embauche'] as $doc) {
                if (trim($doc)) $docs_embauche[] = trim($doc);
            }
        }
        $docsEmbaucheJson = encode_json_field(array_unique($docs_embauche));

        $public_holidays_data = [];
        if (!empty($_POST['holiday_date']) && is_array($_POST['holiday_date'])) {
            foreach ($_POST['holiday_date'] as $k => $date) {
                $desc = $_POST['holiday_description'][$k] ?? '';
                if (trim($date) && trim($desc)) {
                    $public_holidays_data[] = [
                        'date' => trim($date),
                        'description' => trim($desc)
                    ];
                }
            }
        }
        $publicHolidaysJson = encode_json_field(array_map("unserialize", array_unique(array_map("serialize", $public_holidays_data))));
        
        $defined_absence_types_data = [];
        if (!empty($_POST['absence_type_name']) && is_array($_POST['absence_type_name'])) {
            foreach ($_POST['absence_type_name'] as $typeName) {
                if (trim($typeName)) {
                    $defined_absence_types_data[] = trim($typeName);
                }
            }
        }
        $definedAbsenceTypesJson = encode_json_field(array_unique($defined_absence_types_data));

        $exercice_start_val = sanitize($_POST['exercice_start'] ?? '');
        if (empty($exercice_start_val)) $exercice_start_val = null;


        $update_params = [
            'postes' => $postesJson,
            'departements' => $departementsJson,
            'types_contrat' => $contratsJson,
            'work_hours_per_week' => sanitize($_POST['work_hours_per_week'] ?? 40),
            'weekend_days' => isset($_POST['weekend_days']) && is_array($_POST['weekend_days']) ? implode(',', $_POST['weekend_days']) : '',
            'hs_policy' => $hs_policy,
            'min_salaire_cat' => $minSalaireJson,
            'matricule_pattern' => sanitize($_POST['matricule_pattern'] ?? ''),
            'exercice_start' => $exercice_start_val,
            'anciennete_depart' => sanitize($_POST['anciennete_depart'] ?? 'hire_date'),
            'docs_embauche' => $docsEmbaucheJson,
            'public_holidays' => $publicHolidaysJson,
            'defined_absence_types' => $definedAbsenceTypesJson,
            'id' => 1 
        ];
        
        $sql_update = "UPDATE personnel_settings SET 
            postes=:postes, departements=:departements, types_contrat=:types_contrat, 
            work_hours_per_week=:work_hours_per_week, weekend_days=:weekend_days, hs_policy=:hs_policy, 
            min_salaire_cat=:min_salaire_cat, matricule_pattern=:matricule_pattern, 
            exercice_start=:exercice_start, anciennete_depart=:anciennete_depart, docs_embauche=:docs_embauche, 
            public_holidays=:public_holidays, defined_absence_types=:defined_absence_types
            WHERE id=:id";
        
        $stmt = $db->prepare($sql_update);
        $stmt->execute($update_params);

        $_SESSION['success'] = "Paramètres sauvegardés avec succès !";
        header("Location: personnel_settings.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de la sauvegarde: " . $e->getMessage();
    }
}

// Re-fetch settings after potential update or for initial load (if not already fetched or if fetch failed)
if (empty($settings)) {
    $settings_stmt_main = $db->query("SELECT * FROM personnel_settings WHERE id=1 LIMIT 1");
    $settings = $settings_stmt_main->fetch(PDO::FETCH_ASSOC);
}


$postes_list = parse_json_field($settings['postes'] ?? '[]'); // Default to empty JSON array string
$departements_list = parse_json_field($settings['departements'] ?? '[]');
$types_contrat_list = parse_json_field($settings['types_contrat'] ?? '[]');
$min_salaire_list = parse_json_field($settings['min_salaire_cat'] ?? '[]');
$docs_embauche_list = parse_json_field($settings['docs_embauche'] ?? '[]');
$public_holidays_list = parse_json_field($settings['public_holidays'] ?? '[]');

// For defined_absence_types, use the PHP array default *after* parsing attempt
$db_defined_absence_types = parse_json_field($settings['defined_absence_types'] ?? '[]');
if (empty($db_defined_absence_types)) {
    $defined_absence_types_list = ['Congé Annuel', 'Congé Maladie', 'Absence Autorisée Payée', 'Absence Autorisée Non Payée', 'Absence Non Justifiée', 'Maternité', 'Formation', 'Mission'];
} else {
    $defined_absence_types_list = $db_defined_absence_types;
}


$selectedWeekendDays = isset($settings['weekend_days']) && !empty($settings['weekend_days']) ? explode(',', $settings['weekend_days']) : ['5','6']; 
$pageTitle = "Paramètres de Personnel";
include '../../includes/header.php';
?>
<div class="container">
    <h1 class="mb-4">Paramètres de gestion du personnel</h1>
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <?php csrf_input(); // ✅ Correct: Just call the function here ?>
    <input type="hidden" id="modal_poste_nom" name="modal_poste_nom">
        <input type="hidden" id="modal_poste_desc" name="modal_poste_desc">
        <input type="hidden" id="modal_departement_nom" name="modal_departement_nom">

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                Matrice des Postes
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addPosteModal"><i class="bi bi-plus-circle"></i> Ajouter un poste (Modal)</button>
            </div>
            <div class="card-body">
                <div id="postesList">
                    <?php if(empty($postes_list)): ?>
                        <div class="row g-2 mb-2 align-items-center">
                            <div class="col-md-5"><input type="text" name="poste_nom[]" class="form-control form-control-sm" placeholder="Nom du poste"></div>
                            <div class="col-md-6"><input type="text" name="poste_desc[]" class="form-control form-control-sm" placeholder="Description (optionnelle)"></div>
                            <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeDynamicRow(this)" disabled><i class="bi bi-trash"></i></button></div>
                        </div>
                    <?php else: foreach($postes_list as $poste): ?>
                        <div class="row g-2 mb-2 align-items-center">
                            <div class="col-md-5"><input type="text" name="poste_nom[]" class="form-control form-control-sm" value="<?= htmlspecialchars($poste['nom'] ?? '') ?>" placeholder="Nom du poste"></div>
                            <div class="col-md-6"><input type="text" name="poste_desc[]" class="form-control form-control-sm" value="<?= htmlspecialchars($poste['description'] ?? '') ?>" placeholder="Description (optionnelle)"></div>
                            <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeDynamicRow(this)"><i class="bi bi-trash"></i></button></div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-success" onclick="addPosteRow()">Ajouter une ligne de poste</button>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                Départements/Services
                 <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addDepartementModal"><i class="bi bi-plus-circle"></i> Ajouter (Modal)</button>
            </div>
            <div class="card-body">
                <div id="departementsList">
                    <?php if(empty($departements_list)): ?>
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" name="departement_nom[]" class="form-control" placeholder="Nom du département/service">
                            <button type="button" class="btn btn-outline-danger" onclick="removeDynamicRow(this)" disabled><i class="bi bi-trash"></i></button>
                        </div>
                    <?php else: foreach($departements_list as $dept): ?>
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" name="departement_nom[]" class="form-control" value="<?= htmlspecialchars($dept) ?>" placeholder="Nom du département/service">
                            <button type="button" class="btn btn-outline-danger" onclick="removeDynamicRow(this)"><i class="bi bi-trash"></i></button>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-success" onclick="addSimpleDynamicRow('departementsList', 'departement_nom[]', 'Nom du département/service', true)">Ajouter un département</button>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Types de contrats</div>
            <div class="card-body">
                <div id="contratsList">
                    <?php if(empty($types_contrat_list)): ?>
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" name="type_contrat[]" class="form-control" placeholder="Type de contrat (ex: CDI)">
                            <button type="button" class="btn btn-outline-danger" onclick="removeDynamicRow(this)" disabled><i class="bi bi-trash"></i></button>
                        </div>
                    <?php else: foreach($types_contrat_list as $ct): ?>
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" name="type_contrat[]" class="form-control" value="<?= htmlspecialchars($ct) ?>" placeholder="Type de contrat">
                            <button type="button" class="btn btn-outline-danger" onclick="removeDynamicRow(this)"><i class="bi bi-trash"></i></button>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-success" onclick="addSimpleDynamicRow('contratsList', 'type_contrat[]', 'Type de contrat', true)">Ajouter un type de contrat</button>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Paramètres de Travail et Heures</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Durée légale du travail (heures/semaine)</label>
                        <input type="number" min="1" max="60" name="work_hours_per_week" class="form-control" value="<?= htmlspecialchars($settings['work_hours_per_week'] ?? 40) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jour(s) de repos hebdomadaire</label>
                        <select name="weekend_days[]" class="form-select" multiple>
                            <option value="0" <?= in_array('0', $selectedWeekendDays) ? 'selected' : '' ?>>Dimanche</option>
                            <option value="1" <?= in_array('1', $selectedWeekendDays) ? 'selected' : '' ?>>Lundi</option>
                            <option value="2" <?= in_array('2', $selectedWeekendDays) ? 'selected' : '' ?>>Mardi</option>
                            <option value="3" <?= in_array('3', $selectedWeekendDays) ? 'selected' : '' ?>>Mercredi</option>
                            <option value="4" <?= in_array('4', $selectedWeekendDays) ? 'selected' : '' ?>>Jeudi</option>
                            <option value="5" <?= in_array('5', $selectedWeekendDays) ? 'selected' : '' ?>>Vendredi</option>
                            <option value="6" <?= in_array('6', $selectedWeekendDays) ? 'selected' : '' ?>>Samedi</option>
                        </select>
                        <small class="text-muted">Ctrl/Cmd + clic pour plusieurs. Au moins un jour doit être sélectionné.</small>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Politique d’heures supplémentaires (Note informative)</label>
                    <textarea name="hs_policy" class="form-control" rows="2"><?= htmlspecialchars($settings['hs_policy'] ?? 'Conformément à la législation Algérienne en vigueur. Majoration pour heures de jour, nuit, week-end et jours fériés.') ?></textarea>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">Jours Fériés Officiels</div>
            <div class="card-body">
                <div id="publicHolidaysList">
                    <?php if (!empty($public_holidays_list)): foreach ($public_holidays_list as $holiday): ?>
                    <div class="row g-2 mb-2 align-items-center">
                        <div class="col-md-5">
                            <input type="date" name="holiday_date[]" class="form-control form-control-sm" value="<?= htmlspecialchars($holiday['date'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="holiday_description[]" class="form-control form-control-sm" value="<?= htmlspecialchars($holiday['description'] ?? '') ?>" placeholder="Description du jour férié">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="removeDynamicRow(this)"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                     <div class="row g-2 mb-2 align-items-center">
                        <div class="col-md-5">
                            <input type="date" name="holiday_date[]" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="holiday_description[]" class="form-control form-control-sm" placeholder="Description du jour férié">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="removeDynamicRow(this)" disabled><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-success mt-2" onclick="addPublicHolidayRow()">Ajouter un jour férié</button>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Types d'Absence Prédéfinis (pour modèles/formulaires)</div>
            <div class="card-body">
                <div id="definedAbsenceTypesList">
                    <?php if(empty($defined_absence_types_list)): ?>
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" name="absence_type_name[]" class="form-control" placeholder="Ex: Congé Maladie">
                            <button type="button" class="btn btn-outline-danger" onclick="removeDynamicRow(this)" disabled><i class="bi bi-trash"></i></button>
                        </div>
                    <?php else: foreach($defined_absence_types_list as $abs_type): ?>
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" name="absence_type_name[]" class="form-control" value="<?= htmlspecialchars($abs_type) ?>" placeholder="Ex: Congé Annuel">
                            <button type="button" class="btn btn-outline-danger" onclick="removeDynamicRow(this)"><i class="bi bi-trash"></i></button>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-success" onclick="addSimpleDynamicRow('definedAbsenceTypesList', 'absence_type_name[]', 'Type d\'absence', true)">Ajouter un type d'absence</button>
            </div>
        </div>

         <div class="card mb-4">
            <div class="card-header">Salaire minimum par catégorie</div>
            <div class="card-body">
                <div id="minSalaireList">
                <?php if(empty($min_salaire_list)): ?>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6"><input type="text" name="cat_nom[]" class="form-control form-control-sm" placeholder="Catégorie"></div>
                        <div class="col-md-5"><input type="number" name="cat_salaire[]" class="form-control form-control-sm" placeholder="Salaire min." min="0" step="0.01"></div>
                        <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeDynamicRow(this)" disabled><i class="bi bi-trash"></i></button></div>
                    </div>
                <?php else: foreach($min_salaire_list as $cat): ?>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6"><input type="text" name="cat_nom[]" class="form-control form-control-sm" value="<?= htmlspecialchars($cat['categorie'] ?? '') ?>" placeholder="Catégorie"></div>
                        <div class="col-md-5"><input type="number" name="cat_salaire[]" class="form-control form-control-sm" value="<?= htmlspecialchars($cat['min_salaire'] ?? '') ?>" placeholder="Salaire min." min="0" step="0.01"></div>
                        <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeDynamicRow(this)"><i class="bi bi-trash"></i></button></div>
                    </div>
                <?php endforeach; endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-success" onclick="addMinSalaireRow()">Ajouter une catégorie de salaire</button>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Format des Identifiants</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Modèle de numéro matricule</label>
                    <input type="text" name="matricule_pattern" class="form-control" value="<?= htmlspecialchars($settings['matricule_pattern'] ?? '') ?>" placeholder="Ex: EMP-{{YEAR}}-{{N}}">
                    <small class="text-muted">Variables: {{YEAR}} (année en 4 chiffres), {{YY}} (année en 2 chiffres), {{N}} (numéro séquentiel auto-incrémenté).</small>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">Paramètres de l'Exercice et Ancienneté</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Date de début de l’exercice social</label>
                        <input type="date" name="exercice_start" class="form-control" value="<?= htmlspecialchars($settings['exercice_start'] ?? '') ?>">
                        <small class="text-muted">Utilisé pour le calcul des droits aux congés, etc.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                         <label class="form-label">Point de départ calcul ancienneté</label>
                         <select name="anciennete_depart" class="form-select">
                            <option value="hire_date" <?= ($settings['anciennete_depart'] ?? 'hire_date') == 'hire_date' ? 'selected' : '' ?>>Date d'embauche</option>
                            <option value="contract_start_date" <?= ($settings['anciennete_depart'] ?? '') == 'contract_start_date' ? 'selected' : '' ?>>Date début du premier contrat</option>
                         </select>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">Documents obligatoires à l'embauche</div>
            <div class="card-body">
                <div id="docsEmbaucheList">
                <?php if(empty($docs_embauche_list)): ?>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" name="doc_embauche[]" class="form-control" placeholder="Ex: Carte d'identité nationale">
                        <button type="button" class="btn btn-outline-danger" onclick="removeDynamicRow(this)" disabled><i class="bi bi-trash"></i></button>
                    </div>
                <?php else: foreach($docs_embauche_list as $doc): ?>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" name="doc_embauche[]" class="form-control" value="<?= htmlspecialchars($doc) ?>" placeholder="Document">
                        <button type="button" class="btn btn-outline-danger" onclick="removeDynamicRow(this)"><i class="bi bi-trash"></i></button>
                    </div>
                <?php endforeach; endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-success" onclick="addSimpleDynamicRow('docsEmbaucheList', 'doc_embauche[]', 'Nom du document', true)">Ajouter un document</button>
            </div>
        </div>

        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Enregistrer les Paramètres
            </button>
        </div>
    </form>
</div>

<div class="modal fade" id="addPosteModal" tabindex="-1" aria-labelledby="addPosteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="modalPosteForm" onsubmit="submitModalPoste(event)">
      <?php csrf_input(); // ✅ Correct: Just call the function here ?>
    <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="addPosteModalLabel">Ajouter un poste</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Nom du poste</label><input type="text" class="form-control" id="newModalPosteNom" required></div>
          <div class="mb-3"><label class="form-label">Description</label><input type="text" class="form-control" id="newModalPosteDesc"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Ajouter</button></div>
      </div>
    </form>
  </div>
</div>
<div class="modal fade" id="addDepartementModal" tabindex="-1" aria-labelledby="addDepartementModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="modalDepartementForm" onsubmit="submitModalDepartement(event)">
     <?php csrf_input(); // ✅ Correct: Just call the function here ?>
    <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="addDepartementModalLabel">Ajouter un département</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <div class="modal-body"><div class="mb-3"><label class="form-label">Nom du département</label><input type="text" class="form-control" id="newModalDepartementNom" required></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Ajouter</button></div>
      </div>
    </form>
  </div>
</div>

<script>
function removeDynamicRow(button) {
    const row = button.closest('.row, .input-group'); // Handle both types of rows
    const list = row.parentElement;
    row.remove();
    // Disable the remove button on the last remaining item if it's the placeholder
    if (list.children.length === 1) {
        const lastRemoveButton = list.querySelector('.btn-outline-danger');
        if (lastRemoveButton && list.querySelector('input[type="text"]').value === '') { // Check if it's a placeholder
            // lastRemoveButton.disabled = true; // Or simply don't disable if it's always removable
        }
    }
}

function addSimpleDynamicRow(listId, inputName, placeholder, useSmallInput = false) {
    const list = document.getElementById(listId);
    const newRow = document.createElement('div');
    newRow.className = 'input-group mb-2' + (useSmallInput ? ' input-group-sm' : '');
    newRow.innerHTML = `
        <input type="text" name="${inputName}" class="form-control" placeholder="${placeholder}">
        <button type="button" class="btn btn-outline-danger" onclick="removeDynamicRow(this)"><i class="bi bi-trash"></i></button>
    `;
    list.appendChild(newRow);
    // Enable remove button on the first (potentially disabled) item
    const firstRemoveButton = list.querySelector('.btn-outline-danger[disabled]');
    if (firstRemoveButton) firstRemoveButton.disabled = false;
}

function addPosteRow() {
    const list = document.getElementById('postesList');
    const newRow = document.createElement('div');
    newRow.className = 'row g-2 mb-2 align-items-center';
    newRow.innerHTML = `
        <div class="col-md-5"><input type="text" name="poste_nom[]" class="form-control form-control-sm" placeholder="Nom du poste"></div>
        <div class="col-md-6"><input type="text" name="poste_desc[]" class="form-control form-control-sm" placeholder="Description (optionnelle)"></div>
        <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeDynamicRow(this)"><i class="bi bi-trash"></i></button></div>
    `;
    list.appendChild(newRow);
    const firstRemoveButton = list.querySelector('.btn-outline-danger[disabled]');
    if (firstRemoveButton) firstRemoveButton.disabled = false;
}

function addPublicHolidayRow() {
    const list = document.getElementById('publicHolidaysList');
    const newRow = document.createElement('div');
    newRow.className = 'row g-2 mb-2 align-items-center';
    newRow.innerHTML = `
        <div class="col-md-5"><input type="date" name="holiday_date[]" class="form-control form-control-sm"></div>
        <div class="col-md-6"><input type="text" name="holiday_description[]" class="form-control form-control-sm" placeholder="Description du jour férié"></div>
        <div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="removeDynamicRow(this)"><i class="bi bi-trash"></i></button></div>
    `;
    list.appendChild(newRow);
    const firstRemoveButton = list.querySelector('.btn-outline-danger[disabled]');
    if (firstRemoveButton) firstRemoveButton.disabled = false;
}

function addMinSalaireRow() {
    const list = document.getElementById('minSalaireList');
    const newRow = document.createElement('div');
    newRow.className = 'row g-2 mb-2';
    newRow.innerHTML = `
        <div class="col-md-6"><input type="text" name="cat_nom[]" class="form-control form-control-sm" placeholder="Catégorie"></div>
        <div class="col-md-5"><input type="number" name="cat_salaire[]" class="form-control form-control-sm" placeholder="Salaire min." min="0" step="0.01"></div>
        <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeDynamicRow(this)"><i class="bi bi-trash"></i></button></div>
    `;
    list.appendChild(newRow);
    const firstRemoveButton = list.querySelector('.btn-outline-danger[disabled]');
    if (firstRemoveButton) firstRemoveButton.disabled = false;
}

// Modals submission
function submitModalPoste(event) {
    event.preventDefault();
    document.getElementById('modal_poste_nom').value = document.getElementById('newModalPosteNom').value;
    document.getElementById('modal_poste_desc').value = document.getElementById('newModalPosteDesc').value;
    document.querySelector('form[method="post"]').submit(); // Submit main form
}
function submitModalDepartement(event) {
    event.preventDefault();
    document.getElementById('modal_departement_nom').value = document.getElementById('newModalDepartementNom').value;
    document.querySelector('form[method="post"]').submit(); // Submit main form
}
</script>
<?php include '../../includes/footer.php'; ?>