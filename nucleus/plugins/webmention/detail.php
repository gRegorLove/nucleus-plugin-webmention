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

	$id = intval(requestVar('id'));
	$table_received = sql_table('plugin_webmention_received_logs');
	$table_webmentions = sql_table('plugin_webmentions');

	$query = <<< END
SELECT
	`w`.*,
	`r`.`source`,
	`r`.`target`,
	`r`.`modified`,
	`r`.`processed`,
	`r`.`parsed_content`,
	DATE_FORMAT(`r`.`modified`, '%m/%d/%Y') AS `modified_date`,
	DATE_FORMAT(`r`.`processed`, '%m/%d/%Y') AS `processed_date`
FROM 
	`{$table_webmentions}` AS `w`,
	`{$table_received}` AS `r`
WHERE 
	`w`.`id` = {$id}
	AND `r`.`id` = `w`.`log_id`
	AND `r`.`deleted` IS NULL
ORDER BY 
	`r`.`modified` DESC 
END;
	$result = sql_query($query);

	echo '<h2> Webmention Detail </h2>';

	echo sprintf('<p> <a href="plugins/webmention/">Return to webmention admin</a> </p>');

	# if: no results
	if ( sql_num_rows($result) === 0 )
	{
		echo '<p> No webmention found matching that ID. </p>';
	}
	# else: display the details
	else
	{
		$row = sql_fetch_assoc($result);

		# if: do not display content for mentions
		if ( $row['type'] == 'mention' )
		{
			$url_parts = parse_url($row['source']);
			$content = sprintf('<a href="%s">%s</a> mentioned <a href="%s">%3$s</a>', $row['source'], $url_parts['host'], $row['target']);
		}
		# else: 
		else
		{
			$content = htmlspecialchars($row['content']);
		} # end if

		$author_url = $author_photo = $author_logo = '—';

		if ( $row['author_url'] )
		{
			$author_url = sprintf('<a href="%s">%1$s</a>', $row['author_url']);
		}

		if ( $row['author_photo'] )
		{
			$author_photo = sprintf('<img src="%s" alt="photo" style="max-width: 150px;" />', $row['author_photo']);
		}

		if ( $row['author_logo'] )
		{
			$author_logo = sprintf('<img src="%s" alt="logo" style="max-width: 150px;" />', $row['author_logo']);
		}

		$source = sprintf('<a href="%s">%1$s</a>', $row['source']);
		$target = sprintf('<a href="%s">%1$s</a>', $row['target']);

		$checked = ( $row['is_displayed'] ) ? ' checked="checked"' : '';

		print <<< END
	<table>
		<tr>
			<th colspan="2"> Webmention </th>
		</tr>
		<tr>
			<td style="width: 175px;"> Display on site </td>
			<td>
				<form method="post" action="plugins/webmention/update.php" style="display: inline;">
				<input type="hidden" name="display" value="0" />
				<input type="checkbox" name="display" id="i_display" value="1"{$checked} /> <label for="i_display">Display on site</label> &nbsp; 
				<input type="hidden" name="id" value="{$row['id']}" />
				<input type="submit" value="Update" />
				</form>
			</td>
		</tr>
		<tr>
			<td> ID </td>
			<td> {$row['id']} </td>
		</tr>
		<tr>
			<td> Type </td>
			<td> {$row['type']} </td>
		</tr>
		<tr>
			<td> Content </td>
			<td> {$content} </td>
		</tr>
		<tr>
			<td> Author </td>
			<td> {$row['author_name']} </td>
		</tr>
		<tr>
			<td> Author URL </td>
			<td> {$author_url} </td>
		</tr>
		<tr>
			<td> Author Photo </td>
			<td> {$author_photo} </td>
		</tr>
		<tr>
			<td> Author Logo </td>
			<td> {$author_logo} </td>
		</tr>
		<tr>
			<td> Published </td>
			<td> {$row['published']} </td>
		</tr>
		<tr>
			<td> Updated </td>
			<td> {$row['updated']} </td>
		</tr>
		<tr>
			<td> Webmention Received </td>
			<td> {$row['modified']} </td>
		</tr>
		<tr>
			<td> Webmention Processed </td>
			<td> {$row['processed']} </td>
		</tr>
		<tr>
			<td> Source </td>
			<td> {$source} </td>
		</tr>
		<tr>
			<td> Target </td>
			<td> {$target} </td>
		</tr>
		<tr>
			<td> Parsed Content </td>
			<td> <textarea rows="10">{$row['parsed_content']}</textarea> </td>
		</tr>

	</table>
END;
	} # end if

	$PluginAdmin->end();
