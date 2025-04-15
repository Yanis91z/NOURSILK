document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM chargé - Initialisation du formulaire de réservation');

    // Afficher / masquer le message de confirmation
    const urlParams = new URLSearchParams(window.location.search);
    const reservation = urlParams.get('reservation');

    const confirmationMessage = document.getElementById('confirmation');

    if (reservation === 'success') {
        confirmationMessage.style.display = 'block';
        confirmationMessage.style.backgroundColor = '#f8d7da';
        confirmationMessage.style.padding = '10px';
        confirmationMessage.style.borderRadius = '5px';
        confirmationMessage.style.width = '100%';
        // Configurer le lien Google Calendar
        setupGoogleCalendarLink();
        // Scroll jusqu'au message de confirmation
        confirmationMessage.scrollIntoView({ behavior: 'smooth' });
    } else if (reservation === 'error') {
        // Afficher un message d'erreur
        confirmationMessage.innerHTML = '<p class="error-message" style="background-color: #f8d7da; padding: 10px; border-radius: 5px; width: 100%;">Une erreur est survenue lors de la réservation. Veuillez réessayer ou nous contacter directement.</p>';
        confirmationMessage.style.display = 'block';
        confirmationMessage.scrollIntoView({ behavior: 'smooth' });
    } else if (reservation === 'conflict') {
        // Afficher un message d'erreur spécifique pour les conflits de réservation
        confirmationMessage.innerHTML = '<p class="error-message" style="background-color: #f8d7da; padding: 10px; border-radius: 5px; width: 100%;">Ce créneau n\'est plus disponible. Veuillez sélectionner un autre horaire.</p>';
        confirmationMessage.style.display = 'block';
        confirmationMessage.scrollIntoView({ behavior: 'smooth' });
    }

    // Écouter les changements de date et de service pour mettre à jour les créneaux disponibles
    const dateInput = document.getElementById('date');
    if (dateInput) {
        console.log('Champ de date trouvé:', dateInput);
        console.log('Valeur initiale de la date:', dateInput.value);

        dateInput.addEventListener('change', function () {
            console.log('Date changée:', this.value);
            if (this.value) {
                loadAvailableTimeSlots(this.value);
            }
        });

        // Charger les créneaux pour la date par défaut
        if (dateInput.value) {
            console.log('Chargement des créneaux pour la date par défaut:', dateInput.value);
            loadAvailableTimeSlots(dateInput.value);
        } else {
            console.log('Aucune date par défaut définie');
        }
    } else {
        console.error('Champ de date non trouvé dans le formulaire');
    }

    // Écouter les changements de service
    const serviceInputs = document.querySelectorAll('input[name="service"]');
    console.log('Boutons radio de service trouvés:', serviceInputs.length);

    serviceInputs.forEach(input => {
        console.log('Service option:', input.id, 'Value:', input.value, 'Checked:', input.checked);

        input.addEventListener('change', function () {
            console.log('Service changé:', this.value);
            const date = document.getElementById('date').value;
            if (date) {
                loadAvailableTimeSlots(date);
            }
        });
    });

    // Afficher l'état initial des services
    const selectedService = document.querySelector('input[name="service"]:checked');
    console.log('Service initialement sélectionné:', selectedService ? selectedService.value : 'Aucun');
});

// Fonction pour configurer le lien Google Calendar
function setupGoogleCalendarLink() {
    // Récupérer les données du formulaire
    const form = document.querySelector('form');
    if (!form) return;

    const name = form.name ? form.name.value : '';
    const service = form.service && form.service.value === 'indien' ? 'Lissage Indien' : 'Lissage Tanin';
    const date = form.date ? form.date.value : '';
    const time = form.time ? form.time.value : '';

    // Durée par défaut en minutes
    const duration = service === 'Lissage Indien' ? 180 : 120;

    if (!date || !time) return;

    // Formater la date et l'heure pour Google Calendar
    const startDate = new Date(`${date}T${time}`);
    const endDate = new Date(startDate.getTime() + duration * 60000);

    const start = startDate.toISOString().replace(/-|:|\.\d+/g, '');
    const end = endDate.toISOString().replace(/-|:|\.\d+/g, '');

    // Créer le lien Google Calendar
    const calendarLink = document.getElementById('google-calendar-link');
    if (calendarLink) {
        const url = `https://www.google.com/calendar/render?action=TEMPLATE&text=NOURSILK - ${service}&details=Votre réservation pour ${service} à NOURSILK&location=NOURSILK Salon&dates=${start}/${end}`;
        calendarLink.href = url;
    }
}

