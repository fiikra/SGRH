<h2><?php echo $data['titre']; ?></h2>
<form action="/tiers/creer" method="post" style="max-width: 700px;">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
            <label>Nom / Raison Sociale *</label>
            <input type="text" name="nom_raison_sociale" required>
        </div>
        <div class="form-group">
            <label>Type *</label>
            <select name="type" required>
                <option value="Client">Client</option>
                <option value="Fournisseur">Fournisseur</option>
            </select>
        </div>
        <div class="form-group" style="grid-column: 1 / -1;">
            <label>Adresse</label>
            <input type="text" name="adresse">
        </div>
        <div class="form-group">
            <label>Téléphone</label>
            <input type="text" name="telephone">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email">
        </div>
        <div class="form-group">
            <label>NIF (Numéro d'Identification Fiscale)</label>
            <input type="text" name="nif">
        </div>
        <div class="form-group">
            <label>RC (Registre de Commerce)</label>
            <input type="text" name="rc">
        </div>
        <div class="form-group">
            <label>NIS (Numéro d'Identification Statistique)</label>
            <input type="text" name="nis">
        </div>
        <div class="form-group">
            <label>ART (Article d'Imposition)</label>
            <input type="text" name="art">
        </div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Enregistrer</button>
</form>

<style>.form-group{margin-bottom:0.5rem;} .form-group label{display:block; margin-bottom: 5px;} .form-group input, .form-group select{width:100%; padding:8px;}</style>