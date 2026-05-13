let ventasImportacion = [];
const URL_IMPORTAR_VENTAS = "control/procesos/php/importar_ventas.php";

$("#btn_leer_excel_ventas").click(function () {
  const archivo = document.getElementById("archivo_importar_ventas").files[0];

  if (!archivo) {
    Swal.fire("Atención", "Seleccioná un archivo Excel.", "warning");
    return;
  }

  const reader = new FileReader();

  reader.onload = function (e) {
    const data = new Uint8Array(e.target.result);
    const workbook = XLSX.read(data, { type: "array" });
    const sheet = workbook.Sheets[workbook.SheetNames[0]];
    const rows = XLSX.utils.sheet_to_json(sheet, { defval: "" });

    if (!rows.length) {
      Swal.fire("Atención", "El Excel está vacío.", "warning");
      return;
    }

    const datos = rows
      .map((r) => ({
        Cliente: String(r.CLIENTE || r.Cliente || r.cliente || "").trim(),
        Figuritas: parseInt(r.FIGURITAS || r.Figuritas || r.figuritas || 0),
        Album: parseInt(r.ALBUM || r.Album || r.album || 0),
      }))
      .filter((r) => r.Cliente !== "");

    validarImportacionVentas(datos);
  };

  reader.readAsArrayBuffer(archivo);
});

function validarImportacionVentas(datos) {
  $.ajax({
    url: URL_IMPORTAR_VENTAS,
    type: "POST",
    dataType: "json",
    data: {
      accion: "validar",
      datos: JSON.stringify(datos),
    },

    success: function (r) {
      console.log("VALIDACION:", r);

      if (!r.success) {
        Swal.fire("Error", r.error || "No se pudo validar.", "error");
        return;
      }

      ventasImportacion = r.data || [];

      renderPreviewImportacionVentas();

      let errores = ventasImportacion.filter((x) => x.estado !== "OK");

      $("#btn_confirmar_importacion_ventas").prop("disabled", errores.length > 0 || ventasImportacion.length === 0);

      if (errores.length > 0) {
        Swal.fire({
          icon: "warning",
          title: "Clientes no encontrados",
          text: "Hay registros sin coincidencia. Revisalos antes de importar.",
        });
      }
    },

    error: function (xhr) {
      console.log(xhr.responseText);

      Swal.fire("Error", "Error validando Excel.", "error");
    },
  });
}

function renderPreviewImportacionVentas() {
  let html = "";

  if (!ventasImportacion.length) {
    html = `
      <tr>
        <td colspan="6" class="text-center text-muted">
          Sin datos.
        </td>
      </tr>
    `;
  }

  ventasImportacion.forEach((row, i) => {
    let badge = "";

    if (row.estado === "OK") {
      badge = `<span class="badge bg-success">OK</span>`;
    } else {
      badge = `<span class="badge bg-danger">No encontrado</span>`;
    }

    html += `
      <tr>

        <td>${i + 1}</td>

        <td>
          <b>${row.Ncliente}</b>
        </td>

        <td>
          ${
            row.RazonSocial
              ? `[${row.Ncliente}] ${row.RazonSocial}`
              : '<span class="text-danger">Cliente inexistente</span>'
          }
        </td>

        <td class="text-end">
          ${formatoNumero(row.Figuritas)}
        </td>

        <td class="text-end">
          ${formatoNumero(row.Album)}
        </td>

        <td>
          ${badge}
        </td>

      </tr>
    `;
  });

  $("#tabla_preview_importar_ventas tbody").html(html);
}

$(document).on("change", ".cliente_importacion", function () {
  const index = parseInt($(this).data("index"));
  const idCliente = parseInt($(this).val() || 0);

  ventasImportacion[index].idCliente = idCliente;
  ventasImportacion[index].estado = idCliente > 0 ? "OK" : "SIN_COINCIDENCIA";
});

$("#btn_confirmar_importacion_ventas").click(function () {
  const pendientes = ventasImportacion.filter((r) => !r.idCliente || parseInt(r.idCliente) <= 0);

  if (pendientes.length > 0) {
    Swal.fire("Atención", "Hay clientes sin seleccionar. Corregilos antes de importar.", "warning");
    return;
  }

  Swal.fire({
    title: "Confirmar importación",
    text: "Se generarán las ventas y se descontará stock.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sí, importar",
    cancelButtonText: "Cancelar",
  }).then((result) => {
    if (!result.isConfirmed) return;

    $.ajax({
      url: URL_IMPORTAR_VENTAS,
      type: "POST",
      dataType: "json",
      data: {
        accion: "importar",
        datos: JSON.stringify(ventasImportacion),
      },
      success: function (r) {
        if (r.success == 1) {
          Swal.fire("Importación finalizada", "Ventas generadas: " + r.total, "success");
          ventasImportacion = [];
          renderPreviewImportacionVentas();
          $("#archivo_importar_ventas").val("");
          $("#btn_confirmar_importacion_ventas").prop("disabled", true);
        } else {
          Swal.fire("Error", r.error || "No se pudo importar.", "error");
        }
      },
      error: function (xhr) {
        console.log(xhr.responseText);
        Swal.fire("Error", "Error importando ventas.", "error");
      },
    });
  });
});

function formatoNumero(valor) {
  return parseInt(valor || 0).toLocaleString("es-AR");
}

function escapeHtml(str) {
  return String(str ?? "").replace(/[&<>"'`=\/]/g, function (s) {
    return {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
      "/": "&#x2F;",
      "`": "&#x60;",
      "=": "&#x3D;",
    }[s];
  });
}
