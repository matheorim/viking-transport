function resetSelect(sel, placeholder, disabled = true) {
    sel.innerHTML = `<option value="">${placeholder}</option>`;
    sel.disabled = disabled;
}

const selDepart = document.getElementById('arret_depart');
const selArrivee = document.getElementById('arret_arrivee');
const selLigne = document.getElementById('ligne');
const selHoraire = document.getElementById('horaire');
const hidDepartInsee = document.getElementById('hid_depart_insee');
const hidArriveeInsee = document.getElementById('hid_arrivee_insee');
const btnAjouter = document.getElementById('btn-ajouter-etape');
const etapesSupp = document.getElementById('etapes-supplementaires');
const btnSubmit = document.getElementById('btn-reserver');

let nbEtapes = 1;

function getSelectedNom(selectEl) {
    return selectEl.options[selectEl.selectedIndex]?.dataset.nom || '';
}

function formatDistance(val) {
    if (val === null || val === undefined || val === '') return '—';
    return `${val} km`;
}

function updateRecap() {
    const depNom = getSelectedNom(selDepart);
    let arrNom = getSelectedNom(selArrivee);

    const depInsee = selDepart.value;
    const arrInsee = selArrivee.value;
    const lig = selLigne.value;

    let distancesToFetch = [];

    if (depInsee && arrInsee && lig) {
        distancesToFetch.push({ dep: depInsee, arr: arrInsee, lig: lig });
    }

    const blocs = document.querySelectorAll('.etape-bloc');
    for (let i = 1; i < blocs.length; i++) {
        const hiddenDepInsee = document.querySelector(`input[name="etapes[${i}][ville_depart_insee]"]`);
        const selArrDynamique = document.getElementById(`arret_arrivee_${i}`);
        const selLigDynamique = document.getElementById(`ligne_${i}`);

        if (hiddenDepInsee && selArrDynamique && selLigDynamique) {
            const stepDepInsee = hiddenDepInsee.value;
            const stepArrInsee = selArrDynamique.options[selArrDynamique.selectedIndex]?.dataset.insee || '';
            const stepArrNom = selArrDynamique.options[selArrDynamique.selectedIndex]?.dataset.nom || '';
            const stepLig = selLigDynamique.value;

            if (stepArrInsee && stepLig) {
                arrNom = stepArrNom;
                distancesToFetch.push({ dep: stepDepInsee, arr: stepArrInsee, lig: stepLig });
            }
        }
    }

    document.getElementById('recap-depart').textContent = selDepart.value ? depNom : '—';
    document.getElementById('recap-arrivee').textContent = arrNom ? arrNom : '—';
    document.getElementById('recap-etapes').textContent = blocs.length;

    if (distancesToFetch.length === 0) {
        document.getElementById('recap-distance').textContent = '—';
    } else {
        document.getElementById('recap-distance').textContent = 'Calcul...';

        Promise.all(distancesToFetch.map(segment =>
            fetch(`../PHP/get_distance.php?depart=${encodeURIComponent(segment.dep)}&arrivee=${encodeURIComponent(segment.arr)}&ligne=${encodeURIComponent(segment.lig)}`)
                .then(r => r.json())
                .then(data => data.distance ? parseFloat(data.distance) : 0)
                .catch(() => 0)
        )).then(distances => {
            const total = distances.reduce((acc, curr) => acc + curr, 0);
            document.getElementById('recap-distance').textContent = total > 0 ? formatDistance(total.toFixed(1)) : '—';
        });
    }
}

