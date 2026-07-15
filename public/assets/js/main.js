(function () {
  'use strict';

  var toastEl = document.getElementById('toast');
  var toastTimer = null;

  function showToast(text) {
    if (!toastEl) return;
    toastEl.textContent = text;
    toastEl.classList.add('show');
    if (toastTimer) window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () {
      toastEl.classList.remove('show');
    }, 4000);
  }

  function fallbackCopy(text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    textarea.setSelectionRange(0, textarea.value.length);
    var ok = false;
    try {
      ok = document.execCommand('copy');
    } catch (e) {
      ok = false;
    }
    document.body.removeChild(textarea);
    return ok;
  }

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).then(function () {
        return true;
      }).catch(function () {
        return fallbackCopy(text);
      });
    }
    return Promise.resolve(fallbackCopy(text));
  }

  function reportCopyEvent(address) {
    var body = new URLSearchParams({ address: address });
    fetch('api/copy.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    }).catch(function () {
      /* 静默失败，不影响用户复制体验 */
    });
  }

  var copyBtn = document.getElementById('copy-btn');
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      var address = copyBtn.getAttribute('data-address') || '';
      if (!address) return;

      copyText(address).then(function (ok) {
        if (!ok) {
          showToast('复制失败，请长按地址手动复制');
          return;
        }

        var label = copyBtn.querySelector('.copy-btn-label');
        var originalText = label ? label.textContent : '';
        copyBtn.classList.add('copied');
        if (label) label.textContent = '已复制 ✓';
        window.setTimeout(function () {
          copyBtn.classList.remove('copied');
          if (label) label.textContent = originalText;
        }, 1500);

        showToast(window.__COPY_TIP__ || '地址已复制');
        reportCopyEvent(address);
      });
    });
  }
})();
