<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Данные по ID</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 20px;
            padding: 20px;
            background: url('123.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #abb2bf;
        }
        .data-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: rgba(0, 0, 0, 0.85);
            padding: 30px;
            border-radius: 12px;
            margin-top: 30px;
            width: 60%;
            height: auto;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.5);
        }
        .data-block {
            width: 100%;
            background-color: #1f2225;
            border: 1px solid #444;
            border-radius: 8px;
            margin-bottom: 6px;
            padding: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s ease;
        }
        .data-block:hover {
            background-color: #32373d;
        }
        .data-block h3 {
            margin: 0;
            color: #ffffff;
            font-size: 13px;
            font-weight: bold;
        }
        .data-block p {
            margin: 0;
            color: #dcdfe4;
            font-size: 13px;
            font-weight: 400;
        }
        .status-reject {
            color: #ff6b6b;
            font-weight: bold;
        }
        .status-done {
            color: #4caf50;
            font-weight: bold;
        }
        .status-wait {
            color: #ffa500;
            font-weight: bold;
        }
        .divider {
            border-bottom: 1px solid #444;
            margin: 6px 0;
        }
    </style>
</head>
<body>

<?php

if (isset($_GET['id'])) {
    $id = pg_escape_string($_GET['id']);

    $host = '127.0.0.1';
    $dbname = 'fusionpbx';
    $user = 'fusionpbx';
    $password = 'YUFccgJsuBnoPaVyTm5Xk78TEhU';
    $port = '5432';
  
    $conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password sslmode=prefer");

    if (!$conn) {
        die("Ошибка подключения: " . pg_last_error());
    }
  
    $result = pg_query($conn, "SELECT id, username, password, domain, internal_number, context_value, quantity, user_prefix, ip_address, status FROM v_inf WHERE id = '$id'");

    if (!$result) {
        die("Ошибка выполнения запроса: " . pg_last_error());
    }

    if ($fields = pg_fetch_assoc($result)) {
        echo "<div class='data-container'>";

        $fields_map = [
            'ID' => $fields['id'],
            'Login' => $fields['username'],
            'Password for user' => $fields['password'],
            'Domain' => $fields['domain'],
            'SIP number' => $fields['internal_number'],
            'SIP context' => $fields['context_value'],
            'Range SIP' => $fields['quantity'],
            'Prefix' => $fields['user_prefix'],
            'IP' => $fields['ip_address'],
            'Status' => ($fields['status'] === '?' ? "<span class='status-reject'>Reject</span>" : 
                         ($fields['status'] === '+' ? "<span class='status-done'>DONE</span>" : 
                         ($fields['status'] === '-' ? "<span class='status-wait'>Wait...</span>" : htmlspecialchars($fields['status']))))
        ];

        foreach ($fields_map as $label => $value) {
            echo "<div class='data-block'>";
            echo "<h3>" . htmlspecialchars($label) . "</h3>";
            echo "<p>" . $value . "</p>";
            echo "</div>";
            echo "<div class='divider'></div>";
        }

        echo "</div>";
    } else {
        echo "<p>Данные с указанным ID не найдены.</p>";
    }

    pg_close($conn);
} else {
    echo "<p>Пожалуйста, укажите ID в URL для получения данных. Пример: <code>?id=0xz0a24x</code></p>";
}
?>

</body>
</html>
