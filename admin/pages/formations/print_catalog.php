<?php
if (!defined('APP_SECURE_INCLUDE')) { http_response_code(403); die('Direct access not allowed.'); }
redirectIfNotAdminOrHR();

$formation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$formation_id) { die('ID de formation manquant.'); }

// Fetch formation details
$stmt = $db->prepare("SELECT * FROM formations WHERE id = ?");
$stmt->execute([$formation_id]);
$formation = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$formation) { die('Formation non trouvée.'); }

// Fetch participants
$p_stmt = $db->prepare("
    SELECT e.first_name, e.last_name, e.department, e.position 
    FROM formation_participants fp 
    JOIN employees e ON fp.employee_nin = e.nin 
    WHERE fp.formation_id = ? 
    ORDER BY e.last_name, e.first_name
");
$p_stmt->execute([$formation_id]);
$participants = $p_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Catalogue - <?= htmlspecialchars($formation['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container { max-width: 800px; }
        h1, h2 { color: #333; }
        h1 { border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .details-box { border: 1px solid #ddd; padding: 15px; border-radius: 5px; background-color: #f9f9f9; }
        @media print {
            .no-print { display: none; }
            body { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="text-center mb-4">
            <h1>Catalogue de Formation</h1>
        </div>

        <div class="details-box mb-4">
            <h2 class="h4"><?= htmlspecialchars($formation['title']) ?></h2>
            <hr>
            <dl class="row">
                <dt class="col-sm-3">Code :</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($formation['reference_number']) ?></dd>

                <dt class="col-sm-3">Période :</dt>
                <dd class="col-sm-9">Du <?= formatDate($formation['start_date']) ?> au <?= formatDate($formation['end_date']) ?></dd>

                <dt class="col-sm-3">Formateur :</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($formation['trainer_name']) ?></dd>

                <dt class="col-sm-3">Sujet :</dt>
                <dd class="col-sm-9"><?= nl2br(htmlspecialchars($formation['subject'])) ?></dd>
            </dl>
        </div>

        <h2 class="h4">Liste des Participants (<?= count($participants) ?>)</h2>
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th>Nom Complet</th>
                    <th>Poste</th>
                    <th>Département</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($participants as $participant): ?>
                    <tr>
                        <td><?= htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']) ?></td>
                        <td><?= htmlspecialchars($participant['position']) ?></td>
                        <td><?= htmlspecialchars($participant['department']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-center text-muted mt-4 no-print">
            <button onclick="window.print()" class="btn btn-primary">Imprimer</button>
        </p>
    </div>
    <script>
        // Optional: Automatically trigger print dialog
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html>