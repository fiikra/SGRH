<?php

// Sanitization des données
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    // Consider if strip_tags is always desired. htmlspecialchars is the primary security function here.
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Upload de fichier sécurisé
function uploadFile($file, $type = 'document') {
    // Ensure constants UPLOAD_DIR, ALLOWED_TYPES, MAX_FILE_SIZE are defined in your config.php
    if (!defined('UPLOAD_DIR')) {
        // Define a default or throw an error if not set. This should be in config.php
        // For example: define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/assets/uploads/');
        // For now, let's assume it will be defined. If not, this will cause issues.
        // throw new Exception("UPLOAD_DIR constant is not defined.");
    }
    if (!defined('ALLOWED_TYPES')) {
        // define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'application/pdf']);
    }
    if (!defined('MAX_FILE_SIZE')) {
        // define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
    }

    $targetDir = UPLOAD_DIR . $type . 's/'; // e.g., /assets/uploads/documents/ or /assets/uploads/photos/
    if (!file_exists($targetDir)) {
        if (!mkdir($targetDir, 0775, true)) { // Use 0775 for better security than 0777
            throw new Exception("Impossible de créer le répertoire d'upload: " . $targetDir);
        }
    }
    
    // Générer un nom de fichier unique
    $originalName = basename($file['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    // Clean the original name for use in the unique name, but keep it readable
    $safeOriginalName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $fileName = uniqid($type . '_', true) . '_' . $safeOriginalName . '.' . $extension; // More unique and descriptive
    $targetPath = $targetDir . $fileName;
    
    // Vérification du type de fichier (using file extension and MIME type)
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf']; // Define allowed extensions
    if (!in_array($extension, $allowedExtensions)) {
         throw new Exception("Extension de fichier non autorisée. Seuls JPG, PNG, PDF sont acceptés.");
    }

    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, ALLOWED_TYPES)) { // ALLOWED_TYPES should contain MIME types
        throw new Exception("Type de fichier MIME non autorisé. (" . htmlspecialchars($fileType) . ")");
    }
    
    // Vérification de la taille
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception("Fichier trop volumineux. Maximum " . (MAX_FILE_SIZE/1024/1024) . "MB.");
    }
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Return path relative to web root, assuming 'assets' is in the web root
        return 'assets/uploads/' . $type . 's/' . $fileName; 
    } else {
        throw new Exception("Erreur lors de l'upload du fichier. Vérifiez les permissions.");
    }
}

// Génération de numéro de référence
function generateReference($prefix = 'CERT') {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), -6)); // More random
}

// Formatage de date
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return ''; // Ou null, ou 'N/A' selon la préférence
    }
    try {
        $dateTime = new DateTime($date);
        return $dateTime->format($format);
    } catch (Exception $e) {
        // Log error or handle - returning original date might be confusing if it's clearly invalid
        return $date; // Fallback to original if formatting fails
    }
}
// Récupérer le nom de l'employé
function getEmployeeName($db, $nin) {
    if (!$db || empty($nin)) return 'Inconnu';
    $stmt = $db->prepare("SELECT first_name, last_name FROM employees WHERE nin = ?");
    $stmt->execute([$nin]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    return $employee ? htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) : 'Employé Inconnu';
}

// Avoir l'annee de conge actuel 
function getCurrentLeaveYear() {
    // This logic might be specific to a fiscal year for leaves.
    // Algerian labor law usually bases annual leave on the work year from date of hire or civil year.
    // Assuming this refers to a specific company policy for "leave year" definition.
    $now = new DateTime();
    $currentMonth = (int)$now->format('m');
    $currentYear = (int)$now->format('Y');
    
    // Example: if leave year is July 1st to June 30th
    // If current date is July 2024 to June 2025, leave year is "2024"
    // If current month is >= 7 (July), then the leave year started this civil year.
    // If current month is < 7 (Jan-June), then the leave year started last civil year.
    return ($currentMonth >= 7) ? $currentYear : $currentYear - 1;
}

// Helper functions pour améliorer la lisibilité et la maintenance
function getMaritalStatusLabel($status) {
    $statuses = [
        'single' => 'Célibataire',
        'Celibataire' => 'Célibataire', // Adding mapping for values from your view.php
        'married' => 'Marié(e)',
        'Marie' => 'Marié(e)',      // Adding mapping
        'divorced' => 'Divorcé(e)',
        'Divorce' => 'Divorcé(e)',    // Adding mapping
        'widowed' => 'Veuf/Veuve',
        'Veuf'    => 'Veuf/Veuve'     // Adding mapping
    ];
    return $statuses[trim($status)] ?? ucfirst(htmlspecialchars(trim($status)));
}

function getContractTypeLabel($type) {
    // This function can be expanded based on the types stored in personnel_settings
    // For now, basic mapping:
    $types = [
        'cdi' => 'CDI',
        'cdd' => 'CDD',
        'stage' => 'Stage',
        'interim' => 'Intérim',
        'essai' => 'Période d\'Essai'
    ];
    return $types[strtolower(trim($type))] ?? ucfirst(htmlspecialchars(trim($type)));
}

