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

                return `<td><i onclick="download('${row.id}')" style="cursor:point" class="mdi mdi-18px mdi-arrow-down-bold-box-outline text-warning"></i></td>`;
            
            }
        },
        ]  
        });    
    });
