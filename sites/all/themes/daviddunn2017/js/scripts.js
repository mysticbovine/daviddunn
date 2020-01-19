
/**
 * @file
 * A JavaScript file for the theme.
 *
 * In order for this JavaScript to be loaded on pages, see the instructions in
 * the README.txt next to this file.
 */

// JavaScript should be made compatible with libraries other than jQuery by
// wrapping it with an "anonymous closure". See:
// - http://drupal.org/node/1446420
// - http://www.adequatelygood.com/2010/3/JavaScript-Module-Pattern-In-Depth
(function ($, Drupal, window, document, undefined) {
	// Add col-md-2 to finder-form-element 
	$( document ).ready(function() {
	  //$(".finder-form-element").addClass("col-md-2 col-xs-6");
	 // $(".finder-form-element:first").addClass("col-md-offset-1");
	  //$(".finder-form").addClass("container");
	  //$(".finder-form .form-submit").addClass("btn-red col-md-6 col-md-offset-3");
	  
	// vUnit  
	new vUnit({
      CSSMap: {
        // The selector (vUnit will create rules ranging from .selector1 to .selector100)
        '.vh_height': {
          // The CSS property (any CSS property that accepts px as units)
          property: 'height',
          // What to base the value on (vh, vw, vmin or vmax)
          reference: 'vh'
        },
        // Wanted to have a font-size based on the viewport width? You got it.
        '.vw_font-size': {
          property: 'font-size',
          reference: 'vw'
        },
        // vmin and vmax can be used as well.
        '.vmin_margin-top': {
          property: 'margin-top',
          reference: 'vmin'
        }
      },
      onResize: function() {
        console.log('A screen resize just happened, yo.');
      }
    }).init(); // call the public init() method
	
	$('a.page-scroll').bind('click', function(event) {
        var $anchor = $(this);
        $('html, body').stop().animate({
            scrollTop: $($anchor.attr('href')).offset().top
        }, 1500, 'easeInOutExpo');
        event.preventDefault();
    });
	  
	  
	});
	
	// Add row to finder-form-element
})(jQuery, Drupal, this, this.document);
/* Create HTML5 element for IE */
document.createElement("section");
