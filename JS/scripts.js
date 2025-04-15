// Log au début pour confirmer le chargement du fichier
console.log('scripts.js chargé');

// Fonction pour le défilement fluide
function smoothScroll(target) {
    const element = document.querySelector(target);
    if (element) {
        window.scrollTo({
            top: element.offsetTop,
            behavior: 'smooth'
        });
    }
}

// Fonctions exécutées au chargement du document
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM chargé depuis scripts.js');

    // Initialisation du formulaire de réservation
    const dateInput = document.getElementById('date');
    const timeSelect = document.getElementById('time');
    const loadingIndicator = document.getElementById('loading-slots');
    const noSlotsMessage = document.getElementById('no-slots-message');

    console.log('Éléments de réservation (scripts.js):', {
        dateInput: dateInput ? 'trouvé' : 'manquant',
        timeSelect: timeSelect ? 'trouvé' : 'manquant',
        loadingIndicator: loadingIndicator ? 'trouvé' : 'manquant',
        noSlotsMessage: noSlotsMessage ? 'trouvé' : 'manquant'
    });

    // Masquer les messages au chargement
    if (loadingIndicator) loadingIndicator.style.display = 'none';
    if (noSlotsMessage) noSlotsMessage.style.display = 'none';

    // Activer le sélecteur d'heure quand une date est choisie
    if (dateInput && timeSelect) {
        dateInput.addEventListener('change', function () {
            console.log('Date changée (scripts.js):', this.value);
            if (this.value) {
                timeSelect.disabled = false;
                if (loadingIndicator) loadingIndicator.style.display = 'block';
            } else {
                timeSelect.disabled = true;
                timeSelect.innerHTML = '<option value="">Sélectionnez d\'abord une date</option>';
            }
        });
    }

    // Bouton pour remonter en haut de la page
    const scrollTopButton = document.getElementById('scrollTop');
    if (scrollTopButton) {
        // Afficher le bouton quand on scrolle vers le bas
        window.addEventListener('scroll', function () {
            if (window.pageYOffset > 300) {
                scrollTopButton.classList.add('show');
            } else {
                scrollTopButton.classList.remove('show');
            }
        });

        // Action du bouton
        scrollTopButton.addEventListener('click', function (e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // Ajouter le défilement fluide à tous les liens de navigation et au bouton de réservation
    const links = document.querySelectorAll('nav a, .service-cta');

    links.forEach(link => {
        link.addEventListener('click', function (e) {
            // Vérifier si le lien pointe vers une section de la page
            const href = this.getAttribute('href');
            if (href.startsWith('#')) {
                e.preventDefault();
                smoothScroll(href);
            }
        });
    });

    // Script pour afficher le message de confirmation de réservation
    // Vérifier si l'URL contient le paramètre de succès de réservation
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('reservation') === 'success') {
        // Afficher le message de confirmation
        showConfirmation('success');
    } else if (urlParams.get('reservation') === 'error') {
        // Afficher un message d'erreur
        showConfirmation('error', 'Une erreur est survenue lors de l\'enregistrement de votre réservation. Veuillez réessayer ou nous contacter directement.');
    }

    // Gestion des créneaux de réservation
    if (dateInput) {
        // Définir la date minimale à aujourd'hui (en utilisant le fuseau horaire de Paris)
        const today = new Date().toLocaleString('fr-FR', {
            timeZone: 'Europe/Paris',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        }).split('/').reverse().join('-');

        dateInput.min = today;

        // Si aucune date n'est sélectionnée, mettre la date d'aujourd'hui
        if (!dateInput.value) {
            dateInput.value = today;
        }

        updateAvailableTimeSlots();

        // Mettre à jour les créneaux quand la date change
        dateInput.addEventListener('change', updateAvailableTimeSlots);

        // Mettre à jour les créneaux toutes les minutes pour garder l'affichage à jour
        setInterval(updateAvailableTimeSlots, 60000);
    }
});

