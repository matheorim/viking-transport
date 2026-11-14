<?php
session_start();
include_once "../PHP/utils.php";

$conn = OuvrirConn();

// --- LECTURE DU FORMAT MULTI-ÉTAPES ---
$etapes = $_POST['etapes'] ?? [];
$nom = $_POST['nom'] ?? '';
$prenom = $_POST['prenom'] ?? '';
$date_reservation = $_POST['date_reservation'] ?? '';
$vient_sans_compte = isset($_POST['sans_compte']) && $_POST['sans_compte'] == '1';

// Formatage pour un bel affichage sur le billet
$date_affichage = !empty($date_reservation) ? date('d/m/Y', strtotime($date_reservation)) : 'Date inconnue';

// Compatibilité si on reçoit l'ancien format
if (empty($etapes) && isset($_POST['arret_depart'])) {
    $etapes[0] = [
        'ville_depart_nom'  => $_POST['arret_depart'],
        'ville_arrivee_nom' => $_POST['arret_arrivee'],
        'ligne'             => $_POST['ligne'] ?? '',
        'horaire'           => $_POST['horaire'] ?? '00:00'
    ];
}

$distance_trajet = 0;
$nom_depart = "Origine inconnue";
$nom_arrivee = "Destination inconnue";
$prix_base = 0;
$tar_num_tranche = 0;
$typ_reduc = 100;
$typ_nom = "Standard";
$points_client = 0;

$insee_depart_global  = '';
$insee_arrivee_global = '';
$hdep_initial         = '';
$lig_num_initial      = '';
$noms_villes_trajet   = [];

function calculerDistanceVraie($conn, $ligne, $depart, $arrivee) {
    $lig = str_replace("'", "''", $ligne);
    $req = "SELECT DISTINCT COM_CODE_INSEE_ARRET, COM_CODE_INSEE_SUIVANT, NOE_DISTANCE_PROCHAIN
            FROM VIK_NOEUD
            WHERE LIG_NUM = '$lig'";
    $segments = ExecSelect($conn, $req);

    $graph = [];
    foreach ($segments as $seg) {
        if (!empty($seg['COM_CODE_INSEE_SUIVANT'])) {
            $graph[$seg['COM_CODE_INSEE_ARRET']][] = [
                'suivant'  => $seg['COM_CODE_INSEE_SUIVANT'],
                'distance' => floatval($seg['NOE_DISTANCE_PROCHAIN'])
            ];
        }
    }

    $file = [['noeud' => $depart, 'dist' => 0, 'visites' => [$depart]]];
    $iterations = 0;

    while (!empty($file)) {
        $iterations++;
        if ($iterations > 1000) return 0;

        $courant = array_shift($file);
        if ($courant['noeud'] === $arrivee) {
            return $courant['dist'];
        }

        if (isset($graph[$courant['noeud']])) {
            foreach ($graph[$courant['noeud']] as $voisin) {
                if (!in_array($voisin['suivant'], $courant['visites'])) {
                    $nv_visites = $courant['visites'];
                    $nv_visites[] = $voisin['suivant'];
                    $file[] = [
                        'noeud'   => $voisin['suivant'],
                        'dist'    => $courant['dist'] + $voisin['distance'],
                        'visites' => $nv_visites
                    ];
                }
            }
        }
    }
    return 0;
}

