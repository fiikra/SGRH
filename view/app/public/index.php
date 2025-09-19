<?php
// /public/index.php

// Démarrer la session de manière sécurisée
session_start();

// Charger les helpers en premier
require_once '../app/core/helpers.php'; // Chemin mis à jour
require_once ROOT . '/app/core/Auth.php';

// Définir une constante pour le chemin racine de l'application
define('ROOT', dirname(__DIR__));

// Charger les fichiers du cœur de l'application
require_once ROOT . '/app/core/Database.php';
require_once ROOT . '/app/core/Router.php';
require_once ROOT . '/app/core/Controller.php';

// Instancier le routeur et traiter la requête
$router = new Router();
$router->dispatch();