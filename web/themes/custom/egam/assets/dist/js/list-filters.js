/**
 * @file
 * Auto-submit débouncé pour les filtres exposés des vues de liste.
 *
 * Déclenche une soumission AJAX ~250ms après la dernière frappe.
 * Ajoute un bouton × pour effacer un champ rempli.
 */
(function (Drupal, once) {
  'use strict';

  var DEBOUNCE_MS = 250;

  function debounce(fn, wait) {
    var t;
    return function () {
      var args = arguments;
      var ctx = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, args); }, wait);
    };
  }

  function findSubmit(form) {
    return form.querySelector('[data-drupal-selector^="edit-submit-"], input[type="submit"]');
  }

  function addClearButton(input, onClear) {
    var wrap = document.createElement('span');
    wrap.className = 'egam-filter-field';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'egam-filter-clear';
    btn.setAttribute('aria-label', Drupal.t('Effacer'));
    btn.innerHTML = '&times;';
    wrap.appendChild(btn);

    function sync() {
      wrap.classList.toggle('has-value', input.value.length > 0);
    }
    sync();
    input.addEventListener('input', sync);
    btn.addEventListener('click', function () {
      input.value = '';
      sync();
      onClear();
    });
  }

  Drupal.behaviors.egamListFilters = {
    attach: function (context) {
      var forms = once('egam-list-filters', '.views-exposed-form', context);
      forms.forEach(function (form) {
        var submit = findSubmit(form);
        if (!submit) { return; }

        // Cacher le bouton "Appliquer" sans le supprimer
        // (il est conservé pour l'accessibilité clavier et le no-JS).
        form.classList.add('egam-autosubmit');

        var trigger = debounce(function () { submit.click(); }, DEBOUNCE_MS);

        var inputs = form.querySelectorAll('input[type="text"], input[type="search"]');
        inputs.forEach(function (input) {
          input.setAttribute('autocomplete', 'off');
          input.addEventListener('input', trigger);
          // Enter soumet immédiatement (annule le debounce).
          input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); submit.click(); }
          });
          addClearButton(input, function () { submit.click(); });
        });
      });
    }
  };
})(Drupal, once);
