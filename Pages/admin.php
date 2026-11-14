<?php
  session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Viking Transport - Administration</title>
    <link rel="stylesheet" href="../CSS/Header.css" />
    <link rel="stylesheet" href="../CSS/admin.css" />
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
</head>
<body>
<?php include_once 'header.php'; ?>
<main>

    <h1>Administration</h1>

    <div class="admin-grid">

        <div class="admin-card">
            <div class="admin-card-icon"></div>
            <div class="admin-card-content">
                <h2>Comptes clients</h2>
                <p>Consulter et gérer les comptes des clients enregistrés sur la plateforme.</p>
            </div>
            <a href="admin_clients.php">
                <button class="bouton_admin">Voir les clients ›</button>
            </a>
        </div>

        <div class="admin-card">
            <div class="admin-card-icon"></div>
            <div class="admin-card-content">
                <h2>Lignes</h2>
                <p>Modifier les différentes lignes et horaires</p>
            </div>
            <a href="admin_lignes.php">
                <button class="bouton_admin">Voir les lignes ›</button>
            </a>
        </div>

        <div class="admin-card">
            <div class="admin-card-icon"></div>
            <div class="admin-card-content">
                <h2>Statistiques</h2>
                <p>Visualiser les données de fréquentation, réservations et performances du réseau.</p>
            </div>
            <a href="admin_statistiques.php">
                <button class="bouton_admin">Voir les statistiques ›</button>
            </a>
        </div>

    </div>

    <img src="../Files/bus.png" alt="bus Viking Transport" class="center">

</main>
<?php include_once 'footer.php'; ?>
</body>
</html>