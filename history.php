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
?>

<?php
date_default_timezone_set('Europe/Kiev');

$defaultFrom = date('Y-m-d') . 'T00:00'; 
$defaultTo = date('Y-m-d') . 'T23:59'; 

$ip = '';
$prefix = '';
$user = '';
$method = '';
$from = isset($_GET['from']) ? $_GET['from'] : $defaultFrom;
$to = isset($_GET['to']) ? $_GET['to'] : $defaultTo;

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['reset'])) {
        header("Location: reset.php");
        exit();
    } else {

        $_SESSION['ip'] = isset($_GET['ip']) ? $_GET['ip'] : '';
        $_SESSION['prefix'] = isset($_GET['prefix']) ? $_GET['prefix'] : '';
        $_SESSION['user'] = isset($_GET['user']) ? $_GET['user'] : '';
        $_SESSION['method'] = isset($_GET['method']) ? $_GET['method'] : '';
        $_SESSION['from'] = isset($_GET['from']) ? $_GET['from'] : $from;
        $_SESSION['to'] = isset($_GET['to']) ? $_GET['to'] : $to;
    }
}

if(isset($_SESSION['ip'])) {
    $ip = $_SESSION['ip'];
}
if(isset($_SESSION['prefix'])) {
    $prefix = $_SESSION['prefix'];
}
if(isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
}
if(isset($_SESSION['method'])) {
    $method = $_SESSION['method'];
}
if(isset($_SESSION['from'])) {
    $from = $_SESSION['from'];
}
if(isset($_SESSION['to'])) {
    $to = $_SESSION['to'];
}

session_write_close();
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="history.css">
    <title>History</title>
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
        <button type="submit" name="logout">Logout</button>
    </form>

    <div class="main-container">
        <div class="filter-container">
            <div class="container">
                <h2>Filter</h2>
                <form id="search-form" method="GET" action="">
                    <label for="ip">IP Address:</label>
                    <input type="text" id="ip" name="ip" placeholder="Enter IP Address" value="<?php echo htmlspecialchars($ip); ?>">
                    
                    <label for="prefix">Prefix:</label>
                    <input type="text" id="prefix" name="prefix" placeholder="Enter Prefix" value="<?php echo htmlspecialchars($prefix); ?>">
                    
                    <label for="user">User Name:</label>
                    <input type="text" id="user" name="user" placeholder="Enter User Name" value="<?php echo htmlspecialchars($user); ?>">
                    
                    <label for="method">Method:</label>
                    <input type="text" id="method" name="method" placeholder="Enter Method" value="<?php echo htmlspecialchars($method); ?>">
                    
                    <label for="from">From:</label>
                    <input type="datetime-local" id="from" name="from" value="<?php echo htmlspecialchars($from); ?>">
                    
                    <label for="to">To:</label>
                    <input type="datetime-local" id="to" name="to" value="<?php echo htmlspecialchars($to); ?>">
                    
                    <input type="submit" value="Search" class="search-button">
                    <button type="submit" name="reset">Reset</button>
                    <input type="hidden" name="reset_pressed" value="1">
                </form>
            </div>
        </div>

        <div class="history-container">
            <div class="history">
                <h2>History</h2>
                <table class="ip-table">
                    <?php
 $servername = "127.0.0.1";
$username = "root";
$password = "1Yb74rfBhrTwtJHh";
$dbname = "ipadd";

                    $connection = new mysqli($servername, $username, $password, $dbname);

                    if ($connection->connect_error) {
                        die("Connection failed: " . $connection->connect_error);
                    }

                    $ip = isset($_GET['ip']) ? $_GET['ip'] : '';
                    $prefix = isset($_GET['prefix']) ? $_GET['prefix'] : '';
                    $user = isset($_GET['user']) ? $_GET['user'] : '';
                    $method = isset($_GET['method']) ? $_GET['method'] : '';
                    $from = isset($_GET['from']) ? $_GET['from'] : '';
                    $to = isset($_GET['to']) ? $_GET['to'] : '';

                    $sql = "SELECT * FROM ip_attempts WHERE 1=1";

                    if (!empty($ip)) {
                        $sql .= " AND ip_address LIKE '%$ip%'";
                    }
                    if (!empty($prefix)) {
                        $sql .= " AND prefix LIKE '%$prefix%'";
                    }
                    if (!empty($user)) {
                        $sql .= " AND user LIKE '%$user%'";
                    }
                    if (!empty($method)) {
                        $sql .= " AND method LIKE '%$method%'";
                    }
                    if (!empty($from) && !empty($to)) {
                        $sql .= " AND attempt_time BETWEEN '$from' AND '$to'";
                    }

                    $sql .= " ORDER BY attempt_time DESC";

        
                    $result = $connection->query($sql);

  
                    if ($result && $result->num_rows > 0) {
                        echo "<table>";
                        echo "<tr><th>Prefix</th><th>IP Address</th><th>Attempt Time</th><th>User</th><th>Method</th></tr>";
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $row["prefix"] . "</td>";
                            echo "<td>" . $row["ip_address"] . "</td>";
                            echo "<td>" . $row["attempt_time"] . "</td>";
                            echo "<td>" . $row["user"] . "</td>";
                            echo "<td>" . $row["method"] . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    } else {
                        echo "<p>No results found</p>";
                    }

                    $connection->close();
                    ?>
                </table>
            </div>
        </div>
    </div>
</body>
</html>