function getStatusBadgeClass($status) {
    // For employee status
    switch (strtolower(trim($status))) {
        case 'active': return 'success';
        case 'inactive': return 'secondary';
        case 'suspended': return 'warning text-dark'; // Added text-dark for yellow bg
        case 'cancelled': // Assuming 'cancelled' might be used like 'terminated'
        case 'terminated': return 'danger';
        default: return 'info';
    }
}

function getLeaveStatusLabel($status) {
    $statuses = [
        'pending' => 'En Attente',
        'approved' => 'Approuvé',
        'rejected' => 'Rejeté',
        'paused' => 'Suspendu', // From your view.php
        'cancelled' => 'Annulé'  // From your view.php
    ];
    return $statuses[strtolower(trim($status))] ?? ucfirst(htmlspecialchars(trim($status)));
}

function getLeaveStatusBadgeClass($status) {
    switch (strtolower(trim($status))) {
        case 'pending': return 'secondary'; // Changed from warning for consistency
        case 'approved': return 'success';
        case 'rejected': return 'danger';
        case 'paused': return 'warning text-dark';
        case 'cancelled': return 'dark';
        default: return 'info';
    }
}

function getCertificateTypeLabel($type) {
    // Corresponds to $typeLabels in your view.php
    $types = [
        'work' => 'Travail',
        'salary' => 'Salaire',
        'emploi' => 'Emploi',
        'travail' => 'Travail', // Duplicate of work, can be an alias
        'salaire' => 'Salaire'  // Duplicate of salary
    ];
    return $types[strtolower(trim($type))] ?? ucfirst(htmlspecialchars(trim($type)));
}


//generated password on reset password page 
function generateRandomPassword($length = 10) { // Increased length for better security
    $characterSet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?';
    $password = '';
    $characterSetLength = strlen($characterSet);
    for ($i = 0; $i < $length; $i++) {
        $password .= $characterSet[random_int(0, $characterSetLength - 1)];
    }
    return $password;
}

function getResumptionDate($endDate, $weekendDaysInput = ['6','0']) { // Sat, Sun by default
    if (empty($endDate)) return null;
    try {
        $resumption = new DateTime($endDate);
        $resumption->modify('+1 day'); // Start checking from the day after end_date

        $weekendDays = [];
        if (is_string($weekendDaysInput)) {
            $weekendDays = explode(',', $weekendDaysInput);
        } elseif (is_array($weekendDaysInput)) {
            $weekendDays = $weekendDaysInput;
        }
        $weekendDays = array_map('trim', $weekendDays); // Ensure no whitespace

        // Also consider public holidays if you have a global way to access them
        // global $db; // If needed for public holidays, or pass $public_holidays_array
        // $public_holidays_array = get_public_holidays($db); // Example

        while (in_array($resumption->format('w'), $weekendDays) /* || is_public_holiday($resumption, $public_holidays_array) */ ) {
            $resumption->modify('+1 day');
        }
        return $resumption->format('Y-m-d');
    } catch (Exception $e) {
        return null; // Or handle error
    }
}


function getCertTypeEnum($type) {
    // This function normalizes certificate type inputs
    switch (strtolower(trim($type))) {
        case 'salaire':
        case 'salary':
        case 'attestation_sold': // specific key from your list
            return 'Attestation_sold'; // Standardized key
        case 'emploi':
        case 'certficate': // typo from your list
        case 'certificate':
            return 'Certficate'; // Standardized key
        case 'travail':
        case 'work':
        case 'attestation':
        default:
            return 'Attestation'; // Standardized key (for "Attestation de travail")
    }
}

function getTitleAndAbbrAndType($enumKey) {
    // Returns details based on the standardized key from getCertTypeEnum
    switch ($enumKey) {
        case 'Attestation_sold':
            return ['title' => 'Attestation de Salaire', 'abbr' => 'ATSOLDE', 'type_db' => 'Attestation_sold'];
        case 'Certficate': // For "Certificat de travail"
            return ['title' => 'Certificat de Travail', 'abbr' => 'CERTIF', 'type_db' => 'Certficate'];
        case 'Attestation': // For "Attestation de travail"
        default:
            return ['title' => 'Attestation de Travail', 'abbr' => 'ATTEST', 'type_db' => 'Attestation'];
    }
}


function getNextOrderNumber($db, $certificateTypeEnum) {
    $currentYear = date('Y');
    
    // SQL to extract the number part XX from a reference like 'PREFIX-NXX-YYYYMMDD-UNIQUEID'
    // It looks for '-N' then takes the part after it, and before the next '-'
    $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(reference_number, '-N', -1), '-', 1) AS UNSIGNED)) as max_order
            FROM certificates
            WHERE certificate_type = :certificate_type
            AND YEAR(issue_date) = :current_year";
            // Using YEAR(issue_date) is reliable. Ensure 'issue_date' is a DATE or DATETIME column.
            // Your script currently uses CURDATE() for issue_date, which is fine.

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':certificate_type' => $certificateTypeEnum,
            ':current_year' => $currentYear
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC); // Use FETCH_ASSOC for clarity
        
        $maxOrder = 0;
        if ($result && $result['max_order'] !== null) {
            $maxOrder = (int)$result['max_order'];
        }
        return $maxOrder + 1;

    } catch (PDOException $e) {
        // Log error or handle as appropriate for your application
        error_log("Error in getNextOrderNumber: " . $e->getMessage());
        // Fallback to a random number or 1 if query fails, though ideally this shouldn't happen
        return rand(1,99); // Or just 1
    }
}

