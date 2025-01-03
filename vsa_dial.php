<?php
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//get the domains
	$sql = "select * from v_domains ";
	$sql .= "where domain_enabled = 'true' ";
	$database = new database;
	$domains = $database->select($sql, null, 'all');
	unset($sql);

//get the gateways
	$sql = "select * from v_gateways ";
	$sql .= "where enabled = 'true' ";
	if (permission_exists('outbound_route_any_gateway')) {
		$sql .= "order by domain_uuid = :domain_uuid DESC, gateway ";
	}
	else {
		$sql .= "and domain_uuid = :domain_uuid ";
		
	}
	$parameters['domain_uuid'] = $domain_uuid;
	$database = new database;
	$gateways = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//get the bridges
	if (permission_exists('bridge_view')) {
		$sql = "select * from v_bridges ";
		$sql .= "where bridge_enabled = 'true' ";
		$sql .= "and domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
		$database = new database;
		$bridges = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);
	}

	$document['title'] = $text['title-dialplan-outbound-add'];
	require_once "/var/www/fusionpbx/resources/header.php";
//show the content
	echo "<form method='post' name='frm' id='frm' action='/app/vsa/dialplan_outbound_add.php'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['label-outbound-routes']."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','link'=>PROJECT_PATH.'/app/dialplans/dialplans.php?app_uuid=8c914ec3-9fc0-8ab5-4cda-6c9288bdc9a3']);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','style'=>'margin-left: 15px;']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-outbound-routes']."\n";
	echo "<br /><br />\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap>\n";
	echo "	Gateway\n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' align='left'>\n";

	echo "<script>\n";
	echo "var Objs;\n";
	echo "\n";
	echo "function changeToInput(obj){\n";
	echo "	tb=document.createElement('INPUT');\n";
	echo "	tb.type='text';\n";
	echo "	tb.name=obj.name;\n";
	echo "	tb.setAttribute('class', 'formfld');\n";
	echo "	tb.setAttribute('style', 'width: 400px;');\n";
	echo "	tb.value=obj.options[obj.selectedIndex].value;\n";
	echo "	tbb=document.createElement('INPUT');\n";
	echo "	tbb.setAttribute('class', 'btn');\n";
	echo "	tbb.setAttribute('style', 'margin-left: 4px;');\n";
	echo "	tbb.type='button';\n";
	echo "	tbb.value=$('<div />').html('&#9665;').text();\n";
	echo "	tbb.objs=[obj,tb,tbb];\n";
	echo "	tbb.onclick=function(){ Replace(this.objs); }\n";
	echo "	obj.parentNode.insertBefore(tb,obj);\n";
	echo "	obj.parentNode.insertBefore(tbb,obj);\n";
	echo "	obj.parentNode.removeChild(obj);\n";
	echo "}\n";
	echo "\n";
	echo "function Replace(obj){\n";
	echo "	obj[2].parentNode.insertBefore(obj[0],obj[2]);\n";
	echo "	obj[0].parentNode.removeChild(obj[1]);\n";
	echo "	obj[0].parentNode.removeChild(obj[2]);\n";
	echo "}\n";
	echo "function update_dialplan_expression() {\n";
	echo "	if ( document.getElementById('dialplan_expression_select').value == 'CUSTOM_PREFIX' ) {\n";
	echo "		document.getElementById('outbound_prefix').value = '';\n";
	echo "		$('#enter_custom_outbound_prefix_box').slideDown();\n";
	echo "	} else { \n";
	echo "		document.getElementById('dialplan_expression').value += document.getElementById('dialplan_expression_select').value + '\\n';\n";
	echo "		document.getElementById('outbound_prefix').value = '';\n";
	echo "		$('#enter_custom_outbound_prefix_box').slideUp();\n";
	echo "	}\n";
	echo "}\n";
	echo "function update_outbound_prefix() {\n";
	echo "	document.getElementById('dialplan_expression').value += '^' + document.getElementById('outbound_prefix').value + '(\\\d*)\$' + '\\n';\n";
	echo "}\n";
	echo "</script>\n";
	echo "\n";

	echo "<select name=\"gateway\" id=\"gateway\" class=\"formfld\" $onchange>\n";
	echo "<option value=''></option>\n";
	echo "<optgroup label='Шлюз'>\n";
	$previous_domain_uuid = '';
	foreach($gateways as $row) {
		if (permission_exists('outbound_route_any_gateway')) {
			if ($previous_domain_uuid != $row['domain_uuid']) {
				$domain_name = '';
				foreach($domains as $field) {
					if ($row['domain_uuid'] == $field['domain_uuid']) {
						$domain_name = $field['domain_name'];
						break;
					}
				}
				if (empty($domain_name)) { $domain_name = $text['label-global']; }
				echo "</optgroup>";
				echo "<optgroup label='&nbsp; &nbsp;".$domain_name."'>\n";
			}
			if (!empty($gateway_name) && $row['gateway'] == $gateway_name) {
				echo "<option value=\"".escape($row['gateway_uuid']).":".escape($row['gateway'])."\" selected=\"selected\">".escape($row['gateway'])."</option>\n";
			}
			else {
				echo "<option value=\"".escape($row['gateway_uuid']).":".escape($row['gateway'])."\">".escape($row['gateway'])."</option>\n";
			}
		}
		else {
			if (!empty($gateway_name) && $row['gateway'] == $gateway_name) {
				echo "<option value=\"".escape($row['gateway_uuid']).":".escape($row['gateway'])."\" $onchange selected=\"selected\">".escape($row['gateway'])."</option>\n";
			}
			else {
				echo "<option value=\"".escape($row['gateway_uuid']).":".escape($row['gateway'])."\">".escape($row['gateway'])."</option>\n";
			}
		}
		$previous_domain_uuid = $row['domain_uuid'];
	}
	echo "	</optgroup>\n";
	if (permission_exists('bridge_view')) {
		echo "	<optgroup label='".$text['label-bridges']."'>\n";
		foreach($bridges as $row) {
			echo "		<option value=\"bridge:".$row['bridge_destination']."\">".$row['bridge_name']."</option>\n";
		}
		echo "	</optgroup>\n";
	}
	echo "	<optgroup label='".$text['label-add-options']."'>\n";
	echo "		<option value=\"enum\">enum</option>\n";
	echo "		<option value=\"freetdm\">freetdm</option>\n";
	echo "		<option value=\"transfer:\$1 XML \${domain_name}\">transfer</option>\n";
	echo "		<option value=\"xmpp\">xmpp</option>\n";
	echo "		<option value=\"hangup\">hangup</option>\n";
	echo "	</optgroup>\n";
	echo "</select>\n";
	echo "<br />\n";
	echo $text['message-add-options']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "  <td valign=\"top\" class=\"vncellreq\">Dst</td>\n";
	echo "  <td align='left' class=\"vtable\">";

	echo "	<div id=\"dialplan_expression_box\" >\n";
	echo "		<textarea name=\"dialplan_expression\" id=\"dialplan_expression\" class=\"formfld\" cols=\"30\" rows=\"4\" style='width: 350px;' wrap=\"off\"></textarea>\n";
	echo "		<br>\n";
	echo "	</div>\n";

	echo "	<div id=\"enter_custom_outbound_prefix_box\" style=\"display:none\">\n";
	echo "		<input class='formfld' style='width: 10%;' type='text' name='custom-outbound-prefix' id=\"outbound_prefix\" maxlength='255'>\n";
	echo "		<input type='button' class='btn' name='' onclick=\"update_outbound_prefix()\" value='".$text['button-add']."'>\n";
	//echo "		<br />".$text['description-enter-custom-outbound-prefix'].".\n";
	echo "	</div>\n";

	echo "	<select name='dialplan_expression_select' id='dialplan_expression_select' onchange=\"update_dialplan_expression()\" class='formfld'>\n";
	echo "	<option></option>\n";
	echo "	<option value='^\+?(\d+)$'>Основной</option>\n";
	echo "	</select>\n";
	echo "	<span class=\"vexpl\">\n";
	echo "	<br />\n";
	echo "	".$text['description-shortcut']." \n";
	echo "	</span></td>\n";
	echo "</tr>";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	Context\n";
	echo "</td>\n";
	echo "<td colspan='4' class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='context' maxlength='255' value=\"".escape($context)."\">\n";
	echo "<br />\n";
	echo $text['description-enter-prefix']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	User Prefix\n";
	echo "</td>\n";
	echo "<td colspan='4' class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='user_prefix' maxlength='255' value=\"".escape($user_prefix)."\">\n";
	echo "<br />\n";
	echo $text['description-limit']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	Name\n";
	echo "</td>\n";
	echo "<td colspan='4' class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='name' maxlength='255' value=\"".escape($name)."\">\n";
	echo "<br />\n";
	echo $text['description-limit']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	Description\n";
	echo "</td>\n";
	echo "<td colspan='4' class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='dialplan_description' maxlength='255' value=\"".escape($dialplan_description)."\">\n";
	echo "<br />\n";
	echo $text['description-description']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	if (!empty($action) && $action == "update") {
		echo "<input type='hidden' name='dialplan_uuid' value='".escape($dialplan_uuid)."'>\n";
	}
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//show the footer
	require_once "resources/footer.php";

?>
