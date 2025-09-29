<?php
session_start();
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$page_title = "ANACIM - Tableau de Bord Administration";

// Pagination settings
$candidates_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $candidates_per_page;

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM candidates";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute();
$total_candidates = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_candidates / $candidates_per_page);

// Get candidates with their document counts and CNI info (with pagination)
$sql = "SELECT c.*, 
        COUNT(cd.id) as document_count,
        GROUP_CONCAT(cd.document_type) as document_types,
        MAX(CASE WHEN cd.document_type = 'cni' THEN cd.file_name END) as cni_file_name,
        MAX(CASE WHEN cd.document_type = 'cni' THEN cd.id END) as cni_doc_id
        FROM candidates c 
        LEFT JOIN candidate_documents cd ON c.id = cd.candidate_id 
        GROUP BY c.id 
        ORDER BY c.created_at DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $candidates_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_candidates,
    COUNT(CASE WHEN categorie = '1' THEN 1 END) as cat1,
    COUNT(CASE WHEN categorie = '2' THEN 1 END) as cat2,
    COUNT(CASE WHEN categorie = '3' THEN 1 END) as cat3,
    COUNT(CASE WHEN categorie = '4' THEN 1 END) as cat4,
    COUNT(CASE WHEN categorie = '5' THEN 1 END) as cat5
    FROM candidates";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-anacim">
                <h4><i class="fas fa-tachometer-alt"></i> Tableau de Bord - Certification Sûreté Aviation</h4>
                <p class="mb-0">Bienvenue, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Main Content with Sidebar -->
