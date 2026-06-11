function anularExportacion(id, descargas) {

    let texto = descargas > 0
        ? `Este archivo fue descargado ${descargas} vez/veces. Los registros volverán a Conciliados para poder re-exportarse.`
        : `Los registros volverán a Conciliados para poder re-exportarse.`;

    Swal.fire({
        title: '¿Anular exportación #' + String(id).padStart(8, '0') + '?',
        text: texto,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#fa5c7c',
        confirmButtonText: 'Sí, anular',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (!result.isConfirmed) return;

        $.ajax({
            data: { Anular: 1, id: id },
            url: 'control/procesos/php/exportar.php',
            type: 'post',
            dataType: 'json',
            success: function (r) {
                if (r.success == 1) {
                    Swal.fire({ icon: 'success', title: 'Anulado', text: 'Los registros volvieron a Conciliados.', timer: 2000, showConfirmButton: false })
                        .then(function() { location.reload(); });
                } else {
                    Swal.fire("Error", r.error || "No se pudo anular.", "error");
                }
            },
            error: function (xhr) {
                Swal.fire("Error", "Error de comunicación con el servidor.", "error");
            }
        });
    });
}

function download(file){
    
    fetch('https://www.dintersa.com.ar/cobranza/control/procesos/php/exportaciones/'+file+'.csv')
  .then(resp => resp.blob())
  .then(blob => {
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.style.display = 'none';
    a.href = url;
    // the filename you want
    a.download = file+'.csv';
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    // alert('your file has downloaded!'); // or you know, something with better UX...
  })
  .catch(() => alert('oh no!'));

  $.ajax({
    data:{'Exportado':1,'id':file},
    url:'control/procesos/php/exportar.php',
    type:'post',
    success: function(response)
     {
        var jsonData = JSON.parse(response);
        
        if(jsonData.success==1){

            $.NotificationApp.send("Exito !", 'Archivo Descargado.', "bottom-right", "#FFFFFF", "success");

            var datatable= $('#exportaciones_tabla').DataTable();
            datatable.ajax.reload();  

        }
     }  
});  

}

$( document ).ready(function() {
    
    var datatable_exportaciones= $('#exportaciones_tabla').DataTable({
        dom: 'Bfrtip',
        buttons: ['copy', 'excel', 'pdf'],
        paging: true,
        searching: true,
        responsive: true,
        pageLength:100,
        lengthMenu: [
            [10, 25, 50, -1],
            [10, 25, 50, 'All']
          ],    
        ajax: {
          url:"control/procesos/php/exportar.php",
          data:{'Tabla_exportados':1},
          type:'post'
          },
          columns: [
          {data:"id"},    
          {data:null,        
          render: function (data, type, row) {
                return `<td>${row.Fecha}</br>`+
                `<small class="text-muted"> ${row.Hora}</small></td>`;                   
        }
        },  
            
          {data:null,        
          render: function (data, type, row) {
            var Xmas95 = new Date(row.TimeStamp);
            var day = Xmas95.getDate();
            var month= Xmas95.getMonth()+1;
            var year= Xmas95.getUTCFullYear();
            let hour=moment(row.TimeStamp).add(3, 'hour').format('HH:mm');
            var newdate=day+'.'+month+'.'+year+' '+hour;

            if(row.Estado=='Generado'){
                return `<td>Generado el ${row.Fecha} ${row.Hora} por ${row.User}</td>`;
            }else{
                return `<td>Generado el ${row.Fecha} ${row.Hora}</br>`+
                       `<td>Descargado el ${newdate} por ${row.User}</td>`;
            }
            }          
        },  
        {data:null,
            
            render: function (data, type, row) {
                
                var display = $.fn.dataTable.render.number( '.', ',', 2, '$ ' ).display;
                var importe = display( row.Total );
              
                return `<td>${importe}</td></br><small class="text-muted h8">Registros: ${row.Registros}</small></br>`;
          }            
        },

          {data:null,
          width:"20%",
          render: function (data, type, row) {   
            var Xmas95 = new Date(row.TimeStamp);
            var day = Xmas95.getDate();
            var month= Xmas95.getMonth()+1;
            var year= Xmas95.getUTCFullYear();
            let hour=moment(row.TimeStamp).add(3, 'hour').format('HH:mm');
            var newdate=day+'.'+month+'.'+year+' '+hour;

            // console.log(newdate);
                
          if(row.Estado=="Generado"){

            return `<td ><h5><span class="badge badge-success-lighten">${row.Estado}</span></h5></br>`+
            `<small class="text-muted"> ${newdate}</small></td>`;              
            
            }else{
             if(row.Descargas===1){
                return `<td ><h5><span class="badge badge-warning-lighten">${row.Estado} </span></h5>${row.Descargas} (Descarga)</td></br>`+
                `<small class="text-muted"> ${newdate}</small>`;              
             }else{
                return `<td ><h5><span class="badge badge-warning-lighten">${row.Estado} </span></h5> ${row.Descargas} (Descargas)</td></br>`+
                `<small class="text-muted"> ${newdate}</small>`;              
             }
            
            }
          } 
         },
         {data:null,
            render: function(data,type,row){

                let btnDescargar = row.Estado !== 'Anulado'
                    ? `<i onclick="download('${row.id}')" style="cursor:pointer" title="Descargar" class="mdi mdi-18px mdi-arrow-down-bold-box-outline text-warning me-2"></i>`
                    : '';

                let btnAnular = row.Estado !== 'Anulado'
                    ? `<i onclick="anularExportacion('${row.id}', ${row.Descargas || 0})" style="cursor:pointer" title="Anular exportación" class="mdi mdi-18px mdi-undo-variant text-danger"></i>`
                    : `<span class="badge badge-danger-lighten">Anulado</span>`;

                return `<td>${btnDescargar}${btnAnular}</td>`;
            }
        },
        ]  
        });    
    });
