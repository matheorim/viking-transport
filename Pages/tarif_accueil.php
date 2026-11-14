<?php
    session_start();
    include_once "../PHP/utils.php";
    $conn   = OuvrirConn();
    $tarifs = ExecSelect($conn, "SELECT * FROM VIK_TARIF ORDER BY TAR_NUM_TRANCHE");
    $types  = ExecSelect($conn, "SELECT * FROM VIK_TYPE_CLIENT ORDER BY TYP_PT_LIMITE");
    $reductions = ExecSelect($conn, "SELECT * FROM VIK_REDUCTION ORDER BY RED_NB_POINTS");
    $conn = null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tarifs - Viking Transport</title>
    <link rel="stylesheet" href="../CSS/Header.css" />
    <link rel="stylesheet" href="../CSS/tarif_accueil.css" />
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
</head>
<body>

<?php include_once "header.php"; ?>

<main>
    <h1>Nos tarifs</h1>

    <div class="tarif-layout">

        <!-- GAUCHE : tableau distances -->
        <div class="tarif-left">
            <section class="tarif-section">
                <h2>Tarifs selon la distance</h2>
                <p class="tarif-intro">Le prix de votre trajet dépend de la distance parcourue. Plus vous voyagez, plus le tarif est avantageux au kilomètre.</p>
                <table class="tarif-table">
                    <thead>
                        <tr>
                            <th>Tranche</th>
                            <th>Distance min</th>
                            <th>Distance max</th>
                            <th>Prix de base</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tarifs as $t): ?>
                        <tr>
                            <td class="tranche-num"><?= htmlspecialchars($t['TAR_NUM_TRANCHE']) ?></td>
                            <td><?= htmlspecialchars($t['TAR_MIN_DIST']) ?> km</td>
                            <td><?= htmlspecialchars($t['TAR_MAX_DIST']) ?> km</td>
                            <td class="prix"><?= htmlspecialchars($t['TAR_PRIX']) ?> €</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>

        <!-- DROITE : 2 sections empilées -->
        <div class="tarif-right">

            <section class="tarif-section">
                <h2>Niveaux de fidélité</h2>
                <p class="tarif-intro">Votre niveau dépend de votre nombre total de points cumulés. Plus votre niveau est élevé, plus vous payez moins cher.</p>
                <table class="tarif-table">
                    <thead>
                        <tr>
                            <th>Niveau</th>
                            <th>Points requis</th>
                            <th>Réduction</th>
                            <th>Vous payez</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($types as $t): ?>
                        <tr>
                            <td class="tranche-num"><?= htmlspecialchars(ucfirst(strtolower($t['TYP_NOM']))) ?></td>
                            <td>Dès <?= number_format($t['TYP_PT_LIMITE'], 0, ',', ' ') ?> pts</td>
                            <td><?= 100 - $t['TYP_REDUC'] ?> %</td>
                            <td class="prix"><?= $t['TYP_REDUC'] ?> %</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section class="tarif-section">
                <h2>Utiliser ses points</h2>
                <p class="tarif-intro">À chaque réservation, vous pouvez utiliser une réduction pour baisser votre facture. Les points utilisés sont déduits de votre solde.</p>
                <table class="tarif-table">
                    <thead>
                        <tr>
                            <th>Points à utiliser</th>
                            <th>Réduction obtenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reductions as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['RED_NB_POINTS'] / 10) ?> points</td>
                            <td class="prix"><?= htmlspecialchars($r['RED_VALEUR']) ?> €</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

        </div>

    </div>

    <!-- GAGNER DES POINTS : en dessous, centré -->
    <div class="tarif-bottom">
        <section class="tarif-section">
            <h2>Gagner des points</h2>
            <p class="tarif-intro">À chaque voyage, vous cumulez des points selon la distance parcourue.</p>
            <div class="points-info">
                <div class="points-card">
                    <p>1 point gagné par tranche de 10 km parcourus</p>
                </div>
                <div class="points-card">
                    <p>Minimum 1 point garanti par voyage</p>
                </div>
                <div class="points-card">
                    <p>Points perdus après 1 an sans connexion. Compte supprimé après 2 ans.</p>
                </div>
            </div>
        </section>
    </div>

</main>

<?php include_once "footer.php"; ?>
</body>
</html>