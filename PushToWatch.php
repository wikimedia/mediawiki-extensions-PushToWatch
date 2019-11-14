<?php
/**
 * PushToWatch extension for MediaWiki
 * Allows to push a page to another users watchlist
 *
 * @link https://www.mediawiki.org/wiki/Extension:PushToWatch Documentation
 *
 * @file
 * @ingroup Extensions
 * @package MediaWiki
 * @author cloudyks
 * @copyright (C) 2013 cloudyks
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'PushToWatch' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['PushToWatch'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for the PushToWatch extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the PushToWatch extension requires MediaWiki 1.32+' );
}
