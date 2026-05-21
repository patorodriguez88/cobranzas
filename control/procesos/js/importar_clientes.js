let clientesImportados = [];

$("#btn_procesar_clientes").click(function () {
  let archivo = $("#excel_clientes")[0].files[0];

  if (!archivo) {
    alerta("Atención", "Seleccione un archivo Excel.", "warning");

    return;
  }

  let formData = new FormData();

  formData.append("accion", "preview_importacion_clientes");
  formData.append("archivo", archivo);

  formData.append("distribuidora", $("#importar_distribuidora").val());

  $.ajax({
    url: "control/procesos/php/clientes.php",

    type: "POST",

    data: formData,

    processData: false,

    contentType: false,

    dataType: "json",

    success: function (r) {
      if (!r.success) {
        alerta("Error", r.error || "Error procesando archivo.", "error");

        return;
      }

      clientesImportados = r.data;

      let html = "";

      r.data.forEach(function (c) {
        let badge = "";

        if (c.existe == 1) {
          badge = `
                        <span class="badge bg-danger">
                            DUPLICADO
                        </span>
                    `;
        } else {
          badge = `
                        <span class="badge bg-success">
                            NUEVO
                        </span>
                    `;
        }

        html += `
                    <tr>

                        <td>${badge}</td>

                        <td>${c.Ncliente}</td>

                        <td>${c.RazonSocial}</td>

                        <td>${c.Cuit}</td>

                        <td>${c.Direccion}</td>

                        <td>${c.Ciudad}</td>

                        <td>${c.Telefono}</td>

                        <td>${c.Celular}</td>

                    </tr>
                `;
      });

      $("#tabla_preview_clientes tbody").html(html);

      $("#btn_importar_clientes").show();
    },

    error: function (xhr) {
      console.log(xhr.responseText);

      alerta("Error", "Error procesando archivo.", "error");
    },
  });
});

$("#btn_importar_clientes").click(function () {
  if (clientesImportados.length === 0) {
    alerta("Atención", "No hay clientes para importar.", "warning");

    return;
  }

  $.ajax({
    url: "control/procesos/php/clientes.php",

    type: "POST",

    dataType: "json",

    data: {
      accion: "importar_clientes",
      clientes: JSON.stringify(clientesImportados),
    },

    success: function (r) {
      if (r.success) {
        alerta("Éxito", r.insertados + " clientes importados.", "success");
      } else {
        alerta("Error", r.error || "No se pudo importar.", "error");
      }
    },

    error: function (xhr) {
      console.log(xhr.responseText);

      alerta("Error", "Error importando clientes.", "error");
    },
  });
});
