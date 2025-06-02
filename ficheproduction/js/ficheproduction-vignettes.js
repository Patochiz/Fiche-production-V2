/**
 * FicheProduction v2.0 - Module Vignettes (CORRIGÉ)
 * Gestion unifiée des vignettes produits avec code simplifié
 * CRÉÉ pour résoudre les problèmes de doublons identifiés
 */

(function() {
    'use strict';

    // ============================================================================
    // CONFIGURATION DES VIGNETTES
    // ============================================================================

    const VIGNETTE_DEFAULTS = {
        isInColis: false,
        currentQuantity: 1,
        showQuantityInput: false,
        showRemoveButton: false,
        draggable: true,
        showStatusIndicator: true
    };

    // ============================================================================
    // FONCTIONS DE CRÉATION DE VIGNETTES (SIMPLIFIÉES)
    // ============================================================================

    /**
     * Fonction principale pour créer une vignette produit
     * CORRIGÉE: Logique simplifiée et séparée par type
     */
    function createProductVignette(product, options = {}) {
        // Fusionner les options avec les valeurs par défaut
        const config = Object.assign({}, VIGNETTE_DEFAULTS, options);
        
        // Créer la vignette selon le type de produit
        if (product.isLibre) {
            return createLibreProductVignette(product, config);
        } else {
            return createStandardProductVignette(product, config);
        }
    }

    /**
     * NOUVELLE: Créer une vignette pour produit standard
     */
    function createStandardProductVignette(product, config) {
        const vignetteElement = document.createElement('div');
        
        // Calculer les données nécessaires
        const available = product.total - product.used;
        const percentage = (product.used / product.total) * 100;
        const status = getProductStatus(available, product.used);
        
        // Configurer l'élément
        setupVignetteElement(vignetteElement, product, status, config);
        
        // Générer le contenu HTML
        vignetteElement.innerHTML = generateStandardVignetteHTML(product, config, {
            available,
            percentage,
            status
        });
        
        // Ajouter les événements spécifiques aux produits standards
        if (config.draggable && status !== 'exhausted') {
            setupDragEvents(vignetteElement, product);
        }
        
        return vignetteElement;
    }

    /**
     * NOUVELLE: Créer une vignette pour produit libre
     */
    function createLibreProductVignette(product, config) {
        const vignetteElement = document.createElement('div');
        
        // Configurer l'élément (produits libres ont toujours un statut 'libre')
        setupVignetteElement(vignetteElement, product, 'libre', config);
        
        // Générer le contenu HTML pour produit libre
        vignetteElement.innerHTML = generateLibreVignetteHTML(product, config);
        
        return vignetteElement;
    }

    // ============================================================================
    // FONCTIONS UTILITAIRES (SIMPLIFIÉES)
    // ============================================================================

    /**
     * NOUVELLE: Configuration de base de l'élément vignette
     */
    function setupVignetteElement(element, product, status, config) {
        // Classes de base
        element.className = `product-item ${status}`;
        
        if (config.isInColis) {
            element.classList.add('in-colis');
        }
        
        // Attributs de données
        if (!config.isInColis && config.draggable) {
            element.draggable = true;
            element.dataset.productId = product.id;
        }
    }

    /**
     * NOUVELLE: Déterminer le statut d'un produit
     */
    function getProductStatus(available, used) {
        if (available === 0) return 'exhausted';
        if (used > 0) return 'partial';
        return 'available';
    }

    /**
     * NOUVELLE: Générer le HTML pour produit standard
     */
    function generateStandardVignetteHTML(product, config, data) {
        const { available, percentage, status } = data;
        
        // En-tête du produit
        const headerHTML = `
            <div class="product-header">
                <span class="product-ref">${escapeHtml(product.name)}</span>
                <span class="product-color">${escapeHtml(product.color)}</span>
            </div>
        `;
        
        // Dimensions
        const dimensionsHTML = `
            <div class="product-dimensions">
                L: ${product.length}mm × l: ${product.width}mm 
                ${product.ref_ligne ? `<strong>Réf: ${escapeHtml(product.ref_ligne)}</strong>` : ''}
            </div>
        `;
        
        // Informations de quantité
        const quantityHTML = `
            <div class="quantity-info">
                <span class="quantity-used">${product.used}</span>
                <span>/</span>
                <span class="quantity-total">${product.total}</span>
                <div class="quantity-bar">
                    <div class="quantity-progress" style="width: ${percentage}%"></div>
                </div>
            </div>
        `;
        
        // Input de quantité (si nécessaire)
        const quantityInputHTML = config.showQuantityInput ? `
            <div class="quantity-input-container">
                <span class="quantity-input-label">Qté:</span>
                <input type="number" class="quantity-input" value="${config.currentQuantity}" 
                       min="1" data-product-id="${product.id}">
            </div>
        ` : '';
        
        // Indicateur de statut
        const statusIndicatorHTML = config.showStatusIndicator ? `
            <div class="status-indicator ${getStatusClass(status)}"></div>
        ` : '';
        
        return headerHTML + dimensionsHTML + quantityHTML + quantityInputHTML + statusIndicatorHTML;
    }

    /**
     * NOUVELLE: Générer le HTML pour produit libre
     */
    function generateLibreVignetteHTML(product, config) {
        const headerHTML = `
            <div class="product-header">
                <span class="product-ref">${escapeHtml(product.name)}</span>
                <span class="product-color libre-badge">LIBRE</span>
            </div>
        `;
        
        const dimensionsHTML = `
            <div class="product-dimensions">
                Poids unitaire: ${product.weight}kg
            </div>
        `;
        
        const quantityHTML = `
            <div class="quantity-info">
                <span class="libre-info">📦 Élément libre</span>
            </div>
        `;
        
        const quantityInputHTML = config.showQuantityInput ? `
            <div class="quantity-input-container">
                <span class="quantity-input-label">Qté:</span>
                <input type="number" class="quantity-input" value="${config.currentQuantity}" 
                       min="1" data-product-id="${product.id}">
            </div>
        ` : '';
        
        const statusIndicatorHTML = `
            <div class="status-indicator libre"></div>
        `;
        
        return headerHTML + dimensionsHTML + quantityHTML + quantityInputHTML + statusIndicatorHTML;
    }

    /**
     * NOUVELLE: Obtenir la classe CSS pour l'indicateur de statut
     */
    function getStatusClass(status) {
        switch(status) {
            case 'exhausted': return 'error';
            case 'partial': return 'warning';
            case 'libre': return 'libre';
            default: return '';
        }
    }

    /**
     * NOUVELLE: Configuration des événements de drag & drop
     */
    function setupDragEvents(element, product) {
        element.addEventListener('dragstart', function(e) {
            if (window.FicheProduction && window.FicheProduction.state) {
                FicheProduction.state.setDragging(true);
                FicheProduction.state.setDraggedProduct(product);
            }
            
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'copy';
            
            if (window.debugLog) debugLog(`🚀 Drag start: ${product.name}`);
            
            // Activer les zones de drop après un délai
            setTimeout(() => {
                if (window.FicheProduction && window.FicheProduction.dragdrop && FicheProduction.dragdrop.activateDropZones) {
                    FicheProduction.dragdrop.activateDropZones();
                }
            }, 50);
        });

        element.addEventListener('dragend', function(e) {
            this.classList.remove('dragging');
            if (window.FicheProduction && window.FicheProduction.state) {
                FicheProduction.state.setDragging(false);
                FicheProduction.state.setDraggedProduct(null);
            }
            
            if (window.debugLog) debugLog(`🛑 Drag end: ${product.name}`);
            
            // Désactiver les zones de drop
            if (window.FicheProduction && window.FicheProduction.dragdrop && FicheProduction.dragdrop.deactivateDropZones) {
                FicheProduction.dragdrop.deactivateDropZones();
            }
        });
    }

    /**
     * NOUVELLE: Échapper le HTML pour éviter les injections
     */
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // ============================================================================
    // FONCTIONS DE GESTION DES ÉVÉNEMENTS (NOUVELLES)
    // ============================================================================

    /**
     * NOUVELLE: Ajouter les événements aux inputs de quantité
     */
    function setupQuantityInputEvents(container) {
        const quantityInputs = container.querySelectorAll('.quantity-input');
        quantityInputs.forEach(input => {
            input.addEventListener('change', function(e) {
                const productId = parseInt(e.target.dataset.productId);
                const newQuantity = parseInt(e.target.value);
                
                if (window.FicheProduction && FicheProduction.colis && FicheProduction.colis.updateProductQuantity) {
                    const selectedColis = FicheProduction.data.selectedColis();
                    if (selectedColis) {
                        FicheProduction.colis.updateProductQuantity(selectedColis.id, productId, newQuantity);
                    }
                }
            });
        });
    }

    /**
     * NOUVELLE: Ajouter les événements aux boutons de suppression
     */
    function setupRemoveButtonEvents(container) {
        const removeButtons = container.querySelectorAll('.btn-remove-line');
        removeButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const productId = parseInt(e.target.dataset.productId);
                const selectedColis = window.FicheProduction ? FicheProduction.data.selectedColis() : null;
                
                if (selectedColis && window.FicheProduction && FicheProduction.colis && FicheProduction.colis.removeProductFromColis) {
                    FicheProduction.colis.removeProductFromColis(selectedColis.id, productId);
                }
            });
        });
    }

    // ============================================================================
    // MODULE D'EXPORT
    // ============================================================================

    const VignettesModule = {
        // Fonctions principales
        createProductVignette: createProductVignette,
        createStandardProductVignette: createStandardProductVignette,
        createLibreProductVignette: createLibreProductVignette,
        
        // Fonctions utilitaires
        setupQuantityInputEvents: setupQuantityInputEvents,
        setupRemoveButtonEvents: setupRemoveButtonEvents,
        getProductStatus: getProductStatus,
        
        // Configuration
        defaults: VIGNETTE_DEFAULTS,
        
        // Initialisation
        initialize: function() {
            if (window.debugLog) debugLog('🎨 Module Vignettes initialisé (Version corrigée)');
        }
    };

    // ============================================================================
    // REGISTRATION DU MODULE
    // ============================================================================

    // Enregistrer dans le namespace FicheProduction
    if (window.FicheProduction) {
        window.FicheProduction.vignettes = VignettesModule;
        if (window.debugLog) debugLog('📦 Module Vignettes chargé et intégré (Version corrigée)');
    } else {
        // Fallback si le namespace n'est pas encore disponible
        window.addEventListener('FicheProductionCoreReady', function() {
            window.FicheProduction.vignettes = VignettesModule;
            if (window.debugLog) debugLog('📦 Module Vignettes chargé et intégré (Version corrigée - différé)');
        });
    }

    // Export global pour compatibilité
    window.createProductVignette = createProductVignette;

})();