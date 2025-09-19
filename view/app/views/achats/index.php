<h2><?php echo $data['titre']; ?></h2>
<a href="/achats/creer" class="btn btn-primary" style="margin-bottom: 1rem; display: inline-block;">+ Nouvelle Commande</a>

<table class="table">
    <thead>
        <tr>
            <th>Numéro</th>
            <th>Fournisseur</th>
            <th>Date</th>
            <th>Montant HT</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($data['commandes'] as $cmd): ?>
        <tr>
            <td><?php echo htmlspecialchars($cmd['numero_commande']); ?></td>
            <td><?php echo htmlspecialchars($cmd['fournisseur_nom']); ?></td>
            <td><?php echo date('d/m/Y', strtotime($cmd['date_commande'])); ?></td>
            <td><?php echo number_format($cmd['montant_ht'], 2, ',', ' '); ?> DA</td>
            <td><span class="badge"><?php echo $cmd['statut']; ?></span></td>
            <td>
                <a href="/achats/voir/<?php echo $cmd['id']; ?>" class="btn btn-info">Voir</a>
                <?php if($cmd['statut'] == 'Commandé' || $cmd['statut'] == 'Reçu Partiellement'): ?>
                    <a href="/achats/recevoir/<?php echo $cmd['id']; ?>" class="btn btn-success">Recevoir</a>

                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<style>.badge{padding: .35em .65em; font-size: .75em; font-weight: 700; color: #fff; border-radius: .25rem; background-color: #6c757d;}</style>