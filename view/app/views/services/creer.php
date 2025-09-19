<h2><?php echo $data['titre']; ?></h2>
<form action="/services/creer" method="post" style="max-width: 700px;">
    <fieldset>
        <legend>Informations Client</legend>
        <div class="form-group"><label>Nom du Client *</label><input type="text" name="nom_client" required></div>
        <div class="form-group"><label>Téléphone *</label><input type="text" name="telephone_client" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="email_client"></div>
    </fieldset>
    <fieldset style="margin-top:1rem;">
        <legend>Informations Appareil</legend>
        <div class="form-group"><label>Type d'appareil (Marque, Modèle) *</label><input type="text" name="type_appareil" required></div>
        <div class="form-group"><label>Panne déclarée par le client *</label><textarea name="panne_declaree" required></textarea></div>
    </fieldset>
    <button type="submit" class="btn btn-primary" style="margin-top:1rem;">Enregistrer le Bon</button>
</form>
<style>.form-group{margin-bottom:1rem;}label{display:block;margin-bottom:5px;}input,textarea{width:100%;padding:8px;}textarea{min-height:80px;}</style>