$(document).ready(function () {
  var datatable = $("#mis_pagos").DataTable({
    dom: "Bfrtip",
    buttons: ["copy", "excel", "pdf"],
    paging: true,
    searching: true,
    responsive: true,
    pageLength: 100,
    lengthMenu: [
      [10, 25, 50, -1],
      [10, 25, 50, "All"],
    ],
    ajax: {
      url: "procesos/php/mispagos.php",
      data: { Mis_pagos: 1 },
      type: "post",
    },
    columns: [
      { data: "id" },
      {
        data: null,
        render: function (data, type, row) {
          return (
            `<td>${row.Fecha}</br>` +
            `<small class="text-muted"> ${row.Hora}</small></td>`
          );
        },
      },

      { data: "Banco" },
      { data: "Operacion" },
      { data: "Importe" },
    ],
  });
});
