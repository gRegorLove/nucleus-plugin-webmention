<?php

	header('Content-Type: text/html; charset=utf-8');

	/**
	 * If your plugin directory is not in the default location, 
	 * edit this variable to point to your site directory 
	 * where config.php is located
	 */
	$relative_path = '../../../';

	include($relative_path . 'config.php');

	# if: not an administrator
	if ( !$member->isAdmin() )
	{
		doError('You do not have permission to access this page.');
	} # end if

	$id = intval(requestVar('id'));
	$display = intval(requestVar('display'));
	$table_webmentions = sql_table('plugin_webmentions');

	$query = <<< END
UPDATE `{$table_webmentions}` SET 
	`is_displayed` = {$display}
WHERE 
	`id` = {$id}
END;
	$result = sql_query($query);

	header('HTTP/1.1 303 See Other');
	header('Location: detail.php?id=' . $id);
