<?php
// /app/core/helpers.php

// Vérifie si un utilisateur est connecté
function estConnecte() {
    return isset($_SESSION['user_id']);
}

// Redirige vers une autre page
function rediriger($page) {
    // Note : Assurez-vous que l'URL est correcte en fonction de votre configuration serveur.
    // Cette configuration suppose que la racine est bien gérée par .htaccess.
    header('location: /' . $page);
    exit();
}


/**
 * NOUVEAU: Enregistre une action dans le journal du système.
 */
function enregistrerLog($action, $niveau = 'INFO') {
    $db = Database::getInstance()->getConnection();
    $sql = "INSERT INTO systeme_logs (utilisateur_id, utilisateur_nom, niveau, action) VALUES (:uid, :unom, :niv, :act)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':uid' => $_SESSION['user_id'] ?? null,
        ':unom' => $_SESSION['user_nom'] ?? 'Système',
        ':niv' => $niveau,
        ':act' => $action
    ]);


    /**
 * Récupère la valeur d'un paramètre système.
 * Utilise un cache statique pour ne pas interroger la BDD à chaque appel.
 */
function get_param($cle) {
    static $parametres = null;

    if ($parametres === null) {
        $db = Database::getInstance()->getConnection();
        $results = $db->query("SELECT cle, valeur FROM parametres")->fetchAll(PDO::FETCH_KEY_PAIR);
        $parametres = $results;
    }
    
    return $parametres[$cle] ?? null;
}
}