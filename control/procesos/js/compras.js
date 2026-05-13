let productosCompra = [];
let tablaOrdenesCompra;

const URL_COMPRAS = "control/procesos/php/compras.php";

$(document).ready(function () {
  cargarProductosCompra();
  cargarOrdenesCompra();

  $("#btn_agregar_producto_compra").click(function () {
    console.log("CLICK AGREGAR PRODUCTO COMPRA");

    console.log("productosCompra:", productosCompra);

    agregarFilaProductoCompra();
  });

  $("#btn_guardar_compra").click(function () {
    guardarCompra();
  });

  $("#btn_cancelar_compra").click(function () {
    limpiarCompra();
  });
});

function cargarProductosCompra() {
  $.ajax({
    url: URL_COMPRAS,
    type: "POST",
    data: { accion: "productos" },
    dataType: "json",
    success: function (resp) {
      console.log("PRODUCTOS COMPRA:", resp);

      productosCompra = Array.isArray(resp) ? resp : [];

      $("#tabla_detalle_compra tbody").empty();

      agregarFilaProductoCompra();
    },
    error: function (xhr) {
      console.log("ERROR productos compra:", xhr.responseText);
      Swal.fire("Error", "No se pudieron cargar los productos.", "error");
    },
  });
}

function agregarFilaProductoCompra() {
  let opciones = `<option value="">Seleccionar producto</option>`;

  productosCompra.forEach(function (p) {
    opciones += `
      <option 
        value="${p.id}"
        data-nombre="${p.Nombre}"
        data-stock="${p.Stock}">
        [${p.id}] ${p.Nombre} | Stock actual: ${p.Stock}
      </option>
    `;
  });

  let fila = `
    <tr>
      <td>
        <select class="form-select form-select-sm producto_compra">
          ${opciones}
        </select>
      </td>
      <td>
        <input type="number" class="form-control form-control-sm stock_actual_compra" value="0" readonly>
      </td>
      <td>
        <input type="number" class="form-control form-control-sm cantidad_compra" value="1" min="1">
      </td>
      <td>
        <input type="number" class="form-control form-control-sm stock_nuevo_compra" value="0" readonly>
      </td>
      <td class="text-center">
        <i class="mdi mdi-delete mdi-18px text-danger ms-2 eliminar_fila_compra" style="cursor:pointer"></i>
      </td>
    </tr>
  `;

  $("#tabla_detalle_compra tbody").append(fila);
}

$(document).on("change", ".producto_compra", function () {
  let fila = $(this).closest("tr");
  let stock = parseInt($(this).find(":selected").data("stock") || 0);

  fila.find(".stock_actual_compra").val(stock);
  calcularFilaCompra(fila);
});

$(document).on("keyup change", ".cantidad_compra", function () {
  let fila = $(this).closest("tr");
  calcularFilaCompra(fila);
});

$(document).on("click", ".eliminar_fila_compra", function () {
  $(this).closest("tr").remove();
});

function calcularFilaCompra(fila) {
  let stockActual = parseInt(fila.find(".stock_actual_compra").val() || 0);
  let cantidad = parseInt(fila.find(".cantidad_compra").val() || 0);

  fila.find(".stock_nuevo_compra").val(stockActual + cantidad);
}

function guardarCompra() {
  let detalle = [];

  $("#tabla_detalle_compra tbody tr").each(function () {
    let producto = $(this).find(".producto_compra");
    let idProducto = producto.val();

    if (idProducto) {
      detalle.push({
        idProducto: idProducto,
        Cantidad: $(this).find(".cantidad_compra").val(),
      });
    }
  });

  if (detalle.length === 0) {
    Swal.fire({
      icon: "warning",
      title: "Orden incompleta",
      text: "Agregá al menos un producto.",
    });
    return;
  }

  $.ajax({
    url: URL_COMPRAS,
    type: "POST",
    dataType: "json",
    data: {
      accion: "guardar",
      id: $("#compra_id").val(),
      Observaciones: $("#compra_observaciones").val(),
      detalle: JSON.stringify(detalle),
    },
    success: function (r) {
      if (r.success == 1) {
        Swal.fire({
          icon: "success",
          title: "Orden guardada",
          text: "Orden de ingreso #" + r.NumeroOrden + " generada correctamente.",
          timer: 1800,
          showConfirmButton: false,
        });

        limpiarCompra();
        cargarProductosCompra();

        if ($.fn.DataTable.isDataTable("#tabla_ordenes_compra")) {
          $("#tabla_ordenes_compra").DataTable().ajax.reload(null, false);
        }
      } else {
        Swal.fire("Error", r.error || "No se pudo guardar la orden.", "error");
      }
    },
    error: function (xhr) {
      console.log("ERROR guardar compra:", xhr.responseText);
      Swal.fire("Error", "Error en compras.php", "error");
    },
  });
}

