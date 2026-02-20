function ver_duplicados(i) {
  var datatable = $("#duplicados-datatable").DataTable();
  datatable.destroy();

  $("#centermodal").modal("hide");
  $("#id_original").html(i);
  $("#modal_duplicados").modal("show");

  var datatable = $("#duplicados-datatable").DataTable({
    paging: false,
    searching: false,

    ajax: {
      url: "control/procesos/php/panel.php",
      data: { Duplicados_tabla: 1, id_cobranza: i },
      type: "post",
    },
    columns: [
      {
        data: null,
        render: function (data, type, row) {
          return (
            `<td>${row.Fecha}</br>` +
            `<small class="text-muted" style="font-size:10px"> ${row.Hora}</small></td>`
          );
        },
      },

      {
        data: null,

        render: function (data, type, row) {
          return (
            `<td>${row.NombreCliente}</br>` +
            `<small class="text-muted" style="font-size:10px"> N Cliente: ${row.NumeroCliente}</small></td>`
          );
        },
      },

      {
        data: null,
        render: function (data, type, row) {
          return (
            `<td>${row.Banco}</br>` +
            `<small class="text-muted" style="font-size:10px"> Op.: ${row.Operacion}</small></td>`
          );
        },
      },
      {
        data: "Importe",
        render: $.fn.dataTable.render.number(".", ",", 2, "$ "),
      },
      {
        data: "Observaciones",
        render: function (data, type, row) {
          return (
            `<td>${row.Observaciones}</td></br>` +
            `<small class="text-muted" style="font-size:10px"><a style='cursor:pointer' onclick='observaciones(${row.id})'><i class='mdi mdi-comment-text-outline'> </i> ${row.Usuario_obs}</a></small>`
          );
        },
      },
    ],
  });
}

function disableSending(select) {
  // Buscar todos los checkbox con nombre select y que estén marcados
  if ($('input[name="select"]:checked').length > 0) {
    // Al menos hay un checkbox marcado, habilitar botón
    $(".ar").prop("disabled", false);
  } else {
    // No hay checkbox marcado, deshabilitar botón
    $(".ar").prop("disabled", true);
  }
}

function observaciones(id) {
  $("#id_obs").val(id);

  $.ajax({
    type: "POST",
    url: "control/procesos/php/panel.php",
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
    url: "control/procesos/php/panel.php",
    data: { Observaciones: 1, id: id, Observaciones_text: obs },
    success: function (response) {
      var datatable_seguimiento = $("#cobranzas_tabla").DataTable();

      datatable_seguimiento.ajax.reload();

      $("#modal_obs").modal("hide");
    },
  });
});

function conciliar(id) {
  $.ajax({
    type: "POST",
    url: "control/procesos/php/panel.php",
    data: { Datos: 1, id: id },
    success: function (response) {
      var jsonData = JSON.parse(response);

      console.log("datos", jsonData.data[0].NombreCliente);

      $("#nombre_cobranza").val(jsonData.data[0].NombreCliente);
      $("#fecha_cobranza").val(jsonData.data[0].Fecha);
      $("#fecha_original_cobranza").val(jsonData.data[0].Fecha);
      $("#hora_cobranza").val(jsonData.data[0].Hora);
      $("#hora_original_cobranza").val(jsonData.data[0].Hora);
      $("#banco_cobranza").val(jsonData.data[0].Banco);
      $("#banco_original_cobranza").val(jsonData.data[0].Banco);
      $("#obs_cobranza").val(jsonData.data[0].Usuario_obs);
      $("#operacion_cobranza").val(jsonData.data[0].Operacion);
      $("#operacion_original_cobranza").val(jsonData.data[0].Operacion);
      $("#importe_cobranza").val(jsonData.data[0].Importe);
      $("#importe_original_cobranza").val(jsonData.data[0].Importe);
      $("#numero_cobranza").val(jsonData.data[0].NumeroCliente);
      $("#observaciones_cliente").html(
        "Obs. Cliente: " + jsonData.data[0].Observaciones,
      );
      $("#centermodal_title").html("Conciliar el Movimiento N " + id);
      $("#id_cobranza").val(id);

      $("#centermodal").modal("show");
    },
  });
}

