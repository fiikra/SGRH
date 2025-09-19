<?php
// Créer une variable pour un accès plus facile aux données
$tiers = $data['tiers'];
?>

<h2><?php echo $data['titre']; ?>: <?php echo htmlspecialchars($tiers['nom_raison_sociale']); ?></h2>

<form action="/tiers/modifier/<?php echo $tiers['id']; ?>" method="post" style="max-width: 700px;">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
            <label>Nom / Raison Sociale *</label>
            <input type="text" name="nom_raison_sociale" value="<?php echo htmlspecialchars($tiers['nom_raison_sociale']); ?>" required>
        </div>
        <div class="form-group">
            <label>Type *</label>
            <select name="type" required>
                <option value="Client" <?php echo ($tiers['type'] == 'Client') ? 'selected' : ''; ?>>Client</option>
                <option value="Fournisseur" <?php echo ($tiers['type'] == 'Fournisseur') ? 'selected' : ''; ?>>Fournisseur</option>
            </select>
        </div>
        <div class="form-group" style="grid-column: 1 / -1;">
            <label>Adresse</label>
            <input type="text" name="adresse" value="<?php echo htmlspecialchars($tiers['adresse']); ?>">
        </div>
        <div class="form-group">
            <label>Téléphone</label>
            <input type="text" name="telephone" value="<?php echo htmlspecialchars($tiers['telephone']); ?>">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($tiers['email']); ?>">
        </div>
        <div class="form-group">
            <label>NIF (Numéro d'Identification Fiscale)</label>
            <input type="text" name="nif" value="<?php echo htmlspecialchars($tiers['nif']); ?>">
        </div>
        <div class="form-group">
            <label>RC (Registre de Commerce)</label>
            <input type="text" name="rc" value="<?php echo htmlspecialchars($tiers['rc']); ?>">
        </div>
        <div class="form-group">
            <label>NIS (Numéro d'Identification Statistique)</label>
            <input type="text" name="nis" value="<?php echo htmlspecialchars($tiers['nis']); ?>">
        </div>
        <div class="form-group">
            <label>ART (Article d'Imposition)</label>
            <input type="text" name="art" value="<?php echo htmlspecialchars($tiers['art']); ?>">
        </div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Mettre à jour</button>
</form>

<style>.form-group{margin-bottom:0.5rem;} .form-group label{display:block; margin-bottom: 5px;} .form-group input, .form-group select{width:100%; padding:8px;}</style>