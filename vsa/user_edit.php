<?php

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//get user uuid
	if (!empty($_REQUEST["id"]) && ((is_uuid($_REQUEST["id"]) && permission_exists('user_edit')) || (is_uuid($_REQUEST["id"]) && $_REQUEST["id"] == $_SESSION['user_uuid']))) {
		$user_uuid = $_REQUEST["id"];
		$action = 'edit';
	}
	elseif (permission_exists('user_add') && !isset($_REQUEST["id"])) {
		$user_uuid = uuid();
		$action = 'add';
	}
	else {
		// load users own account
		header("Location: vsa.php?id=".urlencode($_SESSION['user_uuid']));
		exit;
	}

	$language = new text;
	$text = $language->get();

//get total user count from the database, check limit, if defined
	if (permission_exists('user_add') && $action == 'add' && !empty($_SESSION['limit']['users']['numeric'])) {
		$sql = "select count(*) ";
		$sql .= "from v_users ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$database = new database;
		$num_rows = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);

		if ($num_rows >= $_SESSION['limit']['users']['numeric']) {
			message::add($text['message-maximum_users'].' '.$_SESSION['limit']['users']['numeric'], 'negative');
			header('Location: /core/users/users.php');
			exit;
		}
	}

//required to be a superadmin to update an account that is a member of the superadmin group
	if (permission_exists('user_edit') && $action == 'edit') {
		$superadmins = superadmin_list();
		if (if_superadmin($superadmins, $user_uuid)) {
			if (!if_group("superadmin")) {
				echo "access denied";
				exit;
			}
		}
	}

//delete the group from the user
	if (!empty($_GET["a"]) && $_GET["a"] == "delete" && is_uuid($_GET["group_uuid"]) && is_uuid($user_uuid) && permission_exists("user_delete")) {
		//set the variables
			$group_uuid = $_GET["group_uuid"];
		//delete the group from the users
			$array['user_groups'][0]['group_uuid'] = $group_uuid;
			$array['user_groups'][0]['user_uuid'] = $user_uuid;

			$p = new permissions;
			$p->add('user_group_delete', 'temp');

			$database = new database;
			$database->app_name = 'users';
			$database->app_uuid = '112124b3-95c2-5352-7e9d-d14c0b88f207';
			$database->delete($array);
			unset($array);

			$p->delete('user_group_delete', 'temp');

		//redirect the user
			message::add($text['message-update']);
			header("Location: vsa_ext.php?id=".urlencode($user_uuid));
			exit;
	}

//retrieve password requirements
	$required['length'] = $_SESSION['users']['password_length']['numeric'];
	$required['number'] = ($_SESSION['users']['password_number']['boolean'] == 'true') ? true : false;
	$required['lowercase'] = ($_SESSION['users']['password_lowercase']['boolean'] == 'true') ? true : false;
	$required['uppercase'] = ($_SESSION['users']['password_uppercase']['boolean'] == 'true') ? true : false;
	$required['special'] = ($_SESSION['users']['password_special']['boolean'] == 'true') ? true : false;

