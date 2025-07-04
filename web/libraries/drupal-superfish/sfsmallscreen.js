/*
 * sf-Smallscreen v1.3b - Provides small-screen compatibility for the jQuery Superfish plugin.
 *
 * Developer's note:
 * Built as a part of the Superfish project for Drupal (http://drupal.org/project/superfish)
 * Found any bug? have any cool ideas? contact me right away! http://drupal.org/user/619294/contact
 *
 * jQuery version: 1.3.x or higher.
 *
 * Dual licensed under the MIT and GPL licenses:
 *  http://www.opensource.org/licenses/mit-license.php
 *  http://www.gnu.org/licenses/gpl.html
  */

(function($){
  $.fn.sfsmallscreen = function(options){
    options = $.extend({
      mode: 'inactive',
      type: 'accordion',
      breakpoint: 768,
      breakpointUnit: 'px',
      useragent: '',
      title: '',
      addSelected: false,
      menuClasses: false,
      hyperlinkClasses: false,
      excludeClass_menu: '',
      excludeClass_hyperlink: '',
      includeClass_menu: '',
      includeClass_hyperlink: '',
      accordionButton: 1,
      expandText: 'Expand',
      collapseText: 'Collapse'
    }, options);

    // We need to clean up the menu from anything unnecessary.
    function refine(menu){
      const refined = menu.clone();
      // Things that should not be in the small-screen menus.
      const rm = refined.find('span.sf-sub-indicator');
      // This is a helper class for those who need to add extra markup that shouldn't exist
      // in the small-screen versions.
      const rh = refined.find('.sf-smallscreen-remove');
      // Mega-menus have to be removed too.
      const mm = refined.find('ul.sf-multicolumn');
      for (let a = 0; a < rh.length; a++){
        rh.eq(a).replaceWith(rh.eq(a).html());
      }
      if (options.accordionButton === 2 || options.type === 'select'){
        for (let b = 0; b < rm.length; b++){
          rm.eq(b).remove();
        }
      }
      if (mm.length > 0){
        mm.removeClass('sf-multicolumn');
        const ol = refined.find('div.sf-multicolumn-column > ol');
        for (let o = 0; o < ol.length; o++){
          ol.eq(o).replaceWith('<ul>' + ol.eq(o).html() + '</ul>');
        }
        const elements = [
          'div.sf-multicolumn-column',
          '.sf-multicolumn-wrapper > ol',
          'li.sf-multicolumn-wrapper'
        ];
        for (let i = 0; i < elements.length; i++){
          let obj = refined.find(elements[i]);
          for (let t = 0; t < obj.length; t++){
            obj.eq(t).replaceWith(obj.eq(t).html());
          }
        }
        refined.find('.sf-multicolumn-column').removeClass('sf-multicolumn-column');
      }
      refined.add(refined.find('*')).css({width:''});
      return refined;
    }

    // Creating <option> elements out of the menu.
    function toSelect(menu, level){
      let items = '';
      const childLI = $(menu).children('li');
      for (let a = 0; a < childLI.length; a++){
        const list = childLI.eq(a)
        const parent = list.children('a, span');
        for (let b = 0; b < parent.length; b++){
          const item = parent.eq(b);
          const path = (item.is('a') && !!item.attr('href')) ? item.attr('href') : '';
          // Class names modification.
          const itemClone = item.clone();
          let classes = (options.hyperlinkClasses) ? ((options.excludeClass_hyperlink && itemClone.hasClass(options.excludeClass_hyperlink)) ? itemClone.removeClass(options.excludeClass_hyperlink).attr('class') : itemClone.attr('class')) : '';
          classes = (options.includeClass_hyperlink && !itemClone.hasClass(options.includeClass_hyperlink)) ? ((options.hyperlinkClasses) ? itemClone.addClass(options.includeClass_hyperlink).attr('class') : options.includeClass_hyperlink) : classes;
          // Retaining the active class if requested.
          if (options.addSelected && item.hasClass('active')){
            classes += ' active';
          }
          classes = classes ? ' class="' + classes + '"' : '';
          // <option> has to be disabled if the item is not a link.
          const disable = path === '' || path === '#' ? ' disabled="disabled"' : '';
          // Crystal clear.
          const subIndicator = 1 < level ? Array(level).join('-') + ' ' : '';
          // Preparing the <option> element.
          items += '<option value="' + path + '"' + classes + disable + '>' + subIndicator + item.text().trim() +'</option>';
          const childUL = list.find('> ul');
          // Using the function for the sub-menu of this item.
          for (let u = 0; u < childUL.length; u++){
            items += toSelect(childUL.eq(u), level + 1);
          }
        }
      }
      return items;
    }

    // Create the new version, hide the original.
    function convert(menu){
      const menuID = menu.attr('id');
      // Creating a refined version of the menu.
      const refinedMenu = refine(menu);
      // Currently, the plugin provides two reactions to small screens.
      // Converting the menu to a <select> element, and converting to an accordion version of the menu.
      if (options.type === 'accordion'){
        const toggleID = menuID + '-toggle';
        const accordionID = menuID + '-accordion';
        // Making sure the accordion does not exist.
        if ($('#' + accordionID).length !== 0){
          return;
        }

        // Getting the style class.
        const styleClass = menu.attr('class').split(' ').filter(function(item){
          return item.indexOf('sf-style-') > -1 ? item : '';
        });
        // Creating the accordion.
        const accordion = $(refinedMenu).attr('id', accordionID);
        // Removing unnecessary classes.
        accordion.removeClass('sf-horizontal sf-vertical sf-navbar sf-shadow sf-js-enabled');
        // Adding necessary classes.
        accordion.addClass('sf-accordion sf-hidden');
        // Removing style attributes and any unnecessary class.
        accordion.find('li').each(function(){
          $(this).removeAttr('style').removeClass('sfHover').attr('id', $(this).attr('id') + '-accordion');
        });
        // Doing the same and making sure all the sub-menus are off-screen (hidden).
        accordion.children('ul').removeAttr('style').not('.sf-hidden').addClass('sf-hidden');
        accordion.find('ul').each(function(){
          $(this).removeAttr('style').not('.sf-hidden').addClass('sf-hidden');
        });

        // Creating the accordion toggle switch.
        const toggle = '<div class="sf-accordion-toggle ' + styleClass + '"><a href="#" id="' + toggleID + '"><span>' + options.title + '</span></a></div>';

        // Adding Expand\Collapse buttons if requested.
        if (options.accordionButton === 2){
          accordion.addClass('sf-accordion-with-buttons');
          const parent = accordion.find('li.menuparent');
          for (let i = 0; i < parent.length; i++){
            parent.eq(i).prepend('<a href="#" class="sf-accordion-button">' + options.expandText + '</a>');
          }
        }
        // Inserting the accordion and hiding the original menu.
        menu.before(toggle).before(accordion).hide();

        const $accordionElement = $('#' + accordionID);
        // Deciding what should be used as accordion buttons.
        const buttonElement = (options.accordionButton < 2) ? 'a.menuparent,span.nolink.menuparent' : 'a.sf-accordion-button';
        const $button = $accordionElement.find(buttonElement);

        // Attaching a click event to the toggle switch.
        $('#' + toggleID).on('click', function(e){
          // Preventing the click.
          e.preventDefault();
          // Adding the sf-expanded class.
          $(this).toggleClass('sf-expanded');

          if ($accordionElement.hasClass('sf-expanded')){
            // If the accordion is already expanded:
            // Hiding its expanded sub-menus and then the accordion itself as well.
            $accordionElement.add($accordionElement.find('li.sf-expanded')).removeClass('sf-expanded')
              .end().children('ul').hide()
              // This is a bit tricky, it's the same trick that has been in use in the main plugin for some time.
              // Basically, we'll add a class that keeps the sub-menu off-screen and still visible,
              // and make it invisible and remove the class one moment before showing or hiding it.
              // This helps screen reader software access all the menu items.
              .end().hide().addClass('sf-hidden').show();
            // Changing the caption of any existing accordion buttons to 'Expand'.
            if (options.accordionButton === 2){
              $accordionElement.find('a.sf-accordion-button').text(options.expandText);
            }
          }
          else {
            // But if it's collapsed,
            $accordionElement.addClass('sf-expanded').hide().removeClass('sf-hidden').show();
          }
        });

        // Attaching a click event to the buttons.
        $button.on('click', function(e){
          // Making sure the button does not exist already.
          if ($(this).closest('li').children('ul').length === 0){
            return;
          }

          e.preventDefault();

          // Selecting the parent menu items.
          const $parent = $(this).closest('li');

          // Creating and inserting Expand\Collapse buttons to the parent menu items,
          // of course only if not already happened.
          if (options.accordionButton === 1 &&
            $parent.children('a.menuparent,span.nolink.menuparent').length > 0 &&
            $parent.children('ul').children('li.sf-clone-parent').length === 0) {

            // Cloning the hyperlink of the parent menu item.
            let cloneLink = $parent.children('a.menuparent').clone();
            // Removing unnecessary classes and element(s).
            cloneLink.removeClass('menuparent sf-with-ul').children('.sf-sub-indicator').remove();
            // Wrapping the hyerplinks in <li>.
            cloneLink = $('<li class="sf-clone-parent" />').html(cloneLink);
            // Adding a helper class and attaching them to the sub-menus.
            $parent.children('ul').addClass('sf-has-clone-parent').prepend(cloneLink);
          }

          if (options.accordionButton === 0 || options.accordionButton === 1){
            // We want to hide a menu, when we click on the menu item with an anchor.
            $accordionElement.find('a.is-active:not(.menuparent)').on('click', function(e){
              $accordionElement.removeClass('sf-expanded').addClass('sf-hidden');
            });
          }

          // Changing the caption of the inserted Collapse link to 'Expand', if any is inserted.
          if (options.accordionButton === 2){
            // We want to hide a menu, when we click on the menu item with an anchor.
            $accordionElement.find('a.is-active').on('click', function(e){
              $accordionElement.removeClass('sf-expanded').addClass('sf-hidden');
            });
          }

          // Once the button is clicked, collapse the sub-menu if it's expanded.
          if ($parent.hasClass('sf-expanded')){
            $parent.children('ul').slideUp('fast', function(){
              // Doing the accessibility trick after hiding the sub-menu.
              $(this).closest('li').removeClass('sf-expanded').end().addClass('sf-hidden').show();
            });
            // Changing the caption of the inserted Collapse link to 'Expand', if any is inserted.
            if (options.accordionButton === 2 && $parent.children('.sf-accordion-button').length > 0){
              $parent.children('.sf-accordion-button').text(options.expandText);
            }
          }
          // Otherwise, expand the sub-menu.
          else {
            // Doing the accessibility trick and then showing the sub-menu.
            $parent.children('ul').hide().removeClass('sf-hidden').slideDown('fast')
              // Changing the caption of the inserted Expand link to 'Collapse' if any is inserted.
              .end().addClass('sf-expanded').children('a.sf-accordion-button').text(options.collapseText)
              // Hiding any expanded sub-menu of the same level.
              .end().siblings('li.sf-expanded').children('ul')
              .slideUp('fast', function(){
                // Doing the accessibility trick after hiding it.
                $(this).closest('li').removeClass('sf-expanded').end().addClass('sf-hidden').show();
              })
              // Assuming Expand\Collapse buttons do exist, resetting captions, in those hidden sub-menus.
              .parent().children('a.sf-accordion-button').text(options.expandText);
          }
        });
      }
      else {
        const menuClone = menu.clone();
        // Class names modification.
        let classes = options.menuClasses ? ((options.excludeClass_menu && menuClone.hasClass(options.excludeClass_menu)) ? menuClone.removeClass(options.excludeClass_menu).attr('class') : menuClone.attr('class')) : '';
        classes = options.includeClass_menu && !menuClone.hasClass(options.includeClass_menu) ? ((options.menuClasses) ? menuClone.addClass(options.includeClass_menu).attr('class') : options.includeClass_menu) : classes;
        classes = classes ? ' class="' + classes + '"' : '';

        // Making sure the <select> element does not exist already.
        if ($('#' + menuID + '-select').length !== 0){
          return;
        }

        // Creating the <option> elements.
        const newMenu = toSelect(refinedMenu, 1);
        // Creating the <select> element and assigning an ID and class name.
        const selectList = $('<select ' + classes + ' id="' + menuID + '-select"/>')
          // Attaching the title and the items to the <select> element.
          .html('<option>' + options.title + '</option>' + newMenu)
          // Attaching an event then.
          .change(function(){
            // Except for the first option that is the menu title and not a real menu item.
            if ($('option:selected', this).index()){
              window.location = selectList.val();
            }
          });
        // Applying the addSelected option to it.
        if (options.addSelected){
          selectList.find('.active').attr('selected', !0);
        }
        // Finally inserting the <select> element into the document then hiding the original menu.
        menu.before(selectList).hide();
      }
    }

    // Turn everything back to normal.
    function turnBack(menu){
      const id = '#' + menu.attr('id');
      // Removing the small screen version.
      $(id + '-' + options.type).remove();
      // Removing the accordion toggle switch as well.
      if (options.type === 'accordion'){
        $(id + '-toggle').parent('div').remove();
      }
      // Remove inline CSS display property; less clear than simply using .show(), but respects stylesheet
      $(id).css('display', '');
    }

    // Return an original object to support chaining.
    // Although this is unnecessary because of the way the module uses these plugins.
    for (let s = 0; s < this.length; s++){
      const menu = $(this).eq(s);
      const mode = options.mode;
      // The rest is crystal clear, isn't it? :)
      if (menu.children('li').length === 0){
        // Skip an empty menu which will not be visible and don't want to suddenly make it visible.
      }
      else if (mode === 'always_active'){
        convert(menu);
      }
      else if (mode === 'window_width'){
        const breakpoint = options.breakpointUnit === 'em' ? options.breakpoint * parseFloat($('body').css('font-size')) : options.breakpoint;
        const windowWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
        let timer;
        if ((typeof Modernizr === 'undefined' || typeof Modernizr.mq !== 'function') && windowWidth < breakpoint){
          convert(menu);
        }
        else if (typeof Modernizr !== 'undefined' && typeof Modernizr.mq === 'function' && Modernizr.mq('(max-width:' + (breakpoint - 1) + 'px)')) {
          convert(menu);
        }

        $(window).resize(function(){
          clearTimeout(timer);
          timer = setTimeout(function(){
            const windowWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
            if ((typeof Modernizr === 'undefined' || typeof Modernizr.mq !== 'function') && windowWidth < breakpoint){
              convert(menu);
            }
            else if (typeof Modernizr !== 'undefined' && typeof Modernizr.mq === 'function' && Modernizr.mq('(max-width:' + (breakpoint - 1) + 'px)')) {
              convert(menu);
            }
            else {
              turnBack(menu);
            }
          }, 50);
        });
      }
      else if (mode === 'useragent_custom'){
        if (options.useragent !== ''){
          const ua = RegExp(options.useragent, 'i');
          if (navigator.userAgent.match(ua)){
            convert(menu);
          }
        }
      }
      else if (mode === 'useragent_predefined' && navigator.userAgent.match(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od|ad)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i)){
        convert(menu);
      }
    }
    return this;
  }
})(jQuery);
