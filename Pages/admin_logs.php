<?php
session_start();
include_once "../PHP/utils.php";

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != true) {
    header("Location: connexion.php");
    exit;
}

$conn = OuvrirConn();

$filtre_ligne  = $_GET['ligne']  ?? '';
$filtre_champ  = $_GET['champ']  ?? '';
$filtre_limite = intval($_GET['limite'] ?? 50);

$where = "WHERE 1=1";
if ($filtre_ligne) {
    $l = addslashes($filtre_ligne);
    $where .= " AND LOG_LIG_NUM = '$l'";
}
if ($filtre_champ) {
    $c = addslashes($filtre_champ);
    $where .= " AND LOG_CHAMP = '$c'";
}

$logs = ExecSelect($conn, "
    SELECT LOG_ID, TO_CHAR(LOG_DATE,'DD/MM/YYYY HH24:MI:SS') AS LOG_DATE,
           LOG_TABLE, LOG_ACTION, LOG_LIG_NUM,
           LOG_INSEE, LOG_INSEE_SUI, LOG_CHAMP,
           LOG_AVANT, LOG_APRES, LOG_USER
    FROM VIK_AUDIT_LOG
    $where
    ORDER BY LOG_ID DESC
    FETCH FIRST $filtre_limite ROWS ONLY
");

$toutes_lignes = ExecSelect($conn, "SELECT DISTINCT LOG_LIG_NUM FROM VIK_AUDIT_LOG ORDER BY LOG_LIG_NUM");

$msg = '';
if (isset($_SESSION['log_msg'])) {
    $msg = $_SESSION['log_msg'];
    unset($_SESSION['log_msg']);
}
$conn = null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Viking Transport — Journal des modifications</title>
    <link rel="stylesheet" href="../CSS/Header.css"/>
    <link rel="stylesheet" href="../CSS/admin.css"/>
    <link rel="stylesheet" href="../CSS/admin_lignes.css"/>
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
</head>
<body>
<?php include_once 'header.php'; ?>
<main>
    <a href="admin_lignes.php" class="back-link">← Retour à l'édition des lignes</a>
    <h1>Journal des modifications</h1>

    <?php if ($msg): ?>
    <div class="feedback <?= str_starts_with($msg, 'Erreur') ? 'error' : (str_starts_with($msg, 'Aucune') ? 'info' : 'success') ?>">
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="admin-section">
        <div class="section-title">Filtres</div>
        <form method="GET" action="admin_logs.php" class="filters">
            <div>
                <label>Ligne</label>
                <select name="ligne">
                    <option value="">Toutes</option>
                    <?php foreach ($toutes_lignes as $tl): ?>
                        <option value="<?= htmlspecialchars($tl['LOG_LIG_NUM']) ?>"
                            <?= ($filtre_ligne === $tl['LOG_LIG_NUM']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tl['LOG_LIG_NUM']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Champ modifié</label>
                <select name="champ">
                    <option value="">Tous</option>
                    <option value="NOE_HEURE_PASSAGE"     <?= $filtre_champ === 'NOE_HEURE_PASSAGE'     ? 'selected' : '' ?>>Heure de passage</option>
                    <option value="NOE_DISTANCE_PROCHAIN" <?= $filtre_champ === 'NOE_DISTANCE_PROCHAIN' ? 'selected' : '' ?>>Distance</option>
                    <option value="NOE_DUREE_PROCHAIN"    <?= $filtre_champ === 'NOE_DUREE_PROCHAIN'    ? 'selected' : '' ?>>Durée</option>
                </select>
            </div>
            <div>
                <label>Afficher</label>
                <select name="limite">
                    <?php foreach ([20, 50, 100, 200] as $lim): ?>
                        <option value="<?= $lim ?>" <?= $filtre_limite === $lim ? 'selected' : '' ?>>
                            <?= $lim ?> dernières
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-charger">Filtrer</button>
        </form>
    </div>

    <div class="admin-section">
        <div class="section-title">Historique</div>
        <form action="vider_logs.php" method="POST" style="display:inline-block;">
            <button type="submit" class="btn btn-danger" style="background-color: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;" onclick="return confirm('⚠️ Êtes-vous sûr de vouloir supprimer TOUT l\'historique ? Cette action est définitive.');">
                 Vider tout l'historique
            </button>
</form>
        <p class="section-desc"><?= count($logs) ?> modification(s) trouvée(s)</p>

        <?php if (empty($logs)): ?>
            <div class="empty-state"><p>Aucune modification enregistrée.</p></div>
        <?php else: ?>
        <table class="log-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Ligne</th>
                    <th>Arrêt (INSEE)</th>
                    <th>Champ</th>
                    <th>Avant</th>
                    <th>Après</th>
                    <th>Admin</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="col-id"><?= $log['LOG_ID'] ?></td>
                    <td class="col-date"><?= htmlspecialchars($log['LOG_DATE']) ?></td>
                    <td><span class="ligne-badge"><?= htmlspecialchars($log['LOG_LIG_NUM'] ?? '—') ?></span></td>
                    <td class="col-insee">
                        <?= htmlspecialchars($log['LOG_INSEE'] ?? '—') ?>
                        <?php if ($log['LOG_INSEE_SUI']): ?>
                            <span class="col-insee-arrow"> → <?= htmlspecialchars($log['LOG_INSEE_SUI']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="champ-badge"><?= htmlspecialchars($log['LOG_CHAMP'] ?? '—') ?></span></td>
                    <td class="val-avant"><?= htmlspecialchars($log['LOG_AVANT'] ?? '—') ?></td>
                    <td class="val-apres"><?= htmlspecialchars($log['LOG_APRES'] ?? '—') ?></td>
                    <td class="col-admin"><?= htmlspecialchars($log['LOG_USER'] ?? '—') ?></td>
                    <td>
                        <form method="POST" action="traitement_restaurer_log.php"
                              onsubmit="return confirm('Restaurer la valeur « <?= htmlspecialchars(addslashes($log['LOG_AVANT'] ?? '')) ?> » ?')">
                            <input type="hidden" name="log_id"    value="<?= intval($log['LOG_ID']) ?>"/>
                            <input type="hidden" name="lig_num"   value="<?= htmlspecialchars($log['LOG_LIG_NUM'] ?? '') ?>"/>
                            <input type="hidden" name="insee"     value="<?= htmlspecialchars($log['LOG_INSEE'] ?? '') ?>"/>
                            <input type="hidden" name="insee_sui" value="<?= htmlspecialchars($log['LOG_INSEE_SUI'] ?? '') ?>"/>
                            <input type="hidden" name="champ"     value="<?= htmlspecialchars($log['LOG_CHAMP'] ?? '') ?>"/>
                            <input type="hidden" name="val_avant" value="<?= htmlspecialchars($log['LOG_AVANT'] ?? '') ?>"/>
                            <button type="submit" class="btn-annuler">↩ Annuler</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</main>
<?php include_once 'footer.php'; ?>
</body>
</html>