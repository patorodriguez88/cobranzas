$(document).ready(function () {
  $.ajax({
    type: "POST",
    url: "conexion/comprueba.php",
    data: { comprueba: 1 },
    success: function (response) {
      var jsonData = JSON.parse(response);

      if (jsonData.success == 1) {
        $.ajax({
          type: "POST",
          url: "control/procesos/php/funciones.php",
          data: { Datos: 1 },

          success: function (response) {
            var jsonData = JSON.parse(response);

            if (jsonData.success == 1) {
              let name = jsonData.data[0].Nombre;
              let ape = jsonData.data[0].Apellido;

              let avatar = name.charAt(0) + ape.charAt(0);

              $("#avatar").html(avatar);
              $("#user_name").html(jsonData.data[0].Nombre + " " + jsonData.data[0].Apellido);
              $("#user_perfil").html(jsonData.data[0].Distribuidora);
            } else {
              // alert('error de ingreso');
              window.location.href = "https://www.dintersa.com.ar/cobranza/inicio_control.html";
            }
          },
        });
      } else {
        window.location.href = "https://www.dintersa.com.ar/cobranza/inicio_control.html";
      }
    },
  });
});
