let tablaVentas;
let productosVenta = [];

const URL_VENTAS = "control/procesos/php/ventas.php";

$(document).ready(function () {
  cargarProductosVenta();
  cargarVentas();
  cargarProductosVentaRapida();
  mostrarPantallaVentas();

  $("#btn_agregar_producto_venta").click(function () {
    agregarFilaProductoVenta();
  });

  $("#btn_guardar_venta").click(function () {
    guardarVenta();
  });

  $("#btn_cancelar_venta").click(function () {
    limpiarVenta();
  });
  $("#venta_cliente").select2({
    placeholder: "Buscar cliente...",
    width: "100%",
    minimumInputLength: 2,
    ajax: {
      url: "control/procesos/php/ventas.php",
      type: "POST",
      dataType: "json",
      delay: 300,
      data: function (params) {
        return {
          accion: "buscar_clientes",
          term: params.term,
        };
      },
      processResults: function (data) {
        return {
          results: data,
        };
      },
    },
  });

  $("#venta_cliente").on("select2:select", function (e) {
    let cliente = e.params.data.cliente;

    $("#cliente_cuit").val(cliente.Cuit || "");
    $("#cliente_direccion").val(cliente.Direccion || "");
    $("#cliente_ciudad").val(cliente.Ciudad || "");
    $("#cliente_telefono").val(cliente.Telefono || "");
  });
});

function cargarProductosVenta() {
  $.ajax({
    url: URL_VENTAS,
    type: "POST",
    data: { accion: "productos" },
    dataType: "json",
    success: function (resp) {
      console.log("PRODUCTOS RECIBIDOS:", resp);

      productosVenta = Array.isArray(resp) ? resp : [];

      $("#tabla_detalle_venta tbody").empty();
      agregarFilaProductoVenta();
    },
    error: function (xhr) {
      console.log("ERROR productos:", xhr.responseText);
      alert("Error cargando productos");
    },
  });
}

