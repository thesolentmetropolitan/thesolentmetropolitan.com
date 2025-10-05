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

              console.log("submenu is of type " + typeof submenu);

              const allSubMenus = document.querySelectorAll('.sub-menu-container');

              allSubMenus.forEach(aSubMenu => {
                console.log("search for current - submenu is of type " + typeof aSubMenu);
                if (aSubMenu.isEqualNode(submenu)) {


                  console.log('found the clicked submenu');

                  /*console.log('and yes there is a submenu');*/
                  aSubMenu.classList.remove("hidden");
                  aSubMenu.classList.add("visible");
                }
                else {
                  aSubMenu.classList.add("hidden");
                  aSubMenu.classList.remove("visible");
                }

              });

              /* https://stackoverflow.com/a/59906259/227926 */
              /* Array.from(nodes).find(node => node.isEqualNode(nodeToFind)); */
              /*const elementToRemove = Array.from(topMainContainers).find(node => node.isEqualNode(submenu));*/

              /*
              console.log('and yes there is a submenu');
              submenu.classList.remove("hidden");
              submenu.classList.add("visible");
              */
            }

          });

        });
        //console.log('It works!');

      });

    }
  };

}(jQuery, Drupal));