// Fonction pour mettre à jour les créneaux horaires disponibles
function updateAvailableTimeSlots() {
    const timeSelect = document.getElementById('time');
    const selectedDate = document.getElementById('date').value;

    // Obtenir l'heure de Paris
    const parisTime = new Date().toLocaleString('fr-FR', {
        timeZone: 'Europe/Paris',
        hour12: false,
        hour: '2-digit',
        minute: '2-digit'
    });
    const [currentHour, currentMinutes] = parisTime.split(':').map(Number);

    // Debug log
    console.log('Heure actuelle (Paris):', currentHour + ':' + currentMinutes);

    // Obtenir la date de Paris
    const today = new Date().toLocaleString('fr-FR', {
        timeZone: 'Europe/Paris',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).split('/').reverse().join('-');

    // Activer/désactiver le select en fonction de la date
    timeSelect.disabled = false;

    // Définir les créneaux possibles
    const timeSlots = [
        "09:00", "09:30", "10:00", "10:30", "11:00", "11:30", "12:00",
        "12:30", "13:00", "13:30", "14:00", "14:30", "15:00", "15:30",
        "16:00", "16:30", "17:00", "17:30"
    ];

    // Vider la liste des créneaux
    timeSelect.innerHTML = '<option value="">Choisir un horaire</option>';

    // Si la date sélectionnée est aujourd'hui, filtrer les créneaux passés
    if (selectedDate === today) {
        // Debug log
        console.log('Date sélectionnée est aujourd\'hui');
        console.log('Heure actuelle (Paris):', currentHour + ':' + currentMinutes);

        // Ne pas afficher de créneaux si on est après 17h30
        if (currentHour >= 18 || (currentHour === 17 && currentMinutes > 30)) {
            console.log('Après 17h30, plus de créneaux disponibles');
            const option = document.createElement('option');
            option.value = "";
            option.textContent = "Plus de créneaux disponibles aujourd'hui";
            timeSelect.innerHTML = '';
            timeSelect.appendChild(option);
            timeSelect.disabled = true;
            return;
        }

        timeSlots.forEach(slot => {
            const [slotHour, slotMinutes] = slot.split(':').map(Number);

            // Debug log pour chaque créneau
            console.log('Vérification créneau:', slotHour + ':' + slotMinutes);

            // Vérifier si le créneau est dans le futur avec une marge de 30 minutes
            const slotTime = slotHour * 60 + slotMinutes;
            const currentTime = currentHour * 60 + currentMinutes;

            // On ajoute la marge de 30 minutes dans la comparaison
            if (slotTime >= currentTime + 30) {
                const option = document.createElement('option');
                option.value = slot + ':00';
                option.textContent = slot;
                timeSelect.appendChild(option);

                // Debug log pour les créneaux ajoutés
                console.log('Créneau ajouté:', slot);
            } else {
                console.log('Créneau ignoré:', slot, '(trop tôt)');
            }
        });
    } else if (selectedDate > today) {
        // Pour les dates futures, afficher tous les créneaux
        console.log('Date future sélectionnée');
        timeSlots.forEach(slot => {
            const option = document.createElement('option');
            option.value = slot + ':00';
            option.textContent = slot;
            timeSelect.appendChild(option);
        });
    }

    // Si aucun créneau n'est disponible
    if (timeSelect.options.length === 1) {
        const option = document.createElement('option');
        option.value = "";
        option.textContent = "Aucun créneau disponible";
        timeSelect.innerHTML = '';
        timeSelect.appendChild(option);
        timeSelect.disabled = true;
    }

    // Debug final
    console.log('Nombre de créneaux disponibles:', timeSelect.options.length - 1);
}

// Fonction pour afficher le message de confirmation
function showConfirmation(type, message) {
    const confirmationElement = document.getElementById('confirmation');

    if (confirmationElement) {
        // Simplifie le message à "Votre réservation a été enregistrée avec succès !"
        if (type === 'success') {
            confirmationElement.textContent = "Votre réservation a été enregistrée avec succès !";
        } else {
            confirmationElement.textContent = message;
        }

        confirmationElement.style.display = 'block';
        confirmationElement.className = 'confirmation-message ' + type + '-message';

        // Scroller jusqu'au message de confirmation
        confirmationElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}
