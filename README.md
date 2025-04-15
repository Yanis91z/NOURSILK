# NOURSILK - Système de Réservation

Ce système permet aux clients de réserver des prestations de lissage chez Noursilk, et aux administrateurs de gérer ces réservations.

## Fonctionnalités

- **Formulaire de réservation intelligent** avec affichage des créneaux disponibles
- **Panneau d'administration** pour gérer les réservations
- **Intégration avec Zapier et Google Calendar** pour l'automatisation
- **Gestion sécurisée** des comptes administrateur

## Structure du projet

- `index.html` : Page d'accueil et formulaire de réservation
- `process_reservation.php` : Traitement des réservations et intégration Zapier
- `get_available_slots.php` : API pour récupérer les créneaux disponibles
- `admin/` : Interface d'administration
  - `index.php` : Gestion des réservations et clients
  - `login.php` : Authentification administrateur
  - `logout.php` : Déconnexion

## Installation

1. Cloner le dépôt
   ```
   git clone https://github.com/Yanis91z/NOURSILK.git
   ```

2. Configurer la base de données
   ```sql
   -- Importer le fichier database.sql
   ```

3. Configurer les paramètres de connexion à la base de données dans les fichiers PHP

## Système de réservation

Le système de réservation a été optimisé pour une meilleure expérience utilisateur :

### Gestion des créneaux disponibles

- Les créneaux horaires sont affichés dynamiquement selon les disponibilités
- Prise en compte des réservations existantes pour éviter les chevauchements
- Exclusion automatique des créneaux passés pour la journée en cours
- Respect des horaires d'ouverture (9h-18h) et de la pause déjeuner (12h-13h)

### Statut des réservations

Le système utilise deux statuts pour les réservations :
- **Confirmé** : Statut par défaut lors de la création d'une réservation
- **Annulé** : Pour les réservations qui ont été annulées

Les réservations sont automatiquement confirmées à la création pour simplifier le processus.

## Intégration Google Calendar via Zapier

À chaque nouvelle réservation, les détails sont automatiquement envoyés à Zapier qui crée un événement dans Google Calendar avec :
- Le nom du client
- Le type de service
- La date et l'heure
- La durée prévue
- Les coordonnées du client

## Technologie utilisée

- HTML/CSS pour l'interface utilisateur
- JavaScript pour les interactions dynamiques
- PHP pour le backend
- MySQL pour la base de données
- Zapier pour l'automatisation des calendriers

## Configuration

### Horaires d'ouverture

Vous pouvez modifier les horaires d'ouverture et les jours de fermeture dans le fichier `get_available_slots.php` :

```php
// Définir les heures d'ouverture
$opening_hour = 9;    // 9h00
$closing_hour = 18;   // 18h00
$slot_duration = 30;  // minutes par créneau
$lunch_start = 12;    // 12h00
$lunch_end = 13;      // 13h00

// Jours de fermeture (0 = dimanche, 6 = samedi)
$closed_days = [0];   // Fermé le dimanche
```

### Webhook Zapier

Pour modifier l'URL du webhook Zapier, modifiez la variable dans le fichier `process_reservation.php` :

```php
// URL du webhook Zapier
$webhook_url = "https://hooks.zapier.com/hooks/catch/22528164/207wzzd/";
```

## Crédits

Développé par Yanis pour Noursilk, 2025. 