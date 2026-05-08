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
      url: "../php/productos.php",
      type: "POST",
      data: { accion: "listar" },
      dataSrc: "",
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
      { data: "Stock" },
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
        render: function (data) {
          return `
            <i class="mdi mdi-pencil mdi-18px text-warning ms-2" style="cursor:pointer" onclick="editarProducto(${data.id})"></i>
            <i class="mdi mdi-delete mdi-18px text-danger ms-2" style="cursor:pointer" onclick="eliminarProducto(${data.id})"></i>
          `;
        },
      },
    ],
  });
}

function guardarProducto() {
  let datos = {
    accion: "guardar",
    id: $("#producto_id").val(),
    Codigo: $("#producto_codigo").val(),
    Nombre: $("#producto_nombre").val(),
    Categoria: $("#producto_categoria").val(),
    PrecioCosto: $("#producto_costo").val(),
    PrecioVenta: $("#producto_venta").val(),
    Stock: $("#producto_stock").val(),
    Descripcion: $("#producto_descripcion").val(),
  };

  $.post("control/procesos/php/productos.php", datos, function (resp) {
    let r = JSON.parse(resp);

    if (r.success) {
      tablaProductos.ajax.reload();
      limpiarFormulario();
    } else {
      alert("Error al guardar");
    }
  });
}

function editarProducto(id) {
  $.post("control/procesos/php/productos.php", { accion: "obtener", id: id }, function (resp) {
    let r = JSON.parse(resp);

    $("#producto_id").val(r.id);
    $("#producto_codigo").val(r.Codigo);
    $("#producto_nombre").val(r.Nombre);
    $("#producto_categoria").val(r.Categoria);
    $("#producto_costo").val(r.PrecioCosto);
    $("#producto_venta").val(r.PrecioVenta);
    $("#producto_stock").val(r.Stock);
    $("#producto_descripcion").val(r.Descripcion);
  });
}

function eliminarProducto(id) {
  if (!confirm("¿Eliminar producto?")) return;

  $.post("control/procesos/php/productos.php", { accion: "eliminar", id: id }, function (resp) {
    let r = JSON.parse(resp);

    if (r.success) {
      tablaProductos.ajax.reload();
    }
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
