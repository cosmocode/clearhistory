<?php
/**
 * english language file
 */
 
$lang['menu'] = 'History Cleanup'; 
 
// custom language strings for the plugin
$lang['name'] = 'History Clearer';
$lang['clean on recent changes'] = 'Clean the history based on the recent changes page.';
$lang['clean on namespace'] = 'Clean the history based on a namespace. (Danger, could be slow)';
$lang['do'] = 'Clear the history';
$lang['deleted'] = '%d items deleted.';
$lang['desc'] = 'Description';
$lang['desctext'] = 'This plugin allows you to cleanup the history.<br /><br />
It deletes all page revisions that are from the same user where created within a
one hour timeframe.<br /><br />

<b>Clean on recent changes:</b><br />
When this option is selected, only the pages contained in the list of the most
recent changes are checked.<br /><br />

<b>Clean on namespace:</b><br />
This option will check all pages within (and below) the given namespace.
If no namespace is entered all namespaces will be checked. Use this with caution,
it can take a long time.';

$lang['onlysmall'] = 'Just remove small changes.';
$lang['onlynocomment'] = 'Just remove changes without a comment.';
?>