<div class="row">
    <!-- Sidebar Filters -->
    <div class="col-md-3">
        <div class="card">
            <div class="card-header card-header-anacim">
                <h6><i class="fas fa-filter"></i> Filtrer par Catégorie</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <button class="list-group-item list-group-item-action category-filter active" data-category="all">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-users me-2"></i>Tous les Candidats</span>
                            <span class="badge bg-anacim-blue rounded-pill"><?php echo $stats['total_candidates']; ?></span>
                        </div>
                    </button>
                    <button class="list-group-item list-group-item-action category-filter" data-category="1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-certificate me-2"></i>Catégorie 1</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['cat1']; ?></span>
                        </div>
                    </button>
                    <button class="list-group-item list-group-item-action category-filter" data-category="2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-certificate me-2"></i>Catégorie 2</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['cat2']; ?></span>
                        </div>
                    </button>
                    <button class="list-group-item list-group-item-action category-filter" data-category="3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-certificate me-2"></i>Catégorie 3</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['cat3']; ?></span>
                        </div>
                    </button>
                    <button class="list-group-item list-group-item-action category-filter" data-category="4">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-certificate me-2"></i>Catégorie 4</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['cat4']; ?></span>
                        </div>
                    </button>
                    <button class="list-group-item list-group-item-action category-filter" data-category="5">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-certificate me-2"></i>Catégorie 5</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['cat5']; ?></span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Download Section
        <div class="card mt-3">
            <div class="card-header card-header-anacim">
                <h6><i class="fas fa-download"></i> Téléchargements</h6>
            </div>
            <div class="card-body">
                <button class="btn btn-success w-100 mb-2" id="downloadAllBtn">
                    <i class="fas fa-file-excel"></i> Télécharger Tous (Excel)
                </button>
                <button class="btn btn-outline-success w-100" id="downloadFilteredBtn" style="display: none;">
                    <i class="fas fa-file-excel"></i> Télécharger Filtrés (Excel)
                </button>
            </div>
        </div>  -->
    </div>
    
    <!-- Main Content -->
    <div class="col-md-9">
        <div class="card">
    <div class="card-header card-header-anacim">
        <h5><i class="fas fa-users"></i> Liste des Candidats</h5>
    </div>
    <div class="card-body">
        <?php if (empty($candidates)): ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun candidat enregistré</h5>
                <p class="text-muted">Les candidatures apparaîtront ici une fois soumises.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Réf.</th>
                            <th>Nom Complet</th>
                            <th>CNI</th>
                            <th>Email</th>
                            <th>Catégorie</th>
                            <th>Documents</th>
                            <th>Date Soumission</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidates as $candidate): ?>
                        <tr class="candidate-row" data-category="<?php echo $candidate['categorie']; ?>">
                            <td>
                                <span class="badge bg-anacim-yellow text-dark">
                                    <?php echo str_pad($candidate['id'], 6, '0', STR_PAD_LEFT); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($candidate['prenom'] . ' ' . $candidate['nom']); ?></strong>
                                <?php if ($candidate['matricule']): ?>
                                    <br><small class="text-muted">Mat: <?php echo htmlspecialchars($candidate['matricule']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($candidate['cni_file_name']): ?>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-file-pdf text-danger me-2"></i>
                                        <div>
                                            
                                            <a href="view_file.php?id=<?php echo $candidate['cni_doc_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="fas fa-eye"></i> Voir
                                            </a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-danger">
                                        <i class="fas fa-exclamation-triangle"></i> CNI manquante
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($candidate['email']): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($candidate['email']); ?>">
                                        <?php echo htmlspecialchars($candidate['email']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Non renseigné</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-primary category-badge" data-category="<?php echo $candidate['categorie']; ?>" 
                                      style="cursor: pointer;" title="Cliquer pour filtrer par catégorie <?php echo $candidate['categorie']; ?>">
                                    Cat. <?php echo $candidate['categorie']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo $candidate['document_count']; ?> document(s)
                                </span>
                            </td>
                            <td>
                                <small><?php echo date('d/m/Y H:i', strtotime($candidate['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="view_candidate.php?id=<?php echo $candidate['id']; ?>" 
                                       class="btn btn-anacim btn-sm" title="Voir le dossier">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="download_files.php?id=<?php echo $candidate['id']; ?>" 
                                       class="btn btn-success btn-sm" title="Télécharger les fichiers">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="text-muted">
                Affichage de <?php echo $offset + 1; ?> à <?php echo min($offset + $candidates_per_page, $total_candidates); ?> 
                sur <?php echo $total_candidates; ?> candidats
            </div>
            <nav aria-label="Navigation des pages">
                <ul class="pagination pagination-sm mb-0">
                    <!-- Previous button -->
                    <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?>">
                                <i class="fas fa-chevron-left"></i> Précédent
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">
                                <i class="fas fa-chevron-left"></i> Précédent
                            </span>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Page numbers -->
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif;
                    endif;
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor;
                    
                    if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Next button -->
                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">
                                Suivant <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">
                                Suivant <i class="fas fa-chevron-right"></i>
                            </span>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const categoryFilters = document.querySelectorAll('.category-filter');
    const candidateRows = document.querySelectorAll('.candidate-row');
    const downloadAllBtn = document.getElementById('downloadAllBtn');
    const downloadFilteredBtn = document.getElementById('downloadFilteredBtn');
    let currentFilter = 'all';
    
    // Category filtering
    categoryFilters.forEach(filter => {
        filter.addEventListener('click', function() {
            const selectedCategory = this.getAttribute('data-category');
            currentFilter = selectedCategory;
            
            // Update active filter
            categoryFilters.forEach(f => f.classList.remove('active'));
            this.classList.add('active');
            
            // Filter candidates
            candidateRows.forEach(row => {
                const candidateCategory = row.getAttribute('data-category');
                
                if (selectedCategory === 'all' || candidateCategory === selectedCategory) {
                    row.style.display = '';
                    row.style.opacity = '1';
                } else {
                    row.style.opacity = '0.3';
                    setTimeout(() => {
                        row.style.display = 'none';
                    }, 200);
                }
            });
            
            // Show/hide filtered download button
            if (selectedCategory === 'all') {
                downloadFilteredBtn.style.display = 'none';
            } else {
                downloadFilteredBtn.style.display = 'block';
                downloadFilteredBtn.innerHTML = `<i class="fas fa-file-excel"></i> Télécharger Cat. ${selectedCategory} (Excel)`;
            }
        });
    });
    
    // Download all candidates
    downloadAllBtn.addEventListener('click', function() {
        window.location.href = 'export_candidates.php?type=all';
    });
    
    // Download filtered candidates
    downloadFilteredBtn.addEventListener('click', function() {
        window.location.href = 'export_candidates.php?type=category&category=' + currentFilter;
    });
});
</script>

<?php include 'includes/footer.php'; ?>
