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

	include($DIR_LIBS . 'PLUGINADMIN.php');

	# create the admin area page
	$PluginAdmin = new PluginAdmin('Webmention');
	$PluginAdmin->start();

	$hostname = requestVar('hostname');
	$hostname = parse_url($hostname, PHP_URL_HOST);

	# if: no hostname found
	if ( empty($hostname) )
	{
		echo sprintf('<p> Invalid URL. Please enter a valid URL. </p>');
	}
	# else: 
	else
	{
		$type = requestVar('type');
		$hostname = sql_real_escape_string($hostname);
		$table = ( $type == 'blacklist' ) ? sql_table('plugin_webmention_blacklist') : sql_table('plugin_webmention_whitelist');

		$query = <<< END
INSERT INTO `{$table}` SET 
	`hostname` = '{$hostname}',
	`created` = NULL, 
	`modified` = NULL
END;
		$result = sql_query($query);

		# if: db/query error
		if ( $result === FALSE )
		{
			echo sprintf('<p> There was an error updating the database. (E%d) </p>', __LINE__);
		}
		# else: 
		else
		{
			$type = ( $type == 'blacklist' ) ? 'blacklist' : 'whitelist';
			echo sprintf('<p> Domain added to %s. </p>', $type);
		} # end if

	} # end if

	echo sprintf('<p> <a href="plugins/webmention/">Return to webmention admin</a> </p>');

	$PluginAdmin->end();
