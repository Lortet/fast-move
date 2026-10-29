/**
 * includes all needed JavaScript for the move plugin
 *
 * Touch this file when included scripts, translations, or progress UI change to refresh DokuWiki's cache.
 */

/* DOKUWIKI:include_once script/json2.js */
/* DOKUWIKI:include script/MoveMediaManager.js */

jQuery(function () {
    /* DOKUWIKI:include script/form.js */
    /* DOKUWIKI:include script/progress.js */
    /* DOKUWIKI:include script/rename.js */


    // lazy load the tree manager
    const $tree = jQuery('#plugin_move__tree');
    if ($tree.length) {
        jQuery.getScript(
            DOKU_BASE + 'lib/plugins/move/script/tree.js',
            () => new PluginMoveTree($tree.get(0))
        );
    }

});
