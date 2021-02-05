CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Recommended modules
 * Requirements
 * Installation
 * Configuration
 * Maintainers

INTRODUCTION
------------

Comment Notify is a lightweight tool to send notification e-mails to visitors about new,
published comments on pages where they have commented.
Comment Notify works for both registered and anonymous users.
Providing comment notifications for anonymous users is an important tool
in bringing anonymous users back to your site, which helps convert anonymous
users to registered users. Anonymous comment notification is a critical tool in
building a blog comment community; all the major blogging platforms include this
functionality.

 * For a full description of the module visit:
   https://www.drupal.org/project/comment_notify

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/comment_notify


RECOMMENDED MODULES
-------------------

 * [Queue Mail](http://drupal.org/project/queue_mail) - For better performance when submitting new comments.

REQUIREMENTS
------------

This module requires the following modules:

 * [Token](https://www.drupal.org/project/token)

INSTALLATION
------------

Install the Comment Notify module as you would normally install a contributed Drupal
module. Visit https://www.drupal.org/node/1897420 for further information.

CONFIGURATION
-------------

1. Enable the module from the Modules admin page from "Modules"
 - /admin/modules

2. Grant permission to use this module from "People > Permissions"
 - /admin/people/permissions#module-comment_notify

3. Set permissions for commenting as per usual from "People > Permissions"
 - /admin/people/permissions#module-comment

4. Configure the settings for comments for content types from Structure
   > Content types > [Your content type] > Manage fields > Edit your comment field.
 - /admin/structure/types/manage/[your_content_type]/fields/[your.comment.field]
 - e.g. /admin/structure/types/manage/article/fields/node.article.comment

 - Look for "Anonymous commenting" and set to either:
     "Anonymous posters may leave their contact information" OR
     "Anonymous posters must leave their contact information"

5. Configure this module at Configuration > People > Comment Notify
  - /admin/config/people/comment_notify

  -Determine which content types to activate it for
  -Determine which subscription modes are allowed
  -Configure the templates for the e-mails

6. Set your node-notify settings per user (optional)

The module includes a feature to notify the node author of all comments on
their nodes. To enable this go to "My account" > Edit (e.g. user/1/edit)
and change the settings there, i.e., "Comment follow-up notification settings"


MAINTAINERS
-----------

 * Greg Knaddison (greggles) - https://www.drupal.org/u/greggles

Supporting organizations:

 * MD Systems - https://www.drupal.org/md-systems
 * Agaric - https://www.drupal.org/agaric
