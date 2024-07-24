/**
 * @file
 * Screenshot caption
 *
 */
(function (Drupal) {

  'use strict';

  Drupal.behaviors.screenshot = {
    attach: function (context, settings) {
      const hasCaption = !! document.querySelector('.chapter');
      if (!hasCaption) {
        return;
      }
      const hasMultipleScreenshots = !! document.querySelector('.swiper-container');
      const activeCaption = hasMultipleScreenshots ? document.querySelector('.swiper-slide-activer .chapter') : document.querySelector('.chapter');
      console.log('scr ' + hasMultipleScreenshots)
      console.log('caption ' + hasCaption)
      console.log(activeCaption)

      function moveCaptionToTable() {
        const caption = document.querySelector('.chapter');
        const table = document.querySelector('table .developer');
        document.body.insertBefore(createRow(caption), table)
        console.log(caption);
      }

      function createRow(el) {
        const newRow = document.createElement('tr');
        const newRowHead = document.createElement('th');
        const newRowHeadContent = document.createTextNode(el.children[0]);
        newRowHead.appendChild(newRowHeadContent)
        const newCell = document.createElement('td');
        const newCellContent = document.createTextNode(el.children[1]);
        newCellContent.appendChild(newCellContent);
        newRow.appendChild(newRowHead);
        newRow.appendChild(newCell);
        return newRow;
      }
    }
  };

})(Drupal);
