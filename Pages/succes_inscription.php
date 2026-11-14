<?php
session_start();
require_once '../PHP/utils.php';

// recuperer ville et depart si existant
function recup_ville($conn){
    if (!isset($_SESSION['cli_ville'])){
        return null;
    }
    else {
        $result = ExecSelect($conn, "select cli_ville from vik_client where cli_courriel = '" . $_SESSION['cli_ville'] . "'");
        if (isset($result[0])){
            return trim($result[0]);
        }
        else {
            return null;
        }
    }
}

function recup_dep($conn){
    if (!isset($_SESSION['cli_dep'])){
        return null;
    }
    else {
        $result = ExecSelect($conn, "select cli_ville from vik_client where cli_courriel = '" . $_SESSION['cli_dep'] . "'");
        if (isset($result[0])){
            return trim($result[0]);
        }
        else {
            return null;
        }
    }
}

// ---------- Récupération et validation des données du formulaire ----------
$insertOk = false;
$cli_num   = $_SESSION['cli_num'] ?? null;
$errorMsg  = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prenom'])) {
    $prenom    = trim($_POST['prenom']    ?? '');
    $nom       = trim($_POST['nom']       ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = trim($_POST['password']  ?? '');

    if (empty($prenom) || empty($nom) || empty($email)) {
        $conn = null;
        header('Location: inscription.php?error=champs_manquants');
        exit;
    }

    // ---------- Connexion BDD et insertion ----------
    $conn = OuvrirConn();
    if ($conn) {
        try {
            // Récupérer le prochain CLI_NUM (séquence Oracle ou MAX+1)
            $resNum = ExecSelect($conn, "SELECT NVL(MAX(CLI_NUM), 0) + 1 AS NEXT_NUM FROM VIK_CLIENT");
            $cli_num = $resNum[0]['NEXT_NUM'] ?? 1;

            // TYP_NUM = 1 par défaut (type client de base)
            $nomEsc       = addslashes(strtoupper($nom));
            $prenomEsc    = addslashes(ucfirst(strtolower($prenom)));
            $emailEsc     = addslashes($email);
            $telEsc       = addslashes($telephone);
            $telEsc       = str_replace(' ', '', $telEsc);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $dateConnec   = date('Y-m-d');

            $req = "INSERT INTO VIK_CLIENT 
                        (CLI_NUM, TYP_NUM, CLI_NOM, CLI_PRENOM, CLI_TELEPHONE, CLI_COURRIEL, CLI_PASSWORD, CLI_NB_POINTS_EC, CLI_NB_POINTS_TOT, CLI_DATE_CONNEC)
                    VALUES 
                        ($cli_num, 1, '$nomEsc', '$prenomEsc', '$telEsc', '$emailEsc', '$passwordHash', 10, 10, TO_DATE('$dateConnec','YYYY-MM-DD'))";

            ExecMAJ($conn, $req);
            $insertOk = true;

            // Stocker en session
            $_SESSION['cli_num']  = $cli_num;
            $_SESSION['cli_nom']  = strtoupper($nom);
            $_SESSION['cli_prenom'] = ucfirst(strtolower($prenom));
            $_SESSION['cli_email'] = $email;
            $_SESSION['is_logged_in'] = true;

        } catch (Exception $e) {
            //$errorMsg = $e->getMessage();
        }
    }
} else if (isset($_SESSION['cli_num'])) {
    // Si on arrive ici sans POST mais avec une session, c'est une consultation après mise à jour
    $insertOk = true;
    $cli_num = $_SESSION['cli_num'];
} else {
    // Pas de POST et pas de session : retour à l'inscription
        $conn = null;
    header('Location: inscription.php');
    exit;
}

// Connexion pour récupérer les départements si non déjà ouverte
$conn = $conn ?? OuvrirConn();
$departements = [];
if ($conn) {
    $departements = ExecSelect($conn, "SELECT DEP_NUM, DEP_NOM FROM VIK_DEPARTEMENT ORDER BY DEP_NOM");
}

