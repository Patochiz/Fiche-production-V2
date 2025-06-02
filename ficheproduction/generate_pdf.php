<?php
/* Copyright (C) 2025 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       generate_pdf.php
 * \ingroup    ficheproduction
 * \brief      Generate production sheet PDF using HTML template - Version améliorée
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

// Load required files
require_once DOL_DOCUMENT_ROOT.'/core/lib/order.lib.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

// Load NEW FicheProduction HTML to PDF class
require_once dol_buildpath('/ficheproduction/class/ficheproductionhtmltopdf.class.php');

// Keep old class as fallback
require_once dol_buildpath('/ficheproduction/class/ficheproductionpdf.class.php');

// Load translations
$langs->loadLangs(array('orders', 'products', 'companies'));
$langs->load('ficheproduction@ficheproduction');

// Get parameters
$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'alpha');
$pdf_method = GETPOST('pdf_method', 'alpha'); // New parameter to choose method

// Check permissions
if (!$user->rights->commande->lire) {
    accessforbidden();
}

// Initialize object
$object = new Commande($db);

// Load object
if ($id > 0 || !empty($ref)) {
    $result = $object->fetch($id, $ref);
    if ($result <= 0) {
        dol_print_error($db, $object->error);
        exit;
    }
} else {
    header('Location: '.dol_buildpath('/commande/list.php', 1));
    exit;
}

/*
 * Actions
 */

if ($action == 'builddoc') {
    // DEBUG: Log de démarrage
    dol_syslog("FicheProduction PDF: Début génération pour commande ".$object->id." avec méthode ".($pdf_method ?: 'auto'), LOG_INFO);
    
    try {
        // Choose PDF generation method
        $useHTMLMethod = true; // Default to HTML method
        
        // Allow override via parameter
        if ($pdf_method === 'tcpdf') {
            $useHTMLMethod = false;
        } elseif ($pdf_method === 'html') {
            $useHTMLMethod = true;
        } else {
            // Auto-detect: prefer HTML method if available
            if (class_exists('FicheProductionHTMLToPDF')) {
                $useHTMLMethod = true;
                dol_syslog("FicheProduction PDF: Auto-selected HTML method", LOG_INFO);
            } else {
                $useHTMLMethod = false;
                dol_syslog("FicheProduction PDF: Fallback to TCPDF method", LOG_INFO);
            }
        }
        
        // Generate PDF
        $hidedetails = GETPOST('hidedetails', 'int') ? GETPOST('hidedetails', 'int') : 0;
        $hidedesc = GETPOST('hidedesc', 'int') ? GETPOST('hidedesc', 'int') : 0;
        $hideref = GETPOST('hideref', 'int') ? GETPOST('hideref', 'int') : 0;
        
        $outputlangs = $langs;
        $newlang = '';
        
        if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id', 'aZ09')) {
            $newlang = GETPOST('lang_id', 'aZ09');
        }
        if ($conf->global->MAIN_MULTILANGS && empty($newlang)) {
            $newlang = $object->thirdparty->default_lang;
        }
        if (!empty($newlang)) {
            $outputlangs = new Translate("", $conf);
            $outputlangs->setDefaultLang($newlang);
        }
        
        // Create PDF generator based on selected method
        if ($useHTMLMethod) {
            dol_syslog("FicheProduction PDF: Utilisation de la méthode HTML", LOG_INFO);
            $pdfGenerator = new FicheProductionHTMLToPDF($db);
            $methodName = "HTML Template";
        } else {
            dol_syslog("FicheProduction PDF: Utilisation de la méthode TCPDF", LOG_INFO);
            $pdfGenerator = new FicheProductionPDF($db);
            $methodName = "TCPDF";
        }
        
        dol_syslog("FicheProduction PDF: Appel write_file avec ".$methodName, LOG_INFO);
        
        $result = $pdfGenerator->write_file($object, $outputlangs, '', $hidedetails, $hidedesc, $hideref);
        
        if ($result <= 0) {
            dol_syslog("FicheProduction PDF: Erreur génération - ".$pdfGenerator->error, LOG_ERR);
            
            // If HTML method failed, try TCPDF as fallback
            if ($useHTMLMethod && class_exists('FicheProductionPDF')) {
                dol_syslog("FicheProduction PDF: Tentative fallback vers TCPDF", LOG_INFO);
                $pdfGenerator = new FicheProductionPDF($db);
                $result = $pdfGenerator->write_file($object, $outputlangs, '', $hidedetails, $hidedesc, $hideref);
                
                if ($result <= 0) {
                    dol_print_error($db, "Erreur avec les deux méthodes: ".$pdfGenerator->error);
                    exit;
                }
                $methodName = "TCPDF (fallback)";
            } else {
                dol_print_error($db, $pdfGenerator->error);
                exit;
            }
        }
        
        dol_syslog("FicheProduction PDF: Génération réussie avec ".$methodName, LOG_INFO);
        
        // Redirect to the generated PDF
        $filename = $object->ref."-fiche-production.pdf";
        $filepath = $conf->commande->multidir_output[$object->entity]."/".$object->ref."/".$filename;
        
        if (file_exists($filepath)) {
            // Add generation info in headers
            header('X-PDF-Method: ' . $methodName);
            header('X-PDF-Generated: ' . date('Y-m-d H:i:s'));
            
            // Set headers for PDF display
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="'.$filename.'"');
            header('Content-Length: '.filesize($filepath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            
            // Output the PDF
            readfile($filepath);
            exit;
        } else {
            dol_syslog("FicheProduction PDF: Fichier non trouvé - ".$filepath, LOG_ERR);
            setEventMessages("Erreur : Fichier PDF non trouvé après génération", null, 'errors');
        }
        
    } catch (Exception $e) {
        dol_syslog("FicheProduction PDF: Exception - ".$e->getMessage(), LOG_ERR);
        dol_print_error($db, "Erreur PHP: ".$e->getMessage());
        exit;
    }
}

if ($action == 'remove_file') {
    // Remove PDF file
    $filename = $object->ref."-fiche-production.pdf";
    $filepath = $conf->commande->multidir_output[$object->entity]."/".$object->ref."/".$filename;
    
    if (file_exists($filepath)) {
        if (unlink($filepath)) {
            setEventMessages("Fichier supprimé avec succès", null, 'mesgs');
        } else {
            setEventMessages("Erreur lors de la suppression du fichier", null, 'errors');
        }
    }
    
    // Redirect back to ficheproduction page
    header('Location: '.dol_buildpath('/ficheproduction/ficheproduction.php?id='.$object->id, 1));
    exit;
}

if ($action == 'test_html_method') {
    // Test action to specifically test HTML method
    header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=builddoc&pdf_method=html');
    exit;
}

if ($action == 'test_tcpdf_method') {
    // Test action to specifically test TCPDF method
    header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=builddoc&pdf_method=tcpdf');
    exit;
}

// If no specific action, redirect back to main page
header('Location: '.dol_buildpath('/ficheproduction/ficheproduction.php?id='.$object->id, 1));
exit;