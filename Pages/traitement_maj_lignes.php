<?php
session_start();
include_once "../PHP/utils.php";

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != true) {
    header("Location: connexion.php");
    exit;
}

$conn     = OuvrirConn();
$lig_num  = $_POST['lig_num'] ?? '';
$admin    = $_SESSION['cli_email'] ?? 'admin';

if (!$conn || empty($lig_num)) {
    $_SESSION['admin_lignes_msg'] = 'Erreur : connexion ou données manquantes.';
    header("Location: admin_lignes.php");
    exit;
}

$arrets        = $_POST['arret']        ?? [];
$suivants      = $_POST['suivant']      ?? [];
$heures        = $_POST['heure']        ?? [];
$distances     = $_POST['distance']     ?? [];
$durees        = $_POST['duree']        ?? [];
$heures_orig   = $_POST['heure_orig']   ?? [];
$dist_orig     = $_POST['distance_orig'] ?? [];
$duree_orig    = $_POST['duree_orig']   ?? [];

$nb_modifs = 0;
$erreurs   = [];

try {
    // 🛡️ SÉCURITÉ : Protection contre les apostrophes pour les variables SQL directes
    $lig_esc = str_replace("'", "''", $lig_num);

    foreach ($arrets as $i => $insee_arret) {
        $insee_arret  = trim($insee_arret);
        $insee_suiv   = trim($suivants[$i] ?? '');
        $nouvelle_h   = trim($heures[$i] ?? '');
        $nouvelle_d   = trim($distances[$i] ?? '');
        $nouvelle_dur = trim($durees[$i] ?? '');
        $ancienne_h   = trim($heures_orig[$i] ?? '');
        $ancienne_d   = trim($dist_orig[$i] ?? '');
        $ancienne_dur = trim($duree_orig[$i] ?? '');

        if (empty($insee_arret) || empty($nouvelle_h)) continue;

        // 🛡️ SÉCURITÉ : Échappement des codes INSEE
        $insee_arret_esc = str_replace("'", "''", $insee_arret);
        $insee_suiv_esc  = str_replace("'", "''", $insee_suiv);

        // Validation format heure HH:MM
        if (!preg_match('/^\d{2}:\d{2}$/', $nouvelle_h)) {
            $erreurs[] = "Heure invalide pour l'arrêt $insee_arret";
            continue;
        }

        $where_suiv = $insee_suiv_esc !== ''
            ? "AND COM_CODE_INSEE_SUIVANT = '$insee_suiv_esc'"
            : "AND COM_CODE_INSEE_SUIVANT IS NULL";

        // 🧠 LE CORRECTIF DU BUG FANTÔME :
        // L'heure cible pour trouver la ligne est l'ancienne heure au début...
        $heure_cible_where = $ancienne_h;

        // --- UPDATE PUIS LOG heure ---
        if ($nouvelle_h !== $ancienne_h) {
            ExecMAJ($conn, "
                UPDATE VIK_NOEUD
                SET NOE_HEURE_PASSAGE = TO_DATE('$nouvelle_h', 'HH24:MI')
                WHERE LIG_NUM = '$lig_esc'
                  AND COM_CODE_INSEE_ARRET = '$insee_arret_esc'
                  $where_suiv
                  AND TO_CHAR(NOE_HEURE_PASSAGE, 'HH24:MI') = '$heure_cible_where'
            ");
            
            ExecMAJ($conn, "
                INSERT INTO VIK_AUDIT_LOG
                    (LOG_TABLE, LOG_ACTION, LOG_LIG_NUM, LOG_INSEE, LOG_INSEE_SUI, LOG_CHAMP, LOG_AVANT, LOG_APRES, LOG_USER)
                VALUES
                    ('VIK_NOEUD', 'UPDATE', '$lig_esc', '$insee_arret_esc', '$insee_suiv_esc',
                     'NOE_HEURE_PASSAGE', '$ancienne_h', '$nouvelle_h', '$admin')
            ");
            $nb_modifs++;

            // 🧠 ... Mais si l'heure a été modifiée avec succès, la NOUVELLE heure 
            // devient la cible pour que les requêtes distance et durée trouvent la ligne !
            $heure_cible_where = $nouvelle_h;
        }

        // --- UPDATE PUIS LOG distance ---
        if ($insee_suiv !== '' && $nouvelle_d !== '' && $nouvelle_d !== $ancienne_d) {
            $nd = floatval($nouvelle_d);
            if ($nd < 0) { $erreurs[] = "Distance négative pour $insee_arret"; continue; }
            
            ExecMAJ($conn, "
                UPDATE VIK_NOEUD
                SET NOE_DISTANCE_PROCHAIN = $nd
                WHERE LIG_NUM = '$lig_esc'
                  AND COM_CODE_INSEE_ARRET = '$insee_arret_esc'
                  AND COM_CODE_INSEE_SUIVANT = '$insee_suiv_esc'
                  AND TO_CHAR(NOE_HEURE_PASSAGE, 'HH24:MI') = '$heure_cible_where'
            ");
            ExecMAJ($conn, "
                INSERT INTO VIK_AUDIT_LOG
                    (LOG_TABLE, LOG_ACTION, LOG_LIG_NUM, LOG_INSEE, LOG_INSEE_SUI, LOG_CHAMP, LOG_AVANT, LOG_APRES, LOG_USER)
                VALUES
                    ('VIK_NOEUD', 'UPDATE', '$lig_esc', '$insee_arret_esc', '$insee_suiv_esc',
                     'NOE_DISTANCE_PROCHAIN', '$ancienne_d', '$nouvelle_d', '$admin')
            ");
            $nb_modifs++;
        }

        // --- UPDATE PUIS LOG durée ---
        if ($insee_suiv !== '' && $nouvelle_dur !== '' && $nouvelle_dur !== $ancienne_dur) {
            $ndur = intval($nouvelle_dur);
            if ($ndur < 0) { $erreurs[] = "Durée négative pour $insee_arret"; continue; }
            
            ExecMAJ($conn, "
                UPDATE VIK_NOEUD
                SET NOE_DUREE_PROCHAIN = $ndur
                WHERE LIG_NUM = '$lig_esc'
                  AND COM_CODE_INSEE_ARRET = '$insee_arret_esc'
                  AND COM_CODE_INSEE_SUIVANT = '$insee_suiv_esc'
                  AND TO_CHAR(NOE_HEURE_PASSAGE, 'HH24:MI') = '$heure_cible_where'
            ");
            ExecMAJ($conn, "
                INSERT INTO VIK_AUDIT_LOG
                    (LOG_TABLE, LOG_ACTION, LOG_LIG_NUM, LOG_INSEE, LOG_INSEE_SUI, LOG_CHAMP, LOG_AVANT, LOG_APRES, LOG_USER)
                VALUES
                    ('VIK_NOEUD', 'UPDATE', '$lig_esc', '$insee_arret_esc', '$insee_suiv_esc',
                     'NOE_DUREE_PROCHAIN', '$ancienne_dur', '$nouvelle_dur', '$admin')
            ");
            $nb_modifs++;
        }
    }

    if (!empty($erreurs)) {
        $_SESSION['admin_lignes_msg'] = 'Erreur(s) : ' . implode(' | ', $erreurs);
    } elseif ($nb_modifs === 0) {
        $_SESSION['admin_lignes_msg'] = 'Aucune modification détectée.';
    } else {
        $_SESSION['admin_lignes_msg'] = " $nb_modifs modification(s) enregistrée(s) et tracée(s) dans le journal.";
    }

} catch (Exception $e) {
    $erreurMsg = $e->getMessage();
    
    // Interception propre de l'erreur de doublon Oracle (Contrainte Unique)
    if (str_contains($erreurMsg, 'ORA-00001')) {
        $_SESSION['admin_lignes_msg'] = " Erreur : Impossible d'enregistrer l'horaire. Un autre bus passe DÉJÀ à cette minute précise à cet arrêt !";
    } else {
        $_SESSION['admin_lignes_msg'] = ' Erreur SQL : ' . $erreurMsg;
    }
}

$conn = null;
header("Location: admin_lignes.php?ligne=" . urlencode($lig_num));
exit;