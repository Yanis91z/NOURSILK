/**
 * Scripts de sécurité pour la page de connexion
 */
function initLoginPage() {
    // Vider le formulaire lors du chargement de la page
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';

    // Empêcher la navigation avec le bouton retour et réinitialiser le formulaire
    window.addEventListener('pageshow', function (event) {
        var form = document.querySelector('form');
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            form.reset();
        }
    });
}

/**
 * Initialisation en fonction de la page
 */
document.addEventListener('DOMContentLoaded', function () {
    // Détecter la page actuelle
    const isLoginPage = document.querySelector('.login-container') !== null;

    if (isLoginPage) {
        initLoginPage();
    }
});

// Script pour améliorer l'interface d'administration

document.addEventListener('DOMContentLoaded', function () {
    // Ajouter un effet visuel lors du changement de statut
    const statusSelects = document.querySelectorAll('.status-select');

    statusSelects.forEach(select => {
        // Sauvegarder la valeur initiale
        select.dataset.initialValue = select.value;

        select.addEventListener('change', function () {
            // Ajout d'un effet visuel pendant la soumission
            this.classList.add('updating');

            // Afficher un message temporaire
            const row = this.closest('tr');
            const statusCell = row.querySelector('td:nth-child(5)');
            const originalStatusText = statusCell.innerHTML;

            statusCell.innerHTML = '<span class="status status-updating">Mise à jour...</span>';

            // Le formulaire sera soumis automatiquement grâce à onchange="this.form.submit()"
        });
    });

    // Gestion des alertes
    setTimeout(function () {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.classList.add('fade-out');
            setTimeout(() => {
                alert.style.display = 'none';
            }, 500);
        });
    }, 3000);

    // Amélioration de la recherche client
    const clientSearch = document.getElementById('client-search');
    if (clientSearch) {
        clientSearch.focus();

        // Ajouter un délai de recherche automatique
        let searchTimeout;
        clientSearch.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 3 || this.value.length === 0) {
                    this.closest('form').submit();
                }
            }, 500);
        });
    }
});

// Fonctions pour le modal d'édition client
function openEditModal(id, name, email, phone) {
    // Remplir le formulaire avec les données du client
    document.getElementById('client_id').value = id;
    document.getElementById('client_name').value = name;
    document.getElementById('client_email').value = email;
    document.getElementById('client_phone').value = phone;

    // Afficher le modal
    document.getElementById('editClientModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editClientModal').style.display = 'none';
}

// Fermer le modal si l'utilisateur clique en dehors
window.onclick = function (event) {
    const modal = document.getElementById('editClientModal');
    if (event.target == modal) {
        closeEditModal();
    }
} 