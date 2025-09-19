<h2><?php echo $data['titre']; ?></h2>
<p>Stock actuel : <strong><?php echo $data['article']['stock_actuel']; ?></strong></p>
<a href="/articles" class="btn btn-primary">Retour à la liste des articles</a>

<table class="table" style="margin-top: 1rem;">
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
        <tr style="background-color: <?php echo strpos($mvt['type_mouvement'], 'entree') !== false || strpos($mvt['type_mouvement'], 'retour_client') !== false ? '#d4edda' : '#f8d7da'; ?>;">
            <td><?php echo date('d/m/Y H:i', strtotime($mvt['date_mouvement'])); ?></td>
            <td><?php echo htmlspecialchars($mvt['type_mouvement']); ?></td>
            <td><?php echo $mvt['quantite']; ?></td>
            <td><?php echo htmlspecialchars($mvt['utilisateur_nom']); ?></td>
            <td><?php echo htmlspecialchars($mvt['notes']); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>