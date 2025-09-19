<?php 
$livreur = $data['livreur']; 
$livraisons = $data['livraisons'];
?>

<h2><?php echo $data['titre']; ?></h2>

<div style="background: #f9f9f9; padding: 1.5rem; border-radius: 5px; margin-bottom: 2rem;">
    <h3>Informations</h3>
    <p><strong>Nom Complet :</strong> <?php echo htmlspecialchars($livreur['nom_complet']); ?></p>
    <p><strong>Téléphone :</strong> <?php echo htmlspecialchars($livreur['telephone'] ?? 'Non renseigné'); ?></p>
    <p><strong>Statut :</strong> 
        <?php if ($livreur['est_actif']): ?>
            <span class="badge bg-success">Actif</span>
        <?php else: ?>
            <span class="badge bg-secondary">Inactif</span>
        <?php endif; ?>
    </p>
</div>

<h3 style="margin-top: 2rem;">Historique et Analyse des Livraisons</h3>

<form method="get" action="/livreurs/voir/<?php echo $livreur['id']; ?>" class="filter-form">
    <label for="date_debut">Du:</label>
    <input type="date" name="date_debut" value="<?php echo $data['date_debut']; ?>">
    <label for="date_fin">Au:</label>
    <input type="date" name="date_fin" value="<?php echo $data['date_fin']; ?>">
    <button type="submit" class="btn btn-primary">Filtrer</button>
</form>

<?php
// Calcul des indicateurs
$total_valeur_livree = 0;
$nombre_livraisons = count($livraisons);
foreach($livraisons as $bl) {
    $total_valeur_livree += $bl['valeur_facture'];
}
?>

<div class="summary-cards">
    <div class="card">
        <h3>Nombre de Livraisons</h3>
        <p><?php echo $nombre_livraisons; ?></p>
    </div>
    <div class="card">
        <h3>Valeur Totale Livrée</h3>
        <p><?php echo number_format($total_valeur_livree, 2, ',', ' '); ?> DA</p>
    </div>
</div>
<table class="table">
    <thead>
        <tr>
            <th>N° BL</th>
            <th>Client</th>
            <th>Date</th>
            <th>Statut</th>
            <th>Valeur de la Commande</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($livraisons as $bl): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($bl['numero_bl']); ?></strong></td>
            <td><?php echo htmlspecialchars($bl['client_nom']); ?></td>
            <td><?php echo date('d/m/Y H:i', strtotime($bl['date_livraison'])); ?></td>
            <td><span class="badge"><?php echo htmlspecialchars($bl['statut']); ?></span></td>
            <td><?php echo number_format($bl['valeur_facture'], 2, ',', ' '); ?> DA</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>



<div style="margin-top: 2rem;">
    <a href="/livreurs" class="btn btn-secondary">Retour à la liste des livreurs</a>
</div>
<style>
.filter-form { margin-bottom: 2rem; background: #f4f4f4; padding: 1rem; border-radius: 5px; }
.summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
.card { background: #fff; padding: 1rem; border: 1px solid #eee; text-align: center; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.card h3 { margin-top: 0; font-size: 1rem; color: #6c757d; }
.card p { font-size: 1.8rem; font-weight: bold; margin: 0; color: #0056b3; }
</style>