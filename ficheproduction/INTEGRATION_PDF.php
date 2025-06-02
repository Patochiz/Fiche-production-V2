<?php
// Modification pour intégrer les boutons PDF dans ficheproduction.php
// À ajouter après la ligne qui inclut les classes FicheProduction

// Load PDF buttons helper
require_once dol_buildpath('/ficheproduction/includes/ficheproduction_buttons.php');

// Cette section remplace la partie "Boutons d'action" dans ficheproduction.php
// Remplacer les lignes suivantes :
/*
// Boutons d'action
print '<div class="tabsAction">';
if ($userCanEdit) {
    print '<a class="butAction" href="javascript:saveColisage();" id="saveColisageBtn">💾 ' . $langs->trans("Save") . '</a>';
}
print '<a class="butAction" href="javascript:preparePrint();">' . $langs->trans("PrintButton") . '</a>';
print '</div>';
*/

// Par cet appel de fonction :
// generatePDFButtons($object, $user, $langs, $conf);

// Instructions d'intégration :
// 1. Ouvrir le fichier ficheproduction/ficheproduction.php
// 2. Ajouter cette ligne après les autres require_once :
//    require_once dol_buildpath('/ficheproduction/includes/ficheproduction_buttons.php');
// 3. Remplacer la section "Boutons d'action" par :
//    generatePDFButtons($object, $user, $langs, $conf);

?>
