const URL_DASHBOARD = "control/procesos/php/dashboard.php";
let tablaProductosDashboard;
let chartVentasVendedor = null;
let chartVentasHora = null;
let tablaMovimientosDashboard = null;

$(document).ready(function () {
  cargarResumenDashboard();
  cargarTablaProductosDashboard();
  cargarRankingVendedores();
  cargarGraficoVentasVendedor();
  cargarGraficoVentasHora();
  cargarUltimosMovimientos();
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
function cargarRankingVendedores() {
  $.ajax({
    url: URL_DASHBOARD,
    type: "POST",
    dataType: "json",
    data: { accion: "ranking_vendedores" },
    success: function (r) {
      let html = "";

      if (!r.success || !r.data || r.data.length === 0) {
        $("#ranking_vendedores").html('<div class="text-muted">Sin datos.</div>');
        return;
      }

      let maximo = parseFloat(r.data[0].TotalFacturado || 0);

      r.data.forEach(function (v, index) {
        let total = parseFloat(v.TotalFacturado || 0);
        let porcentaje = maximo > 0 ? (total / maximo) * 100 : 0;

        html += `
          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <div>
                <strong>${index + 1}. ${v.Usuario || "Sin usuario"}</strong>
                <br>
                <small class="text-muted">${formatoNumero(v.CantidadVentas)} ventas</small>
              </div>
              <strong>${formatoMoneda(total)}</strong>
            </div>

            <div class="progress mt-1" style="height:6px;">
              <div class="progress-bar" style="width:${porcentaje}%"></div>
            </div>
          </div>
        `;
      });

      $("#ranking_vendedores").html(html);
    },
  });
}

function cargarGraficoVentasVendedor() {
  $.ajax({
    url: URL_DASHBOARD,
    type: "POST",
    dataType: "json",
    data: { accion: "ranking_vendedores" },
    success: function (r) {
      if (!r.success) return;

      let labels = [];
      let valores = [];

      r.data.forEach(function (v) {
        labels.push(v.Usuario || "Sin usuario");
        valores.push(parseFloat(v.TotalFacturado || 0));
      });

      let ctx = document.getElementById("chart_ventas_vendedor");

      if (chartVentasVendedor) {
        chartVentasVendedor.destroy();
      }

      chartVentasVendedor = new Chart(ctx, {
        type: "bar",
        data: {
          labels: labels,
          datasets: [
            {
              label: "Facturado",
              data: valores,
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
          },
          scales: {
            y: {
              ticks: {
                callback: function (value) {
                  return "$ " + Number(value).toLocaleString("es-AR");
                },
              },
            },
          },
        },
      });
    },
  });
}

function cargarGraficoVentasHora() {
  $.ajax({
    url: URL_DASHBOARD,
    type: "POST",
    dataType: "json",
    data: { accion: "ventas_por_hora" },
    success: function (r) {
      if (!r.success) return;

      let labels = [];
      let valores = [];

      r.data.forEach(function (h) {
        labels.push(h.Hora + " hs");
        valores.push(parseInt(h.CantidadVentas || 0));
      });

      let ctx = document.getElementById("chart_ventas_hora");

      if (chartVentasHora) {
        chartVentasHora.destroy();
      }

      chartVentasHora = new Chart(ctx, {
        type: "line",
        data: {
          labels: labels,
          datasets: [
            {
              label: "Ventas",
              data: valores,
              tension: 0.35,
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
          },
        },
      });
    },
  });
}

function cargarUltimosMovimientos() {
  tablaMovimientosDashboard = $("#tabla_ultimos_movimientos").DataTable({
    destroy: true,
    searching: false,
    paging: false,
    info: false,
    ordering: false,
    ajax: {
      url: URL_DASHBOARD,
      type: "POST",
      data: { accion: "ultimos_movimientos" },
      dataSrc: "data",
    },
    columns: [
      {
        data: "Fecha",
        render: function (data) {
          if (!data) return "";
          let partes = data.split(" ");
          let fecha = partes[0].split("-").reverse().join("/");
          let hora = partes[1] ? partes[1].substring(0, 5) : "";
          return `${fecha}<br><small class="text-muted">${hora} hs</small>`;
        },
      },
      { data: "Producto" },
      {
        data: "TipoMovimiento",
        render: function (data, type, row) {
          let tipo = data || row.Tipo || "-";
          return `<span class="badge bg-secondary">${tipo}</span>`;
        },
      },
      {
        data: "Cantidad",
        className: "text-end",
        render: function (data) {
          return formatoNumero(data);
        },
      },
      { data: "Usuario" },
    ],
  });
}
