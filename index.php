<?php
  session_start();
  $page = 'index';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Viking Transport</title>
    <link rel="stylesheet" href="../CSS/Header.css" />
    <link rel="icon" type="image/png" href="Files/LogoVicking.png">
    
    <style>
        /* --- Styles Généraux & Image de fond --- */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('Files/Sans titre.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #e0e0e0;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        main {
            max-width: 1200px;
            margin: 50px auto;
            padding: 0 20px;
        }

        /* --- Boîte Principale Semi-Transparente --- */
        .welcome-box {
            background: rgba(20, 20, 20, 0.85);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* --- Titre principal --- */
        .welcome-box h2 {
            color: #ffffff;
            font-size: 2.2rem;
            margin-bottom: 30px;
            font-weight: 700;
        }

        .welcome-box h2::after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background-color: #e53e3e;
            margin: 12px auto 0 auto;
            border-radius: 2px;
        }

        /* --- Nouvelle Grille de Disposition --- */
        .intro-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* Crée deux colonnes égales */
            gap: 25px;
            margin-top: 40px;
        }

        /* --- Style Général des Cartes --- */
        .card {
            background: rgba(45, 55, 72, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 25px;
            text-align: left;
            transition: transform 0.3s ease, background-color 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            background: rgba(45, 55, 72, 0.7);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.4);
        }

        .card h4 {
            color: #e53e3e;
            font-size: 1.3rem;
            margin-top: 0;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card p {
            color: #cbd5e0;
            font-size: 0.95rem;
            margin: 0;
        }

        /* --- Carte Fidélité : Allongée sur toute la largeur (2 colonnes) --- */
        .card.highlight {
            grid-column: span 2; /* Force la carte à prendre les deux colonnes de la grille */
            background: rgba(155, 44, 44, 0.5);
            border: 1px solid rgba(229, 62, 62, 0.3);
            text-align: center; /* Centré pour un look bandeau de pub sympa */
        }

        .card.highlight:hover {
            background: rgba(155, 44, 44, 0.7);
        }

        .card.highlight h4 {
            color: #ffffff;
            font-weight: bold;
        }

        .card.highlight p {
            color: #f7fafc;
            max-width: 800px; /* Évite que le texte s'étale trop en longueur sur grand écran */
            margin: 0 auto;
        }

        /* --- Responsive / Mobile --- */
        @media (max-width: 768px) {
            .intro-grid {
                grid-template-columns: 1fr; /* Bascule tout sur une seule colonne sur mobile */
            }
            .card.highlight {
                grid-column: span 1; /* Aligné normalement sur mobile */
                text-align: left;
            }
            .welcome-box h2 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>

<?php
  include_once "Pages/header.php";
?>

<main>
  <div class="welcome-box">

    <h2>
      Bienvenue sur Viking Transport
      <?php
        if (!empty($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
            echo ', ' . htmlspecialchars($_SESSION['cli_prenom']) . '.';
        } else {
            echo ' !';
        }
      ?>
    </h2>

    <div class="intro-grid">

      <section class="card">
        <h4>Notre réseau</h4>
        <p>
          Viking Transport est le réseau de cars normands dédié aux transports régionaux.
          Avec 19 lignes, il relie de nombreuses communes à travers la Normandie.
          Simple, rapide et efficace pour tous vos trajets.
        </p>
      </section>

      <section class="card">
        <h4>Nos lignes</h4>
        <p>
          Que vous voyagiez sur une seule ligne ou en correspondance,
          Viking Transport vous accompagne partout en région Normandie.
        </p>
      </section>

      <section class="card highlight">
        <h4>Programme de fidélité</h4>
        <p>
          Créez un compte et cumulez des points à chaque trajet.
          Plus vous voyagez, plus vous gagnez de réductions sur vos billets.
        </p>
      </section>

    </div>
  </div>
</main>

<?php
  include_once "Pages/footer.php";
?>

</body> 
</html>