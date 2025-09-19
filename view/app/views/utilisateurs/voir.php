<?php $user = $data['utilisateur']; ?>
<h2>Détails pour : <?php echo htmlspecialchars($user['nom_complet']); ?></h2>

<div class="details-grid" style="display: grid; grid-template-columns: 1fr; gap: 2rem; margin: 1rem 0;">
    <div class="details-box" style="background: #f9f9f9; padding: 1rem; border: 1px solid #eee;">
        <h3>Informations Utilisateur</h3>
        <p><strong>Nom Complet:</strong> <?php echo htmlspecialchars($user['nom_complet']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        <p><strong>Rôle:</strong> <?php echo htmlspecialchars($data['nom_role']); ?></p>
        <p><strong>Statut:</strong> <?php echo $user['est_actif'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>'; ?></p>
    </div>
</div>

<h3 style="margin-top: 2rem;">Dernières Activités</h3>
<table class="table">
    <thead><tr><th>Date</th><th>Niveau</th><th>Action</th></tr></thead>
    <tbody>
        <?php foreach($data['logs'] as $log): ?>
        <tr>
            <td><?php echo date('d/m/Y H:i', strtotime($log['date_log'])); ?></td>
            <td><span class="badge bg-<?php echo strtolower($log['niveau']); ?>"><?php echo $log['niveau']; ?></span></td>
            <td><?php echo htmlspecialchars($log['action']); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div style="margin-top: 2rem;">
    <a href="/utilisateurs" class="btn btn-secondary">Retour à la liste</a>
    <a href="/utilisateurs/modifier/<?php echo $user['id']; ?>" class="btn btn-warning">Modifier</a>
</div>
<style>.badge{padding:.35em .65em;font-size:.75em;font-weight:700;color:#fff;border-radius:.25rem}.bg-success{background-color:#28a745}.bg-secondary{background-color:#6c757d}.bg-info{background-color:#17a2b8}.bg-warning{background-color:#ffc107;color:#212529!important}</style>