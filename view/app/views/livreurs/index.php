<h2><?php echo $data['titre']; ?></h2>
<a href="/livreurs/creer" class="btn btn-primary" style="margin-bottom: 1rem; display: inline-block;">+ Ajouter un Livreur</a>

<table class="table">
    <thead>
        <tr>
            <th>Nom Complet</th>
            <th>Téléphone</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($data['livreurs'] as $livreur): ?>
        <tr>
            <td><?php echo htmlspecialchars($livreur['nom_complet']); ?></td>
            <td><?php echo htmlspecialchars($livreur['telephone'] ?? 'N/A'); ?></td>
            <td>
                <?php if ($livreur['est_actif']): ?>
                    <span class="badge bg-success">Actif</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Inactif</span>
                <?php endif; ?>
            </td>
            <td>
                   <a href="/livreurs/voir/<?php echo $livreur['id']; ?>" class="btn btn-info">Voir</a>
                <a href="/livreurs/modifier/<?php echo $livreur['id']; ?>" class="btn btn-warning">Modifier</a>
           
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<style>
    .badge{padding: .35em .65em; font-size: .75em; font-weight: 700; color: #fff; border-radius: .25rem;} 
    .bg-success{background-color: #28a745;} 
    .bg-secondary{background-color: #6c757d;}
</style>