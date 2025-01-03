<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$servername = "127.0.0.1";
$username = "root";
$password = "1Yb74rfBhrTwtJHh";
$dbname = "ipadd";

$conn = new mysqli($servername, $username, $password, $dbname);

// Проверка соединения
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

//$ZABBIX_URL = 'http://180.149.38.102/zabbix/api_jsonrpc.php';
//$ZABBIX_USER = 'Admin';
//$ZABBIX_PASSWORD = 'nbhgTRfdujgvnbhgtyFreCl4oGbSBwJP2';
//$ZABBIX_HOST_GROUP_ID = '20';
//$ZABBIX_TEMPLATE_ID = '10186';

//function get_zabbix_token($url, $user, $password) {
  //  $headers = array('Content-Type: application/json');
    //$data = json_encode(array(
      //  "jsonrpc" => "2.0",
        //"method" => "user.login",
        //"params" => array(
          //  "user" => $user,
            //"password" => $password
        //),
        //"id" => 1
    //));

    //$ch = curl_init($url);
    //curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    //curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    //$response = curl_exec($ch);
    //curl_close($ch);

    //$result = json_decode($response, true);
    //return $result['result'];
//}

//function add_host_to_zabbix($url, $token, $client_name, $ip_address, $group_id, $template_id) {
  //  $headers = array('Content-Type: application/json');
//    $host_name = "$client_name - $ip_address";
//    $data = json_encode(array(
  //      "jsonrpc" => "2.0",
    //    "method" => "host.create",
      //  "params" => array(
        //    "host" => $host_name,
          //  "interfaces" => array(array(
            //    "type" => 1,
              //  "main" => 1,
            //    "useip" => 1,
            //    "ip" => $ip_address,
            //    "dns" => "",
            //    "port" => "10050"
            //)),
            //"groups" => array(array(
              //  "groupid" => $group_id
            //)),
            //"templates" => array(array(
              //  "templateid" => $template_id
        //    ))
    //    ),
      //  "auth" => $token,
        //"id" => 1
    //));

    //$ch = curl_init($url);
    //curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    //curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    //$response = curl_exec($ch);
    //curl_close($ch);

    //$result = json_decode($response, true);
    //if (isset($result['error'])) {
    //    throw new Exception("Ошибка при добавлении узла в Zabbix: " . $result['error']['data']);
    //}
//}

function ipExistsInIptables($ipAddress) {
    $command = "sudo /usr/sbin/iptables -L INPUT -v -n";
    exec($command, $output, $returnCode);
    if ($returnCode === 0) {
        foreach ($output as $line) {
            if (strpos($line, $ipAddress) !== false) {
                return true;
            }
        }
    }
    return false;
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
  
    $ipAddress = $_POST['ipAddress'];
    $prefix = $_POST['prefix'];
    $current_user = $_SESSION['username'];
  
    if (!empty($ipAddress) && filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $ipAddress != '0.0.0.0') {

        if (ipExistsInIptables($ipAddress)) {
            $_SESSION['error_message'] = "IP $ipAddress уже существует в правилах iptables.";
        } else {

            $command = "sudo /usr/sbin/iptables -A INPUT -s $ipAddress -j ACCEPT";

            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                exec('sudo /usr/sbin/iptables-save > /etc/iptables/rules.v4 2>&1', $output, $saveReturnCode);

                if ($saveReturnCode === 0) {
                  
                    $currentTime = date("Y-m-d H:i:s");

                    $query = "INSERT INTO ip_attempts (ip_address, attempt_time, prefix, user, method) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $method = 'Web';
                    $stmt->bind_param("sssss", $ipAddress, $currentTime, $prefix, $current_user, $method);

                    if ($stmt->execute()) {
                        try {
  //                          $zabbix_token = get_zabbix_token($ZABBIX_URL, $ZABBIX_USER, $ZABBIX_PASSWORD);
                            //add_host_to_zabbix($ZABBIX_URL, $zabbix_token, $prefix, $ipAddress, $ZABBIX_HOST_GROUP_ID, $ZABBIX_TEMPLATE_ID);
                            $_SESSION['success_message'] = "IP $ipAddress - успешно добавлен!";
                        } catch (Exception $e) {
                            $_SESSION['error_message'] = $e->getMessage();
                        }

                        header("Location: " . $_SERVER['REQUEST_URI']);
                        exit();
                    } else {
                        $_SESSION['error_message'] = "Не удалось добавить IP - $ipAddress в базу данных.";
                    }
                    $stmt->close();
                } else {
                    $_SESSION['error_message'] = "Не удалось сохранить правила iptables. Код ошибки: $saveReturnCode. Выходные данные: " . implode("\n", $output);
                }
            } else {
                $_SESSION['error_message'] = "Не удалось добавить IP - $ipAddress. Код ошибки: $returnCode. Выходные данные: " . implode("\n", $output);
            }
        }
    } else {
        $_SESSION['error_message'] = "$ipAddress - Невалидный формат IP";
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add IP Address</title>
<link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="navigation-wrapper">
        <div class="navig">
            <nav>
                <ul>
                    <li><a href="IP.php">Add-IP</a></li>
                    <li><a href="history.php">History</a></li>
                </ul>
            </nav>
        </div>
    </div>
    <form class="logout-form" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <button type="submit" name="logout">logout</button>
    </form>

    <div class="container">
        <h2>Add IP Address</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <label for="prefix">Prefix:</label>
            <input type="text" id="prefix" name="prefix" required>
            <label for="ipAddress">IP Address:</label>
            <input type="text" id="ipAddress" name="ipAddress" required>
            <button type="submit">Add IP</button>
        </form>
    </div>

    <?php if(isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])): ?>
        <div class="error-message">
            <span class="closebtn" onclick="closeError()">&times;</span>
            <p><?php echo $_SESSION['error_message']; ?></p>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if(isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])): ?>
        <div class="success-message">
            <span class="closebtn" onclick="closeSuccess()">&times;</span>
            <p><?php echo $_SESSION['success_message']; ?></p>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="history">
        <h2>Saved IP Addresses</h2>
        <button class="history-button" onclick="redirectToAnotherPage()">All History</button>
        <script>
            function redirectToAnotherPage() {
                window.location.href = "history.php";
            }
        </script>
        <?php
        $query = "SELECT * FROM ip_attempts ORDER BY attempt_time DESC LIMIT 5";
        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>Prefix</th><th>IP Address</th><th>Attempt Time</th><th>User</th><th>Method</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row["prefix"]) . "</td>";
                echo "<td>" . htmlspecialchars($row["ip_address"]) . "</td>";
                echo "<td>" . htmlspecialchars($row["attempt_time"]) . "</td>";
                echo "<td>" . htmlspecialchars($row["user"]) . "</td>";
                echo "<td>" . htmlspecialchars($row["method"]) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Сохраненные IP-адреса не найдены.</p>";
        }
        $conn->close();
        ?>
    </div>
    <script>
        function closeError() {
            document.querySelector('.error-message').style.display = 'none';
        }

        function closeSuccess() {
            document.querySelector('.success-message').style.display = 'none';
        }
    </script>
</body>
</html>
