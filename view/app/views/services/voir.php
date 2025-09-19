<?php $rep = $data['reparation']; ?>
<h2><?php echo $data['titre']; ?></h2>

<div class="details-grid">...</div>

<div class="details-box">
    <h3>Mettre à jour le Statut</h3>
    <form action="/services/mettreAJourStatut/<?php echo $rep['id']; ?>" method="post">
        <select name="statut">
            <option value="Devis en attente" <?php if($rep['statut'] == 'Devis en attente') echo 'selected'; ?>>Devis en attente</option>
            <option value="En attente d'accord" <?php if($rep['statut'] == 'En attente d\'accord') echo 'selected'; ?>>En attente d'accord</option>
            <option value="En cours" <?php if($rep['statut'] == 'En cours') echo 'selected'; ?>>En cours</option>
            <option value="Terminé" <?php if($rep['statut'] == 'Terminé') echo 'selected'; ?>>Terminé</option>
            <option value="Restitué" <?php if($rep['statut'] == 'Restitué') echo 'selected'; ?>>Restitué</option>
        </select>
        <textarea name="notes_technicien" placeholder="Ajouter des notes techniques..."><?php echo htmlspecialchars($rep['notes_technicien']); ?></textarea>
        <button type="submit" class="btn btn-primary">Mettre à jour</button>
    </form>
</div>

<?php if ($rep['statut'] == 'Terminé'): ?>
<div class="details-box" style="border-color: #28a745;">
    <h3>Clôture et Facturation</h3>
    <p>Le service est terminé. Vous pouvez maintenant générer la facture.</p>
    <a href="/services/facturer/<?php echo $rep['id']; ?>" class="btn btn-success">Clôturer et Facturer</a>
</div>
<?php endif; ?>