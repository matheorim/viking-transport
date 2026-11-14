<?php
session_start();
include_once "../PHP/utils.php";

$numLigne = $_GET['ligne'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../CSS/horaire.css">
    <link rel="stylesheet" href="../CSS/Header.css">
    <title>Horaire - Viking Transport</title>
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
</head>
<body>

<?php include_once 'header.php'; ?>

<main>
    <a href="ligne.php" class="revenir">← Revenir aux lignes</a>

    <h1>Horaires — Ligne <?= htmlspecialchars($numLigne) ?></h1>

    <table class="tabHoraire">
    <?php
    if ($numLigne !== null) {
        $conn     = OuvrirConn();
        $ligneEsc = str_replace("'", "''", $numLigne);

        $tabVilles = ExecSelect($conn, "
            SELECT ville AS VILLE_ARRET,
                   TO_CHAR(MIN(heure), 'HH24:MI:SS') AS HEURE_PASSAGE
            FROM (
                SELECT c.com_nom AS ville,
                       n.noe_heure_passage AS heure
                FROM vik_noeud n
                JOIN vik_commune c ON c.com_code_insee = n.com_code_insee_arret
                WHERE n.lig_num = '$ligneEsc'

                UNION ALL

                SELECT c.com_nom AS ville,
                       MAX(n.noe_heure_passage) AS heure
                FROM vik_ligne l
                JOIN vik_commune c ON c.com_code_insee = l.com_code_insee_term
                JOIN vik_noeud n ON n.lig_num = l.lig_num
                WHERE l.lig_num = '$ligneEsc'
                  AND c.com_code_insee NOT IN (
                      SELECT com_code_insee_arret FROM vik_noeud WHERE lig_num = '$ligneEsc'
                  )
                GROUP BY c.com_nom
            )
            GROUP BY ville
            ORDER BY MIN(heure)
        ");

        $resTerminus = ExecSelect($conn, "
            SELECT com_code_insee_term FROM vik_ligne WHERE lig_num = '$ligneEsc'
        ");
        $codeTerminus = !empty($resTerminus) ? $resTerminus[0]['COM_CODE_INSEE_TERM'] : null;

        $totalEtapes = count($tabVilles);
        $indexEtape  = 0;

        foreach ($tabVilles as $villeEtape) {
            $indexEtape++;
            $nomVille    = $villeEtape['VILLE_ARRET'];
            $nomVilleEsc = str_replace("'", "''", $nomVille);
            $estTerminus = ($indexEtape === $totalEtapes);

            $tabHoraires = ExecSelect($conn, "
                SELECT TO_CHAR(n.noe_heure_passage, 'HH24:MI:SS') AS HEURE_PASSAGE
                FROM vik_noeud n
                JOIN vik_commune c ON c.com_code_insee = n.com_code_insee_arret
                WHERE n.lig_num = '$ligneEsc'
                  AND c.com_nom = '$nomVilleEsc'
                ORDER BY n.noe_heure_passage ASC
            ");

            echo '<tr>';

            // Colonne ville
            echo '<td class="col-ville">';
            echo '<h2>' . htmlspecialchars($nomVille) . '</h2>';
            if ($estTerminus) {
                echo '<span class="terminus-label">Terminus</span>';
            } elseif (empty($tabHoraires)) {
                echo '<span class="terminus-label">Arrêt terminus</span>';
            }
            echo '</td>';

            // Colonne heures
            echo '<td class="col-heures"><div class="heures-wrap">';

            if (!empty($tabHoraires)) {
                foreach ($tabHoraires as $heure) {
                    $heureAffichage = substr($heure['HEURE_PASSAGE'], 0, 5);
                    $passe = (date('H:i') > $heureAffichage) ? ' passe' : '';
                    echo '<span class="heure' . $passe . '">' . $heureAffichage . '</span>';
                }
            } else {
                $tabHorairesTerminus = ExecSelect($conn, "
                    SELECT TO_CHAR(
                        n.noe_heure_passage + NUMTODSINTERVAL(n.noe_duree_prochain, 'MINUTE'),
                        'HH24:MI'
                    ) AS HEURE_PASSAGE
                    FROM vik_noeud n
                    WHERE n.lig_num = '$ligneEsc'
                      AND n.com_code_insee_suivant = '$codeTerminus'
                    ORDER BY n.noe_heure_passage ASC
                ");

                foreach ($tabHorairesTerminus as $heure) {
                    $heureAffichage = $heure['HEURE_PASSAGE'];
                    $passe = (date('H:i') > $heureAffichage) ? ' passe' : '';
                    echo '<span class="heure' . $passe . '">' . $heureAffichage . '</span>';
                }
            }

            echo '</div></td>';
            echo '</tr>';
        }

        $conn = null;

    } else {
        echo '<tr><td class="col-ville"><h2>Aucune ligne sélectionnée</h2></td><td class="col-heures"></td></tr>';
    }
    ?>
    </table>
</main>

<?php include_once 'footer.php'; ?>
</body>
</html>