<h2><?php echo $data['titre']; ?></h2>
<?php
// Analyse des données
$total_livraisons = count($data['livraisons']);
$valeur_totale = 0;
$livraisons_par_livreur = [];
foreach($data['livraisons'] as $bl) {
    $valeur_totale += $bl['valeur_facture'];
    $livreur = $bl['livreur_nom'] ?? 'Non Assigné';
    if (!isset($livraisons_par_livreur[$livreur])) {
        $livraisons_par_livreur[$livreur] = 0;
    }
    $livraisons_par_livreur[$livreur]++;
}
?>
<div class="module-section">
    <h3>Détail des Livraisons sur la Période</h3>
    <table class="table"></table>
</div>
<div class="module-section">
    <h3>Analyse par Livreur</h3>
    <table class="table">
        <thead><tr><th>Livreur</th><th>Nombre de Livraisons Effectuées</th></tr></thead>
        <tbody>
            <?php foreach($livraisons_par_livreur as $nom => $nombre): ?>
            <tr><td><?php echo htmlspecialchars($nom); ?></td><td><?php echo $nombre; ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>