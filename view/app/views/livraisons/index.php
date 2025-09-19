<h2><?php echo $data['titre']; ?></h2>

<div class="module-section">
    <h3>Commandes à Préparer</h3>
    <table class="table">
        <thead><tr><th>N° Facture</th><th>Client</th><th>Date</th><th>Type</th><th>Actions</th></tr></thead>
        <tbody>
            <?php if (empty($data['factures_a_preparer'])): ?>
                <tr><td colspan="5" style="text-align:center;">Aucune commande en attente de préparation.</td></tr>
            <?php else: ?>
                <?php foreach($data['factures_a_preparer'] as $facture): ?>
                <tr>
                    <td><?php echo htmlspecialchars($facture['numero_facture']); ?></td>
                    <td><?php echo htmlspecialchars($facture['nom_raison_sociale']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($facture['date_facturation'])); ?></td>
                    <td><?php echo htmlspecialchars($facture['type_livraison']); ?></td>
                    <td><a href="/livraisons/preparer/<?php echo $facture['id']; ?>" class="btn btn-primary">Préparer le Bon de Livraison</a></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="module-section">
    <h3>Suivi des Bons de Livraison</h3>
    <table class="table">
        <thead><tr><th>N° BL</th><th>Facture Orig.</th><th>Client</th><th>Date</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
             <?php if (empty($data['bons_livraison'])): ?>
                <tr><td colspan="6" style="text-align:center;">Aucun bon de livraison créé.</td></tr>
            <?php else: ?>
                <?php foreach($data['bons_livraison'] as $bl): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($bl['numero_bl']); ?></strong></td>
                    <td><?php echo htmlspecialchars($bl['numero_facture']); ?></td>
                    <td><?php echo htmlspecialchars($bl['client_nom']); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($bl['date_livraison'])); ?></td>
                    <td><?php echo htmlspecialchars($bl['livreur_nom'] ?? 'Non assigné'); ?></td> <!-- On affiche le nom -->
               
                    <td><span class="badge"><?php echo htmlspecialchars($bl['statut']); ?></span></td>
                    <td>
                        <a href="#" class="btn btn-info">Voir / Imprimer</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>.module-section { background: #fff; padding: 1.5rem; border-radius: 5px; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }</style>