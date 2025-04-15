# Automatisation complète avec Zapier ou Make

Ce guide vous explique comment configurer une automatisation complète entre votre système de réservation NOURSILK et Google Calendar, en utilisant Zapier ou Make (anciennement Integromat).

## Pourquoi utiliser Zapier ou Make?

Ces services permettent de créer des "flux de travail" automatisés sans programmation complexe:
- Ils peuvent récupérer les données de réservation via un webhook
- Ils se connectent directement à votre compte Google Calendar
- Ils créent automatiquement les événements dans votre calendrier
- Ils fonctionnent même en environnement local (MAMP)

## Option 1: Configuration avec Zapier

### Étape 1: Créer un compte Zapier
1. Inscrivez-vous sur [Zapier](https://zapier.com/)
2. Créez un nouveau Zap (automatisation)

### Étape 2: Configurer le trigger (déclencheur)
1. Choisissez "Webhook by Zapier" comme application trigger
2. Sélectionnez "Catch Hook" comme événement
3. Zapier vous fournira une URL de webhook unique
4. Copiez cette URL

### Étape 3: Modifier votre code
1. Ouvrez le fichier `process_reservation.php`
2. Trouvez la ligne:
   ```php
   $webhook_url = "https://hooks.zapier.com/hooks/catch/123456/abcdef/";
   ```
3. Remplacez-la par votre URL de webhook Zapier

### Étape 4: Configurer l'action
1. Dans Zapier, ajoutez une action "Google Calendar"
2. Choisissez "Create Detailed Event" comme action
3. Connectez votre compte Google Calendar
4. Mappez les champs:
   - Summary: `{{client_nom}} - {{service_nom}}`
   - Description: Utilisez les données du webhook pour construire une description
   - Start Date & Time: Combinez `{{date_reservation}}` et `{{heure_reservation}}`
   - End Date & Time: Ajoutez 1 heure à l'heure de début
   - Reminders: 30 minutes

### Étape 5: Tester l'automatisation
1. Activez votre Zap
2. Faites une réservation test sur votre site
3. Vérifiez votre Google Calendar pour voir si l'événement a été créé

## Option 2: Configuration avec Make (Integromat)

Make offre une interface plus visuelle et des options plus avancées.

### Étape 1: Créer un compte Make
1. Inscrivez-vous sur [Make](https://www.make.com/)
2. Créez un nouveau scénario

### Étape 2: Configurer le webhook
1. Ajoutez un module "Webhooks" comme déclencheur
2. Sélectionnez "Custom webhook"
3. Make vous fournira une URL de webhook
4. Copiez cette URL dans votre fichier `process_reservation.php`

### Étape 3: Ajouter un module Google Calendar
1. Connectez votre compte Google Calendar
2. Configurez les champs comme dans l'étape 4 de Zapier

### Étape 4: Activer le scénario et tester
1. Activez votre scénario Make
2. Faites une réservation test

## Sécurité

Pour renforcer la sécurité, le code inclut une clé secrète. Dans Zapier ou Make, vous pouvez vérifier que cette clé correspond à la valeur attendue avant de créer l'événement.

## Dépannage

- **Le webhook n'est pas reçu** : Vérifiez que l'URL du webhook est correctement copiée dans votre code
- **Erreur lors de la création de l'événement** : Vérifiez le format des dates que vous envoyez
- **Webhook mais pas d'événement** : Consultez les logs d'exécution dans Zapier ou Make 