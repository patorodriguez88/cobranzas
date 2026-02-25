<?php

session_start();

if (isset($_POST['comprueba'])) {

  if ($_SESSION['user_control'] == "") {

    echo json_encode(array('success' => 0));
  } else {

    echo json_encode(array('success' => 1));
  }
}

if (isset($_POST['comprueba_cobranza'])) {

  if ($_SESSION['user_cobranza'] == "") {

    echo json_encode(array('success' => 0));
  } else {

    echo json_encode(array('success' => 1));
  }
}
