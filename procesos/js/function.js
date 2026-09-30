// ==============================
// Navegación
// ==============================
$("#cnl").on("click", function () {
  window.location.href = "inicio.html";
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
// Comprobante (obligatorio, viaja con el pago)
// ==============================
const COMPROBANTE_MAX_MB = 10;
let comprobanteArchivo = null;

function limpiarComprobante() {
  comprobanteArchivo = null;
  $("#comprobante").val("");
  $("#comprobante_img").attr("src", "");
  $("#comprobante_nombre").text("");
  $("#comprobante_preview").addClass("d-none");
  $("#comprobante_vacio").removeClass("d-none");
  $("#comprobante_box").removeClass("con-archivo").addClass("sin-archivo");
  $("#comprobante_error").text("Tenés que adjuntar la foto del comprobante.");
}

function comprobanteInvalido(mensaje) {
  limpiarComprobante();
  $("#comprobante_error").text(mensaje).show();
}

$("#comprobante").on("change", function () {
  const archivo = this.files && this.files[0];
  $("#comprobante_error").removeAttr("style");
  if (!archivo) {
    limpiarComprobante();
    return;
  }
  const esImagen = /^image\//.test(archivo.type) || /\.(jpe?g|png|gif|webp|heic|heif)$/i.test(archivo.name);
  if (!esImagen) {
    comprobanteInvalido("El archivo tiene que ser una foto o imagen (JPG, PNG o WebP).");
    return;
  }
  if (archivo.size > COMPROBANTE_MAX_MB * 1024 * 1024) {
    comprobanteInvalido(`La imagen supera los ${COMPROBANTE_MAX_MB} MB.`);
    return;
  }
  comprobanteArchivo = archivo;
  $("#comprobante_nombre").text(archivo.name);
  $("#comprobante_img").attr("src", URL.createObjectURL(archivo));
  $("#comprobante_vacio").addClass("d-none");
  $("#comprobante_preview").removeClass("d-none");
  $("#comprobante_box").removeClass("sin-archivo").addClass("con-archivo");
});

// Achica la foto a 1600 px en JPEG (como hacía el Dropzone) para que suba rápido
// desde el celular. Si el navegador no la puede leer, se manda la original.
function optimizarComprobante(archivo) {
  return new Promise((resolve) => {
    if (!/^image\/(jpeg|png|webp|gif)$/i.test(archivo.type)) {
      resolve({ blob: archivo, nombre: archivo.name });
      return;
    }
    const url = URL.createObjectURL(archivo);
    const img = new Image();
    img.onload = () => {
      const escala = Math.min(1, 1600 / Math.max(img.naturalWidth, img.naturalHeight));
      const canvas = document.createElement("canvas");
      canvas.width = Math.round(img.naturalWidth * escala);
      canvas.height = Math.round(img.naturalHeight * escala);
      const ctx = canvas.getContext("2d");
      ctx.fillStyle = "#fff";
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
      URL.revokeObjectURL(url);
      canvas.toBlob(
        (blob) => resolve(blob ? { blob, nombre: "comprobante.jpg" } : { blob: archivo, nombre: archivo.name }),
        "image/jpeg",
        0.78,
      );
    };
    img.onerror = () => {
      URL.revokeObjectURL(url);
      resolve({ blob: archivo, nombre: archivo.name });
    };
    img.src = url;
  });
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
        window.location.href = "inicio.html";
        return;
      }

      if (jsonData.success == 1) {
        $("#name").val(jsonData?.data?.[0]?.RazonSocial || "");
      } else {
        window.location.href = "inicio.html";
      }
    },
    error: function () {
      window.location.href = "inicio.html";
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
          window.location.href = "cargarpagos.html";
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

    // validación HTML5 (incluye el comprobante obligatorio)
    if (!comprobanteArchivo) $("#comprobante").val("");
    if (!this.checkValidity() || !comprobanteArchivo) {
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

      if (!name || !ncliente || !fecha || !banco || !noperacion || !importe || !tipooperacion || !comprobanteArchivo) {
        return;
      }

      // iOS focus fix
      document.activeElement?.blur();
      hideStaticBackdrop();
      resetValidationUI();
      $("#send").prop("disabled", true);
      bootstrap.Modal.getOrCreateInstance(document.getElementById("loading")).show();

      optimizarComprobante(comprobanteArchivo).then(({ blob, nombre }) => {
        const datos = new FormData();
        datos.append("IngresarPago", 1);
        datos.append("name", name);
        datos.append("ncliente", ncliente);
        datos.append("fecha", fecha);
        datos.append("banco", banco);
        datos.append("noperacion", noperacion);
        datos.append("importe", importe);
        datos.append("tipooperacion", tipooperacion);
        datos.append("comprobante", blob, nombre);

        $.ajax({
          type: "POST",
          url: "procesos/php/function.php",
          data: datos,
          processData: false,
          contentType: false,
          dataType: "json",
          success: function (jsonData) {
            ocultarLoading();
            if (!jsonData || jsonData.success != 1) {
              mostrarErrorPago(jsonData?.error);
              return;
            }
            $("#texto_exito").html(
              "Cargamos tu Pago en nuestro sistema, el número de registro es: <b>" + jsonData.idIngreso + "</b>",
            );
            bootstrap.Modal.getOrCreateInstance(document.getElementById("success-alert-modal")).show();
            $("#form_cobranza")[0].reset();
            limpiarComprobante();
            resetValidationUI();
          },
          error: function (xhr) {
            console.log("Error IngresarPago:", xhr.responseText);
            ocultarLoading();
            mostrarErrorPago(xhr.responseJSON?.error);
          },
        });
      });
    });
}

function ocultarLoading() {
  $("#send").prop("disabled", false);
  const el = document.getElementById("loading");
  // si el modal todavía se está abriendo, Bootstrap ignora el hide(): se cierra al terminar de mostrarse
  if (loadingVisible) bootstrap.Modal.getOrCreateInstance(el).hide();
  else $(el).one("shown.bs.modal", () => bootstrap.Modal.getOrCreateInstance(el).hide());
}

let loadingVisible = false;
$("#loading")
  .on("shown.bs.modal", () => (loadingVisible = true))
  .on("hidden.bs.modal", () => (loadingVisible = false));

function mostrarErrorPago(mensaje) {
  $("#danger-alert-modal p").text(
    mensaje || "Ocurrió algún error al intentar cargar tu pago, por favor volvé a intentarlo.",
  );
  bootstrap.Modal.getOrCreateInstance(document.getElementById("danger-alert-modal")).show();
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
