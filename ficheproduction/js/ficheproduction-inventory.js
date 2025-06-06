/**
 * FicheProduction v2.0 - Module Inventory (Version Corrigée - Inputs Fonctionnels)
 * Gestion de l'inventaire des produits avec enregistrement amélioré
 */

// Attendre que FicheProduction soit disponible
(function() {
    'use strict';

    // ============================================================================
    // GESTION DE L'INVENTAIRE
    // ============================================================================

    /**
     * Créer une vignette produit (VERSION CORRIGÉE - Inputs Fonctionnels)
     * @param {Object} product - Données du produit
     * @param {boolean} isInColis - Si le produit est affiché dans un colis
     * @param {number} currentQuantity - Quantité actuelle pour les produits dans les colis
     * @returns {HTMLElement} - Élément DOM de la vignette
     */
    function createProductVignette(product, isInColis = false, currentQuantity = 1) {
        // Gestion des produits libres (pas de contraintes de stock)
        if (product.isLibre) {
            const vignetteElement = document.createElement('div');
            vignetteElement.className = 'product-item libre-item';
            if (isInColis) {
                vignetteElement.classList.add('in-colis');
            }

            const quantityInputHtml = isInColis ? `
                <div class="quantity-input-container" style="margin-top: 8px; display: flex; align-items: center; gap: 5px;">
                    <span class="quantity-input-label" style="font-size: 12px; font-weight: bold;">Qté:</span>
                    <input type="number" class="quantity-input" value="${currentQuantity}" min="1" 
                           data-product-id="${product.id}" 
                           style="width: 60px; padding: 4px; border: 1px solid #ced4da; border-radius: 4px; text-align: center;">
                </div>
            ` : '';

            vignetteElement.innerHTML = `
                <div class="product-header">
                    <span class="product-ref">${product.name}</span>
                    <span class="product-color libre-badge">LIBRE</span>
                </div>
                
                <div class="product-dimensions">
                    Poids unitaire: ${product.weight}kg
                </div>
                <div class="quantity-info">
                    <span class="libre-info">📦 Élément libre</span>
                </div>
                ${quantityInputHtml}
                <div class="status-indicator libre"></div>
            `;

            return vignetteElement;
        }

        // Produits normaux (existant)
        const available = product.total - product.used;
        const percentage = (product.used / product.total) * 100;
        let status = 'available';
        
        if (available === 0) status = 'exhausted';
        else if (product.used > 0) status = 'partial';

        const vignetteElement = document.createElement('div');
        vignetteElement.className = `product-item ${status}`;
        if (isInColis) {
            vignetteElement.classList.add('in-colis');
        }
        if (!isInColis) {
            vignetteElement.draggable = status !== 'exhausted';
            vignetteElement.dataset.productId = product.id;
        }

        // CORRECTION : Ajouter input de quantité avec les bons styles pour les vignettes dans les colis
        const quantityInputHtml = isInColis ? `
            <div class="quantity-input-container" style="margin-top: 8px; display: flex; align-items: center; gap: 5px;">
                <span class="quantity-input-label" style="font-size: 12px; font-weight: bold;">Qté:</span>
                <input type="number" class="quantity-input" value="${currentQuantity}" min="1" 
                       data-product-id="${product.id}" 
                       style="width: 60px; padding: 4px; border: 1px solid #ced4da; border-radius: 4px; text-align: center;">
            </div>
        ` : '';

        vignetteElement.innerHTML = `
            <div class="product-header">
                <span class="product-ref">${product.name}</span>
                <span class="product-color">${product.color}</span>
            </div>
            
            <div class="product-dimensions">
                L: ${product.length}mm × l: ${product.width}mm ${product.ref_ligne ? `<strong>Réf: ${product.ref_ligne}</strong>` : ''}
            </div>
            <div class="quantity-info">
                <span class="quantity-used">${product.used}</span>
                <span>/</span>
                <span class="quantity-total">${product.total}</span>
                <div class="quantity-bar">
                    <div class="quantity-progress" style="width: ${percentage}%"></div>
                </div>
            </div>
            ${quantityInputHtml}
            <div class="status-indicator ${status === 'exhausted' ? 'error' : status === 'partial' ? 'warning' : ''}"></div>
        `;

        return vignetteElement;
    }

    /**
     * Créer un produit libre
     */
    function createLibreProduct(name, weight, quantity = 1) {
        const products = FicheProduction.data.products();
        const newId = Math.max(...products.map(p => p.id), 10000) + 1;
        return {
            id: newId,
            name: name,
            weight: parseFloat(weight),
            isLibre: true,
            total: 9999,
            used: 0
        };
    }

    /**
     * Mettre à jour l'inventaire basé sur les données sauvegardées
     */
    function updateInventoryFromSavedData() {
        const products = FicheProduction.data.products();
        const colis = FicheProduction.data.colis();
        
        // Réinitialiser toutes les quantités utilisées
        products.forEach(p => {
            if (!p.isLibre) {
                p.used = 0;
            }
        });

        // Recalculer les quantités utilisées basées sur les colis sauvegardés
        colis.forEach(c => {
            c.products.forEach(p => {
                const product = products.find(prod => prod.id === p.productId);
                if (product && !product.isLibre) {
                    product.used += p.quantity * c.multiple;
                }
            });
        });
    }

    /**
     * Remplir le sélecteur de groupes de produits
     */
    function populateProductGroupSelector() {
        const productGroups = FicheProduction.data.productGroups();
        const selector = document.getElementById('productGroupSelect');
        
        if (!selector) {
            debugLog('⚠️ Sélecteur de groupes non trouvé');
            return;
        }
        
        selector.innerHTML = '<option value="all">Tous les produits</option>';
        
        productGroups.forEach(group => {
            const option = document.createElement('option');
            option.value = group.key;
            option.textContent = `${group.name} - ${group.color}`;
            selector.appendChild(option);
        });
        
        debugLog(`📋 Sélecteur rempli avec ${productGroups.length} groupes`);
    }

    /**
     * Fonction de tri des produits
     */
    function sortProducts(productsList, sortType) {
        const sorted = [...productsList];
        
        switch(sortType) {
            case 'original':
                return sorted.sort((a, b) => a.line_order - b.line_order);
            case 'length_asc':
                return sorted.sort((a, b) => a.length - b.length);
            case 'length_desc':
                return sorted.sort((a, b) => b.length - a.length);
            case 'width_asc':
                return sorted.sort((a, b) => a.width - b.width);
            case 'width_desc':
                return sorted.sort((a, b) => b.width - a.width);
            case 'name_asc':
                return sorted.sort((a, b) => a.name.localeCompare(b.name));
            case 'name_desc':
                return sorted.sort((a, b) => b.name.localeCompare(a.name));
            default:
                return sorted.sort((a, b) => a.line_order - b.line_order);
        }
    }

    /**
     * Rendre l'inventaire des produits (FONCTION CRITIQUE)
     */
    function renderInventory() {
        debugLog('🎨 Rendu de l\'inventaire...');
        
        const container = document.getElementById('inventoryList');
        if (!container) {
            debugLog('❌ Container inventoryList non trouvé !');
            return;
        }

        container.innerHTML = '';

        const products = FicheProduction.data.products();
        const productGroups = FicheProduction.data.productGroups();
        const currentProductGroup = FicheProduction.state.currentProductGroup();
        const currentSort = FicheProduction.state.currentSort();

        debugLog(`📦 Rendu inventaire: ${products.length} produits disponibles`);

        // Filtrer les produits selon le groupe sélectionné (exclure les produits libres)
        let filteredProducts = products.filter(p => !p.isLibre);
        if (currentProductGroup !== 'all') {
            const selectedGroup = productGroups.find(g => g.key === currentProductGroup);
            if (selectedGroup) {
                filteredProducts = filteredProducts.filter(product => selectedGroup.products.includes(product.id));
                debugLog(`🔍 Filtrage par groupe "${currentProductGroup}": ${filteredProducts.length} produits`);
            }
        }

        // Trier les produits selon le critère sélectionné
        const sortedProducts = sortProducts(filteredProducts, currentSort);
        debugLog(`📊 Tri appliqué: ${currentSort} - ${sortedProducts.length} produits à afficher`);

        if (sortedProducts.length === 0) {
            container.innerHTML = '<div class="empty-state">Aucun produit à afficher</div>';
            return;
        }

        sortedProducts.forEach(product => {
            const productElement = createProductVignette(product, false);

            // Événements drag & drop
            productElement.addEventListener('dragstart', function(e) {
                const available = product.total - product.used;
                if (available === 0) {
                    e.preventDefault();
                    return;
                }
                
                FicheProduction.state.setDragging(true);
                FicheProduction.state.setDraggedProduct(product);
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'copy';
                debugLog(`🚀 Drag start: ${product.ref || product.name}`);
                
                // Activer les zones de drop après un délai
                setTimeout(() => {
                    if (FicheProduction.dragdrop && FicheProduction.dragdrop.activateDropZones) {
                        FicheProduction.dragdrop.activateDropZones();
                    }
                }, 50);
            });

            productElement.addEventListener('dragend', function(e) {
                this.classList.remove('dragging');
                FicheProduction.state.setDragging(false);
                FicheProduction.state.setDraggedProduct(null);
                debugLog(`🛑 Drag end: ${product.ref || product.name}`);
                
                // Désactiver les zones de drop
                if (FicheProduction.dragdrop && FicheProduction.dragdrop.deactivateDropZones) {
                    FicheProduction.dragdrop.deactivateDropZones();
                }
            });

            container.appendChild(productElement);
        });

        debugLog(`✅ Inventaire rendu: ${sortedProducts.length} produits affichés`);
    }

    /**
     * Initialiser le module inventory
     */
    function initializeInventoryModule() {
        debugLog('📦 Initialisation du module Inventory');
        
        // Événements pour les contrôles d'inventaire
        const searchBox = document.getElementById('searchBox');
        if (searchBox) {
            searchBox.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const productItems = document.querySelectorAll('.product-item');
                
                productItems.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    item.style.display = text.includes(searchTerm) ? 'block' : 'none';
                });
            });
        }

        // Sélecteur de groupe de produits
        const productGroupSelect = document.getElementById('productGroupSelect');
        if (productGroupSelect) {
            productGroupSelect.addEventListener('change', function(e) {
                FicheProduction.state.setCurrentProductGroup(e.target.value);
                renderInventory();
            });
        }

        // Sélecteur de tri
        const sortSelect = document.getElementById('sortSelect');
        if (sortSelect) {
            sortSelect.addEventListener('change', function(e) {
                FicheProduction.state.setCurrentSort(e.target.value);
                renderInventory();
            });
        }
        
        debugLog('✅ Module Inventory initialisé');
    }

    // ============================================================================
    // REGISTRATION DU MODULE (VERSION AMÉLIORÉE)
    // ============================================================================

    const InventoryModule = {
        createProductVignette: createProductVignette, // FONCTION CRITIQUE CORRIGÉE
        createLibreProduct: createLibreProduct,
        updateInventoryFromSavedData: updateInventoryFromSavedData,
        populateProductGroupSelector: populateProductGroupSelector,
        sortProducts: sortProducts,
        renderInventory: renderInventory, // FONCTION CRITIQUE
        initialize: initializeInventoryModule
    };

    // Fonction d'enregistrement robuste
    function registerInventoryModule() {
        if (window.FicheProduction) {
            if (window.FicheProduction.registerModule) {
                // Utiliser le nouveau système d'enregistrement
                window.FicheProduction.registerModule('inventory', InventoryModule);
            } else {
                // Fallback vers l'ancien système
                window.FicheProduction.inventory = InventoryModule;
                debugLog('📦 Module Inventory enregistré (fallback) dans FicheProduction.inventory');
            }
            
            // Vérification immédiate
            setTimeout(() => {
                if (window.FicheProduction.inventory && window.FicheProduction.inventory.renderInventory) {
                    debugLog('✅ renderInventory disponible dans le namespace');
                    debugLog('✅ createProductVignette corrigé avec inputs fonctionnels');
                } else {
                    debugLog('❌ renderInventory toujours non disponible dans le namespace');
                    // Enregistrement forcé si nécessaire
                    window.FicheProduction.inventory = InventoryModule;
                    debugLog('🔧 Enregistrement forcé du module Inventory');
                }
            }, 50);
        } else {
            debugLog('⏳ FicheProduction namespace pas encore disponible, réessai...');
            setTimeout(registerInventoryModule, 10);
        }
    }

    // Écouter l'événement de disponibilité du core
    if (window.addEventListener) {
        window.addEventListener('FicheProductionCoreReady', registerInventoryModule);
    }

    // Tenter l'enregistrement immédiat ou différé
    registerInventoryModule();

    // Export des fonctions pour compatibilité
    window.createProductVignette = createProductVignette;
    window.createLibreProduct = createLibreProduct;
    window.updateInventoryFromSavedData = updateInventoryFromSavedData;
    window.populateProductGroupSelector = populateProductGroupSelector;
    window.sortProducts = sortProducts;
    window.renderInventory = renderInventory;

    debugLog('📦 Module Inventory chargé et intégré (Version corrigée - Inputs fonctionnels)');

})();