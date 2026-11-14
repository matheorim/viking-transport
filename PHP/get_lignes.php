<?php
header('Content-Type: application/json');
include_once 'utils.php';

$conn = OuvrirConn();
if (!$conn) { echo json_encode(['error' => 'Connexion échouée']); exit; }

$ville_depart  = isset($_GET['depart'])  ? $_GET['depart']  : '';
$ville_arrivee = isset($_GET['arrivee']) ? $_GET['arrivee'] : '';

if (empty($ville_depart) || empty($ville_arrivee)) { echo json_encode([]); exit; }

$req = "
    SELECT DISTINCT numero_ligne
    FROM visu_trajets_directs
    WHERE ville_depart  = :ville_depart
    AND   ville_arrivee = :ville_arrivee
    ORDER BY numero_ligne
";

try {
    $stmt = $conn->prepare($req);
    $stmt->execute([
        ':ville_depart'  => $ville_depart,
        ':ville_arrivee' => $ville_arrivee
    ]);
    $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($lignes);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn = null;
?>