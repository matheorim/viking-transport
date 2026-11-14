<?php
session_start();

if (isset($_GET['err'])){
    if ($_GET['err'] == "no_user"){
        // utilisateur incorrect
        echo '<script type="text/javascript">alert("Nom d\'utilisateur incorrect");</script>';
    }

    if ($_GET['err'] == "wrong_pass"){
        // mdp incorrect
        echo '<script type="text/javascript">alert("Mot de passe incorrect");</script>';
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Connexion - Viking Transport</title>
    <link rel="stylesheet" href="../CSS/connexion.css" />
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
</head>
<body>

<nav class="vt-topbar">
    <a href="../index.php">Retour au site</a>
    <div class="vt-topbar-logo">
        <img src="../Files/LogoVicking.png" alt="Viking Transport" onerror="this.style.display='none'" />
    </div>
</nav>

<div class="page-body">
    <div class="form-wrapper">

        <div class="form-logo">
            <img src="../Files/LogoVicking.png" alt="Viking Transport" onerror="this.style.display='none'" />
            <div class="form-logo-name">VIKING TRANSPORT</div>
            <div class="form-logo-sub">Espace client</div>
        </div>

        <div class="form">
            <p class="form-title">Connexion</p>
            <p class="form-subtitle">Accédez à votre espace de réservation</p>

            <form method="POST" action="traitement_connexion.php">
                
                <div class="input-container">
                    <label for="email">Adresse email</label>
                    <input id="email" name="email" placeholder="exemple@mail.fr" type="email" required />
                    <span>
                      <svg stroke="currentColor" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path>
                      </svg>
                    </span>
                </div>

                <div class="input-container">
                    <label for="password">Mot de passe</label>
                    <input id="password" name="password" placeholder="••••••••" type="password" required />
                    <span id="toggle-password" style="cursor: pointer;" title="Afficher / masquer">
                      <svg id="eye-icon" stroke="currentColor" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path>
                        <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path>
                      </svg>
                    </span>
                </div>

                <div class="form-options">
                    <label>
                        <input type="checkbox" name="remember" /> Se souvenir de moi
                    </label>
                    <a href="#">Mot de passe oublié ?</a>
                </div>

                <button class="submit" type="submit">Se connecter</button>

            </form>

            <div class="divider"><span>ou</span></div>

            <p class="signup-link">
                Pas encore de compte ? <a href="inscription.php">S'inscrire</a>
            </p>

            <p class="form-footer">
                En vous connectant, vous acceptez nos
                <a href="mentions-legales.php">Mentions légales</a> et nos
                <a href="cgu.php">CGU</a>
            </p>
        </div>

    </div>
</div>

<script src="../Scripts/connexion.js"></script>

</body>
</html>