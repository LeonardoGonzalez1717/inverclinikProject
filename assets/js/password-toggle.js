/**
 * Añade botón mostrar/ocultar (solo iconos ojo / ojo tachado) a cada input[type=password].
 * Opcional: data-no-password-toggle="1" en el input para omitir.
 */
(function () {
  var iconEye =
    '<svg class="password-toggle-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';

  var iconEyeOff =
    '<svg class="password-toggle-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

  function syncToggleIcon(btn, input) {
    var hidden = input.type === 'password';
    btn.innerHTML = hidden ? iconEye : iconEyeOff;
    btn.setAttribute('aria-label', hidden ? 'Mostrar contraseña' : 'Ocultar contraseña');
  }

  function wrapPasswordInput(input) {
    if (!input || input.type !== 'password') return;
    if (input.getAttribute('data-no-password-toggle') === '1') return;
    var parent = input.parentNode;
    if (!parent) return;
    if (parent.classList && parent.classList.contains('password-field-wrap')) return;

    var wrap = document.createElement('div');
    wrap.className = 'password-field-wrap';

    parent.insertBefore(wrap, input);
    wrap.appendChild(input);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'password-toggle-btn';

    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      if (show) {
        input.classList.add('password-toggle-input-reveal');
      } else {
        input.classList.remove('password-toggle-input-reveal');
      }
      syncToggleIcon(btn, input);
    });

    wrap.appendChild(btn);
    syncToggleIcon(btn, input);
  }

  function initPasswordToggles(root) {
    root = root || document;
    var list = root.querySelectorAll('input[type="password"]');
    for (var i = 0; i < list.length; i++) {
      wrapPasswordInput(list[i]);
    }
  }

  window.initPasswordToggles = initPasswordToggles;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initPasswordToggles(document);
    });
  } else {
    initPasswordToggles(document);
  }
})();
