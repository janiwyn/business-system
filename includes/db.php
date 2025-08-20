<?php
// database connection 
$host = 'localhost';
$password = '';
$database = 'business-system';
$rootname = 'root';

$conn = mysqli_connect($host, $rootname, $password, $database);
if($conn){
    //echo'successfully connected';
}else{
   echo'failed to connect';
}

?>