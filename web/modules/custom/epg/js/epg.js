/**
 * @file
 * The programme guide: the view switch in page, the line for now, and
 * the "on now" marks, kept up to the minute. Without this script the
 * links still switch the view and the day by a full page.
 */
(function (Drupal, once) {
  'use strict';
  Drupal.behaviors.epgGuide = {
    attach: function (context) {
      once('epg-guide', '.epg', context).forEach(function (root) {
        var windowStart = parseInt(root.getAttribute('data-window-start'), 10) || 0;
        var windowEnd = parseInt(root.getAttribute('data-window-end'), 10) || 0;
        var nowLine = root.querySelector('.epg__now');
        var grid = root.querySelector('.epg__grid');
        var scroller = root.querySelector('.epg__scroller');

        // Switch the view in page; the address keeps the choice.
        root.querySelectorAll('.epg__view').forEach(function (a) {
          a.addEventListener('click', function (e) {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) {
              return;
            }
            e.preventDefault();
            var view = a.getAttribute('data-view');
            root.setAttribute('data-view', view);
            root.querySelectorAll('.epg__view').forEach(function (b) {
              if (b === a) {
                b.setAttribute('aria-current', 'true');
              }
              else {
                b.removeAttribute('aria-current');
              }
            });
            if (window.history && window.history.replaceState) {
              window.history.replaceState(null, '', a.getAttribute('href'));
            }
            update(true);
          });
        });

        function update(scroll) {
          var now = Math.floor(Date.now() / 1000);
          var view = root.getAttribute('data-view');
          root.querySelectorAll('.epg__programme').forEach(function (li) {
            var s = parseInt(li.getAttribute('data-start'), 10);
            var e = parseInt(li.getAttribute('data-end'), 10);
            li.classList.toggle('is-now', s <= now && now < e);
          });
          if (!nowLine) {
            return;
          }
          if (now < windowStart || now >= windowEnd || view === 'list') {
            nowLine.hidden = true;
            return;
          }
          var minutes = Math.floor((now - windowStart) / 60);
          root.style.setProperty('--now', String(minutes));
          var head = root.querySelector('.epg__corner');
          if (head && view === 'vertical') {
            var name = root.querySelector('.epg__station-name');
            root.style.setProperty('--head', (name ? name.offsetHeight : head.offsetHeight) + 'px');
          }
          nowLine.hidden = false;
          if (scroll && scroller && grid) {
            if (view === 'timeline') {
              var left = nowLine.offsetLeft - scroller.clientWidth / 3;
              scroller.scrollLeft = Math.max(0, left);
            }
            else if (view === 'vertical') {
              var top = nowLine.offsetTop - 160;
              if (top > 0) {
                window.scrollTo({ top: root.getBoundingClientRect().top + window.scrollY + top, behavior: 'auto' });
              }
            }
          }
        }
        update(true);
        setInterval(function () { update(false); }, 60000);

        // The timeline pages sideways by chevron (a mouse on a narrow
        // window) and drags with the pointer; a finger swipes it anyway.
        root.classList.add('has-script');
        root.querySelectorAll('.epg__scroll').forEach(function (b) {
          b.addEventListener('click', function () {
            if (!scroller) {
              return;
            }
            var dir = parseInt(b.getAttribute('data-scroll'), 10) || 1;
            scroller.scrollBy({ left: dir * scroller.clientWidth * 0.8, behavior: 'smooth' });
          });
        });
        if (scroller && window.PointerEvent) {
          scroller.classList.add('is-draggable');
          var drag = null;
          scroller.addEventListener('pointerdown', function (e) {
            if (e.pointerType !== 'mouse' || e.button !== 0 || root.getAttribute('data-view') !== 'timeline') {
              return;
            }
            drag = { x: e.clientX, left: scroller.scrollLeft, moved: false };
            scroller.classList.add('is-dragging');
          });
          scroller.addEventListener('pointermove', function (e) {
            if (!drag) {
              return;
            }
            var dx = e.clientX - drag.x;
            if (Math.abs(dx) > 4) {
              drag.moved = true;
            }
            scroller.scrollLeft = drag.left - dx;
          });
          var end = function () {
            if (!drag) {
              return;
            }
            var moved = drag.moved;
            drag = null;
            scroller.classList.remove('is-dragging');
            if (moved) {
              // A drag is not a click on the block it ended over.
              var swallow = function (ev) { ev.preventDefault(); ev.stopPropagation(); scroller.removeEventListener('click', swallow, true); };
              scroller.addEventListener('click', swallow, true);
              setTimeout(function () { scroller.removeEventListener('click', swallow, true); }, 0);
            }
          };
          scroller.addEventListener('pointerup', end);
          scroller.addEventListener('pointerleave', end);
          scroller.addEventListener('pointercancel', end);
        }
      });
    }
  };
})(Drupal, once);
