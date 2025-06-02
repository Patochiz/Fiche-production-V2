/**
 * FICHIER DÉSACTIVÉ - NE PLUS UTILISER
 * 
 * Ce fichier monolithique (56.8KB) causait des problèmes de doublons.
 * Il a été remplacé par l'architecture modulaire :
 * 
 * - ficheproduction-core.js
 * - ficheproduction-ui.js 
 * - ficheproduction-utils.js
 * - ficheproduction-vignettes.js (NOUVEAU)
 * - ficheproduction-ajax.js
 * - ficheproduction-inventory.js
 * - ficheproduction-colis.js
 * - ficheproduction-dragdrop.js
 * - ficheproduction-libre.js
 * - ficheproduction-main.js (NOUVEAU)
 * 
 * LE FICHIER PHP A ÉTÉ MODIFIÉ POUR CHARGER L'ARCHITECTURE MODULAIRE
 * 
 * Si ce fichier est chargé, c'est qu'il y a une erreur de configuration.
 */

(function() {
    'use strict';
    
    // Message d'avertissement visible
    console.error('🚨 FICHIER MONOLITHIQUE DÉSACTIVÉ');
    console.error('📦 Utilisez l\'architecture modulaire à la place');
    console.error('🔧 Vérifiez le fichier PHP ficheproduction.php');
    
    // Afficher un message visible sur la page
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showWarningMessage);
    } else {
        showWarningMessage();
    }
    
    function showWarningMessage() {
        const warningDiv = document.createElement('div');
        warningDiv.innerHTML = `
            <div style="
                background: #ff4444; 
                color: white; 
                padding: 15px; 
                text-align: center; 
                font-weight: bold; 
                border: 2px solid #cc0000;
                margin: 10px;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                z-index: 9999;
                position: relative;
            ">
                🚨 <strong>FICHIER MONOLITHIQUE DÉSACTIVÉ</strong> 🚨<br>
                <span style="font-size: 14px;">
                    Ce fichier causait des doublons. L'architecture modulaire est maintenant active.<br>
                    Si vous voyez ce message, vérifiez la configuration du fichier PHP.
                </span>
            </div>
        `;
        
        // Insérer en haut de la page
        if (document.body) {
            document.body.insertBefore(warningDiv, document.body.firstChild);
        }
    }
    
    // Ne pas charger les fonctions dupliquées
    console.warn('⚠️ Fonctions non chargées pour éviter les conflits');
    
})();

// Export vide pour éviter les erreurs
window.ficheproductionMonolithDisabled = true;