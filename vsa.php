<?php
require_once dirname(__DIR__, 2) . "/resources/require.php";
require_once "resources/check_auth.php";

//include the header
	require_once "/var/www/fusionpbx/resources/header.php";

//show the content
	echo "<script>\n";
	echo "	function compare_passwords() {\n";
	echo "		if (document.getElementById('password') === document.activeElement || document.getElementById('password_confirm') === document.activeElement) {\n";
	echo "			if ($('#password').val() != '' || $('#password_confirm').val() != '') {\n";
	echo "				if ($('#password').val() != $('#password_confirm').val()) {\n";
	echo "					$('#password').removeClass('formfld_highlight_good');\n";
	echo "					$('#password_confirm').removeClass('formfld_highlight_good');\n";
	echo "					$('#password').addClass('formfld_highlight_bad');\n";
	echo "					$('#password_confirm').addClass('formfld_highlight_bad');\n";
	echo "				}\n";
	echo "				else {\n";
	echo "					$('#password').removeClass('formfld_highlight_bad');\n";
	echo "					$('#password_confirm').removeClass('formfld_highlight_bad');\n";
	echo "					$('#password').addClass('formfld_highlight_good');\n";
	echo "					$('#password_confirm').addClass('formfld_highlight_good');\n";
	echo "				}\n";
	echo "			}\n";
	echo "		}\n";
	echo "		else {\n";
	echo "			$('#password').removeClass('formfld_highlight_bad');\n";
	echo "			$('#password_confirm').removeClass('formfld_highlight_bad');\n";
	echo "			$('#password').removeClass('formfld_highlight_good');\n";
	echo "			$('#password_confirm').removeClass('formfld_highlight_good');\n";
	echo "		}\n";
	echo "	}\n";

	echo "	function show_strength_meter() {\n";
	echo "		$('#pwstrength_progress').slideDown();\n";
	echo "	}\n";
	echo "</script>\n";

	echo "<form name='frm' id='frm' method='post' action='/app/vsa/user_edit.php'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['header-user_edit']."</b></div>\n";
	echo "	<div class='actions'>\n";
	if (!empty($unsaved)) {
		echo "<div class='unsaved'>".$text['message-unsaved_changes']." <i class='fas fa-exclamation-triangle'></i></div>";
	}
	if (permission_exists('user_add') || permission_exists('user_edit')) {
		echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','link'=>'users.php']);
	}
	$button_margin = 'margin-left: 15px;';
	if (permission_exists('ticket_add') || permission_exists('ticket_edit')) {
		echo button::create(['type'=>'button','label'=>$text['button-tickets'],'icon'=>'tags','style'=>$button_margin,'link'=>PROJECT_PATH.'/app/tickets/tickets.php?user_uuid='.urlencode($user_uuid)]);
		unset($button_margin);
	}
	if (permission_exists('user_permissions') && file_exists('../../app/user_permissions/user_permissions.php')) {
		echo button::create(['type'=>'button','label'=>$text['button-permissions'],'icon'=>'key','style'=>$button_margin,'link'=>PROJECT_PATH.'/app/user_permissions/user_permissions.php?id='.urlencode($user_uuid)]);
		unset($button_margin);
	}
	echo button::create(['type'=>'button','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','style'=>'margin-left: 15px;','onclick'=>'submit_form();']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-user_edit']."\n";
	echo "<br /><br />\n";

	echo "<table cellpadding='0' cellspacing='0' border='0' width='100%'>";

	echo "	<tr>";
	echo "		<td width='30%' class='vncellreq' valign='top'>Username</td>";
	echo "		<td width='70%' class='vtable'>";
	if (permission_exists("user_edit")) {
		echo "		<input type='text' class='formfld' name='username' id='username' autocomplete='new-password' value='".escape($username ?? '')."' required='required'>\n";
		echo "		<input type='text' style='display: none;' disabled='disabled'>\n"; //help defeat browser auto-fill
	}
	else {
		echo "		".escape($username)."\n";
		echo "		<input type='hidden' name='username' id='username' autocomplete='new-password' value='".escape($username ?? '')."'>\n";
	}
	echo "		</td>";
	echo "	</tr>";

	echo "	<tr>";
	echo "		<td class='vncell".(($action == 'add') ? 'req' : null)."' valign='top'>Password</td>";
	echo "		<td class='vtable'>";
	echo "			<input type='password' style='display: none;' disabled='disabled'>"; //help defeat browser auto-fill
	echo "			<input type='password' autocomplete='new-password' class='formfld' name='password' id='password' value=\"".escape($password ?? null)."\" ".($action == 'add' ? "required='required'" : null)." onkeypress='show_strength_meter();' onfocus='compare_passwords();' onkeyup='compare_passwords();' onblur='compare_passwords();'>";
	echo "			<div id='pwstrength_progress' class='pwstrength_progress'></div><br />\n";
	if ((!empty($required['length']) && is_numeric($required['length']) && $required['length'] != 0) || $required['number'] || $required['lowercase'] || $required['uppercase'] || $required['special']) {
		echo $text['label-required'].': ';
		if (is_numeric($required['length']) && $required['length'] != 0) {
			echo $required['length']." ".$text['label-characters'];
			if ($required['number'] || $required['lowercase'] || $required['uppercase'] || $required['special']) {
				echo " (";
			}
		}
		if ($required['number']) {
			$required_temp[] = $text['label-number'];
		}
		if ($required['lowercase']) {
			$required_temp[] = $text['label-lowercase'];
		}
		if ($required['uppercase']) {
			$required_temp[] = $text['label-uppercase'];
		}
		if ($required['special']) {
			$required_temp[] = $text['label-special'];
		}
		if (!empty($required_temp)) {
			echo implode(', ',$required_temp);
			if (is_numeric($required['length']) && $required['length'] != 0) {
				echo ")";
			}
		}
		unset($required_temp);
	}
	echo "		</td>";
	echo "	</tr>";
	echo "	<tr>";
	echo "		<td class='vncell".(($action == 'add') ? 'req' : null)."' valign='top'>Confirm Password</td>";
	echo "		<td class='vtable'>";
	echo "			<input type='password' autocomplete='new-password' class='formfld' name='password_confirm' id='password_confirm' value=\"".escape($password_confirm ?? null)."\" ".($action == 'add' ? "required='required'" : null)." onfocus='compare_passwords();' onkeyup='compare_passwords();' onblur='compare_passwords();'><br />\n";
	echo "			".$text['message-green_border_passwords_match']."\n";
	echo "		</td>";
	echo "	</tr>";


	if (permission_exists("user_groups")) {
		echo "	<tr>";
		echo "		<td class='vncellreq' valign='top'>User Groups</td>";
		echo "		<td class='vtable'>";

		$sql = "select ";
		$sql .= "	ug.*, g.domain_uuid as group_domain_uuid ";
		$sql .= "from ";
		$sql .= "	v_user_groups as ug, ";
		$sql .= "	v_groups as g ";
		$sql .= "where ";
		$sql .= "	ug.group_uuid = g.group_uuid ";
		$sql .= "	and (";
		$sql .= "		g.domain_uuid = :domain_uuid ";
		$sql .= "		or g.domain_uuid is null ";
		$sql .= "	) ";
		$sql .= "	and ug.domain_uuid = :domain_uuid ";
		$sql .= "	and ug.user_uuid = :user_uuid ";
		$sql .= "order by ";
		$sql .= "	g.domain_uuid desc, ";
		$sql .= "	g.group_name asc ";
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['user_uuid'] = $user_uuid;
		$database = new database;
		$user_groups = $database->select($sql, $parameters, 'all');
		if (is_array($user_groups)) {
			echo "<table cellpadding='0' cellspacing='0' border='0'>\n";
			foreach($user_groups as $field) {
				if (!empty($field['group_name'])) {
					echo "<tr>\n";
					echo "	<td class='vtable' style='white-space: nowrap; padding-right: 30px;' nowrap='nowrap'>";
					echo escape($field['group_name']).((!empty($field['group_domain_uuid'])) ? "@".$_SESSION['domains'][$field['group_domain_uuid']]['domain_name'] : null);
					echo "	</td>\n";
					if (permission_exists('user_group_delete') || if_group("superadmin")) {
						echo "	<td class='list_control_icons' style='width: 25px;'>\n";
						echo "		<a href='user_edit.php?id=".urlencode($user_uuid)."&domain_uuid=".urlencode($domain_uuid)."&group_uuid=".urlencode($field['group_uuid'])."&a=delete' alt='".$text['button-delete']."' onclick=\"return confirm('".$text['confirm-delete']."')\">".$v_link_label_delete."</a>\n";
						echo "	</td>\n";
					}
					echo "</tr>\n";
					if (is_uuid($field['group_uuid'])) {
						$assigned_groups[] = $field['group_uuid'];
					}
				}
			}
			echo "</table>\n";
		}
		unset($sql, $parameters, $user_groups, $field);

		$sql = "select * from v_groups ";
		$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
		if (!empty($assigned_groups) && is_array($assigned_groups) && sizeof($assigned_groups) > 0) {
			$sql .= "and group_uuid not in ('".implode("','",$assigned_groups)."') ";
		}
		$sql .= "order by domain_uuid desc, group_name asc ";
		$parameters['domain_uuid'] = $domain_uuid;
		$database = new database;
		$groups = $database->select($sql, $parameters, 'all');
		if (is_array($groups)) {
			if (isset($assigned_groups)) { echo "<br />\n"; }
			echo "<select name='group_uuid_name' class='formfld' style='width: auto; margin-right: 3px;' ".($action == 'add' ? "required='required'" : null).">\n";
			echo "	<option value=''></option>\n";
			foreach($groups as $field) {
				if ($field['group_level'] <= $_SESSION['user']['group_level']) {
					if (!isset($assigned_groups) || (isset($assigned_groups) && !in_array($field["group_uuid"], $assigned_groups))) {
						if (isset($group_uuid_name) && $group_uuid_name == $field['group_uuid']."|".$field['group_name']) { $selected = "selected='selected'"; } else { $selected = ''; }
						echo "	<option value='".$field['group_uuid']."|".$field['group_name']."' $selected>".$field['group_name'].((!empty($field['domain_uuid'])) ? "@".$_SESSION['domains'][$field['domain_uuid']]['domain_name'] : null)."</option>\n";
					}
				}
			}
			echo "</select>";
			if ($action == 'edit') {
				echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$_SESSION['theme']['button_icon_add'],'onclick'=>'submit_form();']);
			}
		}
		unset($sql, $parameters, $groups, $field);

		echo "		</td>";
		echo "	</tr>";
	}

	if (permission_exists('user_domain')) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	Domain\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <select class='formfld' name='domain_uuid'>\n";
		foreach ($_SESSION['domains'] as $row) {
			echo "	<option value='".escape($row['domain_uuid'])."' ".(($row['domain_uuid'] == $domain_uuid) ? "selected='selected'" : null).">".escape($row['domain_name'])."</option>\n";
		}
		echo "    </select>\n";
		echo "<br />\n";
		echo $text['description-domain_name']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}
	else {
		echo "<input type='hidden' name='domain_uuid' value='".escape($domain_uuid)."'>";
	}
	echo "</form>";
    
	echo "<script type=\"text/javascript\" language=\"JavaScript\">\n";
	echo "\n";
	echo "function enable_change(enable_over) {\n";
	echo "	var endis;\n";
	echo "	endis = !(document.iform.enable.checked || enable_over);\n";
	echo "	document.iform.range_from.disabled = endis;\n";
	echo "	document.iform.range_to.disabled = endis;\n";
	echo "}\n";
	echo "\n";
	if (permission_exists('extension_advanced')) {
		echo "function show_advanced_config() {\n";
		echo "	$('#show_advanced_box').slideToggle();\n";
		echo "	$('#show_advanced').slideToggle();\n";
		echo "}\n";
		echo "\n";
	}

//hide password fields before submit
	echo "	function submit_form() {\n";
	echo "		hide_password_fields();\n";
	echo "		$('form#frm').submit();\n";
	echo "	}\n";
	echo "</script>\n";

//include the footer
	require_once "resources/footer.php";

?>
