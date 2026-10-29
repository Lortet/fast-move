<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Schplurtz le Déboulonné <schplurtz@laposte.net>
 * @author Gary Owen
 * @author Nicolas Friedli <nicolas@theologique.ch>
 */
$lang['menu']                  = 'Déplacer/Renommer pages et catégories...';
$lang['inprogress']            = '(déplacement en attente)';
$lang['treelink']              = 'Au lieu de ce simple formulaire, vous pouvez effectuer des restructurations complexes de votre wiki en utilisant le <a href="%s">gestionnaire de déplacements par arborescence</a>';
$lang['notexist']              = 'La page %s n\'existe pas encore';
$lang['norights']              = 'Vos permissions sont insufisante pour éditer %s.';
$lang['filelocked']            = 'La page %s est verrouillée - réessayez plus tard.';
$lang['notchanged']            = 'Pas de nouvelle destination pour la page %s (emplacement inchangé).';
$lang['exists']                = 'La page %s ne peut être déplacée vers %s, la cible existe déjà.';
$lang['notargetperms']         = 'Pous n\'avez pas la permission de créer la page %s.';
$lang['medianotexist']         = 'Le fichier média %s n\'existe pas.';
$lang['nomediarights']         = 'Vous ne disposez pas des permissions suffisantes pour effacer %s.';
$lang['medianotchanged']       = 'Pas de nouvelle destination pour la page %s (emplacement inchangé).';
$lang['mediaexists']           = 'Impossible de déplacer le média %s. la cible %s existe déjà.';
$lang['nomediatargetperms']    = 'Vous n\'avez pas les permissions de créer le média %s';
$lang['extensionchange']       = 'L\'extension du nouveau fichier diffère de celle de l\'original.';
$lang['indexerror']            = 'Erreur lors de la mise à jour de l\'index de recherche %s.';
$lang['indexpageerror']        = 'Impossible de mettre à jour l’index des pages pendant le déplacement de %1$s vers %2$s. Détail de l’indexeur : %3$s';
$lang['indexreferenceerror']   = 'Impossible de mettre à jour les références de pages pendant le déplacement de %1$s vers %2$s. Détail de l’indexeur : %3$s';
$lang['indexmediaerror']       = 'Impossible de mettre à jour les références des médias pendant le déplacement de %1$s vers %2$s. Détail de l’indexeur : %3$s';
$lang['indexpage_missing']     = 'La page %1$s est absente de l’index de recherche. Le déplacement vers %2$s continue; son enregistrement la réindexera.';
$lang['index_write_failed']    = 'écriture de l’index impossible';
$lang['index_no_detail']       = 'DokuWiki n’a fourni aucun détail supplémentaire';
$lang['metamoveerror']         = 'Impossible de déplacer les métafichiers de la page %s.';
$lang['atticmoveerror']        = 'L\'historique (attic) de la page %s ne peut être déplacé. Veuillez déplacer les fichiers manuellement.';
$lang['mediametamoveerror']    = 'Impossible de déplacer les métafichiers du media %s.';
$lang['mediamoveerror']        = 'Échec du déplacement du média %s.';
$lang['mediaatticmoveerror']   = 'L\'historique (attic) du média %s ne peut être déplacé. Veuillez déplacer les fichiers manuellement.';
$lang['copied']                = 'Page copiée de %s vers %s';
$lang['copyfailed']            = 'Impossible de copier %s vers %s';
$lang['renamed']               = 'Nom de la page changé de %s à %s';
$lang['moved']                 = 'Page déplacée de %s à %s';
$lang['move_rename']           = 'Page déplacée et renommée de %s à %s';
$lang['delete']                = 'Effacé par le plugin de déplacement';
$lang['linkchange']            = 'Liens modifiés en raison d\'un déplacement.';
$lang['intro']                 = 'L\'opération de déplacement n\'a pas encore commencé.';
$lang['preview']               = 'Visualiser les changements à effectuer.';
$lang['inexecution']           = 'Un déplacement n\'est pas arrivé à son terme - utilisez les boutons ci-dessous pour continuer ou abandonner son exécution.';
$lang['btn_start']             = 'Commencer';
$lang['btn_continue']          = 'Continuer';
$lang['btn_retry']             = 'Réessayer cette étape';
$lang['btn_skip']              = 'Passer cette étape';
$lang['btn_abort']             = 'Abandonner';
$lang['log_title']             = 'Journal des déplacements';
$lang['log_empty']             = 'Aucun fichier à déplacer.';
$lang['log_done']              = 'Fait';
$lang['log_copied']            = 'Copié';
$lang['log_error']             = 'Erreur';
$lang['log_warning']           = 'Fait avec avertissement';
$lang['log_skipped']           = 'Ignoré';
$lang['log_pending']           = 'À faire';
$lang['legend']                = 'Déplacer la page ou l\'espace de nom courant';
$lang['movepage']              = 'Déplacer la page';
$lang['movens']                = 'Déplacer l\'espace de nom';
$lang['dst']                   = 'Nouveau nom:';
$lang['content_to_move']       = 'Contenu à déplacer:';
$lang['copyonly']              = 'Copier uniquement et conserver les originaux (réécrire les liens dans les copies)';
$lang['autoskip']              = 'Ignorer les erreurs et passer les pages ou fichiers qui ne peuvent être déplacés.';
$lang['ignorelocks']           = 'Ignorer les verrous de page DokuWiki (ne contourne pas les permissions ni les conflits de destination).';
$lang['copyacl']               = 'Copier les règles ACL explicites vers la nouvelle arborescence (copie uniquement).';
$lang['copyacl_failed']        = 'Impossible de copier les règles ACL : le fichier ACL est inaccessible ou non modifiable.';
$lang['autoskip_progress']     = 'Ignorer les erreurs et continuer automatiquement (les éléments en erreur seront ignorés).';
$lang['autorewrite']           = 'Récrire les liens à l\'issue du déplacement.';
$lang['move_pages']            = 'Pages';
$lang['move_media']            = 'Fichiers média';
$lang['move_media_and_pages']  = 'Pages et fichiers média';
$lang['nodst']                 = 'Pas de nouveau nom donné';
$lang['noaction']              = 'Pas de déplacement demandé';
$lang['renamepage']            = 'Renommer la page';
$lang['cantrename']            = 'Impossible de renommer la page maintenant. Veuillez essayer plus tard.';
$lang['notallowed']            = 'Vous n\'êtes pas autorisé à renommer les pages ou les médias.';
$lang['js']['rename']          = 'Renommer';
$lang['js']['cancel']          = 'Annuler';
$lang['js']['newname']         = 'Nouveau nom:';
$lang['js']['rename_media']    = 'Déplacer les médias référencés en même tant que la page';
$lang['js']['inprogress']      = 'Renommage des pages et ajustement des pages en cours...';
$lang['js']['complete']        = 'Déplacement effectué.';
$lang['js']['renameitem']      = 'Renommer cet élément';
$lang['js']['add']             = 'Créer une nouvelle catégorie';
$lang['js']['moveButton']      = 'Déplacer le fichier.';
$lang['js']['dialogIntro']     = 'Entrez le nouvel emplacement du fichier. Vous pouvez changer la catégorie, mais pas l\'extension.';
$lang['root']                  = '[Catégorie racine]';
$lang['noscript']              = 'Cette fonction requiert JavaScript';
$lang['moveinprogress']        = 'Une opération de déplacement est en cours en ce moment. Vous ne pouvez pas utiliser cet outil maintenant.';

$lang['dual0']            = 'Pages et médias combinés';
$lang['dual1']            = 'Pages et médias séparés';
$lang['js']['duplicate']  = 'Désolé, l\'élément « %s » existe déjà.';

$lang['js']['select']     = 'Select for moving';
$lang['js']['extchange']  = 'Vous ne pouvez pas modifier l\'extension d\'un fichier de média';


