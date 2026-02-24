$(document).ready(function () {
  if (!$("#mis_pagos").length) {
    console.error("No existe #mis_pagos en el DOM");
    return;
  }

  const isMobile = window.matchMedia("(max-width: 576px)").matches;

  $("#mis_pagos").DataTable({
    paging: true,
    searching: false,
    ordering: false,

    // UI más limpia
    info: false,
    lengthChange: !isMobile, // en móvil oculto el "Show entries"
    pageLength: isMobile ? 10 : 100,
    pagingType: "simple",
    autoWidth: false,
    responsive: {
      details: {
        // En móvil: detalle en MODAL (en vez del +)
        display: $.fn.dataTable.Responsive.display.modal({
          header: function (row) {
            const d = row.data();
            return "Detalle del pago #" + (d.id || "");
          },
        }),
        renderer: $.fn.dataTable.Responsive.renderer.tableAll({
          tableClass: "table table-sm table-striped mb-0",
        }),
      },
    },

    lengthMenu: [
      [10, 25, 50, 100, -1],
      [10, 25, 50, 100, "All"],
    ],

    language: {
      emptyTable: "No hay pagos para mostrar",
      zeroRecords: "Sin resultados",
      paginate: { previous: "Anterior", next: "Siguiente" },
    },

    ajax: {
      url: "procesos/php/mispagos.php",
      data: { Mis_pagos: 1 },
      type: "post",
      dataSrc: function (json) {
        if (Array.isArray(json)) return json;
        if (json && Array.isArray(json.data)) return json.data;

        console.error("Respuesta inesperada del backend:", json);
        return [];
      },
    },

    columns: [
      { data: "id", defaultContent: "" },
      {
        data: null,
        defaultContent: "",
        render: function (data, type, row) {
          const fecha = row.Fecha
            ? row.Fecha.split("-").reverse().join("/")
            : "";
          const hora = row.Hora || "";
          return `
        <div class="mp-fecha">
          <div class="mp-fecha-main">${fecha}</div>
          ${hora ? `<div class="mp-fecha-sub">${hora}</div>` : ""}
        </div>
      `;
        },
      },

      // BANCO + OPERACIÓN (en el mismo bloque visual)
      {
        data: null,
        defaultContent: "",
        render: function (data, type, row) {
          const banco = row.Banco || "-";
          const op = row.Operacion || "";
          const tipo = (row.Tipo || "").toLowerCase(); // si tenés campo tipo (transferencia/deposito)
          const pillClass =
            tipo === "transferencia"
              ? "mp-pill mp-pill-warn"
              : tipo === "deposito"
                ? "mp-pill mp-pill-info"
                : "mp-pill mp-pill-muted";

          return `
        <div class="mp-bank">
          <div class="mp-bank-name">${banco}</div>
          ${op ? `<div class="mp-bank-op">${escapeHtml(op)}</div>` : ""}
          ${tipo ? `<div class="${pillClass}">${escapeHtml(tipo)}</div>` : ""}
        </div>
      `;
        },
      },

      // IMPORTE
      {
        data: "Importe",
        defaultContent: "",
        render: function (data) {
          const formatted = $.fn.dataTable.render
            .number(",", ".", 2, "$ ")
            .display(data || 0);
          return `<div class="mp-amount">${formatted}</div>`;
        },
      },
    ],

    columnDefs: [
      // id oculto (queda en el detalle del modal)
      { targets: 0, visible: false },

      // Operación: en móvil oculto para que no se “corte”
      // { targets: 3, visible: !isMobile },

      // Importe a la derecha y sin salto
      { targets: 3, className: "text-end text-nowrap fw-bold" },

      // Prioridades responsive (por si no estás en móvil pero igual se achica)
      { responsivePriority: 1, targets: 1 }, // Fecha
      { responsivePriority: 2, targets: 2 }, // Banco
      { responsivePriority: 3, targets: 3 }, // Importe
      // { responsivePriority: 4, targets: 3 }, // Operación
      // { responsivePriority: 5, targets: 0 }, // id
    ],
  });
});
function escapeHtml(str) {
  return String(str).replace(/[&<>"'`=\/]/g, function (s) {
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
