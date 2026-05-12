const URL_DASHBOARD = "control/procesos/php/dashboard.php";
let tablaProductosDashboard;

$(document).ready(function () {
  cargarResumenDashboard();
  cargarTablaProductosDashboard();
});

function cargarResumenDashboard() {
  $.ajax({
    url: URL_DASHBOARD,
    type: "POST",
    dataType: "json",
    data: {
      accion: "resumen",
    },
    success: function (r) {
      if (r.success != 1) {
        console.log(r.error || "Error cargando resumen");
        return;
      }

      let d = r.data;

      $("#dash_ventas").text(formatoNumero(d.Ventas));
      $("#dash_facturado").text(formatoMoneda(d.TotalFacturado));
      $("#dash_cobrado").text(formatoMoneda(d.TotalCobrado));
      $("#dash_saldo").text(formatoMoneda(d.SaldoPendiente));

      $("#dash_pagadas").text(formatoNumero(d.Pagadas));
      $("#dash_parciales").text(formatoNumero(d.Parciales));
      $("#dash_pendientes").text(formatoNumero(d.Pendientes));
    },
    error: function (xhr) {
      console.log(xhr.responseText);
    },
  });
}

function cargarTablaProductosDashboard() {
  tablaProductosDashboard = $("#tabla_dashboard_productos").DataTable({
    destroy: true,
    pageLength: 25,
    order: [[3, "desc"]],
    ajax: {
      url: URL_DASHBOARD,
      type: "POST",
      data: {
        accion: "productos",
      },
      dataSrc: "data",
    },
    columns: [
      { data: "ProductoNombre" },
      {
        data: "CantidadVentas",
        className: "text-end",
        render: function (data) {
          return formatoNumero(data);
        },
      },
      {
        data: "CantidadVendida",
        className: "text-end",
        render: function (data) {
          return formatoNumero(data);
        },
      },
      {
        data: "Recaudacion",
        className: "text-end",
        render: function (data) {
          return formatoMoneda(data);
        },
      },
    ],
  });
}

function formatoMoneda(valor) {
  return (
    "$ " +
    parseFloat(valor || 0).toLocaleString("es-AR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
}

function formatoNumero(valor) {
  return parseInt(valor || 0).toLocaleString("es-AR");
}
