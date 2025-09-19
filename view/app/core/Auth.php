<?php
// /app/core/Auth.php

class Auth {
    /**
     * Vérifie si l'utilisateur connecté a une permission spécifique.
     * @param string $permissionSlug Le slug de la permission à vérifier.
     * @return bool True si l'utilisateur a la permission, false sinon.
     */
    public static function can($permissionSlug) {
        if (!estConnecte()) {
            return false;
        }
        
        // Vérifie si le tableau des permissions existe et si le slug est dedans
        return isset($_SESSION['user_permissions']) && in_array($permissionSlug, $_SESSION['user_permissions']);
    }
}