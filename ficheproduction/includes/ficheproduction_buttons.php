<?php
/* Copyright (C) 2025 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        includes/ficheproduction_buttons_improved.php
 * \ingroup     ficheproduction
 * \brief       Enhanced PDF action buttons with method selection
 */

/**
 * Generate enhanced PDF buttons with method choice
 *
 * @param Commande $object Order object
 * @param User $user User object
 * @param Translate $langs Language object
 * @param Conf $conf Configuration object
 */
function generateEnhancedPDFButtons($object, $user, $langs, $conf)
{
    global $db;
    
    // Check if user has permission to read orders
    if (!$user->rights->commande->lire) {
        return;
    }
    
    // Check if PDF file exists
    $filename = $object->ref."-fiche-production.pdf";
    $filepath = $conf->commande->multidir_output[$object->entity]."/".$object->ref."/".$filename;
    $fileExists = file_exists($filepath);
    $fileSize = $fileExists ? filesize($filepath) : 0;
    $fileDate = $fileExists ? date('d/m/Y H:i', filemtime($filepath)) : '';
    
    echo '<div class="ficheproduction-pdf-section" style="margin-top: 30px; padding: 20px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px;">';
    
    echo '<h3 style="margin-top: 0; color: #333;">📄 Génération PDF - Fiche de Production</h3>';
    
    // Information about available methods
    echo '<div style="margin-bottom: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #0066cc; font-size: 13px;">';
    echo '<strong>🔧 Méthodes disponibles :</strong><br>';
    echo '• <strong>HTML Template</strong> : Génération basée sur votre maquette HTML/CSS (recommandé)<br>';
    echo '• <strong>TCPDF</strong> : Génération programmatique avec TCPDF (fallback)';
    echo '</div>';
    
    // Current file information
    if ($fileExists) {
        echo '<div style="margin-bottom: 15px; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 3px;">';
        echo '<strong>✅ Fichier PDF existant :</strong><br>';
        echo '<span style="font-family: monospace;">'.$filename.'</span><br>';
        echo '<small>Taille : '.number_format($fileSize/1024, 1).' KB | Généré le : '.$fileDate.'</small>';
        echo '</div>';
    }
    
    // Main buttons row
    echo '<div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">';
    
    // Generate with HTML method (recommended)
    echo '<a href="'.dol_buildpath('/ficheproduction/generate_pdf.php?id='.$object->id.'&action=builddoc&pdf_method=html&token='.newToken(), 1).'" ';
    echo 'class="button" target="_blank" style="background: #28a745; color: white; border: none; padding: 8px 16px; text-decoration: none; border-radius: 3px;">';
    echo '🎨 Générer PDF (HTML Template)</a>';
    
    // Generate with TCPDF method
    echo '<a href="'.dol_buildpath('/ficheproduction/generate_pdf.php?id='.$object->id.'&action=builddoc&pdf_method=tcpdf&token='.newToken(), 1).'" ';
    echo 'class="button" target="_blank" style="background: #17a2b8; color: white; border: none; padding: 8px 16px; text-decoration: none; border-radius: 3px;">';
    echo '⚙️ Générer PDF (TCPDF)</a>';
    
    // Auto method (let system choose)
    echo '<a href="'.dol_buildpath('/ficheproduction/generate_pdf.php?id='.$object->id.'&action=builddoc&token='.newToken(), 1).'" ';
    echo 'class="button" target="_blank" style="background: #6c757d; color: white; border: none; padding: 8px 16px; text-decoration: none; border-radius: 3px;">';
    echo '🔄 Auto (Meilleure méthode)</a>';
    
    echo '</div>';
    
    // Secondary buttons row (if file exists)
    if ($fileExists) {
        echo '<div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">';
        
        // View existing PDF
        echo '<a href="'.DOL_URL_ROOT.'/document.php?modulepart=commande&file='.$object->ref.'/'.$filename.'" ';
        echo 'class="button" target="_blank" style="padding: 6px 12px;">';
        echo '👁️ Voir PDF existant</a>';
        
        // Download PDF
        echo '<a href="'.DOL_URL_ROOT.'/document.php?modulepart=commande&file='.$object->ref.'/'.$filename.'&attachment=1" ';
        echo 'class="button" style="padding: 6px 12px;">';
        echo '⬇️ Télécharger</a>';
        
        // Delete PDF (admin only)
        if ($user->admin) {
            echo '<a href="'.dol_buildpath('/ficheproduction/generate_pdf.php?id='.$object->id.'&action=remove_file&token='.newToken(), 1).'" ';
            echo 'class="button" onclick="return confirm(\'Êtes-vous sûr de vouloir supprimer ce fichier PDF ?\');" ';
            echo 'style="background: #dc3545; color: white; border: none; padding: 6px 12px; text-decoration: none; border-radius: 3px;">';
            echo '🗑️ Supprimer</a>';
        }
        
        echo '</div>';
    }
    
    // Method comparison info
    echo '<div style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 3px; font-size: 12px;">';
    echo '<strong>💡 Quelle méthode choisir ?</strong><br>';
    echo '<strong>HTML Template</strong> : Design identique à votre maquette, utilise les données sauvegardées, plus facile à maintenir<br>';
    echo '<strong>TCPDF</strong> : Génération programmatique, plus stable mais design basique<br>';
    echo '<strong>Auto</strong> : Le système choisit automatiquement la meilleure méthode disponible';
    echo '</div>';
    
    // Debug info (for developers)
    if (getDolGlobalString('FICHEPRODUCTION_DEBUG')) {
        echo '<div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 3px; font-size: 11px; font-family: monospace;">';
        echo '<strong>🔧 Debug Info :</strong><br>';
        echo 'Order ID: '.$object->id.'<br>';
        echo 'Order Ref: '.$object->ref.'<br>';
        echo 'File Path: '.$filepath.'<br>';
        echo 'File Exists: '.($fileExists ? 'Yes' : 'No').'<br>';
        echo 'HTML Class Available: '.(class_exists('FicheProductionHTMLToPDF') ? 'Yes' : 'No').'<br>';
        echo 'TCPDF Class Available: '.(class_exists('FicheProductionPDF') ? 'Yes' : 'No').'<br>';
        
        // Check for production data
        try {
            require_once dol_buildpath('/ficheproduction/class/ficheproductionmanager.class.php');
            $manager = new FicheProductionManager($db);
            $productionData = $manager->loadColisageData($object->id);
            echo 'Production Data: '.($productionData['success'] ? 'Available ('.count($productionData['colis']).' colis)' : 'None').'<br>';
        } catch (Exception $e) {
            echo 'Production Data: Error - '.$e->getMessage().'<br>';
        }
        
        echo '</div>';
    }
    
    echo '</div>'; // End ficheproduction-pdf-section
}

/**
 * Backward compatibility function
 */
function generatePDFButtons($object, $user, $langs, $conf) 
{
    generateEnhancedPDFButtons($object, $user, $langs, $conf);
}