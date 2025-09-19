<h2><?php echo $data['titre']; ?></h2>
<a href="/utilisateurs/creer" class="btn btn-primary" style="margin-bottom: 1rem; display: inline-block;">+ Nouvel Utilisateur</a>

<table class="table">
    <thead>
        <tr>
            <th>Nom Complet</th>
            <th>Email</th>
            <th>Rôle</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($data['utilisateurs'] as $user): ?>
        <tr>
            <td><?php echo htmlspecialchars($user['nom_complet']); ?></td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td><?php echo htmlspecialchars($user['nom_role']); ?></td>
            <td><?php echo $user['est_actif'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>'; ?></td>
            <td>
                <a href="/utilisateurs/voir/<?php echo $user['id']; ?>" class="btn btn-warning">Voir</a>
                <a href="/utilisateurs/modifier/<?php echo $user['id']; ?>" class="btn btn-warning">Modifier</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<style>.badge{padding: .35em .65em; font-size: .75em; font-weight: 700; color: #fff; border-radius: .25rem;} .bg-success{background-color: #28a745;} .bg-secondary{background-color: #6c757d;}</style>