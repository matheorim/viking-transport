<?php
session_start();

if (isset($_SESSION['is_admin'])){
    if ($_SESSION['is_admin'] == true){
        $_SESSION['is_admin'] = false;
    }
}

$_SESSION = [];

session_destroy();

header("Location: ../index.php");
exit;

?>