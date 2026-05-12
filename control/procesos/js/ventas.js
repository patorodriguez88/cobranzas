let tablaVentas;
let productosVenta = [];
let ventaActualOffcanvas = 0;
let numeroOrdenVentaActual = "";
let turnoVentaActual = null;
let turnoFechaSeleccionada = "";
let turnoHoraSeleccionada = "";
const URL_VENTAS = "control/procesos/php/ventas.php";

$(document).ready(function () {
  cargarProductosVenta();
  cargarVentas();
  cargarProductosVentaRapida();
  mostrarPantallaVentas();
  cargarResumenVentas();
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
    $("#cliente_telefono").val(cliente.Celular || "");
  });
});
$(document).on("click", "#btn_guardar_orden_venta", function () {
  let idVenta = $("#orden_venta_id_venta").val();
  let numero = $("#orden_venta_numero").val().trim();

  if (numero === "") {
    Swal.fire({
      icon: "warning",
      title: "Número requerido",
      text: "Debe ingresar un número de orden.",
    });
    return;
  }

  $.ajax({
    url: URL_VENTAS,
    type: "POST",
    dataType: "json",
    data: {
      accion: "guardar_orden_venta",
      idVenta: idVenta,
      NumeroOrdenVenta: numero,
    },
    success: function (r) {
      if (r.success == 1) {
        $("#modal_orden_venta").modal("hide");

        Swal.fire({
          icon: "success",
          title: "Orden guardada",
          timer: 1200,
          showConfirmButton: false,
        });

        abrirEstadoVenta(idVenta);

        if ($.fn.DataTable.isDataTable("#tabla_listado_ventas")) {
          $("#tabla_listado_ventas").DataTable().ajax.reload(null, false);
        }

        if ($.fn.DataTable.isDataTable("#tabla_ventas")) {
          $("#tabla_ventas").DataTable().ajax.reload(null, false);
        }
      } else {
        Swal.fire("Error", r.error || "No se pudo guardar la orden.", "error");
      }
    },
    error: function (xhr) {
      console.log(xhr.responseText);
      Swal.fire("Error", "Error guardando la orden de venta.", "error");
    },
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

        render: function (data, type, row) {
          let clase = "warning";

          if (data === "PAGADA") clase = "success";

          if (data === "PARCIAL") clase = "info";

          let html = `<span class="badge bg-${clase}">${data}</span>`;

          if (row.NumeroOrdenVenta) {
            html += `
      <div class="mt-0">
        <span class="badge bg-dark">
          OV #${row.NumeroOrdenVenta}
        </span>
      </div>
    `;
          }
          if (row.TurnoRetiro) {
            html += `

    <div class="mt-0">

      <span class="badge bg-success">

        <i class="mdi mdi-calendar-clock"></i> ${row.TurnoRetiro}

      </span>

    </div>

  `;
          }

          return html;
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

          if ($.fn.DataTable.isDataTable("#tabla_ventas")) {
            $("#tabla_ventas").DataTable().ajax.reload(null, false);
          }

          if ($.fn.DataTable.isDataTable("#tabla_listado_ventas")) {
            $("#tabla_listado_ventas").DataTable().ajax.reload(null, false);
          }

          cargarProductosVenta();
          cargarProductosVentaRapida();
          cargarResumenVentas();
        } else {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: r.error || "No se pudo eliminar la venta.",
          });
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
          if (type === "sort" || type === "type") {
            return parseInt(data || 0);
          }

          return `
      <div>
        <span class="badge bg-primary">#${data}</span>
      </div>
      <small class="text-muted">${row.Usuario || ""}</small>
    `;
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
        render: function (data, type, row) {
          return renderCantidadProductoVenta(data, row, "FIGURITAS");
        },
      },
      {
        data: "Productos",
        render: function (data, type, row) {
          return renderCantidadProductoVenta(data, row, "ALBUM");
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

        render: function (data, type, row) {
          let clase = "warning";

          if (data === "PAGADA") clase = "success";

          if (data === "PARCIAL") clase = "info";

          let html = `<span class="badge bg-${clase}">${data}</span>`;

          if (row.NumeroOrdenVenta) {
            html += `
      <div class="mt-0">
        <span class="badge bg-dark">
          OV #${row.NumeroOrdenVenta}
        </span>
      </div>
    `;
          }
          if (row.TurnoRetiro) {
            html += `

    <div class="mt-0">

      <span class="badge bg-success">

        <i class="mdi mdi-calendar-clock"></i> ${row.TurnoRetiro}

      </span>

    </div>

  `;
          }

          return html;
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

    $("#cards_resumen_ventas").show();
    $("#card_listado_ventas").show();
    $("#cards_resumen_productos_ventas").show();
    cargarResumenProductosVentas();
    cargarResumenVentas();
    cargarListadoVentas();
  } else {
    $("#card_nueva_venta").show();

    $("#card_ultimas_ventas").show();

    $("#cards_resumen_ventas").hide();

    $("#cards_resumen_productos_ventas").hide();

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

      ventaActualOffcanvas = v.id;
      numeroOrdenVentaActual = v.NumeroOrdenVenta || "";

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

      let htmlDetalleTotal = "";
      let totalDetalle = 0;

      if (!r.detalle || r.detalle.length === 0) {
        htmlDetalleTotal = `
    <tr>
      <td colspan="4" class="text-center text-muted">
        Sin productos.
      </td>
    </tr>
  `;
      } else {
        r.detalle.forEach(function (item) {
          let cantidad = parseFloat(item.Cantidad || 0);
          let precio = parseFloat(item.PrecioUnitario || 0);
          let subtotal = parseFloat(item.Subtotal || cantidad * precio);

          totalDetalle += subtotal;

          htmlDetalleTotal += `
      <tr>
        <td>${item.ProductoNombre || ""}</td>
        <td class="text-end">${cantidad.toLocaleString("es-AR")}</td>
        <td class="text-end">${formatoMoneda(precio)}</td>
        <td class="text-end">${formatoMoneda(subtotal)}</td>
      </tr>
    `;
        });

        htmlDetalleTotal += `
    <tr class="table-light">
      <td colspan="3" class="text-end fw-bold">Total</td>
      <td class="text-end fw-bold">${formatoMoneda(totalDetalle)}</td>
    </tr>
  `;
      }

      $("#tabla_detalle_total_venta").html(htmlDetalleTotal);

      if (v.TurnoRetiro && v.TurnoRetiro !== "") {
        $("#texto_turno_retiro").html(`
    <i class="mdi mdi-calendar-clock"></i> ${v.TurnoRetiro}
  `);
      } else {
        $("#texto_turno_retiro").html(`<span class="text-muted">Sin turno asignado</span>`);
      }

      if (v.NumeroOrdenVenta && v.NumeroOrdenVenta !== "") {
        $("#texto_orden_venta").html(`#${v.NumeroOrdenVenta}`);
      } else {
        $("#texto_orden_venta").html(`<span class="text-muted">Sin orden asignada</span>`);
      }

      // $("#btn_offcanvas_orden_venta")
      //   .off("click")
      //   .on("click", function () {
      //     generarOrdenVentaWepoint(v.id);
      //   });
      if (v.EstadoPago === "PAGADA") {
        $("#btn_offcanvas_orden_venta")
          .prop("disabled", false)
          .removeClass("btn-outline-secondary")
          .addClass("btn-outline-primary")
          .off("click")
          .on("click", function () {
            generarOrdenVentaWepoint(v.id);
          });
      } else {
        $("#btn_offcanvas_orden_venta")
          .prop("disabled", true)
          .removeClass("btn-outline-primary")
          .addClass("btn-outline-secondary")
          .off("click");
      }
      $("#btn_offcanvas_turno_retiro")
        .off("click")
        .on("click", function () {
          abrirModalTurnoRetiro(v.id);
        });
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

      const offcanvasElement = document.getElementById("offcanvas_venta");

      let offcanvas = bootstrap.Offcanvas.getInstance(offcanvasElement) || new bootstrap.Offcanvas(offcanvasElement);

      offcanvas.show();
    },
    error: function (xhr, status, error) {
      console.log("ERROR estado venta:", xhr.responseText);

      Swal.fire({
        icon: "error",
        title: "Error consultando estado de venta",
        html: `
      <div class="text-start">
        <p><b>Status:</b> ${xhr.status}</p>
        <p><b>Error:</b> ${error || "-"}</p>
        <hr>
        <pre style="white-space:pre-wrap;font-size:12px;">${xhr.responseText}</pre>
      </div>
    `,
        width: 800,
      });
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
function cargarResumenVentas() {
  $.ajax({
    url: URL_VENTAS,
    type: "POST",
    dataType: "json",
    data: {
      accion: "resumen_ventas",
    },
    success: function (r) {
      $("#ventas_pendientes").text(r.PENDIENTE || 0);
      $("#ventas_parciales").text(r.PARCIAL || 0);
      $("#ventas_pagadas").text(r.PAGADA || 0);
      $("#ventas_total").text(r.TOTAL || 0);
    },
    error: function (xhr) {
      console.log(xhr.responseText);
    },
  });
}

function editarOrdenVenta(idVenta, numeroActual) {
  $("#orden_venta_id_venta").val(idVenta);
  $("#orden_venta_numero").val(numeroActual || "");

  $("#modal_orden_venta").modal("show");

  setTimeout(function () {
    $("#orden_venta_numero").trigger("focus");
  }, 300);
}
function abrirQRordenVenta(numeroOrdenVenta) {
  $("#qr_orden_venta_titulo").text("Orden de Venta #" + numeroOrdenVenta);
  $("#qr_orden_venta").html("");

  new QRCode(document.getElementById("qr_orden_venta"), {
    text: numeroOrdenVenta,
    width: 220,
    height: 220,
  });

  $("#modal_qr_orden_venta").modal("show");
}
$(document).on("hidden.bs.offcanvas", "#offcanvas_venta", function () {
  $(".offcanvas-backdrop").remove();

  $("body").removeClass("offcanvas-backdrop");
  $("body").removeClass("modal-open");

  $("body").css("overflow", "");
  $("body").css("padding-right", "");
});
function abrirModalTurnoRetiro(idVenta) {
  turnoVentaActual = null;
  turnoFechaSeleccionada = "";
  turnoHoraSeleccionada = "";

  $("#turno_id_venta").val(idVenta);
  $("#btn_guardar_turno_retiro").prop("disabled", true);
  $("#turno_dias").html("");
  $("#turno_horas").html("");

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
        Swal.fire("Error", r.error || "No se pudo cargar la venta.", "error");
        return;
      }

      turnoVentaActual = r.venta;

      $("#turno_cliente").text(r.venta.RazonSocial || "");
      $("#turno_info_venta").text(
        "Venta #" + r.venta.NumeroVenta + (r.venta.NumeroOrdenVenta ? " | OV #" + r.venta.NumeroOrdenVenta : ""),
      );

      renderDiasTurno();

      $("#modal_turno_retiro").modal("show");
    },
    error: function (xhr) {
      console.log(xhr.responseText);
      Swal.fire("Error", "No se pudo abrir el turno.", "error");
    },
  });
}

function renderDiasTurno() {
  let html = "";

  for (let i = 0; i <= 7; i++) {
    let fecha = new Date();
    fecha.setDate(fecha.getDate() + i);

    let yyyy = fecha.getFullYear();
    let mm = String(fecha.getMonth() + 1).padStart(2, "0");
    let dd = String(fecha.getDate()).padStart(2, "0");

    let fechaSQL = `${yyyy}-${mm}-${dd}`;
    let fechaTexto = fecha.toLocaleDateString("es-AR", {
      weekday: "short",
      day: "2-digit",
      month: "2-digit",
    });

    html += `
      <div class="col-md-2 col-6 mb-2">
        <button type="button"
          class="btn btn-outline-primary w-100 btn-dia-turno"
          data-fecha="${fechaSQL}">
          ${fechaTexto}
        </button>
      </div>
    `;
  }

  $("#turno_dias").html(html);
}

$(document).on("click", ".btn-dia-turno", function () {
  turnoFechaSeleccionada = $(this).data("fecha");
  turnoHoraSeleccionada = "";

  $(".btn-dia-turno").removeClass("btn-primary").addClass("btn-outline-primary");
  $(this).removeClass("btn-outline-primary").addClass("btn-primary");

  $("#btn_guardar_turno_retiro").prop("disabled", true);

  cargarHorasTurno(turnoFechaSeleccionada);
});
function cargarHorasTurno(fecha) {
  $("#turno_horas").html(`
    <div class="col-12 text-muted">Cargando horarios...</div>
  `);

  $.ajax({
    url: URL_VENTAS,
    type: "POST",
    dataType: "json",
    data: {
      accion: "turnos_por_fecha",
      FechaTurno: fecha,
    },
    success: function (r) {
      let ocupacion = {};

      if (r.success && r.data) {
        r.data.forEach(function (x) {
          ocupacion[x.Hora] = parseInt(x.Total || 0);
        });
      }

      let html = "";

      for (let h = 7; h <= 17; h++) {
        let hora = String(h).padStart(2, "0") + ":00:00";
        let horaTexto = String(h).padStart(2, "0") + ":00";
        let total = ocupacion[hora] || 0;

        html += `
          <div class="col-md-2 col-6 mb-2">
            <button type="button"
              class="btn btn-outline-secondary w-100 btn-hora-turno"
              data-hora="${hora}">
              <strong>${horaTexto}</strong><br>
              <small>${total} turno${total === 1 ? "" : "s"}</small>
            </button>
          </div>
        `;
      }

      $("#turno_horas").html(html);
    },
    error: function (xhr) {
      console.log(xhr.responseText);
      Swal.fire("Error", "No se pudieron cargar los horarios.", "error");
    },
  });
}

$(document).on("click", ".btn-hora-turno", function () {
  turnoHoraSeleccionada = $(this).data("hora");

  $(".btn-hora-turno").removeClass("btn-success").addClass("btn-outline-secondary");
  $(this).removeClass("btn-outline-secondary").addClass("btn-success");

  $("#btn_guardar_turno_retiro").prop("disabled", false);
});
$(document).on("click", "#btn_guardar_turno_retiro", function () {
  if (!turnoFechaSeleccionada || !turnoHoraSeleccionada) {
    Swal.fire("Atención", "Debe seleccionar día y hora.", "warning");
    return;
  }

  $.ajax({
    url: URL_VENTAS,
    type: "POST",
    dataType: "json",
    data: {
      accion: "guardar_turno_retiro",
      idVenta: $("#turno_id_venta").val(),
      FechaTurno: turnoFechaSeleccionada,
      HoraTurno: turnoHoraSeleccionada,
    },
    success: function (r) {
      if (r.success == 1) {
        $("#modal_turno_retiro").modal("hide");

        let htmlWp = "";

        if (r.whatsapp_url && r.whatsapp_url !== "") {
          htmlWp = `
    <a href="${r.whatsapp_url}" 
       target="_blank" 
       class="btn btn-success mt-2">
      <i class="mdi mdi-whatsapp"></i> Enviar WhatsApp
    </a>
  `;
        } else {
          htmlWp = `
    <div class="alert alert-warning mt-2 mb-0">
      El cliente no tiene celular cargado.
    </div>
  `;
        }

        Swal.fire({
          icon: "success",
          title: "Turno asignado",
          html: `
    <p>El turno fue generado correctamente.</p>
    ${htmlWp}
  `,
          showConfirmButton: true,
          confirmButtonText: "Cerrar",
        });

        abrirEstadoVenta($("#turno_id_venta").val());
      } else {
        Swal.fire("Error", r.error || "No se pudo guardar el turno.", "error");
      }
    },
    error: function (xhr) {
      console.log(xhr.responseText);
      Swal.fire("Error", "Error guardando turno.", "error");
    },
  });
});
$(document).on("change", ".cantidad-producto-venta", function () {
  let input = $(this);

  let cantidad = parseInt(input.val() || 0);
  let stock = parseInt(input.data("stock") || 0);
  let cantidadAnterior = parseInt(input.data("actual") || 0);

  // stock disponible real:
  // lo que tengo + lo ya asignado a esta venta
  let maximoPermitido = stock + cantidadAnterior;

  if (cantidad > maximoPermitido) {
    Swal.fire({
      icon: "warning",
      title: "Stock insuficiente",
      html: `
        <div class="text-start">
          <b>Stock actual:</b> ${stock}<br>
          <b>Ya asignado en esta venta:</b> ${cantidadAnterior}<br>
          <b>Máximo permitido:</b> ${maximoPermitido}
        </div>
      `,
    });

    input.val(cantidadAnterior);
    return;
  }

  $.ajax({
    url: URL_VENTAS,
    type: "POST",
    dataType: "json",
    data: {
      accion: "actualizar_cantidad_producto_venta",
      idVenta: input.data("idventa"),
      ProductoNombre: input.data("producto"),
      Cantidad: cantidad,
    },
    success: function (r) {
      if (r.success == 1) {
        if ($.fn.DataTable.isDataTable("#tabla_listado_ventas")) {
          $("#tabla_listado_ventas").DataTable().ajax.reload(null, false);
        }

        if ($.fn.DataTable.isDataTable("#tabla_ventas")) {
          $("#tabla_ventas").DataTable().ajax.reload(null, false);
        }

        cargarResumenVentas();
        cargarResumenProductosVentas();
      } else {
        Swal.fire("Error", r.error || "No se pudo actualizar.", "error");

        input.val(cantidadAnterior);
      }
    },
  });
});
function renderCantidadProductoVenta(data, row, tipo) {
  if (!data) return "-";

  let productos = data.split("||");
  let itemEncontrado = null;

  productos.forEach(function (p) {
    let partes = p.split(" x");
    let nombre = (partes[0] || "").trim();
    let cantidad = partes[1] || 0;

    if (nombre.toUpperCase().includes(tipo)) {
      itemEncontrado = {
        nombre: nombre,
        cantidad: cantidad,
      };
    }
  });

  if (!itemEncontrado) return `<span class="text-muted">0</span>`;

  let editable = row.EstadoPago === "PENDIENTE";

  if (!editable) {
    return `<span class="fw-bold">${itemEncontrado.cantidad}</span>`;
  }

  let stockDisponible = 0;

  if (tipo === "FIGURITAS") {
    stockDisponible = parseInt(row.StockFiguritas || 0);
  }

  if (tipo === "ALBUM") {
    stockDisponible = parseInt(row.StockAlbum || 0);
  }

  return `

  <input 
    type="number" 
    class="form-control form-control-sm text-center cantidad-producto-venta"
    data-idventa="${row.id}"
    data-producto="${itemEncontrado.nombre}"
    data-actual="${itemEncontrado.cantidad}"
    data-stock="${stockDisponible}"
    value="${itemEncontrado.cantidad}"
    min="0"
    style="width:90px; height:28px; font-size:11px; padding-left:2px; padding-right:2px;"
  >

`;
}
function cargarResumenProductosVentas() {
  $.ajax({
    url: URL_VENTAS,
    type: "POST",
    dataType: "json",
    data: {
      accion: "resumen_productos_ventas",
    },
    success: function (r) {
      $("#figus_stock").text(r.FIGURITAS.stock || 0);
      $("#figus_total").text(r.FIGURITAS.total || 0);
      $("#figus_pendiente").text(r.FIGURITAS.pendiente || 0);

      $("#album_stock").text(r.ALBUM.stock || 0);
      $("#album_total").text(r.ALBUM.total || 0);
      $("#album_pendiente").text(r.ALBUM.pendiente || 0);

      $("#figus_vendedores").html(renderVendedoresResumen(r.FIGURITAS.vendedores));
      $("#album_vendedores").html(renderVendedoresResumen(r.ALBUM.vendedores));
    },
  });
}

function renderVendedoresResumen(vendedores) {
  if (!vendedores || vendedores.length === 0) {
    return "Sin datos por vendedor.";
  }

  let html = "";

  vendedores.forEach(function (v) {
    html += `
      <div class="d-flex justify-content-between">
        <span>${v.Usuario || "Sin usuario"}</span>
        <strong>${v.Total}</strong>
      </div>
    `;
  });

  return html;
}
function generarOrdenVentaWepoint(idVenta) {
  Swal.fire({
    title: "Forma de entrega",
    input: "select",
    inputOptions: {
      1: "Caddy",
      2: "Retira cliente",
      3: "Comisionista",
    },
    inputPlaceholder: "Seleccione una forma de entrega",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Generar OV",
    cancelButtonText: "Cancelar",
    inputValidator: function (value) {
      if (!value) {
        return "Debe seleccionar una forma de entrega.";
      }
    },
  }).then((result) => {
    if (!result.isConfirmed) return;

    let idTransportista = result.value;

    $.ajax({
      url: "control/procesos/php/wepoint_orden_venta.php",
      type: "POST",
      dataType: "json",
      data: {
        idVenta: idVenta,
        idTransportista: idTransportista,
      },
      success: function (res) {
        if (res.success) {
          Swal.fire("OV generada", "Número: " + res.nro_orden_venta, "success");

          abrirEstadoVenta(idVenta);

          if ($.fn.DataTable.isDataTable("#tabla_ventas")) {
            $("#tabla_ventas").DataTable().ajax.reload(null, false);
          }

          if ($.fn.DataTable.isDataTable("#tabla_listado_ventas")) {
            $("#tabla_listado_ventas").DataTable().ajax.reload(null, false);
          }
        } else {
          let htmlError = "";

          if (res.response && res.response.errors && Array.isArray(res.response.errors)) {
            htmlError += `
              <div class="text-start">
                <p><b>${res.response.message || "Wepoint rechazó la orden."}</b></p>
                <table class="table table-sm table-bordered mt-2">
                  <thead>
                    <tr>
                      <th>Producto</th>
                      <th>SKU</th>
                      <th>Disponible</th>
                      <th>Solicitado</th>
                    </tr>
                  </thead>
                  <tbody>
            `;

            res.response.errors.forEach(function (item) {
              htmlError += `
                <tr>
                  <td>${item.nombre || "-"}</td>
                  <td>${item.sku || "-"}</td>
                  <td class="text-end">${item.disponible_para_venta || 0}</td>
                  <td class="text-end">${item.cantidadRequerida || 0}</td>
                </tr>
              `;
            });

            htmlError += `
                  </tbody>
                </table>
              </div>
            `;
          } else {
            htmlError = res.message || res.error || "No se pudo generar la OV.";
          }

          Swal.fire({
            icon: "error",
            title: "Wepoint rechazó la OV",
            html: htmlError,
            width: 850,
          });

          console.log(res);
        }
      },
      error: function (xhr) {
        console.log(xhr.responseText);
        Swal.fire("Error", "No se pudo conectar con el servidor", "error");
      },
    });
  });
}
