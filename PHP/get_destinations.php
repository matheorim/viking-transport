<?php
header('Content-Type: application/json');
include_once 'utils.php';

$conn = OuvrirConn();
if (!$conn) { echo json_encode(['error' => 'Connexion échouée']); exit; }

$insee_depart = isset($_GET['depart']) ? $_GET['depart'] : '';
if (empty($insee_depart)) { echo json_encode([]); exit; }

$req = "
    SELECT DISTINCT c.COM_CODE_INSEE, c.COM_NOM
    FROM VIK_COMMUNE c
    JOIN VIK_NOEUD n_arr ON n_arr.COM_CODE_INSEE_ARRET = c.COM_CODE_INSEE
    JOIN VIK_NOEUD n_dep ON n_dep.LIG_NUM = n_arr.LIG_NUM
    WHERE n_dep.COM_CODE_INSEE_ARRET = :insee_depart
    AND n_arr.NOE_HEURE_PASSAGE > n_dep.NOE_HEURE_PASSAGE
    ORDER BY c.COM_NOM
";

try {
    $stmt = $conn->prepare($req);
    $stmt->execute([':insee_depart' => $insee_depart]);
    $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($destinations);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn = null;
?>