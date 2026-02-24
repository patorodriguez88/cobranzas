$(document).ready(function () {
  if (!$("#mis_pagos").length) {
    console.error("No existe #mis_pagos en el DOM");
    return;
  }

  const isMobile = window.matchMedia("(max-width: 576px)").matches;

  $("#mis_pagos").DataTable({
    paging: true,
    searching: false,

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
          var fecha = row.Fecha ? row.Fecha.split("-").reverse().join("/") : "";
          var hora = row.Hora || "";
          return `
            <div class="dt-fecha">
              <div class="dt-fecha-main">${fecha}</div>
              ${hora ? `<div class="dt-fecha-sub">${hora}</div>` : ""}
            </div>
          `;
        },
      },

      {
        data: "Banco",
        defaultContent: "",
        render: function (data) {
          return `<span class="badge bg-light text-dark border dt-banco">${data || ""}</span>`;
        },
      },

      { data: "Operacion", defaultContent: "" },

      {
        data: "Importe",
        defaultContent: "",
        render: function (data) {
          // mantiene tu formato $ con 2 decimales
          const formatted = $.fn.dataTable.render
            .number(",", ".", 2, "$ ")
            .display(data || 0);
          return `<span class="dt-importe">${formatted}</span>`;
        },
      },
    ],

    columnDefs: [
      // id oculto (queda en el detalle del modal)
      { targets: 0, visible: false },

      // Operación: en móvil oculto para que no se “corte”
      { targets: 3, visible: !isMobile },

      // Importe a la derecha y sin salto
      { targets: 4, className: "text-end text-nowrap fw-bold" },

      // Prioridades responsive (por si no estás en móvil pero igual se achica)
      { responsivePriority: 1, targets: 1 }, // Fecha
      { responsivePriority: 2, targets: 2 }, // Banco
      { responsivePriority: 3, targets: 4 }, // Importe
      { responsivePriority: 4, targets: 3 }, // Operación
      { responsivePriority: 5, targets: 0 }, // id
    ],
  });
});
