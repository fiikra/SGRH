<?php $role = $data['role']; ?>
<h2><?php echo $data['titre']; ?></h2>
<form action="/roles/modifier/<?php echo $role['id']; ?>" method="post" style="max-width: 500px;">
    <div class="form-group">
        <label for="nom_role">Nom du Rôle *</label>
        <input type="text" id="nom_role" name="nom_role" value="<?php echo htmlspecialchars($role['nom_role']); ?>" required style="width:100%; padding:8px;">
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:1rem;">Mettre à jour</button>
</form>