if ($conn && !empty($etapes)) {
    $premiere_etape = reset($etapes);
    $derniere_etape = end($etapes);

    $insee_depart_global  = !empty($premiere_etape['ville_depart_insee']) ? $premiere_etape['ville_depart_insee'] : ($premiere_etape['ville_depart_nom'] ?? '');
    $insee_arrivee_global = !empty($derniere_etape['ville_arrivee_insee']) ? $derniere_etape['ville_arrivee_insee'] : ($derniere_etape['ville_arrivee_nom'] ?? '');

    $hdep_initial    = $premiere_etape['horaire'] ?? '00:00';
    $lig_num_initial = $premiere_etape['ligne'] ?? '';

    // Lister toutes les villes du trajet sans doublons
    $insee_etapes = [];
    $first = true;
    foreach ($etapes as $etape) {
        $inseeD = !empty($etape['ville_depart_insee']) ? $etape['ville_depart_insee'] : ($etape['ville_depart_nom'] ?? '');
        $inseeA = !empty($etape['ville_arrivee_insee']) ? $etape['ville_arrivee_insee'] : ($etape['ville_arrivee_nom'] ?? '');

        if ($first) {
            $insee_etapes[] = $inseeD;
            $first = false;
        }
        $insee_etapes[] = $inseeA;
    }

    $insee_sql = implode("', '", array_map(function($i) {
        return str_replace("'", "''", $i);
    }, $insee_etapes));

    if (!empty($insee_sql)) {
        $reqNomsEtapes = "SELECT COM_CODE_INSEE, COM_NOM FROM VIK_COMMUNE WHERE COM_CODE_INSEE IN ('$insee_sql')";
        $resNomsEtapes = ExecSelect($conn, $reqNomsEtapes);

        $dico_villes = [];
        foreach ($resNomsEtapes as $v) {
            $dico_villes[$v['COM_CODE_INSEE']] = $v['COM_NOM'];
        }

        foreach ($insee_etapes as $insee) {
            $noms_villes_trajet[] = $dico_villes[$insee] ?? 'Inconnu';
        }
    }

    $nom_depart = $noms_villes_trajet[0] ?? $nom_depart;
    $nom_arrivee = end($noms_villes_trajet) ?: $nom_arrivee;

    // Calcul de la distance totale
    foreach ($etapes as $etape) {
        $inseeD = !empty($etape['ville_depart_insee']) ? $etape['ville_depart_insee'] : ($etape['ville_depart_nom'] ?? '');
        $inseeA = !empty($etape['ville_arrivee_insee']) ? $etape['ville_arrivee_insee'] : ($etape['ville_arrivee_nom'] ?? '');
        $lig    = $etape['ligne'] ?? '';

        if ($inseeD && $inseeA && $lig) {
            $dist_etape = calculerDistanceVraie($conn, $lig, $inseeD, $inseeA);
            $distance_trajet += $dist_etape;
        }
    }

    if ($distance_trajet <= 0) $distance_trajet = 10;
    $distance_trajet = round($distance_trajet, 1);

    // Récupération du tarif
    $reqTarif = "SELECT TAR_PRIX, TAR_NUM_TRANCHE
                 FROM VIK_TARIF
                 WHERE $distance_trajet BETWEEN TAR_MIN_DIST AND TAR_MAX_DIST";
    $resTarif = ExecSelect($conn, $reqTarif);

    if (!empty($resTarif)) {
        $prix_base = floatval($resTarif[0]['TAR_PRIX']);
        $tar_num_tranche = intval($resTarif[0]['TAR_NUM_TRANCHE']);
    }

    // Gestion client
    if (!$vient_sans_compte && isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] == true) {
        $cli_num = isset($_SESSION['cli_num']) ? intval($_SESSION['cli_num']) : 1;
        $reqClient = "SELECT c.CLI_NB_POINTS_EC, c.CLI_NB_POINTS_TOT, t.TYP_REDUC, t.TYP_NOM
                      FROM VIK_CLIENT c
                      JOIN VIK_TYPE_CLIENT t ON c.TYP_NUM = t.TYP_NUM
                      WHERE c.CLI_NUM = $cli_num";
        $resClient = ExecSelect($conn, $reqClient);

        if (!empty($resClient)) {
            $points_client = intval($resClient[0]['CLI_NB_POINTS_EC']);
            $points_tot = intval($resClient[0]['CLI_NB_POINTS_TOT']);

            if ($points_tot >= 50000) { $typ_nom = 'Or'; $typ_reduc = 50; }
            elseif ($points_tot >= 10000) { $typ_nom = 'Argent'; $typ_reduc = 65; }
            elseif ($points_tot >= 3000) { $typ_nom = 'Junior'; $typ_reduc = 80; }
            elseif ($points_tot >= 400) { $typ_nom = 'Poussin'; $typ_reduc = 90; }
            elseif ($points_tot >= 10) { $typ_nom = 'Nouveau'; $typ_reduc = 95; }
            else { $typ_nom = 'Standard'; $typ_reduc = 100; }
        }
    }
} else {
    $noms_villes_trajet = [$nom_depart, $nom_arrivee];
}

$pourcentage_reduction = 100 - $typ_reduc;
$montant_reduction_fidelite = $prix_base * ($pourcentage_reduction / 100);
$prix_apres_fidelite = $prix_base - $montant_reduction_fidelite;

$paliers_disponibles = [];
if ($points_client >= 100 && $prix_apres_fidelite >= 15) {
    $paliers_disponibles[] = ['points' => 100, 'remise' => 15];
}
if ($points_client >= 50 && $prix_apres_fidelite >= 7) {
    $paliers_disponibles[] = ['points' => 50, 'remise' => 7];
}
if ($points_client >= 10 && $prix_apres_fidelite >= 1) {
    $paliers_disponibles[] = ['points' => 10, 'remise' => 1];
}

$conn = null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Récapitulatif - Viking Transport</title>
    <link rel="stylesheet" href="../CSS/Header.css" />
    <link rel="stylesheet" href="../CSS/tarif_reserv.css" />
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
</head>
<body>

<?php include_once "header.php"; ?>

