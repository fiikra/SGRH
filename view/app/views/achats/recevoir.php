<h2><?php echo $data['titre']; ?></h2>
<p><strong>Fournisseur:</strong> <?php echo htmlspecialchars($data['commande']['fournisseur_nom']); ?></p>

<form action="/achats/enregistrerReception/<?php echo $data['commande']['id']; ?>" method="post">
    <table class="table">
        <thead>
            <tr>
                <th>Article</th>
                <th>Qté Commandée</th>
                <th>Qté Déjà Reçue</th>
                <th>Quantité à Recevoir</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($data['lignes'] as $ligne): ?>
                <?php $quantite_restante = $ligne['quantite_commandee'] - $ligne['quantite_recue']; ?>
                <?php if($quantite_restante > 0): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ligne['designation']); ?></td>
                        <td><?php echo $ligne['quantite_commandee']; ?></td>
                        <td><?php echo $ligne['quantite_recue']; ?></td>
                        <td>
                            <input type="number" name="lignes[<?php echo $ligne['id']; ?>]" value="0" min="0" max="<?php echo $quantite_restante; ?>" class="form-control" style="width: 100px;">
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <button type="submit" class="btn btn-success" style="margin-top: 1rem; font-size: 1.2rem;">Enregistrer la Réception</button>
</form>