<?php
/* Copyright (C) 2025 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       class/ficheproductionhtmltopdf.class.php
 * \ingroup    ficheproduction
 * \brief      Class to generate production sheet PDF from HTML template - CHEMIN CORRIGÉ
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

// Load FicheProduction classes
require_once dol_buildpath('/ficheproduction/class/ficheproductionmanager.class.php');

// Try to load DOMPDF (if available)
if (file_exists(DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php')) {
    // Fallback to TCPDF if DOMPDF not available
    require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
} elseif (class_exists('TCPDF')) {
    // TCPDF already loaded
} else {
    // Try to find alternative PDF libraries
    if (file_exists('/usr/share/php/dompdf/autoload.inc.php')) {
        require_once '/usr/share/php/dompdf/autoload.inc.php';
    }
}

/**
 * Class to generate production sheet PDF from HTML template
 */
class FicheProductionHTMLToPDF
{
    /**
     * @var DoliDB Database handler
     */
    public $db;
    
    /**
     * @var string Module name
     */
    public $name = 'ficheproduction_html';
    
    /**
     * @var string Module description
     */
    public $description = 'Production sheet PDF generator from HTML';
    
    /**
     * @var string Document type
     */
    public $type = 'pdf';
    
    /**
     * @var string Format
     */
    public $format = 'A4';
    
    /**
     * @var string Error message
     */
    public $error = '';
    
    /**
     * @var array Supported formats
     */
    public $phpmin = array(5, 6);
    
    /**
     * @var string Template path
     */
    private $templatePath;
    
    /**
     * @var bool Debug mode
     */
    private $debug = false;
    
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->debug = getDolGlobalString('FICHEPRODUCTION_DEBUG', false);
        
        // FORCER le chemin du template trouvé
        $this->templatePath = '/home/diamanti/www/doli/custom/ficheproduction/templates/fiche_production_template.html';
        