$("#btn_conciliar").click(function () {
  let id = $("#id_cobranza").val();
  let Nombre = $("#nombre_cobranza").val();
  let Fecha = $("#fecha_cobranza").val();
  let Hora = $("#hora_cobranza").val();
  let Banco = $("#banco_cobranza").val();
  let Observaciones = $("#obs_cobranza").val();
  let Importe = $("#importe_cobranza").val();
  let Operacion = $("#operacion_cobranza").val();
  let Numero = $("#numero_cobranza").val();

  $.ajax({
    type: "POST",
    url: "control/procesos/php/panel.php",
    data: {
      Conciliar: 1,
      id_cobranza: id,
      Numero: Numero,
      Nombre: Nombre,
      Fecha: Fecha,
      Hora: Hora,
      Banco: Banco,
      Operacion: Operacion,
      Observaciones: Observaciones,
      Importe: Importe,
    },
    success: function (response) {
      var jsonData = JSON.parse(response);

      if (jsonData.success == 1) {
        var datatable_seguimiento = $("#cobranzas_tabla").DataTable();

        datatable_seguimiento.ajax.reload();

        $("#centermodal").modal("hide");
      }
    },
  });
});

//RECHAZAR
$("#btn_rechazar").click(function () {
  let id = $("#id_cobranza").val();
  let Nombre = $("#nombre_cobranza").val();
  let Fecha = $("#fecha_cobranza").val();
  let Hora = $("#hora_cobranza").val();
  let Banco = $("#banco_cobranza").val();
  let Observaciones = $("#obs_cobranza").val();
  let Importe = $("#importe_cobranza").val();
  let Operacion = $("#operacion_cobranza").val();
  let Numero = $("#numero_cobranza").val();

  $.ajax({
    type: "POST",
    url: "control/procesos/php/panel.php",
    data: {
      Rechazar: 1,
      id_cobranza: id,
      Numero: Numero,
      Nombre: Nombre,
      Fecha: Fecha,
      Hora: Hora,
      Banco: Banco,
      Operacion: Operacion,
      Observaciones: Observaciones,
      Importe: Importe,
    },
    success: function (response) {
      var jsonData = JSON.parse(response);

      if (jsonData.success == 1) {
        $.NotificationApp.send(
          "Rechazado !",
          `<p style='cursor:pointer'>Registro Rechazado. <a onclick='cancelar(${id})'>cancelar</a></p>`,
          "bottom-right",
          "#FFFFFF",
          "warning",
        );

        var datatable_seguimiento = $("#cobranzas_tabla").DataTable();

        datatable_seguimiento.ajax.reload();

        $("#centermodal").modal("hide");
      }
    },
  });
});

$(document).ready(function () {
  var datatable_seguimiento = $("#cobranzas_tabla").DataTable({
    dom: "Bfrtip",
    buttons: ["copy", "excel", "pdf"],
    paging: true,
    searching: true,
    select: true,
    pageLength: 100,
    lengthMenu: [[10, 25, 50, "All"]],
    ajax: {
      url: "control/procesos/php/panel.php",
      data: { Tabla_no_conciliados: 1 },
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

      {
        data: null,

        render: function (data, type, row) {
          return (
            `<td>${row.NombreCliente}</br>` +
            `<small class="text-muted"> N Cliente: ${row.NumeroCliente}</small></td>`
          );
        },
      },
      {
        data: null,
        render: function (data, type, row) {
          return `<td>${row.TipoOperacion}</td>`;
        },
      },

      {
        data: null,
        render: function (data, type, row) {
          return (
            `<td>${row.Banco}</br>` +
            `<small class="text-muted"> Op.: ${row.Operacion}</small></td>`
          );
        },
      },
      {
        data: "Importe",
        render: $.fn.dataTable.render.number(".", ",", 2, "$ "),
      },
      {
        data: "Observaciones",
        render: function (data, type, row) {
          return (
            `<td>${row.Observaciones}</td></br>` +
            `<small class="text-muted"><a style='cursor:pointer' onclick='observaciones(${row.id})'><i class='mdi mdi-comment-text-outline'> </i> ${row.Usuario_obs}</a></small>`
          );
        },
      },
      {
        data: null,
        render: function (data, type, row) {
          if (row.Conciliado == 0) {
            if (row.AlertaDuplicidad == 0) {
              return (
                `<td><a style='cursor:pointer' class='action-icon' onclick='conciliar(${row.id})'><i class='mdi mdi-text-box-check-outline text-warning'></i></a>` +
                `<a style='cursor:pointer' class='action-icon' onclick='conciliar_quik(${row.id})'><i class='mdi mdi-check-bold text-success'></i></a></td>`
              );
            } else {
              return (
                `<td><a style='cursor:pointer' class='action-icon' onclick='conciliar(${row.id})'><i class='mdi mdi-text-box-check-outline text-warning'></i></a>` +
                `<a style='cursor:pointer' class='action-icon' onclick='ver_duplicados(${row.id})'><i class='mdi mdi-alert text-danger'></i></a></td>`
              );
            }
          } else {
            return "<td></td>";
          }
        },
      },
    ],
  });
});
function conciliar_quik(i) {
  $.ajax({
    type: "POST",
    url: "control/procesos/php/panel.php",
    data: { Conciliar_quik: 1, id_cobranza: i },
    success: function (response) {
      var jsonData = JSON.parse(response);

      if (jsonData.success == 1) {
        var datatable_seguimiento = $("#cobranzas_tabla").DataTable();

        datatable_seguimiento.ajax.reload();

        $.NotificationApp.send(
          "Exito !",
          `Registro Conciliado. <a onclick='cancelar(${i})' style='cursor:pointer'>cancelar</a> `,
          "bottom-right",
          "#FFFFFF",
          "success",
        );
      }
    },
  });
}

