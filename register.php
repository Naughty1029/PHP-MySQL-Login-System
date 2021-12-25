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