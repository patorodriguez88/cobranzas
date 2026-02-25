// ==============================
// Navegación
// ==============================
$("#cnl").on("click", function () {
  window.location.href = "https://www.dintersa.com.ar/cobranza/inicio.html";
});

// ==============================
// Helpers UI
// ==============================
function resetValidationUI() {
  const form = document.getElementById("form_cobranza");
  if (!form) return;
  form.classList.remove("was-validated");
}

function hideFormScreen() {
  $("#screen-form").addClass("d-none");
}

function showFormScreen() {
  $("#screen-form").removeClass("d-none");
}

function showStaticBackdrop() {
  const el = document.getElementById("staticBackdrop");
  if (!el) return;
  const modal = bootstrap.Modal.getOrCreateInstance(el, {
    backdrop: "static",
    keyboard: false,
  });
  modal.show();
}

function hideStaticBackdrop() {
  const el = document.getElementById("staticBackdrop");
  if (!el) return;

  const modal = bootstrap.Modal.getInstance(el) || bootstrap.Modal.getOrCreateInstance(el);

  // sacar foco antes de ocultar (fix iOS)
  if (el.contains(document.activeElement)) document.activeElement.blur();

  modal.hide();
}

function mostrarError(mensaje) {
  $("#error_text").html(mensaje);
  $("#error_alert").fadeIn();
}

// ==============================
// Seguridad / sesión
// ==============================
function CompruebaConexion() {
  $.ajax({
    type: "POST",
    url: "conexion/comprueba.php",
    data: { comprueba: 1 },
    success: function (response) {
      let jsonData;
      try {
        jsonData = typeof response === "string" ? JSON.parse(response) : response;
      } catch (e) {
        window.location.href = "https://www.dintersa.com.ar/cobranza/inicio.html";
        return;
      }

      if (jsonData.success == 1) {
        $("#name").val(jsonData?.data?.[0]?.RazonSocial || "");
      } else {
        window.location.href = "https://www.dintersa.com.ar/cobranza/inicio.html";
      }
    },
    error: function () {
      window.location.href = "https://www.dintersa.com.ar/cobranza/inicio.html";
    },
  });
}

// ==============================
// Login / Ingreso
// ==============================
$("#ingreso_btn")
  .off("click")
  .on("click", function () {
    let doc = ($("#documento").val() || "").trim();

    if (doc === "") {
      mostrarError("Ingrese un D.N.I.");
      return;
    }

    $.ajax({
      type: "POST",
      url: "procesos/php/function.php",
      data: { Ingreso: 1, doc: doc },
      dataType: "json",
      success: function (jsonData) {
        if (jsonData.success == 1) {
          window.location.href = "https://www.dintersa.com.ar/cobranza/cargarpagos.html";
          return;
        }

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
      },
      error: function () {
        mostrarError("Error de conexión con el servidor.");
      },
    });
  });

// ==============================
// Envío de formulario
// ==============================
$("#form_cobranza")
  .off("submit")
  .on("submit", function (e) {
    e.preventDefault();

    // validación HTML5
    if (!this.checkValidity()) {
      this.classList.add("was-validated");
      return;
    }

    resetValidationUI();
    enviarFormulario();
  });

function enviarFormulario() {
  const banco = $("#banco").val();
  const importeTexto = $("#importe").val();

  $("#alert_confirmation_body").html(
    "Confirmo el deposito de $ " + importeTexto + " en la cuenta de Dinter del Banco " + banco,
  );

  showStaticBackdrop();

  // clave: evitar acumulación
  $("#alert_confirmation_btn_ok")
    .off("click")
    .one("click", function () {
      // Tomar valores ANTES de reset
      const name = $("#name").val();
      const ncliente = $("#ncliente").val();
      const fecha = $("#fecha").val();
      const banco = $("#banco").val();
      const noperacion = $("#noperacion").val();
      const importe = ($("#importe").val() || "").replace(/,/g, "");
      const tipooperacion = $("#tipo_operacion").val();

      if (!name || !ncliente || !fecha || !banco || !noperacion || !importe || !tipooperacion) {
        return;
      }

      // iOS focus fix
      document.activeElement?.blur();
      hideStaticBackdrop();
      resetValidationUI();

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

          // Pedir NComprobante
          $.ajax({
            type: "POST",
            url: "procesos/php/function.php",
            dataType: "json",
            data: { NComprobante: 1, n: jsonData.idIngreso },
            success: function () {
              // Mostrar modal standard
              bootstrap.Modal.getOrCreateInstance(document.getElementById("standard-modal"), {
                backdrop: "static",
                keyboard: false,
              }).show();

              // Al cerrar standard -> mostrar éxito
              $("#standard-modal")
                .off("hidden.bs.modal")
                .one("hidden.bs.modal", function () {
                  $("#texto_exito").html(
                    "Cargamos tu Pago en nuestro sistema, el número de registro es: <b>" + jsonData.idIngreso + "</b>",
                  );

                  bootstrap.Modal.getOrCreateInstance(document.getElementById("success-alert-modal")).show();
                  $("#form_cobranza")[0].reset();
                  resetValidationUI();
                });
            },
            error: function () {
              $("#loading").modal("hide");
              bootstrap.Modal.getOrCreateInstance(document.getElementById("danger-alert-modal")).show();
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
function limitarFechaUltimos30Dias() {
  const el = document.getElementById("fecha");
  if (!el) return;

  const hoy = new Date();
  const desde = new Date();
  desde.setDate(hoy.getDate() - 30);

  const fmt = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
  };

  const max = fmt(hoy);
  const min = fmt(desde);

  el.max = max;
  el.min = min;

  // Si ya había un valor fuera de rango, lo ajustamos
  if (el.value && (el.value < min || el.value > max)) el.value = max;
}
$("#fecha").on("change blur", function () {
  const min = this.min;
  const max = this.max;
  const v = (this.value || "").trim();
  if (!v) return;

  if (v < min) this.value = min;
  if (v > max) this.value = max;
});
document.addEventListener("DOMContentLoaded", limitarFechaUltimos30Dias);
// ==============================
// Eventos del modal standard (si existe)
// ==============================
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
