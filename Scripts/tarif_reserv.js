document.addEventListener('DOMContentLoaded', function() {
    // 1. On va chercher tous les éléments HTML dont on a besoin
    const radiosPoints = document.querySelectorAll('input[name="utiliser_points"]');
    const ligneRemisePoints = document.getElementById('ligne-remise-points');
    const texteRemisePoints = document.getElementById('texte-remise-points');
    const affichageTotalFinal = document.getElementById('total-final');
    const sousTotalSpan = document.getElementById('sous-total');

    // 2. On vérifie que les éléments existent bien sur la page avant de faire des calculs
    if (radiosPoints.length > 0 && sousTotalSpan) {
        
        // On récupère le prix de base (le sous-total) caché dans le HTML
        const sousTotal = parseFloat(sousTotalSpan.getAttribute('data-val'));

        // 3. On ajoute un "écouteur d'événement" sur chaque bouton radio
        radiosPoints.forEach(function(radio) {
            radio.addEventListener('change', function() {
                
                // Quand on clique, on lit la remise associée au bouton (0, 1, 7 ou 15)
                const remise = parseFloat(this.getAttribute('data-remise'));
                
                // On fait la soustraction !
                let prixFinal = sousTotal - remise;
                
                // Sécurité : un billet ne peut pas avoir un prix négatif
                if (prixFinal < 0) prixFinal = 0;

                // 4. On met à jour l'affichage visuel
                if (remise > 0) {
                    // On affiche la petite ligne "Remise points : - X,00 €"
                    ligneRemisePoints.style.display = 'flex';
                    texteRemisePoints.textContent = '- ' + remise.toFixed(2).replace('.', ',') + ' €';
                } else {
                    // Si on coche "Ne pas utiliser", on cache la ligne de remise
                    ligneRemisePoints.style.display = 'none';
                }

                // On injecte le nouveau prix calculé dans le gros "TOTAL TTC" en bas
                affichageTotalFinal.textContent = prixFinal.toFixed(2).replace('.', ',') + ' €';
            });
        });
    }
});