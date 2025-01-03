<?php
require_once dirname(__DIR__, 2) . "/resources/require.php";
require_once "resources/check_auth.php";
//begin the page content
	require_once "/var/www/fusionpbx/resources/header.php";
	if ($action == "update") {
	$document['title'] = $text['title-extension-edit'];
	}
//get the users
	$sql = "select * from v_users ";
	$sql .= "where domain_uuid = :domain_uuid ";
	if (!empty($assigned_user_uuids) && is_array($assigned_user_uuids) && @sizeof($assigned_user_uuids) != 0) {
		foreach ($assigned_user_uuids as $index => $assigned_user_uuid) {
			$sql .= "and user_uuid <> :user_uuid_".$index." ";
			$parameters['user_uuid_'.$index] = $assigned_user_uuid;
		}
	}
	$sql .= "and user_enabled = 'true' ";
	$sql .= "order by username asc ";
	$parameters['domain_uuid'] = $domain_uuid;
	$database = new database;
	$users = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters, $assigned_user_uuids, $assigned_user_uuid);

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
	echo "function copy_extension() {\n";
	echo "	var new_ext = prompt('".$text['message-extension']."');\n";
	echo "	if (new_ext != null) {\n";
	echo "		if (!isNaN(new_ext)) {\n";
	echo "			document.location.href='extension_copy.php?id=".escape($extension_uuid ?? '')."&ext=' + new_ext".(!empty($page) && is_numeric($page) ? " + '&page=".$page."'" : null).";\n";
	echo "		}\n";
	echo "		else {\n";
	echo "			var new_number_alias = prompt('".$text['message-number_alias']."');\n";
	echo "			if (new_number_alias != null) {\n";
	echo "				if (!isNaN(new_number_alias)) {\n";
	echo "					document.location.href='extension_copy.php?id=".escape($extension_uuid ?? '')."&ext=' + new_ext + '&alias=' + new_number_alias".(!empty($page) && is_numeric($page) ? " + '&page=".$page."'" : null).";\n";
	echo "				}\n";
	echo "			}\n";
	echo "		}\n";
	echo "	}\n";
	echo "}\n";
	echo "</script>";

	echo "<form method='post' name='frm' id='frm' action='/app/vsa/extension_edit.php'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>";
	if ($action == "add") {
		echo "<b>".$text['header-extension-add']."</b>";
	}
	if ($action == "update") {
		echo "<b>".$text['header-extension-edit']."</b>";
	}
	echo 	"</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','link'=>'extensions.php'.(isset($page) && is_numeric($page) ? '?page='.$page : null)]);
	if ($action == 'update') {
		$button_margin = 'margin-left: 15px;';
		if (permission_exists('xml_cdr_view')) {
			echo button::create(['type'=>'button','label'=>$text['button-cdr'],'icon'=>'info-circle','style'=>($button_margin ?? ''),'link'=>'../xml_cdr/xml_cdr.php?extension_uuid='.urlencode($extension_uuid)]);
			unset($button_margin);
		}
		if (permission_exists('follow_me') || permission_exists('call_forward') || permission_exists('do_not_disturb')) {
			echo button::create(['type'=>'button','label'=>$text['button-call_forward'],'icon'=>'project-diagram','style'=>($button_margin ?? ''),'link'=>'../call_forward/call_forward_edit.php?id='.urlencode($extension_uuid)]);
			unset($button_margin);
		}
		if (permission_exists('extension_setting_view')) {
			echo button::create(['type'=>'button','label'=>$text['button-settings'],'icon'=>$_SESSION['theme']['button_icon_settings'],'id'=>'btn_settings','style'=>'','link'=>PROJECT_PATH.'/app/extension_settings/extension_settings.php?id='.urlencode($extension_uuid)]);
		}
		if (permission_exists('extension_copy')) {
			echo button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$_SESSION['theme']['button_icon_copy'],'id'=>'btn_copy','style'=>'margin-left: 15px;','onclick'=>"copy_extension();"]);
		}

	}
	echo button::create(['type'=>'button','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','style'=>'margin-left: 15px;','onclick'=>'submit_form();']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "    Extensions\n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' align='left'>\n";
	if ($action == "add" || permission_exists("extension_extension")) {
		echo "    <input class='formfld' type='text' name='extension' autocomplete='new-password' maxlength='255' value=\"".escape($extension ?? '')."\" required='required'>\n";
		echo "    <input type='text' style='display: none;' disabled='disabled'>\n"; //help defeat browser auto-fill
		echo "<br />\n";
		echo $text['description-extension']."\n";
	}
	else {
		echo escape($extension);
	}
	echo "</td>\n";
	echo "</tr>\n";

	if (permission_exists('number_alias')) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "    ".$text['label-number_alias']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <input class='formfld' type='number' name='number_alias' autocomplete='new-password' maxlength='255' min='0' step='1' value=\"".escape($number_alias ?? '')."\">\n";
		echo "    <input type='text' style='display: none;' disabled='disabled'>\n"; //help defeat browser auto-fill
		echo "<br />\n";
		echo $text['description-number_alias']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "    Range\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <select class='formfld' name='range'>\n";
		echo "    <option value='1'>1</option>\n";
		echo "    <option value='2'>2</option>\n";
		echo "    <option value='3'>3</option>\n";
		echo "    <option value='4'>4</option>\n";
		echo "    <option value='5'>5</option>\n";
		echo "    <option value='6'>6</option>\n";
		echo "    <option value='7'>7</option>\n";
		echo "    <option value='8'>8</option>\n";
		echo "    <option value='9'>9</option>\n";
		echo "    <option value='10'>10</option>\n";
		echo "    <option value='15'>15</option>\n";
		echo "    <option value='20'>20</option>\n";
		echo "    <option value='25'>25</option>\n";
		echo "    <option value='30'>30</option>\n";
		echo "    <option value='35'>35</option>\n";
		echo "    <option value='40'>40</option>\n";
		echo "    <option value='45'>45</option>\n";
		echo "    <option value='50'>50</option>\n";
		echo "    <option value='75'>75</option>\n";
		echo "    <option value='100'>100</option>\n";
		echo "    <option value='150'>150</option>\n";
		echo "    <option value='200'>200</option>\n";
		echo "    <option value='250'>250</option>\n";
		echo "    <option value='500'>500</option>\n";
		echo "    <option value='750'>750</option>\n";
		echo "    <option value='1000'>1000</option>\n";
		echo "    <option value='5000'>5000</option>\n";
		echo "    </select>\n";
		echo "<br />\n";
		echo $text['description-range']."\n";
		echo "</td>\n";
		echo "</tr>\n";


	if (permission_exists('extension_user_edit')) {
		echo "	<tr>";
		echo "		<td class='vncell' valign='top'>Users</td>";
		echo "		<td class='vtable'>";
		if (!empty($assigned_users) && is_array($assigned_users) && @sizeof($assigned_users) != 0 && $action == "add") {
			echo "		<table width='30%'>\n";
			foreach($assigned_users as $field) {
				echo "		<tr>\n";
				echo "			<td class='vtable'><a href='/core/users/user_edit.php?id=".escape($field['user_uuid'])."'>".escape($field['username'])."</a></td>\n";
				echo "			<td>\n";
				echo "				<a href='#' onclick=\"if (confirm('".$text['confirm-delete']."')) { document.getElementById('delete_type').value = 'user'; document.getElementById('delete_uuid').value = '".$field['user_uuid']."'; document.getElementById('frm').submit(); }\" alt='".$text['button-delete']."'>$v_link_label_delete</a>\n";
				echo "			</td>\n";
				echo "		</tr>\n";
			}
			echo "		</table>\n";
			echo "		<br />\n";
		}
		if (is_array($users) && @sizeof($users) != 0) {
			echo "			<select name='extension_users[0][user_uuid]' id='user_uuid' class='formfld' style='width: auto;'>\n";
			echo "			<option value=''></option>\n";
			foreach($users as $field) {
				echo "			<option value='".escape($field['user_uuid'])."'>".escape($field['username'])."</option>\n";
			}
			echo "			</select>";
			if ($action == "add") {
				echo button::create(['type'=>'submit','label'=>$text['button-add'],'icon'=>$_SESSION['theme']['button_icon_add']]);
			}
			echo "			<br>\n";
		}
		echo "			Users\n";
		echo "			<br />\n";
		echo "		</td>";
		echo "	</tr>";
	}

	if (permission_exists('extension_domain')) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	Domain\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <select class='formfld' name='domain_uuid'>\n";
		foreach ($_SESSION['domains'] as $row) {
			if ($row['domain_uuid'] == $domain_uuid) {
				echo "    <option value='".escape($row['domain_uuid'])."' selected='selected'>".escape($row['domain_name'])."</option>\n";
			}
			else {
				echo "    <option value='".escape($row['domain_uuid'])."'>".escape($row['domain_name'])."</option>\n";
			}
		}
		echo "    </select>\n";
		echo "<br />\n";
		echo $text['description-domain_name']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	if (permission_exists("extension_user_context")) {
		echo "<tr>\n";
		echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
		echo " Context\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <input class='formfld' type='text' name='user_context' maxlength='255' value=\"".escape($user_context ?? '')."\" required='required'>\n";
		echo "<br />\n";
		echo $text['description-user_context']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	//--- begin: show_advanced -----------------------
	//--- end: show_advanced -----------------------


	echo "</form>";
	echo "<script>\n";

//hide password fields before submit
	echo "	function submit_form() {\n";
	echo "		hide_password_fields();\n";
	echo "		$('form#frm').submit();\n";
	echo "	}\n";
	echo "</script>\n";

//include the footer
	require_once "resources/footer.php";

?>
