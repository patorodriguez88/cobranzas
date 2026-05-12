function alerta() {
  $.NotificationApp.send("Atención !", "El cliente debe estar activo.", "bottom-right", "#FFFFFF", "warning");
}
function editar_celular(id) {
  $("#id_celular").val(id);

  $.ajax({
    type: "POST",
    url: "control/procesos/php/clientes.php",
    data: { Celular_search: 1, id: id },
    success: function (response) {
      const jsonData = JSON.parse(response);
      $("#celular_text").val(jsonData.Dato || "");
      $("#modal_celular").modal("show");
    },
  });
}

$("#modal_celular_btn_ok").click(function () {
  const id = $("#id_celular").val();
  const celular = $("#celular_text").val();

  $.ajax({
    type: "POST",
    url: "control/procesos/php/clientes.php",
    data: { Celular: 1, id: id, Celular_text: celular },
    success: function () {
      const dt = $("#clientes_tabla").DataTable();
      dt.ajax.reload(null, false);

      $("#modal_celular").modal("hide");

      $.NotificationApp.send("Éxito!", "Modificamos el celular del cliente.", "bottom-right", "#FFFFFF", "success");
    },
  });
});
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

      datatable.ajax.reload(null, false);

      $("#modal_dni").modal("hide");

      $.NotificationApp.send("Exito !", "Modificamos el Dni del Cliente.", "bottom-right", "#FFFFFF", "success");
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

      datatable.ajax.reload(null, false);

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
          const txt = row.Observaciones || "";
          return `<small class="text-muted">
      <a style="cursor:pointer" onclick="observaciones(${row.id})">
        <i class="mdi mdi-comment-text-outline"></i> ${escapeHtml(txt)}
      </a>
    </small>`;
        },
      },
      {
        data: "Dni",
        render: function (data, type, row) {
          const dni = row.Dni || "";

          if (parseInt(row.Suspendido) === 1) {
            return `<a style="cursor:pointer" class="text-danger" onclick="alerta()">
        <del>${escapeHtml(dni)}</del>
      </a>`;
          }

          return `<a style="cursor:pointer" onclick="dni(${row.id})">
      <i class="mdi mdi-card-account-details-outline"></i> ${escapeHtml(dni)}
    </a>`;
        },
      },
      {
        data: "Celular",
        render: function (data, type, row) {
          const celular = row.Celular || "";

          if (parseInt(row.Suspendido) === 1) {
            return `<a style="cursor:pointer" class="text-danger" onclick="alerta()">
        <del>${escapeHtml(celular)}</del>
      </a>`;
          }

          return `<a style="cursor:pointer" onclick="editar_celular(${row.id})">
      <i class="mdi mdi-cellphone"></i> ${escapeHtml(celular)}
    </a>`;
        },
      },

      {
        data: "Recorrido",
        render: function (data, type, row) {
          const recorrido = row.Recorrido || "";

          if (parseInt(row.Suspendido) === 1) {
            return `<a style="cursor:pointer" class="text-danger" onclick="alerta()">
        <del>${escapeHtml(recorrido)}</del>
      </a>`;
          }

          return `<a style="cursor:pointer" onclick="editar_recorrido(${row.id})">
      <i class="mdi mdi-map-outline"></i> ${escapeHtml(recorrido)}
    </a>`;
        },
      },
      {
        data: null,
        render: function (data, type, row) {
          const susp = parseInt(row.Suspendido) === 1;

          if (susp) {
            return `<i onclick="modificar_status('${row.id}',0)"
                style="cursor:pointer"
                class="mdi mdi-18px mdi-close-circle text-danger"></i>`;
          }

          return `<i onclick="modificar_status('${row.id}',1)"
              style="cursor:pointer"
              class="mdi mdi-18px mdi-check-circle text-success"></i>`;
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
        $.NotificationApp.send("Exito !", "Modificamos el Status del Cliente.", "bottom-right", "#FFFFFF", "success");
        var datatable_clientes = $("#clientes_tabla").DataTable();
        datatable_clientes.ajax.reload(null, false);
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

      $.NotificationApp.send("Éxito!", "Modificamos el nombre del cliente.", "bottom-right", "#FFFFFF", "success");
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

      $.NotificationApp.send("Éxito!", "Modificamos la dirección del cliente.", "bottom-right", "#FFFFFF", "success");
    },
  });
});
function editar_recorrido(id) {
  $("#id_recorrido").val(id);

  $.ajax({
    type: "POST",
    url: "control/procesos/php/clientes.php",
    data: { Recorrido_search: 1, id: id },
    success: function (response) {
      const jsonData = JSON.parse(response);
      $("#recorrido_text").val(jsonData.Dato || "");
      $("#modal_recorrido").modal("show");
    },
  });
}

