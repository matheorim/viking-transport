<?php
session_start();
include_once "../PHP/utils.php";

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != true) {
    header("Location: connexion.php");
    exit;
}

$conn = OuvrirConn();

$lignes = ExecSelect($conn, "
    SELECT LIG_NUM,
           c1.COM_NOM AS VILLE_DEP,
           c2.COM_NOM AS VILLE_ARR
    FROM VIK_LIGNE l
    JOIN VIK_COMMUNE c1 ON c1.COM_CODE_INSEE = l.COM_CODE_INSEE_DEBU
    JOIN VIK_COMMUNE c2 ON c2.COM_CODE_INSEE = l.COM_CODE_INSEE_TERM
    ORDER BY LIG_NUM
");

$lig_sel = $_GET['ligne'] ?? null;
$noeuds  = [];

if ($lig_sel) {
    $lig = addslashes($lig_sel);
    $noeuds = ExecSelect($conn, "
        SELECT n.COM_CODE_INSEE_ARRET,
               n.COM_CODE_INSEE_SUIVANT,
               c1.COM_NOM                              AS NOM_ARRET,
               c2.COM_NOM                              AS NOM_SUIVANT,
               TO_CHAR(n.NOE_HEURE_PASSAGE, 'HH24:MI') AS HEURE,
               n.NOE_DISTANCE_PROCHAIN                 AS DISTANCE,
               n.NOE_DUREE_PROCHAIN                    AS DUREE
        FROM VIK_NOEUD n
        JOIN VIK_COMMUNE c1 ON c1.COM_CODE_INSEE = n.COM_CODE_INSEE_ARRET
        LEFT JOIN VIK_COMMUNE c2 ON c2.COM_CODE_INSEE = n.COM_CODE_INSEE_SUIVANT
        WHERE n.LIG_NUM = '$lig'
       ORDER BY TO_CHAR(n.NOE_HEURE_PASSAGE, 'HH24:MI') ASC");
}

$msg = '';
if (isset($_SESSION['admin_lignes_msg'])) {
    $msg = $_SESSION['admin_lignes_msg'];
    unset($_SESSION['admin_lignes_msg']);
}
$conn = null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Viking Transport — Lignes & Horaires</title>
    <link rel="stylesheet" href="../CSS/Header.css"/>
    <link rel="stylesheet" href="../CSS/admin.css"/>
    <link rel="stylesheet" href="../CSS/admin_lignes.css"/>
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
</head>
<body>
<?php include_once 'header.php'; ?>
<main>
    <a href="admin.php" class="back-link">← Retour à l'administration</a>

    <div class="top-actions">
        <h1>Lignes & Horaires</h1>
        <a href="admin_logs.php" class="btn-logs">📋 Journal des modifications</a>
    </div>

    <?php if ($msg): ?>
    <div class="feedback <?= str_starts_with($msg, 'Erreur') ? 'error' : 'success' ?>">
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="admin-section">
        <div class="section-title">Choisir une ligne</div>
        <form method="GET" action="admin_lignes.php" class="ligne-selector">
            <label for="ligne">Ligne :</label>
            <select name="ligne" id="ligne">
                <option value="">-- Sélectionner --</option>
                <?php foreach ($lignes as $l): ?>
                    <option value="<?= htmlspecialchars($l['LIG_NUM']) ?>"
                        <?= ($lig_sel === $l['LIG_NUM']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($l['LIG_NUM']) ?>
                        — <?= htmlspecialchars($l['VILLE_DEP']) ?>
                        → <?= htmlspecialchars($l['VILLE_ARR']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-charger">Charger</button>
        </form>
    </div>

    <?php if ($lig_sel && empty($noeuds)): ?>
        <div class="empty-state"><p>Aucun arrêt trouvé pour cette ligne.</p></div>

    <?php elseif ($lig_sel && !empty($noeuds)): ?>
    <div class="admin-section">
        <div class="section-title">Arrêts de la ligne <?= htmlspecialchars($lig_sel) ?></div>
        <p class="section-desc">
            <strong><?= count($noeuds) ?> arrêt(s)</strong> —
            Modifiez les valeurs puis cliquez sur <strong>Enregistrer</strong>.
            Chaque modification est tracée et annulable dans le journal.
        </p>

        <form method="POST" action="traitement_maj_lignes.php">
            <input type="hidden" name="lig_num" value="<?= htmlspecialchars($lig_sel) ?>"/>

            <table class="edit-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Arrêt</th>
                        <th></th>
                        <th>Arrêt suivant</th>
                        <th>Heure de passage</th>
                        <th>Distance vers suivant (km)</th>
                        <th>Durée vers suivant (min)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($noeuds as $i => $n): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($n['NOM_ARRET']) ?></td>
                        <td class="arrow-cell"><?= $n['NOM_SUIVANT'] ? '→' : '' ?></td>
                        <td>
                            <?php if ($n['NOM_SUIVANT']): ?>
                                <?= htmlspecialchars($n['NOM_SUIVANT']) ?>
                            <?php else: ?>
                                <span class="no-next">Terminus <span class="badge-terminal">fin</span></span>
                            <?php endif; ?>
                        </td>
                        <input type="hidden" name="arret[<?= $i ?>]"         value="<?= htmlspecialchars($n['COM_CODE_INSEE_ARRET']) ?>"/>
                        <input type="hidden" name="suivant[<?= $i ?>]"       value="<?= htmlspecialchars($n['COM_CODE_INSEE_SUIVANT'] ?? '') ?>"/>
                        <input type="hidden" name="heure_orig[<?= $i ?>]"    value="<?= htmlspecialchars($n['HEURE']) ?>"/>
                        <input type="hidden" name="distance_orig[<?= $i ?>]" value="<?= htmlspecialchars($n['DISTANCE'] ?? '') ?>"/>
                        <input type="hidden" name="duree_orig[<?= $i ?>]"    value="<?= htmlspecialchars($n['DUREE'] ?? '') ?>"/>
                        <td>
                            <input type="time" name="heure[<?= $i ?>]"
                                   value="<?= htmlspecialchars($n['HEURE']) ?>" required/>
                        </td>
                        <td>
                            <?php if ($n['NOM_SUIVANT']): ?>
                            <input type="number" name="distance[<?= $i ?>]"
                                   value="<?= htmlspecialchars($n['DISTANCE'] ?? '') ?>"
                                   step="0.1" min="0"/>
                            <?php else: ?><span class="no-next">—</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($n['NOM_SUIVANT']): ?>
                            <input type="number" name="duree[<?= $i ?>]"
                                   value="<?= htmlspecialchars($n['DUREE'] ?? '') ?>"
                                   step="1" min="0"/>
                            <?php else: ?><span class="no-next">—</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button type="submit" class="btn-save">💾 Enregistrer les modifications</button>
        </form>
    </div>

    <?php else: ?>
        <div class="empty-state"><p>Sélectionnez une ligne pour voir et modifier ses arrêts.</p></div>
    <?php endif; ?>
</main>
<?php include_once 'footer.php'; ?>
</body>
</html>