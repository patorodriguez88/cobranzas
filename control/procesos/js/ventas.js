let tablaVentas;
let productosVenta = [];

const URL_VENTAS = "control/procesos/php/ventas.php";

$(document).ready(function () {
  cargarProductosVenta();
  cargarVentas();

  $("#btn_agregar_producto_venta").click(function () {
    agregarFilaProductoVenta();
  });

  $("#btn_guardar_venta").click(function () {
    guardarVenta();
  });

  $("#btn_cancelar_venta").click(function () {
    limpiarVenta();
  });
});

function cargarProductosVenta() {
  $.ajax({
    url: URL_VENTAS,
    type: "POST",
    data: { accion: "productos" },
    dataType: "json",
    success: function (resp) {
      productosVenta = resp;
      agregarFilaProductoVenta();
    },
    error: function (xhr) {
      console.log("ERROR productos:", xhr.responseText);
    },
  });
}

function cargarVentas() {
  tablaVentas = $("#tabla_ventas").DataTable({
    destroy: true,
    ajax: {
      url: URL_VENTAS,
      type: "POST",
      data: { accion: "listar" },
      dataSrc: "",
      error: function (xhr) {
        console.log("ERROR listar ventas:", xhr.responseText);
      },
    },
    columns: [
      {
        data: "Fecha",
        render: function (data) {
          if (!data) return "";
          return `<span style="display:none;">${data}</span>${data.split(" ")[0].split("-").reverse().join("/")}`;
        },
      },
      { data: "Cliente" },
      { data: "CantidadProductos" },
      {
        data: "Total",
        render: $.fn.dataTable.render.number(",", ".", 2, "$ "),
      },
      { data: "Observaciones" },
      {
        data: null,
        render: function (data) {
          return `
            <i class="mdi mdi-eye mdi-18px text-info ms-2" style="cursor:pointer" onclick="verVenta(${data.id})"></i>
            <i class="mdi mdi-delete mdi-18px text-danger ms-2" style="cursor:pointer" onclick="eliminarVenta(${data.id})"></i>
          `;
        },
      },
    ],
  });
}

function agregarFilaProductoVenta() {
  let opciones = `<option value="">Seleccionar producto</option>`;

  productosVenta.forEach(function (p) {
    opciones += `
      <option 
        value="${p.id}" 
        data-nombre="${p.Nombre}" 
        data-precio="${p.PrecioVenta}">
        ${p.Nombre} - $ ${parseFloat(p.PrecioVenta || 0).toFixed(2)}
      </option>
    `;
  });

  let fila = `
    <tr>
      <td>
        <select class="form-select form-select-sm producto_venta">
          ${opciones}
        </select>
      </td>
      <td>
        <input type="number" class="form-control form-control-sm cantidad_venta" value="1" min="1">
      </td>
      <td>
        <input type="number" class="form-control form-control-sm precio_venta" value="0" step="0.01">
      </td>
      <td>
        <input type="text" class="form-control form-control-sm subtotal_venta" value="0.00" readonly>
      </td>
      <td class="text-center">
        <i class="mdi mdi-delete mdi-18px text-danger ms-2 eliminar_fila_venta" style="cursor:pointer"></i>
      </td>
    </tr>
  `;

  $("#tabla_detalle_venta tbody").append(fila);
}

$(document).on("change", ".producto_venta", function () {
  let precio = $(this).find(":selected").data("precio") || 0;
  let fila = $(this).closest("tr");

  fila.find(".precio_venta").val(precio);
  calcularFilaVenta(fila);
});

$(document).on("keyup change", ".cantidad_venta, .precio_venta", function () {
  let fila = $(this).closest("tr");
  calcularFilaVenta(fila);
});

$(document).on("click", ".eliminar_fila_venta", function () {
  $(this).closest("tr").remove();
  calcularTotalVenta();
});

function calcularFilaVenta(fila) {
  let cantidad = parseFloat(fila.find(".cantidad_venta").val()) || 0;
  let precio = parseFloat(fila.find(".precio_venta").val()) || 0;
  let subtotal = cantidad * precio;

  fila.find(".subtotal_venta").val(subtotal.toFixed(2));

  calcularTotalVenta();
}

function calcularTotalVenta() {
  let total = 0;

  $(".subtotal_venta").each(function () {
    total += parseFloat($(this).val()) || 0;
  });

  $("#venta_total").text(
    "$ " +
      total.toLocaleString("es-AR", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }),
  );
}

function guardarVenta() {
  let detalle = [];

  $("#tabla_detalle_venta tbody tr").each(function () {
    let producto = $(this).find(".producto_venta");
    let idProducto = producto.val();

    if (idProducto) {
      detalle.push({
        idProducto: idProducto,
        ProductoNombre: producto.find(":selected").data("nombre"),
        Cantidad: $(this).find(".cantidad_venta").val(),
        PrecioUnitario: $(this).find(".precio_venta").val(),
        Subtotal: $(this).find(".subtotal_venta").val(),
      });
    }
  });

  if (detalle.length === 0) {
    alert("Agregá al menos un producto.");
    return;
  }

  let datos = {
    accion: "guardar",
    id: $("#venta_id").val() || 0,
    Cliente: $("#venta_cliente").val(),
    Observaciones: $("#venta_observaciones").val(),
    detalle: JSON.stringify(detalle),
  };

  $.ajax({
    url: URL_VENTAS,
    type: "POST",
    data: datos,
    dataType: "json",
    success: function (r) {
      console.log("Respuesta venta:", r);

      if (r.success == 1) {
        limpiarVenta();
        tablaVentas.ajax.reload(null, false);
      } else {
        alert(r.error || "Error al guardar venta");
      }
    },
    error: function (xhr) {
      console.log("ERROR guardar venta:", xhr.responseText);
      alert("Error en ventas.php");
    },
  });
}

function limpiarVenta() {
  $("#venta_id").val(0);
  $("#venta_cliente").val("");
  $("#venta_observaciones").val("");
  $("#tabla_detalle_venta tbody").empty();
  $("#venta_total").text("$ 0,00");

  agregarFilaProductoVenta();
}

function eliminarVenta(id) {
  if (!confirm("¿Eliminar venta?")) return;

  $.ajax({
    url: URL_VENTAS,
    type: "POST",
    data: { accion: "eliminar", id: id },
    dataType: "json",
    success: function (r) {
      if (r.success == 1) {
        tablaVentas.ajax.reload(null, false);
      }
    },
    error: function (xhr) {
      console.log("ERROR eliminar venta:", xhr.responseText);
    },
  });
}

function verVenta(id) {
  $.ajax({
    url: URL_VENTAS,
    type: "POST",
    data: { accion: "ver", id: id },
    dataType: "json",
    success: function (r) {
      console.log(r);
      alert("Venta #" + id + "\nTotal: $ " + r.venta.Total);
    },
    error: function (xhr) {
      console.log("ERROR ver venta:", xhr.responseText);
    },
  });
}