function cancelar(i) {
  $.ajax({
    type: "POST",
    url: "control/procesos/php/panel.php",
    data: { Conciliar_quik_cancel: 1, id_cobranza: i },
    success: function (response) {
      var jsonData = JSON.parse(response);

      if (jsonData.success == 1) {
        var datatable_seguimiento = $("#cobranzas_tabla").DataTable();

        datatable_seguimiento.ajax.reload();

        $.NotificationApp.send(
          "Cancelado ",
          `Cancelamos la conciliación del movimiento.`,
          "bottom-right",
          "#FFFFFF",
          "warning",
        );
      }
    },
  });
}
//MODAL CENTER OPEN
$("#centermodal").on("show.bs.modal", function () {
  let Fecha = $("#fecha_cobranza").val();
  let Banco = $("#banco_cobranza").val();
  let Importe = $("#importe_cobranza").val();
  let Operacion = $("#operacion_cobranza").val();
  let id_cobranza = $("#id_cobranza").val();

  $.ajax({
    type: "POST",
    url: "control/procesos/php/panel.php",
    data: {
      Duplicados: 1,
      fecha: Fecha,
      noperacion: Operacion,
      banco: Banco,
      importe: Importe,
      id_cobranza: id_cobranza,
    },
    success: function (response) {
      var jsonData = JSON.parse(response);

      if (jsonData.success == 1) {
        // let id=[];
        // id=jsonData.data[0].id;
        // console.log(jsonData);

        $("#alerta").html(
          `<div class="alert alert-danger" role="alert"><strong>Alerta Duplicidad ! </strong> Se encontraron ${jsonData.data.length} registros de igual Fecha, Número de Operación y Banco. <a onclick="ver_duplicados(${id_cobranza})" style='cursor:pointer'><b> Abrir </b></a></div>`,
        );
      }
    },
  });

  // MODAL DUPLICADOS OPEN
  $("#modal_duplicados").on("show.bs.modal", function () {
    //   var i=$('#id_original').html();
    //   var datatable= $('#duplicados-datatable').DataTable({
    //     paging: false,
    //     searching: false,
    //     ajax: {
    //     url:"control/procesos/php/panel.php",
    //     data:{'Duplicados_tabla':1,'id_cobranza':i},
    //     type:'post'
    //     },
    //     columns: [
    //     {data:null,
    //         render: function (data, type, row) {
    //           return `<td>${row.Fecha}</br>`+
    //           `<small class="text-muted" style="font-size:10px"> ${row.Hora}</small></td>`;
    //       }
    // },
    // {data:null,
    //     render: function (data, type, row) {
    //           return `<td>${row.NombreCliente}</br>`+
    //           `<small class="text-muted" style="font-size:10px"> N Cliente: ${row.NumeroCliente}</small></td>`;
    //       }
    //   },
    //     {data:null,
    //         render: function (data, type, row) {
    //           return `<td>${row.Banco}</br>`+
    //           `<small class="text-muted" style="font-size:10px"> Op.: ${row.Operacion}</small></td>`;
    //         }
    //       },
    //     {data:"Importe",
    //     render: $.fn.dataTable.render.number('.', ',', 2, '$ ')
    //     },
    //     {data:"Observaciones",
    //     render: function (data, type, row) {
    //     return `<td>${row.Observaciones}</td></br>`+
    //             `<small class="text-muted" style="font-size:10px"><a style='cursor:pointer' onclick='observaciones(${row.id})'><i class='mdi mdi-comment-text-outline'> </i> ${row.Usuario_obs}</a></small>`;
    //     }
    //     }
    //     ]
    //   });
  });

  $("#img_deposito").attr("src", "#");

  let id = $("#id_cobranza").val();

  let img = `images/depositos/${id}.jpg`;

  let noimg = "images/NoImageAvailable.png";

  var request = new XMLHttpRequest();
  request.open("GET", img, true);
  request.send();
  request.onload = function () {
    let status = request.status;
    if (status == 200) {
      $("#img_deposito").attr("src", img);
    } else {
      $("#img_deposito").attr("src", noimg);
    }
  };
});
