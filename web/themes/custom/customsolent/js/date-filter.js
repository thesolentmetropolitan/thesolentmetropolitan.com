/**
 * @file
 * Auto-submit the events_listing exposed date filter when a pill is
 * picked, so users get one-tap interaction instead of having to hunt
 * for the Apply button after selecting.
 *
 * Adds `js-auto-submit` to <html> so CSS can hide the Apply button only
 * when JS is enabled — keeps the form working as a standard radio
 * group + submit when scripts are disabled.
 */
(function () {
  'use strict';

  function init() {
    var forms = document.querySelectorAll(
      'form.views-exposed-form[data-drupal-selector^="views-exposed-form-events-listing-"]'
    );
    if (!forms.length) {
      return;
    }
    document.documentElement.classList.add('js-auto-submit');
    forms.forEach(function (form) {
      var radios = form.querySelectorAll('input[type="radio"][name="date_filter_id"]');
      radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
          form.submit();
        });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  }
  else {
    init();
  }
})();