//prepare the data
	if (!empty($_POST)) {

		//get the HTTP values and set as variables
			if (permission_exists('user_edit') && $action == 'edit') {
				$user_uuid = $_REQUEST["id"];
				$username_old = $_POST["username_old"];
			}
			$domain_uuid = $_POST["domain_uuid"];
			$username = $_POST["username"];
			$password = $_POST["password"];
			$password_confirm = $_POST["password_confirm"];
			$user_email = 'test@gmail.com';
			$user_status = $_POST["user_status"] ?? '';
			$user_language = 'ru-ru';
			$user_time_zone = 'Europe/Kyiv';

			if (permission_exists('contact_edit') && $action == 'edit') {
				$contact_uuid = $_POST["contact_uuid"];
			}
			else if (permission_exists('contact_add') && $action == 'add') {
				$contact_organization = $_POST["contact_organization"];
				$contact_name_given = $_POST["contact_name_given"];
				$contact_name_family = $_POST["contact_name_family"];
			}
			$group_uuid_name = $_POST["group_uuid_name"];
			$user_type = $_POST["user_type"];
			$user_enabled = $_POST["user_enabled"] ?? 'true';
			if (permission_exists('api_key')) {
				$api_key = $_POST["api_key"];
			}
			if (permission_exists('message_key')) {
				$message_key = $_POST["message_key"];
			}
			if (!empty($_SESSION['authentication']['methods']) && in_array('totp', $_SESSION['authentication']['methods'])) {
				$user_totp_secret = strtoupper($_POST["user_totp_secret"]);
			}

		//check required values
			if (empty($username)) {
				$invalid[] = $text['label-username'];
			}

			//require a username format: any, email, no_email
			if (!empty($_SESSION['users']['username_format']['text']) && $_SESSION['users']['username_format']['text'] != 'any') {
				if (
					($_SESSION['users']['username_format']['text'] == 'email' && !valid_email($username)) ||
					($_SESSION['users']['username_format']['text'] == 'no_email' && valid_email($username))
					) {
					message::add($text['message-username_format_invalid'], 'negative', 7500);
				}
			}

			//require unique globally or per domain
			if ((permission_exists('user_edit') && $action == 'edit' && $username != $username_old && !empty($username)) ||
				(permission_exists('user_add') && $action == 'add' && !empty($username))) {

				$sql = "select count(*) from v_users ";
				if (isset($_SESSION["users"]["unique"]["text"]) && $_SESSION["users"]["unique"]["text"] == "global") {
					$sql .= "where username = :username ";
				}
				else {
					$sql .= "where username = :username ";
					$sql .= "and domain_uuid = :domain_uuid ";
					$parameters['domain_uuid'] = $domain_uuid;
				}
				$parameters['username'] = $username;
				$database = new database;
				$num_rows = $database->select($sql, $parameters, 'column');
				if ($num_rows > 0) {
					message::add($text['message-username_exists'], 'negative', 7500);
				}
				unset($sql, $parameters);
			}

			//require the passwords to match
			if (!empty($password) && $password != $password_confirm) {
				message::add($text['message-password_mismatch'], 'negative', 7500);
			}

			//require passwords not allowed to be empty
			if (permission_exists('user_add') && $action == 'add') {
				if (empty($password)) {
					message::add($text['message-password_blank'], 'negative', 7500);
				}
				if (empty($group_uuid_name)) {
					$invalid[] = $text['label-group'];
				}
			}

			//require a value a valid email address format
			if (!valid_email($user_email)) {
				$invalid[] = $text['label-email'];
			}

			//require passwords with the defined required attributes: length, number, lower case, upper case, and special characters
			if (!empty($password)) {
				if (!empty($required['length']) && is_numeric($required['length']) && $required['length'] != 0) {
					if (strlen($password) < $required['length']) {
						$invalid[] = $text['label-characters'];
					}
				}
				if ($required['number']) {
					if (!preg_match('/(?=.*[\d])/', $password)) {
						$invalid[] = $text['label-numbers'];
					}
				}
				if ($required['lowercase']) {
					if (!preg_match('/(?=.*[a-z])/', $password)) {
						$invalid[] = $text['label-lowercase_letters'];
					}
				}
				if ($required['uppercase']) {
					if (!preg_match('/(?=.*[A-Z])/', $password)) {
						$invalid[] = $text['label-uppercase_letters'];
					}
				}
				if ($required['special']) {
					if (!preg_match('/(?=.*[\W])/', $password)) {
						$invalid[] = $text['label-special_characters'];
					}
				}
			}

		//return if error
			if (message::count() != 0 || !empty($invalid)) {
				if ($invalid) { message::add($text['message-required'].implode(', ', $invalid), 'negative', 7500); }
				persistent_form_values('store', $_POST);
				header("Location: vsa.php".(permission_exists('user_edit') && $action != 'add' ? "?id=".urlencode($user_uuid) : null));
				exit;
			}
			else {
				persistent_form_values('clear');
			}

		//save the data
			$i = $n = $x = $c = 0; //set initial array indexes

		//check to see if user language is set
			$sql = "select user_setting_uuid, user_setting_value from v_user_settings ";
			$sql .= "where user_setting_category = 'domain' ";
			$sql .= "and user_setting_subcategory = 'language' ";
			$sql .= "and user_uuid = :user_uuid ";
			$parameters['user_uuid'] = $user_uuid;
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			if (!empty($user_language) && (empty($row) || (!empty($row['user_setting_uuid']) && !is_uuid($row['user_setting_uuid'])))) {
				//add user setting to array for insert
					$array['user_settings'][$i]['user_setting_uuid'] = uuid();
					$array['user_settings'][$i]['user_uuid'] = $user_uuid;
					$array['user_settings'][$i]['domain_uuid'] = $domain_uuid;
					$array['user_settings'][$i]['user_setting_category'] = 'domain';
					$array['user_settings'][$i]['user_setting_subcategory'] = 'language';
					$array['user_settings'][$i]['user_setting_name'] = 'code';
					$array['user_settings'][$i]['user_setting_value'] = $user_language;
					$array['user_settings'][$i]['user_setting_enabled'] = 'true';
					$i++;
			}
			else {
				if (empty($row['user_setting_value']) || empty($user_language)) {
					$array_delete['user_settings'][0]['user_setting_category'] = 'domain';
					$array_delete['user_settings'][0]['user_setting_subcategory'] = 'language';
					$array_delete['user_settings'][0]['user_uuid'] = $user_uuid;

					$p = new permissions;
					$p->add('user_setting_delete', 'temp');

					$database = new database;
					$database->app_name = 'users';
					$database->app_uuid = '112124b3-95c2-5352-7e9d-d14c0b88f207';
					$database->delete($array_delete);
					unset($array_delete);

					$p->delete('user_setting_delete', 'temp');
				}
				if (!empty($user_language)) {
					//add user setting to array for update
					$array['user_settings'][$i]['user_setting_uuid'] = $row['user_setting_uuid'];
					$array['user_settings'][$i]['user_uuid'] = $user_uuid;
					$array['user_settings'][$i]['domain_uuid'] = $domain_uuid;
					$array['user_settings'][$i]['user_setting_category'] = 'domain';
					$array['user_settings'][$i]['user_setting_subcategory'] = 'language';
					$array['user_settings'][$i]['user_setting_name'] = 'code';
					$array['user_settings'][$i]['user_setting_value'] = $user_language;
					$array['user_settings'][$i]['user_setting_enabled'] = 'true';
					$i++;
				}
			}
			unset($sql, $parameters, $row);

		//check to see if user time zone is set
			$sql = "select user_setting_uuid, user_setting_value from v_user_settings ";
			$sql .= "where user_setting_category = 'domain' ";
			$sql .= "and user_setting_subcategory = 'time_zone' ";
			$sql .= "and user_uuid = :user_uuid ";
			$parameters['user_uuid'] = $user_uuid;
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			if (!empty($user_time_zone) && (empty($row) || (!empty($row['user_setting_uuid']) && !is_uuid($row['user_setting_uuid'])))) {
				//add user setting to array for insert
				$array['user_settings'][$i]['user_setting_uuid'] = uuid();
				$array['user_settings'][$i]['user_uuid'] = $user_uuid;
				$array['user_settings'][$i]['domain_uuid'] = $domain_uuid;
				$array['user_settings'][$i]['user_setting_category'] = 'domain';
				$array['user_settings'][$i]['user_setting_subcategory'] = 'time_zone';
				$array['user_settings'][$i]['user_setting_name'] = 'name';
				$array['user_settings'][$i]['user_setting_value'] = $user_time_zone;
				$array['user_settings'][$i]['user_setting_enabled'] = 'true';
				$i++;
			}
			else {
				if (empty($row['user_setting_value']) || empty($user_time_zone)) {
					$array_delete['user_settings'][0]['user_setting_category'] = 'domain';
					$array_delete['user_settings'][0]['user_setting_subcategory'] = 'time_zone';
					$array_delete['user_settings'][0]['user_uuid'] = $user_uuid;

					$p = new permissions;
					$p->add('user_setting_delete', 'temp');

					$database = new database;
					$database->app_name = 'users';
					$database->app_uuid = '112124b3-95c2-5352-7e9d-d14c0b88f207';
					$database->delete($array_delete);
					unset($array_delete);

					$p->delete('user_setting_delete', 'temp');
				}
				if (!empty($user_time_zone)) {
					//add user setting to array for update
					$array['user_settings'][$i]['user_setting_uuid'] = $row['user_setting_uuid'];
					$array['user_settings'][$i]['user_uuid'] = $user_uuid;
					$array['user_settings'][$i]['domain_uuid'] = $domain_uuid;
					$array['user_settings'][$i]['user_setting_category'] = 'domain';
					$array['user_settings'][$i]['user_setting_subcategory'] = 'time_zone';
					$array['user_settings'][$i]['user_setting_name'] = 'name';
					$array['user_settings'][$i]['user_setting_value'] = $user_time_zone;
					$array['user_settings'][$i]['user_setting_enabled'] = 'true';
					$i++;
				}
			}
			unset($sql, $parameters, $row);

		//check to see if message key is set
			if (permission_exists('message_key')) {
				$sql = "select user_setting_uuid, user_setting_value from v_user_settings ";
				$sql .= "where user_setting_category = 'message' ";
				$sql .= "and user_setting_subcategory = 'key' ";
				$sql .= "and user_uuid = :user_uuid ";
				$parameters['user_uuid'] = $user_uuid;
				$database = new database;
				$row = $database->select($sql, $parameters, 'row');
				if (!empty($message_key) && (empty($row) || (!empty($row['user_setting_uuid']) && !is_uuid($row['user_setting_uuid'])))) {
					//add user setting to array for insert
					$array['user_settings'][$i]['user_setting_uuid'] = uuid();
					$array['user_settings'][$i]['user_uuid'] = $user_uuid;
					$array['user_settings'][$i]['domain_uuid'] = $domain_uuid;
					$array['user_settings'][$i]['user_setting_category'] = 'message';
					$array['user_settings'][$i]['user_setting_subcategory'] = 'key';
					$array['user_settings'][$i]['user_setting_name'] = 'text';
					$array['user_settings'][$i]['user_setting_value'] = $message_key;
					$array['user_settings'][$i]['user_setting_enabled'] = 'true';
					$i++;
				}
				else {
					if (empty($row['user_setting_value']) || empty($message_key)) {
						$array_delete['user_settings'][0]['user_setting_category'] = 'message';
						$array_delete['user_settings'][0]['user_setting_subcategory'] = 'key';
						$array_delete['user_settings'][0]['user_uuid'] = $user_uuid;

						$p = new permissions;
						$p->add('user_setting_delete', 'temp');

						$database = new database;
						$database->app_name = 'users';
						$database->app_uuid = '112124b3-95c2-5352-7e9d-d14c0b88f207';
						$database->delete($array_delete);
						unset($array_delete);

						$p->delete('user_setting_delete', 'temp');
					}
					if (!empty($message_key)) {
						//add user setting to array for update
						$array['user_settings'][$i]['user_setting_uuid'] = $row['user_setting_uuid'];
						$array['user_settings'][$i]['user_uuid'] = $user_uuid;
						$array['user_settings'][$i]['domain_uuid'] = $domain_uuid;
						$array['user_settings'][$i]['user_setting_category'] = 'message';
						$array['user_settings'][$i]['user_setting_subcategory'] = 'key';
						$array['user_settings'][$i]['user_setting_name'] = 'text';
						$array['user_settings'][$i]['user_setting_value'] = $message_key;
						$array['user_settings'][$i]['user_setting_enabled'] = 'true';
						$i++;
					}
				}
			}
			unset($sql, $parameters, $row);

		//assign the user to the group
			if ((permission_exists('user_add') || permission_exists('user_edit')) && !empty($_REQUEST["group_uuid_name"])) {
				$group_data = explode('|', $group_uuid_name);
				$group_uuid = $group_data[0];
				$group_name = $group_data[1];

				//compare the group level to only add groups at the same level or lower than the user
				$sql = "select * from v_groups ";
				$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
				$sql .= "and group_uuid = :group_uuid ";
				$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
				$parameters['group_uuid'] = $group_uuid;
				$database = new database;
				$row = $database->select($sql, $parameters, 'row');
				if ($row['group_level'] <= $_SESSION['user']['group_level']) {
					$array['user_groups'][$n]['user_group_uuid'] = uuid();
					$array['user_groups'][$n]['domain_uuid'] = $domain_uuid;
					$array['user_groups'][$n]['group_name'] = $group_name;
					$array['user_groups'][$n]['group_uuid'] = $group_uuid;
					$array['user_groups'][$n]['user_uuid'] = $user_uuid;
					$n++;
				}
				unset($parameters);
			}

		//update domain, if changed
			if ((permission_exists('user_add') || permission_exists('user_edit')) && permission_exists('user_domain')) {
				//adjust group user records
					$sql = "select user_group_uuid from v_user_groups ";
					$sql .= "where user_uuid = :user_uuid ";
					$parameters['user_uuid'] = $user_uuid;
					$database = new database;
					$result = $database->select($sql, $parameters, 'all');
					if (is_array($result)) {
						foreach ($result as $row) {
							//add group user to array for update
							$array['user_groups'][$n]['user_group_uuid'] = $row['user_group_uuid'];
							$array['user_groups'][$n]['domain_uuid'] = $domain_uuid;
							$n++;
						}
					}
					unset($sql, $parameters);
				//adjust user setting records
					$sql = "select user_setting_uuid from v_user_settings ";
					$sql .= "where user_uuid = :user_uuid ";
					$parameters['user_uuid'] = $user_uuid;
					$database = new database;
					$result = $database->select($sql, $parameters);
					if (is_array($result)) {
						foreach ($result as $row) {
							//add user setting to array for update
							$array['user_settings'][$i]['user_setting_uuid'] = $row['user_setting_uuid'];
							$array['user_settings'][$i]['domain_uuid'] = $domain_uuid;
							$i++;
						}
					}
					unset($sql, $parameters);
				//unassign any foreign domain groups
					$sql = "delete from v_user_groups ";
					$sql .= "where domain_uuid = :domain_uuid ";
					$sql .= "and user_uuid = :user_uuid ";
					$sql .= "and group_uuid not in (";
					$sql .= "	select group_uuid from v_groups where domain_uuid = :domain_uuid or domain_uuid is null ";
					$sql .= ") ";
					$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
					$parameters['user_uuid'] = $user_uuid;
					$database = new database;
					$database->execute($sql, $parameters);
					unset($sql, $parameters);
			}

		//add contact to array for insert
			if ($action == 'add' && permission_exists('user_add') && permission_exists('contact_add')) {
				$contact_uuid = uuid();
				$array['contacts'][$c]['domain_uuid'] = $domain_uuid;
				$array['contacts'][$c]['contact_uuid'] = $contact_uuid;
				$array['contacts'][$c]['contact_type'] = 'user';
				$array['contacts'][$c]['contact_organization'] = $contact_organization;
				$array['contacts'][$c]['contact_name_given'] = $contact_name_given;
				$array['contacts'][$c]['contact_name_family'] = $contact_name_family;
				$array['contacts'][$c]['contact_nickname'] = $username;
				$c++;
				if (permission_exists('contact_email_add')) {
					$contact_email_uuid = uuid();
					$array['contact_emails'][$c]['contact_email_uuid'] = $contact_email_uuid;
					$array['contact_emails'][$c]['domain_uuid'] = $domain_uuid;
					$array['contact_emails'][$c]['contact_uuid'] = $contact_uuid;
					$array['contact_emails'][$c]['email_address'] = $user_email;
					$array['contact_emails'][$c]['email_primary'] = '1';
					$c++;
				}
			}

		//set the password hash cost
			$options = array('cost' => 10);

		//add user setting to array for update
			$array['users'][$x]['user_uuid'] = $user_uuid;
			$array['users'][$x]['domain_uuid'] = $domain_uuid;
			if (!empty($username) && (empty($username_old) || $username != $username_old)) {
				$array['users'][$x]['username'] = $username;
			}
			if (!empty($password) && $password == $password_confirm) {
				$array['users'][$x]['password'] = password_hash($password, PASSWORD_DEFAULT, $options);
				$array['users'][$x]['salt'] = null;
			}
			$array['users'][$x]['user_email'] = $user_email;
			$array['users'][$x]['user_status'] = $user_status;
			if (permission_exists('user_add') || permission_exists('user_edit')) {
				if (permission_exists('api_key')) {
					$array['users'][$x]['api_key'] = (!empty($api_key)) ? $api_key : null;
				}
				if (!empty($_SESSION['authentication']['methods']) && in_array('totp', $_SESSION['authentication']['methods'])) {
					$array['users'][$x]['user_totp_secret'] = $user_totp_secret;
				}
				$array['users'][$x]['user_type'] = $user_type;
				$array['users'][$x]['user_enabled'] = $user_enabled;
				if (permission_exists('contact_add')) {
					$array['users'][$x]['contact_uuid'] = (!empty($contact_uuid)) ? $contact_uuid : null;
				}
				if ($action == 'add') {
					$array['users'][$x]['add_user'] = $_SESSION["user"]["username"];
					$array['users'][$x]['add_date'] = date("Y-m-d H:i:s.uO");
				}
			}
			$x++;

		//add the user_edit permission
			$p = new permissions;
			$p->add("user_setting_add", "temp");
			$p->add("user_setting_edit", "temp");
			$p->add("user_edit", "temp");
			$p->add('user_group_add', 'temp');

		//save the data
			$database = new database;
			$database->app_name = 'users';
			$database->app_uuid = '112124b3-95c2-5352-7e9d-d14c0b88f207';
			$database->save($array);
			//$message = $database->message;

		//remove the temporary permission
			$p->delete("user_setting_add", "temp");
			$p->delete("user_setting_edit", "temp");
			$p->delete("user_edit", "temp");
			$p->delete('user_group_add', 'temp');

		//if call center installed
			if ($action == 'edit' && permission_exists('user_edit') && file_exists($_SERVER["PROJECT_ROOT"]."/app/call_centers/app_config.php")) {
				//get the call center agent uuid
					$sql = "select call_center_agent_uuid from v_call_center_agents ";
					$sql .= "where domain_uuid = :domain_uuid ";
					$sql .= "and user_uuid = :user_uuid ";
					$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
					$parameters['user_uuid'] = $user_uuid;
					$database = new database;
					$call_center_agent_uuid = $database->select($sql, $parameters, 'column');
					unset($sql, $parameters);

				//update the user_status
					if (isset($call_center_agent_uuid) && is_uuid($call_center_agent_uuid) && !empty($user_status)) {
						$esl = event_socket::create();
						$switch_cmd = "callcenter_config agent set status ".$call_center_agent_uuid." '".$user_status."'";
						$switch_result = event_socket::api($switch_cmd);
					}

				//update the user state
					if (isset($call_center_agent_uuid) && is_uuid($call_center_agent_uuid)) {
						$esl = event_socket::create();
						$cmd = "callcenter_config agent set state ".$call_center_agent_uuid." Waiting";
						$response = event_socket::api($cmd);
					}
			}

		//response message
			if ($action == 'edit') {
				message::add($text['message-update'],'positive');
			}
			else {
				message::add($text['message-add'],'positive');
			}
			header("Location: vsa_ext.php?id=".urlencode($user_uuid));
			exit;
	}