function getReferencePrefix($certificateTypeEnum) {
    switch ($certificateTypeEnum) {
        case 'Attestation':
            return 'AT'; // For Attestation de Travail
        case 'Attestation_sold':
            return 'AS'; // For Attestation de Salaire
        case 'Certficate': // Matches the value in your form and getTitleAndAbbrAndType
            return 'CT'; // For Certificat de Travail
        default:
            // Fallback for any other types or if $certificateTypeEnum is unexpected
            return 'DOC'; 
    }
}


// ** NOUVELLES FONCTIONS AJOUTÉES CI-DESSOUS **

/**
 * Parses a JSON field (typically from a database TEXT column) into a PHP array.
 * It's robust enough to handle:
 * - Input that is already a PHP array (returns it directly).
 * - Null, empty strings, or '0' (returns an empty array).
 * - Non-string, non-array inputs (returns an empty array).
 * - Valid JSON strings (decodes them).
 * - Invalid JSON strings (returns an empty array).
 *
 * @param mixed $field The field to parse. Expected to be a JSON string, but handles other types.
 * @return array The parsed PHP array, or an empty array if parsing is not possible or not applicable.
 */
if (!function_exists('parse_json_field')) {
    function parse_json_field($field) {
        // If it's already a PHP array (e.g., from a default value or pre-decoded source)
        if (is_array($field)) {
            return $field;
        }

        // If it's empty (covers null, empty string, '0', false), or not a string, return an empty array.
        // This check ensures that only non-empty strings proceed to json_decode.
        if (empty($field) || !is_string($field)) {
            return [];
        }
        
        // Attempt to decode the JSON string
        $arr = json_decode($field, true); // 'true' for associative array
        
        // Return the decoded array if json_decode was successful and resulted in an array;
        // otherwise, return an empty array.
        return is_array($arr) ? $arr : [];
    }
}

/**
 * Encodes a PHP array into a JSON string.
 * If the input is not an array, it returns an empty JSON array string '[]'.
 *
 * @param mixed $arr The PHP array to encode.
 * @return string A JSON string representation of the array, or '[]' for non-array inputs.
 */
if (!function_exists('encode_json_field')) {
    function encode_json_field($arr) {
        if (is_array($arr)) {
            // JSON_UNESCAPED_UNICODE is good for handling special characters properly.
            return json_encode($arr, JSON_UNESCAPED_UNICODE);
        }
        // If input is not an array, return a string representing an empty JSON array.
        return json_encode([]);
    }
}

if (!function_exists('get_public_holiday_dates_for_year')) {
    /**
     * Récupère les dates complètes (YYYY-MM-DD) des jours fériés pour une année donnée.
     * Se base sur les jours fériés stockés avec jour/mois (ex: depuis company_settings).
     * @param array $joursFeriesConfig L'array des jours fériés [{jour, mois, label}, ...].
     * @param int $year L'année pour laquelle générer les dates.
     * @return array Liste des dates de jours fériés au format YYYY-MM-DD.
     */
    function get_public_holiday_dates_for_year($joursFeriesConfig, $year) {
        $holiday_dates = [];
        if (!is_array($joursFeriesConfig)) {
            return $holiday_dates;
        }
        foreach ($joursFeriesConfig as $jf) {
            if (isset($jf['jour']) && isset($jf['mois']) &&
                is_numeric($jf['jour']) && is_numeric($jf['mois'])) {
                $month = str_pad($jf['mois'], 2, '0', STR_PAD_LEFT);
                $day = str_pad($jf['jour'], 2, '0', STR_PAD_LEFT);
                if (checkdate((int)$month, (int)$day, (int)$year)) {
                    $holiday_dates[] = "$year-$month-$day";
                }
            }
        }
        // Ajouter ici la logique pour les jours fériés religieux mobiles si nécessaire,
        // car ils ne peuvent pas être définis par un simple jour/mois fixe.
        // Par exemple, vous pourriez avoir une autre table ou une configuration pour eux.
        return array_unique($holiday_dates);
    }
}
if (!function_exists('calculate_base_working_days_in_month')) {
    /**
     * Calcule le nombre de jours ouvrables de base dans un mois donné.
     * @param int $year L'année.
     * @param int $month Le mois (1-12).
     * @param array $weekendDaysArray Tableau des jours de weekend (0 pour Dimanche, ..., 6 pour Samedi).
     * @param array $publicHolidaysDateArray Tableau des dates de jours fériés (YYYY-MM-DD) pour cette année.
     * @return int Nombre de jours ouvrables de base.
     */
    function calculate_base_working_days_in_month($year, $month, $weekendDaysArray, $publicHolidaysDateArray) {
        $numDaysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $baseWorkingDays = 0;

        for ($day = 1; $day <= $numDaysInMonth; $day++) {
            $currentDateStr = sprintf("%04d-%02d-%02d", $year, $month, $day);
            try {
                $currentDateTime = new DateTime($currentDateStr);
                $dayOfWeek = $currentDateTime->format('w'); // 0 (Dimanche) à 6 (Samedi)

                $isWeekend = in_array((string)$dayOfWeek, $weekendDaysArray);
                // Un jour férié n'est décompté que s'il ne tombe PAS un weekend déjà chômé.
                $isActualPublicHoliday = in_array($currentDateStr, $publicHolidaysDateArray) && !$isWeekend;

                if (!$isWeekend && !$isActualPublicHoliday) {
                    $baseWorkingDays++;
                }
            } catch (Exception $e) {
                // Gérer une date invalide si nécessaire, bien que la boucle devrait être sûre.
                error_log("Erreur de date dans calculate_base_working_days_in_month: " . $e->getMessage());
            }
        }
        return $baseWorkingDays;
    }
}