$("#modal_recorrido_btn_ok").click(function () {
  const id = $("#id_recorrido").val();
  const recorrido = $("#recorrido_text").val();

  $.ajax({
    type: "POST",
    url: "control/procesos/php/clientes.php",
    data: { Recorrido: 1, id: id, Recorrido_text: recorrido },
    success: function (response) {
      const dt = $("#clientes_tabla").DataTable();
      dt.ajax.reload(null, false);

      $("#modal_recorrido").modal("hide");

      $.NotificationApp.send("Éxito!", "Modificamos el recorrido del cliente.", "bottom-right", "#FFFFFF", "success");
    },
  });
});

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
$("#btn_nuevo_cliente").click(function () {
  $("#cliente_id").val(0);
  $("#cliente_ncliente").val("");
  $("#cliente_razon_social").val("");
  $("#cliente_cuit").val("");
  $("#cliente_direccion").val("");
  $("#cliente_ciudad").val("Córdoba");
  $("#cliente_distribuidora").val("Dinter");
  $("#cliente_mail").val("");
  $("#cliente_observaciones").val("");
  $("#cliente_dni").val("");
  $("#cliente_celular").val("");
  $("#modal_cliente").modal("show");
});

$("#btn_guardar_cliente").click(function () {
  let datos = {
    NuevoCliente: 1,
    id: $("#cliente_id").val(),
    Ncliente: $("#cliente_ncliente").val(),
    RazonSocial: $("#cliente_razon_social").val(),
    Cuit: $("#cliente_cuit").val(),
    Direccion: $("#cliente_direccion").val(),
    Ciudad: $("#cliente_ciudad").val(),
    Distribuidora: $("#cliente_distribuidora").val(),
    Mail: $("#cliente_mail").val(),
    Observaciones: $("#cliente_observaciones").val(),
    Dni: $("#cliente_dni").val(),
    Celular: $("#cliente_celular").val(),
  };
  if ($("#cliente_ncliente").val().trim() === "") {
    Swal.fire({
      icon: "warning",

      title: "Número de cliente requerido",

      text: "Debe ingresar el número de cliente.",
    });

    $("#cliente_ncliente").focus();

    return;
  }
  $.ajax({
    url: "control/procesos/php/clientes.php",
    type: "POST",
    data: datos,
    dataType: "json",
    success: function (r) {
      if (r.success == 1) {
        $("#modal_cliente").modal("hide");
        $("#clientes_tabla").DataTable().ajax.reload(null, false);

        Swal.fire({
          icon: "success",
          title: "Cliente guardado",
          text: "El cliente se cargó correctamente.",
          timer: 1500,
          showConfirmButton: false,
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "No se pudo guardar",
          text: r.error || "No se pudo guardar el cliente.",
        });
      }
    },
    error: function (xhr) {
      console.log(xhr.responseText);

      Swal.fire({
        icon: "error",
        title: "Error",
        text: "Error en clientes.php",
      });
    },
    error: function (xhr, status, error) {
      console.log("STATUS:", status);

      console.log("ERROR:", error);

      console.log("RESPONSE:", xhr.responseText);

      Swal.fire({
        icon: "error",

        title: "Error en clientes.php",

        html: `

      <div class="text-start">

        <p><b>Status:</b> ${xhr.status}</p>

        <p><b>Error:</b> ${error || "-"}</p>

        <hr>

        <pre style="white-space:pre-wrap; font-size:12px;">${escapeHtml(xhr.responseText || "Sin respuesta del servidor")}</pre>

      </div>

    `,

        width: 800,

        confirmButtonText: "Cerrar",
      });
    },
  });
});
