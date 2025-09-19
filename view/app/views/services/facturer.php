<?php $reparation = $data['reparation']; ?>
<h2><?php echo $data['titre']; ?></h2>

<div style="background: #e9ecef; padding: 1rem; margin-bottom: 1rem; border-radius: 5px;">
    <strong>Client:</strong> <?php echo htmlspecialchars($reparation['nom_client']); ?><br>
    <strong>Téléphone:</strong> <?php echo htmlspecialchars($reparation['telephone_client']); ?><br>
    <strong>Appareil:</strong> <?php echo htmlspecialchars($reparation['type_appareil']); ?>
</div>

<form id="form-facture-service" action="/services/enregistrerFactureService/<?php echo $reparation['id']; ?>" method="post">
    
    <fieldset style="margin-bottom:1rem;">
        <legend>Ajouter Pièces / Main d'œuvre</legend>
        <select id="article_search" style="width: 300px;">
            <option value="">-- Sélectionner un article ou un service --</option>
            <?php foreach ($data['articles'] as $article): ?>
                <option 
                    value="<?php echo $article['id']; ?>" 
                    data-prix="<?php echo $article['prix_vente_ht']; ?>" 
                    data-cout="<?php echo $article['prix_achat_ht']; ?>"
                    data-tva="<?php echo $article['tva_taux']; ?>">
                    <?php echo htmlspecialchars($article['designation']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label>Qté :</label> <input type="number" id="quantite" value="1" min="1" style="width: 60px;">
        <button type="button" id="btn-ajouter-ligne" class="btn btn-primary">Ajouter</button>
    </fieldset>

    <h3>Détails de la Facture</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Désignation</th>
                <th>Qté</th>
                <th>Prix U. HT</th>
                <th>Total HT</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="lignes_facture"></tbody>
    </table>
    
    <div class="total-summary" style="text-align: right; font-size: 1.2rem;">
        <p>Total HT : <span id="total_ht">0.00</span> DA</p>
        <p>Total TVA (19%) : <span id="total_tva">0.00</span> DA</p>
        <p>Timbre Fiscal : <span id="total_timbre">0.00</span> DA</p>
        <h3>Total TTC : <span id="total_ttc">0.00</span> DA</h3>
    </div>

    <hr>
    <button type="submit" class="btn btn-success" style="font-size: 1.2rem;">Enregistrer et Clôturer</button>
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    let ligneIndex = 0;
    $('#btn-ajouter-ligne').on('click', function() {
        const selectedOption = $('#article_search').find('option:selected');
        const articleId = selectedOption.val();
        if (!articleId) { alert('Veuillez sélectionner un article.'); return; }

        const quantite = parseInt($('#quantite').val());
        const articleDesignation = selectedOption.text();
        const articlePrix = parseFloat(selectedOption.data('prix'));
        const articleCout = parseFloat(selectedOption.data('cout'));
        const articleTva = parseFloat(selectedOption.data('tva'));
        const totalLigneHT = quantite * articlePrix;
        
        const nouvelleLigne = `
            <tr>
                <td>${articleDesignation}
                    <input type="hidden" name="lignes[${ligneIndex}][article_id]" value="${articleId}">
                    <input type="hidden" name="lignes[${ligneIndex}][designation]" value="${articleDesignation}">
                    <input type="hidden" name="lignes[${ligneIndex}][prix_unitaire_ht]" value="${articlePrix}">
                    <input type="hidden" name="lignes[${ligneIndex}][cout_unitaire_ht]" value="${articleCout}">
                    <input type="hidden" name="lignes[${ligneIndex}][taux_tva]" value="${articleTva}">
                    <input type="hidden" name="lignes[${ligneIndex}][quantite]" value="${quantite}">
                </td>
                <td>${quantite}</td>
                <td>${articlePrix.toFixed(2)}</td>
                <td>${totalLigneHT.toFixed(2)}</td>
                <td><button type="button" class="btn-supprimer-ligne btn btn-danger">X</button></td>
            </tr>`;
        $('#lignes_facture').append(nouvelleLigne);
        ligneIndex++;
        calculerTotaux();
    });

    $('#lignes_facture').on('click', '.btn-supprimer-ligne', function() {
        $(this).closest('tr').remove();
        calculerTotaux();
    });
    
    function calculerTotaux() {
        let totalHT = 0, totalTVA = 0;
        $('#lignes_facture tr').each(function() {
            const prix = parseFloat($(this).find('input[name*="[prix_unitaire_ht]"]').val());
            const qte = parseInt($(this).find('input[name*="[quantite]"]').val());
            const tva = parseFloat($(this).find('input[name*="[taux_tva]"]').val());
            totalHT += prix * qte;
            totalTVA += (prix * qte) * tva;
        });
        const timbre = (totalHT + totalTVA > 0) ? 1.00 : 0;
        const totalTTC = totalHT + totalTVA + timbre;
        $('#total_ht').text(totalHT.toFixed(2));
        $('#total_tva').text(totalTVA.toFixed(2));
        $('#total_timbre').text(timbre.toFixed(2));
        $('#total_ttc').text(totalTTC.toFixed(2));
    }
});
</script>