function limpiarCompra() {
  $("#compra_observaciones").val("");
  $("#tabla_detalle_compra tbody").empty();
  agregarFilaProductoCompra();
  $("#compra_id").val(0);
}
function cargarOrdenesCompra() {
  tablaOrdenesCompra = $("#tabla_ordenes_compra").DataTable({
    destroy: true,
    pageLength: 25,
    order: [[0, "desc"]],
    ajax: {
      url: URL_COMPRAS,
      type: "POST",
      data: { accion: "listar" },
      dataSrc: "data",
      error: function (xhr) {
        console.log("ERROR listar compras:", xhr.responseText);
      },
    },
    columns: [
      {
        data: "NumeroOrden",
        render: function (data) {
          return `<span class="badge bg-primary">#${data}</span>`;
        },
      },
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
      { data: "Usuario" },
      {
        data: "Productos",
        render: function (data) {
          if (!data) return "";
          return data
            .split("||")
            .map((p) => `<div style="font-size:11px;">${p}</div>`)
            .join("");
        },
      },
      {
        data: "TotalItems",
        className: "text-end",
        render: function (data) {
          return parseInt(data || 0).toLocaleString("es-AR");
        },
      },
      { data: "Observaciones" },
      {
        data: null,
        orderable: false,
        render: function (row) {
          return `
            <i class="mdi mdi-pencil mdi-18px text-warning ms-2"
               style="cursor:pointer"
               onclick="editarCompra(${row.id})"></i>

            <i class="mdi mdi-delete mdi-18px text-danger ms-2"
               style="cursor:pointer"
               onclick="eliminarCompra(${row.id})"></i>
          `;
        },
      },
    ],
  });
}

function editarCompra(idOrden) {
  $.ajax({
    url: URL_COMPRAS,
    type: "POST",
    dataType: "json",
    data: {
      accion: "ver",
      idOrden: idOrden,
    },
    success: function (r) {
      if (r.success != 1) {
        Swal.fire("Error", r.error || "No se pudo cargar la orden.", "error");
        return;
      }

      $("#compra_id").val(r.orden.id);
      $("#compra_observaciones").val(r.orden.Observaciones || "");
      $("#tabla_detalle_compra tbody").empty();

      r.detalle.forEach(function (item) {
        agregarFilaProductoCompra();

        let fila = $("#tabla_detalle_compra tbody tr").last();

        fila.find(".producto_compra").val(item.idProducto);
        fila.find(".stock_actual_compra").val(item.StockActual);
        fila.find(".cantidad_compra").val(item.Cantidad);

        calcularFilaCompra(fila);
      });

      $("html, body").animate({ scrollTop: 0 }, 300);
    },
    error: function (xhr) {
      console.log(xhr.responseText);
      Swal.fire("Error", "Error cargando orden.", "error");
    },
  });
}

function eliminarCompra(idOrden) {
  Swal.fire({
    title: "¿Anular orden de ingreso?",
    text: "Esto revertirá el stock ingresado por esta orden.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, anular",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#fa5c7c",
  }).then(function (result) {
    if (!result.isConfirmed) return;

    $.ajax({
      url: URL_COMPRAS,
      type: "POST",
      dataType: "json",
      data: {
        accion: "eliminar",
        idOrden: idOrden,
      },
      success: function (r) {
        if (r.success == 1) {
          Swal.fire({
            icon: "success",
            title: "Orden anulada",
            timer: 1200,
            showConfirmButton: false,
          });

          limpiarCompra();
          cargarProductosCompra();

          if ($.fn.DataTable.isDataTable("#tabla_ordenes_compra")) {
            $("#tabla_ordenes_compra").DataTable().ajax.reload(null, false);
          }
        } else {
          Swal.fire("Error", r.error || "No se pudo anular la orden.", "error");
        }
      },
      error: function (xhr) {
        console.log(xhr.responseText);
        Swal.fire("Error", "Error anulando orden.", "error");
      },
    });
  });
}
