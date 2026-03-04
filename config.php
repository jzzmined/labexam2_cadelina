<?php
// config.php - Database Connection
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       
define('DB_PASS', '');        
define('DB_NAME', 'labexam2_jasmine'); 
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("<div style='color:red;padding:20px;font-family:sans-serif;'>
        <h3>Database Connection Failed</h3>
        <p>" . $conn->connect_error . "</p>
        <p>Check your credentials in <code>config.php</code></p>
    </div>");
}

$conn->set_charset("utf8");
?>