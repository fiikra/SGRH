<h2><?php echo $data['titre']; ?></h2>
<form action="/utilisateurs/creer" method="post">
    <div class="form-group"><label>Nom Complet *</label><input type="text" name="nom_complet" required></div>
    <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
    <div class="form-group"><label>Mot de passe *</label><input type="password" name="mot_de_passe" required></div>
    <div class="form-group">
        <label>Rôle *</label>
        <select name="role_id" required>
            <?php foreach ($data['roles'] as $role): ?>
                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['nom_role']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Enregistrer</button>
</form>
<style>.form-group{max-width:500px; margin-bottom:1rem;} label{display:block;margin-bottom:5px;} input,select{width:100%;padding:8px;}</style>