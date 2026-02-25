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
function showStaticBackdrop() {
  const el = document.getElementById("staticBackdrop");
  const modal = bootstrap.Modal.getOrCreateInstance(el, {
    backdrop: "static",
    keyboard: false,
  });
  modal.show();
}
function hideStaticBackdrop() {
  const el = document.getElementById("staticBackdrop");
  const modal = bootstrap.Modal.getInstance(el) || bootstrap.Modal.getOrCreateInstance(el);

  // 1) sacá foco de adentro antes de ocultar
  if (el.contains(document.activeElement)) document.activeElement.blur();

  // 2) ocultá
  modal.hide();
}

function enviarFormulario() {
  let banco = $("#banco").val();
  let importeTexto = $("#importe").val();

  $("#alert_confirmation_body").html(
    "Confirmo el deposito de $ " + importeTexto + " en la cuenta de Dinter del Banco " + banco
  );

  showStaticBackdrop();

  $("#alert_confirmation_btn_ok")
    .off("click")
    .one("click", function () {

      // 1) Tomar valores ANTES de reset
      let name = $("#name").val();
      let ncliente = $("#ncliente").val();
      let fecha = $("#fecha").val();
      let banco = $("#banco").val();
      let noperacion = $("#noperacion").val();
      let importe = $("#importe").val().replace(/,/g, "");
      let tipooperacion = $("#tipo_operacion").val();

      if (!name || !ncliente || !fecha || !banco || !noperacion || !importe || !tipooperacion) {
        return;
      }

      // 2) blur + hide modal
      document.activeElement?.blur();
      hideStaticBackdrop();

      // 3) reset inmediato del form (si querés)
      $("#form_cobranza")[0].reset();

      // 4) ajax...
      $.ajax({
        type: "POST",
        url: "procesos/php/function.php",
        data: {
          IngresarPago: 1,
          name,
          ncliente,
          fecha,
          banco,
          noperacion,
          importe,
          tipooperacion,
        },
        ...
      });
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