function updateSubmitState() {
    const nom = document.getElementById('nom').value.trim();
    const prenom = document.getElementById('prenom').value.trim();
    const dateVal = document.getElementById('date_reservation').value; // Format "YYYY-MM-DD"

    // 1. Récupération de la date du jour au format local "YYYY-MM-DD"
    const aujourdHui = new Date();
    const annee = aujourdHui.getFullYear();
    const mois = String(aujourdHui.getMonth() + 1).padStart(2, '0');
    const jour = String(aujourdHui.getDate()).padStart(2, '0');
    const dateDuJourStr = `${annee}-${mois}-${jour}`;

    // 2. Calcul de la date max (+1 an) au format "YYYY-MM-DD"
    const maxDate = new Date();
    maxDate.setFullYear(maxDate.getFullYear() + 1);
    const maxAnnee = maxDate.getFullYear();
    const maxMois = String(maxDate.getMonth() + 1).padStart(2, '0');
    const maxJour = String(maxDate.getDate()).padStart(2, '0');
    const dateMaxStr = `${maxAnnee}-${maxMois}-${maxJour}`;

    // 3. Comparaison textuelle stricte et fiable (évite les bugs de fuseaux horaires)
    const dateValide = !!dateVal && dateVal >= dateDuJourStr && dateVal <= dateMaxStr;

    let toutesEtapesValides = true;

    if (!selDepart.value || !selArrivee.value || !selLigne.value || !selHoraire.value) {
        toutesEtapesValides = false;
    }

    const blocs = document.querySelectorAll('.etape-bloc');
    for (let i = 1; i < blocs.length; i++) {
        const selArr = document.getElementById(`arret_arrivee_${i}`);
        const selLig = document.getElementById(`ligne_${i}`);
        const selHor = document.getElementById(`horaire_${i}`);

        if (!selArr || !selArr.value || !selLig || !selLig.value || !selHor || !selHor.value) {
            toutesEtapesValides = false;
            break;
        }
    }

    btnSubmit.disabled = !(nom && prenom && dateValide && toutesEtapesValides);
}

function triggerNextStepCascade(stepIndex) {
    const nextIndex = stepIndex + 1;
    const nextLigSelect = document.getElementById(`ligne_${nextIndex}`);
    if (nextLigSelect && nextLigSelect.value) {
        nextLigSelect.dispatchEvent(new Event('change'));
    }
}

function loadHeureArrivee({ depart, arrivee, ligne, horaire, displayId, stepIndex }) {
    const displayArrivee = document.getElementById(displayId);

    fetch(`../PHP/get_heure_arrivee.php?depart=${encodeURIComponent(depart)}&arrivee=${encodeURIComponent(arrivee)}&ligne=${encodeURIComponent(ligne)}&horaire=${encodeURIComponent(horaire)}`)
        .then(r => r.json())
        .then(data => {
            displayArrivee.textContent = data.HEURE_ARRIVEE || '--:--';
            if (stepIndex !== undefined) triggerNextStepCascade(stepIndex);
        })
        .catch(() => {
            displayArrivee.textContent = '--:--';
            if (stepIndex !== undefined) triggerNextStepCascade(stepIndex);
        });
}

// Désactive tous les boutons "ajouter" sauf celui de la dernière étape complétée
function desactiverBoutonsAjouter() {
    const blocs = document.querySelectorAll('.etape-bloc');
    blocs.forEach((bloc, i) => {
        const btn = i === 0
            ? document.getElementById('btn-ajouter-etape')
            : document.getElementById(`btn-ajouter-etape-${i}`);
        if (btn) btn.disabled = true;
    });
}

// Clic sur "ajouter une étape" depuis n'importe quelle étape
function ajouterDepuisEtape(index) {
    const bloc = document.getElementById(`etape-${index}`);
    const villeArriveeInsee = bloc.dataset.villeArriveeInsee;
    const villeArriveeNom = bloc.dataset.villeArriveeNom;

    creerEtape(nbEtapes++, villeArriveeInsee, villeArriveeNom);
    desactiverBoutonsAjouter();
    updateRecap();
    updateSubmitState();
}

fetch('../PHP/get_arrets.php')
    .then(r => r.json())
    .then(data => {
        data.forEach(row => {
            const opt = document.createElement('option');
            opt.value = row.COM_CODE_INSEE;
            opt.dataset.nom = row.COM_NOM;
            opt.textContent = row.COM_NOM;
            selDepart.appendChild(opt);
        });
    });

