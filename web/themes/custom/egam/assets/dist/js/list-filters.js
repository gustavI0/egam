/**
 * @file
 * Filtres exposés des vues de liste :
 *  - auto-submit débouncé pendant la frappe ;
 *  - bouton d'effacement (×) dans le champ ;
 *  - état « aucun résultat » typographique avec lien d'effacement ;
 *  - neutralisation du scroll automatique de Views à chaque rafraîchissement.
 */
(function (Drupal, once) {
  'use strict';

  var DEBOUNCE_MS = 250;
  var focusState = null;

  // Views émet un ScrollTopCommand ciblant le conteneur de la vue à chaque
  // soumission AJAX du formulaire exposé, ce qui fait « sauter » la page vers
  // le haut à chaque frappe. On ignore cette commande pour nos vues de liste
  // uniquement (sélecteur .js-view-dom-id-*), sans toucher aux autres AJAX.
  if (Drupal.AjaxCommands && !Drupal.AjaxCommands.prototype.egamScrollPatched) {
    var originalScrollTop = Drupal.AjaxCommands.prototype.scrollTop;
    Drupal.AjaxCommands.prototype.scrollTop = function (ajax, response) {
      if (response && typeof response.selector === 'string' &&
          response.selector.indexOf('.js-view-dom-id-') === 0) {
        return;
      }
      if (originalScrollTop) { originalScrollTop.apply(this, arguments); }
    };
    Drupal.AjaxCommands.prototype.egamScrollPatched = true;
  }

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

  function searchInputs(form) {
    return Array.prototype.slice.call(
      form.querySelectorAll('input[type="text"], input[type="search"]')
    );
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

  function submitForm(form) {
    saveFocus();
    var submit = findSubmit(form);
    if (submit) { submit.click(); }
  }

  // Vide tous les champs de recherche du formulaire puis resoumet.
  function clearForm(form) {
    searchInputs(form).forEach(function (input) {
      input.value = '';
      var field = input.closest('.egam-filter-field');
      if (field) { field.classList.remove('has-value'); }
    });
    var first = searchInputs(form)[0];
    if (first) { first.focus(); }
    submitForm(form);
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

  // Remplace le texte brut de la zone « aucun résultat » par une présentation
  // typographique reprenant le terme recherché, avec un lien d'effacement.
  function buildEmptyState(emptyEl, form) {
    var terms = form
      ? searchInputs(form).map(function (i) { return i.value.trim(); }).filter(Boolean)
      : [];

    emptyEl.textContent = '';
    emptyEl.classList.add('egam-empty');

    if (terms.length) {
      var lead = document.createElement('p');
      lead.className = 'egam-empty__lead';
      lead.textContent = Drupal.t('Aucun résultat pour');
      emptyEl.appendChild(lead);
    }

    var term = document.createElement('p');
    term.className = 'egam-empty__term';
    term.textContent = terms.length ? '« ' + terms.join(' · ') + ' »' : Drupal.t('Aucun résultat');
    emptyEl.appendChild(term);

    var hint = document.createElement('p');
    hint.className = 'egam-empty__hint';
    if (form) {
      hint.appendChild(document.createTextNode(Drupal.t('Essayez un autre terme ou ')));
      var clear = document.createElement('button');
      clear.type = 'button';
      clear.className = 'egam-empty__clear';
      clear.textContent = Drupal.t('effacez la recherche');
      clear.addEventListener('click', function () { clearForm(form); });
      hint.appendChild(clear);
      hint.appendChild(document.createTextNode('.'));
    } else {
      hint.textContent = Drupal.t('Aucun élément à afficher.');
    }
    emptyEl.appendChild(hint);
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

        searchInputs(form).forEach(function (input) {
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

      once('egam-empty-state', '.view-empty', context).forEach(function (emptyEl) {
        // Stable9 ne pose pas de classe `.view` sur le conteneur ; on cible le
        // sélecteur réellement présent (.js-view-dom-id-*) pour y retrouver le
        // formulaire exposé et lire le terme recherché.
        var container = emptyEl.closest('[class*="js-view-dom-id-"]');
        var form = container ? container.querySelector('.views-exposed-form') : null;
        buildEmptyState(emptyEl, form);
      });

      restoreFocus();
    }
  };
})(Drupal, once);
