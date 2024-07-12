<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('../includes/db_connection.php');
require_once('../includes/encryption_functions.php');

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    try {
        error_log("Attempting login for email: " . $email);
        
        $encrypted_email = encrypt($email);
        error_log("Encrypted email: " . $encrypted_email);
        
        // データベース内の全ユーザーのメールアドレスをログに出力
        $all_users_sql = "SELECT id, email, password FROM users";
        $all_users_result = $conn->query($all_users_sql);
        $user_found = false;
        while ($row = $all_users_result->fetch_assoc()) {
            $db_email = $row['email'];
            error_log("DB email (raw): " . $db_email);
            try {
                $decrypted_email = decrypt($db_email);
                error_log("DB email (decrypted): " . $decrypted_email);
                if ($decrypted_email === $email) {
                    $user_found = true;
                    $user = $row;
                    break;
                }
            } catch (Exception $e) {
                error_log("Failed to decrypt email: " . $e->getMessage());
            }
        }
        
        if ($user_found) {
            error_log("User found in database");
            error_log("Password verification result: " . (password_verify($password, $user['password']) ? 'true' : 'false'));
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $email; // 一時的に平文のメールアドレスを使用
                error_log("Login successful for user: " . $_SESSION['username']);
                header("Location: ../stamp-creation/index.php");
                exit();
            } else {
                error_log("Password verification failed");
                $error = "メールアドレスまたはパスワードが正しくありません。";
            }
        } else {
            error_log("User not found in database for email: " . $email);
            $error = "メールアドレスまたはパスワードが正しくありません。";
        }
    } catch (Exception $e) {
        error_log("Exception occurred: " . $e->getMessage());
        $error = "エラーが発生しました: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="container">
        <h1>ログイン</h1>
        <?php if (!empty($error)) echo "<p class='error'>". htmlspecialchars($error) ."</p>"; ?>
        <form method="post">
            <input type="email" name="email" placeholder="メールアドレス" required>
            <input type="password" name="password" placeholder="パスワード" required>
            <button type="submit">ログイン</button>
        </form>
        <p>アカウントをお持ちでない方は<a href="index.php">こちら</a>から登録してください。</p>
    </div>
</body>
</html>