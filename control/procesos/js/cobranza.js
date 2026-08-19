let tablaCobranza = null;
let importacionActualId = 0;
let mensajesIniciados = 0;

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

function mostrarUltimaImportacion(importacion) {
  if (!importacion) {
    $("#ultimo_archivo_cobranza").html("Todavía no hay archivos registrados.");
    return;
  }
  importacionActualId = Number(importacion.id || 0);
  mensajesIniciados = Number(importacion.MensajesIniciados || 0);
  $("#ultimo_archivo_cobranza").html(
    `Último archivo: <strong>${escaparCobranza(importacion.Archivo)}</strong> · ` +
    `${escaparCobranza(importacion.Fecha)} · Subido por: <strong>${escaparCobranza(importacion.Usuario)}</strong> · ` +
    `${Number(importacion.CantidadFilas || 0)} clientes · ` +
    `Envíos iniciados: <strong id="cantidad_mensajes_cobranza">${mensajesIniciados}</strong>`,
  );
}

function fechaArgentina(fecha) {
  const partes = String(fecha || "").split("-");
  return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : fecha;
}

function importeArgentina(importe) {
  return Number(importe || 0).toLocaleString("es-AR", { style: "currency", currency: "ARS" });
}

function guardarTelefonoEnFila(indice, celular) {
  const filaTabla = tablaCobranza.row(indice);
  const fila = filaTabla.data();
  fila.Celular = celular;
  filaTabla.data(fila).invalidate().draw(false);
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

function abrirWhatsAppCobranza(fila) {
  const celular = numeroWhatsApp(fila.Celular);
  if (!telefonoValido(fila.Celular)) {
    Swal.fire("Teléfono pendiente", "Agregue o corrija el teléfono desde la tabla para poder continuar.", "warning");
    return;
  }

  const nombreCliente = String(fila.RazonSocial || "cliente").trim();
  const datosBancarios = String(fila.Distribuidora || "DINTER").trim().toUpperCase() === "RAK"
    ? "Alias: *ELRAK.PANINI*\nCBU: *0200302101000001152701*\nBanco Córdoba\nCUIT: *30669104959*"
    : "Cuenta 1\nAlias: *DINTER.SA.*\nCBU: *2850331630094145090021*\nBanco Macro\n\nCuenta 2\nAlias: *DINTER.SA.CBA*\nCBU: *0200931901000025067115*\nBanco Córdoba";
  const mensaje = `Estimado ${nombreCliente}:

Queremos informarle que su exigible es de ${importeArgentina(fila.Exigible)}.

Puede realizar el pago a la siguiente cuenta bancaria:
${datosBancarios}

Una vez realizado el pago, tenga a bien informarlo a través de nuestro sistema de gestión de cobranzas:

Acceso al sistema:
https://www.dintersa.com.ar/pagos

Desde allí podrá informar el comprobante y dejarlo registrado en nuestro sistema.

Clave de acceso: ${fila.Dni || "-"}

Muchas gracias.

Dinter S.A.`;

  $("#texto_whatsapp_cobranza").val(mensaje);
  $("#btn_enviar_whatsapp_cobranza").data("celular", celular)
    .attr("href", `https://wa.me/${celular}?text=${encodeURIComponent(mensaje)}`);
  $("#modal_whatsapp_cobranza").modal("show");
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
      { data: null, orderable: false, searchable: false, className: "text-center", render: (dato, tipo, fila, meta) => {
        if (!telefonoValido(fila.Celular)) return `<button type="button" class="btn btn-sm btn-outline-danger btn-editar-telefono" data-fila="${meta.row}" title="Agregar o corregir teléfono"><i class="mdi mdi-phone-plus mdi-18px"></i></button>`;
        return `<button type="button" class="btn btn-sm btn-success btn-whatsapp" data-fila="${meta.row}" title="Enviar aviso por WhatsApp"><i class="mdi mdi-whatsapp mdi-18px"></i></button>`;
      } },
    ],
  });

  $("#tabla_cobranza tbody").on("click", ".btn-whatsapp", function () {
    abrirWhatsAppCobranza(tablaCobranza.row(Number($(this).data("fila"))).data());
  });

  $("#tabla_cobranza tbody").on("click", ".btn-editar-telefono", function () {
    editarTelefonoCobranza(Number($(this).data("fila")));
  });

  $("#texto_whatsapp_cobranza").on("input", function () {
    const celular = $("#btn_enviar_whatsapp_cobranza").data("celular");
    $("#btn_enviar_whatsapp_cobranza").attr("href", `https://wa.me/${celular}?text=${encodeURIComponent($(this).val())}`);
  });

  $("#btn_enviar_whatsapp_cobranza").on("click", function () {
    if (!importacionActualId) return;
    $.post("control/procesos/php/cobranza_exigible.php", {
      accion: "registrar_mensaje", importacion_id: importacionActualId,
    }).done(function () {
      mensajesIniciados++;
      $("#cantidad_mensajes_cobranza").text(mensajesIniciados);
    });
  });

  $.post("control/procesos/php/cobranza_exigible.php", { accion: "ultimo_archivo" }, null, "json")
    .done((respuesta) => mostrarUltimaImportacion(respuesta.data));

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
    $("#btn_procesar_exigible").prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span> Procesando');

    $.ajax({
      url: "control/procesos/php/cobranza_exigible.php", type: "POST", data: datos,
      processData: false, contentType: false, dataType: "json",
    }).done(function (respuesta) {
      if (!respuesta.success) throw new Error(respuesta.error || "No se pudo procesar el archivo.");
      tablaCobranza.clear().rows.add(respuesta.data).draw();
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
