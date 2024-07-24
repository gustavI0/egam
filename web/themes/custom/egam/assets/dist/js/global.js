/**
 * @file
 * Global utilities.
 *
 */
(function (Drupal) {

  'use strict';

  Drupal.behaviors.egam = {
    attach: function (context, settings) {

      // When the user scrolls down 50px from the top of the document, resize the header's font size
      window.onscroll = function() {scrollFunction()};
      function scrollFunction() {
        if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
          document.getElementById("header").classList.add("small");
        } else {
          document.getElementById("header").classList.remove("small");
        }
      }
    }
  };

})(Drupal);
