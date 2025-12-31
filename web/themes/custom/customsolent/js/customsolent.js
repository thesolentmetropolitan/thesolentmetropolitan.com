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
      const menu_bar_height = "96px";
      
      let currentMode = null; // Track current mode to detect changes

      $(document).ready(function () {
        initializeMenu();
        $(window).resize(menu_refreshSize);

        const mainMenuTopLevelButtons = document.querySelectorAll('.main-menu-item-container > * button');

        mainMenuTopLevelButtons.forEach(topLevelButton => {
          topLevelButton.addEventListener('click', function (e) {
            const subMenuContainerForClickedButton = topLevelButton.parentElement.querySelector('.sub-menu-container');

            if (subMenuContainerForClickedButton) {
              e.stopPropagation();
              e.preventDefault();

              const allSubMenuContainers = document.querySelectorAll('.main-menu-item-container > * .sub-menu-container');

              allSubMenuContainers.forEach(aSubMenu => {
                if (aSubMenu.isEqualNode(subMenuContainerForClickedButton)) {
                  // Toggle the clicked submenu
                  toggleSubmenu(aSubMenu);
                } else {
                  // Hide all other submenus
                  hideSubmenu(aSubMenu, true); // true = instant, no animation
                }
              });
            }
          });
        });
      });

      /**
       * Initialize menu on page load
       */
      function initializeMenu() {
        currentMode = isMobile() ? 'mobile' : 'desktop';
        
        if (currentMode === 'desktop') {
          desktop_menu_initialise_container_height();
          desktop_menu_hide_all_submenus();
        }
      }

      /**
       * Unified transition end handler
       */
      function handleTransitionEnd(event, aSubMenu, isShowing) {
        if (isShowing) {
          event.target.style.setProperty("z-index", "0");
        } else {
          event.target.style.setProperty("z-index", "-1");
        }
        // Clean up listener after it fires
        removeTransitionListeners(aSubMenu);
      }

      /**
       * Add transition listeners for a submenu
       */
      function addTransitionListeners(aSubMenu, isShowing) {
        const handler = (e) => handleTransitionEnd(e, aSubMenu, isShowing);
        
        // Store handler reference so we can remove it later
        aSubMenu._transitionHandler = handler;
        
        aSubMenu.addEventListener('webkitTransitionEnd', handler, false);
        aSubMenu.addEventListener('otransitionend', handler, false);
        aSubMenu.addEventListener('oTransitionEnd', handler, false);
        aSubMenu.addEventListener('msTransitionEnd', handler, false);
        aSubMenu.addEventListener('transitionend', handler, false);
      }

      /**
       * Remove all transition listeners from a submenu
       */
      function removeTransitionListeners(aSubMenu) {
        if (aSubMenu._transitionHandler) {
          aSubMenu.removeEventListener('webkitTransitionEnd', aSubMenu._transitionHandler, false);
          aSubMenu.removeEventListener('otransitionend', aSubMenu._transitionHandler, false);
          aSubMenu.removeEventListener('oTransitionEnd', aSubMenu._transitionHandler, false);
          aSubMenu.removeEventListener('msTransitionEnd', aSubMenu._transitionHandler, false);
          aSubMenu.removeEventListener('transitionend', aSubMenu._transitionHandler, false);
          delete aSubMenu._transitionHandler;
        }
      }

      /**
       * Toggle submenu between shown and hidden states
       */
      function toggleSubmenu(aSubMenu) {
        const isCurrentlyHidden = aSubMenu.classList.contains("hidden-2l");
        
        if (isCurrentlyHidden) {
          showSubmenu(aSubMenu);
        } else {
          hideSubmenu(aSubMenu);
        }
      }

      /**
       * Show a submenu with animation
       */
      function showSubmenu(aSubMenu) {
        removeTransitionListeners(aSubMenu);
        addTransitionListeners(aSubMenu, true);

        aSubMenu.classList.remove("hidden-2l");
        aSubMenu.classList.add("visible-2l");

        if (!isMobile()) {
          aSubMenu.style.setProperty("top", submenu_desktop_top_reveal);
          
          const mainMenuNavContainer = get_mainMenuNavContainer();
          desktop_menu_drawer_show(aSubMenu, mainMenuNavContainer);
        }
      }

      /**
       * Hide a submenu with optional instant mode (no animation)
       */
      function hideSubmenu(aSubMenu, instant = false) {
        removeTransitionListeners(aSubMenu);
        
        if (!instant) {
          addTransitionListeners(aSubMenu, false);
        } else {
          // Instant hide: set z-index immediately
          aSubMenu.style.setProperty("z-index", "-1");
        }

        aSubMenu.classList.add("hidden-2l");
        aSubMenu.classList.remove("visible-2l");
        
        if (!isMobile()) {
          aSubMenu.setAttribute("style", "top: " + get_submenu_desktop_top_hide(aSubMenu));
        }
      }

      /**
       * Handle window resize - detect mode changes and clean up
       */
      function menu_refreshSize() {
        const newMode = isMobile() ? 'mobile' : 'desktop';
        const modeChanged = (currentMode !== newMode);
        
        if (modeChanged) {
          console.log('Mode changed from', currentMode, 'to', newMode);
          
          // Clean up all submenus on mode change
          const allSubMenus = document.querySelectorAll('.sub-menu-container');
          allSubMenus.forEach(aSubMenu => {
            removeTransitionListeners(aSubMenu);
            
            const isOpen = aSubMenu.classList.contains("visible-2l");
            
            if (newMode === 'mobile') {
              // Remove inline styles for mobile
              aSubMenu.removeAttribute('style');
            } else {
              // Desktop mode: set position instantly without animation
              if (isOpen) {
                // Keep it open but set position without animating
                aSubMenu.style.setProperty("top", submenu_desktop_top_reveal);
                aSubMenu.style.setProperty("z-index", "0");
              } else {
                // Hide it instantly
                hideSubmenu(aSubMenu, true);
              }
            }
          });
          
          currentMode = newMode;
        }

        const mainMenuNavContainer = get_mainMenuNavContainer();
        
        if (newMode === 'desktop') {
          if (!check_submenu_open()) {
            desktop_menu_initialise_container_height();
          } else {
            const aSubMenu = document.querySelector(".sub-menu-container.visible-2l");
            if (aSubMenu) {
              desktop_menu_drawer_show(aSubMenu, mainMenuNavContainer);
            }
          }
        } else {
          mainMenuNavContainer.setAttribute("style", "height: auto");
        }
      }

      /**
       * Detect if we're in mobile mode
       * @returns {boolean} true if mobile
       */
      function isMobile() {
        const isMobileFlag = $(".main-menu-item-container li").css("display") !== "inline-block";
        
        if (!isMobileFlag) {
          if (!$(".main-menu-item-container").hasClass("desktop")) {
            $(".main-menu-item-container").addClass("desktop");
          }
          $(".main-menu-item-container").removeClass("mobile");
        } else {
          if (!$(".main-menu-item-container").hasClass("mobile")) {
            $(".main-menu-item-container").addClass("mobile");
          }
          $(".main-menu-item-container").removeClass("desktop");
        }
        
        return isMobileFlag;
      }

      function get_desktop_offset_height() {
        return 100;
      }

      function desktop_menu_drawer_show(aSubMenu, mainMenuNavContainer) {
        const offsetHeight = aSubMenu.offsetHeight;
        aSubMenu.setAttribute("style", "top: " + submenu_desktop_top_reveal);

        const desktop_offset_height = get_desktop_offset_height();
        const offsetHeightCalc = parseInt(offsetHeight) + desktop_offset_height + 16;

        mainMenuNavContainer.setAttribute("style", "height: " + offsetHeightCalc + "px");
      }

      function get_submenu_desktop_top_hide(aSubMenu) {
        const offsetHeight = aSubMenu.offsetHeight;
        const topval = -270 + offsetHeight;
        return parseInt(topval) + "px";
      }

      function get_mainMenuNavContainer() {
        return document.querySelector('header > * nav[role=navigation]');
      }

      function desktop_menu_initialise_container_height() {
        const mainMenuNavContainer = get_mainMenuNavContainer();
        mainMenuNavContainer.setAttribute("style", "height: " + menu_bar_height);
      }

      function check_submenu_open() {
        const allSubMenuContainers = document.querySelectorAll('.main-menu-item-container > * .sub-menu-container');
        
        for (let aSubMenu of allSubMenuContainers) {
          if (aSubMenu.classList.contains("visible-2l")) {
            return true;
          }
        }
        return false;
      }

      function desktop_menu_hide_all_submenus() {
        const allSubMenus = document.querySelectorAll('.sub-menu-container');
        allSubMenus.forEach(aSubMenu => {
          hideSubmenu(aSubMenu, true); // Instant hide on init
        });
      }
    }
  };

}(jQuery, Drupal));