<?php
session_start();
include_once "../../conexion/conexioni.php";

if($_POST['NComprobante']==1){

    $_SESSION['NComprobante']=$_POST['n'];    

}

if($_POST['Ingreso']==1){
    
    $doc=$_POST[doc];
    
    if($doc<>""){

        $sql=$mysqli->query("SELECT id FROM Clientes WHERE Dni='$doc'");
        
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

if($_POST['IngresarPago']==1){
     
 $hora = date("H:i:s");
//BUSCO DUPLICIDAD
$sql=$mysqli->query("SELECT * FROM Cobranza WHERE Fecha='$_POST[fecha]' AND Operacion='$_POST[noperacion]' AND
Banco='$_POST[banco]' AND Importe='$_POST[importe]'");

$row=$sql->fetch_array(MYSQLI_ASSOC);

if($sql->num_rows){
  $Alerta=1;
}else{
  $Alerta=0;    
}

 if($mysqli->query("INSERT INTO `Cobranza`(`NombreCliente`, `NumeroCliente`, `Fecha`, `Hora`, `Banco`, `Operacion`, `Importe`,`AlertaDuplicidad`) 
 VALUES ('".$_POST[name]."','".$_POST[ncliente]."','".$_POST[fecha]."','".$hora."','".$_POST[banco]."','".$_POST[noperacion]."',
 '".$_POST[importe]."','".$Alerta."')")){

    $id=$mysqli->insert_id;

    echo json_encode(array('success' => 1,'idIngreso'=>$id));

    }else{
 
    echo json_encode(array('success' => 0));
 
 }

}
if($_POST['Datos']==1){

    $id=$_SESSION['user'];

    $sql=$mysqli->query("SELECT * FROM Clientes WHERE id='$id'");
        
    if($row=$sql->fetch_array(MYSQLI_ASSOC)){

        $rows=array();
    
        $rows[]=$row;
        
        $_SESSION['user']=$row['id'];

        echo json_encode(array('success'=>1,'data'=>$rows));
        
        }else{

        echo json_encode(array('success'=>0));    

    }
};

?>