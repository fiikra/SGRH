<?php $facture = $data['facture']; $lignes = $data['lignes']; ?>
<h2><?php echo $data['titre']; ?></h2>

<form action="/avoirs/enregistrer" method="post">
    <input type="hidden" name="facture_originale_id" value="<?php echo $facture['id']; ?>">
    
    <p>Sélectionnez les articles et les quantités à retourner. Cochez "Remis en stock" si le produit est revendable.</p>

    <table class="table">
        <thead>
            <tr>
                <th>Article</th>
                <th class="text-center">Qté Facturée</th>
                <th class="text-center" style="width: 150px;">Qté à Retourner</th>
                <th class="text-center" style="width: 150px;">Remis en stock ?</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($lignes as $index => $ligne): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($ligne['designation']); ?>
                        <input type="hidden" name="lignes[<?php echo $index; ?>][article_id]" value="<?php echo $ligne['article_id']; ?>">
                        <input type="hidden" name="lignes[<?php echo $index; ?>][designation]" value="<?php echo htmlspecialchars($ligne['designation']); ?>">
                        <input type="hidden" name="lignes[<?php echo $index; ?>][prix_unitaire_ht]" value="<?php echo $ligne['prix_unitaire_ht']; ?>">
                        <input type="hidden" name="lignes[<?php echo $index; ?>][taux_tva]" value="<?php echo $ligne['taux_tva']; ?>">
                    </td>
                    <td class="text-center"><?php echo $ligne['quantite']; ?></td>
                    <td class="text-center">
                        <input type="number" name="lignes[<?php echo $index; ?>][quantite_retournee]" value="0" min="0" max="<?php echo $ligne['quantite']; ?>" style="width: 80px;">
                    </td>
                    <td class="text-center">
                        <input type="checkbox" name="lignes[<?php echo $index; ?>][remis_en_stock]" value="1" checked>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="form-group">
        <label for="notes">Notes sur le retour</label>
        <textarea name="notes" id="notes" rows="3"></textarea>
    </div>

    <button type="submit" class="btn btn-success" onclick="return confirm('Confirmez-vous la création de cet avoir ? L\'opération impactera les stocks et le solde du client.');">Générer la Facture d'Avoir</button>
</form>