<?php
header('Content-Type: application/json');
include_once 'utils.php';

$conn = OuvrirConn();
if (!$conn) { echo json_encode(['error' => 'Connexion échouée']); exit; }

$ville_depart  = isset($_GET['depart'])  ? $_GET['depart']  : '';
$ville_arrivee = isset($_GET['arrivee']) ? $_GET['arrivee'] : '';
$lig_num       = isset($_GET['ligne'])   ? $_GET['ligne']   : '';
$heure_depart  = isset($_GET['horaire']) ? $_GET['horaire'] : '';

if (empty($ville_depart) || empty($ville_arrivee) || empty($lig_num) || empty($heure_depart)) {
    echo json_encode([]); exit;
}

$req = "
    SELECT HEURE_ARRIVEE
    FROM visu_trajets_directs
    WHERE ville_depart  = :ville_depart
    AND   ville_arrivee = :ville_arrivee
    AND   numero_ligne  = :lig_num
    AND   heure_depart  = :heure_depart
";

try {
    $stmt = $conn->prepare($req);
    $stmt->execute([
        ':ville_depart'  => $ville_depart,
        ':ville_arrivee' => $ville_arrivee,
        ':lig_num'       => $lig_num,
        ':heure_depart'  => $heure_depart
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($result ?: []);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn = null;
?>