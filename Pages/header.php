<?php

	function affiche_lien_profil($page){
		if ($page == 'index'){
			if (isset($_SESSION['is_admin'])){
				if ($_SESSION['is_admin'] == true){
					echo '<a href="Pages/admin.php">Administration</a>';
				}
				else {
					echo '<a href="Pages/profil.php">Profil</a>';
				}
			}
			else{
				echo '<a href="Pages/profil.php">Profil</a>';
			}
		}
		else{
			if (isset($_SESSION['is_admin'])){
				if ($_SESSION['is_admin'] == true){
					echo '<a href="admin.php">Administration</a>';
				}
				else {
					echo '<a href="profil.php">Profil</a>';
				}
			}
			else{
				echo '<a href="profil.php">Profil</a>';
			}
		}
	}

	function affiche_connexion($page)
	{
		if (isset($_SESSION['is_logged_in'])){
			if ($_SESSION['is_logged_in'] == true)
			{
				echo '<div class="profile">
  						<div class="profile-btn" onclick="toggleMenu()">' . $_SESSION['cli_prenom'][0] . '</div>
 					 	<div class="menu" id="menu">';
				echo '<p>' . $_SESSION['cli_prenom'] . '</p>';
				if ($page == 'index'){
							affiche_lien_profil($page);
    						echo '<a href="PHP/logout.php">Se deconnecter</a>';
				}
				else {
							affiche_lien_profil($page);
    						echo '<a href="../PHP/logout.php">Se deconnecter</a>';
				}
  				echo '</div>
					</div>
					
					<script>
						function toggleMenu() {
  							document.getElementById("menu").classList.toggle("show");
						}

						// close when clicking outside
						document.addEventListener("click", function(e) {
  							const profile = document.querySelector(".profile");
  							if (!profile.contains(e.target)) {
    							document.getElementById("menu").classList.remove("show");
  							}
						});
					</script>';
			}
		}
		else
		{
			if ($page == 'index'){
				echo '<a href="Pages/connexion.php" class="vt-btn-login">Se connecter / S\'inscrire</a>';
			}
			else{
				echo '<a href="connexion.php" class="vt-btn-login">Se connecter / S\'inscrire</a>';
			}
		}
	}

?>

<header class="vt-header">
		<?php
			if (isset($page))
			{
				echo '<a class="vt-logo" href="#">';
			}
			else {
				echo '<a class="vt-logo" href="../index.php">';
			}
		?>
        <img src="../Files/LogoVicking.png" alt="Viking Transport logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
        <div class="vt-logo-text" style="display:none;">
            <span>VIKING</span>
            <span>Transport</span>
        </div>
    </a>

    <nav class="vt-nav">
        <?php
		if (isset($page))
		{	
			echo '<a href="Pages/ligne.php">Les lignes</a>
			<a href="Pages/tarif_accueil.php">Tarifs</a>
			<a href="Pages/trajet.php">Trajets</a>';
			if (isset($_SESSION['is_admin'])){
				if (!$_SESSION['is_admin']){
					echo '<a href="Pages/formulaire.php" class="vt-btn-reserver">Réserver</a>
					<div class="vt-divider"></div>';
				}
				else{
					echo '<a href="Pages/admin.php">Administration</a>';
				}
			}
			else{
				echo '<a href="Pages/formulaire.php" class="vt-btn-reserver">Réserver</a>
				<div class="vt-divider"></div>';
			}
			affiche_connexion('index');
		}
		else {
			echo '<a href="ligne.php">Les lignes</a>
			<a href="tarif_accueil.php">Tarifs</a>			
			<a href="trajet.php">Trajets</a>';
			if (isset($_SESSION['is_admin'])){
				if (!$_SESSION['is_admin']){
					echo '<a href="formulaire.php" class="vt-btn-reserver">Réserver</a>
					<div class="vt-divider"></div>';
				}
				else{
					echo '<a href="admin.php">Administration</a>';
				}
			}
			else{
				echo '<a href="formulaire.php" class="vt-btn-reserver">Réserver</a>
				<div class="vt-divider"></div>';
			}
			affiche_connexion('autre');
		}
		?>
    </nav>
</header>