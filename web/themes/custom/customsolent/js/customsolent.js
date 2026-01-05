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

        // Desktop menu button handlers
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

        // Mobile burger menu setup
        setupMobileBurgerMenu();
      });

      /**
       * Setup mobile burger menu
       */
      function setupMobileBurgerMenu() {
        // Create burger menu HTML - using same structure as Gospel Choir site
        var menuicon = 
          '<a href="#" id="slnt-togl-expand" class="slnt-togl-expand"> \
            <div class="menu-icon"> \
              <span class="top"></span> \
              <span class="middle"></span> \
              <span class="bottom"></span> \
            </div> \
            <div class="menu-text"> \
              <span class="icon-burger">MENU</span> \
              <span class="icon-close">CLOSE</span> \
            </div> \
          </a>';

        // Insert burger menu if in mobile mode and not already present
        if (isMobile() && $('#slnt-mobile-menu-container').is(':empty')) {
          $("#slnt-mobile-menu-container").append(menuicon);
        }

        // Burger menu click handler - adapted from Gospel Choir site
        $('#slnt-togl-expand')
          .off('.toglexpandns')
          .on({
            'click.toglexpandns': function(e) {
              e.stopPropagation();
              e.preventDefault();
              
              if (isMobile()) {
                var togl_expd = document.getElementById('slnt-togl-expand');
                togl_expd.classList.toggle('slnt-togl-expand--open');

                if ($("nav[role=navigation]").css("display") == "none" || 
                    $(".main-menu-wrap").css("display") == "none") {
                  // Show menu
                  $("body").css("overflow", "hidden");
                  $("body").addClass('slnt-overlay-menu-bg');
                  $("nav[role=navigation]").css("display", "block");
                  $(".main-menu-wrap").css("display", "block");
                  $("#slnt-header").addClass('slnt-overlay-hdr-hgt-togl-expand-mob-menu');
                  $("#slnt-header").removeClass('slnt-hdr-hgt-init');
                } else {
                  // Hide menu
                  $("body").css("overflow", "");
                  $("body").removeClass('slnt-overlay-menu-bg');
                  $("nav[role=navigation]").css("display", "none");
                  $(".main-menu-wrap").css("display", "none");
                  $("#slnt-header").removeClass('slnt-overlay-hdr-hgt-togl-expand-mob-menu');
                  $("#slnt-header").addClass('slnt-hdr-hgt-init');
                }
              }
            }
          });
      }

      /**
       * Initialize menu on page load
       */
      function initializeMenu() {
        currentMode = isMobile() ? 'mobile' : 'desktop';
        
        if (currentMode === 'desktop') {
          desktop_menu_initialise_container_height();
          desktop_menu_hide_all_submenus();
        } else {
          // Mobile mode - hide menu initially and add initial header height class
          $("nav[role=navigation]").css("display", "none");
          $(".main-menu-wrap").css("display", "none");
          $("#slnt-header").addClass('slnt-hdr-hgt-init');
        }
      }

      /**
       * Unified transition end handler
       */
      function handleTransitionEnd(event, aSubMenu, isShowing) {
        console.log('Transition ended:', {isShowing, target: event.target});
        
        if (isShowing) {
          // After showing animation completes, set z-index to 0 for clickable links
          console.log('Setting z-index to 0');
          event.target.style.setProperty("z-index", "0");
        } else {
          // After hiding animation completes
          event.target.style.setProperty("visibility", "hidden");
          event.target.style.setProperty("opacity", "0");
          // z-index is already -1, set at start of hide animation
          console.log('Hide animation complete, z-index already -1');
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

        aSubMenu.classList.remove("hidden-2l");
        aSubMenu.classList.add("visible-2l");

        if (!isMobile()) {
          // Desktop behavior
          // Position the submenu
          aSubMenu.style.setProperty("top", submenu_desktop_top_reveal);
          
          // Start with z-index -1 during animation
          aSubMenu.style.setProperty("z-index", "-1");
          
          // Make visible first
          aSubMenu.style.setProperty("visibility", "visible");
          
          const mainMenuNavContainer = get_mainMenuNavContainer();
          
          // Start with opacity 0
          aSubMenu.style.setProperty("opacity", "0");
          
          // Force reflow to ensure changes are applied
          void aSubMenu.offsetHeight;
          
          // Calculate and set new height, then fade in
          // Use requestAnimationFrame to ensure the height change triggers transition
          requestAnimationFrame(() => {
            desktop_menu_drawer_show(aSubMenu, mainMenuNavContainer);
            
            // Small delay to ensure height is set before opacity change
            requestAnimationFrame(() => {
              aSubMenu.style.setProperty("opacity", "1");
            });
          });
          
          // Add transition listener for cleanup - will set z-index to 0 when done
          addTransitionListeners(aSubMenu, true);
        } else {
          // Mobile behavior - make submenu scrollable with calculated height
          const maxHeight = calculateMobileSubmenuHeight(aSubMenu);
          
          aSubMenu.style.setProperty("display", "block");
          aSubMenu.style.setProperty("width", "100%");
          aSubMenu.style.setProperty("max-height", maxHeight + "px");
          aSubMenu.style.setProperty("overflow-y", "auto");
          aSubMenu.style.setProperty("overflow-x", "hidden");
          aSubMenu.style.setProperty("-webkit-overflow-scrolling", "touch");
          
          // Make the LI items inside full width too
          const subMenuItems = aSubMenu.querySelectorAll('li');
          subMenuItems.forEach(li => {
            li.style.setProperty("width", "100%");
          });
          
          // Add scroll event listener for fade gradients
          // Use setTimeout to ensure layout has settled before checking overflow
          setTimeout(() => {
            setupMobileScrollFade(aSubMenu);
          }, 50);
        }
      }

      /**
       * Calculate appropriate height for mobile submenu
       * Screen height - logo height - other top-level menu items height
       */
      function calculateMobileSubmenuHeight(aSubMenu) {
        // Get viewport height
        const viewportHeight = window.innerHeight;
        
        // Get logo height
        const logoHeight = $("#slnt-logo").outerHeight(true) || 0;
        
        // Get the parent li element of this submenu
        const parentLi = aSubMenu.closest('li');
        
        // Calculate height of all top-level menu item LI elements
        let otherMenuItemsHeight = 0;
        const allMenuItems = document.querySelectorAll('.main-menu-item-container > li');
        
        allMenuItems.forEach(item => {
          // Get just the LI height (including its button, but not counting expanded submenus)
          const itemClone = $(item).clone();
          // Remove any open submenus from the clone to get just the button height
          itemClone.find('.sub-menu-container').remove();
          
          // Create a temporary element to measure
          const tempDiv = $('<div>').css({
            position: 'absolute',
            visibility: 'hidden',
            display: 'block'
          }).append(itemClone);
          
          $('body').append(tempDiv);
          const itemHeight = tempDiv.find('li').outerHeight(true) || 0;
          tempDiv.remove();
          
          otherMenuItemsHeight += itemHeight;
        });
        
        // Larger buffer for breathing room at bottom (aesthetic spacing)
        const buffer = 60;
        
        // Calculate available height
        const availableHeight = viewportHeight - logoHeight - otherMenuItemsHeight - buffer;
        
        // Use 85% of available height for a nice balance
        // This leaves 15% as margin/breathing room
        const targetHeight = availableHeight * 0.85;
        
        // Use at least 150px, but prefer the calculated target
        const calculatedHeight = Math.max(150, targetHeight);
        
        console.log('Mobile submenu height calculation:', {
          viewportHeight,
          logoHeight,
          otherMenuItemsHeight,
          buffer,
          availableHeight,
          targetHeight,
          calculatedHeight,
          percentageUsed: ((calculatedHeight / availableHeight) * 100).toFixed(1) + '%'
        });
        
        return calculatedHeight;
      }

      /**
       * Hide a submenu with optional instant mode (no animation)
       */
      function hideSubmenu(aSubMenu, instant = false) {
        removeTransitionListeners(aSubMenu);
        
        aSubMenu.classList.add("hidden-2l");
        aSubMenu.classList.remove("visible-2l");
        
        if (!isMobile()) {
          // Desktop behavior
          // Reset nav container height when hiding submenu
          const mainMenuNavContainer = get_mainMenuNavContainer();
          mainMenuNavContainer.style.setProperty("height", menu_bar_height);
          
          // Set z-index to -1 BEFORE starting fade out animation
          // This way the menu goes behind content during the fade
          aSubMenu.style.setProperty("z-index", "-1");
          
          if (instant) {
            // Instant hide: set properties immediately
            aSubMenu.style.setProperty("visibility", "hidden");
            aSubMenu.style.setProperty("opacity", "0");
          } else {
            // Animated hide: fade out
            addTransitionListeners(aSubMenu, false);
            aSubMenu.style.setProperty("opacity", "0");
            
            // After fade completes, hide visibility
            setTimeout(() => {
              aSubMenu.style.setProperty("visibility", "hidden");
            }, 500); // Match your CSS transition duration
          }
        } else {
          // Mobile behavior - hide submenu
          aSubMenu.style.setProperty("display", "none");
          aSubMenu.style.removeProperty("width");
          aSubMenu.style.removeProperty("max-height");
          aSubMenu.style.removeProperty("overflow-y");
          aSubMenu.style.removeProperty("overflow-x");
          aSubMenu.style.removeProperty("-webkit-overflow-scrolling");
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
          
          const mainMenuNavContainer = get_mainMenuNavContainer();
          
          // Clean up all submenus on mode change
          const allSubMenus = document.querySelectorAll('.sub-menu-container');
          
          if (newMode === 'mobile') {
            // Going to mobile: remove all inline styles and show burger menu
            allSubMenus.forEach(aSubMenu => {
              removeTransitionListeners(aSubMenu);
              
              const isOpen = aSubMenu.classList.contains("visible-2l");
              
              // Clear desktop styles
              aSubMenu.removeAttribute('style');
              
              // If submenu was open in desktop, apply mobile scrollable styles
              if (isOpen) {
                const maxHeight = calculateMobileSubmenuHeight(aSubMenu);
                
                aSubMenu.style.setProperty("display", "block");
                aSubMenu.style.setProperty("width", "100%");
                aSubMenu.style.setProperty("max-height", maxHeight + "px");
                aSubMenu.style.setProperty("overflow-y", "auto");
                aSubMenu.style.setProperty("overflow-x", "hidden");
                aSubMenu.style.setProperty("-webkit-overflow-scrolling", "touch");
                
                // Setup scroll fades after layout settles
                setTimeout(() => {
                  setupMobileScrollFade(aSubMenu);
                }, 50);
              }
            });
            mainMenuNavContainer.style.setProperty("height", "auto");
            
            // Add burger menu
            if ($('#slnt-mobile-menu-container').is(':empty')) {
              setupMobileBurgerMenu();
            }
            
            // Hide main menu initially in mobile
            $("nav[role=navigation]").css("display", "none");
            $(".main-menu-wrap").css("display", "none");
            $("#slnt-header").addClass('slnt-hdr-hgt-init');
            
            // Reset mobile overlay if it was on
            $("body").css("overflow", "");
            $("body").removeClass('slnt-overlay-menu-bg');
            
          } else {
            // Going to desktop: disable transitions FIRST, then set all properties
            mainMenuNavContainer.classList.remove('animation');
            
            // Remove burger menu and reset mobile styles
            $("#slnt-mobile-menu-container").empty();
            $("#slnt-header").removeClass('slnt-overlay-hdr-hgt-togl-expand-mob-menu');
            $("#slnt-header").removeClass('slnt-hdr-hgt-init');
            
            // Reset mobile overlay styles
            $("body").css("overflow", "");
            $("body").removeClass('slnt-overlay-menu-bg');
            $("nav[role=navigation]").css("display", "");
            $(".main-menu-wrap").css("display", "");
            
            allSubMenus.forEach(aSubMenu => {
              removeTransitionListeners(aSubMenu);
              aSubMenu.classList.remove('animated');
              
              const isOpen = aSubMenu.classList.contains("visible-2l");
              
              // Clear all mobile inline styles first
              aSubMenu.removeAttribute('style');
              
              // Then apply desktop styles
              if (isOpen) {
                // Keep it open but set position and visibility without animating
                aSubMenu.style.setProperty("top", submenu_desktop_top_reveal);
                aSubMenu.style.setProperty("visibility", "visible");
                aSubMenu.style.setProperty("opacity", "1");
                
                // Set nav height instantly for open submenu
                const offsetHeight = aSubMenu.offsetHeight;
                const desktop_offset_height = get_desktop_offset_height();
                const offsetHeightCalc = parseInt(offsetHeight) + desktop_offset_height + 16;
                mainMenuNavContainer.style.setProperty("height", offsetHeightCalc + "px");
              } else {
                // Hide it instantly with desktop styles
                aSubMenu.style.setProperty("visibility", "hidden");
                aSubMenu.style.setProperty("opacity", "0");
              }
            });
            
            // Set default height if no menus open
            if (!check_submenu_open()) {
              mainMenuNavContainer.style.setProperty("height", menu_bar_height);
            }
            
            // Force reflow to ensure all changes are applied
            void mainMenuNavContainer.offsetHeight;
            
            // Re-enable animation classes after changes are complete
            requestAnimationFrame(() => {
              mainMenuNavContainer.classList.add('animation');
              allSubMenus.forEach(aSubMenu => {
                aSubMenu.classList.add('animated');
              });
            });
          }
          
          currentMode = newMode;
        } else {
          // No mode change, just resize within same mode
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
            mainMenuNavContainer.style.setProperty("height", "auto");
          }
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
        const desktop_offset_height = get_desktop_offset_height();
        const offsetHeightCalc = parseInt(offsetHeight) + desktop_offset_height + 16;

        console.log('Setting nav height to:', offsetHeightCalc + 'px');
        mainMenuNavContainer.style.setProperty("height", offsetHeightCalc + "px");
      }

      function get_mainMenuNavContainer() {
        return document.querySelector('header > * nav[role=navigation]');
      }

      function desktop_menu_initialise_container_height() {
        const mainMenuNavContainer = get_mainMenuNavContainer();
        mainMenuNavContainer.style.setProperty("height", menu_bar_height);
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

      /**
       * Setup scroll fade indicators for mobile submenus
       */
      function setupMobileScrollFade(aSubMenu) {
        // Remove any existing scroll handler
        $(aSubMenu).off('scroll.mobilefade');
        
        // Check initial scroll position and overflow
        updateScrollFadeClasses(aSubMenu);
        
        // Add scroll event handler
        $(aSubMenu).on('scroll.mobilefade', function() {
          updateScrollFadeClasses(this);
        });
      }

      /**
       * Update CSS classes based on scroll position for fade effects
       */
      function updateScrollFadeClasses(element) {
        const scrollTop = element.scrollTop;
        const scrollHeight = element.scrollHeight;
        const clientHeight = element.clientHeight;
        const scrollBottom = scrollHeight - scrollTop - clientHeight;
        
        // Check if content actually overflows (is scrollable)
        const hasOverflow = scrollHeight > clientHeight;
        
        console.log('Scroll fade check:', {
          scrollHeight,
          clientHeight,
          hasOverflow,
          scrollTop,
          scrollBottom
        });
        
        if (hasOverflow) {
          element.classList.add('has-overflow');
        } else {
          element.classList.remove('has-overflow');
        }
        
        // Add 'scrolled-down' class if scrolled from top (show top fade)
        if (scrollTop > 10) {
          element.classList.add('scrolled-down');
        } else {
          element.classList.remove('scrolled-down');
        }
        
        // Add 'scrolled-to-bottom' class if at bottom (hide bottom fade)
        if (scrollBottom < 10) {
          element.classList.add('scrolled-to-bottom');
        } else {
          element.classList.remove('scrolled-to-bottom');
        }
      }
    }
  };

}(jQuery, Drupal));