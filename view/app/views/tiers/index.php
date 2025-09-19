<h2><?php echo $data['titre']; ?></h2>
<a href="/tiers/creer" class="btn btn-primary" style="margin-bottom: 1rem; display: inline-block;">+ Créer un nouveau Tiers</a>

<table class="table">
    <thead>
        <tr>
            <th>Nom / Raison Sociale</th>
            <th>Type</th>
            <th>Téléphone</th>
            <th>NIF</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($data['tiers'] as $t): ?>
        <tr>
            <td><?php echo htmlspecialchars($t['nom_raison_sociale']); ?></td>
            <td><span class="badge <?php echo $t['type'] == 'Client' ? 'bg-success' : 'bg-warning'; ?>"><?php echo $t['type']; ?></span></td>
            <td><?php echo htmlspecialchars($t['telephone']); ?></td>
            <td><?php echo htmlspecialchars($t['nif']); ?></td>
            <td>
                <a href="/tiers/modifier/<?php echo $t['id']; ?>" class="btn btn-warning">Modifier</a>
                <form action="/tiers/supprimer/<?php echo $t['id']; ?>" method="post" style="display:inline;">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr ? Ce tiers pourrait être lié à des factures.');">Supprimer</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<style>.badge{padding: .35em .65em; font-size: .75em; font-weight: 700; color: #fff; border-radius: .25rem;} .bg-success{background-color: #28a745;} .bg-warning{background-color: #ffc107; color: #212529 !important;}</style>