// Fonction pour charger les créneaux disponibles pour une date
function loadAvailableTimeSlots(date) {
    // Debug initial
    console.groupCollapsed('Chargement des créneaux pour ' + date);
    console.time('Chargement des créneaux');

    // Récupérer le service sélectionné
    const serviceRadios = document.querySelectorAll('input[name="service"]');
    let serviceId = '';

    // Parcourir tous les boutons radio pour trouver celui qui est coché
    serviceRadios.forEach(radio => {
        if (radio.checked) {
            serviceId = radio.value;
            console.log('Service sélectionné:', serviceId, '(ID:', radio.id, ')');
        }
    });

    // Message UI
    const timeSelect = document.getElementById('time');
    if (timeSelect) {
        timeSelect.innerHTML = '<option value="">Chargement des créneaux...</option>';
        timeSelect.disabled = true;
    }

    const loadingIndicator = document.getElementById('loading-slots');
    if (loadingIndicator) loadingIndicator.style.display = 'block';

    // Construire l'URL avec les paramètres
    const url = `PHP/get_available_slots.php?date=${encodeURIComponent(date)}&service=${encodeURIComponent(serviceId)}`;
    console.log('URL de requête:', url);

    const xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);

    xhr.onreadystatechange = function () {
        console.log('XHR état:', xhr.readyState, 'status:', xhr.status);
        if (xhr.readyState === 4) {
            console.log('Réponse complète reçue');
        }
    };

    xhr.onload = function () {
        console.log('XHR onload - status:', this.status);
        if (this.status === 200) {
            try {
                const responseText = this.responseText;
                console.log('Réponse brute:', responseText.substring(0, 200) + '... (' + responseText.length + ' caractères)');

                // Tester si la réponse est du JSON valide
                try {
                    JSON.parse(responseText);
                    console.log('Réponse est du JSON valide');
                } catch (e) {
                    console.error('Réponse n\'est PAS du JSON valide:', e);
                }

                const response = JSON.parse(responseText);
                console.log('Réponse parsée:', response);

                if (loadingIndicator) loadingIndicator.style.display = 'none';

                if (!timeSelect) {
                    console.error('Élément #time non trouvé dans le DOM');
                    console.groupEnd();
                    return;
                }

                // Vider la liste déroulante
                timeSelect.innerHTML = '<option value="">Choisir un horaire</option>';
                timeSelect.disabled = false;

                if (response.success) {
                    // Ajouter les créneaux disponibles
                    if (response.available_slots && Array.isArray(response.available_slots)) {
                        console.log('Nombre de créneaux disponibles:', response.available_slots.length);

                        response.available_slots.forEach(slot => {
                            const option = document.createElement('option');
                            option.value = slot;
                            option.textContent = slot;
                            timeSelect.appendChild(option);
                        });

                        // Afficher message si aucun créneau disponible
                        if (response.available_slots.length === 0) {
                            const option = document.createElement('option');
                            option.disabled = true;
                            option.textContent = 'Aucun créneau disponible pour cette date';
                            timeSelect.appendChild(option);

                            const noSlotsMessage = document.getElementById('no-slots-message');
                            if (noSlotsMessage) {
                                noSlotsMessage.textContent = response.message || 'Aucun créneau disponible pour cette date. Veuillez choisir une autre date.';
                                noSlotsMessage.style.display = 'block';
                            }
                        } else {
                            const noSlotsMessage = document.getElementById('no-slots-message');
                            if (noSlotsMessage) {
                                noSlotsMessage.style.display = 'none';
                            }
                        }
                    } else {
                        console.error('Format des créneaux invalide:', response.available_slots);
                        const option = document.createElement('option');
                        option.disabled = true;
                        option.textContent = 'Erreur: format des créneaux invalide';
                        timeSelect.appendChild(option);
                    }
                } else {
                    // Afficher le message d'erreur
                    console.error('Erreur dans la réponse:', response.message);
                    const option = document.createElement('option');
                    option.disabled = true;
                    option.textContent = response.message || 'Erreur lors du chargement des créneaux';
                    timeSelect.appendChild(option);
                }
            } catch (e) {
                console.error('Erreur lors du parsing JSON:', e);
                console.log('Réponse brute complète:', this.responseText);

                if (timeSelect) {
                    timeSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                    timeSelect.disabled = true;
                }

                if (loadingIndicator) loadingIndicator.style.display = 'none';
            }
        } else {
            console.error('Erreur HTTP:', this.status, this.statusText);

            if (timeSelect) {
                timeSelect.innerHTML = '<option value="">Erreur de connexion</option>';
                timeSelect.disabled = true;
            }

            if (loadingIndicator) loadingIndicator.style.display = 'none';
        }

        console.timeEnd('Chargement des créneaux');
        console.groupEnd();
    };

    xhr.onerror = function () {
        console.error('Erreur réseau lors de la requête');

        if (timeSelect) {
            timeSelect.innerHTML = '<option value="">Erreur de connexion</option>';
            timeSelect.disabled = true;
        }

        if (loadingIndicator) loadingIndicator.style.display = 'none';

        console.timeEnd('Chargement des créneaux');
        console.groupEnd();
    };

    xhr.send();
} 