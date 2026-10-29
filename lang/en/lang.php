<?php
/**
 * english language file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gary Owen <>
 */

$lang['menu']       = 'Move pages and namespaces';
$lang['inprogress'] = '(move pending)';
$lang['treelink']   = 'Alternatively to this simple form you can manage complex restructuring of your wiki using the <a href="%s">tree-based move manager</a>.';

// page errors
$lang['notexist']      = 'The page %s does not exist';
$lang['norights']      = 'You have insufficient permissions to edit %s.';
$lang['filelocked']    = 'The page %s is locked. Try again later.';
$lang['notchanged']    = 'No new destination given for page %s (location unchanged).';
$lang['exists']        = 'Page %s can\'t be moved to %s, the target already exists.';
$lang['notargetperms'] = 'You don\'t have the permission to create the page %s.';

// media errors
$lang['medianotexist']      = 'The media file %s does not exist';
$lang['nomediarights']      = 'You have insufficient permissions to delete %s.';
$lang['medianotchanged']    = 'No new destination given for page %s (location unchanged).';
$lang['mediaexists']        = 'Media %s can\'t be moved to %s, the target already exists.';
$lang['nomediatargetperms'] = 'You don\'t have the permission to create the media file %s.';
$lang['extensionchange']    = 'Extension of the new file is not the same as the original.';

// system errors
$lang['indexerror']          = 'Error while updating the search index %s';
$lang['indexpageerror']      = 'Could not update the page index while moving %1$s to %2$s. Indexer detail: %3$s';
$lang['indexreferenceerror'] = 'Could not update page references while moving %1$s to %2$s. Indexer detail: %3$s';
$lang['indexmediaerror']     = 'Could not update media references while moving %1$s to %2$s. Indexer detail: %3$s';
$lang['indexpage_missing']   = 'Page %1$s was missing from the search index. The move to %2$s will continue; saving the page will add it to the index again.';
$lang['index_write_failed']  = 'the index could not be written';
$lang['index_no_detail']     = 'DokuWiki returned no additional detail';
$lang['metamoveerror']       = 'The meta files of page %s couldn\'t be moved';
$lang['atticmoveerror']      = 'The attic files of page %s couldn\'t be moved. Please move them manually.';
$lang['mediametamoveerror']  = 'The meta files of the media file %s couldn\'t be moved';
$lang['mediamoveerror']      = 'Moving the media file %s failed';
$lang['mediaatticmoveerror'] = 'The attic files of media file %s couldn\'t be moved. Please move them manually.';
$lang['copied']              = 'Page copied from %s to %s';
$lang['copyfailed']          = 'Could not copy %s to %s';

// changelog summaries
$lang['renamed']     = 'Page name changed from %s to %s';
$lang['moved']       = 'Page moved from %s to %s';
$lang['move_rename'] = 'Page moved and renamed from %s to %s';
$lang['delete']      = 'Deleted by move plugin';
$lang['linkchange']  = 'Links adapted because of a move operation';

// progress view
$lang['intro']        = 'The move operation has not been started, yet!';
$lang['preview']      = 'Preview changes to be executed.';
$lang['inexecution']  = 'A previous move was not completed - use the buttons below to continue or abort the execution.';
$lang['btn_start']    = 'Start';
$lang['btn_continue'] = 'Continue';
$lang['btn_retry']    = 'Retry item';
$lang['btn_skip']     = 'Skip item';
$lang['btn_abort']    = 'Abort';
$lang['log_title']    = 'Move log';
$lang['log_empty']    = 'No files to move.';
$lang['log_done']     = 'Done';
$lang['log_copied']   = 'Copied';
$lang['log_error']    = 'Error';
$lang['log_warning']  = 'Moved with warning';
$lang['log_skipped']  = 'Skipped';
$lang['log_skipped_detected_blocker'] = 'Blocker detected when skipped: %s';
$lang['log_skipped_no_blocker'] = 'No blocker was detected when skipped. The source of this signal was not recorded for this entry.';
$lang['log_skipped_after_error'] = 'Step skipped after the previous error: %s';
$lang['log_error_skipped'] = 'Automatically skipped after failure: %s';
$lang['log_skipped_source'] = 'Skip signal received (%s). Details: %s';
$lang['log_pending']  = 'Pending';

