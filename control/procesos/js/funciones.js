$("#ingreso_btn").click(function(){

    let usuario=$('#usuario').val();    
    let pass=$('#password').val();    
    
    if(usuario==""){
        
        $('#error_text').html('Ingrese un Usuario');
    
        $('#error_alert').css('display','block');
    
        }else if(pass==""){

            $('#error_text').html('Ingrese una Contraseña');
    
            $('#error_alert').css('display','block');

        }else{
    
        $.ajax({
    
            type: "POST",
            url: 'control/procesos/php/funciones.php',
            data:{'Ingreso':1,'usuario':usuario,'password':pass},
    
            success: function(response)
            
            {
    
            var jsonData = JSON.parse(response);
                
                if(jsonData.success==1){
                
                window.location.href = 'http://www.dintersa.com.ar/cobranza/pendientes.html';     
    
                }else{
    
                    $('#error_text').html('Datos incorrectos');               
    
                    $('#error_alert').css('display','block'); 
                
                }
            }    
        });
    }
    });