        // Vérifier que le template forcé existe
        if (file_exists($this->templatePath) && is_readable($this->templatePath)) {
            if ($this->debug) {
                dol_syslog("FicheProductionHTMLToPDF: Template FORCÉ trouvé et accessible: ".$this->templatePath, LOG_DEBUG);
            }
        } else {
            if ($this->debug) {
                dol_syslog("FicheProductionHTMLToPDF: Template FORCÉ non accessible, recherche alternative", LOG_WARNING);
            }
            
            // Fallback: recherche alternative avec les chemins incluant le bon répertoire
            $possiblePaths = array(
                // Votre chemin trouvé en priorité
                '/home/diamanti/www/doli/custom/ficheproduction/templates/fiche_production_template.html',
                
                // Variations possibles
                '/home/diamanti/www/doli/custom/ficheproduction/templates/fiche-production-pdf.html',
                
                // Chemins avec dol_buildpath
                dol_buildpath('/ficheproduction/templates/fiche_production_template.html'),
                dol_buildpath('/custom/ficheproduction/templates/fiche_production_template.html'),
                dol_buildpath('/ficheproduction/templates/fiche-production-pdf.html'),
                dol_buildpath('/custom/ficheproduction/templates/fiche-production-pdf.html'),
                
                // Chemins directs avec DOL_DOCUMENT_ROOT
                DOL_DOCUMENT_ROOT.'/custom/ficheproduction/templates/fiche_production_template.html',
                DOL_DOCUMENT_ROOT.'/custom/ficheproduction/templates/fiche-production-pdf.html',
                
                // Chemins relatifs au fichier de classe
                dirname(__FILE__).'/../templates/fiche_production_template.html',
                dirname(__FILE__).'/../templates/fiche-production-pdf.html'
            );
            
            $this->templatePath = '';
            foreach ($possiblePaths as $path) {
                if (file_exists($path) && is_readable($path)) {
                    $content = file_get_contents($path);
                    if ($content !== false && strlen($content) > 1000) { // Template should be substantial
                        $this->templatePath = $path;
                        if ($this->debug) {
                            dol_syslog("FicheProductionHTMLToPDF: Template alternatif trouvé: ".$path, LOG_DEBUG);
                        }
                        break;
                    }
                }
            }
            
            if (empty($this->templatePath) && $this->debug) {
                dol_syslog("FicheProductionHTMLToPDF: Aucun template trouvé, utilisation du template embarqué", LOG_WARNING);
            }
        }
    }
    
    /**
     * Get template path for debugging
     * @return string Template path or empty string
     */
    public function getTemplatePath()
    {
        return $this->templatePath ?: '';
    }
    
    /**
     * Generate production sheet PDF from HTML template
     *
     * @param Commande $object Order object
     * @param Translate $outputlangs Language object
     * @param string $srctemplatepath Template path
     * @param int $hidedetails Hide details
     * @param int $hidedesc Hide description
     * @param int $hideref Hide reference
     * @return int <0 if error, >0 if success
     */
    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $user, $langs, $conf, $mysoc, $db, $hookmanager;
        
        try {
            if (!is_object($outputlangs)) $outputlangs = $langs;
            
            // Load translations
            $outputlangs->loadLangs(array("main", "dict", "companies", "bills", "products", "orders"));
            $outputlangs->load('ficheproduction@ficheproduction');
            
            // Validate object
            if ($object->id <= 0) {
                $this->error = "Invalid object ID";
                return -1;
            }
            
            // Load object data
            $object->fetch_thirdparty();
            $object->fetch_lines();
            $object->fetch_optionals();
            
            if ($this->debug) {
                dol_syslog("FicheProductionHTMLToPDF: Starting PDF generation for order ".$object->ref, LOG_DEBUG);
                dol_syslog("FicheProductionHTMLToPDF: Template path: ".($this->templatePath ?: 'EMBEDDED'), LOG_DEBUG);
            }
            
            // Get production data using the same manager as the interface
            $manager = new FicheProductionManager($this->db);
            $productionData = $manager->loadColisageData($object->id);
            
            if ($this->debug && $productionData['success']) {
                dol_syslog("FicheProductionHTMLToPDF: Debug production data structure:", LOG_DEBUG);
                foreach ($productionData['colis'] as $i => $colis) {
                    dol_syslog("  Colis $i: ".count($colis['products'])." products", LOG_DEBUG);
                    foreach ($colis['products'] as $j => $prod) {
                        $isLibre = isset($prod['isLibre']) ? ($prod['isLibre'] ? 'LIBRE' : 'STD') : 'UNK';
                        $prodId = isset($prod['productId']) ? $prod['productId'] : 'NO_ID';
                        dol_syslog("    Product $j: ID=$prodId, Type=$isLibre", LOG_DEBUG);
                    }
                }
            }

            if ($this->debug) {
                dol_syslog("FicheProductionHTMLToPDF: Production data success: ".($productionData['success'] ? 'YES' : 'NO'), LOG_DEBUG);
                if ($productionData['success']) {
                    dol_syslog("FicheProductionHTMLToPDF: Colis count: ".count($productionData['colis']), LOG_DEBUG);
                }
            }
            
            if (!$productionData['success']) {
                dol_syslog("No production data found for order ".$object->id, LOG_WARNING);
                // Continue anyway, will show empty production sheet
                $productionData = array('success' => true, 'colis' => array(), 'products' => array());
            }
            
            // Setup file paths
            $dir = $conf->commande->multidir_output[$object->entity];
            $orderDir = $dir . "/" . $object->ref;
            $filename = $object->ref."-fiche-production.pdf";
            $file = $orderDir . "/" . $filename;
            
            // Create directory if it doesn't exist
            if (!file_exists($orderDir)) {
                if (dol_mkdir($orderDir) < 0) {
                    $this->error = $langs->transnoentities("ErrorCanNotCreateDir", $orderDir);
                    return -1;
                }
            }
            
            // Verify directory is writable
            if (!is_writable($orderDir)) {
                $this->error = "Directory not writable: " . $orderDir;
                return -1;
            }
            
            // Generate HTML content
            $htmlContent = $this->generateHTMLContent($object, $productionData, $outputlangs);
            
            if (empty($htmlContent)) {
                $this->error = "Failed to generate HTML content";
                return -1;
            }
            
            if ($this->debug) {
                // Save HTML content for debugging
                $htmlDebugFile = $orderDir . "/" . $object->ref . "-debug.html";
                file_put_contents($htmlDebugFile, $htmlContent);
                dol_syslog("FicheProductionHTMLToPDF: HTML debug saved to ".$htmlDebugFile, LOG_DEBUG);
            }
            
            // Convert HTML to PDF
            $result = $this->convertHTMLToPDF($htmlContent, $file);
            
            if ($result <= 0) {
                return $result;
            }
            
            // Set permissions
            if (!empty($conf->global->MAIN_UMASK)) {
                @chmod($file, octdec($conf->global->MAIN_UMASK));
            }
            
            dol_syslog("FicheProduction HTML PDF: File created successfully at " . $file, LOG_INFO);
            
            return 1;
            
        } catch (Exception $e) {
            $this->error = "Exception in write_file: " . $e->getMessage();
            dol_syslog("FicheProductionHTMLToPDF Error: " . $e->getMessage(), LOG_ERR);
            return -1;
        }
    }
    
    /**
     * Generate HTML content from template and data
     *
     * @param Commande $object Order object
     * @param array $productionData Production data
     * @param Translate $outputlangs Language object
     * @return string HTML content
     */
    private function generateHTMLContent($object, $productionData, $outputlangs)
    {
        try {
            // Load template
            $templateContent = $this->loadTemplate();
            if (empty($templateContent)) {
                if ($this->debug) {
                    dol_syslog("FicheProductionHTMLToPDF: No template found, using embedded template", LOG_WARNING);
                }
                // Use embedded template as fallback
                $templateContent = $this->getEmbeddedTemplate();
            }
            
            if ($this->debug) {
                dol_syslog("FicheProductionHTMLToPDF: Template size: ".strlen($templateContent)." chars", LOG_DEBUG);
            }
            
            // Prepare data for replacement
            $replacements = $this->prepareReplacements($object, $productionData, $outputlangs);
            
            if ($this->debug) {
                dol_syslog("FicheProductionHTMLToPDF: Replacements count: ".count($replacements), LOG_DEBUG);
                dol_syslog("FicheProductionHTMLToPDF: Sample replacements: COMMANDE_REF=".$replacements['{{COMMANDE_REF}}'].", TOTAL_COLIS=".$replacements['{{TOTAL_COLIS}}'], LOG_DEBUG);
            }
            
            // Replace placeholders in template
            $html = $templateContent;
            foreach ($replacements as $placeholder => $value) {
                $html = str_replace($placeholder, $value, $html);
            }
            
            // Verify that replacements worked
            if ($this->debug) {
                $remainingPlaceholders = preg_match_all('/\{\{[A-Z_]+\}\}/', $html);
                dol_syslog("FicheProductionHTMLToPDF: HTML generated, size: ".strlen($html).", remaining placeholders: ".$remainingPlaceholders, LOG_DEBUG);
            }
            
            return $html;
            
        } catch (Exception $e) {
            $this->error = "Error generating HTML: " . $e->getMessage();
            dol_syslog("FicheProductionHTMLToPDF: " . $e->getMessage(), LOG_ERR);
            return '';
        }
    }
    
    /**
     * Load HTML template
     *
     * @return string Template content
     */
    private function loadTemplate()
    {
        // If template path was found in constructor, use it
        if (!empty($this->templatePath) && file_exists($this->templatePath) && is_readable($this->templatePath)) {
            $content = file_get_contents($this->templatePath);
            if ($content !== false) {
                if ($this->debug) {
                    dol_syslog("FicheProductionHTMLToPDF: Template loaded successfully from ".$this->templatePath, LOG_DEBUG);
                }
                return $content;
            } else {
                if ($this->debug) {
                    dol_syslog("FicheProductionHTMLToPDF: Failed to read template from ".$this->templatePath, LOG_ERROR);
                }
            }
        }
        
        if ($this->debug) {
            dol_syslog("FicheProductionHTMLToPDF: No template file available, using embedded template", LOG_WARNING);
        }
        
        return ''; // Will trigger embedded template usage
    }
    
    /**
     * Get embedded template as fallback - COMPATIBLE TCPDF (utilise des tables)
     *
     * @return string Embedded template
     */
    private function getEmbeddedTemplate()
    {
        // Template compatible TCPDF (utilise des tables au lieu de flexbox)
        return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche de Production - {{COMMANDE_REF}}</title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; line-height: 1.2; font-size: 12px; color: #333; }
        
        /* Header */
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #333; padding-bottom: 8px; }
        .header h1 { font-size: 18px; font-weight: bold; margin: 0; color: #333; }
        .header-table { width: 100%; }
        .header-table td { vertical-align: middle; }
        .status { font-size: 14px; font-weight: bold; color: #28a745; text-align: right; }
        
        /* Summary section using table layout */
        .summary-table { width: 100%; margin-bottom: 15px; }
        .summary-table td { vertical-align: top; padding: 0 5px; }
        
        .delivery-box { background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; padding: 8px; margin-bottom: 8px; }
        .delivery-box h4 { margin: 0 0 6px 0; font-size: 12px; color: #333; }
        .delivery-box p { margin: 0; font-size: 10px; line-height: 1.4; }
        
        .instructions-box { background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 8px; }
        .instructions-box h4 { margin: 0 0 6px 0; font-size: 12px; color: #333; }
        .instructions-box p { margin: 0; font-size: 10px; line-height: 1.4; }
        
        .info-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .info-table tr { border-bottom: 1px solid #ddd; }
        .info-label { padding: 6px 8px; background-color: #f8f9fa; font-weight: bold; width: 35%; border-right: 1px solid #ddd; }
        .info-value { padding: 6px 8px; }
        
        /* Main content using table layout */
        .main-table { width: 100%; margin-bottom: 15px; }
        .main-table td { vertical-align: top; padding: 0 5px; }
        
        .section-title { font-size: 12px; font-weight: bold; color: #333; margin-bottom: 8px; border-bottom: 1px solid #ddd; padding-bottom: 2px; }
        
        .product-group { margin-bottom: 8px; border: 1px solid #ddd; border-radius: 3px; overflow: hidden; }
        .product-group-header { background-color: #e9ecef; padding: 4px 8px; font-weight: bold; font-size: 11px; color: #495057; }
        .product-details { padding: 4px 8px; background-color: #fff; }
        .product-line { margin: 1px 0; font-size: 10px; color: #555; padding-left: 12px; }
        
        .colis-list { background-color: #fff; border: 1px solid #ddd; border-radius: 3px; }
        .colis-item { padding: 4px 8px; border-bottom: 1px solid #eee; }
        .colis-item:last-child { border-bottom: none; }
        .colis-header { font-weight: bold; color: #495057; margin-bottom: 3px; font-size: 11px; }
        .colis-content { color: #666; }
        .colis-product-line { margin: 1px 0; padding-left: 12px; font-size: 9px; }
        
        /* Totals */
        .totals-section { margin: 15px 0; background-color: #f8f9fa; border: 2px solid #28a745; border-radius: 4px; padding: 10px; text-align: center; }
        .total-item { font-size: 14px; font-weight: bold; color: #155724; margin: 5px; }
        
        /* Verification using table layout */
        .verification-section { margin-top: 15px; border-top: 2px solid #333; padding-top: 10px; }
        .verification-title { font-size: 13px; font-weight: bold; color: #333; margin-bottom: 8px; text-align: center; }
        .verification-table { width: 100%; border-collapse: collapse; }
        .verification-column { width: 33.33%; vertical-align: top; border: 1px solid #333; padding: 8px; }
        .verification-column h4 { margin: 0 0 6px 0; font-size: 11px; color: #333; text-align: center; }
        
        .checkbox-item { margin: 3px 0; font-size: 10px; }
        .checkbox { display: inline-block; width: 12px; height: 12px; border: 1px solid #333; margin-right: 6px; }
        
        .signature-line { border-bottom: 1px solid #333; min-height: 15px; margin: 4px 0; width: 100%; }
        .signature-item { margin: 6px 0; font-size: 9px; }
        .signature-item strong { font-size: 10px; }
        
        .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #666; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 70%;"><h1>FICHE DE PRODUCTION {{COMMANDE_REF}}</h1></td>
                <td class="status">STATUT: {{COMMANDE_STATUS}}</td>
            </tr>
        </table>
    </div>
    
    <!-- Summary section -->
    <table class="summary-table">
        <tr>
            <td style="width: 38%;">
                <div class="delivery-box">
                    <h4>Adresse de livraison</h4>
                    <p>{{DELIVERY_INFO}}</p>
                </div>
                <div class="instructions-box">
                    <h4>Instructions</h4>
                    <p>{{INSTRUCTIONS}}</p>
                </div>
            </td>
            <td style="width: 62%;">
                <table class="info-table">
                    <tr><td class="info-label">Date :</td><td class="info-value">{{DATE_GENERATION}}</td></tr>
                    <tr><td class="info-label">Client :</td><td class="info-value">{{CLIENT_NAME}}</td></tr>
                    <tr><td class="info-label">Réf. Chantier :</td><td class="info-value">{{REF_CHANTIER}}</td></tr>
                    <tr><td class="info-label">Commentaires :</td><td class="info-value">{{COMMENTAIRES}}</td></tr>
                </table>
            </td>
        </tr>
    </table>
    
    <!-- Main content -->
    <table class="main-table">
        <tr>
            <td style="width: 38%;">
                <div class="section-title">📦 INVENTAIRE PRODUITS</div>
                {{INVENTORY_PRODUCTS}}
            </td>
            <td style="width: 62%;">
                <div class="section-title">📋 LISTE DES COLIS PRÉPARÉS</div>
                <div class="colis-list">
                    {{COLIS_LIST}}
                </div>
            </td>
        </tr>
    </table>
    
    <!-- Totals -->
    <div class="totals-section">
        <div class="total-item">TOTAL COLIS PRÉPARÉS : {{TOTAL_COLIS}} colis</div>
        <div class="total-item">POIDS TOTAL : {{TOTAL_WEIGHT}} kg</div>
    </div>
    
    <!-- Verification -->
    <div class="verification-section">
        <div class="verification-title">✅ CONTRÔLES DE PRODUCTION ET SIGNATURES</div>
        <table class="verification-table">
            <tr>
                <td class="verification-column">
                    <h4>COLISAGE FINAL</h4>
                    <div class="checkbox-item"><span class="checkbox"></span> _____ Palettes = _____ Colis</div>
                    <div class="checkbox-item"><span class="checkbox"></span> _____ Fagots = _____ Colis</div>
                    <div class="checkbox-item"><span class="checkbox"></span> _____ Colis vrac</div>
                    <div style="margin-top: 8px; font-weight: bold; font-size: 11px; text-align: center;">TOTAL: _____ COLIS</div>
                </td>
                <td class="verification-column">
                    <h4>CONTRÔLES QUALITÉ</h4>
                    <div class="checkbox-item"><span class="checkbox"></span> Dimensions conformes</div>
                    <div class="checkbox-item"><span class="checkbox"></span> Couleurs conformes</div>
                    <div class="checkbox-item"><span class="checkbox"></span> Quantités vérifiées</div>
                    <div class="checkbox-item"><span class="checkbox"></span> Étiquetage complet</div>
                    <div class="checkbox-item"><span class="checkbox"></span> Emballage conforme</div>
                </td>
                <td class="verification-column">
                    <h4>RESPONSABLES</h4>
                    <div class="signature-item"><strong>Production:</strong><br><div class="signature-line"></div></div>
                    <div class="signature-item"><strong>Contrôle:</strong><br><div class="signature-line"></div></div>
                    <div class="signature-item"><strong>Expédition:</strong><br><div class="signature-line"></div></div>
                    <div class="signature-item"><strong>Bobines ID:</strong> __________</div>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="footer">
        <p>Fiche générée le {{DATE_GENERATION}} à {{TIME_GENERATION}} | Total: {{TOTAL_PIECES}} pcs commandées | Document confidentiel</p>
    </div>
</body>
</html>';
    }
    
    /**
     * Prepare replacement data for template
     *
     * @param Commande $object Order object
     * @param array $productionData Production data
     * @param Translate $outputlangs Language object
     * @return array Replacements array
     */
    private function prepareReplacements($object, $productionData, $outputlangs)
    {
        $replacements = array();
        
        // Basic order info
        $replacements['{{COMMANDE_REF}}'] = $object->ref;
        $replacements['{{CLIENT_NAME}}'] = $object->thirdparty->name;
        $replacements['{{DATE_GENERATION}}'] = date('d/m/Y');
        $replacements['{{TIME_GENERATION}}'] = date('H:i');
        
        // Order status
        $statusText = 'EN COURS';
        switch ($object->statut) {
            case Commande::STATUS_DRAFT:
                $statusText = 'BROUILLON';
                break;
            case Commande::STATUS_VALIDATED:
            case Commande::STATUS_SHIPMENTONPROCESS:
                $statusText = 'EN COURS';
                break;
            case Commande::STATUS_CLOSED:
                $statusText = 'TERMINE';
                break;
            case Commande::STATUS_CANCELED:
                $statusText = 'ANNULE';
                break;
        }
        $replacements['{{COMMANDE_STATUS}}'] = $statusText;
        
        // Delivery info
        $replacements['{{DELIVERY_INFO}}'] = $this->getDeliveryInfo($object, $outputlangs);
        $replacements['{{INSTRUCTIONS}}'] = $this->getInstructions($object);
        
        // Project reference
        $refChantier = 'Non défini';
        if (!empty($object->array_options['options_ref_chantierfp'])) {
            $refChantier = $object->array_options['options_ref_chantierfp'];
        } elseif (!empty($object->array_options['options_ref_chantier'])) {
            $refChantier = $object->array_options['options_ref_chantier'];
        }
        $replacements['{{REF_CHANTIER}}'] = $refChantier;
        
        // Comments
        $commentaires = 'Aucun commentaire';
        if (!empty($object->array_options['options_commentaires_fp'])) {
            $commentaires = strip_tags($object->array_options['options_commentaires_fp']);
        }
        $replacements['{{COMMENTAIRES}}'] = $commentaires;
        
        // Inventory products
        $replacements['{{INVENTORY_PRODUCTS}}'] = $this->generateInventoryHTML($object);
        
        // Colis list
        $replacements['{{COLIS_LIST}}'] = $this->generateColisHTML($productionData);
        
        // Totals
        $totalColis = count($productionData['colis'] ?? array());
        $totalWeight = 0;
        $totalMultiple = 0;
        
        if (!empty($productionData['colis'])) {
            foreach ($productionData['colis'] as $colis) {
                $multiple = isset($colis['multiple']) ? intval($colis['multiple']) : 1;
                $weight = isset($colis['totalWeight']) ? floatval($colis['totalWeight']) : 0;
                $totalMultiple += $multiple;
                $totalWeight += $weight * $multiple;
            }
        }
        
        $replacements['{{TOTAL_COLIS}}'] = $totalMultiple > 0 ? $totalMultiple : $totalColis;
        $replacements['{{TOTAL_WEIGHT}}'] = number_format($totalWeight, 1);
        
        // Total pieces
        $totalPieces = 0;
        if (!empty($object->lines)) {
            foreach ($object->lines as $line) {
                if ($line->fk_product > 0) {
                    $quantity = isset($line->array_options['options_nombre']) && !empty($line->array_options['options_nombre']) 
                        ? intval($line->array_options['options_nombre'])
                        : intval($line->qty);
                    $totalPieces += $quantity;
                }
            }
        }
        $replacements['{{TOTAL_PIECES}}'] = $totalPieces;
        
        return $replacements;
    }
    
    /**
     * Get delivery information
     *
     * @param Commande $object Order object
     * @param Translate $outputlangs Language object
     * @return string Delivery info HTML
     */
    private function getDeliveryInfo($object, $outputlangs)
    {
        $deliveryInfo = '';
        
        // Get delivery contacts
        $contacts = $object->liste_contact(-1, 'external', 0, 'SHIPPING');
        if (is_array($contacts) && count($contacts) > 0) {
            foreach ($contacts as $contact) {
                $contactstatic = new Contact($this->db);
                if ($contactstatic->fetch($contact['id']) > 0) {
                    $deliveryInfo = '<strong>'.$contactstatic->getFullName($outputlangs).'</strong><br>';
                    $deliveryInfo .= nl2br($contactstatic->address).'<br>';
                    $deliveryInfo .= $contactstatic->zip.' '.$contactstatic->town.'<br>';
                    
                    if (!empty($contactstatic->phone_pro)) {
                        $deliveryInfo .= 'Tél: '.$contactstatic->phone_pro;
                    }
                    if (!empty($contactstatic->phone_mobile)) {
                        $deliveryInfo .= (!empty($contactstatic->phone_pro) ? ' / ' : 'Tél: ').$contactstatic->phone_mobile;
                    }
                    if (!empty($contactstatic->phone_pro) || !empty($contactstatic->phone_mobile)) {
                        $deliveryInfo .= '<br>';
                    }
                    
                    if (!empty($contactstatic->email)) {
                        $deliveryInfo .= 'Email: '.$contactstatic->email;
                    }
                }
                break;
            }
        }
        
        // Fallback to thirdparty address
        if (empty($deliveryInfo)) {
            $deliveryInfo = '<strong>'.$object->thirdparty->name.'</strong><br>';
            $deliveryInfo .= nl2br($object->thirdparty->address).'<br>';
            $deliveryInfo .= $object->thirdparty->zip.' '.$object->thirdparty->town;
            if (!empty($object->thirdparty->phone)) {
                $deliveryInfo .= '<br>Tél: '.$object->thirdparty->phone;
            }
            if (!empty($object->thirdparty->email)) {
                $deliveryInfo .= '<br>Email: '.$object->thirdparty->email;
            }
        }
        
        return $deliveryInfo;
    }
    
    /**
     * Get instructions
     *
     * @param Commande $object Order object
     * @return string Instructions
     */
    private function getInstructions($object)
    {
        if (!empty($object->thirdparty->note_public)) {
            return nl2br(strip_tags($object->thirdparty->note_public));
        }
        return 'Aucune instruction particulière';
    }
    
    /**
     * Generate inventory HTML
     *
     * @param Commande $object Order object
     * @return string Inventory HTML
     */
    private function generateInventoryHTML($object)
    {
        $html = '';
        $productGroups = array();
        
        if (!empty($object->lines)) {
            foreach ($object->lines as $line) {
                if ($line->fk_product > 0) {
                    $product = new Product($this->db);
                    if ($product->fetch($line->fk_product) > 0 && $product->type == 0) {
                        
                        // Get quantity from extrafield nombre
                        $quantity = isset($line->array_options['options_nombre']) && !empty($line->array_options['options_nombre']) 
                            ? intval($line->array_options['options_nombre'])
                            : intval($line->qty);
                        
                        if ($quantity > 0) {
                            // Get dimensions and color
                            $length = $this->getExtraFieldValue($line, array('length', 'longueur', 'long'), 1000);
                            $width = $this->getExtraFieldValue($line, array('width', 'largeur', 'larg'), 100);
                            $color = $this->getExtraFieldValue($line, array('color', 'couleur'), 'Standard');
                            
                            $groupKey = $product->label.' - '.$color;
                            
                            if (!isset($productGroups[$groupKey])) {
                                $productGroups[$groupKey] = array(
                                    'header' => $groupKey.' ('.$quantity.' pcs commandées)',
                                    'details' => array()
                                );
                            }
                            
                            $productGroups[$groupKey]['details'][] = $length.'mm × '.$width.'mm ('.$quantity.' pcs)';
                        }
                    }
                }
            }
        }
        
        foreach ($productGroups as $group) {
            $html .= '<div class="product-group">';
            $html .= '<div class="product-group-header">'.$group['header'].'</div>';
            $html .= '<div class="product-details">';
            foreach ($group['details'] as $detail) {
                $html .= '<div class="product-line">• '.$detail.'</div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        
        if (empty($html)) {
            $html = '<div class="product-group"><div class="product-details">Aucun produit commandé</div></div>';
        }
        
        return $html;
    }
    
/**
 * Generate colis HTML - VERSION CORRIGÉE
 *
 * @param array $productionData Production data
 * @return string Colis HTML
 */
private function generateColisHTML($productionData)
{
    $html = '';
    
    if (!empty($productionData['colis'])) {
        foreach ($productionData['colis'] as $index => $colis) {
            $multiple = isset($colis['multiple']) ? intval($colis['multiple']) : 1;
            $number = isset($colis['number']) ? $colis['number'] : ($index + 1);
            $weight = isset($colis['totalWeight']) ? floatval($colis['totalWeight']) : 0;
            $isLibre = isset($colis['isLibre']) && $colis['isLibre'];
            
            $html .= '<div class="colis-item">';
            $html .= '<div class="colis-header">';
            if ($isLibre) {
                $html .= $multiple.' colis libre n°'.$number.' ('.number_format($weight, 1).' Kg/colis)';
            } else {
                $html .= $multiple.' colis n°'.$number.' ('.number_format($weight, 1).' Kg/colis)';
            }
            $html .= '</div>';
            $html .= '<div class="colis-content">';
            
            if (!empty($colis['products'])) {
                foreach ($colis['products'] as $productData) {
                    $quantity = isset($productData['quantity']) ? intval($productData['quantity']) : 0;
                    
                    if (isset($productData['isLibre']) && $productData['isLibre']) {
                        // Produit libre - utiliser les données sauvegardées
                        $productName = isset($productData['name']) ? $productData['name'] : 'Produit libre';
                        $weight = isset($productData['weight']) ? floatval($productData['weight']) : 0;
                        $html .= '<div class="colis-product-line">• '.$productName.' ('.$quantity.' pcs - '.number_format($weight, 1).'kg/pc)</div>';
                    } else {
                        // ✅ CORRECTION : Récupérer les vraies infos produit
                        $productInfo = $this->getProductInfoForPDF($productData);
                        $html .= '<div class="colis-product-line">• '.$productInfo['display'].' ('.$quantity.' pcs)</div>';
                        
                        if ($this->debug) {
                            dol_syslog("FicheProductionHTMLToPDF: Product resolved - ID:".$productData['productId']." -> ".$productInfo['display'], LOG_DEBUG);
                        }
                    }
                }
            } else {
                $html .= '<div class="colis-product-line" style="font-style: italic; color: #999;">Colis vide</div>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
        }
    } else {
        $html = '<div class="colis-item"><div class="colis-content">Aucun colis préparé</div></div>';
    }
    
    return $html;
}
    
    /**
     * Get extrafield value with fallback options
     *
     * @param object $line Line object
     * @param array $fieldNames Field names to try
     * @param mixed $defaultValue Default value
     * @return mixed Field value or default
     */
    private function getExtraFieldValue($line, $fieldNames, $defaultValue)
    {
        if (isset($line->array_options) && is_array($line->array_options)) {
            foreach ($fieldNames as $fieldName) {
                $optionKey = 'options_'.$fieldName;
                if (isset($line->array_options[$optionKey]) && !empty($line->array_options[$optionKey])) {
                    return $line->array_options[$optionKey];
                }
            }
        }
        return $defaultValue;
    }
    
    /**
     * Convert HTML to PDF
     *
     * @param string $html HTML content
     * @param string $outputFile Output file path
     * @return int <0 if error, >0 if success
     */
    private function convertHTMLToPDF($html, $outputFile)
    {
        try {
            // Try DOMPDF first (if available)
            if (class_exists('Dompdf\Dompdf')) {
                return $this->convertWithDOMPDF($html, $outputFile);
            }
            
            // Fallback to TCPDF
            return $this->convertWithTCPDF($html, $outputFile);
            
        } catch (Exception $e) {
            $this->error = "PDF conversion error: " . $e->getMessage();
            dol_syslog("FicheProductionHTMLToPDF convertHTMLToPDF: " . $e->getMessage(), LOG_ERR);
            return -1;
        }
    }
    
/**
 * ✅ VERSION SIMPLIFIÉE : Uniquement recherche dans commandedet
 */
private function getProductInfoForPDF($productData)
{
    $productId = isset($productData['productId']) ? intval($productData['productId']) : 0;
    
    if ($productId <= 0) {
        return array(
            'display' => isset($productData['name']) ? $productData['name'] : 'Produit non identifié',
            'ref' => '',
            'weight' => 0
        );
    }
    
    if ($this->debug) {
        dol_syslog("FicheProductionHTMLToPDF: Recherche produit pour ligne commande ID: ".$productId, LOG_DEBUG);
    }
    
    // ✅ SEULE STRATÉGIE : Chercher dans les lignes de commande
    $sql = "SELECT cd.fk_product, cd.qty, p.ref, p.label, p.weight, cd.array_options
            FROM ".MAIN_DB_PREFIX."commandedet cd
            LEFT JOIN ".MAIN_DB_PREFIX."product p ON cd.fk_product = p.rowid
            WHERE cd.rowid = ".intval($productId);
    
    $resql = $this->db->query($sql);
    if ($resql && $this->db->num_rows($resql) > 0) {
        $obj = $this->db->fetch_object($resql);
        
        if ($this->debug) {
            dol_syslog("FicheProductionHTMLToPDF: Produit trouvé - ligne:".$productId." -> produit:".$obj->fk_product." -> ".$obj->label, LOG_DEBUG);
        }
        
        $displayName = !empty($obj->label) ? $obj->label : $obj->ref;
        
        // Ajouter des infos supplémentaires des extrafields
        $extraInfo = '';
        if (!empty($obj->array_options)) {
            $arrayOptions = json_decode($obj->array_options, true);
            if (is_array($arrayOptions)) {
                // Couleur
                $color = '';
                foreach (array('options_color', 'options_couleur') as $colorField) {
                    if (isset($arrayOptions[$colorField]) && !empty($arrayOptions[$colorField])) {
                        $color = $arrayOptions[$colorField];
                        break;
                    }
                }
                
                // Dimensions
                $length = '';
                $width = '';
                foreach (array('options_length', 'options_longueur', 'options_long') as $lengthField) {
                    if (isset($arrayOptions[$lengthField]) && !empty($arrayOptions[$lengthField])) {
                        $length = $arrayOptions[$lengthField];
                        break;
                    }
                }
                foreach (array('options_width', 'options_largeur', 'options_larg') as $widthField) {
                    if (isset($arrayOptions[$widthField]) && !empty($arrayOptions[$widthField])) {
                        $width = $arrayOptions[$widthField];
                        break;
                    }
                }
                
                // Construire les infos supplémentaires
                if (!empty($color)) {
                    $extraInfo .= ' - '.$color;
                }
                if (!empty($length) && !empty($width)) {
                    $extraInfo .= ' ('.$length.'×'.$width.')';
                }
            }
        }
        
        return array(
            'display' => $displayName . $extraInfo,
            'ref' => $obj->ref,
            'weight' => $obj->weight
        );
    }
    
    if ($this->debug) {
        dol_syslog("FicheProductionHTMLToPDF: Ligne de commande non trouvée pour ID: ".$productId, LOG_WARNING);
    }
    
    // ✅ FALLBACK si la ligne de commande n'existe pas
    return array(
        'display' => isset($productData['name']) ? $productData['name'] : 'Ligne commande #'.$productId.' (non trouvée)',
        'ref' => '',
        'weight' => isset($productData['weight']) ? $productData['weight'] : 0
    );
}

    /**
     * Convert HTML to PDF using DOMPDF
     *
     * @param string $html HTML content
     * @param string $outputFile Output file path
     * @return int <0 if error, >0 if success
     */
    private function convertWithDOMPDF($html, $outputFile)
    {
        try {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            $output = $dompdf->output();
            file_put_contents($outputFile, $output);
            
            if ($this->debug) {
                dol_syslog("FicheProductionHTMLToPDF: PDF generated with DOMPDF", LOG_DEBUG);
            }
            
            return 1;
            
        } catch (Exception $e) {
            $this->error = "DOMPDF error: " . $e->getMessage();
            dol_syslog("FicheProductionHTMLToPDF DOMPDF: " . $e->getMessage(), LOG_ERR);
            return -1;
        }
    }
    
    /**
     * Convert HTML to PDF using TCPDF
     *
     * @param string $html HTML content
     * @param string $outputFile Output file path
     * @return int <0 if error, >0 if success
     */
    private function convertWithTCPDF($html, $outputFile)
    {
        try {
            // Initialize TCPDF
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Dolibarr FicheProduction');
            $pdf->SetAuthor('Dolibarr');
            $pdf->SetTitle('Fiche de Production');
            
            // Remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Set margins
            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(true, 10);
            
            // Add page
            $pdf->AddPage();
            
            // Write HTML
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Output to file
            $pdf->Output($outputFile, 'F');
            
            if ($this->debug) {
                dol_syslog("FicheProductionHTMLToPDF: PDF generated with TCPDF", LOG_DEBUG);
            }
            
            return 1;
            
        } catch (Exception $e) {
            $this->error = "TCPDF error: " . $e->getMessage();
            dol_syslog("FicheProductionHTMLToPDF TCPDF: " . $e->getMessage(), LOG_ERR);
            return -1;
        }
    }
}