// ---------- Message de mise à jour (si retour depuis le formulaire de complétion) ----------
$updateMsg = '';
if (isset($_SESSION['update_msg'])) {
    $updateMsg = $_SESSION['update_msg'];
    unset($_SESSION['update_msg']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bienvenue - Viking Transport</title>
    <link rel="stylesheet" href="../CSS/inscription.css" />
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
    <style>
        /* ===== VARIABLES & BASE ===== */
        :root {
            --vt-red:    #c0392b;
            --vt-dark:   #1a1a2e;
            --vt-mid:    #2d2d44;
            --vt-light:  #f5f5f0;
            --vt-muted:  #9ca3af;
            --vt-border: #e2e8f0;
            --vt-success:#16a34a;
            --vt-warn:   #d97706;
        }

        /* ===== PAGE LAYOUT ===== */
        .succes-body {
            min-height: 100vh;
            background: var(--vt-light);
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .succes-wrapper {
            max-width: 760px;
            margin: 0 auto;
            padding: 40px 20px 60px;
        }

        /* ===== BANNER BIENVENUE ===== */
        .bienvenue-banner {
            background: linear-gradient(135deg, var(--vt-dark) 0%, var(--vt-mid) 100%);
            border-radius: 16px;
            padding: 36px 40px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 32px;
            box-shadow: 0 8px 32px rgba(26,26,46,.18);
            position: relative;
            overflow: hidden;
        }
        .bienvenue-banner::before {
            content: '';
            position: absolute;
            right: -40px; top: -40px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(192,57,43,.18);
        }
        .bienvenue-avatar {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: var(--vt-red);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }
        .bienvenue-text {
            position: relative; z-index: 1;
        }
        .bienvenue-text h1 {
            margin: 0 0 6px;
            font-size: 1.55rem;
            font-weight: 700;
            letter-spacing: -.5px;
        }
        .bienvenue-text p {
            margin: 0;
            color: rgba(255,255,255,.72);
            font-size: .95rem;
        }
        .bienvenue-badge {
            margin-left: auto;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.22);
            border-radius: 8px;
            padding: 10px 18px;
            text-align: center;
            position: relative; z-index: 1;
            flex-shrink: 0;
        }
        .bienvenue-badge span {
            display: block;
            font-size: 1.6rem;
            font-weight: 700;
            color: #fff;
        }
        .bienvenue-badge small {
            color: rgba(255,255,255,.6);
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ===== CARTE INFOS ===== */
        .info-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--vt-border);
            padding: 28px 32px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,.05);
        }
        .info-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
        }
        .info-card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--vt-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-card-title svg { color: var(--vt-red); }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 24px;
        }
        .info-item label {
            display: block;
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: var(--vt-muted);
            font-weight: 600;
            margin-bottom: 4px;
        }
        .info-item .info-value {
            font-size: .95rem;
            color: var(--vt-dark);
            font-weight: 500;
        }
        .info-item .info-value.empty {
            color: var(--vt-muted);
            font-style: italic;
            font-weight: 400;
        }

        /* ===== BOUTON MODIFIER ===== */
        .btn-modifier {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: none;
            border: 1.5px solid var(--vt-red);
            color: var(--vt-red);
            border-radius: 8px;
            padding: 7px 16px;
            font-size: .85rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .18s, color .18s;
        }
        .btn-modifier:hover {
            background: var(--vt-red);
            color: #fff;
        }

        /* ===== FORMULAIRE DE COMPLÉTION ===== */
        .edit-panel {
            display: none;
            background: #fff;
            border-radius: 14px;
            border: 1.5px solid var(--vt-red);
            padding: 28px 32px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(192,57,43,.08);
        }
        .edit-panel.open { display: block; }
        .edit-panel-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--vt-dark);
            margin: 0 0 22px;
        }

        .edit-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 24px;
        }
        .edit-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .edit-field label {
            font-size: .78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: var(--vt-muted);
        }
        .edit-field input,
        .edit-field select {
            padding: 10px 14px;
            border: 1.5px solid var(--vt-border);
            border-radius: 8px;
            font-size: .92rem;
            color: var(--vt-dark);
            background: var(--vt-light);
            transition: border-color .18s;
            outline: none;
        }
        .edit-field input:focus,
        .edit-field select:focus {
            border-color: var(--vt-red);
            background: #fff;
        }
        .edit-field input[readonly] {
            opacity: .55;
            cursor: not-allowed;
        }

        .edit-actions {
            display: flex;
            gap: 12px;
            margin-top: 22px;
        }
        .btn-valider {
            padding: 10px 28px;
            background: var(--vt-red);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .92rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .18s;
        }
        .btn-valider:hover { opacity: .88; }
        .btn-annuler {
            padding: 10px 20px;
            background: none;
            border: 1.5px solid var(--vt-border);
            border-radius: 8px;
            font-size: .92rem;
            color: var(--vt-muted);
            cursor: pointer;
            transition: border-color .18s, color .18s;
        }
        .btn-annuler:hover {
            border-color: var(--vt-dark);
            color: var(--vt-dark);
        }

        /* ===== MESSAGES FEEDBACK ===== */
        .feedback-msg {
            border-radius: 10px;
            padding: 14px 20px;
            font-size: .9rem;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .feedback-msg.success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: var(--vt-success);
        }
        .feedback-msg.error {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #be123c;
        }

        /* ===== CTA ACCUEIL ===== */
        .cta-accueil {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 20px 28px;
            background: var(--vt-dark);
            border-radius: 12px;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: .95rem;
            transition: opacity .18s;
            width: fit-content;
        }
        .cta-accueil:hover { opacity: .85; }
        .cta-accueil svg { flex-shrink: 0; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 600px) {
            .info-grid, .edit-grid { grid-template-columns: 1fr; }
            .bienvenue-banner { flex-direction: column; text-align: center; }
            .bienvenue-badge { margin-left: 0; }
            .succes-wrapper { padding: 24px 14px 48px; }
        }
    </style>
