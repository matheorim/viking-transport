<?php
session_start();
include_once "../PHP/utils.php";

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != true) {
    header("Location: connexion.php");
    exit;
}

$conn = OuvrirConn();

// === MEILLEURS CLIENTS ===
$meilleurs_clients = ExecSelect($conn, 
    "SELECT c.CLI_PRENOM, c.CLI_NOM, c.CLI_NB_POINTS_TOT, t.TYP_NOM,
            COUNT(r.RES_NUM) AS NB_RESERVATIONS,
            NVL(SUM(r.RES_PRIX_TOT), 0) AS TOTAL_DEPENSE
     FROM VIK_CLIENT c
     JOIN VIK_TYPE_CLIENT t ON c.TYP_NUM = t.TYP_NUM
     LEFT JOIN VIK_RESERVATION r ON c.CLI_NUM = r.CLI_NUM
     WHERE c.CLI_NUM != 0
     GROUP BY c.CLI_PRENOM, c.CLI_NOM, c.CLI_NB_POINTS_TOT, t.TYP_NOM
     ORDER BY NVL(SUM(r.RES_PRIX_TOT), 0) DESC
     FETCH FIRST 10 ROWS ONLY"
);

// === LIGNES LES PLUS UTILISÉES ===
$lignes_populaires = ExecSelect($conn,
    "SELECT e.LIG_NUM, COUNT(*) AS NB_TRAJETS
     FROM VIK_ETAPE e
     GROUP BY e.LIG_NUM
     ORDER BY NB_TRAJETS DESC
     FETCH FIRST 10 ROWS ONLY"
);

// === RÉSERVATIONS PAR PÉRIODE ===
$periode = $_GET['periode'] ?? 'mois';
if ($periode === 'semaine') {
    $reqPeriode = "SELECT TO_CHAR(TO_DATE(RES_DATE, 'DD/MM/YY'), 'IW/YYYY') AS PERIODE,
                          TO_NUMBER(TO_CHAR(TO_DATE(RES_DATE, 'DD/MM/YY'), 'YYYY')) AS AN,
                          TO_NUMBER(TO_CHAR(TO_DATE(RES_DATE, 'DD/MM/YY'), 'IW')) AS SEM,
                          COUNT(*) AS NB_RESA,
                          SUM(RES_PRIX_TOT) AS TOTAL
                   FROM VIK_RESERVATION
                   WHERE RES_DATE IS NOT NULL
                   GROUP BY TO_CHAR(TO_DATE(RES_DATE, 'DD/MM/YY'), 'IW/YYYY'),
                            TO_NUMBER(TO_CHAR(TO_DATE(RES_DATE, 'DD/MM/YY'), 'YYYY')),
                            TO_NUMBER(TO_CHAR(TO_DATE(RES_DATE, 'DD/MM/YY'), 'IW'))
                   HAVING TO_CHAR(TO_DATE(RES_DATE, 'DD/MM/YY'), 'IW/YYYY') IS NOT NULL
                   ORDER BY AN DESC, SEM DESC
                   FETCH FIRST 12 ROWS ONLY";

} elseif ($periode === 'annee') {
    $reqPeriode = "SELECT TO_CHAR(TO_DATE(RES_DATE, 'DD/MM/YY'), 'YYYY') AS PERIODE,
                          COUNT(*) AS NB_RESA,
                          SUM(RES_PRIX_TOT) AS TOTAL
                   FROM VIK_RESERVATION
                   WHERE RES_DATE IS NOT NULL
                   GROUP BY TO_CHAR(TO_DATE(RES_DATE, 'DD/MM/YY'), 'YYYY')
                   HAVING TO_CHAR(TO_DATE(RES_DATE, 'DD/MM/YY'), 'YYYY') IS NOT NULL
                   ORDER BY PERIODE DESC";
} else {
    $reqPeriode = "SELECT TO_CHAR(TO_DATE(RES_DATE, 'DD/MM/YY'), 'MM/YYYY') AS PERIODE,
                          COUNT(*) AS NB_RESA,
                          SUM(RES_PRIX_TOT) AS TOTAL
                   FROM VIK_RESERVATION
                   WHERE RES_DATE IS NOT NULL
                   GROUP BY TO_CHAR(TO_DATE(RES_DATE, 'DD/MM/YY'), 'MM/YYYY')
                   HAVING TO_CHAR(TO_DATE(RES_DATE, 'DD/MM/YY'), 'MM/YYYY') IS NOT NULL
                   ORDER BY PERIODE DESC
                   FETCH FIRST 12 ROWS ONLY";
}
$reservations_periode = ExecSelect($conn, $reqPeriode);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Statistiques - Viking Transport Admin</title>
    <link rel="stylesheet" href="../CSS/Header.css" />
    <link rel="stylesheet" href="../CSS/stats.css" />
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
</head>
<body>

<?php include_once "header.php"; ?>

<main>
    <h1>Statistiques</h1>

    <div class="stats-grid">

        <!-- MEILLEURS CLIENTS -->
        <div class="stat-section">
            <h2>Meilleurs clients</h2>
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Niveau</th>
                        <th>Réservations</th>
                        <th>Points cumulés</th>
                        <th>Total dépensé</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meilleurs_clients as $i => $c): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars(ucfirst(strtolower($c['CLI_PRENOM'])) . ' ' . strtoupper($c['CLI_NOM'])) ?></td>
                        <td><?= htmlspecialchars(ucfirst(strtolower($c['TYP_NOM']))) ?></td>
                        <td><?= $c['NB_RESERVATIONS'] ?></td>
                        <td><?= $c['CLI_NB_POINTS_TOT'] ?> pts</td>
                        <td class="highlight"><?= number_format($c['TOTAL_DEPENSE'], 2, ',', ' ') ?> €</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- LIGNES LES PLUS UTILISÉES -->
        <div class="stat-section">
            <h2>Lignes les plus utilisées</h2>
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ligne</th>
                        <th>Nombre de trajets</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lignes_populaires as $i => $l): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>Ligne <?= htmlspecialchars($l['LIG_NUM']) ?></td>
                        <td class="highlight"><?= $l['NB_TRAJETS'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- RÉSERVATIONS PAR PÉRIODE -->
    <div class="stat-section-bottom">
        <div class="stat-section">
            <h2>Réservations par période</h2>
            <div class="periode-nav">
                <a href="?periode=semaine" class="<?= $periode === 'semaine' ? 'active' : '' ?>">Par semaine</a>
                <a href="?periode=mois" class="<?= $periode === 'mois' ? 'active' : '' ?>">Par mois</a>
                <a href="?periode=annee" class="<?= $periode === 'annee' ? 'active' : '' ?>">Par année</a>
            </div>
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>Période</th>
                        <th>Nombre de réservations</th>
                        <th>Chiffre d'affaires</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations_periode as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['PERIODE']) ?></td>
                        <td><?= $r['NB_RESA'] ?></td>
                        <td class="highlight"><?= number_format($r['TOTAL'], 2, ',', ' ') ?> €</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php include_once "footer.php"; ?>
</body>
</html>