selDepart.addEventListener('change', function () {
    const departInsee = this.value;
    hidDepartInsee.value = departInsee;
    hidArriveeInsee.value = '';
    resetSelect(selArrivee, 'Chargement…');
    resetSelect(selLigne, 'Choisir d\'abord les arrêts');
    resetSelect(selHoraire, 'Choisir d\'abord les arrêts');
    btnAjouter.disabled = true;

    document.getElementById('heure-arrivee-display').textContent = '--:--';

    updateRecap();
    updateSubmitState();

    if (!departInsee) {
        resetSelect(selArrivee, 'Choisir d\'abord un arrêt de départ');
        return;
    }

    fetch(`../PHP/get_destinations.php?depart=${encodeURIComponent(departInsee)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                resetSelect(selArrivee, 'Aucune destination disponible');
                return;
            }

            selArrivee.innerHTML = '<option value="">Choisir un arrêt d\'arrivée</option>';
            data.forEach(row => {
                if (row.COM_CODE_INSEE === departInsee) return;
                const opt = document.createElement('option');
                opt.value = row.COM_CODE_INSEE;
                opt.dataset.nom = row.COM_NOM;
                opt.textContent = row.COM_NOM;
                selArrivee.appendChild(opt);
            });
            selArrivee.disabled = false;
        });
});

selArrivee.addEventListener('change', function () {
    hidArriveeInsee.value = this.value;

    resetSelect(selLigne, 'Chargement…');
    resetSelect(selHoraire, 'Choisir d\'abord une ligne');
    btnAjouter.disabled = true;
    document.getElementById('heure-arrivee-display').textContent = '--:--';

    const departNom = getSelectedNom(selDepart);
    const arriveeNom = getSelectedNom(selArrivee);

    updateRecap();
    updateSubmitState();

    if (!this.value) {
        resetSelect(selLigne, 'Choisir d\'abord un arrêt d\'arrivée');
        return;
    }

    fetch(`../PHP/get_lignes.php?depart=${encodeURIComponent(departNom)}&arrivee=${encodeURIComponent(arriveeNom)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                resetSelect(selLigne, 'Aucune ligne disponible');
                return;
            }

            selLigne.innerHTML = '<option value="">Choisir une ligne</option>';
            data.forEach(row => {
                const opt = document.createElement('option');
                opt.value = row.NUMERO_LIGNE;
                opt.textContent = `Ligne ${row.NUMERO_LIGNE}`;
                selLigne.appendChild(opt);
            });
            selLigne.disabled = false;
        });
});

