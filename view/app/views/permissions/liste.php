<h2><?php echo $data['titre']; ?></h2>
<a href="/permissions/creer" class="btn btn-primary" style="margin-bottom: 1rem; display: inline-block;">+ Nouvelle Permission</a>

<table class="table">
    <thead>
        <tr>
            <th>Module</th>
            <th>Description</th>
            <th>Slug (pour le code)</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($data['permissions'] as $perm): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($perm['module']); ?></strong></td>
            <td><?php echo htmlspecialchars($perm['description']); ?></td>
            <td><code><?php echo htmlspecialchars($perm['slug']); ?></code></td>
            <td>
                <a href="/permissions/modifier/<?php echo $perm['id']; ?>" class="btn btn-warning">Modifier</a>
                <form action="/permissions/supprimer/<?php echo $perm['id']; ?>" method="post" style="display:inline;">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette permission ?');">Supprimer</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>