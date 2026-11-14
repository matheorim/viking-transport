<?php
session_start();

$erreurs   = isset($_SESSION['inscription_erreurs'])   ? $_SESSION['inscription_erreurs']   : [];
$anciennes = isset($_SESSION['inscription_anciennes']) ? $_SESSION['inscription_anciennes'] : [];

unset($_SESSION['inscription_erreurs']);
unset($_SESSION['inscription_anciennes']);

function val($anciennes, $key)
{
    return isset($anciennes[$key]) ? $anciennes[$key] : '';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Inscription - Viking Transport</title>
    <link rel="stylesheet" href="../CSS/inscription.css" />
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
    <style>
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            color: #b91c1c;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-size: .875rem;
        }

        .alert-error ul {
            margin: 6px 0 0 16px;
            padding: 0;
        }

        .alert-error li {
            margin-bottom: 4px;
        }

        .input-container input.invalid {
            border-color: #ef4444;
        }
    </style>
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
                <p class="form-title">Créer un compte</p>
                <p class="form-subtitle">Rejoignez l'espace de réservation Viking</p>

                <?php if (!empty($erreurs)): ?>
                    <div class="alert-error">
                        <strong>Veuillez corriger les erreurs suivantes :</strong>
                        <ul>
                            <?php foreach ($erreurs as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="succes_inscription.php" novalidate>

                    <div class="input-row">
                        <div class="input-container">
                            <label for="prenom">Prénom</label>
                            <input id="prenom" name="prenom" placeholder="Jean" type="text"
                                value="<?= val($anciennes, 'prenom') ?>"
                                class="<?= in_array('Le prénom est obligatoire.', $erreurs) ? 'invalid' : '' ?>" />
                            <span>
                                <svg stroke="currentColor" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path>
                                    <circle cx="12" cy="7" r="4" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></circle>
                                </svg>
                            </span>
                        </div>
                        <div class="input-container">
                            <label for="nom">Nom</label>
                            <input id="nom" name="nom" placeholder="Dupont" type="text"
                                value="<?= val($anciennes, 'nom') ?>"
                                class="<?= in_array('Le nom est obligatoire.', $erreurs) ? 'invalid' : '' ?>" />
                            <span>
                                <svg stroke="currentColor" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path>
                                    <circle cx="12" cy="7" r="4" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></circle>
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div class="input-container">
                        <label for="telephone">Téléphone</label>
                        <input id="telephone" name="telephone" placeholder="+33 6 00 00 00 00" type="tel"
                            value="<?= val($anciennes, 'telephone') ?>"
                            class="<?= in_array('Le numéro de téléphone est invalide.', $erreurs) ? 'invalid' : '' ?>" />
                        <span>
                            <svg viewBox="0 0 1030 1280" xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="#9CA3AF">
                                <g transform="translate(0,1280) scale(0.1,-0.1)">
                                    <path d="M8646 12790 c-60 -16 -149 -67 -194 -113 -71 -72 -2120 -2778 -2157 -2848 -100 -191 -80 -414 53 -578 19 -24 247 -213 506 -421 488 -391 506 -408 555 -521 50 -114 66 -279 42 -430 -28 -171 -113 -399 -240 -644 -170 -327 -201 -370 -1122 -1576 l-874 -1147 -225 -237 c-442 -467 -720 -700 -955 -801 -87 -38 -93 -39 -205 -39 -123 0 -172 14 -246 70 -17 13 -201 233 -407 488 -206 255 -390 476 -408 492 -163 138 -410 119 -560 -41 -53 -57 -2097 -2749 -2136 -2813 -112 -186 -89 -448 55 -608 31 -35 842 -641 1021 -763 282 -192 781 -290 1278 -250 1399 114 2984 1146 4542 2960 1197 1393 2177 2928 2733 4280 351 853 541 1627 589 2400 49 785 -113 1575 -418 2037 -50 76 -821 973 -876 1019 -61 52 -150 86 -235 90 -42 2 -94 -1 -116 -6z" />
                                </g>
                            </svg>
                        </span>
                    </div>
                    <p class="field-error <?= in_array('Le numéro de téléphone est invalide.', $erreurs) ? 'show' : '' ?>"
                        id="err-telephone">Numéro invalide (ex : 06 12 34 56 78 ou +33 6 12 34 56 78)</p>

                    <div class="input-container">
                        <label for="email">Adresse email</label>
                        <input id="email" name="email" placeholder="exemple@mail.fr" type="email"
                            value="<?= val($anciennes, 'email') ?>"
                            class="<?= (in_array("L'adresse email est invalide.", $erreurs) || in_array("Cette adresse email est déjà utilisée.", $erreurs)) ? 'invalid' : '' ?>" />
                        <span>
                            <svg stroke="currentColor" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path>
                            </svg>
                        </span>
                    </div>
                    <p class="field-error <?= in_array("L'adresse email est invalide.", $erreurs) ? 'show' : '' ?>"
                        id="err-email">Adresse email invalide</p>

                    <div class="input-container">
                        <label for="password">Mot de passe</label>
                        <input id="password" name="password" placeholder="••••••••" type="password"
                            class="<?= in_array('Le mot de passe doit contenir au moins 8 caractères.', $erreurs) ? 'invalid' : '' ?>" />
                        <span id="toggle-password" style="cursor:pointer;" title="Afficher / masquer">
                            <svg id="eye-password" stroke="currentColor" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path>
                                <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path>
                            </svg>
                        </span>
                    </div>
                    <p class="field-error <?= in_array('Le mot de passe doit contenir au moins 8 caractères.', $erreurs) ? 'show' : '' ?>"
                        id="err-password">Le mot de passe doit contenir au moins 8 caractères</p>

                    <div class="input-container">
                        <label for="confirm-password">Confirmer le mot de passe</label>
                        <input id="confirm-password" name="confirm-password" placeholder="••••••••" type="password"
                            class="<?= in_array('Les mots de passe ne correspondent pas.', $erreurs) ? 'invalid' : '' ?>" />
                        <span id="toggle-confirm" style="cursor:pointer;" title="Afficher / masquer">
                            <svg id="eye-confirm" stroke="currentColor" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path>
                                <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path>
                            </svg>
                        </span>
                    </div>
                    <p class="field-error <?= in_array('Les mots de passe ne correspondent pas.', $erreurs) ? 'show' : '' ?>"
                        id="err-confirm">Les mots de passe ne correspondent pas</p>

                    <div class="form-options">
                        <input type="checkbox" id="cgu" name="cgu" />
                        <label for="cgu">
                            J'accepte les <a href="mentions-legales.php">Mentions légales</a> et les <a href="cgu.html">CGU</a> de Viking Transport
                        </label>
                    </div>
                    <?php if (in_array('Vous devez accepter les CGU.', $erreurs)): ?>
                        <p class="field-error show">Vous devez accepter les CGU.</p>
                    <?php endif; ?>

                    <button class="submit" type="submit">Créer mon compte</button>

                </form>

                <div class="divider"><span>ou</span></div>

                <p class="signin-link">
                    Déjà un compte ? <a href="connexion.php">Se connecter</a>
                </p>

                <p class="form-footer">
                    Vos données sont protégées conformément au <a href="cgu.php">RGPD</a>
                </p>
            </div>

        </div>
    </div>
    <script src="../Scripts/inscription.js"></script>
</body>

</html>