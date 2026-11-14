<?php
include_once("../PHP/utils.php");

if (!isset($_GET['id'])) {
    header("Location: admin_client.php");
    exit;
}

$id = $_GET['id'];

$conn = OuvrirConn();

try {

    // Début transaction
    $conn->beginTransaction();

    $req = "
        DELETE FROM VIK_ETAPE
        WHERE CLI_NUM = " . $id;
    ExecMAJ($conn, $req);

    $req = "
        DELETE FROM VIK_RESERVATION
        WHERE CLI_NUM = " . $id;
    ExecMAJ($conn, $req);

    $req = "
        DELETE FROM VIK_CLIENT
        WHERE CLI_NUM = " . $id;
    ExecMAJ($conn, $req);

    // Validation
    $conn->commit();

} catch (Exception $e) {

    // Annulation de tout ce qui a été fait
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    die(
        "<h2>Erreur lors de la suppression</h2>" .
        "<p>" . htmlspecialchars($e->getMessage()) . "</p>"
    );
}

$conn = null;

header("Location: admin_clients.php");
exit;

?>