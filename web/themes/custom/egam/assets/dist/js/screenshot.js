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
    once('screenshot', 'html', context).forEach(() => {
      // Attendre que Swiper soit initialisé avant de déplacer la caption
      setTimeout(() => {
        moveCaptionToTable();
      }, 100);

      // Alternative : observer les changements de classe
      observeSwiperInit();
    });
  }

  function observeSwiperInit() {
    // Observer l'apparition de la classe swiper-slide-active
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
          const target = mutation.target;
          if (target.classList.contains('swiper-slide-active')) {
            moveCaptionToTable();
            // Arrêter d'observer une fois que c'est fait
            observer.disconnect();
          }
        }
      });
    });

    // Observer tous les swiper-slide pour détecter l'ajout de la classe active
    const slides = document.querySelectorAll('.swiper-slide');
    slides.forEach(slide => {
      observer.observe(slide, {
        attributes: true,
        attributeFilter: ['class']
      });
    });
  }

  function update() {
    const slides = document.querySelectorAll('.swiper-slide');
    slides.forEach(slide => {
      // Éviter les doublons d'event listeners
      if (!slide.dataset.screenshotListenerAdded) {
        slide.addEventListener('click', handleSlideClick);
        slide.dataset.screenshotListenerAdded = 'true';
      }
    });
  }

  function handleSlideClick() {
    // Masquer toutes les captions originales
    document.querySelectorAll('.chapter').forEach(e => e.style.display = 'none');
    moveCaptionToTable();
  }

  function moveCaptionToTable() {
    document.querySelectorAll('table tr.chapter').forEach(row => row.remove());

    // Masquer toutes les captions originales
    document.querySelectorAll('.chapter').forEach(e => e.style.display = 'none');

    // Chercher d'abord avec .swiper-slide-active, sinon prendre le premier slide
    let caption = document.querySelector('.swiper-slide-active .chapter');

    if (!caption) {
      caption = document.querySelector('.swiper-slide:first-child .chapter');
    }

    if (!caption) {
      return;
    }

    const rowBelow = document.querySelector('table tr.date');
    if (!rowBelow) {
      return;
    }

    const newRow = createRow(caption);
    if (newRow) {
      rowBelow.parentNode.insertBefore(newRow, rowBelow);
    }
  }

  function createRow(el) {
    // Vérification de sécurité pour éviter les erreurs
    const titleElement = el.children[0]?.firstChild;
    const contentElement = el.children[1]?.firstChild;

    if (!titleElement || !contentElement) {
      return null;
    }

    const newRow = document.createElement('tr');
    newRow.classList.add('item', 'chapter');

    const newRowHead = document.createElement('th');
    newRowHead.setAttribute('scope', 'row');
    newRowHead.textContent = titleElement.textContent;

    const newRowCell = document.createElement('td');
    newRowCell.textContent = contentElement.textContent;

    newRow.appendChild(newRowHead);
    newRow.appendChild(newRowCell);

    return newRow;
  }

})(Drupal, once);