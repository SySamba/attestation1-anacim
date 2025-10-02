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

// Email de réussite à l'examen
function sendSuccessEmail($candidate, $score) {
    $subject = "ANACIM - Félicitations ! Vous avez réussi l'examen QCM";
    
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
            <h2>🎉 FÉLICITATIONS ! Examen Réussi</h2>
        </div>
        
        <div class='content'>
            <p>Bonjour <strong>" . htmlspecialchars($candidate['prenom'] . ' ' . $candidate['nom']) . "</strong>,</p>
            
            <p>Nous avons le plaisir de vous informer que vous avez <strong>RÉUSSI</strong> l'examen QCM pour la Certification du Personnel de Sûreté Aviation Civile.</p>
            
            <div class='score-box'>
                <h3>📊 Votre Résultat</h3>
                <p style='font-size: 24px; font-weight: bold; color: #059669; margin: 10px 0;'>" . $score . "%</p>
                <p style='color: #059669; font-weight: bold;'>✅ RÉUSSI</p>
            </div>
            
            <p><strong>Prochaines étapes :</strong></p>
            <ul>
                <li>Votre certificat sera préparé dans les prochains jours</li>
                <li>Vous serez contacté pour la remise officielle</li>
                <li>Conservez cet email comme preuve de votre réussite</li>
            </ul>
            
            <p>Toute l'équipe ANACIM vous félicite pour cette réussite !</p>
        </div>
        
        <div class='footer'>
            <p><strong>ANACIM - Agence Nationale de l'Aviation Civile et de la Météorologie</strong><br>
            Email: contact@anacim.sn | Tél: +221 33 869 23 23</p>
        </div>
    </body>
    </html>";
    
    return sendEmail($candidate['email'], $subject, $message, true);
}

// Email d'échec à l'examen
function sendFailureEmail($candidate, $score) {
    $subject = "ANACIM - Résultat de votre examen QCM";
    
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
            <h2>Résultat de votre Examen QCM</h2>
        </div>
        
        <div class='content'>
            <p>Bonjour <strong>" . htmlspecialchars($candidate['prenom'] . ' ' . $candidate['nom']) . "</strong>,</p>
            
            <p>Nous vous remercions d'avoir passé l'examen QCM pour la Certification du Personnel de Sûreté Aviation Civile.</p>
            
            <div class='score-box'>
                <h3>📊 Votre Résultat</h3>
                <p style='font-size: 24px; font-weight: bold; color: #dc2626; margin: 10px 0;'>" . $score . "%</p>
                <p style='color: #dc2626; font-weight: bold;'>❌ NON ADMIS</p>
                <p><small>Score minimum requis : 80%</small></p>
            </div>
            
            <p><strong>Prochaines étapes :</strong></p>
            <ul>
                <li>Vous pouvez vous représenter après une période de formation complémentaire</li>
                <li>Contactez-nous pour connaître les modalités de représentation</li>
                <li>Des sessions de formation sont organisées régulièrement</li>
            </ul>
            
            <p>N'hésitez pas à nous contacter pour plus d'informations.</p>
        </div>
        
        <div class='footer'>
            <p><strong>ANACIM - Agence Nationale de l'Aviation Civile et de la Météorologie</strong><br>
            Email: contact@anacim.sn | Tél: +221 33 869 23 23</p>
        </div>
    </body>
    </html>";
    
    return sendEmail($candidate['email'], $subject, $message, true);
}
?>
