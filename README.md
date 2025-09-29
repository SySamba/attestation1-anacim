# ANACIM - Système de Certification Sûreté Aviation Civile

## Description
Application web PHP/MySQL pour la gestion des dossiers de certification du personnel chargé de la mise en œuvre des contrôles de sûreté au niveau de l'ANACIM (Agence Nationale de l'Aviation Civile et de la Météorologie).

## Fonctionnalités

### Pour les Candidats
- Formulaire de soumission de dossier en ligne
- Upload de documents PDF (CV, attestations, certificats, etc.)
- Suivi par numéro de référence

### Pour l'Administration
- Interface d'administration sécurisée
- Tableau de bord avec statistiques
- Visualisation des dossiers candidats
- Téléchargement individuel ou groupé des documents
- Gestion par catégories (1 à 5)

## Installation

### Prérequis
- XAMPP (Apache, MySQL, PHP 7.4+)
- Extension PHP: PDO, ZIP

### Étapes d'installation

1. **Copier les fichiers**
   ```
   Copier tous les fichiers dans c:\xampp\htdocs\Anacim-formation\
   ```

2. **Créer la base de données**
   - Démarrer XAMPP (Apache + MySQL)
   - Accéder à phpMyAdmin: http://localhost/phpmyadmin
   - Importer le fichier: `database/schema.sql`

3. **Configuration**
   - Vérifier les paramètres dans `config/database.php`
   - Créer le dossier `uploads/` avec permissions d'écriture

4. **Accès à l'application**
   - Page principale: http://localhost/Anacim-formation/
   - Administration: http://localhost/Anacim-formation/admin_login.php

## Comptes par défaut

### Administrateur
- **Utilisateur:** admin
- **Mot de passe:** admin123

## Structure des fichiers

```
Anacim-formation/
├── config/
│   └── database.php          # Configuration base de données
├── database/
│   └── schema.sql            # Structure de la base de données
├── includes/
│   ├── header.php            # En-tête commun
│   └── footer.php            # Pied de page commun
├── uploads/                  # Dossier des fichiers uploadés
├── index.php                 # Page d'accueil - formulaire candidat
├── submit_application.php    # Traitement soumission dossier
├── admin_login.php          # Page de connexion admin
├── admin_auth.php           # Authentification admin
├── admin_dashboard.php      # Tableau de bord admin
├── view_candidate.php       # Détail d'un candidat
├── download_file.php        # Téléchargement fichier individuel
├── download_files.php       # Téléchargement archive ZIP
├── view_file.php           # Visualisation PDF
├── logout.php              # Déconnexion
└── logo-anacim.png         # Logo ANACIM
```

## Champs du formulaire candidat

### Informations personnelles
- Prénom et Nom (obligatoires)
- CNI (obligatoire)
- Adresse
- Email
- Date et lieu de naissance

### Informations professionnelles
- Matricule
- Catégorie (1-5, obligatoire)
- Date de contrat
- Type de contrat (CDI, CDD, Stage, Consultant)

### Documents à joindre (PDF)
- CV
- Attestation de formation périodique
- Casier judiciaire
- Certificat médical
- Formation de base
- Formation imagerie

## Couleurs ANACIM
- **Jaune:** #f6df33
- **Bleu:** #124d97
- **Rouge:** #ff011e

## Sécurité
- Authentification par session pour l'administration
- Validation des types de fichiers (PDF uniquement)
- Protection contre l'injection SQL (requêtes préparées)
- Noms de fichiers sécurisés

## Support
Pour toute question technique, contacter l'équipe de développement ANACIM.
