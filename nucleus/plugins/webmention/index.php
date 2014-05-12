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

	if ( $_GET['mode'] == 'parse' )
	{
		$PluginAdmin->plugin->processWebmentions();
	}

	echo '<h2> Webmention Admin </h2>';
	echo '<h3> Pending Webmentions </h3>';

	$table_received = sql_table('plugin_webmention_received_logs');
	$table_webmentions = sql_table('plugin_webmentions');
	$table_whitelist = sql_table('plugin_webmention_whitelist');
	$table_blacklist = sql_table('plugin_webmention_blacklist');

	$query = <<< END
SELECT
	`post_id`,
	`source`,
	`target`,
	DATE_FORMAT(`modified`, '%m/%d/%Y') AS `modified_date`
FROM 
	`{$table_received}`
WHERE 
	(
		`processed` IS NULL
		OR (`modified` > `processed`)
	)
	AND `deleted` IS NULL
ORDER BY 
	`modified` ASC
END;
	$result = sql_query($query);

	# if: no pending webmentions
	if ( sql_num_rows($result) == 0 )
	{
		echo '<p> There are no pending webmentions currently. </p>';
	}
	# else: display pending webmentions
	else
	{
		echo sprintf('<p> <a href="plugins/webmention/index.php?mode=parse">Process pending webmentions</a> </p>');

		print <<< END
	<table>
		<tr>
			<th> Post ID </th>
			<th> Target </th>
			<th> Source </th>
			<th> Date </th>
		</tr>
END;

		# loop: 
		while ( $row = sql_fetch_assoc($result) )
		{
			print <<< END
		<tr>
			<td class="center"> {$row['post_id']} </td>
			<td> {$row['target']} </td>
			<td> {$row['source']} </td>
			<td> {$row['modified_date']} </td>
		</tr>
END;
		} # end loop

		print <<< END
	</table>
END;
	} # end if

	$query = <<< END
SELECT
	COUNT(*)
FROM 
	`{$table_webmentions}` AS `w`
WHERE 
	`w`.`is_blacklisted` = 0
	AND `w`.`deleted` IS NULL
END;
	$result = sql_query($query);

	$total_webmentions = sql_result($result, 0);
	$webmentions_per_page = 20;
	$page = intval(requestVar('p'));
	$page = max($page, 1);
	$lower_limit = ($page - 1) * $webmentions_per_page;
	$page_limit = ceil($total_webmentions / $webmentions_per_page);

	$query = <<< END
SELECT
	`w`.*,
	`r`.`source`,
	`r`.`target`,
	DATE_FORMAT(`r`.`modified`, '%m/%d/%Y') AS `modified_date`,
	DATE_FORMAT(`r`.`processed`, '%m/%d/%Y') AS `processed_date`
FROM 
	`{$table_webmentions}` AS `w`,
	`{$table_received}` AS `r`
WHERE 
	`r`.`id` = `w`.`log_id`
	AND `r`.`deleted` IS NULL
	AND `w`.`is_blacklisted` = 0
	AND `w`.`deleted` IS NULL
ORDER BY 
	`r`.`modified` DESC 
LIMIT {$lower_limit}, {$webmentions_per_page}
END;
	$result = sql_query($query);

	echo '<h3> Processed Webmentions </h3>';

	# if: no pending webmentions
	if ( sql_num_rows($result) == 0 )
	{
		echo '<p> There are no processsed webmentions currently. </p>';
	}
	# else: display pending webmentions
	else
	{
		print <<< END
	<table>
		<tr>
			<th> ID </th>
			<th> Type </th>
			<th> Content </th>
			<th> Author </th>
			<th> Detail </th>
			<th> Source </th>
			<th> Target </th>
			<th> Processed </th>
		</tr>
END;

		# loop: 
		while ( $row = sql_fetch_assoc($result) )
		{
			$content = $row['content'];

			# if: truncate the content
			if ( strlen($content) > 250 )
			{
				$content = substr($content, 0, 250) . ' … ';
			} # end if

			$content = htmlspecialchars($content);
			$link_author = sprintf('<a href="%s">%s</a>', $row['author_url'], $row['author_name']);
			$link_detail = sprintf('<a href="plugins/webmention/detail.php?id=%d">Detail</a>', $row['id']);
			$link_source = sprintf('<a href="%s">Source</a>', $row['url']);
			$link_target = sprintf('<a href="%s">Target</a>', $row['target']);

			print <<< END
		<tr>
			<td class="center"> {$row['id']} </td>
			<td> {$row['type']} </td>
			<td> {$content} </td>
			<td style="white-space: nowrap;"> {$link_author} </td>
			<td> {$link_detail} </td>
			<td> {$link_source} </td>
			<td> {$link_target} </td>
			<td> {$row['processed_date']} </td>
		</tr>
END;
		} # end loop

		print <<< END
	</table>
END;
	} # end if

	echo '<h3> Whitelisted Hostnames </h3>';
	echo '<p> Webmentions from whitelisted domains will automatically be approved for display once processed. </p>';

	$query = <<< END
SELECT 
	`id`,
	`hostname`
FROM 
	`{$table_whitelist}`
WHERE 
	`deleted` IS NULL 
ORDER BY 
	`hostname`
END;
	$result = sql_query($query);

	# if: no whitelisted domains
	if ( sql_num_rows($result) == 0 )
	{
		echo '<p> There are no whitelisted hostnames currently. </p>';
	}
	# else: display pending webmentions
	else
	{
		print <<< END
	<table>
		<tr>
			<th> Hostname </th>
		</tr>
END;

		# loop: 
		while ( $row = sql_fetch_assoc($result) )
		{
			print <<< END
		<tr>
			<td> {$row['hostname']} </td>
		</tr>
END;
		} # end loop

		print <<< END
	</table>
END;
	} # end if

	print <<< END
	<form method="post" action="plugins/webmention/hostnames.php">
	<p> <label for="i_hostname_whitelist">Add to whitelist:</label> <input type="text" name="hostname" id="i_hostname_whitelist" size="40" /> <input type="submit" value="Add" /> </p>
	<input type="hidden" name="type" value="whitelist" />
	</form>
END;

	echo '<h3> Blacklisted Hostnames </h3>';
	echo '<p> Webmentions from blacklisted domains will automatically be declined. </p>';

	$query = <<< END
SELECT 
	`id`,
	`hostname`
FROM 
	`{$table_blacklist}`
WHERE 
	`deleted` IS NULL 
ORDER BY 
	`hostname`
END;
	$result = sql_query($query);

	# if: no whitelisted domains
	if ( sql_num_rows($result) == 0 )
	{
		echo '<p> There are no blacklisted hostnames currently. </p>';
	}
	# else: display pending webmentions
	else
	{
		print <<< END
	<table>
		<tr>
			<th> Hostname </th>
		</tr>
END;

		# loop: 
		while ( $row = sql_fetch_assoc($result) )
		{
			print <<< END
		<tr>
			<td> {$row['hostname']} </td>
		</tr>
END;
		} # end loop

		print <<< END
	</table>
END;
	} # end if

	print <<< END
	<form method="post" action="plugins/webmention/hostnames.php">
	<p> <label for="i_hostname_whitelist">Add to blacklist:</label> <input type="text" name="hostname" id="i_hostname_blacklist" size="40" /> <input type="submit" value="Add" /> </p>
	<input type="hidden" name="type" value="blacklist" />
	</form>
END;

	$PluginAdmin->end();
