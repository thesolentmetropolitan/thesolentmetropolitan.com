
https://g.co/gemini/share/fce1c5dc463d


• Details

Gemini Apps
Prompted building me a website menu with 2 levels, first level is always visible and has three categories: Culture, Sectors and Living. No 3rd-party javascript libraries, bespoke JS. Then when one of these is clicked on it reveals further options that push down the page beneath. For Culture, these options: Art & Design Science & Tech Forums Community Dance Fashion Food & Drink Film Heritage Makers & Crafts Music Enthusiasts' Clubs Podcasts & Radio Sport Streaming & TV Theatre & Stage Writing & Blogs For Sectors, these options: Charity & Volunteering Construction Creative & Performance Crafts Education Environment Engineering & Manufacturing Event & Venue Farming Finance & Legal Food & Drink Health & Care Hospitality Infrastructure & Logistics Lifestyle Maritime Marketing & PR Media Professional Services Property & Trades Retail Science & Tech Transport Vehicle & Machinery For Living, these options: Health Mental Health Fitness Family Home & Garden Learning & Education Outreach Advice

Details

event

Today at 18:08

apps

Gemini Apps

chat

Here is a complete, ready-to-use HTML, CSS, and JavaScript solution for a two-level website menu. The primary navigation is always visible, and clicking a category reveals its sub-menu, pushing the rest of the page content down. The solution uses only bespoke JavaScript and does not require any third-party libraries.

HTML Structure

The HTML uses a standard nav element with an unordered list (<ul>) for the main categories and nested unordered lists for the sub-menus. Each sub-menu is initially hidden.


```
<nav class="main-nav">
  <ul>
    <li>
      <a href="#" data-menu-id="culture-menu">Culture</a>
      <ul class="sub-menu" id="culture-menu">
        <li><a href="#">Art & Design</a></li>
        <li><a href="#">Science & Tech Forums</a></li>
        <li><a href="#">Community</a></li>
        <li><a href="#">Dance</a></li>
        <li><a href="#">Fashion</a></li>
        <li><a href="#">Food & Drink</a></li>
        <li><a href="#">Film</a></li>
        <li><a href="#">Heritage</a></li>
        <li><a href="#">Makers & Crafts</a></li>
        <li><a href="#">Music</a></li>
        <li><a href="#">Enthusiasts' Clubs</a></li>
        <li><a href="#">Podcasts & Radio</a></li>
        <li><a href="#">Sport</a></li>
        <li><a href="#">Streaming & TV</a></li>
        <li><a href="#">Theatre & Stage</a></li>
        <li><a href="#">Writing & Blogs</a></li>
      </ul>
    </li>
    <li>
      <a href="#" data-menu-id="sectors-menu">Sectors</a>
      <ul class="sub-menu" id="sectors-menu">
        <li><a href="#">Charity & Volunteering</a></li>
        <li><a href="#">Construction</a></li>
        <li><a href="#">Creative & Performance</a></li>
        <li><a href="#">Crafts</a></li>
        <li><a href="#">Education</a></li>
        <li><a href="#">Environment</a></li>
        <li><a href="#">Engineering & Manufacturing</a></li>
        <li><a href="#">Event & Venue</a></li>
        <li><a href="#">Farming</a></li>
        <li><a href="#">Finance & Legal</a></li>
        <li><a href="#">Food & Drink</a></li>
        <li><a href="#">Health & Care</a></li>
        <li><a href="#">Hospitality</a></li>
        <li><a href="#">Infrastructure & Logistics</a></li>
        <li><a href="#">Lifestyle</a></li>
        <li><a href="#">Maritime</a></li>
        <li><a href="#">Marketing & PR</a></li>
        <li><a href="#">Media</a></li>
        <li><a href="#">Professional Services</a></li>
        <li><a href="#">Property & Trades</a></li>
        <li><a href="#">Retail</a></li>
        <li><a href="#">Science & Tech</a></li>
        <li><a href="#">Transport</a></li>
        <li><a href="#">Vehicle & Machinery</a></li>
      </ul>
    </li>
    <li>
      <a href="#" data-menu-id="living-menu">Living</a>
      <ul class="sub-menu" id="living-menu">
        <li><a href="#">Health</a></li>
        <li><a href="#">Mental Health</a></li>
        <li><a href="#">Fitness</a></li>
        <li><a href="#">Family</a></li>
        <li><a href="#">Home & Garden</a></li>
        <li><a href="#">Learning & Education</a></li>
        <li><a href="#">Outreach</a></li>
        <li><a href="#">Advice</a></li>
      </ul>
    </li>
  </ul>
</nav>

<div class="content">
  <p>This is the main content of the page. It will be pushed down when a sub-menu is opened.</p>
</div>
```


CSS Styling
The CSS sets up the menu's appearance and handles the initial state of the sub-menus, which are hidden using display: none. The .active class is a crucial part of the JavaScript, as it will be toggled to show or hide the sub-menus.


```
body {
  font-family: sans-serif;
  margin: 0;
  padding: 20px;
}

.main-nav ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex; /* Makes the main categories horizontal */
  gap: 20px;
  background-color: #f0f0f0;
  padding: 10px;
  border-radius: 5px;
}

.main-nav li {
  position: relative; /* Essential for positioning the sub-menu if you wanted a dropdown, but here it's for containment */
}

.main-nav a {
  text-decoration: none;
  color: #333;
  padding: 10px 15px;
  display: block;
  font-weight: bold;
}

.main-nav a:hover {
  background-color: #e0e0e0;
  border-radius: 5px;
}

.sub-menu {
  list-style: none;
  padding: 0;
  margin: 10px 0 0;
  background-color: #f9f9f9;
  border: 1px solid #ccc;
  border-top: none;
  border-radius: 0 0 5px 5px;
  display: none; /* Initially hides the sub-menu */
  width: 100%;
}

.sub-menu.active {
  display: block; /* Shows the sub-menu when the 'active' class is added by JavaScript */
}

.sub-menu li a {
  font-weight: normal;
  padding: 8px 15px;
}
```

JavaScript Logic

The JavaScript listens for clicks on the main menu links. When a link is clicked, it first closes any currently open sub-menu. Then, it finds the correct sub-menu using the data-menu-id attribute and toggles the .active class to either show or hide it. This ensures that only one sub-menu is open at a time.

```
document.addEventListener('DOMContentLoaded', () => {
  const mainNavLinks = document.querySelectorAll('.main-nav > ul > li > a');

  mainNavLinks.forEach(link => {
    link.addEventListener('click', (event) => {
      event.preventDefault(); // Prevents the link from navigating

      const menuId = event.target.dataset.menuId;
      const subMenu = document.getElementById(menuId);

      // Close all other sub-menus
      document.querySelectorAll('.sub-menu.active').forEach(openMenu => {
        if (openMenu.id !== menuId) {
          openMenu.classList.remove('active');
        }
      });

      // Toggle the 'active' class on the clicked sub-menu
      if (subMenu) {
        subMenu.classList.toggle('active');
      }
    });
  });
});
```

This solution is a simple and effective way to build a two-level menu without external libraries.
