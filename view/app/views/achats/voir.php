<?php
// Créer des variables pour un accès plus facile
$commande = $data['commande'];
$lignes = $data['lignes'];
?>

<style>
    .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1rem; }
    .details-box { background: #f9f9f9; padding: 1rem; border: 1px solid #eee; }
    .details-box h3 { margin-top: 0; }
</style>

<h2><?php echo $data['titre']; ?></h2>

<div class="details-grid">
    <div class="details-box">
        <h3>Informations Fournisseur</h3>
        <p><strong>Nom:</strong> <?php echo htmlspecialchars($commande['fournisseur_nom']); ?></p>
        </div>
    <div class="details-box">
        <h3>Informations Commande</h3>
        <p><strong>Numéro:</strong> <?php echo htmlspecialchars($commande['numero_commande']); ?></p>
        <p><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($commande['date_commande'])); ?></p>
        <p><strong>Statut:</strong> <span class="badge"><?php echo htmlspecialchars($commande['statut']); ?></span></p>
        <p><strong>Montant Total HT:</strong> <?php echo number_format($commande['montant_ht'], 2, ',', ' '); ?> DA</p>
    </div>
</div>

<h3 style="margin-top: 2rem;">Articles Commandés</h3>
<table class="table">
    <thead>
        <tr>
            <th>Désignation</th>
            <th>Prix d'Achat U. HT</th>
            <th>Quantité Commandée</th>
            <th>Quantité Reçue</th>
            <th>Quantité Restante</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($lignes as $ligne): ?>
        <tr>
            <td><?php echo htmlspecialchars($ligne['designation']); ?></td>
            <td><?php echo number_format($ligne['prix_achat_unitaire_ht'], 2, ',', ' '); ?> DA</td>
            <td><?php echo $ligne['quantite_commandee']; ?></td>
            <td><?php echo $ligne['quantite_recue']; ?></td>
            <td><strong><?php echo $ligne['quantite_commandee'] - $ligne['quantite_recue']; ?></strong></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div style="margin-top: 2rem;">
    <a href="/achats" class="btn btn-secondary">Retour à la liste</a>
    <?php if($commande['statut'] == 'Commandé' || $commande['statut'] == 'Reçu Partiellement'): ?>
        <a href="/achats/recevoir/<?php echo $commande['id']; ?>" class="btn btn-success">Recevoir la Marchandise</a>
    <?php endif; ?>
</div>

<style>.badge{padding: .35em .65em; font-size: .75em; font-weight: 700; color: #fff; border-radius: .25rem; background-color: #6c757d;}</style>