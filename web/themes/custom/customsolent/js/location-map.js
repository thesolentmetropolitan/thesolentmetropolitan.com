/**
 * @file
 * Explore / Locations: the map of places (Location epic).
 *
 * Leaflet over OpenStreetMap tiles. Every venue with a point is a peg;
 * areas are not pegged (choosing one fits the map to the venues within
 * it). Choosing a place, by peg or by its link in the list, fetches its
 * panel (/explore/locations/<slug>/panel), marks it in the list, moves the
 * map and pushes the address, so the back button and a bookmark both
 * work. Without this script every link is a full page.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.slntLocationMap = {
    attach: function (context) {
      once('slnt-location-map', '.slnt-location-map', context).forEach(function (root) {
        if (typeof L === 'undefined') {
          return;
        }
        var data = [];
        try {
          data = JSON.parse(root.querySelector('.slnt-location-map__data').textContent);
        }
        catch (e) {
          return;
        }
        var base = root.getAttribute('data-base') || '/explore/locations';
        var panel = root.querySelector('.slnt-location-map__panel');
        var canvas = root.querySelector('.slnt-location-map__canvas');
        var bySlug = {};
        var children = {};
        data.forEach(function (p) {
          bySlug[p.slug] = p;
          var parent = p.parent || 0;
          (children[parent] = children[parent] || []).push(p);
        });

        var map = L.map(canvas, { scrollWheelZoom: false });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var markers = {};
        var group = L.featureGroup().addTo(map);
        data.forEach(function (p) {
          if (p.area || p.lat === null || p.lon === null) {
            return;
          }
          var m = L.marker([p.lat, p.lon], { title: p.name, alt: p.name });
          m.bindTooltip(p.name);
          m.on('click', function () { select(p.slug, true); });
          m.addTo(group);
          markers[p.slug] = m;
        });

        // The venues within a place, at any depth, that have a peg.
        function pegsWithin(slug) {
          var out = [];
          var walk = function (p) {
            if (markers[p.slug]) {
              out.push(markers[p.slug]);
            }
            (children[p.tid] || []).forEach(walk);
          };
          var p = bySlug[slug];
          if (p) {
            walk(p);
          }
          return out;
        }

        function moveTo(slug) {
          var p = bySlug[slug];
          if (!p) {
            return;
          }
          if (markers[slug]) {
            map.setView(markers[slug].getLatLng(), Math.max(map.getZoom(), 16), { animate: true });
            markers[slug].openTooltip();
            return;
          }
          var pegs = pegsWithin(slug);
          if (pegs.length) {
            map.fitBounds(L.featureGroup(pegs).getBounds().pad(0.15), { animate: true });
          }
          else if (p.lat !== null && p.lon !== null) {
            map.setView([p.lat, p.lon], 13, { animate: true });
          }
        }

        function mark(slug) {
          root.querySelectorAll('.slnt-location-map__places a[aria-current]').forEach(function (a) {
            a.removeAttribute('aria-current');
          });
          var link = root.querySelector('.slnt-location-map__places a[data-place="' + slug + '"]');
          if (link) {
            link.setAttribute('aria-current', 'page');
            for (var li = link.parentElement; li && li !== root; li = li.parentElement) {
              if (li.classList.contains('slnt-location-map__item')) {
                li.classList.add('is-open');
              }
            }
            // Keep the chosen name in view inside the scrolling list, without
            // moving the page itself.
            var list = root.querySelector('.slnt-location-map__places');
            if (list && list.scrollHeight > list.clientHeight) {
              var top = link.offsetTop - list.offsetTop;
              if (top < list.scrollTop || top > list.scrollTop + list.clientHeight - 40) {
                list.scrollTop = Math.max(0, top - list.clientHeight / 3);
              }
            }
          }
          root.setAttribute('data-selected', slug);
        }

        var current = root.getAttribute('data-selected') || '';
        function select(slug, push) {
          var p = bySlug[slug];
          if (!p) {
            return;
          }
          current = slug;
          mark(slug);
          moveTo(slug);
          panel.setAttribute('aria-busy', 'true');
          fetch(base + '/' + slug + '/panel', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
            .then(function (html) {
              if (current !== slug) {
                return;
              }
              panel.innerHTML = html;
              panel.removeAttribute('aria-busy');
            })
            .catch(function () {
              panel.removeAttribute('aria-busy');
              window.location.href = p.url;
            });
          if (push && window.history && window.history.pushState) {
            window.history.pushState({ slnt_place: slug }, '', p.url);
            document.title = p.name + document.title.substring(document.title.indexOf(' | ') >= 0 ? document.title.indexOf(' | ') : document.title.length);
          }
        }

        // Links in the list and in the panel ("In Southampton", "Includes
        // …") select in place.
        root.addEventListener('click', function (e) {
          var a = e.target.closest('a[data-place]');
          if (!a || !root.contains(a) || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) {
            return;
          }
          e.preventDefault();
          select(a.getAttribute('data-place'), true);
        });
        window.addEventListener('popstate', function () {
          var m = window.location.pathname.match(new RegExp('^' + base.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '/([a-z0-9-]+)/?$'));
          if (m && bySlug[m[1]]) {
            select(m[1], false);
          }
        });

        // Start: on the chosen place, or over every peg.
        if (current && bySlug[current]) {
          mark(current);
          var pegs = markers[current] ? [markers[current]] : pegsWithin(current);
          if (markers[current]) {
            map.setView(markers[current].getLatLng(), 16);
            markers[current].openTooltip();
          }
          else if (pegs.length) {
            map.fitBounds(L.featureGroup(pegs).getBounds().pad(0.15));
          }
          else {
            map.fitBounds(group.getBounds().pad(0.05));
          }
        }
        else if (group.getLayers().length) {
          map.fitBounds(group.getBounds().pad(0.05));
        }
        else {
          map.setView([50.85, -1.25], 10);
        }
      });
    }
  };
})(Drupal, once);
