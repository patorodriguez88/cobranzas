function alerta() {
  $.NotificationApp.send(
    "Atención !",
    "El cliente debe estar activo.",
    "bottom-right",
    "#FFFFFF",
    "warning",
  );
}
function dni(id) {
  $("#id_dni").val(id);

  $.ajax({
    type: "POST",
    url: "control/procesos/php/clientes.php",
    data: { Dni_search: 1, id: id },
    success: function (response) {
      var jsonData = JSON.parse(response);

      $("#dni_text").val(jsonData.Dato);

      $("#modal_dni").modal("show");
    },
  });
}

$("#modal_dni_btn_ok").click(function () {
  let id = $("#id_dni").val();
  let obs = $("#dni_text").val();

  $.ajax({
    type: "POST",
    url: "control/procesos/php/clientes.php",
    data: { Dni: 1, id: id, Dni_text: obs },
    success: function (response) {
      var datatable = $("#clientes_tabla").DataTable();

      datatable.ajax.reload();

      $("#modal_dni").modal("hide");

      $.NotificationApp.send(
        "Exito !",
        "Modificamos el Dni del Cliente.",
        "bottom-right",
        "#FFFFFF",
        "success",
      );
    },
  });
});

function observaciones(id) {
  $("#id_obs").val(id);

  $.ajax({
    type: "POST",
    url: "control/procesos/php/clientes.php",
    data: { Observaciones_search: 1, id: id },
    success: function (response) {
      var jsonData = JSON.parse(response);

      $("#observaciones_text").val(jsonData.Dato);

      $("#modal_obs").modal("show");
    },
  });
}
$("#modal_btn_ok").click(function () {
  let id = $("#id_obs").val();
  let obs = $("#observaciones_text").val();

  $.ajax({
    type: "POST",
    url: "control/procesos/php/clientes.php",
    data: { Observaciones: 1, id: id, Observaciones_text: obs },
    success: function (response) {
      var datatable = $("#clientes_tabla").DataTable();

      datatable.ajax.reload();

      $("#modal_obs").modal("hide");

      $.NotificationApp.send(
        "Exito !",
        "Modificamos las Observaciones del Cliente.",
        "bottom-right",
        "#FFFFFF",
        "success",
      );
    },
  });
});

