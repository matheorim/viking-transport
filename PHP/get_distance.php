<?php
require_once 'utils.php';

header('Content-Type: application/json');

$depart = $_GET['depart'] ?? '';
$arrivee = $_GET['arrivee'] ?? '';
$ligne = $_GET['ligne'] ?? '';

if (empty($depart) || empty($arrivee) || empty($ligne)) {
    echo json_encode(['distance' => 0]);
    exit;
}

$conn = OuvrirConn();
if (!$conn) {
    echo json_encode(['distance' => 0]);
    exit;
}

// TON algorithme de calcul importé ici !
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

    $file = [ ['noeud' => $depart, 'dist' => 0, 'visites' => [$depart]] ];
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

$distance = calculerDistanceVraie($conn, $ligne, $depart, $arrivee);
echo json_encode(['distance' => round($distance, 1)]);

$conn = null;
?>