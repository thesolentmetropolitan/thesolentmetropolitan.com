/**
 * @file
 * customsolent behaviors.
 */
(function ($, Drupal) {
  'use strict';
  Drupal.behaviors.customsolent = {
    attach: function (context, settings) {

      /*
        desktop height constants
      */
      const submenu_desktop_hide_position = "110px";
      const submenu_desktop_hide_position_top = "-60px";
      const desktop_offset_height = 100;
      const css_breakpoint_mobile = 991;

      $(document).ready(function () {

        $(window).resize(menu_refreshSize);
        const allSubMenus = document.querySelectorAll('.sub-menu-container');
        allSubMenus.forEach(aSubMenu => {
          submenu_hide(aSubMenu);
        }
        );

        const mainMenuTopLevelButtons = document.querySelectorAll('.main-menu-item-container > * button');

        mainMenuTopLevelButtons.forEach(topLevelButton => {
          topLevelButton.addEventListener('click', function (e) {
            console.log('main menu item clicked');
            // Find the submenu within the clicked item

            const subMenuContainerForClickedButton = topLevelButton.parentElement.querySelector('.sub-menu-container');

            if (subMenuContainerForClickedButton) {
              const allSubMenuContainers = document.querySelectorAll('.main-menu-item-container > * .sub-menu-container');

              allSubMenuContainers.forEach(aSubMenu => {

                if (aSubMenu.isEqualNode(subMenuContainerForClickedButton)) {
                  e.stopPropagation();
                  e.preventDefault();

                  console.log('found the clicked submenu');
                  console.log("search for current - submenu is of type " + typeof aSubMenu);

                  const primaryMenuContainerAsId = document.getElementById("sub-menu-item-container-ul");

                  var primaryMenuContainerStyle = window.getComputedStyle(primaryMenuContainerAsId);
                  var primaryMenuContainerCssDisplay = primaryMenuContainerStyle.getPropertyValue('display');
                  console.log(primaryMenuContainerCssDisplay);

                  if (isMobile()) {
                    console.log('mobile mode');
                    if (aSubMenu.classList.contains("hidden-2l")) {
                      submenu_show(aSubMenu);
                    }
                    else {
                      if (aSubMenu.classList.contains("visible-2l")) {
                        submenu_hide(aSubMenu);
                      }
                    }
                  }
                  else {
                    console.log('desktop mode');

                    const mainMenuNavContainer = document.querySelector('header > * nav[role=navigation]');
                    if (aSubMenu.classList.contains("hidden-2l")) {
                      submenu_show(aSubMenu);

                      console.log("aSubMenu is of type " + typeof aSubMenu);

                      aSubMenu.style.setProperty("top", submenu_desktop_hide_position);

                      /* https://www.reddit.com/r/webdev/comments/n3fijk/change_zindex_of_transitioning_element/ */
                      /* https://jonsuh.com/blog/detect-the-end-of-css-animations-and-transitions-with-javascript/ */

                      /* https://stackoverflow.com/a/15615701/227926 */

                      var offsetHeight = aSubMenu.offsetHeight;

                      console.log('offsetHeight is of type' + typeof offsetHeight);
                      console.log('offsetHeight value is ' + offsetHeight);

                      var offsetHeightCalc = parseInt(offsetHeight) + desktop_offset_height;

                      console.log('offsetHeightCalc is of type ' + typeof offsetHeightCalc);
                      console.log('offsetHeightCalc value is ' + offsetHeightCalc);

                      mainMenuNavContainer.setAttribute("style", "height: " + offsetHeightCalc + "px");
                    }
                    else {
                      if (aSubMenu.classList.contains("visible-2l")) {
                        submenu_hide(aSubMenu);

                        /* https://stackoverflow.com/a/15615701/227926 */

                        var offsetHeight = aSubMenu.offsetHeight;

                        console.log('offsetHeight is of type' + typeof offsetHeight);
                        console.log('offsetHeight value is ' + offsetHeight);

                        var offsetHeightCalc = parseInt(offsetHeight) - desktop_offset_height;

                        console.log('offsetHeightCalc is of type ' + typeof offsetHeightCalc);
                        console.log('offsetHeightCalc value is ' + offsetHeightCalc);

                        /*const mainMenuNavContainer = document.getElementById('block-customsolent-mainnavigation');*/

                        mainMenuNavContainer.setAttribute("style", "height: " + "90" + "px");
                        /* if not mobile */
                        if (!isMobile()) {
                          //mainMenuNavContainer.setAttribute("style", "height: " + "90" + "px");
                          /*
                          const subMenuHeight = "204px";
                          mainMenuNavContainer.setAttribute("style", "height:" + subMenuHeight);
                          */
                        }
                      }
                    } /* desktop hide-show toggle */
                  } /* detect mobile width */
                }
                else {
                  submenu_hide(aSubMenu);
                }
              });
              /* https://stackoverflow.com/a/59906259/227926 */
            }
          });
        });
      });

      function submenu_show(aSubMenu) {
        aSubMenu.removeEventListener('webkitTransitionEnd', transitionEndHandlerFunc_submenu_hide, false);
        aSubMenu.removeEventListener('otransitionend', transitionEndHandlerFunc_submenu_hide, false);
        aSubMenu.removeEventListener('oTransitionEnd', transitionEndHandlerFunc_submenu_hide, false);
        aSubMenu.removeEventListener('msTransitionEnd', transitionEndHandlerFunc_submenu_hide, false);
        aSubMenu.removeEventListener('transitionend', transitionEndHandlerFunc_submenu_hide, false);

        aSubMenu.addEventListener('webkitTransitionEnd', transitionEndHandlerFunc_submenu_show, false);
        aSubMenu.addEventListener('otransitionend', transitionEndHandlerFunc_submenu_show, false);
        aSubMenu.addEventListener('oTransitionEnd', transitionEndHandlerFunc_submenu_show, false);
        aSubMenu.addEventListener('msTransitionEnd', transitionEndHandlerFunc_submenu_show, false);
        aSubMenu.addEventListener('transitionend', transitionEndHandlerFunc_submenu_show, false);

        aSubMenu.classList.remove("hidden-2l");
        aSubMenu.classList.add("visible-2l");
      }


      function transitionEndHandlerFunc_submenu_show(event) {
        /* https://stackoverflow.com/a/68839284/227926 */
        event.target.style.setProperty("z-index", "0");
      }


      function submenu_hide(aSubMenu) {
        aSubMenu.removeEventListener('webkitTransitionEnd', transitionEndHandlerFunc_submenu_show, false);
        aSubMenu.removeEventListener('otransitionend', transitionEndHandlerFunc_submenu_show, false);
        aSubMenu.removeEventListener('oTransitionEnd', transitionEndHandlerFunc_submenu_show, false);
        aSubMenu.removeEventListener('msTransitionEnd', transitionEndHandlerFunc_submenu_show, false);
        aSubMenu.removeEventListener('transitionend', transitionEndHandlerFunc_submenu_show, false);

        aSubMenu.addEventListener('webkitTransitionEnd', transitionEndHandlerFunc_submenu_hide, false);
        aSubMenu.addEventListener('otransitionend', transitionEndHandlerFunc_submenu_hide, false);
        aSubMenu.addEventListener('oTransitionEnd', transitionEndHandlerFunc_submenu_hide, false);
        aSubMenu.addEventListener('msTransitionEnd', transitionEndHandlerFunc_submenu_hide, false);
        aSubMenu.addEventListener('transitionend', transitionEndHandlerFunc_submenu_hide, false);

        aSubMenu.classList.add("hidden-2l");
        aSubMenu.classList.remove("visible-2l");
        aSubMenu.setAttribute("style", "top: " + submenu_desktop_hide_position_top); /* top hidden position */
      }


      function transitionEndHandlerFunc_submenu_hide(event) {
        /* aSubMenu.setAttribute("style", "z-index: 0"); */
        /* https://stackoverflow.com/a/68839284/227926 */
        event.target.style.setProperty("z-index", "-1");
      }


      function menu_refreshSize() {
        //var mainMenuNavContainer = document.querySelector('header > * nav[role=navigation]');
        if (!isMobile()) {
          console.log( ' desktop');
        }
        else {
          console.log(' mobile');
          mainMenuNavContainer.setAttribute("style", "height: auto");
        }
      }


      function isMobile() {
        /*
        var primaryMenuContainerAsId = document.getElementById("sub-menu-item-container-ul");
        var primaryMenuContainerStyle = window.getComputedStyle(primaryMenuContainerAsId);
        var primaryMenuContainerCssDisplay = primaryMenuContainerStyle.getPropertyValue('display');
        var isMobileFlag = false;
        if (primaryMenuContainerCssDisplay == "block") {
          isMobileFlag = true;
        }
        else {
          isMobileFlag = false;
        }
        return isMobileFlag;
    
        */

        // still needs to be fixed but this will help: https://stackoverflow.com/questions/6850164/get-the-device-width-in-javascript

        if (screen.width <= css_breakpoint_mobile) {
          isMobileFlag = true;
                    console.log( ' mobile');
        }
        else {
          isMobileFlag = false;
          console.log( ' desktop');
        }
        return isMobileFlag;
      }
    }
  };

}(jQuery, Drupal));

