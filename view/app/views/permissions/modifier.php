<?php $perm = $data['permission']; ?>
<h2><?php echo $data['titre']; ?></h2>
<form action="/permissions/modifier/<?php echo $perm['id']; ?>" method="post" style="max-width: 600px;">
    <div class="form-group"><label>Module *</label><input type="text" name="module" value="<?php echo htmlspecialchars($perm['module']); ?>" required></div>
    <div class="form-group"><label>Description *</label><input type="text" name="description" value="<?php echo htmlspecialchars($perm['description']); ?>" required></div>
    <div class="form-group"><label>Slug (identifiant unique) *</label><input type="text" name="slug" value="<?php echo htmlspecialchars($perm['slug']); ?>" required></div>
    <button type="submit" class="btn btn-primary">Mettre à jour</button>
</form>
<style>.form-group{margin-bottom:1rem;} label{display:block;margin-bottom:5px;} input{width:100%;padding:8px;}</style>