let tablaTurnos;

const URL_TURNOS = "control/procesos/php/turnos.php";

$(document).ready(function () {
  $("#filtro_fecha_turnos").val(fechaHoy());
  cargarResumenTurnos();
  cargarTablaTurnos();

  $("#filtro_fecha_turnos, #filtro_estado_turnos").on("change", function () {
    cargarResumenTurnos();

    if ($.fn.DataTable.isDataTable("#tabla_turnos")) {
      $("#tabla_turnos").DataTable().ajax.reload(null, false);
    }
  });
});

function fechaHoy() {
  let d = new Date();
  let yyyy = d.getFullYear();
  let mm = String(d.getMonth() + 1).padStart(2, "0");
  let dd = String(d.getDate()).padStart(2, "0");

  return `${yyyy}-${mm}-${dd}`;
}

function cargarResumenTurnos() {
  $.ajax({
    url: URL_TURNOS,
    type: "POST",
    dataType: "json",
    data: {
      accion: "resumen",
      fecha: $("#filtro_fecha_turnos").val(),
    },
    success: function (r) {
      $("#turnos_total").text(r.TOTAL || 0);
      $("#turnos_pendientes").text(r.PENDIENTE || 0);
      $("#turnos_confirmados").text(r.CONFIRMADO || 0);
      $("#turnos_retirados").text(r.RETIRADO || 0);
    },
    error: function (xhr) {
      console.log(xhr.responseText);
    },
  });
}

function cargarTablaTurnos() {
  tablaTurnos = $("#tabla_turnos").DataTable({
    destroy: true,
    pageLength: 50,
    order: [[1, "asc"]],
    ajax: {
      url: URL_TURNOS,
      type: "POST",
      dataSrc: "data",
      data: function () {
        return {
          accion: "listar",
          fecha: $("#filtro_fecha_turnos").val(),
          estado: $("#filtro_estado_turnos").val(),
        };
      },
      error: function (xhr) {
        console.log("ERROR turnos:", xhr.responseText);
      },
    },
    createdRow: function (row) {
      $(row).css("font-size", "11px");
    },
    columns: [
      {
        data: "NumeroVenta",
        render: function (data, type, row) {
          let html = `<span class="badge bg-primary">#${data}</span>`;

          if (row.NumeroOrdenVenta) {
            html += `<div class="mt-1"><span class="badge bg-dark">OV #${row.NumeroOrdenVenta}</span></div>`;
          }

          return html;
        },
      },
      {
        data: "HoraTurno",
        render: function (data) {
          if (!data) return "";
          return `<strong>${data.substring(0, 5)} hs</strong>`;
        },
      },
      {
        data: "Cliente",
        render: function (data, type, row) {
          return `
            <div><strong>${data || ""}</strong></div>
            <small class="text-muted">Cliente ID: ${row.idCliente || ""}</small>
          `;
        },
      },
      {
        data: "Telefono",
        render: function (data) {
          if (!data) return `<span class="text-muted">Sin celular</span>`;

          return `
            <a href="https://wa.me/${normalizarTelefonoWp(data)}" target="_blank">
              <i class="mdi mdi-whatsapp text-success"></i> ${data}
            </a>
          `;
        },
      },
      {
        data: "EstadoPago",
        render: function (data) {
          let clase = "warning";

          if (data === "PAGADA") clase = "success";
          if (data === "PARCIAL") clase = "info";

          return `<span class="badge bg-${clase}">${data || "PENDIENTE"}</span>`;
        },
      },
      {
        data: "EstadoTurno",
        render: function (data) {
          return badgeEstadoTurno(data);
        },
      },
      { data: "Usuario" },
      {
        data: null,
        orderable: false,
        render: function (data) {
          return `
            <i class="mdi mdi-check-circle mdi-18px text-success ms-2" style="cursor:pointer" title="Confirmar" onclick="cambiarEstadoTurno(${data.id}, 'CONFIRMADO')"></i>
            <i class="mdi mdi-package-variant-closed-check mdi-18px text-primary ms-2" style="cursor:pointer" title="Retirado" onclick="cambiarEstadoTurno(${data.id}, 'RETIRADO')"></i>
            <i class="mdi mdi-close-circle mdi-18px text-warning ms-2" style="cursor:pointer" title="Cancelar" onclick="cambiarEstadoTurno(${data.id}, 'CANCELADO')"></i>
            <i class="mdi mdi-delete mdi-18px text-danger ms-2" style="cursor:pointer" title="Eliminar" onclick="eliminarTurno(${data.id})"></i>
          `;
        },
      },
    ],
  });
}

function badgeEstadoTurno(estado) {
  let clase = "warning";
  let texto = estado || "PENDIENTE";

  if (estado === "CONFIRMADO") clase = "info";
  if (estado === "RETIRADO") clase = "success";
  if (estado === "CANCELADO") clase = "danger";

  return `<span class="badge bg-${clase}">${texto}</span>`;
}

function cambiarEstadoTurno(id, estado) {
  Swal.fire({
    title: "Cambiar estado",
    text: "¿Confirmás cambiar el turno a " + estado + "?",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sí, confirmar",
    cancelButtonText: "Cancelar",
  }).then(function (result) {
    if (!result.isConfirmed) return;

    $.ajax({
      url: URL_TURNOS,
      type: "POST",
      dataType: "json",
      data: {
        accion: "cambiar_estado",
        id: id,
        estado: estado,
      },
      success: function (r) {
        if (r.success == 1) {
          Swal.fire({
            icon: "success",
            title: "Estado actualizado",
            timer: 1000,
            showConfirmButton: false,
          });

          cargarResumenTurnos();
          tablaTurnos.ajax.reload(null, false);
        } else {
          Swal.fire("Error", r.error || "No se pudo cambiar el estado.", "error");
        }
      },
      error: function (xhr) {
        console.log(xhr.responseText);
        Swal.fire("Error", "Error actualizando turno.", "error");
      },
    });
  });
}

function eliminarTurno(id) {
  Swal.fire({
    title: "¿Eliminar turno?",
    text: "Esta acción eliminará el turno de retiro.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#fa5c7c",
  }).then(function (result) {
    if (!result.isConfirmed) return;

    $.ajax({
      url: URL_TURNOS,
      type: "POST",
      dataType: "json",
      data: {
        accion: "eliminar",
        id: id,
      },
      success: function (r) {
        if (r.success == 1) {
          Swal.fire({
            icon: "success",
            title: "Turno eliminado",
            timer: 1000,
            showConfirmButton: false,
          });

          cargarResumenTurnos();
          tablaTurnos.ajax.reload(null, false);
        } else {
          Swal.fire("Error", r.error || "No se pudo eliminar.", "error");
        }
      },
      error: function (xhr) {
        console.log(xhr.responseText);
        Swal.fire("Error", "Error eliminando turno.", "error");
      },
    });
  });
}

function normalizarTelefonoWp(tel) {
  let t = String(tel || "").replace(/\D/g, "");

  if (t === "") return "";

  if (!t.startsWith("54")) {
    t = "54" + t;
  }

  t = t.replace(/^5415/, "549");
  t = t.replace(/^5435115/, "549351");
  t = t.replace(/^549549/, "549");

  return t;
}
$(document).on("click", "#btn_imprimir_turnos", function () {
  let fecha = $("#filtro_fecha_turnos").val();

  if (!fecha) {
    Swal.fire("Atención", "Seleccioná una fecha para imprimir.", "warning");
    return;
  }

  window.open("control/procesos/php/imprimir_turnos.php?fecha=" + encodeURIComponent(fecha), "_blank");
});
