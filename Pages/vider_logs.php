<?php
session_start();
include_once "../PHP/utils.php";

// 1. On vérifie toujours que c'est bien l'admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != true) {
    header("Location: connexion.php");
    exit;
}

$conn = OuvrirConn();

if (!$conn) {
    $_SESSION['log_msg'] = ' Erreur de connexion à la base de données.';
    header("Location: admin_logs.php");
    exit;
}

try {
    // 2. On exécute la requête radicale qui supprime toutes les lignes
    ExecMAJ($conn, "DELETE FROM VIK_AUDIT_LOG");
    
    // 3. On prépare le message de succès
    $_SESSION['log_msg'] = " L'historique a été entièrement vidé avec succès !";

} catch (Exception $e) {
    // 4. S'il y a un problème, on l'affiche proprement
    $_SESSION['log_msg'] = ' Erreur lors du nettoyage : ' . $e->getMessage();
}

$conn = null;

// 5. On renvoie l'admin vers la page des logs
header("Location: admin_logs.php");
exit;