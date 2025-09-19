<h2><?php echo $data['titre']; ?> pour : <?php echo htmlspecialchars($data['article']['designation']); ?></h2>
<p>Stock actuel : <strong><?php echo $data['article']['stock_actuel']; ?></strong></p>

<form action="/stock/entree/<?php echo $data['article']['id']; ?>" method="post" style="max-width: 400px;">
    <div style="margin-bottom: 1rem;">
        <label>Quantité à ajouter</label>
        <input type="number" name="quantite" min="1" required style="width: 100%; padding: 8px;">
    </div>
    <div style="margin-bottom: 1rem;">
        <label>Notes (Ex: "Livraison Fournisseur X", "Retour d'un client")</label>
        <input type="text" name="notes" style="width: 100%; padding: 8px;">
    </div>
    <button type="submit" class="btn btn-primary">Enregistrer l'entrée</button>
</form>