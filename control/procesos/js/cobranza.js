let tablaCobranza = null;
let importacionActualId = 0;
let mensajesIniciados = 0;
let fechaLimiteInformeActual = null;
let dropzoneCobranzaDirecta = null;
let idCobranzaDirectaPendienteComprobante = 0;
Dropzone.autoDiscover = false;

function escaparCobranza(valor) {
  return String(valor ?? "").replace(/[&<>"']/g, (caracter) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  })[caracter]);
}

function numeroWhatsApp(celular) {
  let numero = String(celular || "").replace(/\D/g, "");
  if (numero.startsWith("00")) numero = numero.substring(2);
  if (!numero.startsWith("54")) numero = `54${numero}`;
  return numero;
}

function telefonoValido(celular) {
  const numero = numeroWhatsApp(celular);
  return String(celular || "").trim() !== "" && numero.length >= 12 && numero.length <= 15;
}

function fechaHoraArgentina(fecha) {
  if (!fecha) return "";
  const partes = String(fecha).replace("T", " ").split(/[- :]/);
  if (partes.length < 5) return fecha;
  const [anio, mes, dia, hora, minuto] = partes;
  return `${dia}/${mes}/${anio} ${hora}:${minuto}`;
}

function fechaParaInputLocal(fecha) {
  if (!fecha) return "";
  const normalizada = String(fecha).replace(" ", "T");
  return normalizada.slice(0, 16);
}

function mostrarUltimaImportacion(importacion) {
  if (!importacion) {
    $("#ultimo_archivo_cobranza").html("Todavía no hay archivos registrados.");
    return;
  }
  importacionActualId = Number(importacion.id || 0);
  mensajesIniciados = Number(importacion.MensajesIniciados || 0);
  fechaLimiteInformeActual = importacion.FechaLimiteInforme || null;

  const textoLimite = fechaLimiteInformeActual
    ? `<strong id="texto_fecha_limite_cobranza">${escaparCobranza(fechaHoraArgentina(fechaLimiteInformeActual))}</strong>`
    : `<span id="texto_fecha_limite_cobranza" class="text-muted">Sin definir</span>`;

  $("#ultimo_archivo_cobranza").html(
    `Último archivo: <strong>${escaparCobranza(importacion.Archivo)}</strong> · ` +
    `${escaparCobranza(importacion.Fecha)} · Subido por: <strong>${escaparCobranza(importacion.Usuario)}</strong> · ` +
    `${Number(importacion.CantidadFilas || 0)} clientes · ` +
    `Envíos iniciados: <strong id="cantidad_mensajes_cobranza">${mensajesIniciados}</strong><br />` +
    `Plazo para informar el pago: ${textoLimite} ` +
    `<button type="button" id="btn_editar_fecha_limite_cobranza" class="btn btn-link btn-sm p-0 ms-1"><i class="mdi mdi-pencil-outline me-1"></i>Editar</button>`,
  );
}

function importeArgentina(importe) {
  return Number(importe || 0).toLocaleString("es-AR", { style: "currency", currency: "ARS" });
}

function filaEnviada(fila) {
  return Boolean(fila && fila.UltimoEnvio);
}

function guardarTelefonoEnFila(indice, celular) {
  const filaTabla = tablaCobranza.row(indice);
  const fila = filaTabla.data();
  fila.Celular = celular;
  filaTabla.data(fila).invalidate().draw(false);
}

async function editarFechaLimiteCobranza() {
  if (!importacionActualId) return;

  const ingreso = await Swal.fire({
    title: "Plazo para informar el pago",
    input: "datetime-local",
    inputValue: fechaParaInputLocal(fechaLimiteInformeActual),
    showCancelButton: true,
    confirmButtonText: "Guardar",
    cancelButtonText: "Cancelar",
  });

  if (!ingreso.isConfirmed) return;

  $.post("control/procesos/php/cobranza_exigible.php", {
    accion: "actualizar_fecha_limite",
    importacion_id: importacionActualId,
    fecha_limite: ingreso.value || "",
  }, null, "json").done(function (respuesta) {
    if (!respuesta.success) {
      Swal.fire("Error", respuesta.error || "No se pudo actualizar el plazo.", "error");
      return;
    }
    fechaLimiteInformeActual = respuesta.fecha_limite || null;
    const texto = fechaLimiteInformeActual
      ? escaparCobranza(fechaHoraArgentina(fechaLimiteInformeActual))
      : "Sin definir";
    $("#texto_fecha_limite_cobranza").removeClass("text-muted").html(texto);
  }).fail(function (xhr) {
    Swal.fire("Error", xhr.responseJSON?.error || "No se pudo actualizar el plazo.", "error");
  });
}

async function editarTelefonoCobranza(indice) {
  const fila = tablaCobranza.row(indice).data();
  if (!fila) return;

  const ingreso = await Swal.fire({
    title: "Editar teléfono",
    text: `${fila.RazonSocial} · Cliente ${fila.Ncliente}`,
    input: "tel",
    inputValue: fila.Celular || "",
    inputPlaceholder: "Ej.: 3515551234",
    showCancelButton: true,
    confirmButtonText: "Continuar",
    cancelButtonText: "Cancelar",
    inputValidator: (valor) => {
      const numero = String(valor || "").replace(/\D/g, "");
      if (!telefonoValido(numero)) return "Ingrese un teléfono válido, con código de área.";
      return null;
    },
  });

  if (!ingreso.isConfirmed) return;
  const celular = String(ingreso.value).replace(/\D/g, "");

  if (!fila.Encontrado) {
    guardarTelefonoEnFila(indice, celular);
    Swal.fire("Teléfono actualizado", "Se usará solamente para este envío porque el cliente no fue encontrado en la base.", "success");
    return;
  }

  const destino = await Swal.fire({
    icon: "question",
    title: "¿Dónde guardamos este teléfono?",
    text: "Puede actualizar definitivamente el cliente o usarlo solamente para este envío.",
    showCancelButton: true,
    showDenyButton: true,
    confirmButtonText: "Guardar en el cliente",
    denyButtonText: "Solo este envío",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#0acf97",
  });

  if (destino.isDenied) {
    guardarTelefonoEnFila(indice, celular);
    return;
  }
  if (!destino.isConfirmed) return;

  $.ajax({
    url: "control/procesos/php/cobranza_exigible.php",
    type: "POST",
    dataType: "json",
    data: { accion: "actualizar_telefono", ncliente: fila.Ncliente, celular },
  }).done(function (respuesta) {
    if (!respuesta.success) {
      Swal.fire("Error", respuesta.error || "No se pudo actualizar el cliente.", "error");
      return;
    }
    guardarTelefonoEnFila(indice, celular);
    Swal.fire("Teléfono guardado", "El celular se actualizó definitivamente en Clientes.", "success");
  }).fail(function (xhr) {
    Swal.fire("Error", xhr.responseJSON?.error || "No se pudo actualizar el cliente.", "error");
  });
}

function textoMensajeCobranza(fila) {
  const nombreCliente = String(fila.RazonSocial || "cliente").trim();
  const datosBancarios = String(fila.Distribuidora || "DINTER").trim().toUpperCase() === "RAK"
    ? "Alias: *ELRAK.PANINI*\nCBU: *0200302101000001152701*\nBanco Córdoba\nCUIT: *30669104959*"
    : "Cuenta 1\nAlias: *DINTER.SA.*\nCBU: *2850331630094145090021*\nBanco Macro\n\nCuenta 2\nAlias: *DINTER.SA.CBA*\nCBU: *0200931901000025067115*\nBanco Córdoba";
  const lineaPlazo = fechaLimiteInformeActual
    ? `\nLe agradeceríamos poder informar el pago antes del *${fechaHoraArgentina(fechaLimiteInformeActual)}*, ya que de no ser así nos veríamos en la necesidad de postergar el envío del próximo paquete.\n`
    : "";

  return `Estimado ${nombreCliente}:

Queremos informarle que su exigible es de ${importeArgentina(fila.Exigible)}.

Puede realizar el pago a la siguiente cuenta bancaria:
${datosBancarios}
${lineaPlazo}
Una vez realizado el pago, tenga a bien informarlo a través de nuestro sistema de gestión de cobranzas:

Acceso al sistema:
https://www.dintersa.com.ar/pagos

Desde allí podrá informar el comprobante y dejarlo registrado en nuestro sistema.

Clave de acceso: ${fila.Dni || "-"}

Muchas gracias.

Dinter S.A.`;
}

function abrirWhatsAppCobranza(fila, indice) {
  const celular = numeroWhatsApp(fila.Celular);
  if (!telefonoValido(fila.Celular)) {
    Swal.fire("Teléfono pendiente", "Agregue o corrija el teléfono desde la tabla para poder continuar.", "warning");
    return;
  }

  const mensaje = textoMensajeCobranza(fila);
  $("#texto_whatsapp_cobranza").val(mensaje);
  $("#btn_enviar_whatsapp_cobranza").data("celular", celular).data("fila", indice)
    .attr("href", `https://wa.me/${celular}?text=${encodeURIComponent(mensaje)}`);
  $("#modal_whatsapp_cobranza").modal("show");
}

async function confirmarYAbrirWhatsAppCobranza(indice) {
  const fila = tablaCobranza.row(indice).data();
  if (!fila) return;

  if (filaEnviada(fila)) {
    const confirmacion = await Swal.fire({
      icon: "warning",
      title: "Ya se le envió un aviso",
      html: `A <strong>${escaparCobranza(fila.RazonSocial)}</strong> ya se le envió un mensaje el ` +
        `<strong>${escaparCobranza(fechaHoraArgentina(fila.UltimoEnvio))}</strong> (${escaparCobranza(fila.UltimoEnvioUsuario || "")}). ` +
        `¿Enviar de todas formas?`,
      showCancelButton: true,
      confirmButtonText: "Enviar de todas formas",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#f1734f",
    });
    if (!confirmacion.isConfirmed) return;
  }

  abrirWhatsAppCobranza(fila, indice);
}

function actualizarCamposBancoCobranzaDirecta() {
  const esEfectivo = String($("#cobranza_directa_tipo_operacion").val() || "").toLowerCase() === "efectivo";
  $("#grupo_banco_cobranza_directa, #grupo_operacion_cobranza_directa").toggleClass("d-none", esEfectivo);
}

function abrirModalCobranzaDirecta() {
  $("#cobranza_directa_cliente").val(null).trigger("change");
  $("#cobranza_directa_fecha").val(new Date().toISOString().slice(0, 10));
  $("#cobranza_directa_tipo_operacion").val("");
  $("#cobranza_directa_banco").val("");
  $("#cobranza_directa_operacion").val("");
  $("#cobranza_directa_importe").val("");
  $("#cobranza_directa_observaciones").val("");
  actualizarCamposBancoCobranzaDirecta();
  if (dropzoneCobranzaDirecta) dropzoneCobranzaDirecta.removeAllFiles(true);
  $("#ocr_estado_comprobante").addClass("d-none").removeClass("text-success text-danger").text("");
  $("#ocr_overlay_cobranza_directa").addClass("d-none");
  $("#modalCobranzaDirecta").modal("show");
}

function parsearImporteArgentino(texto) {
  let limpio = String(texto || "").replace(/\$/g, "").replace(/\s/g, "").trim();
  if (limpio === "") return 0;

  if (limpio.includes(",")) {
    limpio = limpio.replace(/\./g, "").replace(",", ".");
  } else {
    const partes = limpio.split(".");
    if (partes.length > 1 && partes[partes.length - 1].length === 3) {
      limpio = partes.join("");
    }
  }

  const numero = parseFloat(limpio);
  return isNaN(numero) ? 0 : numero;
}

function formatearImporteArgentino(valor) {
  const numero = Number(valor) || 0;
  return numero.toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function extraerDatosComprobante(texto) {
  const datos = {};

  const matchFecha = texto.match(/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/);
  if (matchFecha) {
    let [, dia, mes, anio] = matchFecha;
    if (anio.length === 2) anio = `20${anio}`;
    dia = dia.padStart(2, "0");
    mes = mes.padStart(2, "0");
    if (Number(dia) <= 31 && Number(mes) <= 12) {
      datos.fecha = `${anio}-${mes}-${dia}`;
    }
  }

  const regexImporte = /\$?\s?(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{1,2})?)/;

  // Preferí números pegados a un "$" (más confiable que cualquier número suelto
  // en una línea que "contenga" un $, ya que el OCR a veces mezcla renglones).
  const regexImporteDolar = /\$\s?(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{1,2})?)/g;
  const candidatosDolar = [...texto.matchAll(regexImporteDolar)]
    .map((m) => parsearImporteArgentino(m[1]))
    .filter((v) => v > 0);

  let valorImporte = candidatosDolar.length ? Math.max(...candidatosDolar) : null;

  if (!valorImporte) {
    let importeTexto = null;
    for (const linea of texto.split("\n")) {
      if (/importe|monto|total|transferiste|enviaste|pagaste/i.test(linea)) {
        const m = linea.match(regexImporte);
        if (m) { importeTexto = m[1]; break; }
      }
    }
    if (!importeTexto) {
      const m = texto.match(regexImporte);
      if (m) importeTexto = m[1];
    }
    if (importeTexto) valorImporte = parsearImporteArgentino(importeTexto);
  }

  if (valorImporte && valorImporte > 0) datos.importe = valorImporte;

  // Mercado Pago: el dato que sirve como "número de operación" es el "Código de identificación",
  // no el "N.° de operación de Mercado Pago" (ese es interno de MP y no lo necesitamos).
  const regexCodigoIdentificacion = /c[oó]digo\s+de\s+identificaci[oó]n[:\s]{0,15}([A-Za-z0-9]{6,30})/i;
  const matchCodigo = texto.match(regexCodigoIdentificacion);
  if (matchCodigo) {
    datos.operacion = matchCodigo[1];
  } else {
    const regexOperacion = /(?:operaci[oó]n|comprobante|referencia|transacci[oó]n|nro\.?|n[°º])\D{0,12}(\d{5,20})/i;
    const matchOperacion = texto.match(regexOperacion);
    if (matchOperacion) {
      datos.operacion = matchOperacion[1];
    } else {
      const numeroLargo = texto.match(/\b\d{8,20}\b/);
      if (numeroLargo) datos.operacion = numeroLargo[0];
    }
  }

  if (/\bmacro\b/i.test(texto)) {
    datos.banco = "Banco Macro";
  } else if (/c[oó]rdoba/i.test(texto)) {
    datos.banco = "Banco Córdoba";
  }

  const textoMinuscula = texto.toLowerCase();
  const posTransferencia = textoMinuscula.search(/transferencia/);
  const posDeposito = textoMinuscula.search(/dep[oó]sito/);
  if (posTransferencia !== -1 && (posDeposito === -1 || posTransferencia <= posDeposito)) {
    datos.tipoOperacion = "transferencia";
  } else if (posDeposito !== -1) {
    datos.tipoOperacion = "deposito";
  }

  return datos;
}

function leerComprobanteConOCR(archivo) {
  if (typeof Tesseract === "undefined") return;

  $("#ocr_estado_comprobante").addClass("d-none").removeClass("text-success text-danger").text("");
  $("#ocr_overlay_cobranza_directa").removeClass("d-none");

  Tesseract.recognize(archivo, "spa")
    .then(({ data: { text } }) => {
      const datos = extraerDatosComprobante(text || "");
      const leidos = [];

      if (datos.fecha) {
        $("#cobranza_directa_fecha").val(datos.fecha);
        leidos.push("fecha");
      }
      if (datos.importe) {
        $("#cobranza_directa_importe").val(formatearImporteArgentino(datos.importe));
        leidos.push("importe");
      }
      if (datos.operacion) {
        $("#cobranza_directa_operacion").val(datos.operacion);
        leidos.push("N° de operación");
      }
      if (datos.tipoOperacion) {
        $("#cobranza_directa_tipo_operacion").val(datos.tipoOperacion).trigger("change");
        leidos.push("tipo de operación");
      }
      if (datos.banco) {
        $("#cobranza_directa_banco").val(datos.banco);
        leidos.push("banco");
      }

      if (leidos.length) {
        $("#ocr_estado_comprobante").removeClass("d-none").addClass("text-success").removeClass("text-danger")
          .text(`Se completó automáticamente: ${leidos.join(", ")}. Revisá los datos antes de guardar.`);
      } else {
        $("#ocr_estado_comprobante").removeClass("d-none").addClass("text-danger").removeClass("text-success")
          .text("No se pudieron leer datos del comprobante. Completá los campos manualmente.");
      }
    })
    .catch((error) => {
      console.log(error);
      $("#ocr_estado_comprobante").removeClass("d-none").addClass("text-danger").removeClass("text-success")
        .text("No se pudo leer el comprobante automáticamente. Completá los campos manualmente.");
    })
    .finally(() => {
      $("#ocr_overlay_cobranza_directa").addClass("d-none");
    });
}

function guardarCobranzaDirecta() {
  const idCliente = $("#cobranza_directa_cliente").val();
  const fecha = $("#cobranza_directa_fecha").val();
  let tipoOperacion = $("#cobranza_directa_tipo_operacion").val();
  let banco = $("#cobranza_directa_banco").val();
  let operacion = $("#cobranza_directa_operacion").val().trim();
  const importe = parsearImporteArgentino($("#cobranza_directa_importe").val());
  const observaciones = $("#cobranza_directa_observaciones").val().trim();

  if (String(tipoOperacion).toLowerCase() === "efectivo") {
    banco = "CAJA";
    operacion = "EFECTIVO";
  }

  if (!idCliente || !fecha || !tipoOperacion || importe <= 0 ||
      (String(tipoOperacion).toLowerCase() !== "efectivo" && (!banco || !operacion))) {
    Swal.fire("Atención", "Completá cliente, fecha, tipo, banco, operación e importe.", "warning");
    return;
  }

  $.ajax({
    url: "control/procesos/php/panel.php",
    type: "POST",
    dataType: "json",
    data: {
      IngresarCobranzaDirecta: 1,
      idCliente, fecha, tipoOperacion, banco, operacion, importe, observaciones,
    },
    beforeSend: function () {
      $("#btn_guardar_cobranza_directa").prop("disabled", true)
        .html('<span class="spinner-border spinner-border-sm me-1"></span> Guardando...');
    },
    success: function (respuesta) {
      if (!respuesta.success) {
        Swal.fire("Error", respuesta.error || "No se pudo guardar el pago.", "error");
        return;
      }

      idCobranzaDirectaPendienteComprobante = respuesta.idCobranza || 0;

      if (dropzoneCobranzaDirecta && dropzoneCobranzaDirecta.getQueuedFiles().length > 0) {
        dropzoneCobranzaDirecta.processQueue();
      } else {
        $("#modalCobranzaDirecta").modal("hide");
        Swal.fire({
          toast: true, position: "top-end", icon: "success",
          title: "Pago cargado. Queda pendiente de conciliación.",
          showConfirmButton: false, timer: 2500, timerProgressBar: true,
        });
      }
    },
    error: function (xhr) {
      console.log(xhr.responseText);
      Swal.fire("Error", "Error de conexión al guardar el pago.", "error");
    },
    complete: function () {
      $("#btn_guardar_cobranza_directa").prop("disabled", false)
        .html('<i class="mdi mdi-content-save mdi-18px"></i> Guardar pago');
    },
  });
}

function reconstruirTablaCobranza(filas) {
  tablaCobranza.clear();
  if (filas && filas.length) tablaCobranza.rows.add(filas);
  tablaCobranza.draw();
}

$(document).ready(function () {
  tablaCobranza = $("#tabla_cobranza").DataTable({
    data: [],
    paging: true,
    searching: true,
    responsive: true,
    pageLength: 100,
    order: [[0, "asc"], [1, "asc"]],
    language: {
      emptyTable: "Suba un archivo CSV para ver el exigible.",
      search: "Buscar:", lengthMenu: "Mostrar _MENU_ filas",
      info: "Mostrando _START_ a _END_ de _TOTAL_ filas",
      infoEmpty: "Sin filas", zeroRecords: "No se encontraron resultados",
      paginate: { previous: "Anterior", next: "Siguiente" },
    },
    columns: [
      { data: "Recorrido", defaultContent: "-" },
      { data: "Ncliente" },
      { data: "RazonSocial", render: (dato, tipo, fila) => tipo === "display"
        ? `<span class="${fila.Encontrado ? "" : "text-danger"}">${escaparCobranza(dato)}</span>` : dato },
      { data: "Exigible", className: "text-end", render: (dato, tipo) => tipo === "display" ? importeArgentina(dato) : dato },
      { data: "Celular", defaultContent: "", render: (dato, tipo, fila, meta) => {
        if (tipo !== "display") return dato;
        const valido = telefonoValido(dato);
        const texto = dato ? escaparCobranza(dato) : "Sin teléfono";
        return `<button type="button" class="btn btn-link btn-sm p-0 btn-editar-telefono ${valido ? "" : "text-danger"}" data-fila="${meta.row}" title="Editar teléfono"><i class="mdi mdi-pencil-outline me-1"></i>${texto}</button>${valido ? "" : '<span class="badge bg-danger ms-2">Revisar</span>'}`;
      } },
      { data: null, orderable: false, searchable: false, render: (dato, tipo, fila) => {
        if (tipo !== "display") return filaEnviada(fila) ? 1 : 0;
        if (!filaEnviada(fila)) return '<span class="text-muted">Sin enviar</span>';
        const cantidad = Number(fila.CantidadEnvios || 1);
        const extra = cantidad > 1 ? ` <span class="badge bg-secondary">x${cantidad}</span>` : "";
        return `<span class="badge bg-success" title="Enviado por ${escaparCobranza(fila.UltimoEnvioUsuario || "")}"><i class="mdi mdi-check"></i> ${escaparCobranza(fechaHoraArgentina(fila.UltimoEnvio))}</span>${extra}`;
      } },
      { data: null, orderable: false, searchable: false, className: "text-center", render: (dato, tipo, fila, meta) => {
        if (!telefonoValido(fila.Celular)) return `<button type="button" class="btn btn-sm btn-outline-danger btn-editar-telefono" data-fila="${meta.row}" title="Agregar o corregir teléfono"><i class="mdi mdi-phone-plus mdi-18px"></i></button>`;
        const clase = filaEnviada(fila) ? "btn-outline-success" : "btn-success";
        return `<button type="button" class="btn btn-sm ${clase} btn-whatsapp" data-fila="${meta.row}" title="Enviar aviso por WhatsApp"><i class="mdi mdi-whatsapp mdi-18px"></i></button>`;
      } },
    ],
  });

  $("#tabla_cobranza tbody").on("click", ".btn-whatsapp", function () {
    confirmarYAbrirWhatsAppCobranza(Number($(this).data("fila")));
  });

  $("#tabla_cobranza tbody").on("click", ".btn-editar-telefono", function () {
    editarTelefonoCobranza(Number($(this).data("fila")));
  });

  $("#ultimo_archivo_cobranza").on("click", "#btn_editar_fecha_limite_cobranza", function () {
    editarFechaLimiteCobranza();
  });

  $("#texto_whatsapp_cobranza").on("input", function () {
    const celular = $("#btn_enviar_whatsapp_cobranza").data("celular");
    $("#btn_enviar_whatsapp_cobranza").attr("href", `https://wa.me/${celular}?text=${encodeURIComponent($(this).val())}`);
  });

  $("#btn_enviar_whatsapp_cobranza").on("click", function () {
    if (!importacionActualId) return;
    const indice = $(this).data("fila");
    const fila = indice !== undefined ? tablaCobranza.row(Number(indice)).data() : null;
    if (!fila) return;

    $.post("control/procesos/php/cobranza_exigible.php", {
      accion: "registrar_mensaje",
      importacion_id: importacionActualId,
      ncliente: fila.Ncliente,
      celular: $(this).data("celular"),
      mensaje: $("#texto_whatsapp_cobranza").val(),
    }, null, "json").done(function (respuesta) {
      if (!respuesta.success) return;
      mensajesIniciados++;
      $("#cantidad_mensajes_cobranza").text(mensajesIniciados);

      fila.UltimoEnvio = respuesta.fecha;
      fila.UltimoEnvioUsuario = respuesta.usuario;
      fila.CantidadEnvios = Number(fila.CantidadEnvios || 0) + 1;
      tablaCobranza.row(Number(indice)).data(fila).invalidate().draw(false);
    });
  });

  $.post("control/procesos/php/cobranza_exigible.php", { accion: "ultimo_archivo" }, null, "json")
    .done((respuesta) => {
      mostrarUltimaImportacion(respuesta.data);
      reconstruirTablaCobranza(respuesta.filas);
      if (respuesta.filas && respuesta.filas.length) {
        const total = respuesta.filas.reduce((suma, fila) => suma + Number(fila.Exigible || 0), 0);
        $("#resumen_cobranza").removeClass("d-none").html(`<strong>${respuesta.filas.length}</strong> clientes · Exigible total: <strong>${importeArgentina(total)}</strong>`);
      }
    });

  $("#card_enviar_exigibles").on("click", function () {
    $("#panel_landing_cobranza").addClass("d-none");
    $("#panel_exigibles").removeClass("d-none");
  });

  $("#btn_volver_landing_cobranza").on("click", function () {
    $("#panel_exigibles").addClass("d-none");
    $("#panel_landing_cobranza").removeClass("d-none");
  });

  $("#card_ingresar_cobranza").on("click", function () {
    abrirModalCobranzaDirecta();
  });

  $("#cobranza_directa_cliente").select2({
    placeholder: "Buscar cliente por número, nombre, CUIT o teléfono...",
    width: "100%",
    minimumInputLength: 2,
    dropdownParent: $("#modalCobranzaDirecta"),
    ajax: {
      url: "control/procesos/php/panel.php",
      type: "POST",
      dataType: "json",
      delay: 300,
      data: (params) => ({ BuscarClientesCobranzaDirecta: 1, term: params.term }),
      processResults: (data) => ({ results: data }),
    },
  });

  $("#cobranza_directa_tipo_operacion").on("change", actualizarCamposBancoCobranzaDirecta);

  $("#cobranza_directa_importe").on("blur", function () {
    const valor = parsearImporteArgentino($(this).val());
    $(this).val(valor > 0 ? formatearImporteArgentino(valor) : "");
  });

  if ($("#dropzoneComprobanteCobranzaDirecta").length) {
    dropzoneCobranzaDirecta = new Dropzone("#dropzoneComprobanteCobranzaDirecta", {
      url: "procesos/php/upload.php",
      autoProcessQueue: false,
      maxFiles: 1,
      acceptedFiles: ".jpeg,.jpg,.png,.gif",
      dictDefaultMessage: "Arrastrá una imagen o hacé click",
      thumbnailWidth: 700,
      thumbnailHeight: 700,
      thumbnailMethod: "contain",
      previewTemplate: `
      <div class="dz-preview dz-file-preview">
        <div class="dz-image"><img data-dz-thumbnail /></div>
        <div class="dz-details">
          <div class="dz-filename"><span data-dz-name></span></div>
          <div class="dz-size" data-dz-size></div>
          <a class="dz-remove" href="javascript:undefined;" data-dz-remove>Quitar</a>
        </div>
      </div>
      `,
      init: function () {
        this.on("maxfilesexceeded", function (file) {
          this.removeAllFiles(true);
          this.addFile(file);
        });
        this.on("addedfile", function (file) {
          leerComprobanteConOCR(file);
        });
        this.on("removedfile", function () {
          $("#ocr_estado_comprobante").addClass("d-none").removeClass("text-success text-danger").text("");
          $("#ocr_overlay_cobranza_directa").addClass("d-none");
        });
        this.on("sending", function (file, xhr, formData) {
          formData.append("idCobranza", idCobranzaDirectaPendienteComprobante);
        });
        this.on("success", function () {
          Swal.fire({
            toast: true, position: "top-end", icon: "success",
            title: "Pago y comprobante cargados. Queda pendiente de conciliación.",
            showConfirmButton: false, timer: 2500, timerProgressBar: true,
          });
          idCobranzaDirectaPendienteComprobante = 0;
        });
        this.on("error", function (file, mensajeError) {
          Swal.fire("Error", typeof mensajeError === "string" ? mensajeError : "No se pudo subir el comprobante.", "error");
        });
        this.on("queuecomplete", function () {
          $("#modalCobranzaDirecta").modal("hide");
          setTimeout(() => { if (dropzoneCobranzaDirecta) dropzoneCobranzaDirecta.removeAllFiles(false); }, 500);
        });
      },
    });
  }

  $("#btn_guardar_cobranza_directa").on("click", guardarCobranzaDirecta);

  $("#form_cobranza").on("submit", function (evento) {
    evento.preventDefault();
    const archivo = $("#archivo_exigible")[0].files[0];
    if (!archivo) {
      Swal.fire("Atención", "Seleccione el archivo CSV de exigibles.", "warning");
      return;
    }

    const datos = new FormData();
    datos.append("accion", "procesar_exigible");
    datos.append("archivo", archivo);
    datos.append("fecha_limite", $("#fecha_limite_exigible").val() || "");
    $("#btn_procesar_exigible").prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span> Procesando');

    $.ajax({
      url: "control/procesos/php/cobranza_exigible.php", type: "POST", data: datos,
      processData: false, contentType: false, dataType: "json",
    }).done(function (respuesta) {
      if (!respuesta.success) throw new Error(respuesta.error || "No se pudo procesar el archivo.");
      reconstruirTablaCobranza(respuesta.data);
      mostrarUltimaImportacion(respuesta.importacion);
      const total = respuesta.data.reduce((suma, fila) => suma + Number(fila.Exigible || 0), 0);
      $("#resumen_cobranza").removeClass("d-none").html(`<strong>${respuesta.data.length}</strong> clientes · Exigible total: <strong>${importeArgentina(total)}</strong>${respuesta.omitidas ? ` · ${respuesta.omitidas} filas omitidas` : ""}`);
    }).fail(function (xhr) {
      const mensaje = xhr.responseJSON?.error || xhr.responseText || "No se pudo procesar el archivo.";
      Swal.fire("Error", mensaje, "error");
    }).always(function () {
      $("#btn_procesar_exigible").prop("disabled", false).html('<i class="mdi mdi-file-table-outline me-1"></i> Procesar archivo');
    });
  });
});
