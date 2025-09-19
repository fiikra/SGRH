<h2><?php echo $data['titre']; ?></h2>
<form action="/roles/creer" method="post" style="max-width: 500px;">
    <div class="form-group">
        <label for="nom_role">Nom du nouveau Rôle *</label>
        <input type="text" id="nom_role" name="nom_role" required style="width:100%; padding:8px;">
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:1rem;">Enregistrer le Rôle</button>
</form>