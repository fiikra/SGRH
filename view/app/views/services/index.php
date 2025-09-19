<h2><?php echo $data['titre']; ?></h2>
<a href="/services/creer" class="btn btn-primary" style="margin-bottom: 1rem;">+ Nouveau Bon de Réception</a>
<table class="table">
    <thead><tr><th>N° Bon</th><th>Client</th><th>Appareil</th><th>Date Réception</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody>
        <?php foreach($data['reparations'] as $rep): ?>
        <tr>
            <td><?php echo htmlspecialchars($rep['numero_bon']); ?></td>
            <td><?php echo htmlspecialchars($rep['nom_client']); ?></td>
            <td><?php echo htmlspecialchars($rep['type_appareil']); ?></td>
            <td><?php echo date('d/m/Y', strtotime($rep['date_reception'])); ?></td>
            <td><span class="badge"><?php echo htmlspecialchars($rep['statut']); ?></span></td>
            <td><a href="/services/voir/<?php echo $rep['id']; ?>" class="btn btn-info">Gérer</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>