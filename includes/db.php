<?php
// database connection 
$host = 'localhost';
$password = '';
$database = 'business';
$rootname = 'root';

$conn = mysqli_connect($host, $rootname, $password, $database);
if($conn){
    //echo'successfully connected';
}else{
   echo'failed to connect';
}

?>