// Form labels
$lang['legend']               = 'Move current page or namespace';
$lang['movepage']             = 'Move page';
$lang['movens']               = 'Move namespace';
$lang['dst']                  = 'Destination namespace (leave empty or enter : for the root):';
$lang['source_namespace']     = 'Source namespace to move:';
$lang['content_to_move']      = 'Content to move:';
$lang['copyonly']             = 'Copy only and keep the originals (rewrite links in copied pages)';
$lang['ignorelocks']          = 'Ignore DokuWiki page locks (does not bypass permissions or destination conflicts).';
$lang['copyacl']              = 'Copy explicit ACL rules to the new tree (copy-only mode).';
$lang['copylinkedmedia']      = 'Also copy linked media outside the selection into the target folder (copy-only mode).';
$lang['create_start_pages']   = 'Create a start page in destination folders that have no pages.';
$lang['start_page_template']  = 'Start page template (TITLE is replaced with the folder name):';
$lang['start_page_created_summary'] = 'Start page created by the Move plugin';
$lang['start_page_create_failed'] = 'Could not create start page %s.';
$lang['start_page_skipped_nonempty'] = 'Folder %s already contains a page; no start page was created.';
$lang['copyacl_failed']       = 'Could not copy ACL rules: the ACL file is unavailable or not writable.';
$lang['autoskip']             = 'Ignore errors and skip pages or files that can\'t be moved';
$lang['autoskip_progress']    = 'Ignore errors and continue automatically (items that fail will be skipped)';
$lang['autorewrite']          = 'Rewrite links right after the move completed';
$lang['move_pages']           = 'Pages';
$lang['move_media']           = 'Media files';
$lang['move_media_and_pages'] = 'Pages and media files';
$lang['nodst']                = 'No new name given';
$lang['noaction']             = 'There were no moves defined';

// Rename feature
$lang['renamepage']       = 'Rename Page';
$lang['cantrename']       = 'The page can\'t be renamed right now. Please try later.';
$lang['notallowed']       = 'You are not allowed to rename pages or media.';
$lang['js']['rename']     = 'Rename';
$lang['js']['cancel']     = 'Cancel';
$lang['js']['newname']    = 'New name:';
$lang['js']['rename_media']  = 'Move referenced media files along with the page';
$lang['js']['inprogress'] = 'renaming page and adjusting links...';
$lang['js']['complete']   = 'Move operation finished.';
$lang['js']['completed_title'] = 'Move completed!';
$lang['js']['duration']   = 'Finished in %1$s min %2$s sec.';
$lang['js']['impacts_title'] = 'Potentially modified folders and files';
$lang['js']['summary_title'] = 'Move summary';
$lang['js']['summary_moved'] = 'items moved';
$lang['js']['summary_copied'] = 'items copied';
$lang['js']['summary_failed'] = 'with errors';
$lang['js']['summary_skipped'] = 'skipped';
$lang['js']['summary_warning'] = 'with warnings';
$lang['js']['progress_count'] = '%1$s / %2$s items processed (%3$s %)';

// Tree Manager
$lang['root']             = '[Root namespace]';
$lang['noscript']         = 'This feature requires JavaScript';
$lang['moveinprogress']   = 'There is another move operation in progress currently, you can\'t use this tool right now.';
$lang['dual0']            = 'Pages and Media combined';
$lang['dual1']            = 'Pages and Media separated';
$lang['js']['renameitem'] = 'Rename this item';
$lang['js']['add']        = 'Create a new namespace';
$lang['js']['duplicate']  = 'Sorry, item "%s" already exists.';

$lang['js']['select']     = 'Select for moving';
$lang['js']['extchange']  = 'You can not change the extension of a media file';


// Media Manager
$lang['js']['moveButton'] = 'Move file';
$lang['js']['dialogIntro'] = 'Enter new file destination. You may change the namespace but not the file extension.';
