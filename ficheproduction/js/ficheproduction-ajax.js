/**
 * FicheProduction v2.0 - AJAX Module
 * Gestion des communications avec le serveur
 */

(function() {
    'use strict';

    // ============================================================================
    // MODULE AJAX
    // ============================================================================

    const AjaxModule = {
        
        /**
         * Initialisation du module AJAX
         */
        initialize() {
            debugLog('🌐 Initialisation du module AJAX');
        },

        /**
         * Fonction principale d'appel API
         */
        async apiCall(action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('token', FicheProduction.config.token());
            formData.append('id', FicheProduction.config.orderId());
            
            for (const [key, value] of Object.entries(data)) {
                formData.append(key, value);
            }

            try {
                debugLog(`🌐 API Call: ${action}`);
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                debugLog(`📡 Response reçue: ${text.substring(0, 200)}...`);
                
                try {
                    return JSON.parse(text);
                } catch (parseError) {
                    debugLog(`❌ JSON Parse Error: ${parseError.message}`);
                    return { success: false, error: 'Invalid JSON response' };
                }
            } catch (error) {
                debugLog('❌ Erreur API: ' + error.message);
                return { success: false, error: error.message };
            }
        },

        /**
         * Charger les données de base de la commande
         */
        async loadData() {
            debugLog('📊 Chargement des données (ordre commande + groupes produits)...');
            const result = await this.apiCall('ficheproduction_get_data');
            
            if (result && result.products) {
                // Les produits sont déjà dans l'ordre de la commande
                FicheProduction.data.setProducts(result.products);
                FicheProduction.data.setProductGroups(result.product_groups || []);
                
                debugLog(`✅ Chargé ${result.products.length} produits dans l'ordre de la commande`);
                debugLog(`✅ Trouvé ${result.product_groups ? result.product_groups.length : 0} groupes de produits`);
                
                // Remplir le sélecteur de groupes
                if (FicheProduction.inventory.populateProductGroupSelector) {
                    FicheProduction.inventory.populateProductGroupSelector();
                }
                
                // Rendu initial de l'inventaire
                if (FicheProduction.inventory.render) {
                    FicheProduction.inventory.render();
                }
                
                // Après avoir chargé les données de base, essayer de charger les données sauvegardées
                await this.loadSavedData();
            } else {
                debugLog('❌ Erreur lors du chargement des données');
            }
        },

        /**
         * Charger les données sauvegardées
         */
        async loadSavedData() {
            if (FicheProduction.data.savedDataLoaded()) return; // Éviter les chargements multiples

            try {
                debugLog('💾 Chargement des données sauvegardées...');
                const result = await this.apiCall('ficheproduction_load_saved_data');

                if (result.success && result.colis && result.colis.length > 0) {
                    debugLog(`✅ Données sauvegardées trouvées: ${result.colis.length} colis`);
                    
                    // Convertir les données sauvegardées au format JavaScript
                    const convertedColis = this.convertSavedDataToJS(result.colis);
                    
                    // Remplacer les colis actuels par les données sauvegardées
                    FicheProduction.data.setColis(convertedColis);
                    
                    // Mettre à jour les quantités utilisées dans l'inventaire
                    this.updateInventoryFromSavedData();
                    
                    // Re-render
                    if (FicheProduction.inventory.render) {
                        FicheProduction.inventory.render();
                    }
                    if (FicheProduction.colis.renderOverview) {
                        FicheProduction.colis.renderOverview();
                    }
                    if (FicheProduction.utils.updateSummaryTotals) {
                        FicheProduction.utils.updateSummaryTotals();
                    }
                    
                    FicheProduction.data.setSavedDataLoaded(true);
                    debugLog('✅ Données sauvegardées chargées avec succès');
                } else {
                    debugLog('ℹ️ Aucune donnée sauvegardée trouvée ou erreur: ' + (result.message || 'Erreur inconnue'));
                }
                
            } catch (error) {
                debugLog('❌ Erreur lors du chargement des données sauvegardées: ' + error.message);
            }
        },

        /**
         * Convertir les données sauvegardées au format JavaScript
         */
        /**
 * Convertir les données sauvegardées au format JavaScript (VERSION CORRIGÉE)
 */
convertSavedDataToJS(savedColis) {
    debugLog('🔄 CONVERSION: Début conversion des données sauvegardées');
    debugLog(`🔄 CONVERSION: ${savedColis.length} colis à convertir`);
    
    const convertedColis = [];
    const currentColis = FicheProduction.data.colis();
    const currentProducts = FicheProduction.data.products();
    let maxColisId = Math.max(...currentColis.map(c => c.id), 0);

    savedColis.forEach((savedColi, index) => {
        debugLog(`🔄 CONVERSION: Traitement colis ${index + 1}/${savedColis.length}`);
        debugLog(`🔄 CONVERSION: Données colis sauvegardé:`, savedColi);
        
        const newColis = {
            id: ++maxColisId,
            number: savedColi.number || (index + 1),
            products: [],
            totalWeight: parseFloat(savedColi.totalWeight) || 0,
            maxWeight: parseFloat(savedColi.maxWeight) || 25,
            status: savedColi.status || 'ok',
            multiple: parseInt(savedColi.multiple) || 1,
            isLibre: savedColi.isLibre || false
        };

        debugLog(`✅ CONVERSION: Colis créé - ID=${newColis.id}, number=${newColis.number}, isLibre=${newColis.isLibre}`);

        // ✅ CORRECTION CRITIQUE : Convertir les produits avec debugging détaillé
        if (savedColi.products && Array.isArray(savedColi.products)) {
            debugLog(`🔄 CONVERSION: ${savedColi.products.length} produits à traiter dans le colis`);
            
            savedColi.products.forEach((savedProduct, productIndex) => {
                debugLog(`🔄 CONVERSION: Traitement produit ${productIndex + 1}:`, savedProduct);
                
                if (savedProduct.isLibre) {
                    // ✅ CORRECTION : Produits libres
                    debugLog(`🆓 CONVERSION: Création produit libre: ${savedProduct.name}`);
                    
                    const libreProduct = this.createLibreProduct(
                        savedProduct.name || `Produit libre ${productIndex + 1}`,
                        parseFloat(savedProduct.weight) || 0
                    );
                    
                    // Ajouter le produit libre à la liste globale
                    currentProducts.push(libreProduct);
                    
                    // Ajouter au colis
                    const productInColis = {
                        productId: libreProduct.id,
                        quantity: parseInt(savedProduct.quantity) || 1,
                        weight: (parseInt(savedProduct.quantity) || 1) * libreProduct.weight
                    };
                    
                    newColis.products.push(productInColis);
                    debugLog(`✅ CONVERSION: Produit libre ajouté - ID=${libreProduct.id}, qté=${productInColis.quantity}`);
                    
                } else {
                    // ✅ CORRECTION : Produits standards avec plusieurs méthodes de matching
                    debugLog(`📦 CONVERSION: Recherche produit standard avec ID ${savedProduct.productId}`);
                    
                    let product = null;
                    
                    // Méthode 1 : Par ID exact
                    if (savedProduct.productId) {
                        product = currentProducts.find(p => !p.isLibre && p.id == savedProduct.productId);
                        if (product) {
                            debugLog(`✅ CONVERSION: Produit trouvé par ID exact: ${product.name}`);
                        }
                    }
                    
                    // Méthode 2 : Par line_id (si disponible)
                    if (!product && savedProduct.line_id) {
                        product = currentProducts.find(p => !p.isLibre && p.line_id == savedProduct.line_id);
                        if (product) {
                            debugLog(`✅ CONVERSION: Produit trouvé par line_id: ${product.name}`);
                        }
                    }
                    
                    // Méthode 3 : Par référence
                    if (!product && savedProduct.ref) {
                        product = currentProducts.find(p => !p.isLibre && p.ref === savedProduct.ref);
                        if (product) {
                            debugLog(`✅ CONVERSION: Produit trouvé par ref: ${product.name}`);
                        }
                    }
                    
                    // Méthode 4 : Par nom (en dernier recours)
                    if (!product && savedProduct.name) {
                        product = currentProducts.find(p => !p.isLibre && p.name === savedProduct.name);
                        if (product) {
                            debugLog(`✅ CONVERSION: Produit trouvé par nom: ${product.name}`);
                        }
                    }
                    
                    if (product) {
                        const productInColis = {
                            productId: product.id,
                            quantity: parseInt(savedProduct.quantity) || 1,
                            weight: (parseInt(savedProduct.quantity) || 1) * (parseFloat(savedProduct.weight) || product.weight || 0)
                        };
                        
                        newColis.products.push(productInColis);
                        debugLog(`✅ CONVERSION: Produit standard ajouté - ID=${product.id}, qté=${productInColis.quantity}, poids=${productInColis.weight}kg`);
                    } else {
                        debugLog(`❌ CONVERSION: Produit non trouvé avec les critères:`, {
                            productId: savedProduct.productId,
                            line_id: savedProduct.line_id,
                            ref: savedProduct.ref,
                            name: savedProduct.name
                        });
                        debugLog(`❌ CONVERSION: Produits disponibles:`, currentProducts.filter(p => !p.isLibre).map(p => ({
                            id: p.id,
                            line_id: p.line_id,
                            ref: p.ref,
                            name: p.name
                        })));
                    }
                }
            });
        } else {
            debugLog(`⚠️ CONVERSION: Aucun produit dans le colis sauvegardé ou format incorrect`);
        }

        // ✅ CORRECTION : Recalculer le poids total basé sur les produits réellement ajoutés
        newColis.totalWeight = newColis.products.reduce((sum, p) => sum + (p.weight || 0), 0);
        debugLog(`⚖️ CONVERSION: Poids total recalculé: ${newColis.totalWeight}kg`);

        convertedColis.push(newColis);
        debugLog(`✅ CONVERSION: Colis ${newColis.id} terminé avec ${newColis.products.length} produits`);
    });

    // ✅ CORRECTION : Mettre à jour la liste des produits avec les nouveaux produits libres
    FicheProduction.data.setProducts(currentProducts);

    debugLog(`🎉 CONVERSION: Conversion terminée - ${convertedColis.length} colis convertis`);
    debugLog(`🎉 CONVERSION: Total produits dans les colis:`, convertedColis.reduce((sum, c) => sum + c.products.length, 0));
    
    return convertedColis;
},
/**
 * Créer un produit libre (pour le module AJAX)
 */
createLibreProduct(name, weight) {
    const products = FicheProduction.data.products();
    const newId = Math.max(...products.map(p => p.id), 10000) + 1;
    
    const libreProduct = {
        id: newId,
        name: name || `Produit libre ${newId}`,
        weight: parseFloat(weight) || 0,
        isLibre: true,
        total: 9999, // Pas de limite pour les produits libres
        used: 0,
        ref: `LIBRE_${newId}`,
        color: 'LIBRE'
    };
    
    debugLog(`🆓 AJAX: Produit libre créé - ID=${libreProduct.id}, nom="${libreProduct.name}", poids=${libreProduct.weight}kg`);
    
    return libreProduct;
},
        /**
         * Créer un produit libre
         */
        createLibreProduct(name, weight) {
            const products = FicheProduction.data.products();
            const newId = Math.max(...products.map(p => p.id), 10000) + 1;
            
            return {
                id: newId,
                name: name,
                weight: parseFloat(weight),
                isLibre: true,
                total: 9999, // Pas de limite pour les produits libres
                used: 0
            };
        },

        /**
         * Vérifier si un produit correspond aux données sauvegardées
         */
        matchSavedProduct(product, savedProduct) {
            // Simple matching par ID de produit Dolibarr si disponible
            return savedProduct.productId && product.line_id === savedProduct.productId;
        },

        /**
         * Mettre à jour l'inventaire basé sur les données sauvegardées
         */
        updateInventoryFromSavedData() {
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
            
            FicheProduction.data.setProducts(products);
        },

        /**
         * Sauvegarder le colisage
         */
        async saveColisage() {
            const colis = FicheProduction.data.colis();
            
            if (colis.length === 0) {
                if (FicheProduction.ui.showConfirm) {
                    await FicheProduction.ui.showConfirm('Aucun colis à sauvegarder.');
                }
                return;
            }

            // Afficher la modale de progression
            if (FicheProduction.ui.showSaveProgress) {
                FicheProduction.ui.showSaveProgress();
            }

            try {
                // Préparer les données pour la sauvegarde
                if (FicheProduction.ui.updateSaveProgress) {
                    FicheProduction.ui.updateSaveProgress(25, 'Préparation des données...');
                }
                const colisageData = this.prepareColisageDataForSave();

                if (FicheProduction.ui.updateSaveProgress) {
                    FicheProduction.ui.updateSaveProgress(50, 'Envoi des données...');
                }
                const result = await this.apiCall('ficheproduction_save_colis', {
                    colis_data: JSON.stringify(colisageData)
                });

                if (FicheProduction.ui.updateSaveProgress) {
                    FicheProduction.ui.updateSaveProgress(75, 'Traitement...');
                }
                
                if (result.success) {
                    if (FicheProduction.ui.updateSaveProgress) {
                        FicheProduction.ui.updateSaveProgress(100, 'Sauvegarde terminée !');
                    }
                    
                    setTimeout(() => {
                        if (FicheProduction.ui.hideSaveProgress) {
                            FicheProduction.ui.hideSaveProgress();
                        }
                        if (FicheProduction.ui.showConfirm) {
                            FicheProduction.ui.showConfirm(`✅ Colisage sauvegardé avec succès !\n\n${result.message}\nSession ID: ${result.session_id}`);
                        }
                        debugLog(`✅ Sauvegarde réussie: ${result.message}`);
                    }, 500);
                } else {
                    if (FicheProduction.ui.hideSaveProgress) {
                        FicheProduction.ui.hideSaveProgress();
                    }
                    if (FicheProduction.ui.showConfirm) {
                        await FicheProduction.ui.showConfirm(`❌ Erreur lors de la sauvegarde :\n${result.error || result.message}`);
                    }
                    debugLog(`❌ Erreur sauvegarde: ${result.error || result.message}`);
                }

            } catch (error) {
                if (FicheProduction.ui.hideSaveProgress) {
                    FicheProduction.ui.hideSaveProgress();
                }
                if (FicheProduction.ui.showConfirm) {
                    await FicheProduction.ui.showConfirm(`❌ Erreur technique :\n${error.message}`);
                }
                debugLog(`❌ Erreur technique: ${error.message}`);
            }
        },

        /**
         * Préparer les données pour la sauvegarde
         */
        prepareColisageDataForSave() {
            const colis = FicheProduction.data.colis();
            const products = FicheProduction.data.products();
            
            return colis.map(c => ({
                number: c.number,
                maxWeight: c.maxWeight,
                totalWeight: c.totalWeight,
                multiple: c.multiple,
                status: c.status,
                isLibre: c.isLibre || false,
                products: c.products.map(p => {
                    const product = products.find(prod => prod.id === p.productId);
                    if (!product) return null;

                    if (product.isLibre) {
                        return {
                            isLibre: true,
                            name: product.name,
                            description: '',
                            quantity: p.quantity,
                            weight: product.weight
                        };
                    } else {
                        return {
                            isLibre: false,
                            productId: p.productId,
                            quantity: p.quantity,
                            weight: product.weight
                        };
                    }
                }).filter(p => p !== null)
            }));
        }
    };

    // ============================================================================
    // EXPORT DU MODULE
    // ============================================================================

    // Ajouter le module au namespace principal
    if (window.FicheProduction) {
        window.FicheProduction.ajax = AjaxModule;
        debugLog('📦 Module AJAX chargé et intégré');
    } else {
        console.warn('FicheProduction namespace not found. Module AJAX not integrated.');
    }

})();