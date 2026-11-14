<?php
session_start();
include_once "../PHP/utils.php";

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != true) {
    header("Location: connexion.php");
    exit;
}

$conn      = OuvrirConn();
$log_id    = intval($_POST['log_id']   ?? 0);
$lig_num   = trim($_POST['lig_num']    ?? '');
$insee     = trim($_POST['insee']      ?? '');
$insee_sui = trim($_POST['insee_sui']  ?? '');
$champ     = trim($_POST['champ']      ?? '');
$val_avant = trim($_POST['val_avant']  ?? '');
$admin     = $_SESSION['cli_email']    ?? 'admin';

if (!$conn || !$log_id || !$lig_num || !$insee || !$champ) {
    $_SESSION['log_msg'] = 'Erreur : paramètres manquants.';
    header("Location: admin_logs.php");
    exit;
}

$champs_autorises = ['NOE_HEURE_PASSAGE', 'NOE_DISTANCE_PROCHAIN', 'NOE_DUREE_PROCHAIN'];
if (!in_array($champ, $champs_autorises)) {
    $_SESSION['log_msg'] = 'Erreur : champ non autorisé.';
    header("Location: admin_logs.php");
    exit;
}

try {
    $lig_esc     = str_replace("'", "''", $lig_num);
    $insee_esc   = str_replace("'", "''", $insee);
    $insee_s_esc = str_replace("'", "''", $insee_sui);

    $actuel = ExecSelect($conn, "
        SELECT TO_CHAR(NOE_HEURE_PASSAGE,'HH24:MI') AS H,
               NOE_DISTANCE_PROCHAIN                AS D,
               NOE_DUREE_PROCHAIN                   AS DUR
        FROM VIK_NOEUD
        WHERE LIG_NUM = '$lig_esc'
          AND COM_CODE_INSEE_ARRET = '$insee_esc'
          AND (COM_CODE_INSEE_SUIVANT = '$insee_s_esc' OR (COM_CODE_INSEE_SUIVANT IS NULL AND '$insee_s_esc' = ''))
    ");

    $val_courante = '';
    if (!empty($actuel)) {
        if ($champ === 'NOE_HEURE_PASSAGE')     $val_courante = $actuel[0]['H']   ?? '';
        if ($champ === 'NOE_DISTANCE_PROCHAIN') $val_courante = $actuel[0]['D']   ?? '';
        if ($champ === 'NOE_DUREE_PROCHAIN')    $val_courante = $actuel[0]['DUR'] ?? '';
    }

    if ($champ === 'NOE_HEURE_PASSAGE') {
        if (!preg_match('/^\d{2}:\d{2}$/', $val_avant)) {
            throw new Exception("Format d'heure invalide : $val_avant");
        }
        $set_clause = "NOE_HEURE_PASSAGE = TO_DATE('$val_avant', 'HH24:MI')";
    } elseif ($champ === 'NOE_DISTANCE_PROCHAIN') {
        $val_num    = floatval($val_avant);
        $set_clause = "NOE_DISTANCE_PROCHAIN = $val_num";
    } elseif ($champ === 'NOE_DUREE_PROCHAIN') {
        $val_num    = intval($val_avant);
        $set_clause = "NOE_DUREE_PROCHAIN = $val_num";
    }

    $where_sui = $insee_sui !== ''
        ? "AND COM_CODE_INSEE_SUIVANT = '$insee_s_esc'"
        : "AND COM_CODE_INSEE_SUIVANT IS NULL";

    ExecMAJ($conn, "
        UPDATE VIK_NOEUD
        SET $set_clause
        WHERE LIG_NUM = '$lig_esc'
          AND COM_CODE_INSEE_ARRET = '$insee_esc'
          $where_sui
    ");

    $val_avant_esc = str_replace("'", "''", $val_avant);
    $val_cour_esc  = str_replace("'", "''", $val_courante);
    $admin_esc     = str_replace("'", "''", $admin);

    ExecMAJ($conn, "
        INSERT INTO VIK_AUDIT_LOG
            (LOG_TABLE, LOG_ACTION, LOG_LIG_NUM, LOG_INSEE, LOG_INSEE_SUI, LOG_CHAMP, LOG_AVANT, LOG_APRES, LOG_USER)
        VALUES
            ('VIK_NOEUD', 'RESTORE', '$lig_esc', '$insee_esc', '$insee_s_esc',
             '$champ', '$val_cour_esc', '$val_avant_esc', '$admin_esc (restauration #$log_id)')
    ");

    $_SESSION['log_msg'] = "✓ Restauration effectuée : $champ remis à « $val_avant » (ligne $lig_num, arrêt $insee).";

} catch (Exception $e) {
    $_SESSION['log_msg'] = 'Erreur lors de la restauration : ' . $e->getMessage();
}

$conn = null;
header("Location: admin_logs.php");
exit;