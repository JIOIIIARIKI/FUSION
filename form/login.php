<?php
session_start();
$error = '';

$servername = "127.0.0.1";
$username = "root";
$password = "1Yb74rfBhrTwtJHh";
$dbname = "ipadd";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE password = '$password'";
    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['username'] = $username;
            header("Location: IP.php");
            exit;
        } else {
            $error = "Неправильный пароль!";
        }
    } else {
        $error = "Пользователь с таким логином не найден!";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>
    <style>
body {
    font-family: Arial, sans-serif;
    background-image: url('https://st2.depositphotos.com/1031174/12328/i/450/depositphotos_123284352-stock-photo-grey-storm-clouds.jpg');
    background-size: cover;
    background-position: center;
    margin: 0;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    filter: grayscale(100%);
}

.login-container {
    background-color: rgba(255, 255, 255, 0.7);
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

input[type="text"],
input[type="password"],
input[type="submit"] {
    display: block;
    width: 100%;
    margin-bottom: 10px;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 5px;
    box-sizing: border-box;
    background-color: rgba(255, 255, 255, 0.8);
}

input[type="submit"] {
    background-color: #007bff;
    color: #fff;
    border: none;
    cursor: pointer;
}

input[type="submit"]:hover {
    background-color: #0056b3;
}

.error-message {
    color: red;
}
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Вход</h2>
        <?php if (isset($error)) { ?>
            <p class="error-message"><?php echo $error; ?></p>
        <?php } ?>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="text" name="username" placeholder="Логин" required>
            <input type="password" name="password" placeholder="Пароль" required>
            <input type="submit" value="Войти">
        </form>
    </div>
</body>
</html>
