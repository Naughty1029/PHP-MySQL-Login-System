## Directory structure
```
root/
 ├ db_connect.php
 ├ functions.php
 ├ register.php
 ├ login.php
 ├ logout.php
 └ welcome.php
```
　
## Creating the Database Table
Execute the following sql query to create a users table in the database.
```sql
CREATE TABLE users (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```
　
## db_connect.php
Implement the process to connect to the database.
```php
<?php
/* Database credentials. Assuming you are running MySQL
server with default setting */
const DB_HOST = 'mysql:dbname=user_login;host=localhost';
const DB_USER = 'root';
const DB_PASSWORD = 'root';

//Receive DB connection in exception handling
try {
	//Connect with PDO
	$pdo = new PDO(DB_HOST,DB_USER,DB_PASSWORD,[
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_EMULATE_PREPARES =>false
	]);
} catch (PDOException $e) {
	echo 'ERROR: Could not connect.'.$e->getMessage()."\n";
	exit();
}
```
　
## functions.php
Code the function to be used later.
```php
<?php
//XSS protection
function h($s){
    return htmlspecialchars($s, ENT_QUOTES, "UTF-8");
}

//Set token to session
function setToken(){
    $token = sha1(uniqid(mt_rand(), true));
    $_SESSION['token'] = $token;
}

//Get token from session and check it
function checkToken(){
    if(empty($_SESSION['token']) || ($_SESSION['token'] != $_POST['token'])){
        echo 'Invalid POST', PHP_EOL;
        exit;
    }
}

function validation($datas,$confirm = true)
{
    $errors = [];

    if(empty($datas['name'])) {
        $errors['name'] = 'Please enter username.';
    }else if(mb_strlen($datas['name']) > 20) {
        $errors['name'] = 'Please enter up to 20 characters.';
    }

    if(empty($datas["password"])){
        $errors['password']  = "Please enter a password.";
    }else if(!preg_match('/\A[a-z\d]{8,100}+\z/i',$datas["password"])){
        $errors['password'] = "Please set a password with at least 8 characters.";
    }

    if($confirm){
        if(empty($datas["confirm_password"])){
            $errors['confirm_password']  = "Please confirm password.";
        }else if(empty($errors['password']) && ($datas["password"] != $datas["confirm_password"])){
            $errors['confirm_password'] = "Password did not match.";
        }
    }

    return $errors;
}
```
　
## register.php
Implement the user registration form and program.
```php
<?php
require_once "db_connect.php";
require_once "functions.php";
session_start();

// Define variables and initialize with empty values
$datas = [
    'name'  => '',
    'password'  => '',
    'confirm_password'  => ''
];

//Set CSRF token when accessed by GET
if($_SERVER['REQUEST_METHOD'] != 'POST'){
    setToken();
}
// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    //CSRF
    checkToken();

    // Get the input value of a submitted form.
    foreach($datas as $key => $value) {
        if($value = filter_input(INPUT_POST, $key, FILTER_DEFAULT)) {
            $datas[$key] = $value;
        }
    }

    // Validation
    $errors = validation($datas);

    //Check existing user names
    if(empty($errors['name'])){
        $sql = "SELECT id FROM users WHERE name = :name";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('name',$datas['name'],PDO::PARAM_INT);
        $stmt->execute();
        if($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            $errors['name'] = 'This username is already taken.';
        }
    }

    if(empty($errors)){
        $params = [
            'id' =>null,
            'name'=>$datas['name'],
            'password'=>password_hash($datas['password'], PASSWORD_DEFAULT),
            'created_at'=>null
        ];

        $count = 0;
        $columns = '';
        $values = '';
        foreach (array_keys($params) as $key) {
            if($count > 0){
                $columns .= ',';
                $values .= ',';
            }
            $columns .= $key;
            $values .= ':'.$key;
            $count++;
        }

        $pdo->beginTransaction(); 
        try {
            $sql = 'insert into users ('.$columns .')values('.$values.')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $pdo->commit();
            header("location: login.php");
            exit;
        } catch (PDOException $e) {
            echo 'ERROR: Could not register.';
            $pdo->rollBack();
        }
    }
}
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body{
            font: 14px sans-serif;
        }
        .wrapper{
            width: 400px;
            padding: 20px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>Sign Up</h2>
        <p>Please fill this form to create an account.</p>
        <form action="<?php echo $_SERVER ['SCRIPT_NAME']; ?>" method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="name" class="form-control <?php echo (!empty(h($errors['name']))) ? 'is-invalid' : ''; ?>" value="<?php echo h($datas['name']); ?>">
                <span class="invalid-feedback"><?php echo h($errors['name']); ?></span>
            </div>    
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control <?php echo (!empty(h($errors['password']))) ? 'is-invalid' : ''; ?>" value="<?php echo h($datas['password']); ?>">
                <span class="invalid-feedback"><?php echo h($errors['password']); ?></span>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control <?php echo (!empty(h($errors['confirm_password']))) ? 'is-invalid' : ''; ?>" value="<?php echo h($datas['confirm_password']); ?>">
                <span class="invalid-feedback"><?php echo h($errors['confirm_password']); ?></span>
            </div>
            <div class="form-group">
                <input type="hidden" name="token" value="<?php echo h($_SESSION['token']); ?>">
                <input type="submit" class="btn btn-primary" value="Submit">
            </div>
            <p>Already have an account? <a href="login.php">Login here</a>.</p>
        </form>
    </div>    
</body>
</html>
```
　
## login.php
Implement the user login form and program.
```php
<?php
require_once "db_connect.php";
require_once "functions.php";
//Initialize the session
session_start();
// Check if the user is already logged in, if yes then redirect him to welcome page
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: welcome.php");
    exit;
}
 
// Define variables and initialize with empty values
$datas = [
    'name'  => '',
    'password'  => '',
    'confirm_password'  => ''
];
$login_err = "";

//Set CSRF token when accessed by GET
if($_SERVER['REQUEST_METHOD'] != 'POST'){
    setToken();
}

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    ////CSRF
    checkToken();

    // Get the input value of a submitted form.
    foreach($datas as $key => $value) {
        if($value = filter_input(INPUT_POST, $key, FILTER_DEFAULT)) {
            $datas[$key] = $value;
        }
    }

    // Validation
    $errors = validation($datas,false);
    if(empty($errors)){
        $sql = "SELECT id,name,password FROM users WHERE name = :name";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('name',$datas['name'],PDO::PARAM_INT);
        $stmt->execute();

        if($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            if (password_verify($datas['password'],$row['password'])) {
                session_regenerate_id(true);

                $_SESSION["loggedin"] = true;
                $_SESSION["id"] = $row['id'];
                $_SESSION["name"] =  $row['name'];

                header("location:welcome.php"); // Redirect to welcome page
                exit();
            } else {
                $login_err = 'Invalid username or password.';
            }
        }else {
            $login_err = 'Invalid username or password.';
        }
    }
}
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body{
            font: 14px sans-serif;
        }
        .wrapper{
            width: 400px;
            padding: 20px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>Login</h2>
        <p>Please fill in your credentials to login.</p>

        <?php 
        if(!empty($login_err)){
            echo '<div class="alert alert-danger">' . $login_err . '</div>';
        }        
        ?>

        <form action="<?php echo $_SERVER ['SCRIPT_NAME']; ?>" method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="name" class="form-control <?php echo (!empty(h($errors['name']))) ? 'is-invalid' : ''; ?>" value="<?php echo h($datas['name']); ?>">
                <span class="invalid-feedback"><?php echo h($errors['name']); ?></span>
            </div>    
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control <?php echo (!empty(h($errors['password']))) ? 'is-invalid' : ''; ?>" value="<?php echo h($datas['password']); ?>">
                <span class="invalid-feedback"><?php echo h($errors['password']); ?></span>
            </div>
            <div class="form-group">
                <input type="hidden" name="token" value="<?php echo h($_SESSION['token']); ?>">
                <input type="submit" class="btn btn-primary" value="Login">
            </div>
            <p>Don't have an account? <a href="register.php">Sign up now</a></p>
        </form>
    </div>
</body>
</html>
```
　
## welcome.php
Build a welcome page after user logined.
```php
<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body{ 
            font: 14px sans-serif;
            text-align: center; 
        }
    </style>
</head>
<body>
    <h1 class="my-5">Hi,<b><?php echo htmlspecialchars($_SESSION["name"]); ?></b>. Welcome to our site.</h1>
    <p>
        <a href="logout.php" class="btn btn-danger ml-3">Sign Out of Your Account</a>
    </p>
</body>
</html>
```
　
## logout.php
Implement a logout program.
```php
<?php
session_start();

// Unset all of the session variables
$_SESSION = array();
// Destroy the session.
session_destroy();

// Redirect to login page
header("location: login.php");
exit;
```
　　
## Reference Links
I learned from the following links. Thank you very much.
- [PHP MySQL Login System](https://www.tutorialrepublic.com/php-tutorial/php-mysql-login-system.php)
- [PHPとMySQLで新規登録とログインを実装する](https://qiita.com/KosukeQiita/items/b56b3004413c999b9858)
- [PHPでクロスサイトリクエストフォージェリ（CSRF）対策する](https://qiita.com/yoh-nak/items/c264d29eb25f4df7f19e)
- [MySQLへ接続](https://www.javadrive.jp/php/pdo/index3.html)
- [言語別：パスワード向けの正規表現](https://qiita.com/mpyw/items/886218e7b418dfed254b)
- [ログイン時のCSRF対策は必要か](https://qiita.com/khsk/items/f654ddddc249f1f7c5e2)
- [パスワードのハッシュ化とログイン画面](https://qiita.com/hththt/items/af7fb0806397704b2a94)
