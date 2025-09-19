<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> - <?= htmlspecialchars($pageTitle ?? 'Tableau de bord') ?></title>

    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:;">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    <meta http-equiv="X-Frame-Options" content="DENY">

    <!-- Stylesheets -->
    <link href="<?= APP_LINK ?>/assets/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="<?= APP_LINK ?>/assets/bootstrap/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= APP_LINK ?>/assets/bootstrap/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?= APP_LINK ?>/assets/bootstrap/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="<?= APP_LINK ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= APP_LINK ?>/assets/css/dashboard.css" rel="stylesheet">
    <!-- <link href="<?= APP_LINK ?>/assets/css/employees/profile/nstyle.css" rel="stylesheet">   -->

    <style>
        /* Extra: Mobile layout tweaks */
        body {
            font-size: 0.95rem;
        }

        h1, h2, h3 {
            font-size: calc(1.3rem + .6vw);
        }

        .container {
            padding-left: 15px;
            padding-right: 15px;
        }

        @media (max-width: 768px) {
            .navbar-nav {
                text-align: center;
            }

            .table-responsive {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    
    <?php include __DIR__ . '/navbar.php'; ?>

    <main class="container mt-4">

        <?php if (!isset($pageTitle)) : ?>
            <h1 class="mb-4"><?= htmlspecialchars($pageTitle) ?></h1>
        <?php endif; ?>

        <!-- Flash messages -->
        <?php display_flash_messages(); ?>