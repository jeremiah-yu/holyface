(function () {
  'use strict';

  var nav = document.getElementById('primary-nav');
  var toggle = document.querySelector('.nav-toggle');
  if (nav && toggle) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    });
  }

  document.querySelectorAll('img').forEach(function (img) {
    if (!img.hasAttribute('loading') && !img.closest('.site-header')) {
      img.setAttribute('loading', 'lazy');
      img.setAttribute('decoding', 'async');
    }
  });

  var KEY = 'hf_cookie_consent';
  var banner = document.getElementById('cookie-banner');

  function readConsent() {
    try {
      return localStorage.getItem(KEY) || '';
    } catch (e) {
      return '';
    }
  }

  function writeConsent(value) {
    try {
      localStorage.setItem(KEY, value);
    } catch (e) { /* ignore quota */ }
    document.cookie = KEY + '=' + encodeURIComponent(value) + ';path=/;max-age=31536000;SameSite=Lax';
  }

  if (banner && !readConsent()) {
    banner.hidden = false;
  }

  document.querySelectorAll('[data-cookie]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      writeConsent(btn.getAttribute('data-cookie') || 'reject');
      if (banner) banner.hidden = true;
    });
  });

  document.querySelectorAll('[data-cookie-reset]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      try { localStorage.removeItem(KEY); } catch (e) { /* ignore */ }
      document.cookie = KEY + '=;path=/;max-age=0';
      if (banner) banner.hidden = false;
    });
  });

  var form = document.querySelector('[data-contact-form]');
  if (form) {
    form.addEventListener('submit', function (ev) {
      var email = form.querySelector('#email');
      var message = form.querySelector('#message');
      var captcha = form.querySelector('#captcha');
      var problems = [];
      if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        problems.push('Enter a valid email.');
      }
      if (message && message.value.trim().length < 10) {
        problems.push('Message must be at least 10 characters.');
      }
      if (captcha && captcha.value.trim() === '') {
        problems.push('Answer the spam-check question.');
      }
      if (problems.length) {
        ev.preventDefault();
        alert(problems.join('\n'));
      }
    });
  }
})();