</head>
<body class="succes-body">

<!-- TOPBAR (identique à inscription.php) -->
<nav class="vt-topbar">
    <a href="../index.php">Retour au site</a>
    <div class="vt-topbar-logo">
        <img src="../Files/LogoVicking.png" alt="Viking Transport" onerror="this.style.display='none'" />
    </div>
</nav>

<div class="succes-wrapper">

    <?php if (!$insertOk): ?>
    <!-- ===== ERREUR D'INSCRIPTION ===== -->
    <div class="feedback-msg error">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        Une erreur est survenue lors de la création de votre compte.
        
    </div>
    <a href="inscription.php" class="cta-accueil">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M19 12H5M12 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Retourner à l'inscription
    </a>

    <?php else: ?>

    <?php
        $initiales = strtoupper(substr($_SESSION['cli_prenom'], 0, 1) . substr($_SESSION['cli_nom'], 0, 1));
    ?>

    <!-- ===== MESSAGE FEEDBACK MISE À JOUR ===== -->
    <?php if ($updateMsg): ?>
    <div class="feedback-msg success">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <?= htmlspecialchars($updateMsg) ?>
    </div>
    <?php endif; ?>

    <!-- ===== BANNIÈRE BIENVENUE ===== -->
    <div class="bienvenue-banner">
        <div class="bienvenue-avatar"><?= $initiales ?></div>
        <div class="bienvenue-text">
            <h1>Bienvenue parmi nous, <?= htmlspecialchars($_SESSION['cli_prenom']) ?> <?= htmlspecialchars($_SESSION['cli_nom']) ?>&nbsp;!</h1>
            <p>Votre compte Viking Transport a bien été créé. Vous pouvez dès maintenant réserver vos voyages en Normandie.</p>
        </div>
        <div class="bienvenue-badge">
            <span>0</span>
            <small>Points fidélité</small>
        </div>
    </div>

    <!-- ===== CARTE INFORMATIONS ===== -->
    <div class="info-card">
        <div class="info-card-header">
            <span class="info-card-title">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="7" r="4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Vos informations
            </span>
            <button class="btn-modifier" id="btn-open-edit" type="button">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Modifier / Compléter
            </button>
        </div>
        <div class="info-grid">
            <div class="info-item">
                <label>Prénom</label>
                <div class="info-value"><?= htmlspecialchars($_SESSION['cli_prenom']) ?></div>
            </div>
            <div class="info-item">
                <label>Nom</label>
                <div class="info-value"><?= htmlspecialchars($_SESSION['cli_nom']) ?></div>
            </div>
            <div class="info-item">
                <label>Adresse email</label>
                <div class="info-value"><?= htmlspecialchars($_SESSION['cli_email']) ?></div>
            </div>
            <div class="info-item">
                <label>Téléphone</label>
                <div class="info-value <?= empty($telephone) ? 'empty' : '' ?>">
                    <?= $telephone ? htmlspecialchars($telephone) : 'Non renseigné' ?>
                </div>
            </div>
            <div class="info-item">
                <label>Ville</label>
                <div class="info-value empty" id="display-ville">Non renseignée</div>
            </div>
            <div class="info-item">
                <label>Département</label>
                <div class="info-value empty" id="display-dep">Non renseigné</div>
            </div>
        </div>
    </div>

    <!-- ===== PANNEAU MODIFICATION ===== -->
    <?php
        $conn_recup = OuvrirConn();
        $ville = recup_ville($conn_recup);
        $dep = recup_dep($conn_recup);
        $conn_recup = null;
    ?>

    <div class="edit-panel" id="edit-panel">
        <p class="edit-panel-title">Modifier ou compléter vos informations</p>
        <form method="POST" action="traitement_maj_client.php">
            <input type="hidden" name="cli_num" value="<?= $cli_num ?>" />
            <div class="edit-grid">
                <div class="edit-field">
                    <label>Prénom</label>
                    <input type="text" name="prenom" value="<?= htmlspecialchars($_SESSION['cli_prenom']) ?>" />
                </div>
                <div class="edit-field">
                    <label>Nom</label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($_SESSION['cli_nom']) ?>" />
                </div>
                <div class="edit-field">
                    <label>Adresse email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_SESSION['cli_email']) ?>" />
                </div>
                <div class="edit-field">
                    <label>Téléphone</label>
                    <input type="tel" name="telephone" value="<?= htmlspecialchars($telephone) ?>" placeholder="+33 6 00 00 00 00" />
                </div>
                <div class="edit-field">
                    <label>Ville</label>
                    <input type="text" name="ville" <?php 
                        if ($ville != null){
                            echo 'value = "'. addslashes($ville) . '"';
                        }
                    ?> placeholder="Ex : Caen" />
                </div>
                <div class="edit-field">
                    <label>Département</label>
                    <select name="dep_num">
                        <option value=""> <?php
                            if ($dep == null){
                                echo '-- Sélectionner --';
                            }
                            else {
                                echo $dep;
                            }
                        ?></option>
                        <?php foreach ($departements as $dep): ?>
                            <option value="<?= htmlspecialchars($dep['DEP_NUM']) ?>">
                                <?= htmlspecialchars($dep['DEP_NOM']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="edit-actions">
                <button type="submit" class="btn-valider">Valider les modifications</button>
                <button type="button" class="btn-annuler" id="btn-cancel-edit">Annuler</button>
            </div>
        </form>
    </div>

    <!-- ===== CTA ACCUEIL ===== -->
    <a href="formulaire.php" class="cta-accueil">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke-linecap="round" stroke-linejoin="round"/>
            <polyline points="9 22 9 12 15 12 15 22" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Accéder à l'espace de réservation
    </a>

    <?php endif; ?>
</div>

<script>
    const btnOpen   = document.getElementById('btn-open-edit');
    const btnCancel = document.getElementById('btn-cancel-edit');
    const panel     = document.getElementById('edit-panel');

    if (btnOpen)   btnOpen.addEventListener('click',   () => panel.classList.add('open'));
    if (btnCancel) btnCancel.addEventListener('click', () => panel.classList.remove('open'));
</script>
</body>
</html> 

<?php
    $conn = null;
?>