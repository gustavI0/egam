/**
 * @file
 * Screenshot caption
 *
 */
(function (Drupal, once) {

  Drupal.behaviors.screenshot = {
    attach: function (context) {
      init(context);
      update();
    }
  };

  function init(context) {
    once('screenshot', 'html', context).forEach(() => moveCaptionToTable());
  }

  function update() {
    const slides = document.querySelectorAll('.swiper-slide');
    slides.forEach(e => e.addEventListener('click', () => {
      document.querySelector('table tr.chapter').style.display = 'none';
      moveCaptionToTable();
    }));
  }

  function moveCaptionToTable() {
    document.querySelectorAll('.chapter').forEach(e => e.style.display = 'none');
    const caption = document.querySelector('.swiper-slide-active .chapter');
    if (!caption) {
      return;
    }
    const rowBelow = document.querySelector('table tr.date');
    const newRow = createRow(caption);
    rowBelow.parentNode.insertBefore(newRow, rowBelow);
  }

  function createRow(el) {
    const newRow = document.createElement('tr');
    newRow.classList.add('item', 'chapter');
    const newRowHead = document.createElement('th');
    newRowHead.setAttribute('scope', 'row');
    const newRowHeadContent = document.createTextNode(el.children[0].firstChild.textContent);
    newRowHead.appendChild(newRowHeadContent);
    const newRowCell = document.createElement('td');
    const newRowCellContent = document.createTextNode(el.children[1].firstChild.textContent);
    newRowCell.appendChild(newRowCellContent);
    newRow.appendChild(newRowHead);
    newRow.appendChild(newRowCell);
    return newRow;
  }

})(Drupal, once);
