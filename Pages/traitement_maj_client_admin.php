<?php
session_start();
require_once '../PHP/utils.php';

$cli_num = intval($_POST['cli_num'] ?? 0);
$origin  = trim($_POST['origin'] ?? 'profil');
$redirect = 'admin_client.php?id=' . $cli_num;

if (!$cli_num) {
    header('Location: admin_clients.php');
    exit;
}

$conn = OuvrirConn();
if (!$conn) {
    $_SESSION['update_msg'] = 'Erreur de connexion à la base de données.';
    header('Location: ' . $redirect);
    exit;
}

$champs = [];

$prenom    = trim($_POST['prenom']    ?? '');
$nom       = trim($_POST['nom']       ?? '');
$email     = trim($_POST['email']     ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$ville     = trim($_POST['ville']     ?? '');
$dep_num   = trim($_POST['dep_num']   ?? '');

if ($prenom)    $champs[] = "CLI_PRENOM = '"   . addslashes(ucfirst(strtolower($prenom))) . "'";
if ($nom)       $champs[] = "CLI_NOM = '"      . addslashes(strtoupper($nom)) . "'";
if ($email)     $champs[] = "CLI_COURRIEL = '" . addslashes($email) . "'";
if ($telephone) $champs[] = "CLI_TELEPHONE = '" . addslashes($telephone) . "'";
if ($ville)     $champs[] = "CLI_VILLE = '"    . addslashes($ville) . "'";
if ($dep_num)   $champs[] = "DEP_NUM = "       . intval($dep_num);

if (empty($champs)) {
    $conn = null;
    header('Location: ' . $redirect);
    exit;
}

try {
    $setClause = implode(', ', $champs);
    $req = "UPDATE VIK_CLIENT SET $setClause WHERE CLI_NUM = $cli_num";
    ExecMAJ($conn, $req);

    // Mettre à jour la session
    if ($prenom)    $_SESSION['cli_prenom'] = ucfirst(strtolower($prenom));
    if ($nom)       $_SESSION['cli_nom']    = strtoupper($nom);
    if ($email)     $_SESSION['cli_email']  = $email;

    $_SESSION['update_msg'] = 'Vos informations ont bien été mises à jour.';
} catch (Exception $e) {
    $_SESSION['update_msg'] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
}

$conn = null;
header('Location: ' . $redirect);
exit;
?>