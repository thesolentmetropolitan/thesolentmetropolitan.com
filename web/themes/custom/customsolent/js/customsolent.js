/**
 * @file
 * customsolent behaviors.
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.customsolent = {
    attach: function (context, settings) {
      $(document).ready(function () {

        const menuItems = document.querySelectorAll('nav[role=navigation] li');

        menuItems.forEach(item => {
          item.addEventListener('click', function () {
                    console.log('main menu item clicked');
            // Find the submenu within the clicked item
            const submenu = this.querySelector('.sub-menu-container');
            if (submenu) {
                    console.log('and yes there is a submenu');
submenu.classList.remove("hidden");
submenu.classList.add("visible");
            }

          });


        });
        //console.log('It works!');

      });

    }
  };

}(jQuery, Drupal));

