<?php
session_start();
require_once '../PHP/utils.php';

if (!isset($_SESSION['cli_num'])) {
    header('Location: inscription.php');
    exit;
}

$cli_num  = $_SESSION['cli_num'];
$errorMsg = '';
$telephone = '';
$ville     = '';
$dep_nom   = '';
$rang      = '';
$rang_tour = '';

$conn = OuvrirConn();
if (!$conn) {
    die('Erreur de connexion à la base de données.');
}

// ── Infos client ──
try {
    $resClient = ExecSelect($conn, "SELECT CLI_TELEPHONE, CLI_VILLE, DEP_NUM, CLI_NB_POINTS_EC, CLI_NB_POINTS_TOT FROM VIK_CLIENT WHERE CLI_NUM = $cli_num");
    if (!empty($resClient)) {
        $telephone     = ($resClient[0]['CLI_TELEPHONE'] ?? $resClient[0]['cli_telephone'] ?? '') ?? '';
        $ville         = ($resClient[0]['CLI_VILLE']     ?? $resClient[0]['cli_ville']     ?? '') ?? '';
        $dep_num       = ($resClient[0]['DEP_NUM']       ?? $resClient[0]['dep_num']       ?? '') ?? '';
        $nb_points     = (int)(($resClient[0]['CLI_NB_POINTS_EC']  ?? $resClient[0]['cli_nb_points_ec']  ?? 0) ?? 0);
        $nb_points_tot = (int)(($resClient[0]['CLI_NB_POINTS_TOT'] ?? $resClient[0]['cli_nb_points_tot'] ?? 0) ?? 0);

        if      ($nb_points_tot >= 0     && $nb_points_tot <= 399)   { $rang = 'Nouveau'; }
        elseif  ($nb_points_tot >= 400   && $nb_points_tot <= 2999)  { $rang = 'Poussin'; }
        elseif  ($nb_points_tot >= 3000  && $nb_points_tot <= 9999)  { $rang = 'Junior';  }
        elseif  ($nb_points_tot >= 10000 && $nb_points_tot <= 49999) { $rang = 'Argent';  }
        elseif  ($nb_points_tot >= 50000)                            { $rang = 'Or';       }
        elseif  ($nb_points_tot < -10)   { $rang = 'Vainqueur du';  $rang_tour = 'tour de France'; }
        elseif  ($nb_points_tot == -10)  { $rang = 'Vainqueur de';  $rang_tour = 'Roland Garos';   }

        if ($dep_num) {
            $resDep  = ExecSelect($conn, "SELECT DEP_NOM FROM VIK_DEPARTEMENT WHERE DEP_NUM = " . intval($dep_num));
            $dep_nom = $resDep[0]['DEP_NOM'] ?? $resDep[0]['dep_nom'] ?? '';
        }
    }
} catch (Exception $e) {
    $errorMsg  = $e->getMessage();
    $nb_points = 0;
}

// ── Départements pour le formulaire ──
$departements = [];
try {
    $departements = ExecSelect($conn, "SELECT DEP_NUM, DEP_NOM FROM VIK_DEPARTEMENT ORDER BY DEP_NOM");
} catch (Exception $e) { }

// ── Message update ──
$updateMsg = '';
if (isset($_SESSION['update_msg'])) {
    $updateMsg = $_SESSION['update_msg'];
    unset($_SESSION['update_msg']);
}

// ════════════════════════════════════════════════════════════
//  RÉSERVATIONS
// ════════════════════════════════════════════════════════════
$reservations_futures = [];
$reservations_passees = [];
$detail_etapes        = [];
$maintenant           = new DateTime();

try {
    $sql_resa    = "select * from visu_historique_client where CLI_NUM=" . $cli_num;
    $toutes_resa = ExecSelect($conn, $sql_resa);

    foreach ($toutes_resa as $resa) {
        $date_str  = $resa['RES_DATE']     ?? $resa['res_date']     ?? '';
        $heure_str = $resa['HEURE_DEPART'] ?? $resa['heure_depart'] ?? '00:00:00';

        $dateObj = DateTime::createFromFormat('d/m/Y H:i', $date_str . ' ' . $heure_str);

        if (!$dateObj) {
            $dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $date_str . ' ' . $heure_str);
        }
        if (!$dateObj) {
            $dateObj = new DateTime();
        }

        $resa_norm = [
            'res_num'     => $resa['RES_NUM'] ?? '',
            'res_date'    => $dateObj->format('Y-m-d'),
            'heure_dep'   => substr($resa['HEURE_DEPART'] ?? $resa['heure_depart'] ?? '00:00', 0, 5),
            'points'      => $resa['RES_NB_POINTS'] ?? 0,
            'prix'        => $resa['RES_PRIX_TOT']  ?? 0,
            'dep_nom'     => $resa['VILLE_DEPART']  ?? 'N/A',
            'arr_nom'     => $resa['VILLE_ARRIVEE'] ?? 'N/A',
            'dist_totale' => (float)($resa['DISTANCE'] ?? 0),
            'nb_etapes'   => 0,
            'dt'          => $dateObj,
        ];

        if ($dateObj >= new DateTime('now')) { $reservations_futures[] = $resa_norm; }
        else                                { $reservations_passees[] = $resa_norm; }
    }
} catch (Exception $e) {
    $reservations_futures = [];
    $reservations_passees = [];
}

