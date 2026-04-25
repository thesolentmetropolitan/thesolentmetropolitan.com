https://github.com/thesolentmetropolitan/thesolentmetropolitan.com/issues/181

The content of the above GitHub issue ticket is as follows:

Display a list of articles on the front page.

Pre-requisites - developer to do:

Use articles Drupal view /admin/structure/views/view/articles , config: config/sync/views.view.articles.yml and teaser view mode: config/sync/core.entity_view_display.node.article.teaser.yml

Make use of the exisiting Paragraph Type view_display to show the view display.

The View to use is Articles and the View Display to show is articles_front.

Then, work for Claude code:

Style the list:

and link styled according to design.
Pagination as advised to Claude Code in prompt, with example.

For this work, update as required:

web/themes/custom/customsolent/templates/content/node--article--teaser.html.twig
web/themes/custom/customsolent/css/node.css
web/themes/custom/customsolent/templates/content/paragraph--view.html.twig - this is for outputting a view display using the viewsreference field provided by the module of the same name. CSS will be needed to style this.

