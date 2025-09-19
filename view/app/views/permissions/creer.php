<h2><?php echo $data['titre']; ?></h2>
<form action="/permissions/creer" method="post" style="max-width: 600px;">
    <div class="form-group"><label>Module *</label><input type="text" name="module" placeholder="Ex: Articles" required></div>
    <div class="form-group"><label>Description *</label><input type="text" name="description" placeholder="Ex: Créer et modifier des articles" required></div>
    <div class="form-group"><label>Slug (identifiant unique) *</label><input type="text" name="slug" placeholder="Ex: articles_gerer" required></div>
    <button type="submit" class="btn btn-primary">Enregistrer</button>
</form>
<style>.form-group{margin-bottom:1rem;} label{display:block;margin-bottom:5px;} input{width:100%;padding:8px;}</style>