// ── Étapes détaillées ──
try {
    if (!empty($toutes_resa)) {
        $sql_etapes = "
            SELECT
                e.RES_NUM,
                TRIM(e.LIG_NUM)                          AS LIG_NUM,
                e.COM_CODE_INSEE_DEPART,
                e.COM_CODE_INSEE_ARRIVEE,
                TO_CHAR(e.ETA_HEURE, 'HH24:MI')          AS HEURE_DEP,
                e.ETA_DISTANCE,
                cd.COM_NOM                                AS DEP_NOM,
                ca.COM_NOM                                AS ARR_NOM,
                (
                    SELECT TO_CHAR(MIN(n.NOE_HEURE_PASSAGE), 'HH24:MI')
                    FROM VIK_NOEUD n
                    WHERE TRIM(n.LIG_NUM) = TRIM(e.LIG_NUM)
                    AND n.COM_CODE_INSEE_ARRET = e.COM_CODE_INSEE_ARRIVEE
                    AND TO_CHAR(n.NOE_HEURE_PASSAGE, 'HH24:MI:SS')
                        >= TO_CHAR(e.ETA_HEURE, 'HH24:MI:SS')
                ) AS HEURE_ARR
            FROM VIK_ETAPE e
            JOIN VIK_COMMUNE cd ON cd.COM_CODE_INSEE = e.COM_CODE_INSEE_DEPART
            JOIN VIK_COMMUNE ca ON ca.COM_CODE_INSEE = e.COM_CODE_INSEE_ARRIVEE
            WHERE e.CLI_NUM = " . intval($cli_num) . "
            ORDER BY e.RES_NUM, e.ETA_HEURE
        ";
        $rows_etapes = ExecSelect($conn, $sql_etapes);

        // Fetch all nodes for lines used by this client to reconstruct paths
        $sql_nodes = "
            SELECT 
                TRIM(n.LIG_NUM) as LIG_NUM, 
                n.COM_CODE_INSEE_ARRET, 
                n.COM_CODE_INSEE_SUIVANT, 
                TO_CHAR(n.NOE_HEURE_PASSAGE, 'HH24:MI') as HEURE, 
                c.COM_NOM
            FROM VIK_NOEUD n
            JOIN VIK_COMMUNE c ON c.COM_CODE_INSEE = n.COM_CODE_INSEE_ARRET
            WHERE TRIM(n.LIG_NUM) IN (SELECT DISTINCT TRIM(LIG_NUM) FROM VIK_ETAPE WHERE CLI_NUM = " . intval($cli_num) . ")
            ORDER BY n.LIG_NUM, n.NOE_HEURE_PASSAGE
        ";
        $all_nodes = ExecSelect($conn, $sql_nodes);
        
        // Indexer les nœuds par (ligne, arret, heure) pour suivre la chaîne COM_CODE_INSEE_SUIVANT
        $nodes_lookup = [];
        foreach ($all_nodes as $node) {
            $l = $node['LIG_NUM'];
            $a = trim($node['COM_CODE_INSEE_ARRET'] ?? '');
            $h = $node['HEURE'];
            $s = trim($node['COM_CODE_INSEE_SUIVANT'] ?? '');
            if ($l && $a && $h) {
                $nodes_lookup[$l][$a][$h] = ['nom' => $node['COM_NOM'], 'suivant' => $s];
            }
        }
        // Trier les horaires par arret pour le lookup du prochain nœud
        foreach ($nodes_lookup as $l => &$arrets_ref) {
            foreach ($arrets_ref as $a => &$heures_ref) {
                ksort($heures_ref);
            }
        }
        unset($arrets_ref, $heures_ref);

        foreach ($rows_etapes as $row) {
            $rnum      = $row['RES_NUM'];
            $lig       = $row['LIG_NUM'];
            $dep_insee = trim($row['COM_CODE_INSEE_DEPART']);
            $arr_insee = trim($row['COM_CODE_INSEE_ARRIVEE']);
            $h_dep     = $row['HEURE_DEP'];

            // Suivre la chaîne COM_CODE_INSEE_SUIVANT du départ vers l'arrivée
            // (évite de ramasser les arrêts d'autres passages sur la même ligne)
            $path    = [];
            $cur     = $dep_insee;
            $h       = $h_dep;
            $visited = [];

            for ($step = 0; $step < 60; $step++) {
                $vk = "$cur@$h";
                if (isset($visited[$vk])) break;
                $visited[$vk] = true;

                $nd = $nodes_lookup[$lig][$cur][$h] ?? null;
                if (!$nd) break;

                $path[] = ['nom' => $nd['nom'], 'heure' => $h];
                if ($cur === $arr_insee) break;

                $next = $nd['suivant'];
                if (!$next) break;

                // Premier horaire >= h à l'arrêt suivant (même passage de bus)
                $next_h = null;
                if (isset($nodes_lookup[$lig][$next])) {
                    foreach ($nodes_lookup[$lig][$next] as $nh => $_) {
                        if ($nh >= $h) { $next_h = $nh; break; }
                    }
                }
                if (!$next_h) break;

                $cur = $next;
                $h   = $next_h;
            }

            $detail_etapes[$rnum][] = [
                'lig_num'   => $lig,
                'dep_nom'   => $row['DEP_NOM'],
                'arr_nom'   => $row['ARR_NOM'],
                'heure_dep' => $h_dep,
                'heure_arr' => $row['HEURE_ARR'] ?? '',
                'distance'  => round((float)($row['ETA_DISTANCE'] ?? 0), 1),
                'stops'     => $path
            ];
        }

        foreach ($reservations_futures as &$r) {
            $r['nb_etapes'] = count($detail_etapes[$r['res_num']] ?? []);
        }
        unset($r);

        foreach ($reservations_passees as &$r) {
            $r['nb_etapes'] = count($detail_etapes[$r['res_num']] ?? []);
        }
        unset($r);
        // Trier futures : date croissante (le plus proche en premier)
        usort($reservations_futures, function($a, $b) {
            return $a['dt'] <=> $b['dt'];
        });

        // Trier passées : date décroissante (la plus récente en premier)
        usort($reservations_passees, function($a, $b) {
            return $b['dt'] <=> $a['dt'];
        });
    }
} catch (Exception $e) {
    $detail_etapes = [];
    echo $e->getmessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mon compte – Viking Transport</title>
    <link rel="stylesheet" href="../CSS/inscription.css" />
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
    <style>
        /* ════════ VARIABLES ════════ */
        :root {
            --vt-red:     #c0392b;
            --vt-dark:    #1a1a2e;
            --vt-mid:     #2d2d44;
            --vt-light:   #f5f5f0;
            --vt-muted:   #9ca3af;
            --vt-border:  #e2e8f0;
            --vt-success: #16a34a;
            --vt-warn:    #d97706;
        }

        /* ════════ BASE ════════ */
        .succes-body {
            min-height: 100vh;
            background: var(--vt-light);
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .succes-wrapper {
            max-width: 760px;
            margin: 0 auto;
            padding: 40px 20px 60px;
        }

        /* ════════ BANNER ════════ */
        .bienvenue-banner {
            background: linear-gradient(135deg, var(--vt-dark) 0%, var(--vt-mid) 100%);
            border-radius: 16px;
            padding: 36px 40px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 32px;
            box-shadow: 0 8px 32px rgba(26,26,46,.18);
            position: relative;
            overflow: hidden;
        }
        .bienvenue-banner::before {
            content: '';
            position: absolute;
            right: -40px; top: -40px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(192,57,43,.18);
        }
        .bienvenue-avatar {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: var(--vt-red);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 700;
            flex-shrink: 0; position: relative; z-index: 1;
        }
        .bienvenue-text { position: relative; z-index: 1; }
        .bienvenue-text h1 { margin: 0 0 6px; font-size: 1.55rem; font-weight: 700; letter-spacing: -.5px; }
        .bienvenue-text p  { margin: 0; color: rgba(255,255,255,.72); font-size: .95rem; }
        .bienvenue-badge, .rang-badge {
            margin-left: auto;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.22);
            border-radius: 8px;
            padding: 10px 18px;
            text-align: center;
            position: relative; z-index: 1;
            flex-shrink: 0;
        }
        .bienvenue-badge span, .rang-badge span { display: block; font-size: 1.6rem; font-weight: 700; color: #fff; }
        .bienvenue-badge small, .rang-badge small { color: rgba(255,255,255,.6); font-size: .72rem; text-transform: uppercase; letter-spacing: 1px; }

        /* ════════ CARDS INFO ════════ */
        .info-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--vt-border);
            padding: 28px 32px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,.05);
        }
        .info-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
        }
        .info-card-title {
            font-size: 1rem; font-weight: 600; color: var(--vt-dark);
            display: flex; align-items: center; gap: 8px;
        }
        .info-card-title svg { color: var(--vt-red); }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; }
        .info-item label { display: block; font-size: .75rem; text-transform: uppercase; letter-spacing: .8px; color: var(--vt-muted); font-weight: 600; margin-bottom: 4px; }
        .info-item .info-value { font-size: .95rem; color: var(--vt-dark); font-weight: 500; }
        .info-item .info-value.empty { color: var(--vt-muted); font-style: italic; font-weight: 400; }

        /* ════════ BOUTONS ════════ */
        .btn-modifier {
            display: inline-flex; align-items: center; gap: 7px;
            background: none; border: 1.5px solid var(--vt-red);
            color: var(--vt-red); border-radius: 8px; padding: 7px 16px;
            font-size: .85rem; font-weight: 600; cursor: pointer;
            transition: background .18s, color .18s;
        }
        .btn-modifier:hover { background: var(--vt-red); color: #fff; }

        /* ════════ EDIT PANEL ════════ */
        .edit-panel {
            display: none;
            background: #fff; border-radius: 14px;
            border: 1.5px solid var(--vt-red); padding: 28px 32px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(192,57,43,.08);
        }
        .edit-panel.open { display: block; }
        .edit-panel-title { font-size: 1rem; font-weight: 600; color: var(--vt-dark); margin: 0 0 22px; }
        .edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; }
        .edit-field { display: flex; flex-direction: column; gap: 6px; }
        .edit-field label { font-size: .78rem; font-weight: 600; text-transform: uppercase; letter-spacing: .7px; color: var(--vt-muted); }
        .edit-field input, .edit-field select {
            padding: 10px 14px; border: 1.5px solid var(--vt-border); border-radius: 8px;
            font-size: .92rem; color: var(--vt-dark); background: var(--vt-light);
            transition: border-color .18s; outline: none;
        }
        .edit-field input:focus, .edit-field select:focus { border-color: var(--vt-red); background: #fff; }
        .edit-actions { display: flex; gap: 12px; margin-top: 22px; }
        .btn-valider {
            padding: 10px 28px; background: var(--vt-red); color: #fff;
            border: none; border-radius: 8px; font-size: .92rem; font-weight: 600;
            cursor: pointer; transition: opacity .18s;
        }
        .btn-valider:hover { opacity: .88; }
        .btn-annuler {
            padding: 10px 20px; background: none;
            border: 1.5px solid var(--vt-border); border-radius: 8px;
            font-size: .92rem; color: var(--vt-muted); cursor: pointer;
            transition: border-color .18s, color .18s;
        }
        .btn-annuler:hover { border-color: var(--vt-dark); color: var(--vt-dark); }

        /* ════════ FEEDBACK ════════ */
        .feedback-msg {
            border-radius: 10px; padding: 14px 20px; font-size: .9rem;
            font-weight: 500; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .feedback-msg.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: var(--vt-success); }
        .feedback-msg.error   { background: #fff1f2; border: 1px solid #fecdd3; color: #be123c; }

        /* ════════ CTA ════════ */
        .cta-accueil {
            display: flex; align-items: center; gap: 14px;
            padding: 20px 28px; background: var(--vt-dark);
            border-radius: 12px; color: #fff; text-decoration: none;
            font-weight: 600; font-size: .95rem; transition: opacity .18s;
            width: fit-content;
        }
        .cta-accueil:hover { opacity: .85; }

        /* ══════════════════════════════════════════════════════
           RÉSERVATIONS — toggle card
        ══════════════════════════════════════════════════════ */
        .resa-toggle-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--vt-border);
            padding: 22px 32px;
            margin-bottom: 0;
            box-shadow: 0 2px 12px rgba(0,0,0,.05);
            cursor: pointer;
            user-select: none;
            transition: box-shadow .18s, border-color .18s;
        }
        .resa-toggle-card:hover {
            border-color: var(--vt-red);
            box-shadow: 0 4px 20px rgba(192,57,43,.08);
        }
        .resa-toggle-inner {
            display: flex; align-items: center; justify-content: space-between;
        }
        .resa-toggle-left { display: flex; align-items: center; gap: 10px; }
        .resa-toggle-title { font-size: 1rem; font-weight: 600; color: var(--vt-dark); }
        .resa-count-badge {
            background: var(--vt-red); color: #fff;
            border-radius: 20px; padding: 2px 11px;
            font-size: .75rem; font-weight: 700;
        }
        .resa-chevron {
            color: var(--vt-muted);
            transition: transform .25s;
        }
        .resa-chevron.open { transform: rotate(180deg); }

        /* ══════════════════════════════════════════════════════
           RÉSERVATIONS — panel
        ══════════════════════════════════════════════════════ */
        .resa-section {
            display: none;
            margin-bottom: 24px;
            background: #fff;
            border: 1px solid var(--vt-border);
            border-top: none;
            border-radius: 0 0 14px 14px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,.06);
        }
        .resa-section.open { display: block; }

        /* ── Onglets ── */
        .resa-tabs {
            display: flex;
            border-bottom: 2px solid var(--vt-border);
            background: #fafaf8;
        }
        .resa-tab {
            display: flex; align-items: center; gap: 8px;
            padding: 14px 24px;
            background: none; border: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            font-size: .88rem; font-weight: 600;
            color: var(--vt-muted);
            cursor: pointer;
            transition: color .18s, border-color .18s;
        }
        .resa-tab:hover { color: var(--vt-dark); }
        .resa-tab.active { color: var(--vt-red); border-bottom-color: var(--vt-red); }
        .tab-pill {
            background: var(--vt-border); color: var(--vt-dark);
            border-radius: 20px; padding: 1px 8px;
            font-size: .7rem; font-weight: 700;
            transition: background .18s, color .18s;
        }
        .resa-tab.active .tab-pill { background: rgba(192,57,43,.12); color: var(--vt-red); }

        /* ── Liste ── */
        .resa-list { display: none; flex-direction: column; }
        .resa-list.active { display: flex; }

        .resa-empty {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 52px 24px;
            color: var(--vt-muted); font-size: .9rem; gap: 10px;
        }
        .resa-empty svg { opacity: .4; }
        .resa-empty a { color: var(--vt-red); font-weight: 600; font-size: .88rem; text-decoration: none; }
        .resa-empty a:hover { text-decoration: underline; }

        /* ── Carte ── */
        .resa-card {
            display: flex; align-items: center; gap: 20px;
            padding: 18px 28px;
            border-bottom: 1px solid var(--vt-border);
            transition: background .15s;
        }
        .resa-card:last-child { border-bottom: none; }
        .resa-card:hover { background: #fafaf8; }

        /* Badge date */
        .resa-date-badge {
            width: 52px; flex-shrink: 0;
            background: rgba(192,57,43,.08);
            border-radius: 10px;
            text-align: center;
            padding: 8px 4px;
        }
        .resa-date-badge.passe { background: #f1f5f9; }
        .resa-day   { display: block; font-size: 1.35rem; font-weight: 700; color: var(--vt-red); line-height: 1; }
        .resa-month { display: block; font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--vt-red); margin-top: 2px; }
        .resa-date-badge.passe .resa-day,
        .resa-date-badge.passe .resa-month { color: var(--vt-muted); }

        /* Corps */
        .resa-card-body { flex: 1; min-width: 0; }
        .resa-route { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; flex-wrap: wrap; }
        .resa-city {
            font-size: .95rem; font-weight: 600; color: var(--vt-dark);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px;
        }
        .resa-arrow { color: var(--vt-muted); flex-shrink: 0; display: flex; align-items: center; }
        .resa-meta { display: flex; gap: 14px; font-size: .75rem; color: var(--vt-muted); flex-wrap: wrap; }
        .resa-meta span { display: flex; align-items: center; gap: 4px; }

        .resa-correspondances {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 3px;
            font-size: .76rem;
            color: var(--vt-muted);
            line-height: 1.5;
            margin-top: 4px;
        }
        .corr-ville {
            white-space: nowrap;
        }
        .corr-arrow {
            color: var(--vt-red);
            font-size: .85rem;
            flex-shrink: 0;
        }
        .corr-none {
            font-size: .75rem;
            color: var(--vt-muted);
            font-style: italic;
        }

        /* Droite */
        .resa-card-right { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; flex-shrink: 0; }
        .resa-prix { font-size: 1.05rem; font-weight: 700; color: var(--vt-dark); }
        .resa-prix.passe { color: var(--vt-muted); }
        .resa-pts  { font-size: .72rem; color: var(--vt-warn); font-weight: 600; }

        /* Bouton détail */
        .btn-detail {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; font-size: .78rem; font-weight: 600;
            border-radius: 8px; cursor: pointer;
            border: 1.5px solid var(--vt-border);
            background: none; color: var(--vt-dark);
            transition: border-color .18s, color .18s, background .18s;
            white-space: nowrap;
        }
        .btn-detail:hover { border-color: var(--vt-dark); }
        .btn-detail.is-future {
            border-color: var(--vt-red); color: var(--vt-red);
        }
        .btn-detail.is-future:hover { background: var(--vt-red); color: #fff; }

        /* ══════════════════════════════════════════════════════
           MODAL
        ══════════════════════════════════════════════════════ */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,15,30,.55);
            backdrop-filter: blur(3px);
            z-index: 1000;
            animation: vtFadeIn .2s ease;
        }
        .modal-box {
            display: none; position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1001;
            width: min(620px, calc(100vw - 32px));
            max-height: 88vh;
            overflow-y: auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 32px 100px rgba(0,0,0,.28);
            animation: vtSlideUp .22s ease;
        }
        @keyframes vtFadeIn  { from { opacity: 0; } to { opacity: 1; } }
        @keyframes vtSlideUp { from { opacity: 0; transform: translate(-50%,-46%); } to { opacity: 1; transform: translate(-50%,-50%); } }

        /* ── Ticket (futur) ── */
        .ticket-head {
            background: linear-gradient(135deg, var(--vt-dark) 0%, #3a3a5c 100%);
            color: #fff;
            padding: 30px 36px 26px;
            border-radius: 18px 18px 0 0;
            position: relative;
            overflow: hidden;
        }
        .ticket-head::after {
            content: '';
            position: absolute;
            right: -60px; bottom: -60px;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: rgba(192,57,43,.2);
        }
        .ticket-badge-label {
            display: inline-block;
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 6px;
            padding: 3px 12px;
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,.8);
            margin-bottom: 16px;
        }
        .ticket-route-big {
            display: flex; align-items: center; gap: 16px;
            margin-bottom: 10px; flex-wrap: wrap;
            position: relative; z-index: 1;
        }
        .ticket-city-big { font-size: 1.45rem; font-weight: 700; line-height: 1.2; }
        .ticket-arrow-big { opacity: .5; }
        .ticket-date-sub { font-size: .82rem; color: rgba(255,255,255,.6); position: relative; z-index: 1; }

        .ticket-perfo {
            border: none;
            border-top: 2px dashed rgba(0,0,0,.1);
            margin: 0;
        }

        .ticket-body { padding: 26px 36px; }

        .ticket-kpis {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 16px; margin-bottom: 26px;
        }
        .ticket-kpi label {
            display: block; font-size: .68rem; text-transform: uppercase;
            letter-spacing: .8px; color: var(--vt-muted); font-weight: 700; margin-bottom: 3px;
        }
        .ticket-kpi .val { font-size: .95rem; font-weight: 600; color: var(--vt-dark); }

        .ticket-etapes-title {
            font-size: .72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .8px; color: var(--vt-muted); margin-bottom: 14px;
        }
        .ticket-etape-row {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 12px 0; border-bottom: 1px solid var(--vt-border);
        }
        .ticket-etape-row:last-child { border-bottom: none; }
        .line-badge {
            background: var(--vt-red); color: #fff;
            border-radius: 6px; padding: 3px 10px;
            font-size: .72rem; font-weight: 700;
            flex-shrink: 0; margin-top: 2px; white-space: nowrap;
        }
        .etape-route { font-size: .9rem; font-weight: 600; color: var(--vt-dark); margin-bottom: 3px; }
        .etape-sub   { font-size: .73rem; color: var(--vt-muted); display: flex; gap: 12px; }

        .ticket-foot {
            background: #f8f9fb;
            border-top: 1px solid var(--vt-border);
            padding: 18px 36px;
            display: flex; justify-content: space-between; align-items: center;
            border-radius: 0 0 18px 18px;
        }
        .ticket-foot-prix { font-size: 1.3rem; font-weight: 700; color: var(--vt-dark); }
        .ticket-foot small { display: block; font-size: .68rem; color: var(--vt-muted); margin-top: 2px; }

        /* ── Détail passé ── */
        .detail-wrap { padding: 30px 36px; }
        .detail-head {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 26px;
        }
        .detail-head-title { font-size: 1.1rem; font-weight: 700; color: var(--vt-dark); }
        .detail-head-sub   { font-size: .8rem; color: var(--vt-muted); margin-top: 4px; }

        /* Timeline */
        .detail-timeline { position: relative; padding-left: 30px; margin-bottom: 26px; }
        .detail-timeline::before {
            content: '';
            position: absolute; left: 8px; top: 14px; bottom: 14px;
            width: 2px; background: var(--vt-border);
        }
        .tl-stop { position: relative; margin-bottom: 22px; }
        .tl-stop:last-child { margin-bottom: 0; }
        .tl-stop::before {
            content: '';
            position: absolute; left: -26px; top: 5px;
            width: 11px; height: 11px;
            border-radius: 50%;
            background: var(--vt-muted);
            border: 2.5px solid #fff;
            box-shadow: 0 0 0 2px var(--vt-muted);
        }
        .tl-stop.first::before { background: var(--vt-dark);   box-shadow: 0 0 0 2px var(--vt-dark); }
        .tl-stop.last::before  { background: var(--vt-success); box-shadow: 0 0 0 2px var(--vt-success); }
        .tl-city { font-size: .92rem; font-weight: 600; color: var(--vt-dark); }
        .tl-meta { font-size: .73rem; color: var(--vt-muted); display: flex; gap: 10px; margin-top: 3px; }
        .tl-line-badge {
            display: inline-block;
            background: rgba(192,57,43,.1); color: var(--vt-red);
            border-radius: 5px; padding: 1px 7px;
            font-size: .68rem; font-weight: 700;
            margin-top: 5px;
        }

        .detail-summary {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            padding-top: 22px; border-top: 1px solid var(--vt-border);
        }
        .detail-summary label {
            display: block; font-size: .68rem; text-transform: uppercase;
            letter-spacing: .7px; color: var(--vt-muted); font-weight: 700; margin-bottom: 3px;
        }
        .detail-summary .val { font-size: .95rem; font-weight: 600; color: var(--vt-dark); }

        /* ── Bouton fermer ── */
        .btn-close-modal {
            padding: 8px 22px;
            background: var(--vt-dark); color: #fff;
            border: none; border-radius: 8px;
            font-size: .85rem; font-weight: 600;
            cursor: pointer; transition: opacity .18s; flex-shrink: 0;
        }
        .btn-close-modal:hover { opacity: .8; }
        .btn-close-icon {
            width: 32px; height: 32px;
            background: var(--vt-border); border: none;
            border-radius: 50%; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: var(--vt-dark);
            transition: background .15s;
            flex-shrink: 0;
        }
        .btn-close-icon:hover { background: #e2e8f0; }

        /* ════════ RESPONSIVE ════════ */
        @media (max-width: 600px) {
            .info-grid, .edit-grid { grid-template-columns: 1fr; }
            .bienvenue-banner { flex-direction: column; text-align: center; }
            .bienvenue-badge, .rang-badge { margin-left: 0; }
            .succes-wrapper { padding: 24px 14px 48px; }
            .resa-card { flex-wrap: wrap; padding: 14px 16px; gap: 12px; }
            .resa-card-right { flex-direction: row; align-items: center; width: 100%; justify-content: space-between; }
            .ticket-kpis, .detail-summary { grid-template-columns: 1fr 1fr; }
            .ticket-body, .detail-wrap, .ticket-head, .ticket-foot { padding-left: 20px; padding-right: 20px; }
            .resa-tabs { overflow-x: auto; }
            .resa-tab  { white-space: nowrap; }
        }
    </style>
</head>
<body class="succes-body">

<nav class="vt-topbar">
    <a href="../index.php">Retour au site</a>
    <div class="vt-topbar-logo">
        <img src="../Files/LogoVicking.png" alt="Viking Transport" onerror="this.style.display='none'" />
    </div>
</nav>

<div class="succes-wrapper">

    <?php if ($errorMsg): ?>
    <div class="feedback-msg error">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        Erreur lors du chargement : <?= htmlspecialchars($errorMsg) ?>
    </div>
    <?php endif; ?>

    <?php if ($updateMsg): ?>
    <div class="feedback-msg success">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <?= htmlspecialchars($updateMsg) ?>
    </div>
    <?php endif; ?>

    <?php $initiales = strtoupper(substr($_SESSION['cli_prenom'], 0, 1) . substr($_SESSION['cli_nom'], 0, 1)); ?>

    <!-- ── Bannière ── -->
    <div class="bienvenue-banner">
        <div class="bienvenue-avatar"><?= $initiales ?></div>
        <div class="bienvenue-text">
            <h1>Bonjour, <?= htmlspecialchars($_SESSION['cli_prenom']) ?> <?= htmlspecialchars($_SESSION['cli_nom']) ?>&nbsp;!</h1>
            <p>Vous pouvez dès maintenant réserver vos voyages en Normandie.</p>
        </div>
        <div class="bienvenue-badge">
            <span><?= (int)$nb_points ?></span>
            <small>Points fidélité</small>
        </div>
        <div class="rang-badge">
            <small>Rang</small>
            <span><?= $rang ?></span>
            <span><?= $rang_tour ?></span>
        </div>
    </div>

    <!-- ── Infos personnelles ── -->
    <div class="info-card">
        <div class="info-card-header">
            <span class="info-card-title">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                Vos informations
            </span>
            <button class="btn-modifier" id="btn-open-edit" type="button">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Modifier / Compléter
            </button>
        </div>
        <div class="info-grid">
            <div class="info-item">
                <label>Prénom</label>
                <div class="info-value"><?= htmlspecialchars($_SESSION['cli_prenom']) ?></div>
            </div>
            <div class="info-item">
                <label>Nom</label>
                <div class="info-value"><?= htmlspecialchars($_SESSION['cli_nom']) ?></div>
            </div>
            <div class="info-item">
                <label>Adresse email</label>
                <div class="info-value"><?= htmlspecialchars($_SESSION['cli_email']) ?></div>
            </div>
            <div class="info-item">
                <label>Téléphone</label>
                <div class="info-value <?= empty($telephone) ? 'empty' : '' ?>">
                    <?= $telephone ? htmlspecialchars($telephone) : 'Non renseigné' ?>
                </div>
            </div>
            <div class="info-item">
                <label>Ville</label>
                <div class="info-value <?= empty($ville) ? 'empty' : '' ?>">
                    <?= $ville ? htmlspecialchars($ville) : 'Non renseignée' ?>
                </div>
            </div>
            <div class="info-item">
                <label>Département</label>
                <div class="info-value <?= empty($dep_nom) ? 'empty' : '' ?>">
                    <?= $dep_nom ? htmlspecialchars($dep_nom) : 'Non renseigné' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Formulaire édition ── -->
    <div class="edit-panel" id="edit-panel">
        <p class="edit-panel-title">Modifier ou compléter vos informations</p>
        <form method="POST" action="traitement_maj_client.php">
            <input type="hidden" name="cli_num" value="<?= $cli_num ?>" />
            <input type="hidden" name="origin"  value="profil" />
            <div class="edit-grid">
                <div class="edit-field">
                    <label>Prénom</label>
                    <input type="text" name="prenom" value="<?= htmlspecialchars($_SESSION['cli_prenom']) ?>" />
                </div>
                <div class="edit-field">
                    <label>Nom</label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($_SESSION['cli_nom']) ?>" />
                </div>
                <div class="edit-field">
                    <label>Adresse email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_SESSION['cli_email']) ?>" />
                </div>
                <div class="edit-field">
                    <label>Téléphone</label>
                    <input type="tel" name="telephone" value="<?= htmlspecialchars($telephone) ?>" placeholder="+33 6 00 00 00 00" />
                </div>
                <div class="edit-field">
                    <label>Ville</label>
                    <input type="text" name="ville" value="<?= htmlspecialchars($ville) ?>" placeholder="Ex : Caen" />
                </div>
                <div class="edit-field">
                    <label>Département</label>
                    <select name="dep_num">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($departements as $dep): ?>
                            <option value="<?= htmlspecialchars($dep['DEP_NUM']) ?>"
                                <?= (isset($dep_num) && $dep_num == $dep['DEP_NUM']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dep['DEP_NOM']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="edit-actions">
                <button type="submit" class="btn-valider">Valider les modifications</button>
                <button type="button" class="btn-annuler" id="btn-cancel-edit">Annuler</button>
            </div>
        </form>
    </div>

    <!-- ══════════════════════════════════════════════════════
         RÉSERVATIONS
    ══════════════════════════════════════════════════════ -->
    <?php
    $total_resa = count($reservations_futures) + count($reservations_passees);
    ?>

    <!-- Toggle -->
    <div class="resa-toggle-card" onclick="toggleResa()" style="border-radius:14px; margin-bottom:0;">
        <div class="resa-toggle-inner">
            <div class="resa-toggle-left">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--vt-red);">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8"  y1="2" x2="8"  y2="6"/>
                    <line x1="3"  y1="10" x2="21" y2="10"/>
                </svg>
                <span class="resa-toggle-title">Mes réservations</span>
                <span class="resa-count-badge"><?= $total_resa ?></span>
            </div>
            <svg class="resa-chevron" id="resa-chevron" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </div>
    </div>

    <!-- Panneau -->
    <div class="resa-section" id="resa-section">

        <!-- Onglets -->
        <div class="resa-tabs">
            <button class="resa-tab active" id="tab-btn-future" onclick="switchTab('future')">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                À venir
                <span class="tab-pill"><?= count($reservations_futures) ?></span>
            </button>
            <button class="resa-tab" id="tab-btn-passe" onclick="switchTab('passe')">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                    <polyline points="9 11 12 14 22 4"/>
                    <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                </svg>
                Passées
                <span class="tab-pill"><?= count($reservations_passees) ?></span>
            </button>
        </div>

        <!-- Liste futures -->
        <div class="resa-list active" id="list-future">
            <?php if (empty($reservations_futures)): ?>
            <div class="resa-empty">
                <svg width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                </svg>
                <span>Aucun voyage à venir pour l'instant.</span>
                <a href="formulaire.php">Réserver un voyage →</a>
            </div>
            <?php else: ?>
                <?php foreach ($reservations_futures as $r): ?>
                <?php
                    $dtObj    = new DateTime($r['res_date']);
                    $jour     = $dtObj->format('d');
                    $moisCourt= strtoupper($dtObj->format('M'));
                ?>
                <div class="resa-card">
                    <div class="resa-date-badge">
                        <span class="resa-day"><?= $jour ?></span>
                        <span class="resa-month"><?= $moisCourt ?></span>
                    </div>
                    <div class="resa-card-body">
                        <div class="resa-route">
                            <span class="resa-city"><?= htmlspecialchars($r['dep_nom']) ?></span>
                            <span class="resa-arrow">
                                <svg width="20" height="10" viewBox="0 0 20 10" fill="none">
                                    <path d="M0 5h18M13 1l5 4-5 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span class="resa-city"><?= htmlspecialchars($r['arr_nom']) ?></span>
                        </div>
                        <div class="resa-correspondances">
                            <?php
                            $etapes_carte = $detail_etapes[$r['res_num']] ?? [];
                            if (!empty($etapes_carte)):
                                $villes_unique = [];
                                foreach ($etapes_carte as $i => $etape) {
                                    if ($i === 0) {
                                        $villes_unique[] = htmlspecialchars($etape['dep_nom']);
                                    }
                                    $villes_unique[] = htmlspecialchars($etape['arr_nom']);
                                }
                                $villes_final = [];
                                if (!empty($villes_unique)) {
                                    $villes_final[] = $villes_unique[0];
                                    for ($j = 1; $j < count($villes_unique); $j++) {
                                        if ($villes_unique[$j] !== $villes_unique[$j-1]) {
                                            $villes_final[] = $villes_unique[$j];
                                        }
                                    }
                                }
                                $villes_html = array_map(function($v) {
                                    return '<span class="corr-ville">' . $v . '</span>';
                                }, $villes_final);
                                echo implode('<span class="corr-arrow">›</span>', $villes_html);
                            else:
                                echo '<span class="corr-none">Aucune étape</span>';
                            endif;
                            ?>
                        </div>
                    </div>
                    <div class="resa-card-right">
                        <span class="resa-prix"><?= number_format((float)$r['prix'], 2, ',', '') ?> €</span>
                        <?php if ($r['points'] > 0): ?>
                        <span class="resa-pts"><?= $r['points'] ?> pts gagnés</span>
                        <?php endif; ?>
                        <button class="btn-detail is-future" onclick="ouvrirDetail(<?= (int)$r['res_num'] ?>, 'future')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
                                <path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
                            </svg>
                            Voir le ticket
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div><!-- #list-future -->

        <!-- Liste passées -->
        <div class="resa-list" id="list-passe">
            <?php if (empty($reservations_passees)): ?>
            <div class="resa-empty">
                <svg width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                <span>Aucun voyage passé enregistré.</span>
            </div>
            <?php else: ?>
                <?php foreach ($reservations_passees as $r): ?>
                <?php
                    $dtObj    = new DateTime($r['res_date']);
                    $jour     = $dtObj->format('d');
                    $moisCourt= strtoupper($dtObj->format('M'));
                ?>
                <div class="resa-card">
                    <div class="resa-date-badge passe">
                        <span class="resa-day"><?= $jour ?></span>
                        <span class="resa-month"><?= $moisCourt ?></span>
                    </div>
                    <div class="resa-card-body">
                        <div class="resa-route">
                            <span class="resa-city"><?= htmlspecialchars($r['dep_nom']) ?></span>
                            <span class="resa-arrow">
                                <svg width="20" height="10" viewBox="0 0 20 10" fill="none">
                                    <path d="M0 5h18M13 1l5 4-5 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span class="resa-city"><?= htmlspecialchars($r['arr_nom']) ?></span>
                        </div>
                        <div class="resa-correspondances">
                            <?php
                            $etapes_carte = $detail_etapes[$r['res_num']] ?? [];
                            if (!empty($etapes_carte)):
                                $villes_unique = [];
                                foreach ($etapes_carte as $i => $etape) {
                                    if ($i === 0) {
                                        $villes_unique[] = htmlspecialchars($etape['dep_nom']);
                                    }
                                    $villes_unique[] = htmlspecialchars($etape['arr_nom']);
                                }
                                $villes_final = [];
                                if (!empty($villes_unique)) {
                                    $villes_final[] = $villes_unique[0];
                                    for ($j = 1; $j < count($villes_unique); $j++) {
                                        if ($villes_unique[$j] !== $villes_unique[$j-1]) {
                                            $villes_final[] = $villes_unique[$j];
                                        }
                                    }
                                }
                                $villes_html = array_map(function($v) {
                                    return '<span class="corr-ville">' . $v . '</span>';
                                }, $villes_final);
                                echo implode('<span class="corr-arrow">›</span>', $villes_html);
                            else:
                                echo '<span class="corr-none">Aucune étape</span>';
                            endif;
                            ?>
                        </div>
                    </div>
                    <div class="resa-card-right">
                        <span class="resa-prix passe"><?= number_format((float)$r['prix'], 2, ',', '') ?> €</span>
                        <?php if ($r['points'] > 0): ?>
                        <span class="resa-pts"><?= $r['points'] ?> pts gagnés</span>
                        <?php endif; ?>
                        <button class="btn-detail" onclick="ouvrirDetail(<?= (int)$r['res_num'] ?>, 'passe')">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            Voir le détail
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div><!-- #list-passe -->

    </div><!-- .resa-section -->

    <!-- ── Données JSON pour le JS ── -->
    <script id="js-etapes" type="application/json">
    <?php
    $etapes_json = [];
    foreach ($detail_etapes as $res_num => $etapes) { $etapes_json[(int)$res_num] = $etapes; }
    echo json_encode($etapes_json, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    ?>
    </script>
    <script id="js-resas" type="application/json">
    <?php
    $resas_json = [];
    foreach (array_merge($reservations_futures, $reservations_passees) as $r) {
        $tmp = $r; unset($tmp['dt']);
        $resas_json[(int)$r['res_num']] = $tmp;
    }
    echo json_encode($resas_json, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    ?>
    </script>

    <!-- ── Modal ── -->
    <div class="modal-overlay" id="modal-overlay" onclick="fermerModal()"></div>
    <div class="modal-box"     id="modal-box"     role="dialog" aria-modal="true">
        <div id="modal-content"></div>
    </div>

    <!-- ── CTA réservation ── -->
    <div style="margin-top:28px;">
        <a href="formulaire.php" class="cta-accueil">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke-linecap="round" stroke-linejoin="round"/>
                <polyline points="9 22 9 12 15 12 15 22" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Accéder à l'espace de réservation
        </a>
    </div>

</div><!-- .succes-wrapper -->

<script>
/* ════════ DONNÉES ════════ */
const ETAPES = JSON.parse(document.getElementById('js-etapes').textContent);
const RESAS  = JSON.parse(document.getElementById('js-resas').textContent);

/* ════════ TOGGLE RÉSERVATIONS ════════ */
let resaOpen = false;
function toggleResa() {
    resaOpen = !resaOpen;
    const section  = document.getElementById('resa-section');
    const chevron  = document.getElementById('resa-chevron');
    const card     = section.previousElementSibling; // le toggle card
    section.classList.toggle('open', resaOpen);
    chevron.classList.toggle('open', resaOpen);
    // Adapter le border-radius du toggle quand ouvert
    card.style.borderRadius = resaOpen ? '14px 14px 0 0' : '14px';
    card.style.borderBottom = resaOpen ? '1px solid var(--vt-border)' : '';
}

/* ════════ ONGLETS ════════ */
function switchTab(tab) {
    document.getElementById('list-future').classList.toggle('active', tab === 'future');
    document.getElementById('list-passe').classList.toggle('active',  tab === 'passe');
    document.getElementById('tab-btn-future').classList.toggle('active', tab === 'future');
    document.getElementById('tab-btn-passe').classList.toggle('active',  tab === 'passe');
}

/* ════════ ÉDITION PROFIL ════════ */
const btnOpen   = document.getElementById('btn-open-edit');
const btnCancel = document.getElementById('btn-cancel-edit');
const panel     = document.getElementById('edit-panel');
if (btnOpen)   btnOpen.addEventListener('click',   () => panel.classList.add('open'));
if (btnCancel) btnCancel.addEventListener('click', () => panel.classList.remove('open'));

/* ════════ MODAL ════════ */
function ouvrirDetail(resNum, type) {
    const resa   = RESAS[resNum]   || {};
    const etapes = ETAPES[resNum]  || [];

    const dateObj = new Date(resa.res_date + 'T00:00:00');
    // Pour les futures on affiche le jour de la semaine
    const dateStr = (type === 'future') 
        ? dateObj.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
        : dateObj.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });

    let timelineHtml = '';
    if (etapes.length > 0) {
        etapes.forEach((e, idx) => {
            const isFirstSegment = (idx === 0);
            const isLastSegment = (idx === etapes.length - 1);
            
            if (e.stops && e.stops.length > 0) {
                e.stops.forEach((stop, sIdx) => {
                    const isLastStopOfSegment = (sIdx === e.stops.length - 1);
                    const isFirstStopOfTotal = (isFirstSegment && sIdx === 0);
                    const isLastStopOfTotal = (isLastSegment && isLastStopOfSegment);
                    
                    if (!isFirstStopOfTotal && sIdx === 0) return; 

                    timelineHtml += `
                        <div class="tl-stop ${isFirstStopOfTotal ? 'first' : ''} ${isLastStopOfTotal ? 'last' : ''}">
                            <div class="tl-city">${esc(stop.nom)}</div>
                            <div class="tl-meta">🕐 ${esc(stop.heure)}</div>
                            ${sIdx === 0 ? `<span class="tl-line-badge">Ligne ${esc(e.lig_num)}</span>` : ''}
                        </div>
                    `;
                });
            } else {
                timelineHtml += `
                    <div class="tl-stop ${isFirstSegment ? 'first' : ''}">
                        <div class="tl-city">${esc(e.dep_nom)}</div>
                        <div class="tl-meta">🕐 ${esc(e.heure_dep)}</div>
                        <span class="tl-line-badge">Ligne ${esc(e.lig_num)}</span>
                    </div>
                `;
                if (isLastSegment) {
                    timelineHtml += `
                        <div class="tl-stop last">
                            <div class="tl-city">${esc(e.arr_nom)}</div>
                            <div class="tl-meta">🕐 ${esc(e.heure_arr || 'N/A')}</div>
                        </div>
                    `;
                }
            }
        });
    } else {
        timelineHtml = '<p style="color:var(--vt-muted);font-size:.85rem;margin:0;">Aucune étape enregistrée.</p>';
    }

    const titleTimeline = (type === 'future') ? 'Détail du trajet' : 'Trajet effectué';
    const labelPrix     = (type === 'future') ? 'Prix estimé' : 'Prix payé';
    const labelPoints   = (type === 'future') ? 'Points gagnés' : 'Points gagnés';

    const html = `
    <div class="detail-wrap">
        <div class="detail-head">
            <div>
                <div class="detail-head-title">${esc(resa.dep_nom)} → ${esc(resa.arr_nom)}</div>
                <div class="detail-head-sub">Réservation #${resNum} · ${dateStr}</div>
            </div>
            <button class="btn-close-icon" onclick="fermerModal()" aria-label="Fermer">✕</button>
        </div>
        <div class="ticket-etapes-title">${titleTimeline}</div>
        <div class="detail-timeline">${timelineHtml}</div>
        <div class="detail-summary">
            <div><label>${labelPrix}</label><div class="val">${fmtPrix(resa.prix)} €</div></div>
            <div><label>${labelPoints}</label><div class="val">${resa.points > 0 ? resa.points + ' pts' : '—'}</div></div>
            <div><label>Distance</label><div class="val">${resa.dist_totale > 0 ? resa.dist_totale + ' km' : '—'}</div></div>
        </div>
        ${type === 'future' ? `
        <div style="margin-top:24px; text-align:right;">
            <button class="btn-close-modal" onclick="fermerModal()">Fermer</button>
        </div>` : ''}
    </div>`;

    document.getElementById('modal-content').innerHTML = html;
    document.getElementById('modal-overlay').style.display = 'block';
    document.getElementById('modal-box').style.display     = 'block';
    document.body.style.overflow = 'hidden';
}

function fermerModal() {
    document.getElementById('modal-overlay').style.display = 'none';
    document.getElementById('modal-box').style.display     = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') fermerModal(); });

/* ── Helpers ── */
function esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str || '');
    return d.innerHTML;
}
function fmtPrix(p) {
    return parseFloat(p || 0).toFixed(2).replace('.', ',');
}
</script>

<?php $conn = null; ?>
</body>
</html> 