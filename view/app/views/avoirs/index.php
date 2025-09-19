<h2><?php echo $data['titre']; ?></h2>
<table class="table">
    <thead>
        <tr>
            <th>N° Avoir</th>
            <th>Facture d'Origine</th>
            <th>Client</th>
            <th>Date</th>
            <th>Montant TTC</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($data['avoirs'] as $avoir): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($avoir['numero_avoir']); ?></strong></td>
            <td><a href="/factures/voir/<?php echo $avoir['facture_originale_id']; ?>"><?php echo htmlspecialchars($avoir['num_fact_orig']); ?></a></td>
            <td><?php echo htmlspecialchars($avoir['client_nom']); ?></td>
            <td><?php echo date('d/m/Y', strtotime($avoir['date_avoir'])); ?></td>
            <td><?php echo number_format($avoir['montant_ttc'], 2, ',', ' '); ?> DA</td>
            <td>
    <a href="/avoirs/voir/<?php echo $avoir['id']; ?>" class="btn btn-info">Voir Détails</a>
</td>
            <td>
                <a href="#" class="btn btn-info">Voir / Imprimer Avoir</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>