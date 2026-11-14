<?php
session_start();
require_once("../PHP/utils.php");

$admin_mail = "admin@example.com";
$admin_pass = "CHANGEZ_MOI";

if (trim($_POST['email']) == $admin_mail && $_POST['password'] == $admin_pass){
    $_SESSION['is_logged_in'] = true;
    $_SESSION['is_admin'] = true;
    $_SESSION['cli_prenom'] = "Admin";
    $_SESSION['cli_email']  = "admin@example.com";
    header("Location: admin.php");
    exit;
}

$conn = OuvrirConn();

$mail = trim($_POST['email']);

$results = ExecSelect($conn, "select cli_password from vik_client where cli_courriel = '" . $mail . "'");


if (!isset($results[0])) {
    header("Location: connexion_echouee.php?err=no_user");
    exit;
}
else {
    $crypted = ($results[0]['CLI_PASSWORD'] ?? $results[0]['cli_password'] ?? '');
}


if (password_verify($_POST['password'], $crypted)){
    $_SESSION['is_logged_in'] = true;
    $user_val = ExecSelect($conn, "select * from vik_client where cli_courriel = '" . $mail . "'")[0];
    $prenom    = $user_val['CLI_PRENOM']    ?? $user_val['cli_prenom']    ?? '';
    $nom       = $user_val['CLI_NOM']       ?? $user_val['cli_nom']       ?? '';
    $email     = $user_val['CLI_COURRIEL']  ?? $user_val['cli_courriel']  ?? '';
    $telephone = $user_val['CLI_TELEPHONE'] ?? $user_val['cli_telephone'] ?? '';
    $ville     = $user_val['CLI_VILLE']     ?? $user_val['cli_ville']     ?? '';
    $dep_num   = $user_val['DEP_NUM']       ?? $user_val['dep_num']       ?? '';

    $conn_update = OuvrirConn();
    $date = date('Y-m-d');
    ExecMAJ($conn_update, "UPDATE vik_client set CLI_DATE_CONNEC = TO_DATE('$date','YYYY-MM-DD') where cli_courriel = '" . $mail . "'");
    $conn_update = null;


    $_SESSION['cli_num']  = ($user_val['CLI_NUM'] ?? $user_val['cli_num'] ?? null);
    if ($prenom) $_SESSION['cli_prenom'] = ucfirst(strtolower($prenom));
    if ($nom)    $_SESSION['cli_nom']    = strtoupper($nom);
    if ($email)  $_SESSION['cli_email']  = $email;
    if ($telephone) $_SESSION['cli_tel'] = $telephone;
    if ($ville) $_SESSION['cli_ville'] = $ville;
    if ($dep_num) $_SESSION['cli_dep'] = $dep_num;
}

else{
    header("Location: connexion_echouee.php?err=wrong_pass");
    $conn = null;
    exit;
}

$conn = null;

header("Location: ../index.php");
exit;

?>