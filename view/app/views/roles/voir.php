<?php 
$role = $data['role']; 
$permissions = $data['permissions'];
?>
<h2><?php echo htmlspecialchars($data['titre']); ?></h2>

<div class="details-box" style="background: #f9f9f9; padding: 1rem; border: 1px solid #eee; margin-top: 1rem;">
    <h3>Permissions Assignées</h3>
    <?php if (empty($permissions)): ?>
        <p>Aucune permission n'est assignée à ce rôle.</p>
    <?php else: ?>
        <ul style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px; list-style-type: none; padding: 0;">
            <?php foreach ($permissions as $perm): ?>
                <li style="background: #e9ecef; padding: 5px 10px; border-radius: 3px;">
                    <strong style="color: #0056b3;"><?php echo htmlspecialchars($perm['module']); ?></strong>: <?php echo htmlspecialchars($perm['description']); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div style="margin-top: 2rem;">
    <a href="/roles" class="btn btn-secondary">Retour à la liste des rôles</a>
    <a href="/permissions" class="btn btn-primary">Gérer les assignations</a>
</div>