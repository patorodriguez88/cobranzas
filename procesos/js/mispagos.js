$(document).ready(function () {
  // 1) Asegurarse que exista la tabla antes de inicializar
  if (!$("#mis_pagos").length) {
    console.error("No existe #mis_pagos en el DOM");
    return;
  }

  // 2) Debug: ver qué devuelve el backend
  // (lo podés dejar un rato)
  // $.post('procesos/php/mispagos.php', {Mis_pagos:1}, function(r){ console.log(r); });

  $("#mis_pagos").DataTable({
    paging: true,
    searching: false,
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
      dataSrc: function (json) {
        // DataTables espera {data:[...]}
        // Si tu backend devuelve directo un array, lo adaptamos:
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
          return `${fecha}<br><small class="text-muted">${hora}</small>`;
        },
      },
      { data: "Banco", defaultContent: "" },
      { data: "Operacion", defaultContent: "" },
      {
        data: "Importe",
        defaultContent: "",
        render: $.fn.dataTable.render.number(",", ".", 2, "$ "),
      },
    ],
  });
});
