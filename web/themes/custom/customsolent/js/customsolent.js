/**
 * @file
 * customsolent behaviors.
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.customsolent = {
    attach: function (context, settings) {
      $(document).ready(function () {

        // or https://stackoverflow.com/a/24296220/227926
        //const mainMenuNavContainer = document.querySelector('block-customsolent-mainnavigation');

        const mainMenuNavContainer = document.querySelector('header > * nav[role=navigation]');


        mainMenuNavContainer.setAttribute("style", "height: 100px");

        const allSubMenus = document.querySelectorAll('.sub-menu-container');

        allSubMenus.forEach(aSubMenu => {
          aSubMenu.classList.remove("visible");
          aSubMenu.classList.add("hidden");


          aSubMenu.setAttribute("style", "top: 30px");
        }
        );


        const menuItems = document.querySelectorAll('nav[role=navigation] li');

        menuItems.forEach(item => {
          item.addEventListener('click', function (e) {

            e.stopPropagation();
            /*
            e.preventDefault();
            */

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


                  if (aSubMenu.classList.contains("hidden")) {

/*

.navigation__link--selected .navigation__link__down-icon
Specificity: (0,2,0)
 {
    transform: rotate(-180deg);
}

*/

                    /* opening submenu */
                    aSubMenu.classList.remove("hidden");
                    aSubMenu.classList.add("visible");
                    aSubMenu.setAttribute("style", "top: 110px");


                    /* https://stackoverflow.com/a/15615701/227926 */

                    var offsetHeight = aSubMenu.offsetHeight;

                    console.log('offsetHeight is of type' + typeof offsetHeight);
                    console.log('offsetHeight value is ' + offsetHeight);

                    /* var offsetHeightCalc = parseInt(offsetHeight.value) + 100; */

                    var offsetHeightCalc = parseInt(offsetHeight) + 100;

                    console.log('offsetHeightCalc is of type ' + typeof offsetHeightCalc);
                    console.log('offsetHeightCalc value is ' + offsetHeightCalc);

                    const mainMenuNavContainer = document.querySelector('header > * nav[role=navigation]');
                    //const mainMenuNavContainer = document.getElementById('block-customsolent-mainnavigation');

                    mainMenuNavContainer.setAttribute("style", "height: " + offsetHeightCalc + "px");
                  }
                  else {
                    /* closing submenu */
                    if (aSubMenu.classList.contains("visible")) {
                      aSubMenu.classList.remove("visible");
                      aSubMenu.classList.add("hidden");
                      aSubMenu.setAttribute("style", "top: 30px");


                      /* https://stackoverflow.com/a/15615701/227926 */

                      var offsetHeight = aSubMenu.offsetHeight;

                      console.log('offsetHeight is of type' + typeof offsetHeight);
                      console.log('offsetHeight value is ' + offsetHeight);

                      /* var offsetHeightCalc = parseInt(offsetHeight.value) + 100; */

                      var offsetHeightCalc = parseInt(offsetHeight) - 100;

                      console.log('offsetHeightCalc is of type ' + typeof offsetHeightCalc);
                      console.log('offsetHeightCalc value is ' + offsetHeightCalc);

                      const mainMenuNavContainer = document.getElementById('block-customsolent-mainnavigation');

                      const subMenuHeight = "128px";
                      mainMenuNavContainer.setAttribute("style", "height:" + subMenuHeight);
                    }
                  }




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