<main class="recap-container">
    <h1>Résumé de la commande</h1>

    <form method="POST" action="traitement_reservation.php">
        <div class="ticket-caisse">
            <div class="ticket-body">

                <input type="hidden" name="lig_num" value="<?= htmlspecialchars($lig_num_initial) ?>" />
                <input type="hidden" name="hdep" value="<?= htmlspecialchars($hdep_initial) ?>" />
                <input type="hidden" name="insee_depart" value="<?= htmlspecialchars($insee_depart_global) ?>" />
                <input type="hidden" name="insee_arrivee" value="<?= htmlspecialchars($insee_arrivee_global) ?>" />
                <input type="hidden" name="distance_trajet" value="<?= $distance_trajet ?>" />
                <input type="hidden" name="tar_num_tranche" value="<?= $tar_num_tranche ?>" />
                <input type="hidden" name="date_reservation" value="<?= htmlspecialchars($date_reservation) ?>" />
                <input type="hidden" name="sans_compte" value="<?= $vient_sans_compte ? '1' : '0' ?>" />
                <input type="hidden" name="nom" value="<?= htmlspecialchars($nom) ?>" />
                <input type="hidden" name="prenom" value="<?= htmlspecialchars($prenom) ?>" />

                <div class="ticket-header-info">
                    VIKING TRANSPORT SAS<br>
                    Caen, Normandie<br>
                    SIRET: 456 789 123 00012<br>
                    N° TVA: FR 88 45678912
                </div>

                <h2>Billet de transport</h2>

                <?php if ($vient_sans_compte && $nom): ?>
                <div class="ticket-trajet">
                    <div class="ticket-trajet-detail">
                        <?= htmlspecialchars($prenom) ?> <?= htmlspecialchars($nom) ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="ticket-trajet">
                    <div class="ticket-trajet-villes" style="line-height: 1.4;">
                        <?php
                            $villes_html = array_map('htmlspecialchars', $noms_villes_trajet);
                            echo implode(' &rarr; ', $villes_html);
                        ?>
                    </div>
                    <div class="ticket-trajet-detail">
                        LE <?= $date_affichage ?> &nbsp;|&nbsp;
                        <?php if (count($etapes) > 1): ?>
                            TRAJET EN <?= count($etapes) ?> ÉTAPES &nbsp;|&nbsp;
                        <?php else: ?>
                            LIGNE <?= htmlspecialchars($lig_num_initial) ?> &nbsp;|&nbsp;
                        <?php endif; ?>
                        DÉPART <?= htmlspecialchars($hdep_initial) ?> &nbsp;|&nbsp;
                        <?= $distance_trajet ?> KM
                    </div>
                </div>

                <div class="ligne-detail">
                    <span>Prix du trajet</span>
                    <span><?= number_format($prix_base, 2, ',', ' ') ?> €</span>
                </div>

                <?php if ($pourcentage_reduction > 0): ?>
                <div class="ligne-detail reduc">
                    <span>Fidélité <?= htmlspecialchars($typ_nom) ?> (-<?= $pourcentage_reduction ?>%)</span>
                    <span>- <?= number_format($montant_reduction_fidelite, 2, ',', ' ') ?> €</span>
                </div>
                <?php endif; ?>

                <div class="ligne-total-intermediaire">
                    <span>Sous-total</span>
                    <span id="sous-total" data-val="<?= $prix_apres_fidelite ?>">
                        <?= number_format($prix_apres_fidelite, 2, ',', ' ') ?> €
                    </span>
                </div>

                <?php if (!empty($paliers_disponibles)): ?>
                <div class="zone-points">
                    <p><strong>Solde points : <?= $points_client ?> pts</strong><br>
                    Choisissez la remise à appliquer :</p>

                    <label class="switch-points" style="display: block; margin-bottom: 8px; font-weight: normal;">
                        <input type="radio" name="utiliser_points" value="0" data-remise="0" checked>
                        Ne pas utiliser de points
                    </label>

                    <?php foreach($paliers_disponibles as $palier): ?>
                    <label class="switch-points" style="display: block; margin-bottom: 8px; font-weight: normal;">
                        <input type="radio" name="utiliser_points" value="<?= $palier['points'] ?>" data-remise="<?= $palier['remise'] ?>">
                        Utiliser <?= $palier['points'] ?> pts (- <?= number_format($palier['remise'], 2, ',', ' ') ?> €)
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="ligne-detail reduc" id="ligne-remise-points" style="display:none;">
                    <span>Remise points</span>
                    <span id="texte-remise-points">- 0,00 €</span>
                </div>
                <?php else: ?>
                <div class="zone-points">
                    <p style="color:#888;">
                        <?php if ($vient_sans_compte): ?>
                            Connectez-vous pour bénéficier de réductions et cumuler des points.
                        <?php else: ?>
                            Points ou montant insuffisants pour déclencher un palier de réduction.<br>
                            Solde actuel : <?= $points_client ?> pts.
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>

                <div class="ligne-total-final">
                    <span>Total TTC</span>
                    <span id="total-final"><?= number_format($prix_apres_fidelite, 2, ',', ' ') ?> €</span>
                </div>

                <div class="ticket-footer">
                    MERCI DE VOTRE VISITE<br>
                    À BIENTÔT SUR NOS LIGNES<br>
                    <div class="ticket-barcode">||||| || ||| | |||| |||</div>
                </div>

                <button type="submit" class="btn-payer">Passer en caisse</button>
            </div>
            <div class="ticket-bas"></div>
        </div>
    </form>
</main>

<?php include_once "footer.php"; ?>
<script src="../Scripts/tarif_reserv.js"></script>
</body>
</html>