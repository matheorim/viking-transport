<?php
session_start();
require_once '../PHP/utils.php';

$conn = OuvrirConn();

$lig_num          = $_POST['lig_num'] ?? '';
$insee_depart     = $_POST['insee_depart'] ?? '';
$insee_arrivee    = $_POST['insee_arrivee'] ?? '';
$distance_trajet  = floatval($_POST['distance_trajet'] ?? 0);
$tar_num_tranche  = intval($_POST['tar_num_tranche'] ?? 0);
$points_a_utiliser = isset($_POST['utiliser_points']) ? intval($_POST['utiliser_points']) : 0;
$nom              = htmlspecialchars($_POST['nom'] ?? '');
$prenom           = htmlspecialchars($_POST['prenom'] ?? '');

// --- NOUVEAU : Récupération de la date de réservation choisie ---
$date_reservation = $_POST['date_reservation'] ?? date('Y-m-d'); 

$vient_sans_compte = isset($_POST['sans_compte']) && $_POST['sans_compte'] == '1';
$est_connecte      = !$vient_sans_compte && isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] == true;
$cli_num           = $est_connecte ? intval($_SESSION['cli_num'] ?? 0) : 0;

$hdep = trim($_POST['hdep'] ?? '00:00');
if (strlen($hdep) === 4) $hdep = '0' . $hdep;
if (strlen($hdep) !== 5) $hdep = '00:00';

$reservation_reussie = false;
$erreur_msg = '';
$points_gagnes = 0;
$nouveau_solde_ec = 0; // Solde courant (utilisable)
$nouveau_solde_tot = 0; // Solde total (fidélité à vie)
$next_res = 0;

