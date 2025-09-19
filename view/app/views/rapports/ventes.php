<h2><?php echo $data['titre']; ?></h2>

<form method="get" action="/rapports/ventes" style="margin-bottom: 2rem; background: #f4f4f4; padding: 1rem;">
    <label for="date_debut">Du:</label>
    <input type="date" name="date_debut" value="<?php echo $data['date_debut']; ?>">
    <label for="date_fin">Au:</label>
    <input type="date" name="date_fin" value="<?php echo $data['date_fin']; ?>">
    <button type="submit" class="btn btn-primary">Filtrer</button>
</form>

<?php
$total_ca = 0;
$total_cout = 0;
$total_benefice = 0;
foreach($data['lignes_ventes'] as $ligne) {
    $total_ca += $ligne['chiffre_affaires_ligne'];
    $total_cout += $ligne['cout_total_ligne'];
    $total_benefice += $ligne['benefice_ligne'];
}
?>

<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; text-align: center; margin-bottom: 2rem;">
    <div style="background: #d4edda; padding: 1rem;"><h3>Chiffre d'Affaires</h3><p style="font-size: 1.5rem;"><?php echo number_format($total_ca, 2, ',', ' '); ?> DA</p></div>
    <div style="background: #f8d7da; padding: 1rem;"><h3>Coût des Marchandises</h3><p style="font-size: 1.5rem;"><?php echo number_format($total_cout, 2, ',', ' '); ?> DA</p></div>
    <div style="background: #cce5ff; padding: 1rem;"><h3>Bénéfice Brut</h3><p style="font-size: 1.5rem;"><?php echo number_format($total_benefice, 2, ',', ' '); ?> DA</p></div>
</div>


<table class="table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Article</th>
            <th>Qté</th>
            <th>CA Ligne</th>
            <th>Coût Ligne</th>
            <th>Bénéfice Ligne</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($data['lignes_ventes'] as $ligne): ?>
        <tr>
            <td><?php echo date('d/m/Y', strtotime($ligne['date_facturation'])); ?></td>
            <td><?php echo htmlspecialchars($ligne['designation']); ?></td>
            <td><?php echo $ligne['quantite']; ?></td>
            <td><?php echo number_format($ligne['chiffre_affaires_ligne'], 2, ',', ' '); ?></td>
            <td><?php echo number_format($ligne['cout_total_ligne'], 2, ',', ' '); ?></td>
            <td><strong><?php echo number_format($ligne['benefice_ligne'], 2, ',', ' '); ?></strong></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>