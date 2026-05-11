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
          if (row.Estado == "Rechazado") {
            return `
        <i onclick="vuelve('${row.id_cobranza}')" 
           class="mdi mdi-18px mdi-reload text-success ms-2" 
           style="cursor:pointer"></i>

        <i onclick="eliminar('${row.id_cobranza}')" 
           class="mdi mdi-18px mdi-trash-can-outline text-danger ms-2" 
           style="cursor:pointer"></i>
      `;
          }

          let checkboxExportar = "";

          if (row.Exportado == "") {
            checkboxExportar = `
        <div class="form-check d-inline-block me-2">
          <input value="${row.id_cobranza}" 
                 type="checkbox" 
                 class="form-check-input dt-checkboxes" 
                 onclick="calcular_total(0)">
          <label class="form-check-label">&nbsp;</label>
        </div>
      `;
          }

          let btnAsignar = `
      <i class="mdi mdi-link-variant mdi-18px text-success ms-2" 
         title="Asignar pago a ventas"
         style="cursor:pointer" 
         onclick="abrirAsignarPago(${row.id_cobranza})"></i>
    `;

          return checkboxExportar + btnAsignar;
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

function abrirAsignarPago(idCobranza) {
  $.ajax({
    type: "POST",
    url: "control/procesos/php/panel.php",
    dataType: "json",
    data: {
      Datos: 1,
      id: idCobranza,
    },
    success: function (r) {
      if (!r.data || !r.data[0]) {
        alert("No se encontró la cobranza.");
        return;
      }

      let c = r.data[0];

      $("#asignar_id_cobranza").val(c.id);
      $("#asignar_cliente").val(c.NombreCliente);
      $("#asignar_numero_cliente").val(c.NumeroCliente);
      $("#asignar_importe_pago").val(c.Importe);
      $("#asignar_banco_operacion").val(c.Banco + " / Op.: " + c.Operacion);

      $("#resumen_pago").text(formatearMonedaAsignacion(c.Importe));
      $("#resumen_aplicado").text(formatearMonedaAsignacion(0));
      $("#resumen_diferencia").text(formatearMonedaAsignacion(c.Importe));

      cargarVentasPendientesAsignacion(c.NumeroCliente, c.Importe);

      $("#modal_asignar_pago").modal("show");
    },
    error: function (xhr) {
      console.log(xhr.responseText);
      alert("Error al abrir la asignación.");
    },
  });
}

function cargarVentasPendientesAsignacion(numeroCliente, importePago) {
  $("#tabla_asignar_ventas tbody").html(`
    <tr>
      <td colspan="6" class="text-center text-muted">Buscando ventas pendientes...</td>
    </tr>
  `);

  $.ajax({
    type: "POST",
    url: "control/procesos/php/panel.php",
    dataType: "json",
    data: {
      VentasPendientesCliente: 1,
      NumeroCliente: numeroCliente,
    },
    success: function (r) {
      let html = "";

      if (!r.success || !r.data || r.data.length === 0) {
        html = `
          <tr>
            <td colspan="6" class="text-center text-muted">
              No hay ventas pendientes para este cliente.
            </td>
          </tr>
        `;
      } else {
        let disponible = parseFloat(importePago || 0);

        r.data.forEach(function (v) {
          let saldo = parseFloat(v.Saldo || 0);
          let sugerido = 0;

          if (disponible > 0) {
            sugerido = Math.min(disponible, saldo);
            disponible -= sugerido;
          }

          html += `
            <tr>
              <td><span class="badge bg-primary">#${v.NumeroVenta}</span></td>
              <td>${formatearFechaAsignacion(v.Fecha)}</td>
              <td>${formatearMonedaAsignacion(v.Total)}</td>
              <td>${formatearMonedaAsignacion(v.TotalPagado)}</td>
              <td>${formatearMonedaAsignacion(v.Saldo)}</td>
              <td>
                <input 
                  type="number"
                  class="form-control form-control-sm importe_aplicar_pago"
                  data-idventa="${v.id}"
                  data-saldo="${v.Saldo}"
                  value="${sugerido.toFixed(2)}"
                  min="0"
                  max="${v.Saldo}"
                  step="0.01">
              </td>
            </tr>
          `;
        });
      }

      $("#tabla_asignar_ventas tbody").html(html);
      recalcularResumenAsignacion();
    },
    error: function (xhr) {
      console.log(xhr.responseText);
    },
  });
}

$(document).on("keyup change", ".importe_aplicar_pago", function () {
  let valor = parseFloat($(this).val() || 0);
  let saldo = parseFloat($(this).data("saldo") || 0);

  if (valor > saldo) {
    $(this).val(saldo.toFixed(2));
  }

  recalcularResumenAsignacion();
});

function recalcularResumenAsignacion() {
  let pago = parseFloat($("#asignar_importe_pago").val() || 0);
  let aplicado = 0;

  $(".importe_aplicar_pago").each(function () {
    aplicado += parseFloat($(this).val() || 0);
  });

  let diferencia = pago - aplicado;

  $("#resumen_pago").text(formatearMonedaAsignacion(pago));
  $("#resumen_aplicado").text(formatearMonedaAsignacion(aplicado));
  $("#resumen_diferencia").text(formatearMonedaAsignacion(diferencia));
}

function obtenerAplicacionesAsignacion() {
  let aplicaciones = [];

  $(".importe_aplicar_pago").each(function () {
    let importe = parseFloat($(this).val() || 0);

    if (importe > 0) {
      aplicaciones.push({
        idVenta: $(this).data("idventa"),
        ImporteAplicado: importe,
      });
    }
  });

  return aplicaciones;
}

$("#btn_confirmar_asignacion_pago").click(function () {
  let aplicaciones = obtenerAplicacionesAsignacion();

  if (aplicaciones.length === 0) {
    alert("No hay importes aplicados.");
    return;
  }

  $.ajax({
    type: "POST",
    url: "control/procesos/php/panel.php",
    dataType: "json",
    data: {
      AsignarPagoVenta: 1,
      idCobranza: $("#asignar_id_cobranza").val(),
      AplicacionesVentas: JSON.stringify(aplicaciones),
    },
    success: function (r) {
      if (r.success == 1) {
        $("#modal_asignar_pago").modal("hide");

        let tabla = $("#cobranzas_tabla").DataTable();
        tabla.ajax.reload(null, false);

        alert("Pago asignado correctamente.");
      } else {
        alert(r.error || "No se pudo asignar el pago.");
      }
    },
    error: function (xhr) {
      console.log(xhr.responseText);
      alert("Error asignando pago.");
    },
  });
});

function formatearMonedaAsignacion(valor) {
  return (
    "$ " +
    parseFloat(valor || 0).toLocaleString("es-AR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
}

function formatearFechaAsignacion(fecha) {
  if (!fecha) return "";
  let partes = fecha.split(" ");
  let f = partes[0].split("-").reverse().join("/");
  let h = partes[1] ? partes[1].substring(0, 5) : "";
  return `${f}<br><small class="text-muted">${h} hs</small>`;
}
