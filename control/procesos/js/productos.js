let tablaProductos;

$(document).ready(function () {
  cargarProductos();

  $("#btn_guardar_producto").click(function () {
    guardarProducto();
  });

  $("#btn_cancelar_producto").click(function () {
    limpiarFormulario();
  });
});

function cargarProductos() {
  tablaProductos = $("#tabla_productos").DataTable({
    destroy: true,
    ajax: {
      url: "control/procesos/php/productos.php",
      type: "POST",
      data: { accion: "listar" },
      error: function (xhr) {
        console.log("ERROR listar:", xhr.responseText);
      },
      dataSrc: "data",
    },
    columns: [
      { data: "Codigo" },
      { data: "Nombre" },
      { data: "Categoria" },
      {
        data: "PrecioCosto",
        render: $.fn.dataTable.render.number(",", ".", 2, "$ "),
      },
      {
        data: "PrecioVenta",
        render: $.fn.dataTable.render.number(",", ".", 2, "$ "),
      },
      {
        data: "StockReal",
        render: function (data, type, row) {
          let stock = parseFloat(row.StockReal || 0);
          let minimo = parseFloat(row.StockMinimo || 0);
          let clase = stock <= minimo ? "danger" : "success";

          return `
            <span class="badge bg-${clase}">
              ${stock.toLocaleString("es-AR")}
            </span>
          `;
        },
      },
      {
        data: "Activo",
        render: function (data) {
          return data == 1
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-danger">Inactivo</span>';
        },
      },
      {
        data: null,
        orderable: false,
        render: function (data, type, row) {
          return `
            <i class="mdi mdi-pencil mdi-18px text-warning ms-2"
               style="cursor:pointer"
               title="Editar producto"
               onclick="editarProducto(${row.id})"></i>

            <i class="mdi mdi-delete mdi-18px text-danger ms-2"
               style="cursor:pointer"
               title="Eliminar producto"
               onclick="eliminarProducto(${row.id})"></i>
          `;
        },
      },
    ],
  });
}

function guardarProducto() {

  let datos = {
    accion: "guardar",
    id: $("#producto_id").val() || 0,
    Codigo: $("#producto_codigo").val(),
    Nombre: $("#producto_nombre").val(),
    Categoria: $("#producto_categoria").val(),
    PrecioCosto: $("#producto_costo").val(),
    PrecioVenta: $("#producto_venta").val(),
    Stock: $("#producto_stock").val(),
    Descripcion: $("#producto_descripcion").val(),
  };

  $.ajax({
    url: "control/procesos/php/productos.php",
    type: "POST",
    data: datos,
    dataType: "json",
    success: function (r) {
      console.log("Respuesta guardar:", r);

      if (r.success == 1) {
        limpiarFormulario();
        tablaProductos.ajax.reload(null, false);
      } else {
        alert("Error al guardar");
      }
    },
    error: function (xhr) {
      console.log("ERROR PHP:", xhr.responseText);
      alert("Error en productos.php");
    },
  });
}

function editarProducto(id) {
  $.ajax({
    url: "control/procesos/php/productos.php",
    type: "POST",
    data: { accion: "obtener", id: id },
    dataType: "json",
    success: function (r) {

      console.log(r);

      $("#producto_id").val(r.id);
      $("#producto_codigo").val(r.Codigo);
      $("#producto_nombre").val(r.Nombre);
      $("#producto_categoria").val(r.Categoria);
      $("#producto_costo").val(r.PrecioCosto);
      $("#producto_venta").val(r.PrecioVenta);
      $("#producto_stock").val(r.Stock || 0);
      $("#producto_descripcion").val(r.Descripcion);

      $("#producto_venta_rapida").prop(
        "checked",
        parseInt(r.MostrarEnVentaRapida || 0) === 1
      );

      $("#producto_activo").prop(
        "checked",
        parseInt(r.Activo || 0) === 1
      );

      $("#modal_producto_titulo").text("Editar Producto");

      $("#modal_producto").modal("show");
    },
    error: function (xhr) {
      console.log("ERROR obtener:", xhr.responseText);
    },
  });
}

function eliminarProducto(id) {
  if (!confirm("¿Eliminar producto?")) return;

  $.ajax({
    url: "control/procesos/php/productos.php",
    type: "POST",
    data: { accion: "eliminar", id: id },
    dataType: "json",
    success: function (r) {
      if (r.success == 1) {
        tablaProductos.ajax.reload(null, false);
      }
    },
    error: function (xhr) {
      console.log("ERROR eliminar:", xhr.responseText);
    },
  });
}

function limpiarFormulario() {
  $("#producto_id").val(0);
  $("#producto_codigo").val("");
  $("#producto_nombre").val("");
  $("#producto_categoria").val("");
  $("#producto_costo").val("");
  $("#producto_venta").val("");
  $("#producto_stock").val(0);
  $("#producto_descripcion").val("");
}
$("#btn_nuevo_producto").click(function () {
  $("#modal_producto_titulo").text("Agregar Producto");

  $("#producto_id").val(0);
  $("#producto_codigo").val("");
  $("#producto_nombre").val("");
  $("#producto_categoria").val("");
  $("#producto_costo").val("");
  $("#producto_venta").val("");
  $("#producto_stock").val("");
  $("#producto_descripcion").val("");
  $("#producto_venta_rapida").prop("checked", false);

  $("#modal_producto").modal("show");
});
