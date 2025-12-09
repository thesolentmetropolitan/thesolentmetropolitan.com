That's a great choice. Using Drush with a custom script is the most efficient and scalable way to handle bulk content and menu creation in Drupal. It leverages Drupal's core services directly.

Here is an example of the workflow and the necessary PHP code you would run using Drush's `scr` (script) command. This process assumes you have a simple list of page titles you want to create.

-----

## 💻 Drush Script for Content and Menu Automation

### 1\. The Input List (Your Menu Items)

First, create an array in your script that holds the data for the pages you want to create.

```php
$pages_to_create = [
  'Our Mission',
  'Company Values',
  'Team Directory',
  'Partnerships',
  'Careers',
];

// Set a few necessary variables
$content_type = 'basic_page'; // The machine name of your content type
$menu_name = 'main'; // The machine name of your menu (e.g., 'main' or 'main-navigation')
$user_id = 1; // The UID of the content author
```

### 2\. The PHP Script (`create_content_and_menu.php`)

You will write a PHP script that iterates through this list, creates a Drupal node (content), and then immediately creates a menu link that points to that node.

```php
<?php

// Use the necessary entity classes from Drupal
use Drupal\node\Entity\Node;
use Drupal\menu_link_content\Entity\MenuLinkContent;

// --- Input Data from Step 1 ---
$pages_to_create = [
  'Our Mission',
  'Company Values',
  'Team Directory',
  'Partnerships',
  'Careers',
];
$content_type = 'basic_page';
$menu_name = 'main';
$user_id = 1;
// ------------------------------

$weight = 0; // Starting weight for menu items

foreach ($pages_to_create as $title) {
  // 1. Create the Content Node
  $node = Node::create([
    'type' => $content_type,
    'title' => $title,
    'uid' => $user_id,
    'status' => 1, // 1 for published, 0 for unpublished
    'body' => [
      'value' => "This is the content for the **$title** page. It was created automatically by a Drush script.",
      'format' => 'basic_html', // Ensure this is a valid text format
    ],
  ]);

  $node->save();

  // Get the internal URI for the new node
  $node_uri = 'internal:/node/' . $node->id();

  // 2. Create the Menu Link
  $menu_link = MenuLinkContent::create([
    'title' => $title,
    'link' => ['uri' => $node_uri],
    'menu_name' => $menu_name,
    'weight' => $weight,
    'enabled' => 1,
  ]);

  $menu_link->save();

  // Print confirmation
  echo "✅ Created Node: '{$title}' (ID: {$node->id()}) and Menu Link.\n";
  
  $weight++; // Increment weight so the next link appears after the current one
}

// 3. Clear Caches
\Drupal::service('cache_clearer')->clear();
echo "Caches cleared. Your new content and menu links should now be visible.\n";

?>
```

### 3\. Execution via Drush

Save the file (e.g., as `create_content_and_menu.php`) in a location accessible from your Drupal root (a common spot is in a custom module directory or the site root). Then, execute it from your command line:

```bash
drush scr create_content_and_menu.php
```

Drush will bootstrap Drupal, run the PHP script, and you will see the confirmation messages for each node and menu link created.

### Summary of Drupal Services Used

| Action | Drupal Service/Class | Purpose |
| :--- | :--- | :--- |
| **Create Content** | `Drupal\node\Entity\Node::create()` | Creates and populates the actual content page (Node). |
| **Link to Menu** | `Drupal\menu_link_content\Entity\MenuLinkContent::create()` | Creates the specific menu item, linking its URI to the newly created node. |
| **Finalize** | `\Drupal::service('cache_clearer')->clear()` | Ensures the menu and content caches are updated so the items appear immediately on the front-end. |

This method is highly robust and is the standard practice for automated content generation in modern Drupal development.

-----

The video below explains how to use Drush's `generate` command to create boilerplate code for modules, which is a key part of creating custom Drush commands for more complex automation tasks.

[How to Generate Configuration Form for Drupal Module using Drush](https://www.youtube.com/watch?v=oE5cFlB3Lho)

http://googleusercontent.com/youtube_content/0
