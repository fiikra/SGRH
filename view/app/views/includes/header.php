<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $data['titre'] ?? 'Mon ERP'; ?></title>
    <style>
        /* Styles de base */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 0; background-color: #f4f6f9; }
        a { text-decoration: none; color: #007bff; }
        .main-content { padding: 1.5rem; }
        
        /* Barre de navigation */
        .navbar { 
            background-color: #fff; 
            padding: 0 2rem; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            height: 60px;
        }
        .navbar-links a, .navbar-user a {
            padding: 20px 15px;
            display: inline-block;
            color: #333;
            transition: background-color 0.3s;
        }
        .navbar-links a:hover, .navbar-user .dropdown:hover .dropbtn {
            background-color: #f1f1f1;
        }
        .navbar .logo { font-weight: bold; font-size: 1.5rem; }
        
        /* Styles pour les menus déroulants */
        .dropdown { position: relative; display: inline-block; }
        .dropbtn { border: none; background: none; cursor: pointer; font-size: 1rem; font-family: inherit; }
        .dropdown-content { 
            display: none; 
            position: absolute; 
            background-color: #f9f9f9; 
            min-width: 200px; 
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); 
            z-index: 100; 
            border-radius: 4px;
            overflow: hidden;
        }
        .dropdown-content a { color: black; padding: 12px 16px; display: block; }
        .dropdown-content a:hover { background-color: #ddd; }
        .dropdown:hover .dropdown-content { display: block; }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="/dashboard" class="logo">Mon<strong>ERP</strong></a>
        
        <div class="navbar-links">
            <?php if (Auth::can('pos_utiliser')): ?><a href="/ventes/pos">POS</a><?php endif; ?>
            <?php if (Auth::can('factures_voir_liste')): ?><a href="/factures">Factures</a><?php endif; ?>
            <?php if (Auth::can('achats_voir_liste')): ?><a href="/achats">Achats</a><?php endif; ?>
            <?php if (Auth::can('articles_voir_liste')): ?><a href="/articles">Articles</a><?php endif; ?>
                <?php if (Auth::can('avoirs_voir_liste')): ?>
    <a href="/avoirs" style="margin-right: 1rem;">Avoirs</a>
<?php endif; ?>
            <?php if (Auth::can('tiers_voir_liste')): ?><a href="/tiers">Tiers</a><?php endif; ?>
            <?php if (get_param('module_services_active') == '1' && Auth::can('services_gerer')): ?><a href="/services">Services</a><?php endif; ?>

            <?php if (Auth::can('rapports_voir')): ?>
                <div class="dropdown">
                    <a href="#" class="dropbtn">Rapports</a>
                    <div class="dropdown-content">
                        <a href="/rapports/ventes">Ventes & Bénéfices</a>
                        <a href="/rapports/tresorerie">Trésorerie</a>
                        <a href="/rapports/livraisons">Livraisons</a>
                        <?php if (get_param('module_services_active') == '1'): ?>
                            <a href="/rapports/services">Services</a>
                        <?php endif; ?>
                        <?php if (Auth::can('paiements_encaisser')): ?>
    <a href="/paiements" style="margin-right: 1rem;">Encaissements</a>
<?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="navbar-user">
            <?php if (Auth::can('utilisateurs_voir_liste') || Auth::can('permissions_gerer') || Auth::can('logs_voir')): ?>
            <div class="dropdown">
                <a href="#" class="dropbtn">⚙️ Administration</a>
                <div class="dropdown-content">
                    <?php if (Auth::can('utilisateurs_voir_liste')): ?><a href="/utilisateurs">Utilisateurs</a><?php endif; ?>
                    <?php if (Auth::can('permissions_gerer')): ?><a href="/roles">Rôles</a><?php endif; ?>
                    <?php if (Auth::can('permissions_gerer')): ?><a href="/permissions">Permissions</a><?php endif; ?>
                    <?php if (Auth::can('logs_voir')): ?><a href="/logs">Journal des Logs</a><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <span>Bonjour, <strong><?php echo htmlspecialchars($_SESSION['user_nom']); ?></strong></span>
            <a href="/utilisateurs/logout" style="color: #e74c3c;">Déconnexion</a>
        </div>
    </nav>
    <main class="main-content">