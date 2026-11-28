/**
 * @file
 * The venue map on an event page: Leaflet over OpenStreetMap tiles, one
 * peg per venue with a point (the data is in the page). One venue is
 * centred; several are fitted. A peg's tooltip names the place and a
 * click goes to its page. Without this script the address line says
 * where, and the map box is not shown.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.slntVenueMap = {
    attach: function (context) {
      once('slnt-venue-map', '.slnt-venue-map', context).forEach(function (root) {
        if (typeof L === 'undefined') {
          return;
        }
        var pegs = [];
        try {
          pegs = JSON.parse(root.querySelector('.slnt-venue-map__data').textContent);
        }
        catch (e) {
          return;
        }
        pegs = pegs.filter(function (p) { return p.lat !== null && p.lon !== null; });
        if (!pegs.length) {
          return;
        }
        root.classList.add('is-ready');
        var map = L.map(root.querySelector('.slnt-venue-map__canvas'), { scrollWheelZoom: false });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        // The same peg as Explore / Locations' chosen place: Leaflet's
        // marker in the Explore orange (location-map.css recolours it).
        var icon = L.icon({
          iconUrl: '/themes/custom/customsolent/leaflet/images/marker-icon.png',
          iconRetinaUrl: '/themes/custom/customsolent/leaflet/images/marker-icon-2x.png',
          shadowUrl: '/themes/custom/customsolent/leaflet/images/marker-shadow.png',
          iconSize: [25, 41], iconAnchor: [12, 41], tooltipAnchor: [16, -28], shadowSize: [41, 41],
          className: 'slnt-peg slnt-peg--chosen'
        });
        var group = L.featureGroup().addTo(map);
        pegs.forEach(function (p) {
          var m = L.marker([p.lat, p.lon], { title: p.name, alt: p.name, icon: icon });
          m.bindTooltip(p.name);
          if (p.url) {
            m.on('click', function () { window.location.href = p.url; });
          }
          group.addLayer(m);
        });
        if (pegs.length === 1) {
          map.setView([pegs[0].lat, pegs[0].lon], 15);
        }
        else {
          map.fitBounds(group.getBounds(), { padding: [24, 24] });
        }
      });
    }
  };
})(Drupal, once);
