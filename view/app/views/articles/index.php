<style>
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { border: 1px solid #ddd; padding: 8px; }
    .table th { background-color: #f2f2f2; text-align: left; }
    .btn { padding: 5px 10px; color: #fff; text-decoration: none; border-radius: 3px; }
    .btn-primary { background-color: #007bff; }
    .btn-warning { background-color: #ffc107; }
    .btn-danger { background-color: #dc3545; }
</style>

<h2><?php echo $data['titre']; ?></h2>
<a href="/articles/creer" class="btn btn-primary" style="margin-bottom: 1rem; display: inline-block;">+ Créer un nouvel article</a>

<table class="table">
    <thead>
        <tr>
            <th>Référence</th>
            <th>Désignation</th>
            <th>Prix Vente HT</th>
            <th>Stock</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($data['articles'] as $article): ?>
        <tr>
            <td><?php echo htmlspecialchars($article['reference']); ?></td>
            <td><?php echo htmlspecialchars($article['designation']); ?></td>
            <td><?php echo number_format($article['prix_vente_ht'], 2, ',', ' '); ?> DA</td>
            <td><?php echo $article['stock_actuel']; ?></td>
           <td>
            <a href="/articles/voir/<?php echo $article['id']; ?>" class="btn btn-info" title="Voir Détails">Voir</a>
    <a href="/stock/entree/<?php echo $article['id']; ?>" class="btn btn-primary" title="Entrée de stock">+</a>
    <a href="/stock/historique/<?php echo $article['id']; ?>" class="btn" style="background-color:#5bc0de;" title="Historique">H</a>
    <a href="/articles/modifier/<?php echo $article['id']; ?>" class="btn btn-warning">Modifier</a>
    <a href="/articles/supprimer/<?php echo $article['id']; ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?');">Supprimer</a>
</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>