function cargarVentas() {
  tablaVentas = $("#tabla_ventas").DataTable({
    destroy: true,
    order: [[0, "desc"]],
    createdRow: function (row) {
      $(row).css("font-size", "11px");
    },
    ajax: {
      url: URL_VENTAS,
      type: "POST",
      data: { accion: "ultimas_ventas" },
      dataSrc: "data",
      error: function (xhr) {
        console.log("ERROR ultimas ventas:", xhr.responseText);
      },
    },
    pageLength: 10,
    searching: false,
    lengthChange: false,
    columns: [
      {
        data: "NumeroVenta",
        render: function (data, type, row) {
          return `
      <div><span class="badge bg-primary">#${data}</span></div>
      <small class="text-muted">${row.Usuario || ""}</small>
    `;
        },
      },
      {
        data: "Fecha",
        render: function (data) {
          if (!data) return "";

          let partes = data.split(" ");
          let fecha = partes[0].split("-").reverse().join("/");
          let hora = partes[1] ? partes[1].substring(0, 5) : "";

          return `
      <span style="display:none;">${data}</span>
      <div>${fecha}</div>
      <small class="text-muted">${hora} hs</small>
    `;
        },
      },

      { data: "Cliente" },
      {
        data: "Productos",
        render: function (data) {
          if (!data) return "";

          let productos = data.split("||");
          let html = "";

          productos.forEach(function (p) {
            html += `<div style="font-size:11px; line-height:14px;">${p}</div>`;
          });

          return html;
        },
      },
      {
        data: "Total",
        render: $.fn.dataTable.render.number(",", ".", 2, "$ "),
      },
      { data: "Observaciones" },
      {
        data: "EstadoPago",

        render: function (data) {
          let clase = "warning";

          if (data === "PAGADA") clase = "success";

          if (data === "PARCIAL") clase = "info";

          return `<span class="badge bg-${clase}">${data}</span>`;
        },
      },

      {
        data: "Saldo",

        render: $.fn.dataTable.render.number(",", ".", 2, "$ "),
      },
      {
        data: null,
        orderable: false,
        render: function (data) {
          return `
            <i class="mdi mdi-eye mdi-18px text-info ms-2" style="cursor:pointer" onclick="abrirEstadoVenta(${data.id})"></i>
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
    let stock = parseInt(p.Stock || 0);
    let disabled = stock <= 0 ? "disabled" : "";

    opciones += `
    <option 
      value="${p.id}" 
      data-nombre="${p.Nombre}" 
      data-precio="${p.PrecioVenta}"
      data-stock="${p.Stock}"
      ${disabled}>
      [${p.id}] ${p.Nombre} | Stock: ${p.Stock} | $ ${parseFloat(p.PrecioVenta || 0).toFixed(2)}
    </option>
  `;
  });

  let fila = `
    <tr>
      <td>
    <select class="form-select form-select-sm producto_venta">
      ${opciones}
    </select>
    <small class="stock_disponible text-muted d-block mt-1"></small>
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
  let stock = parseInt($(this).find(":selected").data("stock") || 0);
  let fila = $(this).closest("tr");

  fila.find(".precio_venta").val(precio);
  fila.find(".cantidad_venta").attr("max", stock);

  if (stock > 0) {
    fila
      .find(".stock_disponible")
      .removeClass("text-danger text-warning")
      .addClass("text-muted")
      .text("Stock disponible: " + stock);
  } else {
    fila
      .find(".stock_disponible")
      .removeClass("text-muted text-warning")
      .addClass("text-danger")
      .text("Sin stock disponible");
  }

  calcularFilaVenta(fila);
});

$(document).on("keyup change", ".cantidad_venta, .precio_venta", function () {
  let fila = $(this).closest("tr");

  let cantidad = parseInt(fila.find(".cantidad_venta").val() || 0);
  let stock = parseInt(fila.find(".producto_venta option:selected").data("stock") || 0);

  if (stock > 0 && cantidad > stock) {
    fila.find(".cantidad_venta").val(stock);

    fila
      .find(".stock_disponible")
      .removeClass("text-muted text-warning")
      .addClass("text-danger")
      .text("No podés vender más de " + stock + " unidades.");
  }

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
    Swal.fire({
      icon: "warning",
      title: "Venta incompleta",
      text: "Agregá al menos un producto.",
    });
    return;
  }

  let datos = {
    accion: "guardar",
    id: $("#venta_id").val() || 0,
    idCliente: $("#venta_cliente").val(),
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
        Swal.fire({
          icon: "success",

          title: "Venta guardada",

          text: "La venta se cargó correctamente.",

          timer: 1500,

          showConfirmButton: false,
        });

        limpiarVenta();

        cargarProductosVenta();

        cargarProductosVentaRapida();

        tablaVentas.ajax.reload(null, false);

        if ($.fn.DataTable.isDataTable("#tabla_listado_ventas")) {
          $("#tabla_listado_ventas").DataTable().ajax.reload(null, false);
        }
      } else {
        Swal.fire({
          icon: "error",
          title: "No se pudo guardar",
          text: r.error || "Error al guardar venta",
        });
      }
    },
    error: function (xhr) {
      console.log("ERROR guardar venta:", xhr.responseText);
      Swal.fire({
        icon: "error",
        title: "Error",
        text: "Error en ventas.php",
      });
    },
  });
}

function limpiarVenta() {
  $("#venta_id").val(0);
  $("#venta_cliente").val(null).trigger("change");
  $("#venta_observaciones").val("");
  $("#tabla_detalle_venta tbody").empty();
  $("#venta_total").text("$ 0,00");

  agregarFilaProductoVenta();
}

function eliminarVenta(id) {
  Swal.fire({
    title: "¿Eliminar venta?",
    text: "Esta acción marcará la venta como eliminada.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#fa5c7c",
    cancelButtonColor: "#6c757d",
  }).then((result) => {
    if (!result.isConfirmed) return;

    $.ajax({
      url: URL_VENTAS,
      type: "POST",
      data: { accion: "eliminar", id: id },
      dataType: "json",
      success: function (r) {
        if (r.success == 1) {
          Swal.fire({
            icon: "success",
            title: "Venta eliminada",
            timer: 1200,
            showConfirmButton: false,
          });

          tablaVentas.ajax.reload(null, false);
        }
      },
      error: function (xhr) {
        console.log("ERROR eliminar venta:", xhr.responseText);

        Swal.fire({
          icon: "error",
          title: "Error",
          text: "No se pudo eliminar la venta.",
        });
      },
    });
  });
}
function cargarProductosVentaRapida() {
  $.ajax({
    url: URL_VENTAS,
    type: "POST",
    data: { accion: "productos_venta_rapida" },
    dataType: "json",
    success: function (productos) {
      let html = "";

      productos.forEach(function (p) {
        html += `
          <div class="col-md-2 col-sm-4 mb-2">
            <div class="card border shadow-sm producto-rapido" 
                 style="cursor:pointer"
                 data-id="${p.id}">
              <div class="card-body p-2 text-center">
                <div class="fw-bold small">${p.Nombre}</div>
                <div class="text-muted small">Stock: ${p.Stock}</div>
                <div class="badge bg-success">$ ${parseFloat(p.PrecioVenta || 0).toFixed(2)}</div>
              </div>
            </div>
          </div>
        `;
      });

      $("#productos_venta_rapida").html(html);
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

      Swal.fire({
        icon: "info",
        title: "Venta #" + r.venta.NumeroVenta,
        html: `
    <div class="text-start">
      <p><b>Cliente:</b> ${r.venta.Cliente || "-"}</p>
      <p><b>Total:</b> $ ${parseFloat(r.venta.Total || 0).toLocaleString("es-AR", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}</p>
      <p><b>Estado:</b> ${r.venta.EstadoPago || "PENDIENTE"}</p>
      <p><b>Saldo:</b> $ ${parseFloat(r.venta.Saldo || 0).toLocaleString("es-AR", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}</p>
      <p><b>Observaciones:</b><br>${r.venta.Observaciones || "-"}</p>
    </div>
  `,
        width: 600,
        confirmButtonText: "Cerrar",
      });
    },
    error: function (xhr) {
      console.log("ERROR ver venta:", xhr.responseText);
    },
  });
}
function cargarListadoVentas() {
  $("#tabla_listado_ventas").DataTable({
    destroy: true,
    createdRow: function (row) {
      $(row).css("font-size", "11px");
    },
    ajax: {
      url: URL_VENTAS,
      type: "POST",
      data: { accion: "listar_ventas" },
      dataSrc: "data",
    },
    pageLength: 25,
    order: [[0, "desc"]],
    columns: [
      {
        data: "NumeroVenta",
        render: function (data, type, row) {
          return `<div>
                  <span class="badge bg-primary">
                    #${data}
                  </span>
                </div>
              <small class="text-muted">
              ${row.Usuario || ""}
              </small>`;
        },
      },
      {
        data: "Fecha",
        render: function (data) {
          if (!data) return "";
          return `<span style="display:none;">${data}</span>${data.split(" ")[0].split("-").reverse().join("/")}`;
        },
      },
      { data: "Cliente" },
      {
        data: "Productos",
        render: function (data) {
          if (!data) return "";

          let productos = data.split("||");

          let html = "";

          productos.forEach(function (p) {
            html += `
        <div class="mb-1">
          <span class="badge bg-secondary">
            ${p}
          </span>
        </div>
      `;
          });

          return html;
        },
      },
      {
        data: "Total",
        render: $.fn.dataTable.render.number(",", ".", 2, "$ "),
      },
      {
        data: "TotalPagado",

        render: $.fn.dataTable.render.number(",", ".", 2, "$ "),
      },

      {
        data: "Saldo",

        render: $.fn.dataTable.render.number(",", ".", 2, "$ "),
      },

      {
        data: "EstadoPago",

        render: function (data) {
          let clase = "warning";

          if (data === "PAGADA") clase = "success";

          if (data === "PARCIAL") clase = "info";

          return `<span class="badge bg-${clase}">${data || "PENDIENTE"}</span>`;
        },
      },

      { data: "Observaciones" },
      {
        data: null,
        orderable: false,
        render: function (data) {
          return `
            <i class="mdi mdi-eye mdi-18px text-info ms-2" style="cursor:pointer" onclick="abrirEstadoVenta(${data.id})"></i>
            <i class="mdi mdi-delete mdi-18px text-danger ms-2" style="cursor:pointer" onclick="eliminarVenta(${data.id})"></i>
          `;
        },
      },
    ],
  });
}
function mostrarPantallaVentas() {
  let hash = window.location.hash || "#ventas";

  if (hash === "#listado_ventas") {
    $("#card_nueva_venta").hide();
    $("#card_ultimas_ventas").hide();
    $("#card_listado_ventas").show();

    cargarListadoVentas();
  } else {
    $("#card_nueva_venta").show();
    $("#card_ultimas_ventas").show();
    $("#card_listado_ventas").hide();
  }
}

$(window).on("hashchange", function () {
  mostrarPantallaVentas();
});
function abrirEstadoVenta(idVenta) {
  $.ajax({
    url: URL_VENTAS,
    type: "POST",
    dataType: "json",
    data: {
      accion: "estado_venta",
      idVenta: idVenta,
    },
    success: function (r) {
      if (!r.success) {
        Swal.fire("Error", "No se pudo cargar la venta.", "error");
        return;
      }

      let v = r.venta;

      $("#offcanvas_venta_titulo").text("Venta #" + v.NumeroVenta);

      $("#venta_estado_cuenta").html(`
        <h5>${v.RazonSocial || ""}</h5>

        <div class="row mt-3">
          <div class="col-6">
            <small class="text-muted">Total</small>
            <h5>${formatoMoneda(v.Total)}</h5>
          </div>

          <div class="col-6">
            <small class="text-muted">Pagado</small>
            <h5>${formatoMoneda(v.TotalPagado)}</h5>
          </div>

          <div class="col-6 mt-2">
            <small class="text-muted">Saldo</small>
            <h5>${formatoMoneda(v.Saldo)}</h5>
          </div>

          <div class="col-6 mt-2">
            <small class="text-muted">Estado</small>
            <h5>${badgeEstadoPago(v.EstadoPago)}</h5>
          </div>
        </div>
      `);

      let htmlPagos = "";

      if (!r.pagos || r.pagos.length === 0) {
        htmlPagos = `
          <tr>
            <td colspan="4" class="text-center text-muted">
              Sin pagos asignados.
            </td>
          </tr>
        `;
      } else {
        r.pagos.forEach(function (p) {
          htmlPagos += `
            <tr>
              <td>${p.Fecha || ""}<br><small class="text-muted">${p.Hora || ""}</small></td>
              <td>${p.Banco || ""}</td>
              <td>${p.Operacion || ""}</td>
              <td>${formatoMoneda(p.ImporteAplicado)}</td>
            </tr>
          `;
        });
      }

      $("#tabla_pagos_venta").html(htmlPagos);

      let offcanvas = new bootstrap.Offcanvas(document.getElementById("offcanvas_venta"));
      offcanvas.show();
    },
    error: function (xhr) {
      console.log(xhr.responseText);
      Swal.fire("Error", "Error consultando estado de venta.", "error");
    },
  });
}

function formatoMoneda(valor) {
  return (
    "$ " +
    parseFloat(valor || 0).toLocaleString("es-AR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
}

function badgeEstadoPago(estado) {
  let clase = "warning";

  if (estado === "PAGADA") clase = "success";
  if (estado === "PARCIAL") clase = "info";

  return `<span class="badge bg-${clase}">${estado || "PENDIENTE"}</span>`;
}
