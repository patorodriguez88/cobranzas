<?php
session_start();
include_once "../../conexion/conexioni.php";

if($_POST['Mis_pagos']==1){
    
    $doc=$_POST[doc];
    
    if($doc<>""){
        $id='10';
        $sql=$mysqli->query("SELECT * FROM Conciliados WHERE NumeroCliente='$id'");
        
            $row=$sql->fetch_array(MYSQLI_ASSOC);
            
            if($row['id']<>0 && $row['id']<>NULL){

                $rows=array();
            
                $rows[]=$row;
                
                $_SESSION['user']=$row['id'];

                echo json_encode(array('success'=>1,'data'=>$rows));
                
                }else{

                echo json_encode(array('success'=>0));    

            }
        
        }else{
    
        echo json_encode(array('success'=>0));    
    
    }
}
?>