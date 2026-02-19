$( document ).ready(function() {

    $.ajax({
    type: "POST",
    url: 'control/procesos/php/funciones.php',
    data:{'Totales':1},
    success: function(response)
    {

    var jsonData = JSON.parse(response);

    $('#total_conciliados').html(jsonData.conciliados);
    
    $('#total_no_conciliados').html(jsonData.total);
    
    $('#total_exportados').html(jsonData.total_exportados);
    
    }
    });

});