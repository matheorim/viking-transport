<?php
header('Content-Type: application/json');
include_once 'utils.php';

$conn = OuvrirConn();
if (!$conn) { echo json_encode(['error' => 'Connexion échouée']); exit; }

$req = "
    SELECT DISTINCT c.COM_CODE_INSEE, c.COM_NOM
    FROM VIK_COMMUNE c
    JOIN VIK_NOEUD n ON n.COM_CODE_INSEE_ARRET = c.COM_CODE_INSEE
    ORDER BY c.COM_NOM
";

$arrets = ExecSelect($conn, $req);

$conn = null;
echo json_encode($arrets);
?>