selLigne.addEventListener('change', function () {
    const departNom = getSelectedNom(selDepart);
    const arriveeNom = getSelectedNom(selArrivee);
    const lig_num = this.value;

    resetSelect(selHoraire, 'Chargement…');
    btnAjouter.disabled = true;
    document.getElementById('heure-arrivee-display').textContent = '--:--';

    updateSubmitState();
    updateRecap();
    triggerNextStepCascade(0);

    if (!lig_num) {
        resetSelect(selHoraire, 'Choisir d\'abord une ligne');
        return;
    }

    fetch(`../PHP/get_horaires.php?depart=${encodeURIComponent(departNom)}&arrivee=${encodeURIComponent(arriveeNom)}&ligne=${encodeURIComponent(lig_num)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                resetSelect(selHoraire, 'Aucun horaire disponible');
                return;
            }

            selHoraire.innerHTML = '<option value="">Choisir un horaire</option>';
            data.forEach(row => {
                const opt = document.createElement('option');
                opt.value = row.HEURE_DEPART;
                opt.textContent = row.HEURE_DEPART;
                selHoraire.appendChild(opt);
            });
            selHoraire.disabled = false;
        });
});

selHoraire.addEventListener('change', function () {
    btnAjouter.disabled = !this.value;

    const departNom = getSelectedNom(selDepart);
    const arriveeNom = getSelectedNom(selArrivee);
    const arriveeInsee = selArrivee.value;
    const lig_num = selLigne.value;
    const horaire = this.value;

    if (!horaire) {
        document.getElementById('heure-arrivee-display').textContent = '--:--';
        updateSubmitState();
        triggerNextStepCascade(0);
        return;
    }

    document.getElementById('etape-0').dataset.villeArriveeInsee = arriveeInsee;
    document.getElementById('etape-0').dataset.villeArriveeNom = arriveeNom;

    loadHeureArrivee({
        depart: departNom,
        arrivee: arriveeNom,
        ligne: lig_num,
        horaire,
        displayId: 'heure-arrivee-display',
        stepIndex: 0
    });

    updateSubmitState();
});

// Le btnAjouter (étape 0) utilise maintenant ajouterDepuisEtape
btnAjouter.addEventListener('click', function () {
    ajouterDepuisEtape(0);
});

document.getElementById('nom').addEventListener('input', updateSubmitState);
document.getElementById('prenom').addEventListener('input', updateSubmitState);
document.getElementById('date_reservation').addEventListener('change', updateSubmitState);

function creerEtape(index, villeArriveeInsee, villeArriveeNom) {
    const bloc = document.createElement('div');
    bloc.className = 'etape-bloc';
    bloc.id = `etape-${index}`;
    bloc.innerHTML = `
        <div class="etape-header">
            <span class="section-label" style="margin:0">Étape ${index + 1}</span>
            <button type="button" class="btn-supprimer-etape" onclick="supprimerEtape(${index})">✕ Supprimer</button>
        </div>
        <div class="etape-grid">
            <div class="field">
                <label>Ville de départ</label>
                <input type="text" value="${villeArriveeNom}" disabled name="etapes[${index}][ville_depart_nom]">
                <input type="hidden" value="${villeArriveeInsee}" name="etapes[${index}][ville_depart_insee]">
            </div>
            <div class="field">
                <label for="ligne_${index}">Ligne</label>
                <select id="ligne_${index}" name="etapes[${index}][ligne]" class="sel-ligne" disabled required>
                    <option value="">Choisir d'abord l'arrivée</option>
                </select>
            </div>
            <div class="field" id="bloc-heure-arrivee-${index}">
                <label>Heure d'arrivée estimée</label>
                <div id="heure-arrivee-display-${index}" class="heure-display">--:--</div>
            </div>
            <div class="field">
                <label for="arret_arrivee_${index}">Ville d'arrivée</label>
                <select id="arret_arrivee_${index}" name="etapes[${index}][ville_arrivee_nom]" class="sel-arrivee" required>
                    <option value="">Chargement…</option>
                </select>
                <input type="hidden" id="hid_arrivee_insee_${index}" name="etapes[${index}][ville_arrivee_insee]">
            </div>
            <div class="field">
                <label for="horaire_${index}">Horaire de départ</label>
                <select id="horaire_${index}" name="etapes[${index}][horaire]" class="sel-horaire" disabled required>
                    <option value="">Choisir d'abord une ligne</option>
                </select>
            </div>
            <div class="field" style="display:flex; align-items:flex-end;">
                <button type="button" class="btn-ajouter" id="btn-ajouter-etape-${index}" disabled onclick="ajouterDepuisEtape(${index})">
                    + Ajouter une étape
                </button>
            </div>
        </div>
    `;

    etapesSupp.appendChild(bloc);

    const selArr = document.getElementById(`arret_arrivee_${index}`);
    fetch(`../PHP/get_destinations.php?depart=${encodeURIComponent(villeArriveeInsee)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                resetSelect(selArr, 'Aucune destination disponible');
                return;
            }

            selArr.innerHTML = '<option value="">Choisir un arrêt d\'arrivée</option>';
            data.forEach(row => {
                if (row.COM_NOM === villeArriveeNom) return;
                const opt = document.createElement('option');
                opt.value = row.COM_NOM;
                opt.dataset.nom = row.COM_NOM;
                opt.dataset.insee = row.COM_CODE_INSEE;
                opt.textContent = row.COM_NOM;
                selArr.appendChild(opt);
            });
            selArr.disabled = false;
        });

    selArr.addEventListener('change', function () {
        const selLig = document.getElementById(`ligne_${index}`);
        const selHor = document.getElementById(`horaire_${index}`);
        const hidIns = document.getElementById(`hid_arrivee_insee_${index}`);

        hidIns.value = this.options[this.selectedIndex]?.dataset.insee || '';
        const nom_arr = this.value;

        resetSelect(selLig, 'Chargement…');
        resetSelect(selHor, 'Choisir d\'abord une ligne');
        desactiverBoutonsAjouter();
        document.getElementById(`heure-arrivee-display-${index}`).textContent = '--:--';

        updateRecap();
        updateSubmitState();

        if (!this.value) return;

        fetch(`../PHP/get_lignes.php?depart=${encodeURIComponent(villeArriveeNom)}&arrivee=${encodeURIComponent(nom_arr)}`)
            .then(r => r.json())
            .then(data => {
                if (!data.length) {
                    resetSelect(selLig, 'Aucune ligne disponible');
                    return;
                }

                selLig.innerHTML = '<option value="">Choisir une ligne</option>';
                data.forEach(row => {
                    const opt = document.createElement('option');
                    opt.value = row.NUMERO_LIGNE;
                    opt.textContent = `Ligne ${row.NUMERO_LIGNE}`;
                    selLig.appendChild(opt);
                });
                selLig.disabled = false;
            });
    });

    document.getElementById(`ligne_${index}`).addEventListener('change', function () {
        const selHor = document.getElementById(`horaire_${index}`);
        const arriveeSelect = document.getElementById(`arret_arrivee_${index}`);
        resetSelect(selHor, 'Chargement…');
        desactiverBoutonsAjouter();
        document.getElementById(`heure-arrivee-display-${index}`).textContent = '--:--';

        updateRecap();
        updateSubmitState();
        triggerNextStepCascade(index);

        if (!this.value) return;

        const arriveeNom = arriveeSelect.value;

        let heureMinDepart = '';
        if (index === 1) {
            heureMinDepart = document.getElementById('heure-arrivee-display').textContent.trim();
        } else {
            heureMinDepart = document.getElementById(`heure-arrivee-display-${index - 1}`).textContent.trim();
        }

        if (heureMinDepart === '--:--') heureMinDepart = '';

        fetch(`../PHP/get_horaires.php?depart=${encodeURIComponent(villeArriveeNom)}&arrivee=${encodeURIComponent(arriveeNom)}&ligne=${encodeURIComponent(this.value)}&heure_min=${encodeURIComponent(heureMinDepart)}`)
            .then(r => r.json())
            .then(data => {
                if (heureMinDepart) {
                    data = data.filter(row => row.HEURE_DEPART >= heureMinDepart);
                }

                if (!data.length) {
                    resetSelect(selHor, `Aucun horaire après ${heureMinDepart}`);
                    return;
                }

                selHor.innerHTML = '<option value="">Choisir un horaire</option>';
                data.forEach(row => {
                    const opt = document.createElement('option');
                    opt.value = row.HEURE_DEPART;
                    opt.textContent = row.HEURE_DEPART;
                    selHor.appendChild(opt);
                });
                selHor.disabled = false;
            });
    });

    document.getElementById(`horaire_${index}`).addEventListener('change', function () {
        if (!this.value) {
            desactiverBoutonsAjouter();
            document.getElementById(`heure-arrivee-display-${index}`).textContent = '--:--';
            updateSubmitState();
            triggerNextStepCascade(index);
            return;
        }

        const arriveeSelect = document.getElementById(`arret_arrivee_${index}`);
        const bloc = document.getElementById(`etape-${index}`);
        const departNom = villeArriveeNom;
        const arriveeNom = arriveeSelect.value;
        const lig = document.getElementById(`ligne_${index}`).value;

        bloc.dataset.villeArriveeInsee = arriveeSelect.options[arriveeSelect.selectedIndex]?.dataset.insee || '';
        bloc.dataset.villeArriveeNom = arriveeNom;

        // Activer le bouton ajouter de cette étape
        const btnAjouterEtape = document.getElementById(`btn-ajouter-etape-${index}`);
        if (btnAjouterEtape) btnAjouterEtape.disabled = false;

        loadHeureArrivee({
            depart: departNom,
            arrivee: arriveeNom,
            ligne: lig,
            horaire: this.value,
            displayId: `heure-arrivee-display-${index}`,
            stepIndex: index
        });

        updateSubmitState();
    });
}

function supprimerEtape(index) {
    const bloc = document.getElementById(`etape-${index}`);
    if (bloc) bloc.remove();
    nbEtapes = Math.max(1, nbEtapes - 1);

    // Réactiver le bouton ajouter de la nouvelle dernière étape
    const blocs = document.querySelectorAll('.etape-bloc');
    const derniere = blocs[blocs.length - 1];
    const derniereIndex = derniere.id === 'etape-0' ? 0 : parseInt(derniere.id.replace('etape-', ''));
    const btnDerniere = derniereIndex === 0
        ? document.getElementById('btn-ajouter-etape')
        : document.getElementById(`btn-ajouter-etape-${derniereIndex}`);
    if (btnDerniere && derniere.dataset.villeArriveeInsee) btnDerniere.disabled = false;

    updateRecap();
    updateSubmitState();
}