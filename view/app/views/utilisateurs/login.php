<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-container { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 360px; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; }
        input { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }
        .error { color: #e74c3c; font-size: 0.9rem; }
        button { width: 100%; padding: 0.75rem; background: #3498db; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Connexion</h2>
        <p>Veuillez entrer vos identifiants pour vous connecter.</p>
        <form action="/utilisateurs/login" method="post">
            <div class="form-group">
                <label for="email">Email: <sup>*</sup></label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($data['email']); ?>">
                <span class="error"><?php echo $data['erreur_email'] ?? ''; ?></span>
            </div>
            <div class="form-group">
                <label for="mot_de_passe">Mot de passe: <sup>*</sup></label>
                <input type="password" name="mot_de_passe">
                <span class="error"><?php echo $data['erreur_mdp'] ?? ''; ?></span>
            </div>
            <button type="submit">Se connecter</button>
        </form>
    </div>
</body>
</html>