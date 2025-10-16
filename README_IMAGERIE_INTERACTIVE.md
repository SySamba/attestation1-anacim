# Système d'Examen d'Imagerie Interactive - ANACIM

## Vue d'ensemble

Le nouveau système d'examen d'imagerie interactive remplace l'ancien système textuel par une interface moderne et interactive qui simule les conditions réelles d'analyse d'images de sécurité.

## Fonctionnalités principales

### Pour les candidats

#### Interface d'examen
- **Timer de 30 secondes** par image maximum
- **Outils d'analyse** : zoom, rotation, orientation de l'image
- **Système de réponse** : 
  - "Passer" si l'image est correcte (aucun objet suspect)
  - "Objet trouvé" si un élément suspect est détecté

#### Catégories d'objets suspects
1. **Substances ou engins explosifs ou incendiaires**
2. **Objets tranchants**
3. **Outils de travail**
4. **Équipements émettant des projectiles**
5. **Appareils à effets paralysants**
6. **Instruments contondants**
7. **LAGs** (Liquides, Aérosols, Gels)

#### Processus d'examen
1. L'image s'affiche avec un timer de 30 secondes
2. Le candidat peut zoomer, orienter l'image pour l'analyser
3. Si l'image est correcte → cliquer "Passer"
4. Si un objet suspect est détecté :
   - Sélectionner la catégorie dans la liste
   - Cliquer sur la position exacte de l'objet dans l'image
5. Validation automatique de la réponse
6. Passage automatique à la question suivante

### Pour les administrateurs

#### Gestion des questions
- **Interface de création** : `admin_imagerie_interactive.php`
- **Upload d'images** : formats JPG, JPEG, PNG, GIF acceptés
- **Configuration des réponses** :
  - Type : "Passer" ou "Objet trouvé"
  - Catégorie d'objet (si applicable)
  - Position exacte (coordonnées X, Y)
  - Rayon de tolérance pour le clic

#### Obtention des coordonnées
1. Télécharger l'image dans le système
2. Cliquer sur l'image pour l'ouvrir en grand
3. Cliquer sur l'objet suspect pour obtenir les coordonnées
4. Copier les coordonnées affichées
5. Modifier la question pour ajouter les bonnes coordonnées

#### Gestion des résultats
- **Interface de consultation** : `admin_imagerie_interactive_results.php`
- **Détails par candidat** : scores, temps, réponses détaillées
- **Analyse des erreurs** : type d'erreur, distance du clic, etc.

## Structure de la base de données

### Tables principales

#### `imagerie_questions_interactive`
- `id` : Identifiant unique
- `image_path` : Chemin vers l'image
- `correct_answer_type` : 'pass' ou 'object_found'
- `object_category` : Catégorie d'objet (si applicable)
- `correct_x_position`, `correct_y_position` : Position correcte
- `tolerance_radius` : Rayon de tolérance (défaut: 50px)
- `is_active` : Question active ou non

#### `imagerie_sessions_interactive`
- `id` : Identifiant de session
- `candidate_id` : ID du candidat
- `status` : 'in_progress', 'completed', 'timeout'
- `total_questions`, `correct_answers` : Statistiques
- `score` : Score final en pourcentage
- `time_spent` : Temps total en secondes

#### `imagerie_responses_interactive`
- `session_id` : Lien vers la session
- `question_id` : ID de la question
- `response_type` : 'pass' ou 'object_found'
- `selected_category` : Catégorie choisie
- `clicked_x_position`, `clicked_y_position` : Position cliquée
- `time_spent` : Temps sur cette question
- `is_correct` : Réponse correcte ou non

## Accès au système

### Candidats
- **Prérequis** : Score ≥ 80% en THI (Catégorie 1) ou THB (Catégories 2-5)
- **URL d'accès** : `candidate_imagerie_interactive.php`
- **Résultats** : `candidate_imagerie_interactive_results.php`

### Administrateurs
- **Gestion questions** : `admin_imagerie_interactive.php`
- **Consultation résultats** : `admin_imagerie_interactive_results.php`
- **Détails candidat** : `admin_imagerie_interactive_details.php`

## Système de notation

### Calcul du score
- **Score = (Réponses correctes / Total questions) × 100**
- **Seuil de réussite** : 70%

### Critères de correction
1. **Type de réponse** : "Passer" vs "Objet trouvé"
2. **Catégorie d'objet** : Correspondance exacte requise
3. **Position** : Distance du clic ≤ rayon de tolérance
4. **Temps** : Enregistré mais n'affecte pas le score

## Sécurité et anti-triche

### Mesures implémentées
- **Timer strict** : 30 secondes maximum par image
- **Soumission automatique** : Si temps écoulé
- **Détection de changement d'onglet** : Pause du timer
- **Désactivation clic droit** : Sur les images
- **Session unique** : Une seule tentative par candidat

### Validation côté serveur
- Vérification de l'appartenance de la session au candidat
- Validation des coordonnées et catégories
- Calcul automatique de la correction
- Enregistrement horodaté de toutes les actions

## Installation et configuration

### Prérequis
- PHP 7.4+
- MySQL/MariaDB
- Extensions PHP : PDO, GD (pour les images)

### Déploiement
1. Les tables sont créées automatiquement via `schema.sql`
2. Créer le dossier `uploads/imagerie/` avec permissions d'écriture
3. Configurer les paramètres de base de données dans `config.php`
4. Tester l'upload d'images et la création de questions

### Maintenance
- **Sauvegarde régulière** des images dans `uploads/imagerie/`
- **Archivage des sessions** anciennes si nécessaire
- **Monitoring des performances** pour les gros volumes

## Support et dépannage

### Problèmes courants
1. **Images ne s'affichent pas** : Vérifier les permissions du dossier uploads
2. **Coordonnées incorrectes** : Utiliser l'outil de clic dans l'interface admin
3. **Timer ne fonctionne pas** : Vérifier JavaScript activé
4. **Scores incorrects** : Vérifier la tolérance et les coordonnées

### Logs et debugging
- Erreurs PHP dans les logs serveur
- Console JavaScript pour les erreurs côté client
- Table `imagerie_responses_interactive` pour l'audit des réponses

---

**Version** : 1.0  
**Date** : Octobre 2025  
**Auteur** : Système ANACIM
