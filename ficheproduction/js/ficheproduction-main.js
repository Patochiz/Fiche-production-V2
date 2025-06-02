/**
 * FicheProduction v2.0 - Main Module (CORRIGÉ)
 * Fichier principal utilisant UNIQUEMENT l'architecture modulaire
 * REMPLACE: ficheproduction.js (l'ancien fichier monolithique de 56.8KB)
 */

(function() {
    'use strict';

    // ============================================================================
    // VÉRIFICATION DES DÉPENDANCES
    // ============================================================================

    /**
     * Vérifier que tous les modules requis sont chargés
     */
    function checkDependencies() {
        const requiredModules = ['ui', 'utils', 'vignettes', 'ajax', 'colis', 'dragdrop'];
        const missingModules = [];

        if (!window.FicheProduction) {
            throw new Error('FicheProduction namespace not found - ficheproduction-core.js not loaded?');
        }

        requiredModules.forEach(module => {
            if (!window.FicheProduction[module]) {
                missingModules.push(`ficheproduction-${module}.js`);
            }
        });

        if (missingModules.length > 0) {
            console.warn(`⚠️ Modules manquants: ${missingModules.join(', ')}`);
            if (window.FicheProduction.ui && window.FicheProduction.ui.showWarning) {
                window.FicheProduction.ui.showWarning(`Modules manquants: ${missingModules.join(', ')}`);
            }
        }

        return missingModules.length === 0;
    }

    // ============================================================================
    // INITIALISATION PRINCIPALE (CORRIGÉE)
    // ============================================================================

    /**
     * Fonction d'initialisation principale
     * CORRIGÉE: Utilise uniquement les modules, plus de code dupliqué
     */
    function initializeFicheProduction(orderId, token) {
        try {
            if (window.debugLog) debugLog('='.repeat(50));
            if (window.debugLog) debugLog('🚀 INITIALISATION FICHEPRODUCTION V2.0 (VERSION CORRIGÉE)');
            if (window.debugLog) debugLog('='.repeat(50));

            // 1. Vérifier les dépendances
            if (!checkDependencies()) {
                throw new Error('Modules requis manquants - initialisation impossible');
            }

            // 2. Configuration
            FicheProduction.config.setConfig(orderId, token);
            if (window.debugLog) debugLog(`✅ Configuration: Order ID=${orderId}`);

            // 3. Initialisation des modules dans l'ordre
            initializeModules();

            // 4. Configuration des event listeners
            setupMainEventListeners();

            // 5. Chargement des données
            loadInitialData();

            if (window.debugLog) debugLog('🎉 Initialisation principale terminée avec succès');

        } catch (error) {
            console.error('❌ Erreur lors de l\'initialisation:', error);
            if (window.FicheProduction && window.FicheProduction.ui) {
                FicheProduction.ui.showError(`Erreur d'initialisation: ${error.message}`);
            } else {
                alert(`Erreur d'initialisation: ${error.message}`);
            }
        }
    }

    /**
     * NOUVELLE: Initialiser tous les modules dans l'ordre correct
     */
    function initializeModules() {
        const modules = [
            { name: 'ui', module: FicheProduction.ui },
            { name: 'utils', module: FicheProduction.utils },
            { name: 'vignettes', module: FicheProduction.vignettes },
            { name: 'dragdrop', module: FicheProduction.dragdrop },
            { name: 'inventory', module: FicheProduction.inventory },
            { name: 'colis', module: FicheProduction.colis },
            { name: 'libre', module: FicheProduction.libre }
        ];

        modules.forEach(({ name, module }) => {
            if (module && module.initialize) {
                try {
                    module.initialize();
                    if (window.debugLog) debugLog(`✅ Module ${name} initialisé`);
                } catch (error) {
                    console.error(`❌ Erreur lors de l'initialisation du module ${name}:`, error);
                }
            } else {
                if (window.debugLog) debugLog(`⚠️ Module ${name} non disponible ou sans méthode initialize`);
            }
        });
    }

    /**
     * NOUVELLE: Configuration des event listeners principaux
     */
    function setupMainEventListeners() {
        if (window.debugLog) debugLog('🔧 Configuration des event listeners principaux');

        // Recherche dans l'inventaire
        const searchBox = document.getElementById('searchBox');
        if (searchBox) {
            searchBox.addEventListener('input', handleSearch);
        }

        // Sélecteur de groupe de produits
        const productGroupSelect = document.getElementById('productGroupSelect');
        if (productGroupSelect) {
            productGroupSelect.addEventListener('change', handleProductGroupChange);
        }

        // Sélecteur de tri
        const sortSelect = document.getElementById('sortSelect');
        if (sortSelect) {
            sortSelect.addEventListener('change', handleSortChange);
        }

        // Boutons principaux
        setupMainButtons();

        // Affichage/masquage de la console de debug
        const header = document.querySelector('.header h1');
        if (header) {
            header.addEventListener('dblclick', toggleDebugConsole);
        }

        // Gestionnaire d'erreurs globales
        window.addEventListener('error', handleGlobalError);

        if (window.debugLog) debugLog('✅ Event listeners principaux configurés');
    }

    /**
     * NOUVELLE: Configuration des boutons principaux
     */
    function setupMainButtons() {
        // ✅ PROTECTION : Éviter les doublons d'event listeners
        

        // Bouton Nouveau Colis Libre
        const addNewColisLibreBtn = document.getElementById('addNewColisLibreBtn');
        if (addNewColisLibreBtn) {
            addNewColisLibreBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (FicheProduction.libre && FicheProduction.libre.showColisLibreModal) {
                    FicheProduction.libre.showColisLibreModal();
                } else {
                    console.error('Module Libre non disponible');
                    if (FicheProduction.ui && FicheProduction.ui.showError) {
                        FicheProduction.ui.showError('Module Libre non disponible');
                    }
                }
            });
        }

        // Bouton de sauvegarde (chercher par ID ou par JS)
        const saveBtn = document.getElementById('saveColisageBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (FicheProduction.ajax && FicheProduction.ajax.saveColisage) {
                    FicheProduction.ajax.saveColisage();
                } else {
                    console.error('Module AJAX non disponible');
                    if (FicheProduction.ui && FicheProduction.ui.showError) {
                        FicheProduction.ui.showError('Module AJAX non disponible');
                    }
                }
            });
        }
    }

    // ============================================================================
    // GESTIONNAIRES D'ÉVÉNEMENTS (NOUVEAUX)
    // ============================================================================

    /**
     * NOUVEAU: Gestionnaire pour la recherche
     */
    function handleSearch(e) {
        const searchTerm = e.target.value.toLowerCase();
        const productItems = document.querySelectorAll('.product-item');
        
        productItems.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(searchTerm) ? 'block' : 'none';
        });

        if (window.debugLog) debugLog(`🔍 Recherche: "${searchTerm}" - ${Array.from(productItems).filter(item => item.style.display !== 'none').length} résultats`);
    }

    /**
     * NOUVEAU: Gestionnaire pour le changement de groupe de produits
     */
    function handleProductGroupChange(e) {
        FicheProduction.state.setCurrentProductGroup(e.target.value);
        
        if (FicheProduction.inventory && FicheProduction.inventory.renderInventory) {
            FicheProduction.inventory.renderInventory();
        }
        
        if (window.debugLog) debugLog(`📦 Groupe sélectionné: ${e.target.value}`);
    }

    /**
     * NOUVEAU: Gestionnaire pour le changement de tri
     */
    function handleSortChange(e) {
        FicheProduction.state.setCurrentSort(e.target.value);
        
        if (FicheProduction.inventory && FicheProduction.inventory.renderInventory) {
            FicheProduction.inventory.renderInventory();
        }
        
        if (window.debugLog) debugLog(`🔄 Tri appliqué: ${e.target.value}`);
    }

    /**
     * NOUVEAU: Basculer l'affichage de la console de debug
     */
    function toggleDebugConsole() {
        const debugConsole = document.getElementById('debugConsole');
        if (debugConsole) {
            const isVisible = debugConsole.style.display !== 'none';
            debugConsole.style.display = isVisible ? 'none' : 'block';
            if (window.debugLog) debugLog(`🐛 Console de debug ${isVisible ? 'masquée' : 'affichée'}`);
        }
    }

    /**
     * NOUVEAU: Gestionnaire d'erreurs globales
     */
    function handleGlobalError(event) {
        console.error('❌ ERREUR GLOBALE CAPTURÉE:', {
            message: event.message,
            filename: event.filename,
            lineno: event.lineno,
            colno: event.colno,
            error: event.error
        });

        if (window.debugLog) debugLog(`❌ ERREUR: ${event.message} (${event.filename}:${event.lineno})`);

        // Afficher dans l'interface si les modules UI sont disponibles
        if (FicheProduction.ui && FicheProduction.ui.showError) {
            FicheProduction.ui.showError(`Erreur: ${event.message}`);
        }
    }

    // ============================================================================
    // CHARGEMENT DES DONNÉES (CORRIGÉ)
    // ============================================================================

    /**
     * NOUVEAU: Chargement initial des données
     */
    async function loadInitialData() {
        try {
            if (window.debugLog) debugLog('📊 Chargement des données initiales...');

            if (!FicheProduction.ajax || !FicheProduction.ajax.loadData) {
                throw new Error('Module AJAX non disponible');
            }

            // Afficher un loader si disponible
            if (FicheProduction.ui && FicheProduction.ui.showLoader) {
                FicheProduction.ui.showLoader(true, 'Chargement des données...');
            }

            // Charger les données
            await FicheProduction.ajax.loadData();

            // Masquer le loader
            if (FicheProduction.ui && FicheProduction.ui.showLoader) {
                FicheProduction.ui.showLoader(false);
            }

            // Mettre à jour l'interface
            updateInterface();

            if (window.debugLog) debugLog('✅ Données initiales chargées avec succès');

        } catch (error) {
            console.error('❌ Erreur lors du chargement des données:', error);
            if (FicheProduction.ui) {
                if (FicheProduction.ui.showLoader) FicheProduction.ui.showLoader(false);
                if (FicheProduction.ui.showError) FicheProduction.ui.showError(`Erreur de chargement: ${error.message}`);
            }
        }
    }

    /**
     * NOUVEAU: Mettre à jour toute l'interface
     */
    function updateInterface() {
        // Mettre à jour l'inventaire
        if (FicheProduction.inventory && FicheProduction.inventory.renderInventory) {
            FicheProduction.inventory.renderInventory();
        }

        // Mettre à jour l'aperçu des colis
        if (FicheProduction.colis && FicheProduction.colis.renderColisOverview) {
            FicheProduction.colis.renderColisOverview();
        }

        // Mettre à jour les totaux
        if (FicheProduction.utils && FicheProduction.utils.updateSummaryTotals) {
            FicheProduction.utils.updateSummaryTotals();
        }

        if (window.debugLog) debugLog('🔄 Interface mise à jour');
    }

    // ============================================================================
    // EXPORTS GLOBAUX (COMPATIBILITÉ)
    // ============================================================================

    // Export des fonctions principales pour maintenir la compatibilité
    window.initializeFicheProduction = initializeFicheProduction;
    
    // Fonctions de compatibilité qui délèguent aux modules
    window.saveColisage = function() {
        if (FicheProduction.ajax && FicheProduction.ajax.saveColisage) {
            return FicheProduction.ajax.saveColisage();
        } else {
            console.error('Module AJAX non disponible');
        }
    };

    window.preparePrint = function() {
        if (FicheProduction.utils && FicheProduction.utils.preparePrint) {
            return FicheProduction.utils.preparePrint();
        } else {
            console.error('Module Utils non disponible');
            // Fonction de fallback
            var originalTitle = document.title;
            document.title = 'Fiche de Production - Commande';
            window.print();
            setTimeout(function() {
                document.title = originalTitle;
            }, 1000);
        }
    };

    // ============================================================================
    // INITIALISATION AUTOMATIQUE
    // ============================================================================

    if (window.debugLog) debugLog('📦 Module Main chargé (Version corrigée - Architecture modulaire)');
    if (window.debugLog) debugLog('💡 Ce fichier remplace l\'ancien ficheproduction.js monolithique');
    if (window.debugLog) debugLog('✨ Plus de doublons, architecture propre et maintenable');

    // Le module est prêt, attendre l'appel d'initialisation depuis le PHP

})();