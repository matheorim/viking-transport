<?php
// ─────────────────────────────────────────────
//  Connexion BDD  (adapter selon votre config)
// ─────────────────────────────────────────────
$host   = 'HOST:1521';
$dbname = 'SERVICE';
$user   = 'VOTRE_UTILISATEUR';
$pass   = 'VOTRE_MOT_DE_PASSE';

$pdo = null;
$erreurBDD = false;
try {
    $pdo = new PDO("oci:dbname=//$host/$dbname", $user, $pass);
    // Pour MySQL : $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $erreurBDD = true;
}

// ─────────────────────────────────────────────
//  Charger la liste des communes pour l'autocomplete
// ─────────────────────────────────────────────
$communes = [];
if ($pdo) {
    $stm = $pdo->query("SELECT COM_CODE_INSEE, COM_NOM FROM VIK_COMMUNE ORDER BY COM_NOM");
    $communes = $stm->fetchAll(PDO::FETCH_ASSOC);
}

// ─────────────────────────────────────────────
//  Recherche d'itinéraires
// ─────────────────────────────────────────────
$depart_insee   = $_GET['depart']  ?? '';
$arrivee_insee  = $_GET['arrivee'] ?? '';
$depart_nom     = '';
$arrivee_nom    = '';
$itineraires    = [];
$erreurRecherche = '';

