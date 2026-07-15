(function () {
  'use strict';

  window.adminCsrfToken = function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  };
})();
