<?
session_start();
include_once "../../../conexion/conexioni.php";
date_default_timezone_set("America/Argentina/Cordoba");

//DNI
if($_POST['Dni_search']==1){
    $sql=$mysqli->query("SELECT Dni FROM Clientes WHERE id='$_POST[id]'"); 
    $row=$sql->fetch_array(MYSQLI_ASSOC);

    echo json_encode(array('success'=>1,'Dato'=>$row[Dni]));
}

if($_POST['Dni']==1){

    $mysqli->query("UPDATE Clientes SET Dni='$_POST[Dni_text]' WHERE id='$_POST[id]'"); 
    
    echo json_encode(array('success'=>1));

}


//OBSERVACIONES
if($_POST['Observaciones_search']==1){
    $sql=$mysqli->query("SELECT Observaciones FROM Clientes WHERE id='$_POST[id]'"); 
    $row=$sql->fetch_array(MYSQLI_ASSOC);

    echo json_encode(array('success'=>1,'Dato'=>$row['Observaciones']));
}

if($_POST['Observaciones']==1){

    $mysqli->query("UPDATE Clientes SET Observaciones='$_POST[Observaciones_text]' WHERE id='$_POST[id]'"); 
    
    echo json_encode(array('success'=>1));

}

//TABLA 

if($_POST['Tabla_clientes']==1){

    $sql=$mysqli->query("SELECT id,Ncliente,RazonSocial,Dni,Observaciones,Suspendido,Direccion,Ciudad FROM Clientes");
    
    $rows=array();
    
    while($row=$sql->fetch_array(MYSQLI_ASSOC)){
        
        $rows[]=$row;
    }
    
    echo json_encode(array('data'=>$rows));        


}
if($_POST['Status']==1){

    if($mysqli->query("UPDATE Clientes SET Suspendido='$_POST[status]' WHERE id='$_POST[id_cliente]'")<>NULL){
        
        echo json_encode(array('success'=>1)); 

    }else{

        echo json_encode(array('success'=>0));
    }

}
?>