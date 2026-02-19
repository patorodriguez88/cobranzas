if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./sw.js')
      .then(reg => console.log('Registro de SW exitoso', reg))
      .catch(err => console.warn('Error al tratar de registrar el sw', err))
      
  }
  
  $('a.scroll').click(function(e){
    e.preventDefault();
    $('html, body').stop().animate({scrollTop: $($(this).attr('href')).offset().top}, 1000);
    });
    // Tu problema lo resolvería con esto.
    ww = $(window).width();
    if (ww>800) {
    $('a.scroll').click(function(e){
    e.preventDefault();
    $('html, body').stop().animate({
        scrollTop: $($(this).attr('href')).offset().top}, 1000);
    });
    }


    // var button = document.getElementById("notifications");
    // button.addEventListener('click', function(e) {
        
    //     Notification.requestPermission().then(function(result) {
    //         console.log(result);
    //         if(result === 'granted') {
    //             randomNotification();
    //         }
    //     });
    // });

    function randomNotification() {
        // var randomItem = 1;
        var notifTitle = 'Dinter S.A.';
        var notifBody = 'Creado por .';
        var notifImg = './img/icon_32.png';
        var options = {
            body: notifBody,
            icon: notifImg
        }
        var notif = new Notification(notifTitle, options);
        // setTimeout(randomNotification, 30000);
    }