if (!function_exists('calculate_deductible_absence_days')) {
    /**
     * Calcule les jours d'absence déductibles sur une période,
     * en appliquant la règle où un weekend est compté si absence le jour précédent.
     *
     * @param PDO $db Connexion à la base de données.
     * @param string $employeeNin NIN de l'employé.
     * @param string $startDate Début de la période (YYYY-MM-DD).
     * @param string $endDate Fin de la période (YYYY-MM-DD).
     * @param array $weekendDaysArray Tableau des jours de weekend (0-6).
     * @param array $publicHolidaysDateArray Tableau des jours fériés (YYYY-MM-DD).
     * @return int Nombre de jours d'absence déductibles.
     */
    function calculate_deductible_absence_days($db, $employeeNin, $periodStart, $periodEnd, $weekendDaysArray, $publicHolidaysDateArray) {
        $sql = "SELECT attendance_date, status
                FROM employee_attendance
                WHERE employee_nin = :nin
                  AND attendance_date BETWEEN :start_date AND :end_date
                ORDER BY attendance_date ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':nin' => $employeeNin, ':start_date' => $periodStart, ':end_date' => $periodEnd]);
        $daily_records_map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $daily_records_map[$row['attendance_date']] = $row['status'];
        }

        $deductible_days = 0;
        $processed_weekend_absences = []; // Pour éviter de compter un weekend plusieurs fois

        $current_date = new DateTime($periodStart);
        $end_date_obj = new DateTime($periodEnd);

        while ($current_date <= $end_date_obj) {
            $current_date_str = $current_date->format('Y-m-d');
            $dayOfWeek = $current_date->format('w');
            $isWeekend = in_array((string)$dayOfWeek, $weekendDaysArray);
            $isPublicHoliday = in_array($current_date_str, $publicHolidaysDateArray);

            $status_today = $daily_records_map[$current_date_str] ?? 'no_record'; // 'no_record' si pas d'entrée

            // Est-ce un jour d'absence direct (non weekend, non férié, et statut d'absence) ?
            if (!$isWeekend && !$isPublicHoliday && $status_today !== 'present' && $status_today !== 'no_record') {
                $deductible_days++;

                // Vérifier si ce jour d'absence précède un weekend (ex: Vendredi si weekend = Sam/Dim)
                // Cette logique doit être adaptée à la configuration exacte du weekend.
                // Supposons weekend = Vendredi (5) et Samedi (6) pour un exemple algérien commun.
                // Ou weekend = Samedi (6) et Dimanche (0) pour un autre.
                // Pour l'exemple donné par l'utilisateur : absence Vendredi (jour N) => Samedi (N+1) et Dimanche (N+2) comptés.
                // On doit déterminer quel est le "jour avant le weekend".

                // Exemple simple : si on est jeudi et le weekend est Ven/Sam.
                // Si on est absent un jeudi, et que le vendredi et samedi sont des jours de weekend.
                $day_before_weekend1 = null;
                $weekend_day1 = null;
                $weekend_day2 = null;

                if (count($weekendDaysArray) == 2) { // Cas typique de 2 jours de weekend
                    // Exemple: Weekend = [5,6] (Vendredi, Samedi). Jour avant = Jeudi (4)
                    // Exemple: Weekend = [6,0] (Samedi, Dimanche). Jour avant = Vendredi (5)
                    sort($weekendDaysArray); // S'assurer de l'ordre
                    $weekend_day1_num = (int)$weekendDaysArray[0];
                    $weekend_day2_num = (int)$weekendDaysArray[1];

                    // Le jour juste avant le premier jour du weekend
                    $day_before_weekend1_num = ($weekend_day1_num == 0) ? 6 : $weekend_day1_num - 1;

                    if ((int)$dayOfWeek == $day_before_weekend1_num) {
                        $date_w1 = (clone $current_date)->modify('+1 day')->format('Y-m-d');
                        $date_w2 = (clone $current_date)->modify('+2 days')->format('Y-m-d');

                        // Si le weekend n'a pas déjà été traité pour cette absence
                        if (!isset($processed_weekend_absences[$date_w1])) {
                            // Vérifier si l'employé n'est pas revenu travailler le premier jour ouvrable après le weekend.
                            // Cette vérification de "retour" est complexe et dépend de la granularité des données.
                            // Pour l'instant, on applique la règle si absent le jour avant.
                            $deductible_days += 2; // Ajoute les 2 jours de weekend
                            $processed_weekend_absences[$date_w1] = true;
                            $processed_weekend_absences[$date_w2] = true;
                        }
                    }
                }
            } elseif ($isWeekend && isset($processed_weekend_absences[$current_date_str])) {
                // Ce jour de weekend est déjà compté comme absence déductible, ne rien faire.
            } elseif ($isWeekend || ($isPublicHoliday && !$isWeekend)) {
                 // Si c'est un weekend normal non précédé d'une absence "trigger" ou un jour férié chômé,
                 // ce n'est pas un jour d'absence déductible en soi (sauf si règle spécifique du weekend).
            } elseif ($status_today === 'no_record' && !$isWeekend && !$isPublicHoliday) {
                // Un jour ouvrable sans enregistrement est souvent considéré comme une absence injustifiée.
                // $deductible_days++;
                // Appliquer ici aussi la logique du weekend si ce "no_record" est un jour avant weekend.
                // Cette partie dépend de comment vous voulez traiter les "no_record".
            }
            $current_date->modify('+1 day');
        }
        return $deductible_days;
    }
}
function formatMonthYear($date) {
    // $date peut être un objet DateTime ou une string 'Y-m' ou 'Y-m-d'
    if (is_string($date)) {
        // Si format Y-m uniquement
        if (preg_match('/^\d{4}-\d{2}$/', $date)) {
            $date = DateTime::createFromFormat('Y-m', $date);
        } else {
            $date = new DateTime($date);
        }
    }
    if (empty($dateString) || $dateString === '0000-00-00') {
    return ''; // Return an empty string if the date is null or invalid
}
// Use the modern DateTime object to format the date
return (new DateTime($dateString))->format('d/m/Y');
}

