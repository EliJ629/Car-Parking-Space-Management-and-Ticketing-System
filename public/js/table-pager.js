/*
 * Pages for tables the server does not page: <table data-paginate> shows 10 rows at a time (data-paginate="15" for another size).
 * Tables the server already pages (Laravel links) are left alone. The controls use the .bpager styles from pager.css.
 */
(function () {
  'use strict';

  function build(table) {
    var size = parseInt(table.getAttribute('data-paginate'), 10) || 10;
    var body = table.tBodies[0];
    if (!body) return;
    var page = 1;
    var bar = document.createElement('nav');
    bar.className = 'bpager';
    bar.setAttribute('aria-label', 'Pages');
    bar.hidden = true;
    var host = table.parentNode && table.parentNode !== document.body && /wrap|overflow/i.test(table.parentNode.className + ' ' + table.parentNode.getAttribute('style')) ? table.parentNode : table;
    host.parentNode.insertBefore(bar, host.nextSibling);

    var observer = new MutationObserver(function () { draw(); });

    function rows() {
      return [].slice.call(body.rows).filter(function (r) { return !r.querySelector('td[colspan]'); });
    }

    function go(n) { page = n; draw(); }

    function btn(label, n, off, current) {
      var el;
      if (current) { el = document.createElement('strong'); el.setAttribute('aria-current', 'page'); }
      else if (off) { el = document.createElement('span'); el.className = 'off'; el.setAttribute('aria-disabled', 'true'); }
      else { el = document.createElement('button'); el.type = 'button'; el.addEventListener('click', function () { go(n); }); }
      el.textContent = label;
      return el;
    }

    function draw() {
      observer.disconnect();
      var list = rows();
      var pages = Math.max(1, Math.ceil(list.length / size));
      if (page > pages) page = pages;
      list.forEach(function (r, i) { r.style.display = i >= (page - 1) * size && i < page * size ? '' : 'none'; });

      bar.hidden = pages < 2;
      bar.innerHTML = '';
      if (pages > 1) {
        var info = document.createElement('span');
        info.className = 'bp-info';
        info.textContent = 'Showing ' + ((page - 1) * size + 1) + ' to ' + Math.min(page * size, list.length) + ' of ' + list.length;
        var pg = document.createElement('span');
        pg.className = 'bp-pages';
        pg.appendChild(btn('Previous', page - 1, page === 1));
        for (var p = Math.max(1, page - 2); p <= Math.min(pages, page + 2); p++) pg.appendChild(btn(String(p), p, false, p === page));
        pg.appendChild(btn('Next', page + 1, page === pages));
        bar.appendChild(info);
        bar.appendChild(pg);
      }
      observer.observe(body, { childList: true });
    }

    draw();
  }

  function start() { document.querySelectorAll('table[data-paginate]').forEach(build); }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
