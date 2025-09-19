<?php 
$avoir = $data['avoir'];
$lignes = $data['lignes'];
?>

<h2><?php echo $data['titre']; ?></h2>

<div class="details-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin: 1rem 0;">
    <div class="details-box" style="background: #f9f9f9; padding: 1rem; border: 1px solid #eee;">
        <h3>Informations Générales</h3>
        <p><strong>N° Avoir :</strong> <?php echo htmlspecialchars($avoir['numero_avoir']); ?></p>
        <p><strong>Date :</strong> <?php echo date('d/m/Y', strtotime($avoir['date_avoir'])); ?></p>
        <p><strong>Facture d'origine :</strong> <a href="/factures/voir/<?php echo $avoir['facture_originale_id']; ?>"><?php echo htmlspecialchars($avoir['num_fact_orig']); ?></a></p>
        <p><strong>Créé par :</strong> <?php echo htmlspecialchars($avoir['utilisateur_nom']); ?></p>
    </div>
    <div class="details-box" style="background: #f9f9f9; padding: 1rem; border: 1px solid #eee;">
        <h3>Informations Client</h3>
        <p><strong>Client :</strong> <?php echo htmlspecialchars($avoir['client_nom'] ?? 'N/A'); ?></p>
        <p><strong>Notes sur le retour :</strong> <?php echo nl2br(htmlspecialchars($avoir['notes'])); ?></p>
    </div>
</div>

<h3 style="margin-top: 2rem;">Articles Retournés</h3>
<table class="table">
    <thead>
        <tr>
            <th>Désignation</th>
            <th class="text-center">Qté Retournée</th>
            <th>Prix U. HT</th>
            <th>Total Ligne HT</th>
            <th class="text-center">Remis en stock</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($lignes as $ligne): ?>
        <tr>
            <td><?php echo htmlspecialchars($ligne['designation']); ?></td>
            <td class="text-center"><?php echo $ligne['quantite_retournee']; ?></td>
            <td><?php echo number_format($ligne['prix_unitaire_ht'], 2, ',', ' '); ?> DA</td>
            <td><?php echo number_format($ligne['prix_unitaire_ht'] * $ligne['quantite_retournee'], 2, ',', ' '); ?> DA</td>
            <td class="text-center"><?php echo $ligne['remis_en_stock'] ? 'Oui' : 'Non'; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div style="text-align: right; font-size: 1.2rem; margin-top: 1rem;">
    <p>Total HT : <?php echo number_format($avoir['montant_ht'], 2, ',', ' '); ?> DA</p>
    <p>Total TVA : <?php echo number_format($avoir['montant_tva'], 2, ',', ' '); ?> DA</p>
    <h3>Montant Total de l'Avoir : <?php echo number_format($avoir['montant_ttc'], 2, ',', ' '); ?> DA</h3>
</div>

<div style="margin-top: 2rem;">
    <a href="/avoirs" class="btn btn-secondary">Retour à la liste des avoirs</a>
    </div>