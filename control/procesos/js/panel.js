function vuelve(id) {
  $.ajax({
    data: { Vuelve: 1, id_cobranza: id },
    url: "control/procesos/php/panel.php",
    type: "post",
    success: function (response) {
      var jsonData = JSON.parse(response);

      if (jsonData.success == 1) {
        $.NotificationApp.send(
          "Exito !",
          "Registro disponible para conciliacion en Pendientes.",
          "bottom-right",
          "#FFFFFF",
          "success",
        );

        var datatable_seguimiento = $("#cobranzas_tabla").DataTable();
        datatable_seguimiento.ajax.reload();
      }
    },
  });
}
function eliminar(id) {
  // $.ajax({
  //     data:{'Eliminar':1,'id_cobranza':id},
  //     url:'control/procesos/php/panel.php',
  //     type:'post',
  //     success: function(response)
  //      {
  //         var jsonData = JSON.parse(response);
  //         if(jsonData.success==1){
  //             $.NotificationApp.send("Exito !", 'Registro Eliminado.', "bottom-right", "#FFFFFF", "success");
  //             var datatable_seguimiento= $('#cobranzas_tabla').DataTable();
  //             datatable_seguimiento.ajax.reload();
  //         }
  //      }
  // });
}

function calcular_total(a) {
  $("#selectAll").prop("checked", false);
  let total = $('input[type="checkbox"]:checked').length;
  var oTable = $("#cobranzas_tabla").dataTable();
  var allPages = oTable.fnGetNodes();
  //Creamos un array que almacenará los valores de los input "checked"
  var checked = [];
  //Recorremos todos los input checkbox que se encuentren "checked"
  $("input.form-check-input:checked", allPages).each(function () {
    //Mediante la función push agregamos al arreglo los values de los checkbox
    if ($(this).attr("value") != null) {
      checked.push($(this).attr("value"));
    }
  });
  console.log("boton general", checked);

  if (checked != 0) {
    $.ajax({
      data: { Exportar_ver: 1, id_cobranza: checked },
      url: "control/procesos/php/exportar.php",
      type: "post",
      success: function (response) {
        var jsonData = JSON.parse(response);

        if (jsonData.success == 1) {
          $(".modal-footer").css("display", "flex");

          $("#total_importe_exportar").html("$ " + jsonData.total);
          $("#total_exportar").html(total - a);
        }
      },
    });
  } else {
    $("#total_importe_exportar").html("");
    $(".modal-footer").css("display", "none");
  }
}

function calcular_total_exportaciones(a) {
  let total = $('input[type="checkbox"]:checked').length;
  var oTable = $("#cobranzas_tabla").dataTable();
  var allPages = oTable.fnGetNodes();
  //Creamos un array que almacenará los valores de los input "checked"
  var checked = [];
  //Recorremos todos los input checkbox que se encuentren "checked"
  $("input.form-check-input:checked", allPages).each(function () {
    //Mediante la función push agregamos al arreglo los values de los checkbox
    if ($(this).attr("value") != null) {
      checked.push($(this).attr("value"));
    }
  });
  console.log("boton general", checked);

  if (checked != 0) {
    $.ajax({
      data: { Exportar_ver: 1, id_cobranza: checked },
      url: "control/procesos/php/exportar.php",
      type: "post",
      success: function (response) {
        var jsonData = JSON.parse(response);

        if (jsonData.success == 1) {
          $(".modal-footer").css("display", "flex");

          $("#total_importe_exportar").html("$ " + jsonData.total);
          $("#total_exportar").html(total - a);
        }
      },
    });
  } else {
    $("#total_importe_exportar").html("");
    $(".modal-footer").css("display", "none");
  }
}

// $('#select_one').change(function () {

