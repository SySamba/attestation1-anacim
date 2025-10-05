<?php
// Script to fix all config/database.php references to config.php

$files_to_fix = [
    'admin_auth.php', 'admin_dashboard.php', 'admin_imagerie.php', 'admin_imagerie_questions.php',
    'admin_qcm.php', 'admin_qcm_edit.php', 'admin_question_add.php', 'admin_question_edit.php',
    'admin_questions.php', 'admin_results.php', 'admin_test_authorizations.php', 'candidate_auth.php',
    'candidate_dashboard_clean.php', 'candidate_imagerie.php', 'candidate_qcm.php', 'check_modou_faye.php',
    'clean_auth_system.php', 'clean_dashboard.php', 'debug_auth.php', 'debug_candidate_access.php',
    'delete_qcm_question.php', 'direct_fix_auth.php', 'download_file.php', 'download_files.php',
    'export_candidates.php', 'final_debug.php', 'fix_auth_now.php', 'force_debug_current_candidate.php',
    'force_demba_fix.php', 'force_refresh_auth.php', 'process_candidate_action.php', 'qcm_api.php',
    'quick_candidate_check.php', 'save_qcm_question.php', 'submit_application.php', 'test_simple_auth.php',
    'update_qcm_question.php', 'view_candidate.php', 'view_file.php'
];

$fixed_count = 0;
$errors = [];

foreach ($files_to_fix as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $new_content = str_replace("require_once 'config/database.php';", "require_once 'config.php';", $content);
        $new_content = str_replace('require_once "config/database.php";', 'require_once "config.php";', $new_content);
        
        if ($content !== $new_content) {
            if (file_put_contents($file, $new_content)) {
                echo "Fixed: $file\n";
                $fixed_count++;
            } else {
                $errors[] = "Failed to write: $file";
            }
        }
    } else {
        $errors[] = "File not found: $file";
    }
}

echo "\nSummary:\n";
echo "Files fixed: $fixed_count\n";
if (!empty($errors)) {
    echo "Errors:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}
echo "Done!\n";
?>
