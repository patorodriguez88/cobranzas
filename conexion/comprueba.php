<?php

session_start();

if($_POST['comprueba']==1){
 
  if($_SESSION['user']==""){

   echo json_encode(array('success' => 0)); 

 }else{

    echo json_encode(array('success' => 1));
    
 }

}
?>