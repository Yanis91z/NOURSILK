# NOURSILK - Système de Réservation

Ce système permet aux clients de réserver des prestations de lissage chez Noursilk, et aux administrateurs de gérer ces réservations.

## Fonctionnalités

- **Formulaire de réservation** pour les clients
- **Panneau d'administration** pour gérer les réservations
- **Intégration avec Zapier et Google Calendar** pour l'automatisation
- **Gestion sécurisée** des comptes administrateur

## Structure du projet

- `index.html` : Page d'accueil et formulaire de réservation
- `process_reservation.php` : Traitement des réservations et intégration Zapier
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

3. Configurer les paramètres de connexion à la base de données dans `process_reservation.php`

## Technologie utilisée

- HTML/CSS pour l'interface utilisateur
- PHP pour le backend
- MySQL pour la base de données
- Zapier pour l'automatisation des calendriers

## Crédits

Développé par Yanis pour Noursilk, 2025.

## Ajout automatique des réservations au Google Calendar de Nour

Le système propose plusieurs méthodes pour ajouter les réservations au Google Calendar de Nour :

### 1. Notifications par email (Méthode principale)

À chaque nouvelle réservation, un email est envoyé à l'adresse `chailiyanis.pro@gmail.com` avec :
- Les détails de la réservation (client, service, date, heure...)
- Une pièce jointe au format iCalendar (.ics)

**Comment utiliser** :
1. Ouvrez l'email depuis Gmail
2. La pièce jointe .ics sera automatiquement détectée par Gmail
3. Cliquez sur "Ajouter à mon calendrier" qui apparaît dans l'email

### 2. Fichiers HTML avec liens Google Calendar (Méthode alternative)

Pour chaque réservation, un fichier HTML est créé dans le dossier `gcal_links/` avec :
- Un résumé des détails de la réservation
- Un bouton qui ouvre Google Calendar avec les détails pré-remplis

**Comment utiliser** :
1. Accédez au dossier `gcal_links/` sur le serveur
2. Ouvrez le fichier HTML correspondant à la réservation
3. Cliquez sur le bouton "Ajouter à Google Calendar"
4. Vérifiez les détails et cliquez sur "Enregistrer" dans Google Calendar

### 3. Fichier de log (Sauvegarde)

Toutes les réservations sont également enregistrées dans un fichier `reservations_log.txt` qui sert d'historique.

## Configuration

Si vous souhaitez changer l'adresse email de destination :

1. Ouvrez le fichier `process_reservation.php`
2. Recherchez la ligne suivante (environ ligne 95) :
   ```php
   $nour_email = "chailiyanis.pro@gmail.com";
   ```
3. Remplacez par l'adresse email souhaitée

## Remarques importantes

- L'envoi d'emails peut ne pas fonctionner en environnement local (MAMP) si aucun serveur mail n'est configuré.
- Dans ce cas, utilisez la méthode des fichiers HTML (option 2).
- Assurez-vous que le dossier `gcal_links/` a les permissions d'écriture appropriées. 