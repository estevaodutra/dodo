<?php
function dbConnect() {
    return new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
}

function logMessage($file, $message) {
    file_put_contents($file, "[".date('Y-m-d H:i:s')."] $message\n", FILE_APPEND);
}
?>
