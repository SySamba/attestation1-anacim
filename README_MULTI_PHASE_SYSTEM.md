# Système QCM Multi-Phases ANACIM

## Vue d'ensemble

Le système QCM multi-phases permet aux candidats de passer des tests de certification en deux phases distinctes :

### Phase 1 : Filtrage des personnes et bagages
- **THB** (Théorie de Base) - Connaissances générales de sûreté
- **FBAG** (Filtrage Bagages) - Contrôle des bagages
- **PLP** (Palpation) - Techniques de palpation sécurisée  
- **FMAG** (Filtrage Magnétomètre) - Utilisation du magnétomètre

### Phase 2 : Imagerie
- **IMAGERIE** - Interprétation des images de contrôle par rayons X

## Règles de progression

1. **Seuil de réussite** : 80% minimum pour chaque épreuve
2. **Accès Phase 2** : Toutes les 4 épreuves de Phase 1 doivent être réussies
3. **Reprise** : Les candidats peuvent reprendre les épreuves échouées
4. **Notifications** : Emails automatiques envoyés pour chaque résultat

## Structure de la base de données

### Tables modifiées
- `qcm_questions` : Ajout des colonnes `phase` et `epreuve`
- `qcm_sessions` : Ajout des colonnes `phase` et `epreuve`

### Nouvelle table
- `candidate_phase_results` : Suivi des résultats par phase et épreuve

## Interfaces

### Interface Administrateur
- **admin_qcm.php** : Création de questions par phase/épreuve
- **admin_dashboard.php** : Vue d'ensemble des candidats et résultats

### Interface Candidat  
- **candidate_dashboard.php** : Tableau de bord avec progression par épreuve
- **candidate_qcm.php** : Interface de passage des tests

## API et Backend

### qcm_api.php
- Gestion des sessions par phase/épreuve
- Calcul automatique des scores
- Mise à jour des résultats dans `candidate_phase_results`
- Envoi d'emails selon les résultats

### send_email.php
Fonctions d'email spécialisées :
- `sendEpreuveSuccessEmail()` : Succès d'une épreuve
- `sendEpreuveFailureEmail()` : Échec d'une épreuve  
- `sendPhase2AdmissionEmail()` : Admission en Phase 2

## Utilisation

### Pour les administrateurs
1. Créer des questions via l'interface admin en spécifiant phase et épreuve
2. Suivre les progrès des candidats sur le tableau de bord
3. Gérer les résultats et certifications

### Pour les candidats
1. Se connecter sur l'interface candidat
2. Voir les épreuves disponibles sur le tableau de bord
3. Passer les épreuves de Phase 1 dans l'ordre souhaité
4. Accéder à la Phase 2 après validation de toutes les épreuves Phase 1
5. Recevoir les notifications par email

## Scripts utilitaires

- `test_multi_phase_system.php` : Test et vérification du système
- `create_sample_questions.php` : Création de questions d'exemple
- `update_database_phases.php` : Mise à jour de la structure de base de données

## Configuration requise

- PHP 7.4+
- MySQL 5.7+
- Extensions PHP : PDO, mail
- Serveur web (Apache/Nginx)

## Sécurité

- Validation stricte des paramètres phase/épreuve
- Sessions sécurisées pour les candidats
- Contrôle d'accès par rôle (admin/candidat)
- Protection contre la triche (désactivation clic droit, sélection de texte)

## Maintenance

### Ajout d'une nouvelle épreuve
1. Modifier les ENUM dans la base de données
2. Mettre à jour les validations dans le code
3. Ajouter les templates d'email correspondants
4. Créer les questions via l'interface admin

### Modification du seuil de réussite
Modifier la constante dans `qcm_api.php` (actuellement 80%)

## Support

Pour toute question ou problème, consulter les logs d'erreur PHP et vérifier :
1. La structure de la base de données
2. Les permissions des fichiers
3. La configuration email du serveur
