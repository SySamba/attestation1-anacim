<?php
session_start();
$page_title = "ANACIM - Dépôt de Dossier Certification";
include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow">
            <div class="card-header card-header-anacim text-center">
                <h3><i class="fas fa-file-upload"></i> Dépôt de Dossier pour Certification</h3>
                <p class="mb-0">Certification du Personnel Chargé de la Mise en Œuvre des Contrôles de Sûreté</p>
            </div>
            <div class="card-body">
                <div class="alert alert-info d-flex align-items-center" role="alert">
                    <i class="fas fa-file-word fa-lg me-2"></i>
                    <div>
                        Téléchargez le formulaire officiel à remplir: 
                        <a class="fw-bold" href="FORMULAIRE CERTFICATION PERSONNEL DE SÜRETE_v2_.docx" download>
                            FORMULAIRE CERTFICATION PERSONNEL DE SÛRETÉ (Word)
                        </a>
                    </div>
                </div>
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="submit_application.php" method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cni" class="form-label">CNI (PDF) <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="cni" name="cni" accept=".pdf" required>
                            <small class="text-muted">Joindre une copie de votre CNI en format PDF</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="matricule" class="form-label">Matricule <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="matricule" name="matricule" required>
                            <small class="text-muted">Votre matricule d'employé est obligatoire</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="adresse" class="form-label">Adresse</label>
                        <textarea class="form-control" id="adresse" name="adresse" rows="2"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="date_naissance" class="form-label">Date de Naissance</label>
                            <input type="date" class="form-control" id="date_naissance" name="date_naissance">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="lieu_naissance" class="form-label">Lieu de Naissance</label>
                            <input type="text" class="form-control" id="lieu_naissance" name="lieu_naissance">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="categorie" class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select class="form-select" id="categorie" name="categorie" required>
                                <option value="">Sélectionner une catégorie</option>
                                <option value="1">Catégorie 1</option>
                                <option value="2">Catégorie 2</option>
                                <option value="3">Catégorie 3</option>
                                <option value="4">Catégorie 4</option>
                                <option value="5">Catégorie 5</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_contrat" class="form-label">Date de Contrat</label>
                            <input type="date" class="form-control" id="date_contrat" name="date_contrat">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="type_contrat" class="form-label">Type de Contrat</label>
                            <select class="form-select" id="type_contrat" name="type_contrat">
                                <option value="">Sélectionner le type</option>
                                <option value="CDI">CDI</option>
                                <option value="CDD">CDD</option>
                                <option value="Stage">Stage</option>
                                <option value="Consultant">Consultant</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="text-anacim-blue mb-3"><i class="fas fa-paperclip"></i> Documents à Joindre</h5>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cv" class="form-label">CV (PDF)</label>
                            <input type="file" class="form-control" id="cv" name="cv" accept=".pdf">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="attestation_formation" class="form-label">Attestation de Formation Périodique (PDF)</label>
                            <input type="file" class="form-control" id="attestation_formation" name="attestation_formation" accept=".pdf">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="casier" class="form-label">Casier Judiciaire (PDF)</label>
                            <input type="file" class="form-control" id="casier" name="casier" accept=".pdf">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="certificat_medical" class="form-label">Certificat Médical (PDF)</label>
                            <input type="file" class="form-control" id="certificat_medical" name="certificat_medical" accept=".pdf">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="formation_base" class="form-label">Formation de Base (PDF)</label>
                            <input type="file" class="form-control" id="formation_base" name="formation_base" accept=".pdf">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="formation_imagerie" class="form-label">Formation Imagerie (PDF)</label>
                            <input type="file" class="form-control" id="formation_imagerie" name="formation_imagerie" accept=".pdf">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="formulaire_surete" class="form-label">Formulaire Sûreté rempli (Word ou PDF)</label>
                        <input type="file" class="form-control" id="formulaire_surete" name="formulaire_surete" accept=".doc,.docx,.pdf">
                        <small class="text-muted">Téléchargez d'abord le formulaire Word, remplissez-le puis déposez ici le fichier rempli (.docx ou .pdf).</small>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-anacim btn-lg">
                            <i class="fas fa-paper-plane"></i> Soumettre le Dossier
                        </button>
                    </div>
                </form>

                <hr class="my-4">
                <div class="text-center">
                    <div class="row">
                        <div class="col-md-6">
                            <a href="candidate_login.php" class="btn btn-anacim btn-lg w-100 mb-2">
                                <i class="fas fa-user me-2"></i>Espace Candidat
                                <br><small>Passer mon examen QCM</small>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="admin_login.php" class="btn btn-outline-secondary btn-lg w-100 mb-2">
                                <i class="fas fa-user-shield me-2"></i>Administration
                                <br><small>Gestion des candidatures</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
