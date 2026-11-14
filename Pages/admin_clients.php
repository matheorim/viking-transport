<?php
session_start();
include_once("../PHP/utils.php");

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Viking Transport - Administration - Clients</title>
    <link rel="stylesheet" href="../CSS/Header.css" />
    <link rel="stylesheet" href="../CSS/admin_clients.css" />
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
</head>
<body>

<?php include_once 'header.php'; ?>

<main>
  <div class="welcome-box">
    <h2>Liste des clients</h2>
    <?php

    $conn = OuvrirConn();

    $result = ExecSelect($conn, "select * from client_admin");

    $conn = null;

    ?>

    <div class="user-controls">

        <input type="text" id="searchInput" placeholder="Rechercher nom ou prénom..." class="search-bar">

        <select id="filterInactive" class="filter-select">
            <option value="all">Tous</option>
            <option value="0">Inactifs uniquement</option>
            <option value="1">Actifs uniquement</option>
        </select>

        <select id="sortSelect" class="filter-select">
            <option value="name_asc">Nom A → Z</option>
            <option value="name_desc">Nom Z → A</option>
            <option value="points_asc">Points Croissants</option>
            <option value="points_desc">Points Déroissants</option>
            <option value="date_asc">Dates Croissantes</option>
            <option value="date_desc">Dates Déroissantes</option>
        </select>

    </div>

    <div class="user-grid" id="userGrid">

        <?php foreach ($result as $id => $u): ?>
            <a class="user-card" href="<?php echo "admin_client.php?id=" . $u['CLI_NUM'];?>"
                data-name="<?= strtolower($u['CLI_NOM']) ?>"
                data-prenom="<?= strtolower($u['CLI_PRENOM']) ?>"
                data-inactive="<?= $u['CLIENT_ACTIF'] ?>"
                data-points="<?= $u['CLI_NB_POINTS_TOT'] ?>"
                data-date="<?= $u['DERNIERE_CONNEXION'] ?>">

                <div class="user-header">
                    <h3><?= htmlspecialchars($u['CLI_NOM']) ?> <?= htmlspecialchars($u['CLI_PRENOM']) ?></h3>
                </div>

                <div class="user-body">
                    <p><strong>Mail:</strong> <?= htmlspecialchars($u['CLI_COURRIEL']) ?></p>
                    <p><strong>Points:</strong> <?= $u['CLI_NB_POINTS_TOT'] ?></p>
                    <p><strong>Date:</strong> <?= $u['DERNIERE_CONNEXION'] ?></p>
                </div>

                <?php if ($u['CLIENT_ACTIF'] == 0): ?>
                    <span class="badge-inactive">Inactif</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

    </div>

  </div>
  <img src="../Files/bus.png" alt="bus" class="center">

</main>

<?php include_once 'footer.php'; ?>


</body> 

<script>
const searchInput = document.getElementById("searchInput");
const filterInactive = document.getElementById("filterInactive");
const sortSelect = document.getElementById("sortSelect");
const grid = document.getElementById("userGrid");

function getCards() {
    return Array.from(document.querySelectorAll(".user-card"));
}

function filterCards() {
    const search = searchInput.value.toLowerCase();
    const inactiveFilter = filterInactive.value;

    const cards = getCards();

    cards.forEach(card => {
        const name = card.dataset.name;
        const prenom = card.dataset.prenom;
        const inactive = card.dataset.inactive;

        let show = true;

        // recherche
        if (search && !(name.includes(search) || prenom.includes(search))) {
            show = false;
        }

        // filtre actif/inactif
        if (inactiveFilter !== "all" && inactive !== inactiveFilter) {
            show = false;
        }

        card.style.display = show ? "" : "none";
    });
}

function parseFrenchDate(dateStr) {
    const [day, month, year] = dateStr.split('/');
    return new Date(year, month - 1, day);
}

function sortCards() {
    const value = sortSelect.value;

    const cards = getCards().filter(card => getComputedStyle(card).display !== "none");

    const sorted = cards.sort((a, b) => {

        switch (value) {

            case "name_asc":
                return a.dataset.name.localeCompare(b.dataset.name);

            case "name_desc":
                return b.dataset.name.localeCompare(a.dataset.name);

            case "points_asc":
                return a.dataset.points - b.dataset.points;

            case "points_desc":
                return b.dataset.points - a.dataset.points;

            case "date_asc":
                return parseFrenchDate(a.dataset.date) - parseFrenchDate(b.dataset.date);

            case "date_desc":
                return parseFrenchDate(b.dataset.date) - parseFrenchDate(a.dataset.date);
        }
    });

    sorted.forEach(card => grid.appendChild(card));
}

function refreshCards() {
    filterCards();
    sortCards();
}

// events
searchInput.addEventListener("input", refreshCards);
filterInactive.addEventListener("change", refreshCards);
sortSelect.addEventListener("change", refreshCards);
</script>

</html>