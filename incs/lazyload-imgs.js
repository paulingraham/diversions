document.addEventListener("DOMContentLoaded", function() {
  var lazyloadImages;    

console.log ('LAZY LOADER gittin bizzy');

  if ("IntersectionObserver" in window) {
    lazyloadImages = document.querySelectorAll(".lazyimg");
    var imageObserver = new IntersectionObserver(function(entries, observer) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          var image = entry.target;
          image.src = image.dataset.src;
          // image.classList.remove("lazyimg"); // I don't want to remove the class; it's useful!
          imageObserver.unobserve(image);
        }
      });
    });

    lazyloadImages.forEach(function(image) {
      imageObserver.observe(image);
    });
  } 
  
else 
    
  {  
    var lazyloadThrottleTimeout;
    lazyloadImages = document.querySelectorAll(".lazyimg");
    
    function lazyload () { 
      if(lazyloadThrottleTimeout) {
        clearTimeout(lazyloadThrottleTimeout);
      }    

      lazyloadThrottleTimeout = setTimeout(function() {
        var scrollTop = window.pageYOffset;
        lazyloadImages.forEach(function(img) {
            if(img.offsetTop < (window.innerHeight + scrollTop)) {
              img.src = img.dataset.src;
			  // image.classList.remove("lazyimg"); // I don't want to remove the class; it's useful!
            }
        });
        if(lazyloadImages.length == 0) { 
          document.removeEventListener("scroll", lazyload);
          window.removeEventListener("resize", lazyload);
          window.removeEventListener("orientationChange", lazyload);
        }
      }, 20);
    }

    document.addEventListener("scroll", lazyload);
    window.addEventListener("resize", lazyload);
    window.addEventListener("orientationChange", lazyload);
  }
})