if ($conn) {
    try {
        $reqTarif = ExecSelect($conn, "SELECT TAR_PRIX FROM VIK_TARIF WHERE TAR_NUM_TRANCHE = $tar_num_tranche");
        $prix_base = !empty($reqTarif) ? floatval($reqTarif[0]['TAR_PRIX']) : 0;

        $typ_reduc = 100;
        $points_actuels_ec = 0;
        $points_actuels_tot = 0;

        if ($est_connecte && $cli_num > 0) {
            $reqClient = ExecSelect($conn, "SELECT CLI_NB_POINTS_EC, CLI_NB_POINTS_TOT 
                                            FROM VIK_CLIENT 
                                            WHERE CLI_NUM = $cli_num");
            
            if (!empty($reqClient)) {
                $points_actuels_ec  = intval($reqClient[0]['CLI_NB_POINTS_EC']);
                $points_actuels_tot = intval($reqClient[0]['CLI_NB_POINTS_TOT']);
                
                if ($points_actuels_tot >= 50000) { $typ_reduc = 50; }
                elseif ($points_actuels_tot >= 10000) { $typ_reduc = 65; }
                elseif ($points_actuels_tot >= 3000) { $typ_reduc = 80; }
                elseif ($points_actuels_tot >= 400) { $typ_reduc = 90; }
                elseif ($points_actuels_tot >= 10) { $typ_reduc = 95; }
                else { $typ_reduc = 100; }
            }
        }

        $prix_final = $prix_base * ($typ_reduc / 100);

        if ($est_connecte && $points_a_utiliser > 0) {
            $remise = ($points_a_utiliser == 100) ? 15 : (($points_a_utiliser == 50) ? 7 : 1);
            $prix_final -= $remise;
        }

        $prix_final = max(0, $prix_final);
        $points_gagnes = $est_connecte ? max(1, floor($distance_trajet / 10)) : 0;
        
        if ($est_connecte) {
            $nouveau_solde_ec = $points_actuels_ec - $points_a_utiliser + $points_gagnes;
            $nouveau_solde_tot = $points_actuels_tot + $points_gagnes;
        }

        $resMax = ExecSelect($conn, "SELECT NVL(MAX(RES_NUM), 0) + 1 AS NEXT_RES FROM VIK_RESERVATION");
        $next_res = intval($resMax[0]['NEXT_RES']);

        if ($est_connecte && $cli_num > 0) {
            // FIX : Remplacement de SYSDATE par TO_DATE('$date_reservation', 'YYYY-MM-DD')
            ExecMAJ($conn, "INSERT INTO VIK_RESERVATION (CLI_NUM, RES_NUM, RES_PRIX_TOT, TAR_NUM_TRANCHE, RES_DATE, RES_NB_POINTS) 
                             VALUES ($cli_num, $next_res, $prix_final, $tar_num_tranche, TO_DATE('$date_reservation', 'YYYY-MM-DD'), $points_gagnes)");

            ExecMAJ($conn, "INSERT INTO VIK_ETAPE (LIG_NUM, CLI_NUM, RES_NUM, ETA_DISTANCE, ETA_HEURE, COM_CODE_INSEE_DEPART, COM_CODE_INSEE_ARRIVEE) 
                             VALUES ('$lig_num', $cli_num, $next_res, $distance_trajet, TO_DATE('$hdep', 'HH24:MI'), '$insee_depart', '$insee_arrivee')");

            $var_pts = $points_gagnes - $points_a_utiliser;
            ExecMAJ($conn, "UPDATE VIK_CLIENT 
                             SET CLI_NB_POINTS_EC = CLI_NB_POINTS_EC + ($var_pts), 
                                 CLI_NB_POINTS_TOT = CLI_NB_POINTS_TOT + ($points_gagnes) 
                             WHERE CLI_NUM = $cli_num");
        } else {
            // FIX : Remplacement de SYSDATE par TO_DATE('$date_reservation', 'YYYY-MM-DD')
            ExecMAJ($conn, "INSERT INTO VIK_RESERVATION (CLI_NUM, RES_NUM, RES_PRIX_TOT, TAR_NUM_TRANCHE, RES_DATE, RES_NB_POINTS) 
                             VALUES (0, $next_res, $prix_final, $tar_num_tranche, TO_DATE('$date_reservation', 'YYYY-MM-DD'), 0)");

            ExecMAJ($conn, "INSERT INTO VIK_ETAPE (LIG_NUM, CLI_NUM, RES_NUM, ETA_DISTANCE, ETA_HEURE, COM_CODE_INSEE_DEPART, COM_CODE_INSEE_ARRIVEE) 
                             VALUES ('$lig_num', 0, $next_res, $distance_trajet, TO_DATE('$hdep', 'HH24:MI'), '$insee_depart', '$insee_arrivee')");
        }

        $reservation_reussie = true;

    } catch (Exception $e) {
        $reservation_reussie = false;
        $erreur_msg = $e->getMessage();
    }
}

$conn = null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Confirmation - Viking Transport</title>
    <link rel="stylesheet" href="../CSS/Header.css" />
    <link rel="stylesheet" href="../CSS/tarif_reserv.css" />
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
</head>
<body>
<?php include_once "header.php"; ?>

<main class="recap-container">
    <div class="ticket-caisse" style="text-align: center; max-width: 580px; margin: 0 auto;">
        <?php if ($reservation_reussie): ?>
            <h2 style="margin-top: 15px; font-size: 26px;">✔ RÉSERVATION VALIDÉE</h2> 
            
            <?php if ($prenom || $nom): ?>
                <p style="margin-bottom: 8px; font-size: 20px;">Bonjour <?= $prenom ?> <?= $nom ?>,</p>
            <?php endif; ?>
            
            <p style="margin-top: 0; font-size: 20px;">Réservation N° <?= $next_res ?> enregistrée.</p>
            
            <?php if ($est_connecte): ?>
                <div style="margin-top: 25px; font-size: 19px; line-height: 1.8;">
                    <p style="margin: 6px 0;">Points gagnés : +<?= $points_gagnes ?> pts</p>
                    <p style="margin: 6px 0;">Nouveau solde : <?= $nouveau_solde_ec ?> pts</p>
                    <p style="margin: 6px 0; font-weight: bold;">Solde total fidélité : <?= $nouveau_solde_tot ?> pts</p>
                </div>
            <?php else: ?>
                <p style="margin-top: 20px; font-size: 18px;">Créez un compte pour cumuler des points à chaque trajet !</p>
            <?php endif; ?>
            
            <a href="ligne.php" class="btn-payer" style="text-decoration: none;">RETOUR AU RÉSEAU</a>
        <?php else: ?>
            <h2 style="color: #D81F2C; margin-top: 15px; font-size: 26px;">✕ ÉCHEC DE L'OPÉRATION</h2>
            <p style="margin: 25px 0; font-size: 18px; line-height: 1.6;"> 
                Une erreur matérielle est survenue lors de l'écriture dans la base de données.<br><br>
                <small style="color: #666; font-size: 14px;"><?= htmlspecialchars($erreur_msg) ?></small>
            </p>
            <a href="formulaire.php" class="btn-payer" style="text-decoration: none;">RÉESSAYER</a>
        <?php endif; ?>
    </div>
</main>
<?php include_once "footer.php"; ?>
</body>
</html>