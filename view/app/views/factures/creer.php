<h2><?php echo $data['titre']; ?></h2>

<form id="form-creer-facture" action="/factures/enregistrer" method="post">

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1.5rem;">
        <fieldset>
            <legend>Informations Client</legend>
            <div class="form-group">
                <label for="client_id">Client *</label>
                <select name="client_id" id="client_id_selector" required>
                    <option value="">-- Choisir un client --</option>
                    <?php foreach ($data['clients'] as $client): ?>
                        <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['nom_raison_sociale']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </fieldset>
        
        <fieldset>
            <legend>Livraison</legend>
            <div class="form-group">
                <label for="type_livraison">Type de Livraison</label>
                <select name="type_livraison" id="type_livraison">
                    <option value="Sur Place">Retrait sur Place</option>
                    <option value="A Domicile">Livraison à Domicile</option>
                </select>
            </div>
            <div id="form_livraison_domicile" style="display:none; margin-top:1rem;">
                <div class="form-group">
                    <label>Adresse de Livraison</label>
                    <textarea name="adresse_livraison" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Coût de la Livraison</label>
                    <input type="number" name="cout_livraison" id="cout_livraison" value="0.00" step="0.01">
                </div>
            </div>
        </fieldset>
    </div>

    <fieldset style="margin-bottom:1.5rem;">
        <legend>Ajouter des Articles à la Facture</legend>
        <div style="display:flex; align-items: flex-end; gap: 1rem;">
            <div style="flex-grow: 1;">
                <label for="article_search">Chercher un article</label>
                <select id="article_search">
                    <option value="">-- Sélectionner un article --</option>
                    <?php foreach ($data['articles'] as $article): ?>
                        <option 
                            value="<?php echo $article['id']; ?>" 
                            data-prix="<?php echo $article['prix_vente_ht']; ?>" 
                            data-cout="<?php echo $article['prix_achat_ht']; ?>"
                            data-tva="<?php echo $article['tva_taux']; ?>"
                            data-est-stocke="<?php echo $article['est_stocke']; ?>">
                            <?php echo htmlspecialchars($article['designation']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="quantite">Quantité</label>
                <input type="number" id="quantite" value="1" min="1" style="width: 80px;">
            </div>
            <button type="button" id="btn-ajouter-ligne" class="btn btn-primary">Ajouter à la facture</button>
        </div>
    </fieldset>

    <h3>Détails de la Facture</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Désignation</th>
                <th style="width: 100px;">Qté</th>
                <th style="width: 150px;">Prix U. HT</th>
                <th style="width: 150px;">Total HT</th>
                <th style="width: 50px;">Action</th>
            </tr>
        </thead>
        <tbody id="lignes_facture">
            </tbody>
    </table>
    
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 1.5rem;">
        <fieldset>
            <legend>Paiement</legend>
            <div class="form-group">
                <label>Méthode de Paiement</label>
                <select name="methode_paiement" id="methode_paiement">
                    <option value="Credit">À Crédit (Paiement différé)</option>
                    <option value="Paiement a la livraison">Paiement à la livraison</option>
                    <option value="Especes">Espèces (Payé)</option>
                    <option value="TPE">TPE (Payé)</option>
                    <option value="Virement">Virement / Chèque (En attente)</option>
                </select>
            </div>
            <div id="champ_reference_paiement" class="form-group" style="display:none;">
                <label>Référence (N° Transaction, Chèque...)</label>
                <input type="text" name="reference_paiement">
            </div>
            <div class="form-group">
                <label>Montant Payé (Avance)</label>
                <input type="number" name="montant_paye" id="montant_paye" value="0.00" step="0.01">
            </div>
        </fieldset>

        <div class="total-summary" style="background: #f9f9f9; padding: 1rem; border-radius: 5px;">
            <p style="display:flex; justify-content:space-between;"><span>Total Articles HT :</span> <span id="total_articles_ht">0.00</span></p>
            <p style="display:flex; justify-content:space-between;"><span>Total TVA :</span> <span id="total_tva">0.00</span></p>
            <p style="display:flex; justify-content:space-between;"><span>Coût Livraison :</span> <span id="total_livraison">0.00</span></p>
            <p style="display:flex; justify-content:space-between;"><span>Timbre Fiscal :</span> <span id="total_timbre">0.00</span></p>
            <hr>
            <h3 style="display:flex; justify-content:space-between;"><span>TOTAL À PAYER :</span> <span id="total_ttc">0.00</span></h3>
        </div>
    </div>
    
    <hr>
    <div style="text-align: right;">
        <?php if (Auth::can('livraisons_gerer')): ?>
            <div class="form-group" style="display: inline-block; margin-right: 1rem;">
                <label><input type="checkbox" name="preparer_livraison_immediatement" value="1" checked> <strong>Préparer le BL immédiatement</strong></label>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-success btn-lg">Créer la Facture</button>
    </div>
</form>

<style>
    fieldset { border: 1px solid #ddd; padding: 1.5rem; border-radius: 5px; }
    legend { font-weight: bold; padding: 0 0.5rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group:last-child { margin-bottom: 0; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
    .form-group input[type="text"],
    .form-group input[type="number"],
    .form-group input[type="email"],
    .form-group select,
    .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
    .total-summary span:last-child { font-weight: bold; }
</style>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    let ligneIndex = 0;

    // ----- GESTION DES LIGNES D'ARTICLES -----
    $('#btn-ajouter-ligne').on('click', function() {
        const selectedOption = $('#article_search').find('option:selected');
        const articleId = selectedOption.val();
        if (!articleId) { alert('Veuillez sélectionner un article.'); return; }

        const quantite = parseInt($('#quantite').val());
        const articleDesignation = selectedOption.text();
        const articlePrix = parseFloat(selectedOption.data('prix'));
        const articleCout = parseFloat(selectedOption.data('cout'));
        const articleTva = parseFloat(selectedOption.data('tva'));
        const articleEstStocke = parseInt(selectedOption.data('est-stocke'));
        const totalLigneHT = quantite * articlePrix;
        
        const nouvelleLigne = `
            <tr>
                <td>${articleDesignation}
                    <input type="hidden" name="lignes[${ligneIndex}][article_id]" value="${articleId}">
                    <input type="hidden" name="lignes[${ligneIndex}][designation]" value="${articleDesignation}">
                    <input type="hidden" name="lignes[${ligneIndex}][prix_unitaire_ht]" value="${articlePrix}">
                    <input type="hidden" name="lignes[${ligneIndex}][cout_unitaire_ht]" value="${articleCout}">
                    <input type="hidden" name="lignes[${ligneIndex}][taux_tva]" value="${articleTva}">
                    <input type="hidden" name="lignes[${ligneIndex}][est_stocke]" value="${articleEstStocke}">
                    <input type="hidden" name="lignes[${ligneIndex}][quantite]" value="${quantite}">
                </td>
                <td class="text-center">${quantite}</td>
                <td class="text-right">${articlePrix.toFixed(2)}</td>
                <td class="text-right"><strong>${totalLigneHT.toFixed(2)}</strong></td>
                <td class="text-center"><button type="button" class="btn-supprimer-ligne btn btn-danger">X</button></td>
            </tr>`;
        $('#lignes_facture').append(nouvelleLigne);
        ligneIndex++;
        calculerEtAfficherTotaux();
    });

    $('#lignes_facture').on('click', '.btn-supprimer-ligne', function() {
        $(this).closest('tr').remove();
        calculerEtAfficherTotaux();
    });

    // ----- GESTION DES FORMULAIRES DYNAMIQUES -----
    $('#type_livraison, #cout_livraison, #methode_paiement').on('change keyup', function() {
        calculerEtAfficherTotaux();
    });

    $('#type_livraison').on('change', function() {
        if ($(this).val() === 'A Domicile') {
            $('#form_livraison_domicile').slideDown();
        } else {
            $('#form_livraison_domicile').slideUp();
            $('#cout_livraison').val('0.00');
        }
    });

    $('#methode_paiement').on('change', function() {
        const methode = $(this).val();
        if (methode === 'TPE' || methode === 'Virement') {
            $('#champ_reference_paiement').slideDown();
        } else {
            $('#champ_reference_paiement').slideUp();
        }
        // Si ce n'est pas un paiement différé, on pré-remplit le montant payé
        if (methode !== 'Credit' && methode !== 'Paiement a la livraison') {
            const total = parseFloat($('#total_ttc').text().replace(',', '.'));
            $('#montant_paye').val(total.toFixed(2));
        } else {
            $('#montant_paye').val('0.00');
        }
    });

    // ----- FONCTION DE CALCUL PRINCIPALE -----
    function calculerEtAfficherTotaux() {
        let totalHT = 0;
        let totalTVA = 0;

        $('#lignes_facture tr').each(function() {
            const prix = parseFloat($(this).find('input[name*="[prix_unitaire_ht]"]').val());
            const qte = parseInt($(this).find('input[name*="[quantite]"]').val());
            const tva = parseFloat($(this).find('input[name*="[taux_tva]"]').val());
            totalHT += prix * qte;
            totalTVA += (prix * qte) * tva;
        });

        const coutLivraison = parseFloat($('#cout_livraison').val()) || 0;
        const methodePaiement = $('#methode_paiement').val();
        const timbre = (methodePaiement === 'Especes') ? 1.00 : 0;
        const totalTTC = totalHT + totalTVA + coutLivraison + timbre;

        $('#total_articles_ht').text(totalHT.toFixed(2));
        $('#total_tva').text(totalTVA.toFixed(2));
        $('#total_livraison').text(coutLivraison.toFixed(2));
        $('#total_timbre').text(timbre.toFixed(2));
        $('#total_ttc').text(totalTTC.toFixed(2));
    }
});
</script>