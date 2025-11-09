/**
 * @file
 * customsolent behaviors.
 */
(function ($, Drupal) {
  'use strict';
  Drupal.behaviors.customsolent = {
    attach: function (context, settings) {
      /* desktop height constants */
      const submenu_desktop_top_reveal = "110px";
      //const submenu_desktop_top_hide = get_submenu_desktop_top_hide(aSubMenu); /* TODO this needs to be calculated individually for each submenu */
      //const desktop_offset_height = get_desktop_offset_height();

      $(document).ready(function () {

        if (!isMobile()) {
          desktop_menu_initialise_container_height();

          desktop_menu_hide_all_submenus();
        }


        $(window).resize(menu_refreshSize);

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

                    var mainMenuNavContainer = get_mainMenuNavContainer();
                    if (aSubMenu.classList.contains("hidden-2l")) {
                      submenu_show(aSubMenu);

                      console.log("aSubMenu is of type " + typeof aSubMenu);

                      aSubMenu.style.setProperty("top", submenu_desktop_top_reveal);

                      /* https://www.reddit.com/r/webdev/comments/n3fijk/change_zindex_of_transitioning_element/ */
                      /* https://jonsuh.com/blog/detect-the-end-of-css-animations-and-transitions-with-javascript/ */
                      /* https://stackoverflow.com/a/15615701/227926 */

                      desktop_menu_drawer_show(aSubMenu, mainMenuNavContainer);
                    }
                    else {
                      if (aSubMenu.classList.contains("visible-2l")) {
                        submenu_hide(aSubMenu);

                        mainMenuNavContainer.setAttribute("style", "height: " + "96px"); /* menu_bar_height */
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
        aSubMenu.setAttribute("style", "top: " + get_submenu_desktop_top_hide(aSubMenu)); /* top hidden position */
      }


      function transitionEndHandlerFunc_submenu_hide(event) {
        /* https://stackoverflow.com/a/68839284/227926 */
        event.target.style.setProperty("z-index", "-1");
      }


      function menu_refreshSize() {
        var mainMenuNavContainer = get_mainMenuNavContainer();
        if (!isMobile()) {
          /* https://stackoverflow.com/a/15615701/227926 */

          if (!check_submenu_open()) {
            desktop_menu_initialise_container_height();

            desktop_menu_hide_all_submenus();
          }
          else {
            console.log(' desktop');
            /* get the visible menu element */
            var aSubMenu = document.querySelector(".sub-menu-container.visible-2l");
            if (aSubMenu) {
              desktop_menu_drawer_show(aSubMenu, mainMenuNavContainer);
            }
            else {
              aSubMenu = document.querySelector(".sub-menu-container");
            }
          }
        }
        else {
          console.log(' mobile');
          mainMenuNavContainer.setAttribute("style", "height: auto");
        }
      }


      function isMobile() {
        var isMobileFlag = false;
        /* if ($(".main-menu-item-container").css("column-count") > 1) { */
        if ($(".main-menu-item-container li ").css("display") == "inline-block") {
          isMobileFlag = false;
          console.log(' desktop ');
          $(".main-menu-item-container").removeClass("mobile");
        }
        else {
          isMobileFlag = true;
          console.log(' mobile ');
          $(".main-menu-item-container").addClass("mobile");
        }
        return isMobileFlag;
      }

      function get_desktop_offset_height() {
        return 100;
      }

      function desktop_menu_drawer_show(aSubMenu, mainMenuNavContainer) {
        // https://www.w3schools.com/Jsref/prop_element_offsetheight.asp
        var offsetHeight = aSubMenu.offsetHeight;

        var topValue = aSubMenu.style.getPropertyValue("top");

        //if (topValue == "-60px") {
        var calcTopValue = get_submenu_desktop_top_hide(aSubMenu);
       // if (topValue == calcTopValue) {
          aSubMenu.setAttribute("style", "top: " + submenu_desktop_top_reveal);
       // }


        var desktop_offset_height = get_desktop_offset_height();

        console.log('offsetHeight is of type' + typeof offsetHeight);
        console.log('offsetHeight value is ' + offsetHeight);

        var offsetHeightCalc = parseInt(offsetHeight) + desktop_offset_height + 16;

        console.log('offsetHeightCalc is of type ' + typeof offsetHeightCalc);
        console.log('offsetHeightCalc value is ' + offsetHeightCalc);

        mainMenuNavContainer.setAttribute("style", "height: " + offsetHeightCalc + "px");
      }

      function get_submenu_desktop_top_hide(aSubMenu) {
        var offsetHeight = aSubMenu.offsetHeight;
        var topval = -270 + offsetHeight;
        var retval = parseInt(topval);
        return retval + "px";
        //return "-60px";
      }

      function get_mainMenuNavContainer() {
        var mainMenuNavContainer = document.querySelector('header > * nav[role=navigation]');
        return mainMenuNavContainer;
      }

      function desktop_menu_initialise_container_height() {
        var mainMenuNavContainer = get_mainMenuNavContainer();
        mainMenuNavContainer.setAttribute("style", "height: " + "96px"); /* menu_bar_height */
      }

      // let / var / const?

      function check_submenu_open() {
        const allSubMenuContainers = document.querySelectorAll('.main-menu-item-container > * .sub-menu-container');

        // https://stackoverflow.com/a/58194747/227926

        /*
        allSubMenuContainers.forEach(aSubMenu => {
          if (aSubMenu.classList.contains("visible-2l")) {

            return true;
          }
        }
        );
        */


        for (let aSubMenu of allSubMenuContainers) {
          if (aSubMenu.classList.contains("visible-2l")) {
            return true;
          }
        }


        return false;

        /* https://stackoverflow.com/a/48802390/227926 */
      }


      function desktop_menu_hide_all_submenus() {
        const allSubMenus = document.querySelectorAll('.sub-menu-container');
        allSubMenus.forEach(aSubMenu => {
          submenu_hide(aSubMenu);
        }
        );
      }
    }
  };

}(jQuery, Drupal));

/*

https://stackoverflow.com/questions/707565/how-do-you-add-css-with-javascript
https://www.w3schools.com/jquery/jquery_css.asp
https://stackoverflow.com/questions/507138/how-to-add-a-class-to-a-given-element
https://api.jquery.com/addClass/
https://stackoverflow.com/questions/15615552/get-div-height-with-plain-javascript/15615701#15615701
https://share.google/aimode/AwSaOjVxeyB5KzXQl

https://www.w3schools.com/js/js_break.asp
https://stackoverflow.com/questions/48802066/how-to-break-continue-across-nested-for-each-loops-in-typescript
https://stackoverflow.com/questions/58194616/how-to-check-if-an-element-class-child-has-a-specific-class
https://stackoverflow.com/questions/51782899/css-grid-variable-column-width-and-wrapping
https://www.w3schools.com/cssref/css_colors.php
https://stackoverflow.com/questions/61951047/how-do-i-draw-a-line-at-the-top-of-a-div-for-a-home-bar
*/