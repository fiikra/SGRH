<h2><?php echo $data['titre']; ?></h2>

<form id="form-creer-commande" action="/achats/enregistrer" method="post">
    <fieldset style="margin-bottom:1rem;">
        <legend>Fournisseur</legend>
        <select name="fournisseur_id" required>
            <option value="">-- Choisir un fournisseur --</option>
            <?php foreach ($data['fournisseurs'] as $fournisseur): ?>
                <option value="<?php echo $fournisseur['id']; ?>"><?php echo htmlspecialchars($fournisseur['nom_raison_sociale']); ?></option>
            <?php endforeach; ?>
        </select>
    </fieldset>
    
    <fieldset style="margin-bottom:1rem;">
        <legend>Ajouter un Article</legend>
        <select id="article_search" style="width: 300px;">
            <option value="">-- Sélectionner un article --</option>
            <?php foreach ($data['articles'] as $article): ?>
                <option value="<?php echo $article['id']; ?>" data-designation="<?php echo htmlspecialchars($article['designation']); ?>" data-prix="<?php echo $article['prix_achat_ht']; ?>">
                    <?php echo htmlspecialchars($article['designation']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label>Qté:</label> <input type="number" id="quantite" value="1" min="1" style="width: 60px;">
        <label>Prix Achat HT:</label> <input type="number" step="0.01" id="prix_achat" style="width: 80px;">
        <button type="button" id="btn-ajouter-ligne" class="btn btn-primary">Ajouter</button>
    </fieldset>

    <h3>Détails de la Commande</h3>
    <table class="table">
        <thead>
            <tr><th>Désignation</th><th>Qté</th><th>P.U. Achat HT</th><th>Total HT</th><th>Action</th></tr>
        </thead>
        <tbody id="lignes_commande"></tbody>
    </table>
    
    <button type="submit" class="btn btn-primary" style="font-size: 1.2rem; margin-top: 1rem;">Enregistrer la Commande</button>
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Le script JS est quasiment identique à celui de la création de facture.
// Il faut juste adapter les noms de variables (prix_achat au lieu de prix_vente, etc.)
// et les noms des champs cachés (lignes[${index}][prix_achat]).
// Je vous laisse le soin de l'adapter, c'est un excellent exercice !
// Indice : la logique de calcul des totaux et d'ajout/suppression de lignes est la même.
// /app/views/achats/creer.php (dans la balise <script>)

$(document).ready(function() {
    let ligneIndex = 0;

    // Quand on change le produit, met à jour le champ du prix d'achat
    $('#article_search').on('change', function() {
        const prixAchat = $(this).find('option:selected').data('prix');
        $('#prix_achat').val(prixAchat);
    });

    $('#btn-ajouter-ligne').on('click', function() {
        const articleSelect = $('#article_search');
        const selectedOption = articleSelect.find('option:selected');
        const articleId = selectedOption.val();
        const articleDesignation = selectedOption.data('designation');
        const quantite = parseInt($('#quantite').val());
        const prixAchat = parseFloat($('#prix_achat').val());

        if (!articleId || quantite <= 0 || prixAchat < 0) {
            alert('Veuillez sélectionner un article, une quantité et un prix valide.');
            return;
        }
        
        const totalLigneHT = quantite * prixAchat;
        
        const nouvelleLigne = `
            <tr>
                <td>
                    ${articleDesignation}
                    <input type="hidden" name="lignes[${ligneIndex}][article_id]" value="${articleId}">
                    <input type="hidden" name="lignes[${ligneIndex}][designation]" value="${articleDesignation}">
                    <input type="hidden" name="lignes[${ligneIndex}][quantite]" value="${quantite}">
                    <input type="hidden" name="lignes[${ligneIndex}][prix_achat]" value="${prixAchat}">
                </td>
                <td>${quantite}</td>
                <td>${prixAchat.toFixed(2)}</td>
                <td class="total-ligne-ht">${totalLigneHT.toFixed(2)}</td>
                <td><button type="button" class="btn-supprimer-ligne btn btn-danger">X</button></td>
            </tr>
        `;
        
        $('#lignes_commande').append(nouvelleLigne);
        ligneIndex++;
        // On pourrait ajouter un calcul de total ici si nécessaire
    });

    $('#lignes_commande').on('click', '.btn-supprimer-ligne', function() {
        $(this).closest('tr').remove();
        // Recalculer les totaux si nécessaire
    });
});
</script>