if ($depart_insee && $arrivee_insee && $pdo) {
    // Noms des communes sélectionnées
    $stmNoms = $pdo->prepare("SELECT COM_NOM FROM VIK_COMMUNE WHERE COM_CODE_INSEE = :c");
    $stmNoms->execute([':c' => $depart_insee]);
    $depart_nom = $stmNoms->fetchColumn() ?: $depart_insee;

    $stmNoms->execute([':c' => $arrivee_insee]);
    $arrivee_nom = $stmNoms->fetchColumn() ?: $arrivee_insee;

    if ($depart_insee === $arrivee_insee) {
        $erreurRecherche = "Le départ et l'arrivée doivent être différents.";
    } else {
        /*
         * Recherche des trajets (réservations) existants entre ces deux communes,
         * regroupés par réservation avec leurs étapes, lignes, distance et horaires.
         *
         * Un voyage = 1 réservation (RES_NUM / CLI_NUM)
         * composé de N étapes (VIK_ETAPE), chacune sur une ligne (VIK_LIGNE).
         * La distance d'une étape est ETA_DISTANCE.
         * L'heure de départ/arrivée se calcule via VIK_NOEUD (NOE_HEURE_PASSAGE).
         * Le tarif global est dans VIK_TARIF via TAR_NUM_TRANCHE.
         */
        try {
            // ── Étape 1 : trouver les voyages (réservations) qui partent du départ et arrivent à l'arrivée ──
            $sqlVoyages = "
                SELECT DISTINCT
                    r.CLI_NUM,
                    r.RES_NUM,
                    r.RES_PRIX_TOT,
                    t.TAR_MIN_DIST,
                    t.TAR_MAX_DIST,
                    t.TAR_PRIX
                FROM VIK_RESERVATION r
                JOIN VIK_TARIF        t ON t.TAR_NUM_TRANCHE = r.TAR_NUM_TRANCHE
                WHERE r.CLI_NUM IN (
                    SELECT e1.CLI_NUM
                    FROM   VIK_ETAPE e1
                    WHERE  e1.COM_CODE_INSEE_DEPART  = :dep
                )
                AND r.RES_NUM IN (
                    SELECT e2.RES_NUM
                    FROM   VIK_ETAPE e2
                    WHERE  e2.COM_CODE_INSEE_ARRIVEE = :arr
                )
                ORDER BY r.RES_PRIX_TOT ASC
                FETCH FIRST 10 ROWS ONLY
            ";
            /*
             * NOTE : si la base ne contient pas encore de réservations,
             * on bascule sur une recherche par nœuds/lignes (voir bloc alternatif ci-dessous).
             */
            $stmV = $pdo->prepare($sqlVoyages);
            $stmV->execute([':dep' => $depart_insee, ':arr' => $arrivee_insee]);
            $voyages = $stmV->fetchAll(PDO::FETCH_ASSOC);

            // ── Étape 2 : pour chaque voyage, récupérer les étapes ordonnées ──
            foreach ($voyages as $v) {
                $sqlEtapes = "
                    SELECT
                        e.LIG_NUM,
                        l.COM_CODE_INSEE_DEBU,
                        l.COM_CODE_INSEE_TERM,
                        cd.COM_NOM  AS NOM_DEP,
                        ca.COM_NOM  AS NOM_ARR,
                        e.ETA_DISTANCE,
                        e.ETA_HEURE,
                        e.COM_CODE_INSEE_DEPART,
                        e.COM_CODE_INSEE_ARRIVEE,
                        -- heure départ du nœud
                        nd.NOE_HEURE_PASSAGE AS HEURE_DEP,
                        -- heure arrivée = heure du nœud suivant (approx via la durée)
                        nd.NOE_DUREE_PROCHAIN
                    FROM   VIK_ETAPE   e
                    JOIN   VIK_LIGNE   l  ON l.LIG_NUM             = e.LIG_NUM
                    JOIN   VIK_COMMUNE cd ON cd.COM_CODE_INSEE      = e.COM_CODE_INSEE_DEPART
                    JOIN   VIK_COMMUNE ca ON ca.COM_CODE_INSEE      = e.COM_CODE_INSEE_ARRIVEE
                    LEFT JOIN VIK_NOEUD nd ON nd.LIG_NUM            = e.LIG_NUM
                                          AND nd.COM_CODE_INSEE_ARRET = e.COM_CODE_INSEE_DEPART
                    WHERE  e.CLI_NUM  = :cli
                    AND    e.RES_NUM  = :res
                    ORDER BY e.ETA_HEURE ASC
                ";
                $stmE = $pdo->prepare($sqlEtapes);
                $stmE->execute([':cli' => $v['CLI_NUM'], ':res' => $v['RES_NUM']]);
                $etapes = $stmE->fetchAll(PDO::FETCH_ASSOC);

                if (empty($etapes)) continue;

                // Calcul totaux
                $distTotale = array_sum(array_column($etapes, 'ETA_DISTANCE'));
                $dureeTotale = array_sum(array_column($etapes, 'NOE_DUREE_PROCHAIN'));

                $itineraires[] = [
                    'cli_num'      => $v['CLI_NUM'],
                    'res_num'      => $v['RES_NUM'],
                    'prix_total'   => $v['RES_PRIX_TOT'],
                    'dist_totale'  => $distTotale,
                    'duree_totale' => $dureeTotale,
                    'etapes'       => $etapes,
                    'nb_lignes'    => count(array_unique(array_column($etapes, 'LIG_NUM'))),
                ];
            }

            // ── Fallback : recherche par nœuds si aucune réservation trouvée ──
            if (empty($itineraires)) {
                /*
                 * Recherche directe via les lignes passant par les deux communes (nœuds).
                 * On cherche les lignes qui ont à la fois un nœud dans la commune de départ
                 * et un nœud dans la commune d'arrivée, avec départ AVANT arrivée (heure).
                 */
                $sqlLignes = "
                    SELECT
                        n1.LIG_NUM,
                        cd.COM_NOM  AS NOM_DEP,
                        ca.COM_NOM  AS NOM_ARR,
                        n1.NOE_HEURE_PASSAGE  AS HEURE_DEP,
                        n2.NOE_HEURE_PASSAGE  AS HEURE_ARR,
                        n1.NOE_DISTANCE_PROCHAIN AS DIST_SEG,
                        -- distance approx = somme nœuds entre dep et arr : on utilise ETA_DISTANCE si dispo
                        0 AS PRIX
                    FROM VIK_NOEUD n1
                    JOIN VIK_NOEUD n2 ON n2.LIG_NUM = n1.LIG_NUM
                    JOIN VIK_COMMUNE cd ON cd.COM_CODE_INSEE = n1.COM_CODE_INSEE_ARRET
                    JOIN VIK_COMMUNE ca ON ca.COM_CODE_INSEE = n2.COM_CODE_INSEE_ARRET
                    WHERE n1.COM_CODE_INSEE_ARRET  = :dep
                    AND   n2.COM_CODE_INSEE_ARRET  = :arr
                    AND   n1.NOE_HEURE_PASSAGE    <= n2.NOE_HEURE_PASSAGE
                    ORDER BY n1.LIG_NUM
                ";
                $stmL = $pdo->prepare($sqlLignes);
                $stmL->execute([':dep' => $depart_insee, ':arr' => $arrivee_insee]);
                $lignes = $stmL->fetchAll(PDO::FETCH_ASSOC);

                foreach ($lignes as $lig) {
                    // Calcul durée en minutes
                    $heureDepStr = $lig['HEURE_DEP'];
                    $heureArrStr = $lig['HEURE_ARR'];
                    $dureeMin    = 0;
                    if ($heureDepStr && $heureArrStr) {
                        [$hd, $md] = array_map('intval', explode(':', substr($heureDepStr, 0, 5)));
                        [$ha, $ma] = array_map('intval', explode(':', substr($heureArrStr, 0, 5)));
                        $dureeMin = ($ha * 60 + $ma) - ($hd * 60 + $md);
                    }

                    // Distance via les nœuds de la ligne entre dep et arr
                    $sqlDist = "
                        SELECT SUM(nk.NOE_DISTANCE_PROCHAIN) AS DIST
                        FROM   VIK_NOEUD nk
                        WHERE  nk.LIG_NUM = :lig
                        AND    nk.NOE_HEURE_PASSAGE >= :hdep
                        AND    nk.NOE_HEURE_PASSAGE <  :harr
                    ";
                    $stmD = $pdo->prepare($sqlDist);
                    $stmD->execute([':lig' => $lig['LIG_NUM'], ':hdep' => $heureDepStr, ':harr' => $heureArrStr]);
                    $dist = (float)($stmD->fetchColumn() ?: 0);

                    // Tarif selon la distance
                    $prixBase = 0;
                    if ($dist > 0) {
                        $stmT = $pdo->prepare("SELECT TAR_PRIX FROM VIK_TARIF WHERE :d BETWEEN TAR_MIN_DIST AND TAR_MAX_DIST");
                        $stmT->execute([':d' => $dist]);
                        $prixBase = (float)($stmT->fetchColumn() ?: 0);
                    }

                    $itineraires[] = [
                        'cli_num'      => null,
                        'res_num'      => null,
                        'prix_total'   => $prixBase,
                        'dist_totale'  => $dist,
                        'duree_totale' => $dureeMin,
                        'nb_lignes'    => 1,
                        'etapes' => [[
                            'LIG_NUM'    => $lig['LIG_NUM'],
                            'NOM_DEP'    => $lig['NOM_DEP'],
                            'NOM_ARR'    => $lig['NOM_ARR'],
                            'ETA_DISTANCE' => $dist,
                            'HEURE_DEP'  => $heureDepStr,
                            'HEURE_ARR'  => $heureArrStr,
                            'NOE_DUREE_PROCHAIN' => $dureeMin,
                        ]],
                    ];
                }
            }

        } catch (PDOException $e) {
            $erreurRecherche = "Erreur lors de la recherche : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Itinéraires — Viking Transport</title>
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #343330;
            font-family: Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── TOPBAR ── */
        .vt-topbar {
            background: #343330;
            height: 70px;
            display: flex;
            align-items: center;
            padding: 0 32px;
            border-bottom: 1px solid #4a4845;
            flex-shrink: 0;
        }
        .vt-topbar a {
            color: #B5B8BA;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s;
        }
        .vt-topbar a:hover { color: #fff; }
        .vt-topbar a::before { content: "←"; font-size: 16px; }
        .vt-topbar-logo {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .vt-topbar-logo img { height: 48px; width: auto; }

        /* ── BODY ── */
        .page-body {
            flex: 1;
            padding: 40px 16px 60px;
            background: repeating-linear-gradient(
                45deg,
                transparent, transparent 60px,
                rgba(216,31,44,0.04) 60px, rgba(216,31,44,0.04) 120px
            );
        }

        .container {
            max-width: 860px;
            margin: 0 auto;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            text-align: center;
            margin-bottom: 36px;
        }
        .page-header img {
            height: 64px;
            width: auto;
            filter: drop-shadow(0 4px 12px rgba(216,31,44,0.3));
            margin-bottom: 12px;
        }
        .page-header h1 {
            font-size: 26px;
            font-weight: 900;
            color: #fff;
            letter-spacing: 1px;
        }
        .page-header p {
            font-size: 13px;
            color: #8D9195;
            margin-top: 6px;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-weight: 600;
        }

        /* ── SEARCH CARD ── */
        .search-card {
            background: #fff;
            border-radius: 12px;
            border-top: 4px solid #D81F2C;
            padding: 28px 28px 24px;
            margin-bottom: 32px;
        }
        .search-card h2 {
            font-size: 17px;
            font-weight: 700;
            color: #343330;
            margin-bottom: 20px;
        }
        .search-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 14px;
            align-items: end;
        }
        @media (max-width: 600px) {
            .search-grid { grid-template-columns: 1fr; }
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #343330;
            margin-bottom: 6px;
        }
        .field input, .field select {
            width: 100%;
            padding: 11px 14px;
            font-size: 14px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            outline: none;
            color: #343330;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .field input:focus, .field select:focus {
            border-color: #D81F2C;
            box-shadow: 0 0 0 3px rgba(216,31,44,0.12);
        }

        /* ── SWAP BUTTON ── */
        .swap-btn {
            background: none;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 12px;
            cursor: pointer;
            color: #8D9195;
            font-size: 18px;
            transition: border-color 0.2s, color 0.2s;
            align-self: end;
        }
        .swap-btn:hover { border-color: #D81F2C; color: #D81F2C; }

        .btn-search {
            display: block;
            width: 100%;
            margin-top: 18px;
            padding: 13px;
            background: #D81F2C;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-search:hover { background: #921520; }
        .btn-search:active { transform: scale(0.98); }

        /* ── ERREUR ── */
        .alert {
            background: #fff3f3;
            border: 1px solid #f5c6cb;
            border-left: 4px solid #D81F2C;
            color: #721c24;
            border-radius: 8px;
            padding: 14px 18px;
            font-size: 14px;
            margin-bottom: 24px;
        }

        /* ── RÉSULTATS ── */
        .results-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .results-title {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
        }
        .results-count {
            font-size: 13px;
            color: #8D9195;
            background: #2a2927;
            padding: 4px 12px;
            border-radius: 20px;
        }

        /* ── CARTE ITINÉRAIRE ── */
        .itin-card {
            background: #fff;
            border-radius: 12px;
            margin-bottom: 16px;
            overflow: hidden;
            border: 1px solid #ececec;
            transition: box-shadow 0.2s;
        }
        .itin-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .itin-summary {
            display: flex;
            align-items: center;
            gap: 0;
            padding: 18px 22px;
            cursor: pointer;
        }

        .itin-badge {
            background: #D81F2C;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 6px;
            margin-right: 16px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .itin-route {
            flex: 1;
            min-width: 0;
        }
        .itin-route-villes {
            font-size: 16px;
            font-weight: 700;
            color: #343330;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .itin-route-villes .arrow {
            color: #D81F2C;
            margin: 0 6px;
        }
        .itin-route-sub {
            font-size: 12px;
            color: #8D9195;
            margin-top: 3px;
        }

        .itin-meta {
            display: flex;
            gap: 28px;
            flex-shrink: 0;
            margin-left: 16px;
        }
        .itin-meta-item {
            text-align: center;
        }
        .itin-meta-item .val {
            font-size: 18px;
            font-weight: 800;
            color: #343330;
            line-height: 1;
        }
        .itin-meta-item .val.prix { color: #D81F2C; }
        .itin-meta-item .lbl {
            font-size: 11px;
            color: #8D9195;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        .btn-detail {
            flex-shrink: 0;
            margin-left: 20px;
            padding: 9px 18px;
            background: #fff;
            border: 2px solid #D81F2C;
            color: #D81F2C;
            font-size: 13px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .btn-detail:hover {
            background: #D81F2C;
            color: #fff;
        }
        .btn-detail.open {
            background: #D81F2C;
            color: #fff;
        }

        /* ── DÉTAIL ACCORDÉON ── */
        .itin-detail {
            display: none;
            border-top: 1px solid #f0f0f0;
            padding: 20px 22px 22px;
            background: #fafafa;
        }
        .itin-detail.visible { display: block; }

        .detail-title {
            font-size: 13px;
            font-weight: 700;
            color: #343330;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 14px;
        }

        /* Timeline des étapes */
        .timeline {
            position: relative;
            padding-left: 28px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 9px;
            top: 8px;
            bottom: 8px;
            width: 2px;
            background: linear-gradient(to bottom, #D81F2C, #f0a0a0);
            border-radius: 1px;
        }

        .tl-step {
            position: relative;
            margin-bottom: 18px;
        }
        .tl-step:last-child { margin-bottom: 0; }
        .tl-dot {
            position: absolute;
            left: -24px;
            top: 4px;
            width: 12px;
            height: 12px;
            background: #D81F2C;
            border: 2px solid #fff;
            border-radius: 50%;
            box-shadow: 0 0 0 2px #D81F2C;
        }
        .tl-dot.last-dot {
            background: #343330;
            box-shadow: 0 0 0 2px #343330;
        }

        .tl-ligne-badge {
            display: inline-block;
            background: #343330;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }
        .tl-villes {
            font-size: 14px;
            font-weight: 600;
            color: #343330;
        }
        .tl-villes .arr { color: #D81F2C; }
        .tl-infos {
            display: flex;
            gap: 20px;
            margin-top: 5px;
            flex-wrap: wrap;
        }
        .tl-info {
            font-size: 12px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .tl-info svg {
            width: 13px;
            height: 13px;
            color: #8D9195;
        }

        /* Totaux */
        .detail-totaux {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px dashed #e0e0e0;
            display: flex;
            gap: 0;
        }
        .total-item {
            flex: 1;
            text-align: center;
            padding: 10px 0;
        }
        .total-item + .total-item {
            border-left: 1px solid #e0e0e0;
        }
        .total-item .t-val {
            font-size: 20px;
            font-weight: 800;
            color: #343330;
        }
        .total-item .t-val.red { color: #D81F2C; }
        .total-item .t-lbl {
            font-size: 11px;
            color: #8D9195;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        .btn-reserver {
            display: block;
            width: 100%;
            margin-top: 18px;
            padding: 13px;
            background: #D81F2C;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-reserver:hover { background: #921520; }
        .btn-reserver:active { transform: scale(0.98); }

        /* ── VIDE ── */
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: #8D9195;
        }
        .empty-state svg {
            width: 48px;
            height: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        .empty-state p {
            font-size: 15px;
            font-weight: 600;
            color: #B5B8BA;
            margin-bottom: 6px;
        }
        .empty-state span {
            font-size: 13px;
        }

        @media (max-width: 640px) {
            .itin-meta { display: none; }
            .itin-summary { flex-wrap: wrap; gap: 10px; }
            .btn-detail { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- ── TOPBAR ── -->
<nav class="vt-topbar">
    <a href="../index.php">Retour au site</a>
    <div class="vt-topbar-logo">
        <img src="../Files/LogoVicking.png" alt="Viking Transport" onerror="this.style.display='none'" />
    </div>
</nav>

<div class="page-body">
    <div class="container">

        <!-- En-tête -->
        <div class="page-header">
            <img src="../Files/LogoVicking.png" alt="Viking Transport" onerror="this.style.display='none'" />
            <h1>VIKING TRANSPORT</h1>
            <p>Rechercher un itinéraire</p>
        </div>

        <!-- Formulaire de recherche -->
        <div class="search-card">
            <h2>Où souhaitez-vous aller ?</h2>
            <form method="GET" action="itineraires.php">
                <div class="search-grid">
                    <!-- Départ -->
                    <div class="field">
                        <label for="depart">Commune de départ</label>
                        <select name="depart" id="depart" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($communes as $c): ?>
                                <option value="<?= htmlspecialchars($c['COM_CODE_INSEE']) ?>"
                                    <?= $c['COM_CODE_INSEE'] === $depart_insee ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['COM_NOM']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Bouton échange -->
                    <button type="button" class="swap-btn" id="swap-btn" title="Inverser départ / arrivée">⇄</button>

                    <!-- Arrivée -->
                    <div class="field">
                        <label for="arrivee">Commune d'arrivée</label>
                        <select name="arrivee" id="arrivee" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($communes as $c): ?>
                                <option value="<?= htmlspecialchars($c['COM_CODE_INSEE']) ?>"
                                    <?= $c['COM_CODE_INSEE'] === $arrivee_insee ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['COM_NOM']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-search">
                    Rechercher les itinéraires
                </button>
            </form>
        </div>

        <?php if ($erreurBDD): ?>
        <div class="alert">
            Impossible de se connecter à la base de données. Veuillez vérifier la configuration.
        </div>
        <?php endif; ?>

        <?php if ($erreurRecherche): ?>
        <div class="alert"><?= htmlspecialchars($erreurRecherche) ?></div>
        <?php endif; ?>

        <?php if ($depart_isbn !== '' || ($depart_insee && $arrivee_insee && !$erreurRecherche)): ?>

        <!-- En-tête résultats -->
        <?php if ($depart_isbn || ($depart_nom && $arrivee_nom)): ?>
        <div class="results-header">
            <div class="results-title">
                <?= htmlspecialchars($depart_nom) ?> → <?= htmlspecialchars($arrivee_nom) ?>
            </div>
            <div class="results-count">
                <?= count($itineraires) ?> itinéraire<?= count($itineraires) > 1 ? 's' : '' ?> trouvé<?= count($itineraires) > 1 ? 's' : '' ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($itineraires) && $depart_insee && $arrivee_insee && !$erreurRecherche): ?>
        <!-- État vide -->
        <div class="itin-card">
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
                <p>Aucun itinéraire direct trouvé</p>
                <span>Essayez une autre combinaison de communes ou consultez le plan du réseau.</span>
            </div>
        </div>

        <?php else: ?>

        <!-- Liste des itinéraires -->
        <?php foreach ($itineraires as $i => $itin): ?>
        <?php
            $nbEtapes  = count($itin['etapes']);
            $nbLignes  = $itin['nb_lignes'];
            $dist      = round($itin['dist_totale'], 1);
            $prix      = number_format($itin['prix_total'], 2, ',', ' ');
            $dureeH    = floor($itin['duree_totale'] / 60);
            $dureeMin  = $itin['duree_totale'] % 60;
            $dureeStr  = ($dureeH > 0 ? $dureeH . 'h' : '') . str_pad($dureeMin, 2, '0', STR_PAD_LEFT) . 'min';

            // Résumé des lignes empruntées
            $lignesStr = implode(' + ', array_unique(array_map(fn($e) => 'Ligne ' . $e['LIG_NUM'], $itin['etapes'])));

            // Heure de départ du 1er nœud et heure d'arrivée du dernier
            $firstEtape = $itin['etapes'][0];
            $lastEtape  = end($itin['etapes']);
            $heureDepStr = isset($firstEtape['HEURE_DEP']) ? substr($firstEtape['HEURE_DEP'], 0, 5) : '–';
            $heureArrStr = '';
            if (isset($lastEtape['HEURE_ARR'])) {
                $heureArrStr = substr($lastEtape['HEURE_ARR'], 0, 5);
            } elseif (isset($lastEtape['HEURE_DEP']) && $itin['duree_totale'] > 0) {
                // Calcul approximatif
                [$hh, $mm] = array_map('intval', explode(':', $heureDepStr . ':00'));
                $totalMin = $hh * 60 + $mm + (int)$itin['duree_totale'];
                $heureArrStr = str_pad(floor($totalMin / 60) % 24, 2, '0', STR_PAD_LEFT) . 'h' . str_pad($totalMin % 60, 2, '0', STR_PAD_LEFT);
            }
        ?>
        <div class="itin-card" id="card-<?= $i ?>">
            <!-- Résumé cliquable -->
            <div class="itin-summary" onclick="toggleDetail(<?= $i ?>)">

                <div class="itin-badge"><?= $nbLignes ?> ligne<?= $nbLignes > 1 ? 's' : '' ?></div>

                <div class="itin-route">
                    <div class="itin-route-villes">
                        <?= htmlspecialchars($depart_nom) ?>
                        <span class="arrow">→</span>
                        <?= htmlspecialchars($arrivee_nom) ?>
                    </div>
                    <div class="itin-route-sub">
                        <?= htmlspecialchars($lignesStr) ?>
                        <?php if ($heureDepStr !== '–'): ?>
                            &nbsp;·&nbsp; Départ <?= $heureDepStr ?>
                            <?php if ($heureArrStr): ?>&nbsp;·&nbsp; Arrivée <?= $heureArrStr ?><?php endif; ?>
                        <?php endif; ?>
                        &nbsp;·&nbsp; <?= $nbEtapes ?> étape<?= $nbEtapes > 1 ? 's' : '' ?>
                    </div>
                </div>

                <div class="itin-meta">
                    <div class="itin-meta-item">
                        <div class="val"><?= $dist ?> <small style="font-size:12px;font-weight:600;">km</small></div>
                        <div class="lbl">Distance</div>
                    </div>
                    <?php if ($itin['duree_totale'] > 0): ?>
                    <div class="itin-meta-item">
                        <div class="val"><?= $dureeStr ?></div>
                        <div class="lbl">Durée</div>
                    </div>
                    <?php endif; ?>
                    <div class="itin-meta-item">
                        <div class="val prix"><?= $prix ?> <small style="font-size:12px;font-weight:600;">€</small></div>
                        <div class="lbl">Prix</div>
                    </div>
                </div>

                <button class="btn-detail" id="btn-<?= $i ?>" onclick="event.stopPropagation(); toggleDetail(<?= $i ?>)">
                    En savoir plus
                </button>
            </div>

            <!-- Détail accordéon -->
            <div class="itin-detail" id="detail-<?= $i ?>">
                <div class="detail-title">Détail du trajet</div>

                <div class="timeline">
                    <?php foreach ($itin['etapes'] as $j => $etape): ?>
                    <?php
                        $isLast     = ($j === count($itin['etapes']) - 1);
                        $distEtape  = round($etape['ETA_DISTANCE'] ?? 0, 1);
                        $hdep       = isset($etape['HEURE_DEP']) ? substr($etape['HEURE_DEP'], 0, 5) : null;
                        $harr       = isset($etape['HEURE_ARR']) ? substr($etape['HEURE_ARR'], 0, 5) : null;
                        $dureeEtMin = (int)($etape['NOE_DUREE_PROCHAIN'] ?? 0);
                        $dureeEtH   = floor($dureeEtMin / 60);
                        $dureeEtM   = $dureeEtMin % 60;
                        $dureeEtStr = ($dureeEtH > 0 ? $dureeEtH . 'h' : '') . str_pad($dureeEtM, 2, '0', STR_PAD_LEFT) . 'min';
                    ?>
                    <div class="tl-step">
                        <div class="tl-dot <?= $isLast ? 'last-dot' : '' ?>"></div>
                        <div class="tl-ligne-badge">Ligne <?= htmlspecialchars($etape['LIG_NUM'] ?? '–') ?></div>
                        <div class="tl-villes">
                            <?= htmlspecialchars($etape['NOM_DEP'] ?? '–') ?>
                            <span class="arr"> → <?= htmlspecialchars($etape['NOM_ARR'] ?? '–') ?></span>
                        </div>
                        <div class="tl-infos">
                            <?php if ($distEtape > 0): ?>
                            <div class="tl-info">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <?= $distEtape ?> km
                            </div>
                            <?php endif; ?>
                            <?php if ($hdep): ?>
                            <div class="tl-info">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Départ <?= $hdep ?><?= $harr ? ' · Arrivée ' . $harr : '' ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($dureeEtMin > 0): ?>
                            <div class="tl-info">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <?= $dureeEtStr ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Totaux -->
                <div class="detail-totaux">
                    <div class="total-item">
                        <div class="t-val"><?= $dist ?> km</div>
                        <div class="t-lbl">Distance totale</div>
                    </div>
                    <?php if ($itin['duree_totale'] > 0): ?>
                    <div class="total-item">
                        <div class="t-val"><?= $dureeStr ?></div>
                        <div class="t-lbl">Durée totale</div>
                    </div>
                    <?php endif; ?>
                    <div class="total-item">
                        <div class="t-val red"><?= $prix ?> €</div>
                        <div class="t-lbl">Prix total</div>
                    </div>
                    <div class="total-item">
                        <div class="t-val"><?= max(1, (int)($dist / 10)) ?> pts</div>
                        <div class="t-lbl">Points gagnés</div>
                    </div>
                </div>

                <!-- Bouton réservation -->
                <button class="btn-reserver"
                    onclick="reserverTrajet('<?= htmlspecialchars($depart_insee) ?>','<?= htmlspecialchars($arrivee_insee) ?>',<?= json_encode(array_map(fn($e) => $e['LIG_NUM'] ?? '', $itin['etapes'])) ?>)">
                    Réserver ce trajet
                </button>
            </div>
        </div>
        <?php endforeach; ?>

        <?php endif; /* fin if empty */ ?>
        <?php endif; /* fin if recherche */ ?>

    </div>
</div>

<script>
    // ── Accordéon ──
    function toggleDetail(idx) {
        const detail = document.getElementById('detail-' + idx);
        const btn    = document.getElementById('btn-' + idx);
        const isOpen = detail.classList.contains('visible');
        // Fermer tous les autres
        document.querySelectorAll('.itin-detail').forEach(d => d.classList.remove('visible'));
        document.querySelectorAll('.btn-detail').forEach(b => { b.classList.remove('open'); b.textContent = 'En savoir plus'; });
        if (!isOpen) {
            detail.classList.add('visible');
            btn.classList.add('open');
            btn.textContent = 'Réduire';
        }
    }

    // ── Swap communes ──
    document.getElementById('swap-btn').addEventListener('click', function () {
        const dep = document.getElementById('depart');
        const arr = document.getElementById('arrivee');
        const tmp = dep.value;
        dep.value = arr.value;
        arr.value = tmp;
    });

    // ── Réservation ──
    function reserverTrajet(depart, arrivee, lignes) {
        // Redirige vers la page de réservation en passant les paramètres
        const params = new URLSearchParams({
            depart:  depart,
            arrivee: arrivee,
            lignes:  lignes.join(',')
        });
        window.location.href = 'reservation.php?' + params.toString();
    }
</script>
</body>
</html>