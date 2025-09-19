<?php 
$facture = $data['facture'];
$lignes = $data['lignes'];
?>

<h2><?php echo $data['titre']; ?></h2>

<div style="background: #f9f9f9; padding: 1rem; border: 1px solid #eee; margin-bottom: 2rem;">
    <h4>Récapitulatif de la Commande</h4>
    <p><strong>Client:</strong> <?php echo htmlspecialchars($facture['nom_raison_sociale']); ?></p>
    <p><strong>Type de Livraison:</strong> <?php echo htmlspecialchars($facture['type_livraison']); ?></p>
</div>

<form action="/livraisons/valider/<?php echo $facture['id']; ?>" method="post">
     <fieldset style="margin-bottom: 2rem;">
        <?php if ($facture['type_livraison'] == 'A Domicile'): ?>
             <legend>Assignation de la Livraison</legend>
        <div class="form-group" style="max-width: 400px;">
            <label for="livreur_id">Assigner à un livreur *</label>
            <select name="livreur_id" id="livreur_id" required>
                <option value="">-- Sélectionner un livreur disponible --</option>
                <?php foreach($data['livreurs'] as $livreur): ?>
                    <option value="<?php echo $livreur['id']; ?>"><?php echo htmlspecialchars($livreur['nom_complet']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </fieldset>
        <p><strong>Adresse:</strong> <?php echo nl2br(htmlspecialchars($facture['adresse_livraison'])); ?></p>
    <?php endif; ?>
       
    <h3>Articles à Préparer</h3>
    <p>Confirmez les quantités sortant du stock. Par défaut, toutes les quantités commandées sont sélectionnées.</p>

    <table class="table">
        <thead>
            <tr>
                <th>Article</th>
                <th class="text-center">Quantité Commandée</th>
                <th class="text-center" style="width: 150px;">Quantité à Livrer</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($lignes as $index => $ligne): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($ligne['designation']); ?>
                        <input type="hidden" name="lignes[<?php echo $index; ?>][article_id]" value="<?php echo $ligne['article_id']; ?>">
                        <input type="hidden" name="lignes[<?php echo $index; ?>][cout_unitaire_ht]" value="<?php echo $ligne['cout_unitaire_ht']; ?>">
                    </td>
                    <td class="text-center"><?php echo $ligne['quantite']; ?></td>
                    <td class="text-center">
                        <input type="number" name="lignes[<?php echo $index; ?>][quantite_livree]" 
                               value="<?php echo $ligne['quantite']; ?>" 
                               min="0" 
                               max="<?php echo $ligne['quantite']; ?>" 
                               style="width: 80px; text-align: center;" required>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="text-align: right; margin-top: 2rem;">
        <a href="/livraisons" class="btn btn-secondary">Annuler</a>
        <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Confirmez-vous la sortie de stock de ces articles ? Cette action est irréversible.');">
            Valider la Livraison et Mettre à Jour le Stock
        </button>
    </div>
</form>

<style>.text-center{text-align:center;}</style>