/**
 * @file
 * Explore / Locations: the map of places (Location epic).
 *
 * Leaflet over OpenStreetMap tiles. Every venue with a point is a peg;
 * areas are not pegged: choosing one fits the map to the area's extent
 * (its Extent field, the gazetteer's bounding box for a town or suburb),
 * or, without one, to the venues within it, or to its own centre.
 * Choosing a place, by peg or by its link in the list, fetches its
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

        // The chosen place's peg is in the Explore colour; the rest are
        // Leaflet's blue. Same image, a class the stylesheet recolours.
        var pegIcon = function (chosen) {
          return L.icon({
            iconUrl: '/themes/custom/customsolent/leaflet/images/marker-icon.png',
            iconRetinaUrl: '/themes/custom/customsolent/leaflet/images/marker-icon-2x.png',
            shadowUrl: '/themes/custom/customsolent/leaflet/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], tooltipAnchor: [16, -28], shadowSize: [41, 41],
            className: chosen ? 'slnt-peg slnt-peg--chosen' : 'slnt-peg'
          });
        };
        var chosenPeg = null;
        function highlight(slug) {
          if (chosenPeg && chosenPeg !== markers[slug]) {
            chosenPeg.setIcon(pegIcon(false));
            chosenPeg.setZIndexOffset(0);
          }
          chosenPeg = markers[slug] || null;
          if (chosenPeg) {
            chosenPeg.setIcon(pegIcon(true));
            chosenPeg.setZIndexOffset(1000);
          }
        }

        var markers = {};
        var group = L.featureGroup().addTo(map);
        data.forEach(function (p) {
          if (p.area || p.lat === null || p.lon === null) {
            return;
          }
          var m = L.marker([p.lat, p.lon], { title: p.name, alt: p.name, icon: pegIcon(false) });
          m.bindTooltip(p.name);
          m.on('click', function () { select(p.slug, true); });
          markers[p.slug] = m;
        });

        // Which pegs: the places with something on (the default) or all.
        // The chosen place always shows.
        var show = root.getAttribute('data-show') || 'busy';
        function applyShow() {
          Object.keys(markers).forEach(function (slug) {
            var p = bySlug[slug];
            var visible = show === 'all' || p.busy || slug === current;
            if (visible && !group.hasLayer(markers[slug])) {
              group.addLayer(markers[slug]);
            }
            else if (!visible && group.hasLayer(markers[slug])) {
              group.removeLayer(markers[slug]);
            }
          });
          root.setAttribute('data-show', show);
          root.querySelectorAll('.slnt-location-map__show-button').forEach(function (b) {
            b.setAttribute('aria-pressed', b.getAttribute('data-show') === show ? 'true' : 'false');
          });
        }
        root.querySelectorAll('.slnt-location-map__show-button').forEach(function (b) {
          b.addEventListener('click', function () {
            show = b.getAttribute('data-show') === 'all' ? 'all' : 'busy';
            applyShow();
            var link = current && root.querySelector('.slnt-location-map__places a[data-place="' + current + '"]');
            if (link) {
              link.closest('.slnt-location-map__item').classList.remove('slnt-location-map__item--quiet');
            }
          });
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
          if (p.bounds) {
            map.fitBounds(p.bounds, { padding: [12, 12], animate: true });
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
                        var list = root.querySelector('.slnt-location-map__tree');
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
        applyShow();
        function select(slug, push) {
          var p = bySlug[slug];
          if (!p) {
            return;
          }
          current = slug;
          if (markers[slug] && !group.hasLayer(markers[slug])) {
            group.addLayer(markers[slug]);
          }
          mark(slug);
          highlight(slug);
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
          highlight(current);
          var start = bySlug[current];
          var pegs = markers[current] ? [markers[current]] : pegsWithin(current);
          if (markers[current]) {
            map.setView(markers[current].getLatLng(), 16);
            markers[current].openTooltip();
          }
          else if (start.bounds) {
            // An area: the shape of it, not a street in one of its towns.
            map.fitBounds(start.bounds, { padding: [12, 12] });
          }
          else if (pegs.length) {
            map.fitBounds(L.featureGroup(pegs).getBounds().pad(0.15));
          }
          else if (start.lat !== null && start.lon !== null) {
            // An area with no pegged venue yet (Hilsea): its own centroid,
            // as a later click does, not the whole region.
            map.setView([start.lat, start.lon], 13);
          }
          else if (group.getLayers().length) {
            map.fitBounds(group.getBounds().pad(0.05));
          }
          else {
            map.setView([50.85, -1.25], 10);
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
