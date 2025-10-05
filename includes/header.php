<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'ANACIM - Certification Sûreté Aviation'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --anacim-yellow: #f6df33;
            --anacim-blue: #124d97;
            --anacim-red: #ff011e;
        }
        
        .navbar-anacim {
            background: linear-gradient(135deg, var(--anacim-blue), var(--anacim-blue));
            border-bottom: 3px solid var(--anacim-yellow);
        }
        
        .btn-anacim {
            background-color: var(--anacim-blue);
            border-color: var(--anacim-blue);
            color: white;
        }
        
        .btn-anacim:hover {
            background-color: var(--anacim-yellow);
            border-color: var(--anacim-yellow);
            color: var(--anacim-blue);
        }
        
        .btn-outline-anacim {
            border-color: var(--anacim-blue);
            color: var(--anacim-blue);
        }
        
        .btn-outline-anacim:hover {
            background-color: var(--anacim-blue);
            border-color: var(--anacim-blue);
            color: white;
        }
        
        .category-filter {
            margin-bottom: 10px;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        
        .category-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .category-filter.active {
            background-color: var(--anacim-blue) !important;
            border-color: var(--anacim-blue) !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(18, 77, 151, 0.3);
        }
        
        .candidate-row {
            transition: all 0.3s ease;
        }
        
        .candidate-row:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        
        .category-badge {
            transition: all 0.3s ease;
        }
        
        .category-badge:hover {
            transform: scale(1.05) !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .bg-anacim-blue {
            background-color: var(--anacim-blue) !important;
        }
        
        .category-filter {
            border: none !important;
            transition: all 0.3s ease;
        }
        
        .category-filter:hover {
            background-color: #f8f9fa !important;
            transform: translateX(5px);
        }
        
        .category-filter.active {
            background-color: var(--anacim-yellow) !important;
            color: var(--anacim-blue) !important;
            font-weight: bold;
        }
        
        .btn-danger-anacim {
            background-color: var(--anacim-red);
            border-color: var(--anacim-red);
        }
        
        .card-header-anacim {
            background: linear-gradient(135deg, var(--anacim-blue), #1a5bb8);
            color: white;
        }
        
        .text-anacim-blue {
            color: var(--anacim-blue) !important;
        }
        
        .text-anacim-yellow {
            color: var(--anacim-yellow) !important;
        }
        
        .bg-anacim-yellow {
            background-color: var(--anacim-yellow) !important;
        }
        
        .logo-container {
            max-height: 60px;
            background-color: white;
            padding: 5px;
            border-radius: 8px;
            border: 2px solid var(--anacim-yellow);
        }
        
        .logo-container img {
            max-height: 60px;
            width: auto;
        }

        /* Utilities for wrapping long content (filenames, emails) */
        .break-word {
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        .no-wrap {
            white-space: nowrap;
        }

        /* Responsive tweaks */
        @media (max-width: 576px) {
            .navbar-anacim .navbar-brand small {
                font-size: 0.7rem;
            }
            .navbar-anacim .navbar-brand strong {
                font-size: 1rem;
            }
            .logo-container {
                max-height: 48px;
            }
            .logo-container img {
                max-height: 48px;
            }
            .btn {
                padding: 0.375rem 0.5rem;
                font-size: 0.9rem;
            }
            .table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-anacim">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <div class="logo-container me-3">
                    <img src="logo-anacim.png" alt="ANACIM Logo" class="img-fluid">
                </div>
                <div>
                    <strong>ANACIM</strong><br>
                    <small>Certification Sûreté Aviation Civile</small>
                </div>
            </a>
            
            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <main class="container mt-4">