//             let total=$('input[type="checkbox"]:checked').length;
//             var oTable = $('#cobranzas_tabla').dataTable();
//             var allPages = oTable.fnGetNodes();
//                         //Creamos un array que almacenará los valores de los input "checked"
//                         var checked = [];
//                         //Recorremos todos los input checkbox que se encuentren "checked"
//                         $("input.form-check-input:checked",allPages).each(function() {
//                         //Mediante la función push agregamos al arreglo los values de los checkbox
//                         if ($(this).attr("value") != null) {
//                             checked.push(($(this).attr("value")));
//                             }
//                         });
//                         console.log(checked);
//                         if (checked != 0) {
//                         $.ajax({
//                             data:{'Exportar_ver':1,'id_cobranza':checked},
//                             url:'control/procesos/php/exportar.php',
//                             type:'post',
//                             success: function(response)
//                              {
//                                 var jsonData = JSON.parse(response);
//                                 if(jsonData.success==1){
//                                     $('#total_importe_exportar').html(jsonData.total);
//                                     $('#total_exportar').html(total);
//                                 }
//                              }
//                         });
//                     }
//     });

$("#btn_exportar").click(function (e) {
  var oTable = $("#cobranzas_tabla").dataTable();
  var allPages = oTable.fnGetNodes();

  var checked = [];
  $("input.form-check-input:checked", allPages).each(function () {
    var v = $(this).attr("value");
    if (v != null) checked.push(v);
  });

  if (checked.length > 0) {
    $.ajax({
      data: { Exportar: 1, id_cobranza: checked },
      url: "control/procesos/php/exportar.php",
      type: "post",

      success: function (response) {
        // DEBUG recomendado (por si tu PHP imprime algo extra)
        // console.log("RAW:", response);

        var jsonData;
        try {
          jsonData = JSON.parse(response);
        } catch (err) {
          console.error("JSON inválido:", err);
          console.log("Respuesta cruda:", response);
          $.NotificationApp.send(
            "Error !",
            "El servidor devolvió una respuesta inválida (no JSON).",
            "bottom-right",
            "#FFFFFF",
            "danger",
          );
          return;
        }

        if (jsonData.success == 1) {
          $(".modal-footer").css("display", "none");

          var datatable_seguimiento = $("#cobranzas_tabla").DataTable();
          datatable_seguimiento.ajax.reload();

          $("#selectAll").prop("checked", false);

          $.NotificationApp.send(
            "Exito !",
            "Generaste el archivo " + jsonData.name + ".csv podés descargarlo desde la pestaña Exportados.",
            "bottom-right",
            "#FFFFFF",
            "success",
          );
        } else {
          $.NotificationApp.send(
            "Error !",
            "No se pudo generar el archivo. Intente nuevamente.",
            "bottom-right",
            "#FFFFFF",
            "danger",
          );
        }
      },

      error: function (xhr) {
        console.error("AJAX error:", xhr.status, xhr.responseText);
        $.NotificationApp.send(
          "Error !",
          "Falló la comunicación con el servidor.",
          "bottom-right",
          "#FFFFFF",
          "danger",
        );
      },
    });
  } else {
    alert("Seleccione al menos una opcion");
  }
});
$(document).ready(function () {
  ver_tabla_conciliados(1);
});

$("#filtro_pendientes").click(function () {
  var datatable_seguimiento = $("#cobranzas_tabla").DataTable();
  datatable_seguimiento.destroy();

  ver_tabla_conciliados(1);
  // $('#filtro_pendientes').addClass('success');
  $(this).removeClass("btn btn-light mb-2 me-1");
  $(this).addClass("btn btn-success mb-2 me-1 ");
  $("#filtro_todos").removeClass("btn btn-success mb-2 me-1");
  $("#filtro_todos").addClass("btn btn-light mb-2 me-1");
});

$("#filtro_todos").click(function () {
  var datatable_seguimiento = $("#cobranzas_tabla").DataTable();
  datatable_seguimiento.destroy();

  ver_tabla_conciliados(0);
  $(this).removeClass("btn btn-light mb-2 me-1");
  $(this).addClass("btn btn-success mb-2 me-1");
  $("#filtro_pendientes").removeClass("btn btn-success mb-2 me-1");
  $("#filtro_pendientes").addClass("btn btn-light mb-2 me-1");
});

