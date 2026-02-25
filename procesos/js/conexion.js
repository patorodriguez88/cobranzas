$(document).ready(function () {
  $("#success-alert-modal").on("hidden.bs.modal", function () {
    location.reload();
  });

  $.ajax({
    type: "POST",
    url: "conexion/comprueba.php",
    data: { comprueba_cobranza: 1 },
    success: function (response) {
      var jsonData = JSON.parse(response);

      if (jsonData.success == 1) {
        $.ajax({
          type: "POST",
          url: "procesos/php/function.php",
          data: { Datos: 1 },

          success: function (response) {
            var jsonData = JSON.parse(response);

            if (jsonData.success == 1) {
              $("#name").val(jsonData.data[0].RazonSocial);
              $("#ncliente").val(jsonData.data[0].Ncliente);
            } else {
              alert("error de ingreso");
            }
          },
        });
      } else {
        window.location.href = "https://www.dintersa.com.ar/cobranza/inicio.html";
      }
    },
  });
});
