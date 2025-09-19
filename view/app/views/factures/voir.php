<?php
$facture = $data['facture'];
$lignes = $data['lignes'];
?>

<style>
    .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1rem; }
    .details-box { background: #f9f9f9; padding: 1rem; border: 1px solid #eee; }
    .details-box h3 { margin-top: 0; }
    .totals-summary { text-align: right; }
</style>

<h2><?php echo $data['titre']; ?></h2>

<div class="details-grid">
    <div class="details-box">
        <h3>Informations Client</h3>
        <?php if ($facture['client_id']): ?>
            <p><strong>Nom:</strong> <?php echo htmlspecialchars($facture['nom_raison_sociale']); ?></p>
            <p><strong>Adresse:</strong> <?php echo htmlspecialchars($facture['adresse']); ?></p>
            <p><strong>NIF:</strong> <?php echo htmlspecialchars($facture['nif']); ?></p>
        <?php else: ?>
            <p>Vente au comptoir (Client Anonyme)</p>
        <?php endif; ?>
    </div>
    <div class="details-box">
        <h3>Informations Facture</h3>
        <p><strong>Numéro:</strong> <?php echo htmlspecialchars($facture['numero_facture']); ?></p>
        <p><strong>Date:</strong> <?php echo date('d/m/Y H:i', strtotime($facture['date_facturation'])); ?></p>
        <p><strong>Méthode de Paiement:</strong> <?php echo htmlspecialchars($facture['methode_paiement']); ?></p>
    </div>
</div>

<h3 style="margin-top: 2rem;">Articles Facturés</h3>
<table class="table">
    <thead>
        <tr>
            <th>Désignation</th>
            <th>Qté</th>
            <th>P.U. HT</th>
            <th>Taux TVA</th>
            <th>Total Ligne HT</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($lignes as $ligne): ?>
        <tr>
            <td><?php echo htmlspecialchars($ligne['designation']); ?></td>
            <td><?php echo $ligne['quantite']; ?></td>
            <td><?php echo number_format($ligne['prix_unitaire_ht'], 2, ',', ' '); ?> DA</td>
            <td><?php echo ($ligne['taux_tva'] * 100); ?> %</td>
            <td><strong><?php echo number_format($ligne['prix_unitaire_ht'] * $ligne['quantite'], 2, ',', ' '); ?> DA</strong></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="totals-summary">
    <p>Total HT: <?php echo number_format($facture['montant_ht'], 2, ',', ' '); ?> DA</p>
    <p>Montant TVA: <?php echo number_format($facture['montant_tva'], 2, ',', ' '); ?> DA</p>
    <p>Timbre Fiscal: <?php echo number_format($facture['montant_timbre'], 2, ',', ' '); ?> DA</p>
    <hr>
    <h3>Total TTC: <?php echo number_format($facture['montant_ttc'], 2, ',', ' '); ?> DA</h3>
</div>

<div style="margin-top: 2rem;">
    <a href="/factures" class="btn btn-secondary">Retour à la liste</a>
    <div style="margin-top: 2rem;">
    <?php if (Auth::can('avoirs_creer') && $facture['statut_paiement'] != 'En attente'): ?>
        <a href="/avoirs/creer/<?php echo $facture['id']; ?>" class="btn btn-danger">Générer Avoir / Retour Client</a>
    <?php endif; ?>
</div>
    <a href="/factures/imprimer/<?php echo $facture['id']; ?>" class="btn btn-primary" target="_blank">Imprimer la Facture</a>
</div>