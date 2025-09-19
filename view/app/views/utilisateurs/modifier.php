<?php $user = $data['utilisateur']; ?>
<h2><?php echo $data['titre']; ?></h2>
<form action="/utilisateurs/modifier/<?php echo $user['id']; ?>" method="post">
    <div class="form-group"><label>Nom Complet *</label><input type="text" name="nom_complet" value="<?php echo htmlspecialchars($user['nom_complet']); ?>" required></div>
    <div class="form-group"><label>Email *</label><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
    <div class="form-group"><label>Nouveau Mot de passe</label><input type="password" name="mot_de_passe"><small>Laisser vide pour ne pas changer.</small></div>
    <div class="form-group">
        <label>Rôle *</label>
        <select name="role_id" required>
            <?php foreach ($data['roles'] as $role): ?>
                <option value="<?php echo $role['id']; ?>" <?php echo ($role['id'] == $user['role_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($role['nom_role']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><label><input type="checkbox" name="est_actif" value="1" <?php echo $user['est_actif'] ? 'checked' : ''; ?>> Utilisateur Actif</label></div>
    <button type="submit" class="btn btn-primary">Mettre à jour</button>
</form>
<style>.form-group{max-width:500px; margin-bottom:1rem;} label{display:block;margin-bottom:5px;} input,select{width:100%;padding:8px;} input[type=checkbox]{width:auto;}</style>