//populate form
	if (persistent_form_values('exists')) {
		//populate the form with values from session variable
			persistent_form_values('load');
		//clear, set $unsaved flag
			persistent_form_values('clear');
	}
	else {
		//populate the form with values from db
			if ($action == 'edit') {
				$sql = "select domain_uuid, user_uuid, username, user_email, api_key, user_totp_secret, ";
				$sql .= "user_type, user_enabled, contact_uuid, cast(user_enabled as text), user_status ";
				$sql .= "from v_users ";
				$sql .= "where user_uuid = :user_uuid ";
				if (!permission_exists('user_all')) {
					$sql .= "and domain_uuid = :domain_uuid ";
					$parameters['domain_uuid'] = $domain_uuid;
				}
				$parameters['user_uuid'] = $user_uuid;
				$database = new database;
				$row = $database->select($sql, $parameters, 'row');
				if (is_array($row) && sizeof($row) > 0) {
					$domain_uuid = $row["domain_uuid"];
					$user_uuid = $row["user_uuid"];
					$username = $row["username"];
					$user_email = $row["user_email"];
					$api_key = $row["api_key"];
					$user_totp_secret = $row["user_totp_secret"];
					$user_type = $row["user_type"];
					$user_enabled = $row["user_enabled"];
					if (permission_exists('contact_view')) {
						$contact_uuid = $row["contact_uuid"];
					}
					$user_status = $row["user_status"];
				}
				else {
					message::add($text['message-invalid_user'], 'negative', 7500);
					header("Location: vsa.php?id=".$_SESSION['user_uuid']);
					exit;
				}
				unset($sql, $parameters, $row);

				//get user settings
				$sql = "select * from v_user_settings ";
				$sql .= "where user_uuid = :user_uuid ";
				$sql .= "and user_setting_enabled = 'true' ";
				$parameters['user_uuid'] = $user_uuid;
				$database = new database;
				$result = $database->select($sql, $parameters, 'all');
				if (is_array($result)) {
					foreach($result as $row) {
						$name = $row['user_setting_name'];
						$category = $row['user_setting_category'];
						$subcategory = $row['user_setting_subcategory'];
						if (empty($subcategory)) {
							//$$category[$name] = $row['domain_setting_value'];
							$user_settings[$category][$name] = $row['user_setting_value'];
						}
						else {
							$user_settings[$category][$subcategory][$name] = $row['user_setting_value'];
						}
					}
				}
				unset($sql, $parameters, $result, $row);
			}
	}

//set the defaults
	if (empty($user_enabled)) { $user_enabled = "true"; }
	if (empty($user_totp_secret)) { $user_totp_secret = ""; }
?>
