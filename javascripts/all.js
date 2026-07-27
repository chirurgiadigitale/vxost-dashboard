/* ==========================================================================
   XAMPP Dashboard — UI runtime (vanilla JS, zero dipendenze)
   Sostituisce jQuery 1.10 + Foundation 4. Backup: all.foundation.bak.js
   Funzioni:
     1. Tema chiaro/scuro persistente
     2. Menu mobile accessibile
     3. Icone SVG automatiche nella navigazione
     4. Accordion FAQ animato
     5. Reveal on scroll
     6. Compatibilita' legacy (@2x, data-delayed-href, data-x64-href)
   ========================================================================== */
(function () {
  'use strict';

  var STORAGE_KEY = 'xampp-dashboard-theme';
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------------------------------------------------------------
     Icone SVG (stroke 1.6, viewBox 24) — famiglia unica per tutta la UI
     --------------------------------------------------------------------- */
  var ICONS = {
    faq:      '<path d="M12 17h.01M9.1 9a3 3 0 1 1 4.2 2.7c-.8.4-1.3 1.1-1.3 2"/><circle cx="12" cy="12" r="9"/>',
    guide:    '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H19v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H19v3H6.5A2.5 2.5 0 0 1 4 20.5z"/><path d="M8 7.5h7M8 11h5"/>',
    info:     '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/>',
    database: '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
    folder:   '<path d="M3 8.5A2.5 2.5 0 0 1 5.5 6h3.2c.6 0 1.1.3 1.5.7l1 1.3h7.3A2.5 2.5 0 0 1 21 10.5v7A2.5 2.5 0 0 1 18.5 20h-13A2.5 2.5 0 0 1 3 17.5z"/>',
    home:     '<path d="M4 10.5 12 4l8 6.5V19a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19z"/><path d="M9.5 20.5v-6h5v6"/>',
    moon:     '<path d="M20 14.5A8.2 8.2 0 0 1 9.5 4 8.5 8.5 0 1 0 20 14.5"/>',
    sun:      '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M4.9 4.9l1.5 1.5m11.2 11.2 1.5 1.5M19.1 4.9l-1.5 1.5M6.4 17.6l-1.5 1.5"/>'
  };

  function svg(name, size) {
    return '<svg viewBox="0 0 24 24" width="' + (size || 18) + '" height="' + (size || 18) +
      '" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" ' +
      'stroke-linejoin="round" aria-hidden="true" focusable="false">' + ICONS[name] + '</svg>';
  }

  /* ---------------------------------------------------------------------
     1. Tema
     --------------------------------------------------------------------- */
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) { /* storage non disponibile */ }
  }

  function initTheme() {
    var stored;
    try { stored = localStorage.getItem(STORAGE_KEY); } catch (e) { stored = null; }
    applyTheme(stored === 'light' ? 'light' : 'dark');
  }

  function buildThemeToggle() {
    var bar = document.querySelector('.top-bar');
    if (!bar || bar.querySelector('.theme-toggle')) return;

    var actions = bar.querySelector('.nav-actions');
    if (!actions) {
      actions = document.createElement('div');
      actions.className = 'nav-actions';
      bar.appendChild(actions);
    }

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'theme-toggle';
    btn.setAttribute('aria-label', 'Cambia tema chiaro/scuro');
    btn.setAttribute('title', 'Cambia tema');
    btn.innerHTML = '<span class="icon-moon">' + svg('moon') + '</span>' +
                    '<span class="icon-sun">' + svg('sun') + '</span>';
    btn.addEventListener('click', function () {
      var current = document.documentElement.getAttribute('data-theme');
      applyTheme(current === 'light' ? 'dark' : 'light');
    });
    actions.appendChild(btn);
  }

  /* ---------------------------------------------------------------------
     2. Menu mobile
     --------------------------------------------------------------------- */
  function initNav() {
    var bar = document.querySelector('.top-bar');
    if (!bar) return;

    var toggle = bar.querySelector('.toggle-topbar a');
    var section = bar.querySelector('.top-bar-section');
    if (!toggle || !section) return;

    if (!section.id) section.id = 'primary-navigation';
    toggle.setAttribute('role', 'button');
    toggle.setAttribute('aria-controls', section.id);
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Apri o chiudi il menu');

    function setOpen(open) {
      bar.classList.toggle('expanded', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      setOpen(!bar.classList.contains('expanded'));
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && bar.classList.contains('expanded')) {
        setOpen(false);
        toggle.focus();
      }
    });

    document.addEventListener('click', function (e) {
      if (bar.classList.contains('expanded') && !bar.contains(e.target)) setOpen(false);
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth >= 900) setOpen(false);
    });
  }

  /* ---------------------------------------------------------------------
     3. Icone e stato attivo nella navigazione
     --------------------------------------------------------------------- */
  function decorateNav() {
    var links = document.querySelectorAll('.top-bar-section li.item > a');
    var path = window.location.pathname;

    Array.prototype.forEach.call(links, function (link) {
      var href = (link.getAttribute('href') || '').toLowerCase();
      var name = null;

      if (href.indexOf('phpmyadmin') > -1)      name = 'database';
      else if (href.indexOf('phpinfo') > -1)    name = 'info';
      else if (href.indexOf('progetti') > -1)   name = 'folder';
      else if (href.indexOf('faq') > -1)        name = 'faq';
      else if (href.indexOf('howto') > -1)      name = 'guide';
      else if (href.indexOf('index') > -1)      name = 'home';

      if (name && !link.querySelector('svg')) {
        link.insertAdjacentHTML('afterbegin', svg(name));
      }

      // Stato attivo calcolato dal percorso corrente (fallback sulle pagine legacy)
      var item = link.parentNode;
      if (!item.classList.contains('active') && href && href.charAt(0) === '/' &&
          href.split('#')[0] === path) {
        item.classList.add('active');
        link.setAttribute('aria-current', 'page');
      } else if (item.classList.contains('active')) {
        link.setAttribute('aria-current', 'page');
      }
    });
  }

  /* ---------------------------------------------------------------------
     4. Accordion FAQ
     --------------------------------------------------------------------- */
  function initAccordion() {
    var lists = document.querySelectorAll('dl.accordion');
    if (!lists.length) return;

    Array.prototype.forEach.call(lists, function (list) {
      var terms = list.querySelectorAll(':scope > dt');

      Array.prototype.forEach.call(terms, function (dt, i) {
        var dd = dt.nextElementSibling;
        if (!dd || dd.tagName !== 'DD') return;

        if (!dd.id) dd.id = 'faq-panel-' + (i + 1);
        dt.setAttribute('role', 'button');
        dt.setAttribute('tabindex', '0');
        dt.setAttribute('aria-expanded', 'false');
        dt.setAttribute('aria-controls', dd.id);

        dd.style.display = 'none';
        dd.style.overflow = 'hidden';

        function open() {
          // Chiude gli altri pannelli dello stesso gruppo
          Array.prototype.forEach.call(terms, function (other) {
            if (other !== dt) close(other);
          });
          dd.style.display = 'block';
          dt.classList.add('is-open');
          dt.setAttribute('aria-expanded', 'true');
          if (reduceMotion) return;
          var h = dd.scrollHeight;
          dd.style.maxHeight = '0px';
          dd.style.transition = 'max-height 260ms cubic-bezier(.22,.61,.36,1)';
          requestAnimationFrame(function () { dd.style.maxHeight = h + 'px'; });
        }

        function close(term) {
          var target = term || dt;
          var panel = target.nextElementSibling;
          if (!panel) return;
          panel.style.display = 'none';
          panel.style.maxHeight = '';
          target.classList.remove('is-open');
          target.setAttribute('aria-expanded', 'false');
        }

        function toggle() {
          if (dt.classList.contains('is-open')) close(); else open();
        }

        dt.addEventListener('click', toggle);
        dt.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
        });

        // Apertura diretta via ancora (#id nell'URL)
        if (window.location.hash && dd.id === window.location.hash.slice(1)) open();
      });
    });
  }

  /* ---------------------------------------------------------------------
     5. Reveal on scroll
     --------------------------------------------------------------------- */
  function initReveal() {
    var items = document.querySelectorAll('[data-reveal]');
    if (!items.length) return;

    if (reduceMotion || !('IntersectionObserver' in window)) {
      Array.prototype.forEach.call(items, function (el) { el.classList.add('is-visible'); });
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var delay = parseInt(entry.target.getAttribute('data-reveal-delay') || '0', 10);
        setTimeout(function () { entry.target.classList.add('is-visible'); }, delay);
        io.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

    Array.prototype.forEach.call(items, function (el) { io.observe(el); });
  }

  /* ---------------------------------------------------------------------
     6. Compatibilita' legacy
     --------------------------------------------------------------------- */
  function initLegacy() {
    // Immagini @2x su schermi ad alta densita'
    if (window.devicePixelRatio > 1) {
      Array.prototype.forEach.call(document.querySelectorAll('img[data-2x]'), function (img) {
        img.src = img.getAttribute('data-2x');
      });
    }

    // Link alternativi per Linux a 64 bit
    if (navigator.platform && navigator.platform.indexOf('Linux') === 0 &&
        navigator.platform.slice(-2) === '64') {
      Array.prototype.forEach.call(document.querySelectorAll('a[data-x64-href]'), function (a) {
        a.href = a.getAttribute('data-x64-href');
      });
    }

    // Download differiti
    Array.prototype.forEach.call(document.querySelectorAll('a[data-delayed-href]'), function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        window.open(a.getAttribute('data-delayed-href'), '_blank');
        window.location = a.getAttribute('href');
      });
    });
  }

  /* --------------------------------------------------------------------- */
  function boot() {
    buildThemeToggle();
    initNav();
    decorateNav();
    initAccordion();
    initReveal();
    initLegacy();
  }

  initTheme(); // prima del paint per evitare flash del tema sbagliato

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
