<h2><?php echo $data['titre']; ?></h2>
<form action="/articles/creer" method="post" style="max-width: 600px;">
    <div style="margin-bottom: 1rem;">
        <label>Référence</label>
        <input type="text" name="reference" required style="width: 100%; padding: 8px;">
    </div>
    <div style="margin-bottom: 1rem;">
        <label>Désignation</label>
        <input type="text" name="designation" required style="width: 100%; padding: 8px;">
    </div>
    <div style="margin-bottom: 1rem;">
        <label>Prix de Vente (HT)</label>
        <input type="number" step="0.01" name="prix_vente_ht" required style="width: 100%; padding: 8px;">
    </div>
     <div style="margin-bottom: 1rem;">
        <label>Prix d'Achat (HT)</label>
        <input type="number" step="0.01" name="prix_achat_ht" required style="width: 100%; padding: 8px;">
    </div>
    <div style="margin-bottom: 1rem;">
        <label>Stock d'alerte</label>
        <input type="number" name="stock_alerte" value="10" style="width: 100%; padding: 8px;">
    </div>
    <div style="margin-bottom: 1rem;">
        <label>Code-barres (Optionnel)</label>
        <input type="text" name="code_barre" style="width: 100%; padding: 8px;">
    </div>
     <div style="margin-bottom: 1rem;">
        <label>Notes (Optionnel)</label>
        <textarea name="notes" style="width: 100%; padding: 8px;"></textarea>
    </div>
    <div class="form-group">
    <label>
        <input type="checkbox" name="est_stocke" value="1" checked> Gérer le stock pour cet article
    </label>
    <small>Décochez cette case s'il s'agit d'un service ou d'une main d'œuvre.</small>
</div>
    <button type="submit" class="btn btn-primary">Enregistrer l'article</button>
</form>