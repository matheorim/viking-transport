<?php
include_once("../PHP/utils.php");

if ($_GET['err'] == "no_user"){
    // utilisateur incorrect
    header("Location: connexion.php?err=no_user");
    exit;
}

if ($_GET['err'] == "wrong_pass"){
    // mdp incorrect
    header("Location: connexion.php?err=wrong_pass");
    exit;
}

?>