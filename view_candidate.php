<?php
session_start();
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$candidate_id = $_GET['id'] ?? null;
if (!$candidate_id) {
    header('Location: admin_dashboard.php');
    exit;
}

// Get candidate information
$sql = "SELECT * FROM candidates WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidate) {
    $_SESSION['error'] = "Candidat non trouvé.";
    header('Location: admin_dashboard.php');
    exit;
}

// Get candidate documents
$doc_sql = "SELECT * FROM candidate_documents WHERE candidate_id = ? ORDER BY document_type";
$doc_stmt = $pdo->prepare($doc_sql);
$doc_stmt->execute([$candidate_id]);
$documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "ANACIM - Dossier " . $candidate['prenom'] . ' ' . $candidate['nom'];
include 'includes/header.php';
?>

<div class="row">
    <div class="col-12 mb-3">
        <a href="admin_dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header card-header-anacim">
                <h4><i class="fas fa-user"></i> Informations du Candidat</h4>
                <p class="mb-0">Référence: <?php echo str_pad($candidate['id'], 6, '0', STR_PAD_LEFT); ?></p>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Prénom:</strong></td>
                                <td><?php echo htmlspecialchars($candidate['prenom']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Nom:</strong></td>
                                <td><?php echo htmlspecialchars($candidate['nom']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Matricule:</strong></td>
                                <td><?php echo $candidate['matricule'] ? htmlspecialchars($candidate['matricule']) : '<span class="text-muted">Non renseigné</span>'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>
                                    <?php if ($candidate['email']): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($candidate['email']); ?>">
                                            <?php echo htmlspecialchars($candidate['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Non renseigné</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Catégorie:</strong></td>
                                <td><span class="badge bg-primary">Catégorie <?php echo $candidate['categorie']; ?></span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Date de Naissance:</strong></td>
                                <td><?php echo $candidate['date_naissance'] ? date('d/m/Y', strtotime($candidate['date_naissance'])) : '<span class="text-muted">Non renseigné</span>'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Lieu de Naissance:</strong></td>
                                <td><?php echo $candidate['lieu_naissance'] ? htmlspecialchars($candidate['lieu_naissance']) : '<span class="text-muted">Non renseigné</span>'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Date de Contrat:</strong></td>
                                <td><?php echo $candidate['date_contrat'] ? date('d/m/Y', strtotime($candidate['date_contrat'])) : '<span class="text-muted">Non renseigné</span>'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Type de Contrat:</strong></td>
                                <td><?php echo $candidate['type_contrat'] ? htmlspecialchars($candidate['type_contrat']) : '<span class="text-muted">Non renseigné</span>'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Date de Soumission:</strong></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($candidate['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($candidate['adresse']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <strong>Adresse:</strong><br>
                        <?php echo nl2br(htmlspecialchars($candidate['adresse'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header card-header-anacim">
                <h5><i class="fas fa-paperclip"></i> Documents Joints</h5>
            </div>
            <div class="card-body">
                <?php if (empty($documents)): ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-file-slash fa-2x mb-2"></i>
                        <p>Aucun document joint</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php 
                        $doc_labels = [
                            'cni' => 'CNI (Carte Nationale d\'Identité)',
                            'cv' => 'CV',
                            'attestation_formation' => 'Attestation Formation',
                            'casier' => 'Casier Judiciaire',
                            'certificat_medical' => 'Certificat Médical',
                            'formation_base' => 'Formation de Base',
                            'formation_imagerie' => 'Formation Imagerie'
                        ];
                        
                        foreach ($documents as $doc): 
                        ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-file-pdf text-danger me-2"></i>
                                <strong><?php echo $doc_labels[$doc['document_type']] ?? $doc['document_type']; ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($doc['file_name']); ?></small>
                                <br>
                                <small class="text-muted">Ajouté le <?php echo date('d/m/Y H:i', strtotime($doc['uploaded_at'])); ?></small>
                            </div>
                            <div>
                                <a href="download_file.php?id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-anacim btn-sm" title="Télécharger">
                                    <i class="fas fa-download"></i>
                                </a>
                                <a href="view_file.php?id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-info btn-sm" title="Visualiser" target="_blank">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <a href="download_files.php?id=<?php echo $candidate['id']; ?>" 
                           class="btn btn-success">
                            <i class="fas fa-download"></i> Télécharger Tous les Documents
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
