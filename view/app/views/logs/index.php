<h2><?php echo $data['titre']; ?></h2>
<p>Affichage des 200 dernières opérations enregistrées.</p>

<table class="table">
    <thead>
        <tr>
            <th style="width:150px;">Date</th>
            <th style="width:150px;">Utilisateur</th>
            <th style="width:100px;">Niveau</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($data['logs'] as $log): ?>
        <tr>
            <td><?php echo date('d/m/Y H:i:s', strtotime($log['date_log'])); ?></td>
            <td><?php echo htmlspecialchars($log['utilisateur_nom']); ?></td>
            <td>
                <span class="badge bg-<?php echo strtolower($log['niveau']); ?>">
                    <?php echo htmlspecialchars($log['niveau']); ?>
                </span>
            </td>
            <td><?php echo htmlspecialchars($log['action']); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<style>.badge{padding:.35em .65em;font-size:.75em;font-weight:700;color:#fff;border-radius:.25rem}.bg-info{background-color:#17a2b8}.bg-warning{background-color:#ffc107;color:#212529!important}.bg-critical{background-color:#dc3545}</style>