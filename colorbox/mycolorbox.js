jQuery(document).ready(function(){
//Examples of how to assign the ColorBox event to elements
jQuery("a[rel^='example4']").colorbox({
    slideshow: true,
    slideshowAuto: false,
    slideshowSpeed: 3500,
    maxWidth: "90%",
    maxHeight: "90%",
    current: "{current} of {total}",
    });
});
