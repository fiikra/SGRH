<?php $facture = $data['facture']; ?>
<h2><?php echo $data['titre']; ?></h2>

<div style="background: #f9f9f9; padding: 1rem; margin-bottom: 2rem;">
    <p><strong>Client:</strong> <?php echo htmlspecialchars($facture['nom_raison_sociale'] ?? 'N/A'); ?></p>
    <p><strong>Montant à Encaisser:</strong> <strong style="font-size: 1.5rem;"><?php echo number_format($facture['montant_ttc'], 2, ',', ' '); ?> DA</strong></p>
</div>

<form action="/paiements/enregistrer/<?php echo $facture['id']; ?>" method="post" style="max-width: 500px;">
    <div class="form-group">
        <label>Méthode d'Encaissement *</label>
        <select name="methode_paiement_encaissement" required>
            <option value="Especes">Espèces</option>
            <option value="TPE">TPE</option>
            <option value="Virement">Virement / Chèque</option>
        </select>
    </div>
    <div class="form-group">
        <label>Référence de Paiement (si applicable)</label>
        <input type="text" name="reference_paiement">
    </div>
    <button type="submit" class="btn btn-primary btn-lg">Confirmer l'Encaissement</button>
</form>