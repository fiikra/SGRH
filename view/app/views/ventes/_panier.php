<?php 
// /app/views/ventes/_panier.php
$panier = $_SESSION['panier'] ?? [];
$total_ht = 0;
$total_tva = 0;
?>

<div style="flex-grow:1; overflow-y:auto;">
    <h3>Panier Actuel</h3>
    <hr>
    <ul id="cart-items" style="list-style: none; padding: 0;">
        <?php if (empty($panier)): ?>
            <li style="text-align:center; color: #888;">Le panier est vide.</li>
        <?php else: ?>
            <?php foreach ($panier as $item) :
                $sub_total_ht = $item['prix_vente_ht'] * $item['qte'];
                $sub_total_tva = $sub_total_ht * $item['tva_taux'];
                $total_ht += $sub_total_ht;
                $total_tva += $sub_total_tva;
            ?>
                <li style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem;">
                    <span><?php echo $item['qte']; ?>x <?php echo htmlspecialchars($item['designation']); ?></span>
                    <strong><?php echo number_format($sub_total_ht + $sub_total_tva, 2); ?> DA</strong>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>

<div class="cart-summary">
    <hr>
    <?php 
        $total_ttc = $total_ht + $total_tva;
        $timbre = ($total_ttc > 0) ? 1.00 : 0; 
    ?>
    <p style="display:flex; justify-content:space-between;"><span>Total HT:</span> <span><?php echo number_format($total_ht, 2); ?> DA</span></p>
    <p style="display:flex; justify-content:space-between;"><span>TVA (19%):</span> <span><?php echo number_format($total_tva, 2); ?> DA</span></p>
    <p style="display:flex; justify-content:space-between;"><span>Timbre:</span> <span><?php echo number_format($timbre, 2); ?> DA</span></p>
    <h3 style="display:flex; justify-content:space-between;"><span>Total TTC:</span> <span><?php echo number_format($total_ttc + $timbre, 2); ?> DA</span></h3>
    <hr>

    <?php if (!empty($panier)) : ?>
    <form id="form-finaliser-vente" method="post">
        <div style="margin-bottom: 1rem;">
            <label>Client</label>
            <select name="client_id" id="client_id_selector" style="width:100%; padding: 8px;">
                <option value="0">Vente au comptoir (Anonyme)</option>
                <?php foreach ($data['clients'] as $client) : ?>
                    <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['nom_raison_sociale']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="margin-bottom: 1rem;">
            <label>Méthode de Paiement (pour encaissement direct)</label>
            <select id="methode_paiement" name="methode_paiement" style="width:100%; padding: 8px;">
                <option value="Especes">Espèces</option>
                <option value="TPE">TPE (Carte Bancaire)</option>
                <option value="Virement">Virement / Chèque</option>
            </select>
        </div>
        <div id="champ_reference_paiement" style="margin-bottom: 1rem; display: none;">
            <label>Référence (N° Transaction, Chèque...)</label>
            <input type="text" name="reference_paiement" style="width:100%; padding: 8px;">
        </div>
        <div style="margin-bottom: 1rem; padding: 10px; background: #fff3cd; border-radius: 4px;">
            <label style="font-weight: bold;">
                <input type="checkbox" name="mode_walker" value="1" <?php if ($mode_par_defaut == 'Ticket') echo 'checked'; ?>>
                Ticket simple (Sans Facture formelle)
            </label>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <button type="submit" formaction="/ventes/finaliserVente" class="btn btn-primary" style="padding: 10px; font-size: 1.1rem;"> encaisser</button>
            <?php if (Auth::can('ventes_credit_accorder')): ?>
                <button type="submit" id="btn-credit-sale" formaction="/ventes/finaliserVenteACredit" class="btn btn-secondary" style="padding: 10px; font-size: 1.1rem;" disabled> a crédit</button>
            <?php endif; ?>
        </div>
    </form>
    <a href="#" id="btn-vider-panier" class="btn btn-danger" style="width:100%; display:block; text-align:center; margin-top:1rem;">Annuler & Vider</a>
    <?php endif; ?>
</div>