if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $status_badge_class = 'secondary'; // Default
        $status_text = ucfirst(htmlspecialchars($status));
        switch (strtolower($status)) {
            case 'approved': 
                $status_badge_class = 'success'; 
                $status_text = 'Approuvé';
                break;
            case 'pending': 
                $status_badge_class = 'warning text-dark'; 
                $status_text = 'En attente';
                break;
            case 'rejected': 
                $status_badge_class = 'danger'; 
                $status_text = 'Rejeté';
                break;
            case 'completed': 
                $status_badge_class = 'primary'; 
                $status_text = 'Terminé';
                break;
            case 'cancelled': 
                $status_badge_class = 'dark'; 
                $status_text = 'Annulé';
                break;
             case 'prise': // For leave requests, if used for missions too
                $status_badge_class = 'info'; 
                $status_text = 'En cours';
                break;
        }
        echo "<span class=\"badge bg-{$status_badge_class}\">{$status_text}</span>";
    }
}
if (!function_exists('validateDate')) {
    /**
     * Validates a date string against a given format.
     *
     * @param string $date The date string to validate.
     * @param string $format The expected date format (default: 'Y-m-d H:i:s').
     * @return bool True if the date is valid and matches the format, false otherwise.
     */
    function validateDate($date, $format = 'Y-m-d H:i:s') {
        $d = DateTime::createFromFormat($format, $date);
        // The Y-m-d format check needs special handling if only date is passed but format expects time.
        // If only date part is needed for validation, and $date is just 'Y-m-d'
        if ($format === 'Y-m-d' && strlen($date) === 10) {
             $d = DateTime::createFromFormat('Y-m-d', $date);
             return $d && $d->format('Y-m-d') === $date;
        }
        return $d && $d->format($format) === $date;
    }
}
// Helper function for random string (ensure it's robust)
if (!function_exists('generateRandomAlphanumericString')) {
    function generateRandomAlphanumericString($length = 4) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            try {
                $randomString .= $characters[random_int(0, $charactersLength - 1)];
            } catch (Exception $e) {
                // Fallback for environments where random_int is not available (though unlikely for modern PHP)
                $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
            }
        }
        return $randomString;
    }
}




/**
 * Generates a unique, sequential reference number for a document.
 * Example format: CT0001/2025/DG
 *
 * @param string $prefix The prefix for the document type (e.g., 'CT', 'PV').
 * @param string $tableName The database table where the numbers are stored (e.g., 'contrats').
 * @param string $columnName The specific column in the table that holds the reference number.
 * @param PDO $db The database connection object.
 * @return string The newly generated unique reference number.
 */
