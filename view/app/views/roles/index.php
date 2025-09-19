<h2><?php echo $data['titre']; ?></h2>
<a href="/roles/creer" class="btn btn-primary" style="margin-bottom: 1rem; display: inline-block;">+ Nouveau Rôle</a>

<table class="table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nom du Rôle</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($data['roles'] as $role): ?>
        <tr>
            <td><?php echo $role['id']; ?></td>
            <td><?php echo htmlspecialchars($role['nom_role']); ?></td>
            <td>
                <a href="/roles/voir/<?php echo $role['id']; ?>" class="btn btn-info">Voir</a>
                <a href="/roles/modifier/<?php echo $role['id']; ?>" class="btn btn-warning">Modifier</a>
                <?php if ($role['id'] > 5): // Empêche la suppression des 5 rôles initiaux ?>
                    <form action="/roles/supprimer/<?php echo $role['id']; ?>" method="post" style="display:inline;">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Attention ! Supprimer ce rôle le retirera de tous les utilisateurs assignés. Êtes-vous sûr ?');">Supprimer</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>