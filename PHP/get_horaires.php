<?php
header('Content-Type: application/json');
include_once 'utils.php';

$conn = OuvrirConn();
if (!$conn) { echo json_encode(['error' => 'Connexion échouée']); exit; }

$ville_depart  = isset($_GET['depart'])    ? $_GET['depart']    : '';
$ville_arrivee = isset($_GET['arrivee'])   ? $_GET['arrivee']   : '';
$lig_num       = isset($_GET['ligne'])     ? $_GET['ligne']     : '';
$heure_min     = isset($_GET['heure_min']) ? $_GET['heure_min'] : null;

if (empty($ville_depart) || empty($ville_arrivee) || empty($lig_num)) { echo json_encode([]); exit; }

$req = "
    SELECT DISTINCT heure_depart
    FROM visu_trajets_directs
    WHERE ville_depart  = :ville_depart
    AND   ville_arrivee = :ville_arrivee
    AND   numero_ligne  = :lig_num
" . ($heure_min ? " AND heure_depart > :heure_min" : "") . "
    ORDER BY heure_depart ASC
";

try {
    $stmt = $conn->prepare($req);
    $params = [
        ':ville_depart'  => $ville_depart,
        ':ville_arrivee' => $ville_arrivee,
        ':lig_num'       => $lig_num
    ];
    if ($heure_min) $params[':heure_min'] = $heure_min;
    $stmt->execute($params);
    $horaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($horaires);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn = null;
?>