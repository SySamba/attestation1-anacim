<?php
// Configuration email
function sendEmail($to, $subject, $message, $isHTML = true) {
    $headers = array(
        'From' => 'noreply@anacim.sn',
        'Reply-To' => 'contact@anacim.sn',
        'X-Mailer' => 'PHP/' . phpversion()
    );
    
    if ($isHTML) {
        $headers['MIME-Version'] = '1.0';
        $headers['Content-type'] = 'text/html; charset=UTF-8';
    }
    
    $header_string = '';
    foreach ($headers as $key => $value) {
        $header_string .= $key . ': ' . $value . "\r\n";
    }
    
    return mail($to, $subject, $message, $header_string);
}

// Générer mot de passe temporaire
function generateTempPassword($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

// Email d'acceptation avec lien QCM
function sendAcceptanceEmail($candidate) {
    $tempPassword = generateTempPassword();
    
    // Sauvegarder le mot de passe temporaire en base
    global $pdo;
    $stmt = $pdo->prepare("UPDATE candidates SET temp_password = ?, qcm_access = 1 WHERE id = ?");
    $stmt->execute([password_hash($tempPassword, PASSWORD_DEFAULT), $candidate['id']]);
    
    $subject = "ANACIM - Candidature Acceptée - Accès à l'Examen QCM";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: #1e3a8a; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .credentials { background: #f0f9ff; padding: 15px; border-left: 4px solid #1e3a8a; margin: 20px 0; }
            .button { background: #1e3a8a; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
            .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>🎉 Félicitations ! Votre candidature est acceptée</h2>
        </div>
        
        <div class='content'>
            <p>Bonjour <strong>" . htmlspecialchars($candidate['prenom'] . ' ' . $candidate['nom']) . "</strong>,</p>
            
            <p>Nous avons le plaisir de vous informer que votre candidature pour la <strong>Certification du Personnel de Sûreté Aviation Civile</strong> a été acceptée.</p>
            
            <p><strong>Prochaine étape :</strong> Vous devez maintenant passer l'examen QCM en ligne.</p>
            
            <div class='credentials'>
                <h3>🔐 Vos identifiants de connexion :</h3>
                <p><strong>Matricule :</strong> " . htmlspecialchars($candidate['matricule']) . "</p>
                <p><strong>Mot de passe temporaire :</strong> <code style='background: #e5e7eb; padding: 2px 6px; border-radius: 3px;'>" . $tempPassword . "</code></p>
            </div>
            
            <p><strong>⚠️ Important :</strong></p>
            <ul>
                <li>Changez votre mot de passe lors de votre première connexion</li>
                <li>L'examen dure 30 minutes maximum</li>
                <li>Vous devez obtenir au moins 80% pour réussir</li>
                <li>Une seule tentative est autorisée</li>
            </ul>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . $_SERVER['HTTP_HOST'] . "/anacim-formation/candidate_login.php' class='button'>
                    🚀 ACCÉDER À L'EXAMEN QCM
                </a>
            </div>
            
            <p>Si vous avez des questions, n'hésitez pas à nous contacter.</p>
            
            <p>Bonne chance pour votre examen !</p>
        </div>
        
        <div class='footer'>
            <p><strong>ANACIM - Agence Nationale de l'Aviation Civile et de la Météorologie</strong><br>
            Email: contact@anacim.sn | Tél: +221 33 869 23 23</p>
        </div>
    </body>
    </html>";
    
    return sendEmail($candidate['email'], $subject, $message, true);
}

// Email de réussite d'une épreuve spécifique
function sendEpreuveSuccessEmail($candidate, $epreuve, $score) {
    $epreuve_names = [
        'THB' => 'Théorie de Base',
        'FBAG' => 'Filtrage Bagages', 
        'PLP' => 'Palpation',
        'FMAG' => 'Filtrage Magnétomètre',
        'IMAGERIE' => 'Imagerie'
    ];
    $epreuve_name = $epreuve_names[$epreuve] ?? $epreuve;
    
    $subject = "ANACIM - Épreuve {$epreuve_name} Réussie";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: #059669; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .score-box { background: #ecfdf5; padding: 20px; border-left: 4px solid #059669; margin: 20px 0; text-align: center; }
            .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>🎉 Épreuve {$epreuve_name} Réussie !</h2>
        </div>
        
        <div class='content'>
            <p>Bonjour <strong>" . htmlspecialchars($candidate['prenom'] . ' ' . $candidate['nom']) . "</strong>,</p>
            
            <p>Félicitations ! Vous avez <strong>RÉUSSI</strong> l'épreuve <strong>{$epreuve_name}</strong>.</p>
            
            <div class='score-box'>
                <h3>📊 Votre Résultat</h3>
                <p style='font-size: 24px; font-weight: bold; color: #059669; margin: 10px 0;'>{$score}%</p>
                <p style='color: #059669; font-weight: bold;'>✅ RÉUSSI</p>
            </div>
            
            <p><strong>Prochaines étapes :</strong></p>
            <ul>
                <li>Continuez avec les autres épreuves de la Phase 1</li>
                <li>Rappel : TH.B, FBAG, PLP, FMAG (toutes requises)</li>
                <li>Score minimum 80% pour chaque épreuve</li>
            </ul>
            
            <p>Continuez sur cette lancée !</p>
        </div>
        
        <div class='footer'>
            <p><strong>ANACIM - Agence Nationale de l'Aviation Civile et de la Météorologie</strong><br>
            Email: contact@anacim.sn | Tél: +221 33 869 23 23</p>
        </div>
    </body>
    </html>";
    
    return sendEmail($candidate['email'], $subject, $message, true);
}

// Email de réussite à l'examen (backward compatibility)
function sendSuccessEmail($candidate, $score) {
    return sendEpreuveSuccessEmail($candidate, 'GENERAL', $score);
}

// Email d'échec d'une épreuve spécifique
function sendEpreuveFailureEmail($candidate, $epreuve, $score) {
    $epreuve_names = [
        'THB' => 'Théorie de Base',
        'FBAG' => 'Filtrage Bagages', 
        'PLP' => 'Palpation',
        'FMAG' => 'Filtrage Magnétomètre',
        'IMAGERIE' => 'Imagerie'
    ];
    $epreuve_name = $epreuve_names[$epreuve] ?? $epreuve;
    
    $subject = "ANACIM - Épreuve {$epreuve_name} - Résultat";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: #dc2626; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .score-box { background: #fef2f2; padding: 20px; border-left: 4px solid #dc2626; margin: 20px 0; text-align: center; }
            .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>Résultat Épreuve {$epreuve_name}</h2>
        </div>
        
        <div class='content'>
            <p>Bonjour <strong>" . htmlspecialchars($candidate['prenom'] . ' ' . $candidate['nom']) . "</strong>,</p>
            
            <p>Nous vous remercions d'avoir passé l'épreuve <strong>{$epreuve_name}</strong>.</p>
            
            <div class='score-box'>
                <h3>📊 Votre Résultat</h3>
                <p style='font-size: 24px; font-weight: bold; color: #dc2626; margin: 10px 0;'>{$score}%</p>
                <p style='color: #dc2626; font-weight: bold;'>❌ NON VALIDÉ</p>
                <p><small>Score minimum requis : 80%</small></p>
            </div>
            
            <p><strong>Prochaines étapes :</strong></p>
            <ul>
                <li>Vous pouvez repasser cette épreuve après préparation</li>
                <li>Révisez les points faibles identifiés</li>
                <li>Contactez-nous pour des conseils de préparation</li>
            </ul>
            
            <p>Ne vous découragez pas, la réussite est à votre portée !</p>
        </div>
        
        <div class='footer'>
            <p><strong>ANACIM - Agence Nationale de l'Aviation Civile et de la Météorologie</strong><br>
            Email: contact@anacim.sn | Tél: +221 33 869 23 23</p>
        </div>
    </body>
    </html>";
    
    return sendEmail($candidate['email'], $subject, $message, true);
}

// Email d'admission à la Phase 2
function sendPhase2AdmissionEmail($candidate, $last_epreuve, $score) {
    $subject = "ANACIM - 🎉 ADMISSION PHASE 2 - IMAGERIE";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; }
            .celebration { background: #f0f9ff; padding: 25px; border-radius: 10px; margin: 20px 0; text-align: center; border: 2px solid #3b82f6; }
            .epreuves-list { background: #ecfdf5; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>🎉 FÉLICITATIONS EXCEPTIONNELLES ! 🎉</h1>
            <h2>ADMISSION À LA PHASE 2 - IMAGERIE</h2>
        </div>
        
        <div class='content'>
            <p>Bonjour <strong>" . htmlspecialchars($candidate['prenom'] . ' ' . $candidate['nom']) . "</strong>,</p>
            
            <div class='celebration'>
                <h2 style='color: #1e3a8a; margin-bottom: 15px;'>🚀 BRAVO ! PHASE 1 COMPLÈTEMENT RÉUSSIE !</h2>
                <p style='font-size: 18px; font-weight: bold; color: #059669;'>Vous avez obtenu au minimum 80% à TOUTES les épreuves !</p>
            </div>
            
            <p>Nous avons l'immense plaisir de vous informer que vous avez <strong>BRILLAMMENT RÉUSSI</strong> toutes les épreuves de la Phase 1 - Filtrage des personnes et bagages.</p>
            
            <div class='epreuves-list'>
                <h3 style='color: #059669;'>✅ Épreuves Phase 1 Validées :</h3>
                <ul style='list-style: none; padding: 0;'>
                    <li style='padding: 5px 0;'><strong>✅ TH.B</strong> - Théorie de Base</li>
                    <li style='padding: 5px 0;'><strong>✅ FBAG</strong> - Filtrage Bagages</li>
                    <li style='padding: 5px 0;'><strong>✅ PLP</strong> - Palpation</li>
                    <li style='padding: 5px 0;'><strong>✅ FMAG</strong> - Filtrage Magnétomètre</li>
                </ul>
                <p style='margin-top: 15px; font-weight: bold; color: #1e3a8a;'>Dernière épreuve validée : {$last_epreuve} avec {$score}%</p>
            </div>
            
            <div style='background: #fef3c7; padding: 20px; border-radius: 8px; border-left: 4px solid #f59e0b; margin: 20px 0;'>
                <h3 style='color: #92400e;'>🎯 VOUS ÊTES MAINTENANT ADMIS(E) À LA PHASE 2 !</h3>
                <p><strong>Phase 2 - IMAGERIE</strong> : Vous pouvez désormais accéder aux épreuves d'imagerie.</p>
            </div>
            
            <p><strong>Prochaines étapes :</strong></p>
            <ul>
                <li>Accès immédiat à la Phase 2 - Imagerie</li>
                <li>Vous recevrez les détails du planning sous peu</li>
                <li>Conservez cet email comme preuve de votre admission</li>
                <li>Félicitations pour cette performance exceptionnelle !</li>
            </ul>
            
            <p style='font-size: 18px; font-weight: bold; color: #1e3a8a; text-align: center; margin: 30px 0;'>
                🏆 TOUTE L'ÉQUIPE ANACIM VOUS FÉLICITE ! 🏆
            </p>
        </div>
        
        <div class='footer'>
            <p><strong>ANACIM - Agence Nationale de l'Aviation Civile et de la Météorologie</strong><br>
            Email: contact@anacim.sn | Tél: +221 33 869 23 23</p>
        </div>
    </body>
    </html>";
    
    return sendEmail($candidate['email'], $subject, $message, true);
}

// Email d'échec à l'examen (backward compatibility)
function sendFailureEmail($candidate, $score) {
    return sendEpreuveFailureEmail($candidate, 'GENERAL', $score);
}
?>
