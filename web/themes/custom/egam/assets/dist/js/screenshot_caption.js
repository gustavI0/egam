/**
 * @file
 * Screenshot caption
 *
 */
(function (Drupal) {

  'use strict';

  Drupal.behaviors.egam = {
    attach: function (context, settings) {
      const caption = document.querySelector('.chapter');

      function moveCaptionToTable() {
        const caption = document.querySelector('.chapter');
        const table = document.querySelector('table');
        console.log(caption);
      }
    }
  };

})(Drupal);