$(document).ready(function () {
  var datatable_clientes = $("#clientes_tabla").DataTable({
    dom: "Bfrtip",
    // buttons: ['copy', 'excel', 'pdf'],
    paging: true,
    searching: true,
    responsive: true,
    pageLength: 100,

    lengthMenu: [
      [10, 25, 50, -1],
      [10, 25, 50, "All"],
    ],
    ajax: {
      url: "control/procesos/php/clientes.php",
      data: { Tabla_clientes: 1 },
      type: "post",
    },
    columns: [
      { data: "Ncliente" },
      {
        data: null,
        render: function (data, type, row) {
          const razon = row.RazonSocial || "";
          const dir = row.Direccion || "";
          const ciudad = row.Ciudad || "";

          if (parseInt(row.Suspendido) === 1) {
            return `
        <div>
          <a class="text-danger" style="cursor:pointer" onclick="alerta()">
            <del>${escapeHtml(razon)}</del>
          </a><br>
          <small class="text-muted">
            <a class="text-danger" style="cursor:pointer" onclick="alerta()">
              <del>${escapeHtml(dir)}</del>
            </a>
          </small><br>
          <small class="text-muted">${escapeHtml(ciudad)}</small>
        </div>
      `;
          }

          return `
      <div>
        <a style="cursor:pointer" onclick="editar_nombre(${row.id})">
          <i class="mdi mdi-pencil-outline"></i> ${escapeHtml(razon)}
        </a><br>

        <small class="text-muted">
          <a style="cursor:pointer" onclick="editar_direccion(${row.id})">
            <i class="mdi mdi-map-marker-outline"></i> ${escapeHtml(dir)}
          </a>
        </small><br>

        <small class="text-muted">${escapeHtml(ciudad)}</small>
      </div>
    `;
        },
      },
      {
        data: "Observaciones",
        render: function (data, type, row) {
          return `<td><small class="text-muted"><a style='cursor:pointer' onclick='observaciones(${row.id})'><i class='mdi mdi-comment-text-outline'> </i> ${row.Observaciones}</a></small></td>`;
        },
      },
      {
        data: "Dni",
        render: function (data, type, row) {
          if (row.Suspendido == 1) {
            return `<td><a style='cursor:pointer' class='text-danger' onclick='alerta()'><del> ${row.Dni}</del></a></td>`;
          } else {
            return `<td><a style='cursor:pointer' onclick='dni(${row.id})'><i class='mdi mdi-comment-text-outline'> </i> ${row.Dni}</a></td>`;
          }
        },
      },
      { data: "Recorrido" },
      {
        data: null,
        render: function (data, type, row) {
          if (row.Suspendido == 1) {
            return `<td><i onclick="modificar_status('${row.id}',0)" style="cursor:point" class="mdi mdi-18px mdi-close-circle text-danger"></i></td>`;
          } else {
            return `<td><i onclick="modificar_status('${row.id}',1)" style="cursor:point" class="mdi mdi-18px mdi-check-circle text-success"></i></td>`;
          }
        },
      },
      {
        data: null,
        render: function (data, type, row) {
          return `<td><i onclick="modificar_cliente('${row.id}',0)" style="cursor:point" class="mdi mdi-18px mdi-face-man-shimmer text-primary"></i></td>`;
        },
      },
    ],
  });
});
function modificar_status(id, status) {
  $.ajax({
    data: { Status: 1, id_cliente: id, status: status },
    url: "control/procesos/php/clientes.php",
    type: "post",
    success: function (response) {
      var jsonData = JSON.parse(response);

      if (jsonData.success == 1) {
        $.NotificationApp.send(
          "Exito !",
          "Modificamos el Status del Cliente.",
          "bottom-right",
          "#FFFFFF",
          "success",
        );
        var datatable_clientes = $("#clientes_tabla").DataTable();
        datatable_clientes.ajax.reload();
      } else {
        $.NotificationApp.send(
          "Error !",
          "No pudimos modificar el Status del Cliente, intente nuevamente.",
          "bottom-right",
          "#FFFFFF",
          "danger",
        );
      }
    },
  });
}
function editar_nombre(id) {
  $("#id_nombre").val(id);

  $.ajax({
    type: "POST",
    url: "control/procesos/php/clientes.php",
    data: { Nombre_search: 1, id: id },
    success: function (response) {
      const jsonData = JSON.parse(response);
      $("#nombre_text").val(jsonData.Dato || "");
      $("#modal_nombre").modal("show");
    },
  });
}

$("#modal_nombre_btn_ok").click(function () {
  const id = $("#id_nombre").val();
  const nombre = $("#nombre_text").val();

  $.ajax({
    type: "POST",
    url: "control/procesos/php/clientes.php",
    data: { Nombre: 1, id: id, Nombre_text: nombre },
    success: function (response) {
      const dt = $("#clientes_tabla").DataTable();
      dt.ajax.reload(null, false); // no reset page
      $("#modal_nombre").modal("hide");

      $.NotificationApp.send(
        "Éxito!",
        "Modificamos el nombre del cliente.",
        "bottom-right",
        "#FFFFFF",
        "success",
      );
    },
  });
});

function editar_direccion(id) {
  $("#id_direccion").val(id);

  $.ajax({
    type: "POST",
    url: "control/procesos/php/clientes.php",
    data: { Direccion_search: 1, id: id },
    success: function (response) {
      const jsonData = JSON.parse(response);
      $("#direccion_text").val(jsonData.Dato || "");
      $("#modal_direccion").modal("show");
    },
  });
}

$("#modal_direccion_btn_ok").click(function () {
  const id = $("#id_direccion").val();
  const dir = $("#direccion_text").val();

  $.ajax({
    type: "POST",
    url: "control/procesos/php/clientes.php",
    data: { Direccion: 1, id: id, Direccion_text: dir },
    success: function (response) {
      const dt = $("#clientes_tabla").DataTable();
      dt.ajax.reload(null, false);
      $("#modal_direccion").modal("hide");

      $.NotificationApp.send(
        "Éxito!",
        "Modificamos la dirección del cliente.",
        "bottom-right",
        "#FFFFFF",
        "success",
      );
    },
  });
});
