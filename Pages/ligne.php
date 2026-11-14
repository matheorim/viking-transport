<?php
    session_start();
    include_once "../PHP/utils.php";
    $conn = OuvrirConn();
    $lignes = ExecSelect($conn, "select * from visu_ligne");
    $conn = null;

    $lignesA = [];
    $lignesB = [];
    foreach ($lignes as $ligne) {
        $num = trim($ligne['NUMERO_LIGNE']);
        $lettre = strtoupper(substr($num, -1));
        if ($lettre === 'B') {
            $lignesB[] = $ligne;
        } else {
            $lignesA[] = $ligne;
        }
    }

    $villes = array_unique(array_merge(
        array_column($lignes, 'VILLE_DEPART'),
        array_column($lignes, 'VILLE_ARRIVE')
    ));
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Lignes du réseau - Viking Transport</title>
        <link rel="stylesheet" href="../CSS/ligne.css">
        <link rel="stylesheet" href="../CSS/Header.css" />
        <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
    </head>
    <body>

        <?php include_once "header.php"; ?>

        <main>
            <h1>Les lignes du réseau Viking Transport</h1>

            <div class="stats-bar">
                <div class="stat-card">
                    <div class="stat-val"><?php echo count($lignesA); ?></div>
                    <div class="stat-label">Lignes actives</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val"><?php echo count($villes); ?></div>
                    <div class="stat-label">Villes desservies</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val">6h – 22h</div>
                    <div class="stat-label">Amplitude horaire</div>
                </div>
            </div>

            <div class="ligne-layout">

                <!-- IMAGE GAUCHE -->
                <div class="img-panel">
                    <img src="../Files/image_ligne.png"
                         alt="Carte du réseau Viking Transport"
                         onclick="document.getElementById('overlay').style.display='flex'">
                    <p class="img-caption">Cliquez sur la carte pour l'agrandir</p>
                </div>

                <!-- TABLEAUX DROITE -->
                <div class="tables-panel">

                    <!-- TABLEAU ALLER -->
                    <div class="table-wrap">
                        <h2 class="titre-aller">&#8594; Allers</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Ligne</th>
                                    <th>Départ</th>
                                    <th></th>
                                    <th>Arrivée</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lignesA as $ligne): ?>
                                <tr>
                                    <td>
                                        <span class="ligne-badge">
                                            <?php echo htmlspecialchars(rtrim(trim($ligne['NUMERO_LIGNE']), 'Aa')); ?>A
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ligne['VILLE_DEPART']); ?></td>
                                    <td><span class="route-arrow">→</span></td>
                                    <td><?php echo htmlspecialchars($ligne['VILLE_ARRIVE']); ?></td>
                                    <td>
                                        <a href="horaire.php?ligne=<?php echo urlencode(trim($ligne['NUMERO_LIGNE'])); ?>">
                                            <button class="btn-aller">›</button>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- TABLEAU RETOUR -->
                    <div class="table-wrap">
                        <h2 class="titre-retour">&#8592; Retours</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Ligne</th>
                                    <th>Départ</th>
                                    <th></th>
                                    <th>Arrivée</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lignesB as $ligne): ?>
                                <tr>
                                    <td>
                                        <span class="ligne-badge badge-retour">
                                            <?php echo htmlspecialchars(rtrim(trim($ligne['NUMERO_LIGNE']), 'Bb')); ?>B
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ligne['VILLE_DEPART']); ?></td>
                                    <td><span class="route-arrow">←</span></td>
                                    <td><?php echo htmlspecialchars($ligne['VILLE_ARRIVE']); ?></td>
                                    <td>
                                        <a href="horaire.php?ligne=<?php echo urlencode(trim($ligne['NUMERO_LIGNE'])); ?>">
                                            <button class="btn-retour">›</button>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                </div>

            </div>
        </main>

        <div id="overlay" onclick="this.style.display='none'">
            <img src="../Files/image_ligne.png" alt="Carte agrandie">
        </div>

        <?php include_once "footer.php"; ?>

    </body>
</html>