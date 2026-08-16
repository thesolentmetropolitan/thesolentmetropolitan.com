/**
 * @file
 * customsolent behaviors.
 */

/* 15 Jan 2026 - no reveal animation when switching between submenus */
(function ($, Drupal) {
  'use strict';
  Drupal.behaviors.customsolent = {
    attach: function (context, settings) {
      /* desktop height constants */
      //const submenu_desktop_top_reveal = "96px";
      const submenu_desktop_top_reveal = "48px";
      //const menu_bar_height = "96px";
      const menu_bar_height = "48px";
      const submenu_padding_top = 16; /* matches CSS padding-top on .sub-menu-container */
      
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

              // Check if any submenu is currently open (for switching detection)
              const anySubmenuCurrentlyOpen = check_submenu_open();
              // Check if the clicked submenu is the one that's open
              const clickedSubmenuIsOpen = subMenuContainerForClickedButton.classList.contains("visible-2l");
              // Determine if we're switching between submenus
              const switchingFromOther = anySubmenuCurrentlyOpen && !clickedSubmenuIsOpen;

              // When switching between submenus:
              // - Submenu text fades out, then new text fades in
              // - Nav height animates smoothly (so main position animates)

              if (switchingFromOther && !isMobile()) {
                // Find the currently open submenu
                const currentlyOpenSubmenu = document.querySelector('.sub-menu-container.visible-2l');

                if (currentlyOpenSubmenu) {
                  // Measure old submenu before any changes
                  const oldSubmenuHeight = currentlyOpenSubmenu.offsetHeight;
                  const finalTop = parseInt(window.getComputedStyle(currentlyOpenSubmenu).top);

                  // Step 1: Pre-hide new submenu text (before any animation starts)
                  subMenuContainerForClickedButton.classList.add('text-hidden');
                  // Force browser to apply the class
                  void subMenuContainerForClickedButton.offsetHeight;

                  // Step 2: Fade out old submenu text
                  currentlyOpenSubmenu.classList.add('text-hidden');

                  // Step 3: After text fades out, switch submenus with top slide animation
                  setTimeout(() => {
                    const newMenu = subMenuContainerForClickedButton;
                    const mainMenuNavContainer = get_mainMenuNavContainer();
                    const slntHeader = document.getElementById('slnt-header');

                    // Hide all other submenus instantly (skip height reset)
                    allSubMenuContainers.forEach(aSubMenu => {
                      if (aSubMenu.isEqualNode(newMenu)) {
                        toggleChevron(aSubMenu);
                      } else {
                        unselectChevron(aSubMenu);
                        hideSubmenu(aSubMenu, true, true); // instant hide, skip height reset
                      }
                    });

                    // Show new submenu manually (bypassing showSubmenu for top animation control)
                    removeTransitionListeners(newMenu);
                    if (newMenu._hideTimeout) {
                      clearTimeout(newMenu._hideTimeout);
                      delete newMenu._hideTimeout;
                    }

                    newMenu.classList.remove("hidden-2l");
                    newMenu.classList.add("visible-2l");

                    // Disable transitions to set up initial state instantly
                    newMenu.style.setProperty("transition", "none", "important");

                    // Create stacking context on header so z-index:-1 renders
                    // behind the header's white background (not behind the whole page)
                    slntHeader.style.setProperty("isolation", "isolate");

                    // Show at final position first to measure natural height
                    newMenu.style.setProperty("top", finalTop + "px", "important");
                    newMenu.style.setProperty("z-index", "-1"); // Behind header background during slide
                    newMenu.style.setProperty("visibility", "visible");
                    newMenu.style.setProperty("opacity", "1");

                    // Force reflow to render and measure
                    void newMenu.offsetHeight;
                    const newSubmenuHeight = newMenu.offsetHeight;

                    const heightDiff = newSubmenuHeight - oldSubmenuHeight;

                    if (heightDiff > 0) {
                      // TALLER new submenu: slide DOWN from underneath the menu bar
                      const startTop = finalTop - heightDiff;

                      // Start at offset position with top portion clipped
                      // Left/right use -100vw so clip-path doesn't clip the ::before
                      // pseudo-element (100vw wide full-width background)
                      newMenu.style.setProperty("top", startTop + "px", "important");
                      newMenu.style.setProperty("clip-path", "inset(" + heightDiff + "px -100vw 0 -100vw)");

                      void newMenu.offsetHeight;

                      // Animate top + clip-path together
                      newMenu.style.setProperty("transition", "top 0.5s ease, clip-path 0.5s ease", "important");

                      void newMenu.offsetHeight;

                      // Slide to final position, removing clip
                      newMenu.style.setProperty("top", finalTop + "px", "important");
                      newMenu.style.setProperty("clip-path", "inset(0 -100vw 0 -100vw)");

                    } else if (heightDiff < 0) {
                      // SHORTER new submenu: keep at finalTop (no white gap), use
                      // padding-top to push content down, then animate padding to base.
                      // The ::before background (height:100%) fills the padding area
                      // so the warm-grey background connects seamlessly with the menu bar.
                      const paddingOffset = Math.abs(heightDiff);

                      // Keep submenu at final position - no gap between menu bar and submenu
                      newMenu.style.setProperty("top", finalTop + "px", "important");
                      newMenu.style.setProperty("padding-top", (paddingOffset + submenu_padding_top) + "px");

                      void newMenu.offsetHeight;

                      // Animate padding-top to base value (content slides up, background contracts from bottom)
                      newMenu.style.setProperty("transition", "padding-top 0.5s ease", "important");

                      void newMenu.offsetHeight;

                      newMenu.style.setProperty("padding-top", submenu_padding_top + "px");
                    }

                    // Animate nav container height in sync (nav has its own 0.5s height transition)
                    const desktop_offset_height = get_desktop_offset_height();
                    const navHeight = newSubmenuHeight + desktop_offset_height;
                    mainMenuNavContainer.style.setProperty("height", navHeight + "px");

                    // Step 4: Fade in new submenu text
                    setTimeout(() => {
                      newMenu.classList.remove('text-hidden');
                    }, 50);

                    // Clean up after slide animation completes - remove ALL inline
                    // overrides so CSS rules control the submenu again (critical for
                    // subsequent close animation which relies on CSS .hidden-2l { top: -50px })
                    setTimeout(() => {
                      newMenu.style.removeProperty("transition");
                      newMenu.style.removeProperty("top");
                      newMenu.style.removeProperty("z-index");
                      newMenu.style.removeProperty("clip-path");
                      newMenu.style.removeProperty("padding-top");
                      slntHeader.style.removeProperty("isolation");
                    }, 550);
                  }, 180); // Match CSS transition duration (0.18s)
                }
              } else {
                // Normal open/close (not switching between submenus)
                // Check if search form is open — capture height for slide animation
                let oldSearchHeight = 0;
                const searchFormEl = document.querySelector('#search-form-container');
                if (searchFormEl && searchFormEl.classList.contains('visible-2l')) {
                  oldSearchHeight = searchFormEl.offsetHeight;
                }

                // Close search form if open (instant, skip height reset since submenu will set it)
                hideSearchForm(true, true);
                const searchBtn = document.querySelector('#search-in-menu');
                if (searchBtn) searchBtn.classList.remove('navigation__link--selected');

                // When switching from search, skip height reset for other submenus
                // (showSubmenu will set the correct nav height via slide animation)
                const skipOtherReset = oldSearchHeight > 0;

                // Hide all non-clicked submenus FIRST, before toggling the
                // clicked one. On mobile, showSubmenu calls calculateMobileSubmenuHeight
                // which measures all li heights — any still-expanded submenu would
                // inflate menuItemsHeight and give the new submenu a wrong max-height.
                allSubMenuContainers.forEach(aSubMenu => {
                  if (!aSubMenu.isEqualNode(subMenuContainerForClickedButton)) {
                    unselectChevron(aSubMenu);
                    hideSubmenu(aSubMenu, true, skipOtherReset);
                  }
                });

                toggleChevron(subMenuContainerForClickedButton);
                toggleSubmenu(subMenuContainerForClickedButton, false, oldSearchHeight);
              }
            }
          });
        });

        // Mobile burger menu setup
        setupMobileBurgerMenu();

        // Viewport-edge fades track page scroll and resizes (once-guarded —
        // behaviors can attach more than once).
        if (!window._slntViewportFadeBound) {
          window._slntViewportFadeBound = true;
          window.addEventListener('scroll', updateViewportFades, { passive: true });
          window.addEventListener('resize', updateViewportFades, { passive: true });
        }

        // Escape key (2026-08 brief §5): close the mobile menu (focus returns
        // to the hamburger), or on desktop close the open submenu/search
        // panel (focus returns to its toggle button). Bound once on the
        // document — behaviors can attach more than once.
        if (!document._slntMenuEscapeBound) {
          document._slntMenuEscapeBound = true;
          document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;

            if (isMobile()) {
              if (mobileMenuIsOpen()) {
                closeMobileMenu(true);
              }
              return;
            }

            const openSubmenu = document.querySelector('.sub-menu-container.visible-2l');
            if (openSubmenu) {
              const parentButton = openSubmenu.parentElement.querySelector('button.main_nav_link');
              unselectChevron(openSubmenu);
              hideSubmenu(openSubmenu);
              if (parentButton) parentButton.focus();
              return;
            }

            const searchFormEl = document.querySelector('#search-form-container');
            if (searchFormEl && searchFormEl.classList.contains('visible-2l')) {
              hideSearchForm();
              const searchBtnEsc = document.querySelector('#search-in-menu');
              if (searchBtnEsc) {
                searchBtnEsc.classList.remove('navigation__link--selected');
                searchBtnEsc.focus();
              }
            }
          });
        }

        // insert search term in search box on search page
        if (window.location.href.includes('/search/node') ) {
          const url = window.location.href;

          const regex = /\/search\/node\?keys=(.*)/;
          const match = url.match(regex);

          if (match && match[1]) {
            const result = decodeURI(match[1]);

            const inputField = document.getElementById('searchpageinputfield');
            inputField.value = result;

            console.log(result); 

            console.log("No match found.");
          }
        }

      });

      /**
       * Setup mobile burger menu
       */
      function setupMobileBurgerMenu() {
        // Create burger menu HTML - using same structure as Gospel Choir site.
        // Close state (2026-08 brief §4): ✕ icon over stacked CLOSE / MENU.
        var menuicon =
          '<a href="#" id="slnt-togl-expand" class="slnt-togl-expand" role="button" aria-expanded="false" aria-label="Open menu"> \
            <div class="menu-icon"> \
              <span class="top"></span> \
              <span class="middle"></span> \
              <span class="bottom"></span> \
            </div> \
            <div class="menu-text"> \
              <span class="icon-burger">MENU</span> \
              <span class="icon-close"><span class="icon-close-line">CLOSE</span><span class="icon-close-line">MENU</span></span> \
            </div> \
          </a>';

        // Insert burger menu if in mobile mode and not already present
        if (isMobile() && $('#slnt-mobile-menu-container').is(':empty')) {
          $("#slnt-mobile-menu-container").append(menuicon);
        }

        // Burger menu click handler - adapted from Gospel Choir site.
        // keydown: the toggle is an <a role="button">, so Enter fires click
        // natively but Space must be handled for keyboard users (brief §5).
        $('#slnt-togl-expand')
          .off('.toglexpandns')
          .on({
            'click.toglexpandns': function(e) {
              e.stopPropagation();
              e.preventDefault();

              if (isMobile()) {
                // detail === 0 means the click was synthesised from a
                // keyboard activation (Enter on the <a role="button">); a
                // real tap/mouse click reports >= 1. Only keyboard openers
                // get focus moved to the first menu item — see openMobileMenu.
                const detail = e.originalEvent ? e.originalEvent.detail : e.detail;
                toggleMobileMenu(detail === 0);
              }
            },
            'keydown.toglexpandns': function(e) {
              if (e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                if (isMobile()) {
                  toggleMobileMenu(true);
                }
              }
            }
          });
      }

      function mobileMenuIsOpen() {
        const nav = document.querySelector("nav[role=navigation]:not(.pager)");
        return !!nav && nav.classList.contains('slnt-mobile-menu-open');
      }

      function toggleMobileMenu(viaKeyboard = false) {
        if (mobileMenuIsOpen()) {
          closeMobileMenu();
        } else {
          openMobileMenu(viaKeyboard);
        }
      }

      /**
       * Open the mobile push-down menu.
       *
       * The nav lives in the normal document flow and animates its height
       * (see menu-mobile.css — the .main-menu-wrap grid 0fr/1fr rule),
       * pushing page content down rather than covering it. Open/closed state
       * is a single class on the nav; CSS owns the animation.
       *
       * The legacy blanket-cover ("overlay") behaviour is preserved for a
       * future contrib module under [data-mobile-menu-mode="overlay"] in
       * menu-mobile.css (and in git history prior to the 2026-07-20
       * push-down work).
       */
      /**
       * @param {boolean} viaKeyboard - True when the toggle was activated by
       *   keyboard. Only then is focus moved to the first menu item — after a
       *   touch/mouse tap, moving focus would paint the orange
       *   :focus-visible marker on Home (iOS treats programmatic focus as
       *   focus-visible), confusing users who never pressed Tab.
       */
      function openMobileMenu(viaKeyboard = false) {
        const togl_expd = document.getElementById('slnt-togl-expand');
        const nav = document.querySelector("nav[role=navigation]:not(.pager)");

        togl_expd.classList.add('slnt-togl-expand--open');
        togl_expd.setAttribute('aria-expanded', 'true');
        togl_expd.setAttribute('aria-label', 'Close menu');
        nav.classList.add('slnt-mobile-menu-open');

        // Focus management (2026-08 brief §5): move focus to the first main
        // menu item (Home) once the push-down reveal has finished — focusing
        // a still-moving target mid-animation can scroll to the wrong place.
        if (viaKeyboard) {
          setTimeout(() => {
            if (!nav.classList.contains('slnt-mobile-menu-open')) return;
            const firstItem = nav.querySelector('.main-menu-item-container .main_nav_link');
            if (firstItem) firstItem.focus();
          }, 300); // just after the 0.28s grid reveal
        }
      }

      /**
       * Close the mobile push-down menu, resetting the search form and any
       * open submenus so the menu is clean when it reopens. The nav's own
       * height animation hides everything as it collapses.
       *
       * @param {boolean} returnFocus - Move focus back to the toggle (used by
       *   the Escape key; a pointer/keyboard activation of the toggle itself
       *   already leaves focus there).
       */
      function closeMobileMenu(returnFocus = false) {
        const togl_expd = document.getElementById('slnt-togl-expand');
        const nav = document.querySelector("nav[role=navigation]:not(.pager)");

        hideSearchForm(true);
        const searchBtn = document.querySelector('#search-in-menu');
        if (searchBtn) searchBtn.classList.remove('navigation__link--selected');

        const allSubMenus = document.querySelectorAll('.sub-menu-container');
        allSubMenus.forEach(aSubMenu => {
          unselectChevron(aSubMenu);
          hideSubmenu(aSubMenu, true);
        });

        nav.classList.remove('slnt-mobile-menu-open');

        if (togl_expd) {
          togl_expd.classList.remove('slnt-togl-expand--open');
          togl_expd.setAttribute('aria-expanded', 'false');
          togl_expd.setAttribute('aria-label', 'Open menu');
          if (returnFocus) {
            togl_expd.focus();
          }
        }
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
          // Mobile mode - the menu starts collapsed via CSS (.main-menu-wrap
          // grid 0fr), so no display toggling is needed. Just make sure the
          // open-state class is off and set the initial header height.
          $("nav[role=navigation]:not(.pager)").removeClass('slnt-mobile-menu-open');
          $("#slnt-header").addClass('slnt-hdr-hgt-init');
        }
      }

      /**
       * Unified transition end handler
       */
      function handleTransitionEnd(event, aSubMenu, isShowing, transitionId) {
        // Check if this is still the current transition - ignore stale events
        if (aSubMenu._currentTransitionId !== transitionId) {
          console.log('Ignoring stale transition event, id:', transitionId, 'current:', aSubMenu._currentTransitionId);
          return;
        }
        
        console.log('Transition ended:', { isShowing, target: event.target, transitionId });

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
        // Generate unique ID for this transition
        const transitionId = Date.now() + Math.random();
        aSubMenu._currentTransitionId = transitionId;
        
        const handler = (e) => handleTransitionEnd(e, aSubMenu, isShowing, transitionId);

        // Store handler reference so we can remove it later
        aSubMenu._transitionHandler = handler;

        aSubMenu.addEventListener('webkitTransitionEnd', handler, false);
        aSubMenu.addEventListener('otransitionend', handler, false);
        aSubMenu.addEventListener('oTransitionEnd', handler, false);
        aSubMenu.addEventListener('msTransitionEnd', handler, false);
        aSubMenu.addEventListener('transitionend', handler, false);
        
        console.log('Added transition listener with id:', transitionId);
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
       * @param {Element} aSubMenu - The submenu element
       * @param {boolean} instantShow - If true, show without animation (used when switching between submenus)
       * @param {number} oldHeight - Height of the previously open panel (for slide animation)
       */
      function toggleSubmenu(aSubMenu, instantShow = false, oldHeight = 0) {
        // Use the intended state, not the current class which changes immediately
        // Check if we're currently animating to show or already shown
        const isCurrentlyOrBecomingVisible = aSubMenu.classList.contains("visible-2l");

        console.log('toggleSubmenu called:', {
          isCurrentlyOrBecomingVisible,
          instantShow,
          currentClasses: aSubMenu.className,
          currentOpacity: aSubMenu.style.opacity,
          currentVisibility: aSubMenu.style.visibility,
          currentZIndex: aSubMenu.style.zIndex
        });

        if (isCurrentlyOrBecomingVisible) {
          // It's visible or becoming visible, so hide it (always animated)
          console.log('→ Calling hideSubmenu');
          hideSubmenu(aSubMenu);
        } else {
          // It's hidden or becoming hidden, so show it
          console.log('→ Calling showSubmenu, instant:', instantShow, 'oldHeight:', oldHeight);
          showSubmenu(aSubMenu, instantShow, oldHeight);
        }
      }

      /**
       * Show a submenu with optional instant mode (no animation)
       * @param {Element} aSubMenu - The submenu element
       * @param {boolean} instant - If true, show without animation (used when switching between submenus)
       * @param {number} oldHeight - Height of the previously open panel (for slide animation when switching from search)
       */
      function showSubmenu(aSubMenu, instant = false, oldHeight = 0) {
        console.log('showSubmenu START, instant:', instant, 'oldHeight:', oldHeight);

        // IMPORTANT: Remove ALL existing listeners first to prevent conflicts
        removeTransitionListeners(aSubMenu);

        // Clear any pending setTimeout from previous hide operations
        if (aSubMenu._hideTimeout) {
          console.log('Clearing pending hide timeout');
          clearTimeout(aSubMenu._hideTimeout);
          delete aSubMenu._hideTimeout;
        }

        aSubMenu.classList.remove("hidden-2l");
        aSubMenu.classList.add("visible-2l");

        if (!isMobile()) {
          // Desktop behavior
          const mainMenuNavContainer = get_mainMenuNavContainer();

          if (instant) {
            // Instant show: disable ALL CSS transitions using inline style
            // (nav animation is already disabled at click handler level)
            console.log('showSubmenu: Instant show (no animation)');

            // Disable transitions via inline style (overrides all CSS transitions)
            aSubMenu.style.setProperty("transition", "none", "important");

            // Force reflow to apply transition disabling
            void aSubMenu.offsetHeight;

            // Position the submenu and set all properties immediately
            aSubMenu.style.setProperty("top", submenu_desktop_top_reveal);
            aSubMenu.style.setProperty("z-index", "0");
            aSubMenu.style.setProperty("visibility", "visible");
            aSubMenu.style.setProperty("opacity", "1");

            // Set height immediately
            desktop_menu_drawer_show(aSubMenu, mainMenuNavContainer);

            // Force reflow to ensure changes are applied before re-enabling transitions
            void aSubMenu.offsetHeight;

            // Re-enable CSS transitions after a short delay to ensure all changes are committed
            setTimeout(() => {
              aSubMenu.style.removeProperty("transition");
            }, 50);
          } else if (oldHeight > 0) {
            // Slide animation: switching from another panel (e.g. search form)
            // Uses same heightDiff logic as submenu-to-submenu switching
            const slntHeader = document.getElementById('slnt-header');
            const desktop_offset_height = get_desktop_offset_height();
            const finalTop = desktop_offset_height;

            // Disable transitions to set up initial state instantly
            aSubMenu.style.setProperty("transition", "none", "important");

            // Create stacking context so z-index:-1 renders behind header
            slntHeader.style.setProperty("isolation", "isolate");

            // Position at final position to measure natural height
            aSubMenu.style.setProperty("top", finalTop + "px", "important");
            aSubMenu.style.setProperty("z-index", "-1");
            aSubMenu.style.setProperty("visibility", "visible");
            aSubMenu.style.setProperty("opacity", "1");

            void aSubMenu.offsetHeight;
            const newSubmenuHeight = aSubMenu.offsetHeight;

            const heightDiff = newSubmenuHeight - oldHeight;

            if (heightDiff > 0) {
              // TALLER: slide DOWN from behind header
              const startTop = finalTop - heightDiff;

              aSubMenu.style.setProperty("top", startTop + "px", "important");
              aSubMenu.style.setProperty("clip-path", "inset(" + heightDiff + "px -100vw 0 -100vw)");

              void aSubMenu.offsetHeight;

              aSubMenu.style.setProperty("transition", "top 0.5s ease, clip-path 0.5s ease", "important");

              void aSubMenu.offsetHeight;

              aSubMenu.style.setProperty("top", finalTop + "px", "important");
              aSubMenu.style.setProperty("clip-path", "inset(0 -100vw 0 -100vw)");
            } else if (heightDiff < 0) {
              // SHORTER: padding-top animation
              const paddingOffset = Math.abs(heightDiff);

              aSubMenu.style.setProperty("top", finalTop + "px", "important");
              aSubMenu.style.setProperty("padding-top", (paddingOffset + submenu_padding_top) + "px");

              void aSubMenu.offsetHeight;

              aSubMenu.style.setProperty("transition", "padding-top 0.5s ease", "important");

              void aSubMenu.offsetHeight;

              aSubMenu.style.setProperty("padding-top", submenu_padding_top + "px");
            }
            // heightDiff === 0: no animation needed, submenu is already at correct position

            // Animate nav container height
            const navHeight = newSubmenuHeight + desktop_offset_height;
            mainMenuNavContainer.style.setProperty("height", navHeight + "px");

            // Clean up after animation completes
            setTimeout(() => {
              aSubMenu.style.removeProperty("transition");
              aSubMenu.style.removeProperty("top");
              aSubMenu.style.removeProperty("z-index");
              aSubMenu.style.removeProperty("clip-path");
              aSubMenu.style.removeProperty("padding-top");
              slntHeader.style.removeProperty("isolation");
            }, 550);
          } else {
            // Normal animated show: fade in with transition
            // Position the submenu
            aSubMenu.style.setProperty("top", submenu_desktop_top_reveal);
            // Start with z-index -1 during animation
            aSubMenu.style.setProperty("z-index", "-1");

            // Make visible first
            aSubMenu.style.setProperty("visibility", "visible");

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
                console.log('showSubmenu: Setting opacity to 1');
                aSubMenu.style.setProperty("opacity", "1");
              });
            });

            // Add transition listener for cleanup - will set z-index to 0 when done
            addTransitionListeners(aSubMenu, true);
          }
        } else {
          // Mobile behavior - animated reveal with scrollable height
          // Set initial collapsed state FIRST so all submenus are collapsed
          // during height calculation (prevents target submenu inflating its li)
          aSubMenu.style.setProperty("width", "100%");
          aSubMenu.style.setProperty("overflow", "hidden");
          aSubMenu.style.setProperty("max-height", "0");
          aSubMenu.style.setProperty("opacity", "0");

          // Force reflow to ensure collapsed state is rendered before measuring
          void aSubMenu.offsetHeight;

          const maxHeight = calculateMobileSubmenuHeight();

          // Scroll affordance (2026-08 brief §2): decide overflow BEFORE the
          // reveal starts so the CSS gradient is present from the first frame
          // (no flash), and short submenus never show it. scrollHeight gives
          // the full content height even while collapsed.
          aSubMenu.classList.toggle('has-overflow', aSubMenu.scrollHeight > maxHeight);
          aSubMenu.classList.remove('scrolled-down', 'scrolled-to-bottom');

          // Trigger animation to target state
          aSubMenu.style.setProperty("max-height", maxHeight + "px");
          aSubMenu.style.setProperty("opacity", "1");

          // After animation completes, enable scrolling and setup fade gradients
          aSubMenu._showTimeout = setTimeout(() => {
            if (aSubMenu.classList.contains("visible-2l")) {
              aSubMenu.style.setProperty("overflow-y", "auto");
              aSubMenu.style.setProperty("overflow-x", "hidden");
              aSubMenu.style.setProperty("-webkit-overflow-scrolling", "touch");
              setupMobileScrollFade(aSubMenu);
              updateViewportFades();
            }
          }, 500); // Match CSS transition duration (0.5s)
        }
      }

      /**
       * Max-height (px) for an open mobile submenu.
       *
       * Push-down behaviour (2026-07-20): the menu now lives in the normal
       * document flow and the page scrolls, so the submenu no longer has to
       * shrink to fit the viewport. Instead it is capped to a fixed height of
       * roughly six rows. Submenus with six or fewer items are shorter than the
       * cap and so show in full; taller submenus (Culture, Sectors, …) hit the
       * cap and scroll, with the existing fade indicator. This keeps the overall
       * menu height roughly consistent whichever section is open.
       *
       * This value is applied as the submenu's inline max-height: it is what the
       * open animation transitions toward and what the overflow/fade detection
       * measures against. ~290px ≈ six 48px rows.
       */
      function calculateMobileSubmenuHeight() {
        return 290;
      }

      /**
       * Hide a submenu with optional instant mode (no animation)
       * @param {Element} aSubMenu - The submenu element
       * @param {boolean} instant - If true, hide without animation
       * @param {boolean} skipHeightReset - If true, don't reset nav height (used when switching between submenus)
       */
      function hideSubmenu(aSubMenu, instant = false, skipHeightReset = false) {
        console.log('hideSubmenu START, instant:', instant, 'skipHeightReset:', skipHeightReset);

        // IMPORTANT: Remove ALL existing listeners first to prevent conflicts
        removeTransitionListeners(aSubMenu);

        // Clear any pending setTimeout from previous operations
        if (aSubMenu._hideTimeout) {
          console.log('Clearing pending hide timeout in hideSubmenu');
          clearTimeout(aSubMenu._hideTimeout);
          delete aSubMenu._hideTimeout;
        }

        if (!isMobile()) {
          // Desktop behavior — swap classes immediately
          aSubMenu.classList.add("hidden-2l");
          aSubMenu.classList.remove("visible-2l");
          aSubMenu.classList.remove("text-hidden");

          // Reset nav container height when hiding submenu (unless switching)
          if (!skipHeightReset) {
            const mainMenuNavContainer = get_mainMenuNavContainer();
            mainMenuNavContainer.style.setProperty("height", menu_bar_height);
          }

          // Set z-index to -1 BEFORE starting fade out animation
          // This way the menu goes behind content during the fade
          aSubMenu.style.setProperty("z-index", "-1");

          if (instant) {
            // Instant hide: set properties immediately
            console.log('hideSubmenu: Instant hide');
            aSubMenu.style.setProperty("visibility", "hidden");
            aSubMenu.style.setProperty("opacity", "0");
          } else {
            // Animated hide: fade out
            console.log('hideSubmenu: Animated hide, setting opacity to 0');
            addTransitionListeners(aSubMenu, false);
            aSubMenu.style.setProperty("opacity", "0");

            // After fade completes, hide visibility
            // Store timeout ID so we can cancel it if needed
            aSubMenu._hideTimeout = setTimeout(() => {
              console.log('hideSubmenu: setTimeout fired, hiding visibility');
              aSubMenu.style.setProperty("visibility", "hidden");
              delete aSubMenu._hideTimeout;
            }, 500); // Match your CSS transition duration
          }
        } else {
          // Mobile behavior
          // Clear any pending show timeout
          if (aSubMenu._showTimeout) {
            clearTimeout(aSubMenu._showTimeout);
            delete aSubMenu._showTimeout;
          }

          if (instant) {
            // Instant hide: swap classes and set properties immediately
            aSubMenu.classList.add("hidden-2l");
            aSubMenu.classList.remove("visible-2l");
            aSubMenu.classList.remove("text-hidden");
            aSubMenu.style.setProperty("overflow", "hidden");
            aSubMenu.style.setProperty("max-height", "0");
            aSubMenu.style.setProperty("opacity", "0");
          } else {
            // Animated hide: keep visible-2l during animation so CSS
            // hidden-2l doesn't interfere with the transition.
            // Lock current overflow to hidden (disable scrolling during collapse)
            aSubMenu.style.setProperty("overflow", "hidden");

            // Force reflow so browser registers the current max-height
            // as the starting point for the transition
            void aSubMenu.offsetHeight;

            // Animate to collapsed state (CSS transition on .sub-menu-container
            // handles max-height and opacity over 0.5s)
            aSubMenu.style.setProperty("max-height", "0");
            aSubMenu.style.setProperty("opacity", "0");
          }

          // Clean up after animation completes (or immediately for instant)
          const cleanupDelay = instant ? 0 : 500;
          setTimeout(() => {
            // Swap classes after animation so CSS hidden-2l takes over
            aSubMenu.classList.add("hidden-2l");
            aSubMenu.classList.remove("visible-2l");
            aSubMenu.classList.remove("text-hidden");
            aSubMenu.classList.remove("has-overflow", "scrolled-down", "scrolled-to-bottom");
            // Remove inline styles — CSS hidden-2l handles the collapsed state
            aSubMenu.style.removeProperty("width");
            aSubMenu.style.removeProperty("max-height");
            aSubMenu.style.removeProperty("opacity");
            aSubMenu.style.removeProperty("overflow");
            aSubMenu.style.removeProperty("overflow-y");
            aSubMenu.style.removeProperty("overflow-x");
            aSubMenu.style.removeProperty("-webkit-overflow-scrolling");
            updateViewportFades();
          }, cleanupDelay);
        }
      }

      /**
       * Show the search form with slide-down animation (like submenu switching)
       * @param {number} oldHeight - Height of the previously open panel (submenu or 0 if none)
       */
      function showSearchForm(oldHeight = 0) {
        const searchFormContainer = document.querySelector('#search-form-container');
        if (!searchFormContainer || searchFormContainer.classList.contains('visible-2l')) return;

        searchFormContainer.classList.remove('hidden-2l');
        searchFormContainer.classList.add('visible-2l');

        if (!isMobile()) {
          // Desktop: slide down from behind header (like submenu switching animation)
          const mainMenuNavContainer = get_mainMenuNavContainer();
          const slntHeader = document.getElementById('slnt-header');
          const desktop_offset_height = get_desktop_offset_height();
          const finalTop = desktop_offset_height;

          // Disable transitions to set up initial state instantly
          searchFormContainer.style.setProperty("transition", "none", "important");

          // Create stacking context so z-index:-1 renders behind header
          slntHeader.style.setProperty("isolation", "isolate");

          // Position at final position to measure natural height
          searchFormContainer.style.setProperty("top", finalTop + "px", "important");
          searchFormContainer.style.setProperty("z-index", "-1");
          searchFormContainer.style.setProperty("visibility", "visible");
          searchFormContainer.style.setProperty("opacity", "1");

          void searchFormContainer.offsetHeight;
          const searchHeight = searchFormContainer.offsetHeight;

          const heightDiff = searchHeight - oldHeight;

          if (heightDiff >= 0) {
            // TALLER (or opening from nothing): slide DOWN from behind header
            const startTop = finalTop - heightDiff;

            searchFormContainer.style.setProperty("top", startTop + "px", "important");
            searchFormContainer.style.setProperty("clip-path", "inset(" + heightDiff + "px -100vw 0 -100vw)");

            void searchFormContainer.offsetHeight;

            searchFormContainer.style.setProperty("transition", "top 0.5s ease, clip-path 0.5s ease", "important");

            void searchFormContainer.offsetHeight;

            searchFormContainer.style.setProperty("top", finalTop + "px", "important");
            searchFormContainer.style.setProperty("clip-path", "inset(0 -100vw 0 -100vw)");
          } else {
            // SHORTER than old panel: padding-top animation
            const paddingOffset = Math.abs(heightDiff);

            searchFormContainer.style.setProperty("top", finalTop + "px", "important");
            searchFormContainer.style.setProperty("padding-top", (paddingOffset + submenu_padding_top) + "px");

            void searchFormContainer.offsetHeight;

            searchFormContainer.style.setProperty("transition", "padding-top 0.5s ease", "important");

            void searchFormContainer.offsetHeight;

            searchFormContainer.style.setProperty("padding-top", submenu_padding_top + "px");
          }

          // Animate nav container height
          const navHeight = searchHeight + desktop_offset_height;
          mainMenuNavContainer.style.setProperty("height", navHeight + "px");

          // Clean up after animation completes
          setTimeout(() => {
            searchFormContainer.style.removeProperty("transition");
            searchFormContainer.style.removeProperty("top");
            searchFormContainer.style.removeProperty("clip-path");
            searchFormContainer.style.removeProperty("padding-top");
            slntHeader.style.removeProperty("isolation");
            // Set z-index for interactive content (search input needs to be clickable)
            if (searchFormContainer.classList.contains('visible-2l')) {
              searchFormContainer.style.setProperty('z-index', '0');
            }
          }, 550);
        } else {
          // Mobile: animate max-height
          searchFormContainer.style.setProperty('overflow', 'hidden');
          searchFormContainer.style.setProperty('max-height', '0');
          searchFormContainer.style.setProperty('opacity', '0');

          void searchFormContainer.offsetHeight;

          searchFormContainer.style.setProperty('max-height', '200px');
          searchFormContainer.style.setProperty('opacity', '1');

          setTimeout(() => {
            if (searchFormContainer.classList.contains('visible-2l')) {
              searchFormContainer.style.removeProperty('overflow');
              // Auto-scroll (2026-08 brief §3): on small viewports (e.g.
              // iPhone SE) the revealed form can sit below the fold. After
              // the height transition completes, bring it into view —
              // block:'nearest' is a no-op when it's already visible.
              searchFormContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
          }, 500);
        }
      }

      /**
       * Hide the search form with optional instant mode
       * @param {boolean} instant - If true, hide without animation
       * @param {boolean} skipHeightReset - If true, don't reset nav height
       */
      function hideSearchForm(instant = false, skipHeightReset = false) {
        const searchFormContainer = document.querySelector('#search-form-container');
        if (!searchFormContainer || !searchFormContainer.classList.contains('visible-2l')) return;

        if (!isMobile()) {
          // Desktop
          const mainMenuNavContainer = get_mainMenuNavContainer();
          const slntHeader = document.getElementById('slnt-header');

          if (instant) {
            // Instant hide: swap classes and reset immediately
            searchFormContainer.classList.add('hidden-2l');
            searchFormContainer.classList.remove('visible-2l');

            if (!skipHeightReset && !check_submenu_open()) {
              mainMenuNavContainer.style.setProperty('height', menu_bar_height);
            }

            searchFormContainer.style.setProperty('z-index', '-1');
            searchFormContainer.style.setProperty('visibility', 'hidden');
            searchFormContainer.style.setProperty('opacity', '0');
          } else {
            // Animated hide: slide UP behind header (reverse of show)
            const searchHeight = searchFormContainer.offsetHeight;
            const currentTop = parseInt(getComputedStyle(searchFormContainer).top);
            const endTop = currentTop - searchHeight;

            // Keep visible-2l during animation so CSS !important doesn't interfere
            slntHeader.style.setProperty("isolation", "isolate");
            searchFormContainer.style.setProperty("z-index", "-1");

            searchFormContainer.style.setProperty("transition", "top 0.5s ease, clip-path 0.5s ease", "important");

            void searchFormContainer.offsetHeight;

            // Slide up and clip from top simultaneously
            searchFormContainer.style.setProperty("top", endTop + "px", "important");
            searchFormContainer.style.setProperty("clip-path", "inset(" + searchHeight + "px -100vw 0 -100vw)");

            // Animate nav height back
            if (!skipHeightReset && !check_submenu_open()) {
              mainMenuNavContainer.style.setProperty('height', menu_bar_height);
            }

            // Clean up after animation completes
            setTimeout(() => {
              searchFormContainer.classList.add('hidden-2l');
              searchFormContainer.classList.remove('visible-2l');

              searchFormContainer.style.removeProperty("transition");
              searchFormContainer.style.removeProperty("top");
              searchFormContainer.style.removeProperty("clip-path");
              searchFormContainer.style.setProperty('visibility', 'hidden');
              searchFormContainer.style.setProperty('opacity', '0');
              slntHeader.style.removeProperty("isolation");
            }, 550);
          }
        } else {
          // Mobile
          if (instant) {
            searchFormContainer.classList.add('hidden-2l');
            searchFormContainer.classList.remove('visible-2l');
            searchFormContainer.style.setProperty('overflow', 'hidden');
            searchFormContainer.style.setProperty('max-height', '0');
            searchFormContainer.style.setProperty('opacity', '0');
          } else {
            // Animated: keep visible-2l during animation, swap after
            searchFormContainer.style.setProperty('overflow', 'hidden');
            void searchFormContainer.offsetHeight;
            searchFormContainer.style.setProperty('max-height', '0');
            searchFormContainer.style.setProperty('opacity', '0');
          }

          const cleanupDelay = instant ? 0 : 500;
          setTimeout(() => {
            searchFormContainer.classList.add('hidden-2l');
            searchFormContainer.classList.remove('visible-2l');
            searchFormContainer.style.removeProperty('overflow');
            searchFormContainer.style.removeProperty('max-height');
            searchFormContainer.style.removeProperty('opacity');
          }, cleanupDelay);
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
          
          // Disable chevron transitions so they reset instantly (no animated rotation)
          const allChevrons = document.querySelectorAll('.navigation__link__down-icon');
          allChevrons.forEach(chevron => {
            chevron.style.setProperty('transition', 'none', 'important');
          });

          // Close all submenus and search on any mode change - clean slate
          allSubMenus.forEach(aSubMenu => {
            removeTransitionListeners(aSubMenu);
            unselectChevron(aSubMenu);
            aSubMenu.classList.remove('visible-2l');
            aSubMenu.classList.add('hidden-2l');
            aSubMenu.removeAttribute('style');
          });

          // Reset search form
          const sfReset = document.querySelector('#search-form-container');
          if (sfReset) {
            sfReset.classList.remove('visible-2l');
            sfReset.classList.add('hidden-2l');
            sfReset.removeAttribute('style');
          }
          const searchBtnReset = document.querySelector('#search-in-menu');
          if (searchBtnReset) searchBtnReset.classList.remove('navigation__link--selected');

          // Force reflow so chevron state is applied instantly, then re-enable transitions
          void document.body.offsetHeight;
          allChevrons.forEach(chevron => {
            chevron.style.removeProperty('transition');
          });

          if (newMode === 'mobile') {
            // Going to mobile
            mainMenuNavContainer.style.setProperty("height", "auto");

            // Add burger menu
            if ($('#slnt-mobile-menu-container').is(':empty')) {
              setupMobileBurgerMenu();
            }

            // Menu starts collapsed via CSS (.main-menu-wrap grid 0fr); just
            // clear any open state carried over from a previous mobile session.
            $("nav[role=navigation]:not(.pager)").removeClass('slnt-mobile-menu-open');
            $("#slnt-header").addClass('slnt-hdr-hgt-init');

            // Reset any legacy overlay styles that may linger.
            $("body").css("overflow", "");
            $("body").removeClass('slnt-overlay-menu-bg');

          } else {
            // Going to desktop
            mainMenuNavContainer.classList.remove('animation');

            // Remove burger menu and reset mobile styles
            $("#slnt-mobile-menu-container").empty();
            $("#slnt-header").removeClass('slnt-overlay-hdr-hgt-togl-expand-mob-menu');
            $("#slnt-header").removeClass('slnt-hdr-hgt-init');
            $("nav[role=navigation]:not(.pager)").removeClass('slnt-mobile-menu-open');

            // Reset mobile overlay styles
            $("body").css("overflow", "");
            $("body").removeClass('slnt-overlay-menu-bg');
            // Clear ALL inline styles from nav (including transition/opacity from burger fade)
            document.querySelector("nav[role=navigation]:not(.pager)").removeAttribute('style');
            document.querySelector(".main-menu-wrap").removeAttribute('style');

            // Set all submenus to desktop hidden state
            allSubMenus.forEach(aSubMenu => {
              aSubMenu.style.setProperty("visibility", "hidden");
              aSubMenu.style.setProperty("opacity", "0");
            });

            // Set search form to desktop hidden state
            if (sfReset) {
              sfReset.style.setProperty("visibility", "hidden");
              sfReset.style.setProperty("opacity", "0");
            }

            mainMenuNavContainer.style.setProperty("height", menu_bar_height);

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
            const searchFormEl = document.querySelector('#search-form-container');
            const searchIsOpen = searchFormEl && searchFormEl.classList.contains('visible-2l');

            if (check_submenu_open()) {
              const aSubMenu = document.querySelector(".sub-menu-container.visible-2l");
              if (aSubMenu) {
                desktop_menu_drawer_show(aSubMenu, mainMenuNavContainer);
              }
            } else if (searchIsOpen) {
              // Recalculate nav height for visible search form
              const searchHeight = searchFormEl.offsetHeight;
              const desktop_offset_height = get_desktop_offset_height();
              mainMenuNavContainer.style.setProperty("height", (searchHeight + desktop_offset_height) + "px");
            } else {
              desktop_menu_initialise_container_height();
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
        const display = $(".main-menu-item-container li").css("display");
        const isMobileFlag = display !== "inline-block" && display !== "flex";

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
        return 48; /* 48px, was 96px but logo moved above */
      }

      /**
       * Show the menu drawer by setting nav height
       * @param {Element} aSubMenu - The submenu element
       * @param {Element} mainMenuNavContainer - The nav container element
       */
      function desktop_menu_drawer_show(aSubMenu, mainMenuNavContainer) {
        const offsetHeight = aSubMenu.offsetHeight;
        const desktop_offset_height = get_desktop_offset_height();
        const offsetHeightCalc = parseInt(offsetHeight) + desktop_offset_height;

        console.log('Setting nav height to:', offsetHeightCalc + 'px');
        mainMenuNavContainer.style.setProperty("height", offsetHeightCalc + "px");
      }

      function get_mainMenuNavContainer() {
        return document.querySelector('header > * nav[role=navigation]:not(.pager)');
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
        // Initialize search form as hidden
        const sf = document.querySelector('#search-form-container');
        if (sf) {
          sf.style.setProperty("visibility", "hidden");
          sf.style.setProperty("opacity", "0");
        }
      }

      /**
       * Viewport-edge scroll affordance (2026-08-16 follow-up): fixed
       * gradient overlays at the screen's top/bottom edge, shown only while
       * the open mobile submenu is cut off by that edge of the viewport
       * (page scroll), complementing the sticky fades that indicate the
       * panel's own internal scrolling. Hidden again the moment the panel is
       * fully in view — or when nothing is open / we're on desktop.
       */
      function getViewportFades() {
        let top = document.querySelector('.slnt-viewport-fade--top');
        let bottom = document.querySelector('.slnt-viewport-fade--bottom');
        if (!top) {
          top = document.createElement('div');
          top.className = 'slnt-viewport-fade slnt-viewport-fade--top';
          top.setAttribute('aria-hidden', 'true');
          document.body.appendChild(top);
        }
        if (!bottom) {
          bottom = document.createElement('div');
          bottom.className = 'slnt-viewport-fade slnt-viewport-fade--bottom';
          bottom.setAttribute('aria-hidden', 'true');
          document.body.appendChild(bottom);
        }
        return { top, bottom };
      }

      function updateViewportFades() {
        const fades = getViewportFades();
        const openSub = document.querySelector('.sub-menu-container.visible-2l');
        let showTop = false;
        let showBottom = false;

        if (openSub && mobileMenuIsOpen() && isMobile()) {
          const rect = openSub.getBoundingClientRect();
          const viewportHeight = window.innerHeight;
          // Cut off at an edge, with enough of the panel on-screen that the
          // 48px fade actually overlays submenu rows rather than other content.
          showTop = rect.top < 0 && rect.bottom > 48;
          showBottom = rect.bottom > viewportHeight && rect.top < viewportHeight - 48;
        }

        fades.top.classList.toggle('is-visible', showTop);
        fades.bottom.classList.toggle('is-visible', showBottom);
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

      function toggleChevron(aSubMenu) {
        const parentAsMainMenuListItem = aSubMenu.parentElement;
        const aMenuButton = parentAsMainMenuListItem.querySelector("button.main_nav_link");

        if (aMenuButton.classList.contains("navigation__link--selected")) {
          aMenuButton.classList.remove("navigation__link--selected");
        }
        else {
          aMenuButton.classList.add("navigation__link--selected");
        }
      }

      function unselectChevron(aSubMenu) {
        const parentAsMainMenuListItem = aSubMenu.parentElement;
        const aMenuButton = parentAsMainMenuListItem.querySelector("button.main_nav_link");

        if (aMenuButton.classList.contains("navigation__link--selected")) {
          aMenuButton.classList.remove("navigation__link--selected");
        }
      }

      // Search form handler - integrated with submenu system
      const searchMenuButton = document.querySelector('#search-in-menu');
      const searchFormContainer = document.querySelector('#search-form-container');

      if (searchMenuButton && searchFormContainer) {
        searchMenuButton.addEventListener('click', function(e) {
          e.stopPropagation();
          e.preventDefault();

          const searchIsOpen = searchFormContainer.classList.contains('visible-2l');

          if (searchIsOpen) {
            // Close search
            hideSearchForm();
            searchMenuButton.classList.remove('navigation__link--selected');
          } else {
            // Open search - first measure and close any open submenus
            let oldSubmenuHeight = 0;
            const openSubmenu = document.querySelector('.sub-menu-container.visible-2l');
            if (openSubmenu) {
              oldSubmenuHeight = openSubmenu.offsetHeight;
            }

            const allSubMenuContainers = document.querySelectorAll('.main-menu-item-container > * .sub-menu-container');
            allSubMenuContainers.forEach(aSubMenu => {
              unselectChevron(aSubMenu);
              hideSubmenu(aSubMenu, true, true); // instant hide, skip height reset
            });

            showSearchForm(oldSubmenuHeight);
            searchMenuButton.classList.add('navigation__link--selected');
          }
        });
      }

      // Search clear buttons — clear input and refocus
      document.querySelectorAll('.search-clear-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var input = this.previousElementSibling;
          if (input) {
            input.value = '';
            input.focus();
          }
        });
      });
    }
  };

}(jQuery, Drupal));
