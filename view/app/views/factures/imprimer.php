<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo $data['titre']; ?></title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, .15); }
        .header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .company-details, .client-details { width: 48%; }
        .invoice-details { text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .totals { margin-top: 20px; text-align: right; }
        @media print {
            body, .invoice-box { margin: 0; border: 0; box-shadow: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="header">
            <div class="company-details">
                <strong>VOTRE ENTREPRISE</strong><br>
                Votre Adresse, Ville<br>
                NIF: 1234567890 | RC: 9876543210<br>
                Tél: 0123 456 789
            </div>
            <div class="client-details">
                <strong>Client :</strong><br>
                <?php echo htmlspecialchars($data['facture']['nom_raison_sociale']); ?><br>
                <?php echo htmlspecialchars($data['facture']['adresse']); ?><br>
                NIF: <?php echo htmlspecialchars($data['facture']['nif']); ?> | RC: <?php echo htmlspecialchars($data['facture']['rc']); ?>
            </div>
        </div>
        <div class="invoice-details">
            <h3>Facture N°: <?php echo htmlspecialchars($data['facture']['numero_facture']); ?></h3>
            Date: <?php echo date('d/m/Y', strtotime($data['facture']['date_facturation'])); ?>
        </div>
        <hr>
        <table>
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th>Qté</th>
                    <th>P.U. HT</th>
                    <th>Total HT</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($data['lignes'] as $ligne): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ligne['designation']); ?></td>
                    <td><?php echo $ligne['quantite']; ?></td>
                    <td><?php echo number_format($ligne['prix_unitaire_ht'], 2, ',', ' '); ?></td>
                    <td><?php echo number_format($ligne['prix_unitaire_ht'] * $ligne['quantite'], 2, ',', ' '); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="totals">
            <p>Total HT: <?php echo number_format($data['facture']['montant_ht'], 2, ',', ' '); ?> DA</p>
            <p>TVA (19%): <?php echo number_format($data['facture']['montant_tva'], 2, ',', ' '); ?> DA</p>
            <p>Timbre Fiscal: <?php echo number_format($data['facture']['montant_timbre'], 2, ',', ' '); ?> DA</p>
            <hr>
            <h3>Total TTC: <?php echo number_format($data['facture']['montant_ttc'], 2, ',', ' '); ?> DA</h3>
        </div>
        <hr>
        <p>Arrêtée la présente facture à la somme de : ... (Montant en lettres à ajouter ici)</p>
        <div class="no-print" style="text-align:center; margin-top: 20px;">
             <button onclick="window.print();" class="btn btn-primary">Imprimer</button>
        </div>
    </div>
</body>
</html>