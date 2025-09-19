<h2><?php echo $data['titre']; ?></h2>

<form action="/livreurs/creer" method="post" style="max-width: 500px;">
    <div class="form-group">
        <label for="nom_complet">Nom Complet *</label>
        <input type="text" id="nom_complet" name="nom_complet" required>
    </div>
    <div class="form-group">
        <label for="telephone">Téléphone</label>
        <input type="text" id="telephone" name="telephone">
    </div>
    <div class="form-group">
        <label>
            <input type="checkbox" name="est_actif" value="1" checked>
            Livreur Actif
        </label>
    </div>
    <button type="submit" class="btn btn-primary">Enregistrer le Livreur</button>
</form>

<style>
    .form-group{margin-bottom: 1rem;} 
    .form-group label{display: block; margin-bottom: 5px;}
    .form-group input[type="text"]{width: 100%; padding: 8px;}
    .form-group input[type="checkbox"]{width: auto;}
</style>