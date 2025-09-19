<?php $article = $data['article']; ?>

<h2><?php echo htmlspecialchars($article['designation']); ?></h2>

<div class="details-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin: 1rem 0;">
    <div class="details-box" style="background: #f9f9f9; padding: 1rem; border: 1px solid #eee;">
        <h3>Informations Générales</h3>
        <p><strong>Référence:</strong> <?php echo htmlspecialchars($article['reference']); ?></p>
        <p><strong>Code-barres:</strong> <?php echo htmlspecialchars($article['code_barre'] ?? 'N/A'); ?></p>
        <p><strong>Notes:</strong> <?php echo htmlspecialchars($article['notes'] ?? 'Aucune'); ?></p>
    </div>
    <div class="details-box" style="background: #f9f9f9; padding: 1rem; border: 1px solid #eee;">
        <h3>Tarifs & Stock</h3>
        <p><strong>Prix d'Achat HT:</strong> <?php echo number_format($article['prix_achat_ht'], 2, ',', ' '); ?> DA</p>
        <p><strong>Prix de Vente HT:</strong> <?php echo number_format($article['prix_vente_ht'], 2, ',', ' '); ?> DA</p>
        <p><strong>Stock Actuel:</strong> <strong style="font-size: 1.5rem;"><?php echo $article['stock_actuel']; ?></strong> Unités</p>
        <p><strong>Seuil d'Alerte:</strong> <?php echo $article['stock_alerte']; ?> Unités</p>
    </div>
</div>

<h3 style="margin-top: 2rem;">Historique des Mouvements de Stock</h3>
<table class="table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Quantité</th>
            <th>Utilisateur</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($data['mouvements'] as $mvt): ?>
        <tr style="background-color: <?php echo strpos($mvt['type_mouvement'], 'entree') !== false ? '#d4edda' : '#f8d7da'; ?>;">
            <td><?php echo date('d/m/Y H:i', strtotime($mvt['date_mouvement'])); ?></td>
            <td><?php echo htmlspecialchars($mvt['type_mouvement']); ?></td>
            <td><strong><?php echo strpos($mvt['type_mouvement'], 'entree') !== false ? '+' : '-'; ?><?php echo $mvt['quantite']; ?></strong></td>
            <td><?php echo htmlspecialchars($mvt['utilisateur_nom']); ?></td>
            <td><?php echo htmlspecialchars($mvt['notes']); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div style="margin-top: 2rem;">
    <a href="/articles" class="btn btn-secondary">Retour à la liste</a>
    <a href="/articles/modifier/<?php echo $article['id']; ?>" class="btn btn-warning">Modifier</a>
</div>