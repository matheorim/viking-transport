<?php
if (!isset($_GET['id'])) {
    header("Location: admin_clients.php");
    exit;
}

$id = intval($_GET['id']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Confirmation suppression</title>
    <style>
        body{
            font-family: Arial, sans-serif;
            display:flex;
            justify-content:center;
            align-items:center;
            height:100vh;
            background:#f5f5f5;
        }

        .box{
            background:white;
            padding:30px;
            border-radius:10px;
            text-align:center;
            box-shadow:0 0 10px rgba(0,0,0,.1);
        }

        .btn{
            display:inline-block;
            margin:10px;
            padding:10px 20px;
            text-decoration:none;
            border-radius:5px;
            color:white;
        }

        .oui{
            background:#c0392b;
        }

        .non{
            background:#2c3e50;
        }
    </style>
</head>
<body>

<div class="box">
    <h2>Confirmer la suppression</h2>
    <p>Voulez-vous vraiment supprimer ce compte ?</p>

    <a class="btn oui" href="traitement_suppression.php?id=<?= $id ?>">
        Oui
    </a>

    <a class="btn non" href="admin_client.php?id=<?= $id ?>">
        Non
    </a>
</div>

</body>
</html>