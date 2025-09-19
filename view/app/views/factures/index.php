<style>
    /* Vous pouvez réutiliser les styles de la liste d'articles */
    .table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    .table th, .table td { border: 1px solid #ddd; padding: 8px; }
    .table th { background-color: #f2f2f2; text-align: left; }
    .btn { padding: 5px 10px; color: #fff; text-decoration: none; border-radius: 3px; }
    .btn-primary { background-color: #007bff; }
    .btn-info { background-color: #17a2b8; }
</style>

<h2><?php echo $data['titre']; ?></h2>
<a href="/factures/creer" class="btn btn-primary" style="margin-bottom: 1rem; display: inline-block;">+ Créer une nouvelle facture</a>

<table class="table">
    <thead>
        <tr>
            <th>Numéro</th>
            <th>Client</th>
            <th>Date</th>
            <th>Montant TTC</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($data['factures'] as $facture): ?>
        <tr>
            <td><?php echo htmlspecialchars($facture['numero_facture']); ?></td>
            <td>
                <?php 
                    // Affiche le nom du client, ou "Vente au comptoir" si c'est une vente anonyme
                    echo htmlspecialchars($facture['nom_raison_sociale'] ?? 'Vente au comptoir'); 
                ?>
            </td>
            <td><?php echo date('d/m/Y', strtotime($facture['date_facturation'])); ?></td>
            <td><strong><?php echo number_format($facture['montant_ttc'], 2, ',', ' '); ?> DA</strong></td>
            <td>
    <span class="badge bg-<?php echo ($facture['statut_paiement'] == 'Payé') ? 'success' : 'warning'; ?>">
        <?php echo htmlspecialchars($facture['statut_paiement']); ?>
    </span>
</td>
            <td>
                <?php if($facture['statut_paiement'] == 'En attente'): ?>
    <form action="/factures/marquerEncaisse/<?php echo $facture['id']; ?>" method="post" style="display:inline;">
        <button type="submit" class="btn btn-success" title="Marquer comme Encaissé">✓</button>
    </form>
<?php endif; ?>
    <a href="/factures/voir/<?php echo $facture['id']; ?>" class="btn btn-info">Voir les Détails</a>
</td>
            <td>
                <a href="/factures/imprimer/<?php echo $facture['id']; ?>" class="btn btn-info" target="_blank">Voir / Imprimer</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>