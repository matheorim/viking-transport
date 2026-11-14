<?php
session_start();

$nom = null;
$prenom = null;
if (isset($_SESSION['is_logged_in'])) {
    $nom = $_SESSION['cli_nom'] ?? null;
    $prenom = $_SESSION['cli_prenom'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Réservation - Viking Transport</title>
    <link rel="stylesheet" href="../CSS/formulaire.css" />
    <link rel="stylesheet" href="../CSS/Header.css" />
    <link rel="icon" type="image/png" href="../Files/LogoVicking.png">
    
    <style>
        #miniature-carte {
            width: 100%;
            max-width: 250px; 
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid #ddd;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        #miniature-carte:hover {
            transform: scale(1.03);
            border-color: #D81F2C; 
        }

        .modal-overlay {
            display: none; 
            position: fixed;
            z-index: 9999; 
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.85); 
            backdrop-filter: blur(4px); 
        }

        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%); 
            border: 4px solid #fff;
            border-radius: 8px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.8);
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 40px;
            color: #fff;
            font-size: 50px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #D81F2C;
        }
    </style>
</head>

<body>
    <?php include_once "header.php"; ?>
    <div class="wrapper">
        <div class="page-title">Réservation</div>
        <div class="page-subtitle">Remplissez le formulaire pour réserver votre trajet</div>

        <div class="form-card">
            <form method="POST" action="tarif_reserv.php" id="form-reservation">
                <input type="hidden" name="sans_compte" id="sans_compte" value="0">
                <input type="hidden" id="hid_depart_insee" name="etapes[0][ville_depart_insee]">
                <input type="hidden" id="hid_arrivee_insee" name="etapes[0][ville_arrivee_insee]">

                <div class="two-col">
                    <div class="col-left">
                        <div class="section-label">Vos informations</div>

                        <div class="field">
                            <label for="nom">Nom</label>
                            <input type="text" id="nom" name="nom"
                                pattern="[A-Za-zÀ-ÿ\-' ]+"
                                title="Lettres, tirets et apostrophes uniquement"
                                <?php if ($nom != null) echo 'value="' . htmlspecialchars($nom) . '"'; ?>
                                placeholder="Ex : Dupont" required>
                        </div>

                        <div class="field">
                            <label for="prenom">Prénom</label>
                            <input type="text" id="prenom" name="prenom"
                                pattern="[A-Za-zÀ-ÿ\-']+"
                                title="Lettres, tirets et apostrophes uniquement"
                                <?php if ($prenom != null) echo 'value="' . htmlspecialchars($prenom) . '"'; ?>
                                placeholder="Ex : Marie-Anne" required>
                        </div>

                        <div class="field">
                            <label for="date_reservation">Date de réservation</label>
                            <input type="date" id="date_reservation" name="date_reservation" required
                                max="<?= date('Y-m-d', strtotime('+1 year')) ?>">
                        </div>

                        <div class="divider"></div>

                        <div class="section-label">Récapitulatif</div>
                        <div class="recap-box">
                            <div class="recap-line">
                                <span>Distance estimée</span>
                                <span id="recap-distance">—</span>
                            </div>

                            <div class="recap-line">
                                <span>Prix estimée</span>
                                <span id="recap-prix">—</span>
                            </div>

                            <div class="recap-line">
                                <span>Départ</span>
                                <span id="recap-depart">—</span>
                            </div>
                            <div class="recap-line">
                                <span>Arrivée</span>
                                <span id="recap-arrivee">—</span>
                            </div>
                            <div class="recap-line recap-total">
                                <span>Étapes</span>
                                <span id="recap-etapes">1</span>
                            </div>
                        </div>

                        <div id="msg-erreur" style="display:none; background:#fdecea; color:#D81F2C; border:1px solid #D81F2C; border-radius:6px; padding:12px 16px; margin-top:16px; font-size:14px; font-weight:600;"></div>

                        <button type="button" id="btn-reserver" class="btn-submit" disabled
                            onclick="document.getElementById('form-reservation').submit();">
                            Réserver
                        </button>
                        <div style="margin-top: 35px; text-align: center;">
                            <p style="font-size: 12px; font-weight: 700; color: #8D9195; margin-bottom: 8px; text-transform: uppercase;">
                                Consulter la carte du réseau
                            </p>
                            <img src="../Files/image_ligne.png" alt="Carte du réseau" id="miniature-carte" title="Cliquez pour agrandir">
                        </div>
                    </div>

                    <div class="col-right">
                        <div class="etape-bloc" id="etape-0">
                            <div class="etape-header">
                                <span class="section-label" style="margin:0">Étape 1</span>
                            </div>

                            <div class="etape-grid">
                                <div class="field">
                                    <label for="arret_depart">Ville de départ</label>
                                    <select id="arret_depart" name="etapes[0][ville_depart_nom]" required>
                                        <option value="">Choisir une ville de départ</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="ligne">Ligne</label>
                                    <select id="ligne" name="etapes[0][ligne]" disabled required>
                                        <option value="">Choisir d'abord les arrêts</option>
                                    </select>
                                </div>

                                <div class="field" id="bloc-heure-arrivee">
                                    <label>Heure d'arrivée estimée</label>
                                    <div id="heure-arrivee-display" class="heure-display">--:--</div>
                                </div>

                                <div class="field">
                                    <label for="arret_arrivee">Ville d'arrivée</label>
                                    <select id="arret_arrivee" name="etapes[0][ville_arrivee_nom]" disabled required>
                                        <option value="">Choisir d'abord une ville de départ</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="horaire">Horaire de départ</label>
                                    <select id="horaire" name="etapes[0][horaire]" disabled required>
                                        <option value="">Choisir d'abord une ligne</option>
                                    </select>
                                </div>

                                <div class="field" style="display:flex; align-items:flex-end;">
                                    <button type="button" class="btn-ajouter" id="btn-ajouter-etape" disabled>
                                        + Ajouter une étape
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="etapes-supplementaires"></div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-carte" class="modal-overlay">
        <span class="modal-close" id="close-modal">&times;</span>
        <img class="modal-content" id="img-carte-agrandie">
    </div>

    <?php include_once "footer.php"; ?>
    <script src="../Scripts/formulaire.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById("modal-carte");
            const imgMiniature = document.getElementById("miniature-carte");
            const imgAgrandie = document.getElementById("img-carte-agrandie");
            const spanClose = document.getElementById("close-modal");

            if (imgMiniature && modal) {
                imgMiniature.addEventListener('click', function() {
                    modal.style.display = "block";
                    imgAgrandie.src = this.src;
                });

                spanClose.addEventListener('click', function() {
                    modal.style.display = "none";
                });

                modal.addEventListener('click', function(event) {
                    if (event.target === modal) {
                        modal.style.display = "none";
                    }
                });
            }
        });
    </script>
</body>
</html>