function generate_reference_number(string $prefix, string $tableName, string $columnName, PDO $db): string {
    $year = date('Y');
    
    // This SQL is specifically designed to extract the number part from formats like 'NT0001-YYYY-...'
    // It safely handles cases where no records exist for the current year.
    $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(SUBSTRING($columnName, " . (strlen($prefix) + 1) . "), '-', 1) AS UNSIGNED)) 
            FROM $tableName 
            WHERE $columnName LIKE :pattern";

    $stmt = $db->prepare($sql);
    $stmt->execute([':pattern' => "$prefix%-$year%"]);
    
    $lastNum = $stmt->fetchColumn();
    
    $nextNum = ($lastNum) ? (int)$lastNum + 1 : 1;

    // Pad with leading zeros to a length of 4 (e.g., 0001, 0012).
    $paddedNum = str_pad($nextNum, 4, '0', STR_PAD_LEFT);

    // Assemble the final reference number with a random suffix for uniqueness, just like your example.
    return "{$prefix}{$paddedNum}-{$year}-" . strtoupper(substr(uniqid(), -4));
}
//generate admin route function 
function route($route, $params = []) {
    $url = APP_LINK . '/admin/index.php?route=' . urlencode($route);
    foreach ($params as $k => $v) {
        $url .= '&' . urlencode($k) . '=' . urlencode($v);
    }
    return $url;
}
//generate public route function 
function Proute($Proute, $params = []) {
    $url = APP_LINK . '/index.php?route=' . urlencode($Proute);
    foreach ($params as $k => $v) {
        $url .= '&' . urlencode($k) . '=' . urlencode($v);
    }
    return $url;
}



// --- HELPER FUNCTION FOR FILE SIZE ---
/**
 * Gets the server's maximum file upload size in bytes from php.ini.
 * @return int The maximum upload size in bytes.
 */
function get_max_upload_size(): int {
    $shorthand_to_bytes = function(string $shorthand): int {
        $shorthand = strtolower(trim($shorthand));
        $last = substr($shorthand, -1);
        $value = (int)$shorthand;
        switch ($last) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
        }
        return $value;
    };
    $upload_max = $shorthand_to_bytes(ini_get('upload_max_filesize'));
    $post_max = $shorthand_to_bytes(ini_get('post_max_size'));
    return min($upload_max, $post_max);
}
/**
 * Prints the hidden CSRF token input field.
 * Call this inside any <form> that uses method="post".
 */
function csrf_input() {
    // The '??' prevents errors if the session token isn't set for some reason.
    $token = $_SESSION['csrf_token'] ?? '';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}



/**
 * Generates a CSRF token if one doesn't already exist for the session.
 *
 * @return string The CSRF token.
 */
function generate_csrf_token() {
    // Regenerate token if it's not set
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates the submitted CSRF token against the one in the session.
 * Dies with an error if validation fails.
 */
function validate_csrf_token() {
    // Check if token is valid. Use hash_equals for timing-attack-safe comparison.
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        // Clear the invalid token
        unset($_SESSION['csrf_token']);
        http_response_code(403);
        die('CSRF validation failed. Request blocked.');
    }
    // Clear the token after use to ensure it's only used once
    unset($_SESSION['csrf_token']);
}

// Redirect function to handle URL redirection

function redirect(string $url): void
{
    header("Location: " . $url);
    exit();
}

/**
 * Creates a persistent login cookie for the user.
 *
 * @param PDO $db The database connection.
 * @param int $userId The user's ID.
 */
function create_persistent_login_cookie($db, $userId) {
    $selector = bin2hex(random_bytes(12));
    $token = bin2hex(random_bytes(32));
    $token_hash = password_hash($token, PASSWORD_DEFAULT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

    try {
        $stmt = $db->prepare(
            "INSERT INTO persistent_logins (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $selector, $token_hash, $expires_at]);

        // Set the cookie with the selector and the plain token
        setcookie('remember_me', $selector . ':' . $token, time() + 86400 * 30, '/', '', true, true);
    } catch (PDOException $e) {
        error_log("Failed to create persistent login token: " . $e->getMessage());
    }
}

/**
 * Attempts to log in a user using a persistent cookie.
 *
 * @param PDO $db The database connection.
 * @return bool True on success, false on failure.
 */
function login_with_cookie($db) {
    if (empty($_COOKIE['remember_me'])) {
        return false;
    }

    list($selector, $token) = explode(':', $_COOKIE['remember_me'], 2);

    if (!$selector || !$token) {
        return false;
    }

    $stmt = $db->prepare("SELECT * FROM persistent_logins WHERE selector = ? AND expires_at > NOW()");
    $stmt->execute([$selector]);
    $persistent_login = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($persistent_login && password_verify($token, $persistent_login['token_hash'])) {
        // Token is valid, log the user in
        $stmt_user = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt_user->execute([$persistent_login['user_id']]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Regenerate session and set user data
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Refresh the token for better security
            create_persistent_login_cookie($db, $user['id']);
            return true;
        }
    }
    
    // If validation fails, clear the cookie
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', time() - 3600, '/');
        // Optionally, delete the corresponding record from the database
        if ($persistent_login) {
            $db->prepare("DELETE FROM persistent_logins WHERE id = ?")->execute([$persistent_login['id']]);
        }
    }

    return false;
}
/**
 * Finalizes the user's login process by setting session variables,
 * handling the "Remember Me" cookie, and updating the database.
 *
 * @param PDO $db The database connection object.
 * @param int $user_id The ID of the user who has successfully authenticated.
 * @param bool $remember_me True if the user checked "Stay logged in".
 */
function finalize_login_session($db, $user_id, $remember_me = false) {
    // 1. Regenerate session ID to prevent session fixation attacks.
    session_regenerate_id(true);

    // 2. Fetch fresh user data to set in the session.
    $stmt = $db->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // This should not happen if called after successful authentication.
        // Clear session and redirect to login as a failsafe.
        session_destroy();
        redirect(Proute('login'));
        exit();
    }

    // 3. Set the main session variables.
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    // 4. Clean up any temporary OTP-related session data.
    unset(
        $_SESSION['otp_pending_user_id'],
        $_SESSION['otp_method'],
        $_SESSION['remember_me_after_otp'],
        $_SESSION['otp_last_sent']
    );

    // 5. Update the user's record in the database.
    // Reset failed login attempts and set the last login time.
    $db->prepare("UPDATE users SET failed_attempts = 0, account_locked_until = NULL, last_login = NOW() WHERE id = ?")
       ->execute([$user_id]);

    // 6. Handle the "Remember Me" functionality if requested.
    if ($remember_me) {
        create_persistent_login_cookie($db, $user_id);
    }
}

