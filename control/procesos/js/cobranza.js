let tablaCobranza = null;

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

function fechaArgentina(fecha) {
  const partes = String(fecha || "").split("-");
  return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : fecha;
}

function importeArgentina(importe) {
  return Number(importe || 0).toLocaleString("es-AR", { style: "currency", currency: "ARS" });
}

function abrirWhatsAppCobranza(fila) {
  const celular = numeroWhatsApp(fila.Celular);
  if (!fila.Celular || celular.length < 10) {
    Swal.fire("Cliente sin celular", "Este cliente no posee un celular válido cargado.", "warning");
    return;
  }

  const nombreCliente = String(fila.RazonSocial || "cliente").trim();
  const mensaje = `Estimado ${nombreCliente}:

Queremos informarle que su exigible del día ${fechaArgentina(fila.Fecha)} es de ${importeArgentina(fila.Exigible)}.

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
      { data: null, orderable: false, searchable: false, className: "text-center", render: (dato, tipo, fila, meta) => {
        if (!fila.Encontrado || !fila.Celular) return '<i class="mdi mdi-whatsapp mdi-24px text-muted" title="Cliente sin celular"></i>';
        return `<button type="button" class="btn btn-sm btn-success btn-whatsapp" data-fila="${meta.row}" title="Enviar aviso por WhatsApp"><i class="mdi mdi-whatsapp mdi-18px"></i></button>`;
      } },
    ],
  });

  $("#tabla_cobranza tbody").on("click", ".btn-whatsapp", function () {
    abrirWhatsAppCobranza(tablaCobranza.row(Number($(this).data("fila"))).data());
  });

  $("#texto_whatsapp_cobranza").on("input", function () {
    const celular = $("#btn_enviar_whatsapp_cobranza").data("celular");
    $("#btn_enviar_whatsapp_cobranza").attr("href", `https://wa.me/${celular}?text=${encodeURIComponent($(this).val())}`);
  });

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