function ver_tabla_conciliados(a) {
  var datatable_seguimiento = $("#cobranzas_tabla").DataTable({
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
      url: "control/procesos/php/panel.php",
      data: { Tabla_conciliados: 1, Filtro: a },
      type: "post",
    },
    columns: [
      { data: "id_cobranza" },
      {
        data: null,

        render: function (data, type, row) {
          return `<td>${row.Fecha}</br>` + `<small class="text-muted"> ${row.Hora}</small></td>`;
        },
      },

      {
        data: null,

        render: function (data, type, row) {
          return (
            `<td>${row.NombreCliente}</br>` + `<small class="text-muted"> N Cliente: ${row.NumeroCliente}</small></td>`
          );
        },
      },
      {
        data: null,
        render: function (data, type, row) {
          return `<td>${row.Banco}</br>` + `<small class="text-muted"> Op.: ${row.Operacion}</small></td>`;
        },
      },
      {
        data: null,

        render: function (data, type, row) {
          var display = $.fn.dataTable.render.number(".", ",", 2, "$ ").display;
          var importe = display(row.Importe);
          var importe_original = display(row.Importe_original);

          if (row.Importe != row.Importe_original) {
            return (
              `<td>${importe}</br>` +
              `<small class="text-muted h6">Imp.Orig.:<p class="text-danger"><del>${importe_original}</del></p></small></td>`
            );
          } else {
            return `<td>${importe}</td>`;
          }
        },
      },
      {
        data: null,
        render: function (data, type, row) {
          var Xmas95 = new Date(row.TimeStamp);
          var day = Xmas95.getDate();
          var month = Xmas95.getMonth() + 1;
          var year = Xmas95.getUTCFullYear();
          let hour = moment(row.TimeStamp).add(3, "hour").format("HH:mm");
          var newdate = day + "." + month + "." + year + " " + hour;

          if (row.Estado == "Rechazado") {
            return (
              "<td>" +
              '<h6><span class="badge badge-danger-lighten"><i class="mdi mdi-cancel"></i> Rechazado </span></h6>' +
              "</td>"
            );
          } else {
            return (
              "<td>" +
              '<h6><span class="badge badge-success-lighten mb-0"><i class="mdi mdi-bitcoin"></i> Aceptado </span></h6>' +
              `<small class="text-muted">${row.User} ${newdate}</small></td>`
            );
          }
        },
      },
      {
        data: null,
        width: "20%",
        render: function (data, type, row) {
          if (row.Exportado != "") {
            return (
              `<td ><span class="badge badge-warning-lighten">Exportado</span></br>` +
              `<small id="${row.Exportado}" class="text-muted h8">${row.Exportado}.txt</small></i></br>` +
              `<small class="text-muted">${row.Observaciones}</small></td>`
            );
          }
          return `<td style="max-width:50px"><small style="max-width:50px" class="text-muted">${row.Observaciones}</small></td>`;
        },
      },
      {
        data: null,
        render: function (data, type, row) {
          if (row.Exportado == "" && row.Estado != "Rechazado") {
            return (
              '<td class="dtr-control dt-checkboxes-cell"><div class="form-check"><input value="' +
              row.id_cobranza +
              '" type="checkbox" class="form-check-input dt-checkboxes" onclick="calcular_total(0)"><label class="form-check-label">&nbsp;</label></div></td>'
            );
          } else if (row.Exportado == "" && row.Estado == "Rechazado") {
            return (
              `<td style="cursor:point"><i onclick="vuelve('${row.id_cobranza}')" class="mdi mdi-18px mdi-reload text-success"></i>` +
              `<i onclick="eliminar('${row.id_cobranza}')" class="mdi mdi-18px mdi-trash-can-outline text-danger ml-3"></i></td>`
            );
          } else {
            return `<td></td>`;
          }
        },
      },
    ],
  });

  $("#selectAll").click(function (e) {
    if ($(this).hasClass("checkedAll")) {
      $("input").prop("checked", false);
      $(this).removeClass("checkedAll");

      calcular_total_exportaciones(0);

      //   $('#total_exportar').html('');
    } else {
      $("input").prop("checked", true);
      $(this).addClass("checkedAll");

      let total = $('input[type="checkbox"]:checked').length;

      calcular_total_exportaciones(1);
      $("#total_exportar").html(total);
    }
  });
}