/**
 * Creates a secure, persistent "Remember Me" cookie and stores its token in the database.
 *
 * @param PDO $db The database connection object.
 * @param int $user_id The ID of the user to remember.
 */


function create_remember_me_token($db, $userId) {
    $selector = bin2hex(random_bytes(16)); // 16 bytes = 32 hex characters
    $validator = bin2hex(random_bytes(32)); // 32 bytes = 64 hex characters

    $hashed_validator = password_hash($validator, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

    try {
        $stmt = $db->prepare(
            "INSERT INTO auth_tokens (selector, hashed_validator, user_id, expires) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$selector, $hashed_validator, $userId, $expires]);

        // Set the cookie with the selector and the RAW validator
        $cookie_value = $selector . ':' . $validator;
        setcookie('remember_me', $cookie_value, [
            'expires' => strtotime('+30 days'),
            'path' => '/',
            'domain' => '', // Set your domain if needed
            'secure' => true, // Only send over HTTPS
            'httponly' => true, // Prevent JavaScript access
            'samesite' => 'Lax'
        ]);
    } catch (PDOException $e) {
        error_log("Failed to create remember me token: " . $e->getMessage());
    }
}

/**
 * Validates a "Remember Me" cookie and logs the user in if it's valid.
 *
 * @param PDO $db The database connection.
 * @return bool True if login was successful, false otherwise.
 */
function login_with_remember_me_cookie($db) {
    if (empty($_COOKIE['remember_me'])) {
        return false;
    }

    list($selector, $validator) = explode(':', $_COOKIE['remember_me'], 2);

    if (!$selector || !$validator) {
        return false;
    }

    $stmt = $db->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires >= NOW()");
    $stmt->execute([$selector]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($token && password_verify($validator, $token['hashed_validator'])) {
        // Token is valid, log the user in
        $stmt_user = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt_user->execute([$token['user_id']]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Log in success: Regenerate session and set user data
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // For added security, issue a new token and delete the old one
            create_remember_me_token($db, $user['id']);
            $db->prepare("DELETE FROM auth_tokens WHERE id = ?")->execute([$token['id']]);

            return true;
        }
    }
    
    // If token is invalid or expired, clear the cookie
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', time() - 3600, '/');
        if ($token) {
             $db->prepare("DELETE FROM auth_tokens WHERE id = ?")->execute([$token['id']]);
        }
    }

    return false;
}

function getStatusBadge($status) {
    $badges = [
        'present' => ['bg-success', 'Présent'],
        'present_offday' => ['bg-primary', 'Présent jour off'],
        'weekend' => ['bg-light text-dark', 'Week-end'],
        'holiday' => ['bg-warning', 'Férié'],
        'absent_unjustified' => ['bg-danger', 'Absence non justifiée'],
        'absent_authorized_paid' => ['bg-info', 'Absence autorisée payée'],
        'absent_authorized_unpaid' => ['bg-info', 'Absence autorisée non payée'],
        'sick_leave' => ['bg-secondary', 'Arrêt maladie'],
        'maternity_leave' => ['bg-secondary', 'Congé maternité'],
        'training' => ['bg-primary', 'Formation'],
        'mission' => ['bg-primary', 'Mission'],
        'other_leave' => ['bg-info', 'Autre congé'],
        'annual_leave' => ['bg-success', 'Congé annuel'],
        'present_weekend' => ['bg-success', 'Présent week-end']
    ];
    
    return '<span class="badge '.$badges[$status][0].'">'.$badges[$status][1].'</span>';
}

function calculateWorkHours($nin, $year, $month) {
    global $db;
    $startDate = "$year-$month-01";
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $result = [
        'total' => 0,
        'effective' => 0,
        'absent' => 0
    ];
    
    $stmt = $db->prepare("SELECT 
        SUM(effective_work_hours) AS total_hours,
        SUM(CASE WHEN status = 'present' THEN effective_work_hours ELSE 0 END) AS effective_hours,
        SUM(CASE WHEN status LIKE 'absent%' THEN 1 ELSE 0 END) AS absent_days
        FROM employee_attendance 
        WHERE employee_nin = ? 
        AND attendance_date BETWEEN ? AND ?");
    $stmt->execute([$nin, $startDate, $endDate]);
    $data = $stmt->fetch();
    
    if ($data) {
        $result['total'] = round($data['total_hours'] ?? 0, 2);
        $result['effective'] = round($data['effective_hours'] ?? 0, 2);
        $result['absent'] = $data['absent_days'] ?? 0;
    }
    
    return $result;
}

function calculateOvertime($nin, $year, $month) {
    global $db;
    $startDate = "$year-$month-01";
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $stmt = $db->prepare("SELECT SUM(overtime_hours_recorded) AS total_overtime 
                         FROM employee_attendance 
                         WHERE employee_nin = ? 
                         AND attendance_date BETWEEN ? AND ?");
    $stmt->execute([$nin, $startDate, $endDate]);
    $data = $stmt->fetch();
    
    return round($data['total_overtime'] ?? 0, 2);
}

function isMonthValidated($nin, $year, $month) {
    global $db;
    $startDate = "$year-$month-01";
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $stmt = $db->prepare("SELECT COUNT(*) AS invalid 
                         FROM employee_attendance 
                         WHERE employee_nin = ? 
                         AND attendance_date BETWEEN ? AND ?
                         AND is_validated = FALSE");
    $stmt->execute([$nin, $startDate, $endDate]);
    $data = $stmt->fetch();
    
    return $data['invalid'] == 0;
}
function fetchAttendanceData($nin, $year, $month) {
    global $db;
    $startDate = "$year-$month-01";
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $stmt = $db->prepare("SELECT * FROM employee_attendance 
                         WHERE employee_nin = ? 
                         AND attendance_date BETWEEN ? AND ?
                         ORDER BY attendance_date");
    $stmt->execute([$nin, $startDate, $endDate]);
    return $stmt->fetchAll();
}

function calculateLateHours($nin, $year, $month) {
    global $db;
    $startDate = "$year-$month-01";
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $stmt = $db->prepare("SELECT SUM(late_minutes) AS total_late 
                         FROM employee_attendance 
                         WHERE employee_nin = ? 
                         AND attendance_date BETWEEN ? AND ?");
    $stmt->execute([$nin, $startDate, $endDate]);
    $result = $stmt->fetch();
    
    return round(($result['total_late'] ?? 0) / 60, 2);
}

function hasUnvalidatedEntries($nin, $year, $month) {
    global $db;
    $startDate = "$year-$month-01";
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $stmt = $db->prepare("SELECT COUNT(*) AS count 
                         FROM employee_attendance 
                         WHERE employee_nin = ? 
                         AND attendance_date BETWEEN ? AND ?
                         AND is_validated = FALSE");
    $stmt->execute([$nin, $startDate, $endDate]);
    $result = $stmt->fetch();
    
    return $result['count'] > 0;
}
function formatTime(?string $time): string {
    if (empty($time) || $time == '00:00:00') {
        return '-';
    }
    return substr($time, 0, 5); // Return only HH:MM
}
function findAttendanceRecord(array $records, string $date): ?array {
    foreach ($records as $record) {
        if ($record['attendance_date'] == $date) {
            return $record;
        }
    }
    return null;
}
function isHoliday(string $date): bool {
    global $db;
    static $holidays = null;
    
    if ($holidays === null) {
        $year = date('Y', strtotime($date));
        $stmt = $db->prepare("SELECT holiday_date FROM company_holidays 
                             WHERE YEAR(holiday_date) = ?");
        $stmt->execute([$year]);
        $holidays = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    return in_array($date, $holidays);
}
if (!function_exists('sanitizeTextarea')) {
    function sanitizeTextarea($input) {
        if ($input === null) return '';
        // This trim is often sufficient for textarea content going to TCPDF
        return trim($input); 
    }
}
// Defines the absolute path to the project's root directory
define('ROOT_PATH', dirname(__DIR__));


     


        /**
 * Helper function to safely parse a JSON field from the database.
 * @param string|null $field The JSON string.
 * @return array The decoded array, or an empty array on failure.
 */


/**
 * Helper function to process dynamic rows from POST data into a JSON string.
 * @param array $jours An array of day values.
 * @param array $mois An array of month values.
 * @param array $labels An array of label values.
 * @param array $keys The keys for the associative array.
 * @return string JSON encoded string of the processed data.
 */
function process_dynamic_rows($jours, $mois, $labels, $keys) {
    $result = [];
    $count = count($jours);
    for ($i = 0; $i < $count; $i++) {
        // Only include rows where essential data is present
        if (!empty($jours[$i]) && !empty($mois[$i]) && !empty($labels[$i])) {
            $result[] = [
                $keys[0] => trim($jours[$i]),
                $keys[1] => trim($mois[$i]),
                $keys[2] => trim($labels[$i]),
            ];
        }
    }
    return json_encode($result, JSON_UNESCAPED_UNICODE);
}

function calculate_trial_months($start_date, $end_date) {
    if (empty($start_date) || empty($end_date)) {
        return 0;
    }
    try {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);
        return ($interval->y * 12) + $interval->m;
    } catch (Exception $e) {
        return 0; // Return 0 if dates are invalid
    }
}

?>