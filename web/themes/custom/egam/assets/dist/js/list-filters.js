/**
 * @file
 * Auto-submit débouncé pour les filtres exposés des vues de liste.
 */
(function (Drupal, once) {
  'use strict';

  var DEBOUNCE_MS = 250;
  var focusState = null;

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

  function saveFocus() {
    var el = document.activeElement;
    if (el && el.matches('.egam-filter-field input')) {
      focusState = {
        name: el.name,
        start: el.selectionStart,
        end: el.selectionEnd,
        formId: el.closest('form').id
      };
    }
  }

  function restoreFocus() {
    if (!focusState) { return; }
    var form = document.getElementById(focusState.formId);
    if (!form) { focusState = null; return; }
    var input = form.querySelector('input[name="' + focusState.name + '"]');
    if (!input) { focusState = null; return; }
    input.focus();
    try { input.setSelectionRange(focusState.start, focusState.end); } catch (e) {}
    focusState = null;
  }

  function addClearButton(input, onClear) {
    if (input.parentNode.classList.contains('egam-filter-field')) { return; }
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

        form.classList.add('egam-autosubmit');

        var trigger = debounce(function () {
          saveFocus();
          submit.click();
        }, DEBOUNCE_MS);

        var inputs = form.querySelectorAll('input[type="text"], input[type="search"]');
        inputs.forEach(function (input) {
          input.setAttribute('autocomplete', 'off');
          input.addEventListener('input', trigger);
          input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
              e.preventDefault();
              saveFocus();
              submit.click();
            }
          });
          addClearButton(input, function () {
            saveFocus();
            submit.click();
          });
        });
      });

      restoreFocus();
    }
  };
})(Drupal, once);
