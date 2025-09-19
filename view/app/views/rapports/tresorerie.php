<?php 
$resume = $data['resume'];
$total_encaisse = $resume['Especes'] + $resume['TPE'] + $resume['Virement_Paye'];
$total_attente = $resume['Virement_Attente'];
?>

<h2><?php echo $data['titre']; ?></h2>

<form method="get" action="/rapports/tresorerie" style="margin-bottom: 2rem; background: #f4f4f4; padding: 1rem; border-radius: 5px;">
    <label for="date_debut">Du:</label>
    <input type="date" name="date_debut" value="<?php echo $data['date_debut']; ?>">
    <label for="date_fin" style="margin-left: 1rem;">Au:</label>
    <input type="date" name="date_fin" value="<?php echo $data['date_fin']; ?>">
    <button type="submit" class="btn btn-primary" style="margin-left: 1rem;">Filtrer</button>
</form>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; text-align: center; margin-bottom: 2rem;">
    <div style="background: #d4edda; padding: 1rem; border-radius: 5px;">
        <h3>Total Encaissé</h3>
        <p style="font-size: 2rem; font-weight: bold; margin: 0; color: #155724;">
            <?php echo number_format($total_encaisse, 2, ',', ' '); ?> DA
        </p>
    </div>
    <div style="background: #fff3cd; padding: 1rem; border-radius: 5px;">
        <h3>Total en Attente</h3>
        <p style="font-size: 2rem; font-weight: bold; margin: 0; color: #856404;">
            <?php echo number_format($total_attente, 2, ',', ' '); ?> DA
        </p>
    </div>
</div>

<h3>Détails par Méthode de Paiement</h3>
<table class="table">
    <thead class="thead-light">
        <tr>
            <th>Méthode de Paiement</th>
            <th>Statut</th>
            <th>Montant Total</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Espèces</td>
            <td>Payé</td>
            <td><strong><?php echo number_format($resume['Especes'], 2, ',', ' '); ?> DA</strong></td>
        </tr>
        <tr>
            <td>TPE (Carte Bancaire)</td>
            <td>Payé</td>
            <td><strong><?php echo number_format($resume['TPE'], 2, ',', ' '); ?> DA</strong></td>
        </tr>
        <tr>
            <td>Virement / Chèque</td>
            <td>Payé (Encaissé)</td>
            <td><strong><?php echo number_format($resume['Virement_Paye'], 2, ',', ' '); ?> DA</strong></td>
        </tr>
        <tr style="background-color: #fff3cd;">
            <td>Virement / Chèque</td>
            <td>En attente</td>
            <td><strong><?php echo number_format($resume['Virement_Attente'], 2, ',', ' '); ?> DA</strong></td>
        </tr>
    </tbody>
</table>