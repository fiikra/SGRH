<h2><?php echo $data['titre']; ?></h2>
<p>Cochez les cases pour accorder les permissions à chaque rôle.</p>
<div style="margin-bottom: 1rem;">
    <a href="/permissions/liste" class="btn btn-secondary">Gérer les Permissions</a>
    <a href="/roles" class="btn btn-secondary">Gérer les Rôles</a>
</div>
<form action="/permissions/sauvegarder" method="post">
    <div class="table-responsive">
        <table class="table table-bordered table-striped" style="font-size: 0.9em;">
            <thead style="position: sticky; top: 0; background: #fff;">
                <tr>
                    <th>Module / Permission</th>
                    <?php foreach($data['roles'] as $role): ?>
                        <th class="text-center"><?php echo htmlspecialchars($role['nom_role']); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $current_module = '';
                foreach($data['permissions'] as $perm): 
                    if ($perm['module'] !== $current_module) {
                        $current_module = $perm['module'];
                        echo '<tr><td colspan="'.(count($data['roles']) + 1).'" class="table-dark"><strong>'.$current_module.'</strong></td></tr>';
                    }
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($perm['description']); ?></td>
                        <?php foreach($data['roles'] as $role): ?>
                            <td class="text-center">
                                <input type="checkbox" name="perms[<?php echo $role['id']; ?>][]" value="<?php echo $perm['id']; ?>"
                                    <?php 
                                    if (isset($data['role_perms'][$role['id']]) && in_array($perm['id'], $data['role_perms'][$role['id']])) {
                                        echo 'checked';
                                    } 
                                    ?>
                                >
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="submit" class="btn btn-primary btn-lg mt-3">Sauvegarder les Permissions</button>
</form>

<style>.text-center{text-align:center;}</style>