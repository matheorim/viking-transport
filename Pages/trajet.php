<?php
session_start();
include_once "../PHP/utils.php";

$conn = OuvrirConn();

$villesTab = ExecSelect($conn, "
    SELECT DISTINCT c.COM_CODE_INSEE, c.COM_NOM
    FROM VIK_COMMUNE c
    WHERE EXISTS (
        SELECT 1 FROM VIK_NOEUD n
        WHERE n.COM_CODE_INSEE_ARRET = c.COM_CODE_INSEE
    )
    OR EXISTS (
        SELECT 1 FROM VIK_LIGNE l
        WHERE l.COM_CODE_INSEE_TERM = c.COM_CODE_INSEE
    )
    OR EXISTS (
        SELECT 1 FROM VIK_LIGNE l
        WHERE l.COM_CODE_INSEE_DEBU = c.COM_CODE_INSEE
    )
    ORDER BY c.COM_NOM
");

$result       = null;
$erreur       = null;
$critere      = $_POST['critere']      ?? 'rapide';
$codeDep      = $_POST['depart']       ?? null;
$codeArr      = $_POST['arrivee']      ?? null;
$heureChoisie = $_POST['heure_depart'] ?? null;
$nbReponses   = isset($_POST['nb_reponses']) ? (int)$_POST['nb_reponses'] : 1;
$nomDep       = '';
$nomArr       = '';

$codeDepEsc = !empty($codeDep) ? str_replace("'", "''", $codeDep) : '';
$codeArrEsc = !empty($codeArr) ? str_replace("'", "''", $codeArr) : '';

if (!empty($codeDepEsc)) {
    $heuresDispoTab = ExecSelect($conn, "
        SELECT DISTINCT TO_CHAR(NOE_HEURE_PASSAGE, 'HH24:MI') AS HEURE
        FROM VIK_NOEUD
        WHERE COM_CODE_INSEE_ARRET = '$codeDepEsc'
          AND NOE_HEURE_PASSAGE IS NOT NULL
        UNION
        SELECT DISTINCT TO_CHAR(
            n.NOE_HEURE_PASSAGE + NUMTODSINTERVAL(n.NOE_DUREE_PROCHAIN, 'MINUTE'),
            'HH24:MI'
        ) AS HEURE
        FROM VIK_NOEUD n
        JOIN VIK_LIGNE l ON l.LIG_NUM = n.LIG_NUM
        WHERE l.COM_CODE_INSEE_TERM = '$codeDepEsc'
          AND (n.COM_CODE_INSEE_SUIVANT IS NULL OR TRIM(n.COM_CODE_INSEE_SUIVANT) = '')
        ORDER BY HEURE
    ");
} else {
    $heuresDispoTab = [];
}

/**
 * Construit le graphe des villes reliées par les lignes de transport.
 *
 * IMPORTANT (fix) :
 * - On ne fait plus de self-JOIN sur VIK_NOEUD avec une condition ">=" sur
 *   NOE_HEURE_PASSAGE, car cette condition matchait TOUS les passages
 *   ultérieurs et générait des dizaines d'arcs identiques (même
 *   distance/durée) entre les mêmes villes. Cela faisait exploser le
 *   nombre de combinaisons explorées par l'algorithme des k-plus-courts-
 *   chemins (symptôme : l'algo "tourne en boucle" / devient très lent).
 * - NOE_DISTANCE_PROCHAIN et NOE_DUREE_PROCHAIN sont déjà stockés sur le
 *   nœud de départ de l'arc, donc aucun JOIN n'est nécessaire pour
 *   connaître le poids de l'arc.
 * - On déduplique les arcs (from, to, ligne) pour ne garder qu'une seule
 *   entrée par combinaison, peu importe le nombre d'horaires de passage.
 */
function construireGraphe($conn)
{
    $arcs = ExecSelect($conn, "
        SELECT LIG_NUM,
               COM_CODE_INSEE_ARRET   AS FROM_CODE,
               COM_CODE_INSEE_SUIVANT AS TO_CODE,
               NOE_DISTANCE_PROCHAIN  AS DISTANCE,
               NOE_DUREE_PROCHAIN     AS DUREE
        FROM VIK_NOEUD
        WHERE COM_CODE_INSEE_SUIVANT IS NOT NULL
    ");

    $termini = ExecSelect($conn, "
        SELECT n.LIG_NUM,
               n.COM_CODE_INSEE_ARRET  AS FROM_CODE,
               l.COM_CODE_INSEE_TERM   AS TO_CODE,
               n.NOE_DISTANCE_PROCHAIN AS DISTANCE,
               n.NOE_DUREE_PROCHAIN    AS DUREE
        FROM VIK_NOEUD n
        JOIN VIK_LIGNE l ON l.LIG_NUM = n.LIG_NUM
        WHERE n.COM_CODE_INSEE_SUIVANT IS NULL
           OR TRIM(n.COM_CODE_INSEE_SUIVANT) = ''
    ");

    $graphe = [];
    $vus    = []; // clé "from|to|ligne" déjà ajoutée, pour dédoublonnage

    foreach (array_merge($arcs, $termini) as $arc) {
        $from = trim($arc['FROM_CODE']);
        $to   = trim($arc['TO_CODE']);
        if (!$from || !$to || $from === $to) continue;

        $cle = $from . '|' . $to . '|' . trim($arc['LIG_NUM']);
        if (isset($vus[$cle])) continue;
        $vus[$cle] = true;

        $graphe[$from][] = [
            'to'       => $to,
            'duree'    => (float)$arc['DUREE'],
            'distance' => (float)$arc['DISTANCE'],
            'ligne'    => $arc['LIG_NUM'],
        ];
    }
    return $graphe;
}

function dijkstra($graphe, $depart, $arrivee, $poids = 'duree')
{
    $dist    = [];
    $visited = [];
    $queue   = new SplPriorityQueue();
    $queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);

    $dist[$depart] = 0;
    $queue->insert(['node' => $depart, 'path' => []], 0);

    while (!$queue->isEmpty()) {
        $item    = $queue->extract();
        $current = $item['data']['node'];
        $path    = $item['data']['path'];

        if (isset($visited[$current])) continue;
        $visited[$current] = true;

        if ($current === $arrivee) return $path;
        if (!isset($graphe[$current])) continue;

        foreach ($graphe[$current] as $arc) {
            $voisin  = $arc['to'];
            $newDist = ($dist[$current] ?? INF) + $arc[$poids];
            if ($newDist < ($dist[$voisin] ?? INF)) {
                $dist[$voisin] = $newDist;
                $newPath = array_merge($path, [array_merge($arc, ['from' => $current])]);
                $queue->insert(['node' => $voisin, 'path' => $newPath], -$newDist);
            }
        }
    }
    return null;
}

/**
 * Algorithme de Yen (k-plus-courts-chemins).
 *
 * IMPORTANT (fix) :
 * Avant de calculer le "spur path" (la portion alternative depuis le
 * pivot jusqu'à l'arrivée), on retire du graphe toutes les villes déjà
 * traversées dans la portion "racine" du chemin précédent (sauf le pivot
 * lui-même). Sans cela, le spur pouvait revenir sur des villes déjà
 * visitées dans la racine, créant des itinéraires en "aller-retour"
 * (ex: Coutances -> Caumont-l'Évent -> Saint-Lô -> Caumont-l'Évent ->
 * Saint-Lô -> ...), ce qui correspond au symptôme "l'algo tourne en
 * boucle / le résultat boucle entre deux villes".
 */
function dijkstraKShortestPaths($graphe, $depart, $arrivee, $k, $poids = 'duree')
{
    $cheminInitial = dijkstra($graphe, $depart, $arrivee, $poids);
    if (!$cheminInitial) return [];

    $cheminsValides   = [$cheminInitial];
    $cheminsCandidats = new SplPriorityQueue();

    for ($i = 1; $i < $k; $i++) {
        $cheminPrecedent = $cheminsValides[$i - 1];
        $nbArcs          = count($cheminPrecedent);

        for ($j = 0; $j < $nbArcs; $j++) {
            $noeudPivot    = $cheminPrecedent[$j]['from'];
            $grapheModifie = $graphe;

            foreach ($cheminsValides as $chemin) {
                if (count($chemin) > $j) {
                    $egal = true;
                    for ($m = 0; $m <= $j; $m++) {
                        if (
                            $chemin[$m]['from'] !== $cheminPrecedent[$m]['from'] ||
                            $chemin[$m]['to']   !== $cheminPrecedent[$m]['to']
                        ) {
                            $egal = false;
                            break;
                        }
                    }
                    if ($egal && isset($grapheModifie[$noeudPivot])) {
                        foreach ($grapheModifie[$noeudPivot] as $index => $arc) {
                            if ($arc['to'] === $chemin[$j]['to']) {
                                unset($grapheModifie[$noeudPivot][$index]);
                            }
                        }
                    }
                }
            }

            // --- FIX boucle : on interdit au spur de repasser par une
            // ville déjà présente dans la portion racine (sauf le pivot)
            $grapheSpur = $grapheModifie;
            for ($m = 0; $m < $j; $m++) {
                $villeRacine = $cheminPrecedent[$m]['from'];
                if ($villeRacine !== $noeudPivot) {
                    unset($grapheSpur[$villeRacine]);
                }
            }

            $cheminAlternative = dijkstra($grapheSpur, $noeudPivot, $arrivee, $poids);
            if ($cheminAlternative) {
                $cheminComplet = array_merge(array_slice($cheminPrecedent, 0, $j), $cheminAlternative);
                $score         = array_sum(array_column($cheminComplet, $poids));
                $cheminsCandidats->insert($cheminComplet, -$score);
            }
        }

        if ($cheminsCandidats->isEmpty()) break;

        while (!$cheminsCandidats->isEmpty()) {
            $meilleurCandidat = $cheminsCandidats->extract();
            if (!in_array($meilleurCandidat, $cheminsValides)) {
                $cheminsValides[] = $meilleurCandidat;
                break;
            }
        }
    }
    return $cheminsValides;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$codeDep || !$codeArr) {
        $erreur = "Veuillez sélectionner une ville de départ et d'arrivée.";
    } elseif ($codeDep === $codeArr) {
        $erreur = "La ville de départ et d'arrivée doivent être différentes.";
    } else {
        foreach ($villesTab as $v) {
            if ($v['COM_CODE_INSEE'] === $codeDep) $nomDep = $v['COM_NOM'];
            if ($v['COM_CODE_INSEE'] === $codeArr) $nomArr = $v['COM_NOM'];
        }

        $graphe = construireGraphe($conn);
        $poids  = ($critere === 'court') ? 'distance' : 'duree';

        $tousLesChemins = dijkstraKShortestPaths($graphe, $codeDep, $codeArr, $nbReponses, $poids);

        if (empty($tousLesChemins)) {
            $erreur = "Aucun trajet trouvé entre ces deux villes.";
        } else {
            $results = [];

            foreach ($tousLesChemins as $optIdx => $cheminBrut) {
                $chemin       = [];
                $noeudCourant = $codeDep;
                while (count($chemin) < count($cheminBrut)) {
                    foreach ($cheminBrut as $arc) {
                        if ($arc['from'] === $noeudCourant) {
                            $chemin[]     = $arc;
                            $noeudCourant = $arc['to'];
                            break;
                        }
                    }
                }

                $dureeTotal    = array_sum(array_column($chemin, 'duree'));
                $distanceTotal = array_sum(array_column($chemin, 'distance'));

                $premiereLigne = $chemin[0]['ligne'];
                $ligneEscPrem  = str_replace("'", "''", $premiereLigne);

                if (!empty($heureChoisie)) {
                    $heureMinEsc     = str_replace("'", "''", trim($heureChoisie));
                    $heuresPremEtape = ExecSelect($conn, "
                        SELECT DISTINCT TO_CHAR(NOE_HEURE_PASSAGE, 'HH24:MI') AS HEURE
                        FROM VIK_NOEUD
                        WHERE COM_CODE_INSEE_ARRET = '$codeDepEsc'
                          AND LIG_NUM = '$ligneEscPrem'
                          AND TO_CHAR(NOE_HEURE_PASSAGE, 'HH24:MI') >= '$heureMinEsc'
                        ORDER BY HEURE
                    ");
                } else {
                    $heuresPremEtape = ExecSelect($conn, "
                        SELECT DISTINCT TO_CHAR(NOE_HEURE_PASSAGE, 'HH24:MI') AS HEURE
                        FROM VIK_NOEUD
                        WHERE COM_CODE_INSEE_ARRET = '$codeDepEsc'
                          AND LIG_NUM = '$ligneEscPrem'
                        ORDER BY HEURE
                    ");
                }

                if (!empty($heuresPremEtape) && isset($heuresPremEtape[$optIdx])) {
                    $heureDepartInitiale = $heuresPremEtape[$optIdx]['HEURE'];
                } elseif (!empty($heuresPremEtape)) {
                    $heureDepartInitiale = $heuresPremEtape[0]['HEURE'];
                } else {
                    // Aucun passage trouvé pour cette ligne à cette heure : on garde
                    // l'heure choisie (ou minuit par défaut) en dernier recours.
                    $heureDepartInitiale = !empty($heureChoisie) ? trim($heureChoisie) : '00:00';
                }

                $dt                = DateTime::createFromFormat('H:i', $heureDepartInitiale) ?: new DateTime($heureDepartInitiale);
                $heureDepartOption = $dt->format('H:i');

                $segments      = [];
                $ligneActuelle = $chemin[0]['ligne'];
                $segDep        = $chemin[0]['from'];
                $segHeureDep   = $dt->format('H:i');
                $segDist       = 0;
                $segDuree      = 0;

                foreach ($chemin as $arcIdx => $arc) {
                    if ($arc['ligne'] !== $ligneActuelle) {
                        $segments[] = [
                            'ligne'     => $ligneActuelle,
                            'from'      => $segDep,
                            'to'        => $arc['from'],
                            'heure_dep' => $segHeureDep,
                            'heure_arr' => $dt->format('H:i'),
                            'distance'  => $segDist,
                            'duree'     => $segDuree,
                        ];
                        $ligneActuelle = $arc['ligne'];
                        $segDep        = $arc['from'];
                        $nouvelleLigne = str_replace("'", "''", $arc['ligne']);
                        $villeCorresp  = str_replace("'", "''", $arc['from']);
                        $heureActuelle = $dt->format('H:i');

                        $prochainHoraire = ExecSelect($conn, "
            SELECT MIN(TO_CHAR(NOE_HEURE_PASSAGE, 'HH24:MI')) AS PROCHAINE_HEURE
            FROM VIK_NOEUD
            WHERE COM_CODE_INSEE_ARRET = '$villeCorresp'
              AND LIG_NUM = '$nouvelleLigne'
              AND TO_CHAR(NOE_HEURE_PASSAGE, 'HH24:MI') >= '$heureActuelle'
        ");

                        if (!empty($prochainHoraire) && !empty($prochainHoraire[0]['PROCHAINE_HEURE'])) {
                            $nouvelleHeure = DateTime::createFromFormat('H:i', $prochainHoraire[0]['PROCHAINE_HEURE']);
                            if ($nouvelleHeure > $dt) {
                                $dt = $nouvelleHeure;
                            }
                        }

                        $segHeureDep = $dt->format('H:i');
                        $segDist     = 0;
                        $segDuree    = 0;
                    }

                    $segDist  += $arc['distance'];
                    $segDuree += $arc['duree'];

                    $villeArriveeArc = str_replace("'", "''", $arc['to']);
                    $ligneArc        = str_replace("'", "''", $arc['ligne']);
                    $heureDepActuel  = $dt->format('H:i');

                    $heureArriveeArc = ExecSelect($conn, "
        SELECT MIN(TO_CHAR(NOE_HEURE_PASSAGE, 'HH24:MI')) AS HEURE
        FROM VIK_NOEUD
        WHERE COM_CODE_INSEE_ARRET = '$villeArriveeArc'
          AND LIG_NUM = '$ligneArc'
          AND TO_CHAR(NOE_HEURE_PASSAGE, 'HH24:MI') > '$heureDepActuel'
    ");

                    if (!empty($heureArriveeArc) && !empty($heureArriveeArc[0]['HEURE'])) {
                        $dt = DateTime::createFromFormat('H:i', $heureArriveeArc[0]['HEURE']);
                    } else {
                        $dt->modify("+" . round($arc['duree']) . " minutes");
                    }
                }

                $dernierArc = end($chemin);

                $dtArrivee = DateTime::createFromFormat('H:i', $segHeureDep);
                $dtArrivee->modify("+" . round($segDuree) . " minutes");
                $heureArriveeFinale = $dtArrivee->format('H:i');

                $segments[] = [
                    'ligne'     => $ligneActuelle,
                    'from'      => $segDep,
                    'to'        => $dernierArc['to'],
                    'heure_dep' => $segHeureDep,
                    'heure_arr' => $heureArriveeFinale,
                    'distance'  => $segDist,
                    'duree'     => $segDuree,
                ];

                $heureArrivee = $heureArriveeFinale;

                $tousLesCodes = [];
                foreach ($chemin as $arc) {
                    $tousLesCodes[] = $arc['from'];
                    $tousLesCodes[] = $arc['to'];
                }
                $tousLesCodes = array_unique($tousLesCodes);
                $codesEsc     = implode("','", array_map(fn($c) => str_replace("'", "''", $c), $tousLesCodes));
                $nomsVilles   = ExecSelect($conn, "SELECT COM_CODE_INSEE, COM_NOM FROM VIK_COMMUNE WHERE COM_CODE_INSEE IN ('$codesEsc')");
                $mapNoms      = [];
                foreach ($nomsVilles as $v) {
                    $mapNoms[trim($v['COM_CODE_INSEE'])] = $v['COM_NOM'];
                }

                $results[] = [
                    'duree'           => $dureeTotal,
                    'distance'        => $distanceTotal,
                    'heure_depart'    => $heureDepartOption,
                    'heure_arrivee'   => $heureArrivee,
                    'segments'        => $segments,
                    'mapNoms'         => $mapNoms,
                    'correspondances' => count($segments) - 1,
                ];
            }

            usort($results, fn($a, $b) => $a[$poids] <=> $b[$poids]);
        }
    }
}

$conn = null;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../CSS/horaire.css">
    <link rel="stylesheet" href="../CSS/Header.css">
    <link rel="stylesheet" href="../CSS/trajet.css">
    <title>Itinéraire - Viking Transport</title>
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
    <style>
        .titre-option-trajet {
            font-size: 1.4rem;
            margin-top: 35px;
            margin-bottom: 12px;
        }
    </style>
</head>

<body>

    <?php include_once 'header.php'; ?>

    <main>
        <a href="ligne.php" class="revenir">← Revenir en arrière</a><br><br>
        <h1>Calculer un itinéraire</h1><br>

        <form method="POST" class="form-itineraire" id="formTrajet">
            <div class="form-group">
                <label for="depart">Ville de départ</label>
                <select name="depart" id="depart" required onchange="document.getElementById('formTrajet').submit();">
                    <option value="">-- Choisir --</option>
                    <?php foreach ($villesTab as $v): ?>
                        <option value="<?= htmlspecialchars($v['COM_CODE_INSEE']) ?>"
                            <?= ($codeDep === $v['COM_CODE_INSEE']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['COM_NOM']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="arrivee">Ville d'arrivée</label>
                <select name="arrivee" id="arrivee" required onchange="document.getElementById('formTrajet').submit();">
                    <option value="">-- Choisir --</option>
                    <?php foreach ($villesTab as $v): ?>
                        <option value="<?= htmlspecialchars($v['COM_CODE_INSEE']) ?>"
                            <?= ($codeArr === $v['COM_CODE_INSEE']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['COM_NOM']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="heure_depart">Heure de départ</label>
                <select name="heure_depart" id="heure_depart">
                    <option value="">-- Première heure par défaut --</option>
                    <?php foreach ($heuresDispoTab as $h): ?>
                        <option value="<?= htmlspecialchars($h['HEURE']) ?>"
                            <?= ($heureChoisie === $h['HEURE']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($h['HEURE']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="nb_reponses">Nombre de trajets</label>
                <select name="nb_reponses" id="nb_reponses">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>" <?= ($nbReponses === $i) ? 'selected' : '' ?>>
                            <?= $i ?> trajet<?= $i > 1 ? 's' : '' ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Critère</label>
                <div class="critere-group">
                    <label class="critere-option">
                        <input type="radio" name="critere" value="rapide"
                            <?= ($critere === 'rapide') ? 'checked' : '' ?>>
                        <span>Le plus rapide</span>
                    </label>
                    <label class="critere-option">
                        <input type="radio" name="critere" value="court"
                            <?= ($critere === 'court') ? 'checked' : '' ?>>
                        <span>Le plus court</span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-recherche">Rechercher</button>
        </form>

        <?php if ($erreur): ?>
            <p class="erreur"><?= htmlspecialchars($erreur) ?></p>
        <?php endif; ?>

        <?php if (!empty($results)): ?>
            <h2 class="titre-resultats">
                <?= count($results) ?> itinéraire(s) — <?= htmlspecialchars($nomDep) ?> vers <?= htmlspecialchars($nomArr) ?>
            </h2>

            <?php foreach ($results as $index => $result): ?>
                <?php
                $segments = $result['segments'];
                $mapNoms  = $result['mapNoms'];
                ?>

                <div class="trajet-card <?= $index === 0 ? 'trajet-best' : '' ?>">
                    <div class="trajet-card-header">
                        <h3 class="titre-option-trajet">
                            Option #<?= $index + 1 ?> —
                            <?= $critere === 'court' ? round($result['distance'], 1) . ' km' : round($result['duree']) . ' min' ?>
                        </h3>
                        <?php if ($result['correspondances'] === 0): ?>
                            <span class="badge">Trajet direct</span>
                        <?php else: ?>
                            <span class="badge">
                                <?= $result['correspondances'] ?> correspondance<?= $result['correspondances'] > 1 ? 's' : '' ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <p class="trajet-type">
                        Départ <?= htmlspecialchars($result['heure_depart']) ?>
                        — Arrivée <?= htmlspecialchars($result['heure_arrivee']) ?>
                    </p>

                    <div class="trajet-timeline">
                        <?php foreach ($segments as $i => $seg): ?>
                            <div class="tl-stop <?= $i > 0 ? 'tl-stop-corresp' : '' ?>">
                                <span class="tl-ville"><?= htmlspecialchars($mapNoms[$seg['from']] ?? $seg['from']) ?></span>
                                <span class="tl-heure"><?= htmlspecialchars($seg['heure_dep']) ?></span>
                            </div>
                            <?php $numLigneSeul = preg_replace('/[^0-9]/', '', trim($seg['ligne'])); ?>
                            <div class="tl-ligne">
                                <span class="tl-tag" data-ligne="<?= htmlspecialchars($numLigneSeul) ?>">
                                    Ligne <?= htmlspecialchars(trim($seg['ligne'])) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>

                        <div class="tl-stop">
                            <?php $dernierSeg = end($segments); ?>
                            <span class="tl-ville"><?= htmlspecialchars($mapNoms[$dernierSeg['to']] ?? $dernierSeg['to']) ?></span>
                            <span class="tl-heure"><?= htmlspecialchars($dernierSeg['heure_arr']) ?></span>
                        </div>
                    </div>

                    <div class="trajet-stats">
                        <span>Durée : <?= round($result['duree']) ?> min</span>
                        <span>Distance : <?= number_format($result['distance'], 1) ?> km</span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <?php include_once 'footer.php'; ?>
</body>

</html>