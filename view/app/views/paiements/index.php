<h2><?php echo $data['titre']; ?></h2>
<p>Liste des factures en attente de paiement (Crédits, Virements, Paiements à la livraison).</p>
<table class="table">
    <thead><tr><th>N° Facture</th><th>Client</th><th>Date</th><th>Montant TTC</th><th>Méthode Initiale</th><th>Statut Livraison</th><th>Action</th></tr></thead>
    <tbody>
        <?php foreach($data['factures_en_attente'] as $facture): ?>
        <tr>
            <td><?php echo htmlspecialchars($facture['numero_facture']); ?></td>
            <td><?php echo htmlspecialchars($facture['nom_raison_sociale'] ?? 'N/A'); ?></td>
            <td><?php echo date('d/m/Y', strtotime($facture['date_facturation'])); ?></td>
            <td><strong><?php echo number_format($facture['montant_ttc'], 2, ',', ' '); ?> DA</strong></td>
            <td><?php echo htmlspecialchars($facture['methode_paiement']); ?></td>
            <td><span class="badge"><?php echo htmlspecialchars($facture['statut_livraison']); ?></span></td>
            <td><a href="/paiements/encaisser/<?php echo $facture['id']; ?>" class="btn btn-success">Encaisser</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>