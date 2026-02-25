$("#cnl").click(function () {
  window.location.href = "https://www.dintersa.com.ar/cobranza/inicio.html";
});
function resetValidationUI() {
  const form = document.getElementById("form_cobranza");
  form.classList.remove("was-validated");
}
function hideFormScreen() {
  $("#screen-form").addClass("d-none");
}

function showFormScreen() {
  $("#screen-form").removeClass("d-none");
}
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
$("#form_cobranza").on("submit", function (e) {
  e.preventDefault();

  // validación HTML5
  if (!this.checkValidity()) {
    this.classList.add("was-validated");
    return;
  }

  // si es válido, recién ahí arrancás tu flujo
  resetValidationUI(); // ✅ para que no quede rojo

  enviarFormulario();
});
function enviarFormulario() {
  let banco = $("#banco").val();
  let importeTexto = $("#importe").val();

  $("#alert_confirmation_body").html(
    "Confirmo el deposito de $ " + importeTexto + " en la cuenta de Dinter del Banco " + banco,
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
      resetValidationUI();
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
        success: function (response) {
          let jsonData;
          try {
            jsonData = typeof response === "string" ? JSON.parse(response) : response;
          } catch (e) {
            console.log("Respuesta inválida:", response);
            $("#loading").modal("hide");
            bootstrap.Modal.getOrCreateInstance(document.getElementById("danger-alert-modal")).show();
            return;
          }

          $("#loading").modal("hide");

          if (jsonData.success != 1) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById("danger-alert-modal")).show();
            return;
          }

          // Pedís NComprobante
          $.ajax({
            type: "POST",
            url: "procesos/php/function.php",
            dataType: "json",
            data: { NComprobante: 1, n: jsonData.idIngreso },
            success: function (resp2) {
              // Mostrás el modal "standard" YA
              bootstrap.Modal.getOrCreateInstance(document.getElementById("standard-modal"), {
                backdrop: "static",
                keyboard: false,
              }).show();

              // IMPORTANTÍSIMO: usá .one para que no se acumulen eventos
              $("#standard-modal")
                .off("hidden.bs.modal") // por si quedó algo viejo
                .one("hidden.bs.modal", function () {
                  // Mostrás éxito inmediatamente al cerrar standard
                  $("#texto_exito").html(
                    "Cargamos tu Pago en nuestro sistema, el número de registro es: <b>" + jsonData.idIngreso + "</b>",
                  );

                  bootstrap.Modal.getOrCreateInstance(document.getElementById("success-alert-modal")).show();
                  // Si querés reset adicional, ok (pero no debería ser necesario)
                  $("#form_cobranza")[0].reset();
                });
            },
          });
        },
        error: function (xhr) {
          console.log("Error IngresarPago:", xhr.responseText);
          $("#loading").modal("hide");
          bootstrap.Modal.getOrCreateInstance(document.getElementById("danger-alert-modal")).show();
        },
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
$("#cnl").click(function () {
  window.location.href = "https://www.dintersa.com.ar/cobranza/inicio.html";
});
function resetValidationUI() {
  const form = document.getElementById("form_cobranza");
  form.classList.remove("was-validated");
}
function hideFormScreen() {
  $("#screen-form").addClass("d-none");
}

function showFormScreen() {
  $("#screen-form").removeClass("d-none");
}
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
$("#form_cobranza").on("submit", function (e) {
  e.preventDefault();

  // validación HTML5
  if (!this.checkValidity()) {
    this.classList.add("was-validated");
    return;
  }

  // si es válido, recién ahí arrancás tu flujo
  resetValidationUI(); // ✅ para que no quede rojo

  enviarFormulario();
});
function enviarFormulario() {
  let banco = $("#banco").val();
  let importeTexto = $("#importe").val();

  $("#alert_confirmation_body").html(
    "Confirmo el deposito de $ " + importeTexto + " en la cuenta de Dinter del Banco " + banco,
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
      resetValidationUI();
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
        success: function (response) {
          let jsonData;
          try {
            jsonData = typeof response === "string" ? JSON.parse(response) : response;
          } catch (e) {
            console.log("Respuesta inválida:", response);
            $("#loading").modal("hide");
            bootstrap.Modal.getOrCreateInstance(document.getElementById("danger-alert-modal")).show();
            return;
          }

          $("#loading").modal("hide");

          if (jsonData.success != 1) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById("danger-alert-modal")).show();
            return;
          }

          // Pedís NComprobante
          $.ajax({
            type: "POST",
            url: "procesos/php/function.php",
            dataType: "json",
            data: { NComprobante: 1, n: jsonData.idIngreso },
            success: function (resp2) {
              // Mostrás el modal "standard" YA
              bootstrap.Modal.getOrCreateInstance(document.getElementById("standard-modal"), {
                backdrop: "static",
                keyboard: false,
              }).show();

              // IMPORTANTÍSIMO: usá .one para que no se acumulen eventos
              $("#standard-modal")
                .off("hidden.bs.modal") // por si quedó algo viejo
                .one("hidden.bs.modal", function () {
                  // Mostrás éxito inmediatamente al cerrar standard
                  $("#texto_exito").html(
                    "Cargamos tu Pago en nuestro sistema, el número de registro es: <b>" + jsonData.idIngreso + "</b>",
                  );

                  bootstrap.Modal.getOrCreateInstance(document.getElementById("success-alert-modal")).show();
                  // Si querés reset adicional, ok (pero no debería ser necesario)
                  $("#form_cobranza")[0].reset();
                });
            },
          });
        },
        error: function (xhr) {
          console.log("Error IngresarPago:", xhr.responseText);
          $("#loading").modal("hide");
          bootstrap.Modal.getOrCreateInstance(document.getElementById("danger-alert-modal")).show();
        },
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
const standardEl = document.getElementById("standard-modal");
if (standardEl) {
  standardEl.addEventListener("show.bs.modal", () => {
    hideFormScreen();
    resetValidationUI();
  });

  standardEl.addEventListener("hidden.bs.modal", () => {
    showFormScreen();
    $("#form_cobranza")[0].reset();
    resetValidationUI();
  });
}
