$("#cnl").click(function () {
  window.location.href = "https://www.dintersa.com.ar/cobranza/inicio.html";
});

function CompruebaConexion() {
  $.ajax({
    type: "POST",
    url: "conexion/comprueba.php",
    data: { comprueba: 1 },
    success: function (response) {
      var jsonData = JSON.parse(response);

      if (jsonData.success == 1) {
        // $('#name').val(jsonData.data[0].RazonSocial);
      } else {
        window.location.href = "https://www.dintersa.com.ar/cobranza/inicio.html";
      }
    },
  });
}

function enviarFormulario() {
  let banco = $("#banco").val();
  // let importe = $('#importe').val().replace(/,/g, "");

  let importe = $("#importe").val();

  $("#alert_confirmation_body").html(
    "Confirmo el deposito de $ " + importe + " en la cuenta de Dinter del Banco " + banco,
  );

  $("#staticBackdrop").modal("show");

  $("#alert_confirmation_btn_ok").click(function () {
    $("#staticBackdrop").modal("hide");
    $("#form_cobranza").trigger("reset");
    let name = $("#name").val();
    let ncliente = $("#ncliente").val();
    let fecha = $("#fecha").val();
    let banco = $("#banco").val();
    let noperacion = $("#noperacion").val();
    let importe = $("#importe").val().replace(/,/g, "");
    let tipooperacion = $("#tipo_operacion").val();
    if (
      name != "" &&
      ncliente != "" &&
      fecha != "" &&
      banco != "" &&
      noperacion != "" &&
      importe != "" &&
      tipooperacion != ""
    ) {
      $.ajax({
        type: "POST",
        url: "procesos/php/function.php",
        data: {
          IngresarPago: 1,
          name: name,
          ncliente: ncliente,
          fecha: fecha,
          banco: banco,
          noperacion: noperacion,
          importe: importe,
          tipooperacion: tipooperacion,
        },
        success: function (response) {
          var jsonData = JSON.parse(response);
          $("#loading").modal("hide");
          if (jsonData.success == 1) {
            $.ajax({
              type: "POST",
              url: "procesos/php/function.php",
              data: { NComprobante: 1, n: jsonData.idIngreso },
              success: function (response) {
                $("#standard-modal").modal("show");

                $("#standard-modal").on("hidden.bs.modal", function () {
                  $("#success-alert-modal").modal("show");

                  $("#form_cobranza").trigger("reset");

                  console.log("dato", jsonData);

                  $("#texto_exito").html(
                    "Cargamos tu Pago en nuestro sistema, el numero de registro es el Numero: </b> " +
                      jsonData.idIngreso,
                  );
                });
              },
            });
          } else {
            $("#danger-alert-modal").modal("show");
          }
        },
      });
    }
  });
}

$("#ingreso_btn").click(function () {
  let doc = $("#documento").val().trim();

  if (doc === "") {
    mostrarError("Ingrese un D.N.I.");
    return;
  }

  $.ajax({
    type: "POST",
    url: "procesos/php/function.php",
    data: { Ingreso: 1, doc: doc },
    dataType: "json", // 👈 importante, evitás JSON.parse manual
    success: function (jsonData) {
      if (jsonData.success == 1) {
        window.location.href = "https://www.dintersa.com.ar/cobranza/cargarpagos.html";
      } else {
        // Si usás códigos estructurados
        switch (jsonData.code) {
          case "CLIENTE_SUSPENDIDO":
            mostrarError("Su cuenta se encuentra suspendida. Comuníquese con administración.");
            break;

          case "CLIENTE_INEXISTENTE":
            mostrarError("El cliente no existe.");
            break;

          case "SIN_NUMERO_CLIENTE":
            mostrarError("No se encuentra el número de cliente.");
            break;

          default:
            mostrarError(jsonData.error || "Ocurrió un error inesperado.");
        }
      }
    },

    error: function () {
      mostrarError("Error de conexión con el servidor.");
    },
  });
});

function mostrarError(mensaje) {
  $("#error_text").html(mensaje);
  $("#error_alert").fadeIn();
}
