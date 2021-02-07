# OpenReporter
Version: **v0.01**

OpenReporter is an open-source app that helps organizations mobilize their community via geo-centric reporting. OpenReporter is built on top of [Drupal 9](https://www.drupal.org/about/9) as a pre-packaged distribution making it extendable like any other Drupal project.

## System requirements:
- PHP 7.3 +
- Apache 2.4.7+ or Nginx 0.7.x+
- MySQL 5.7.8+ 
- [Composer](https://getcomposer.org) 
- [Google MAP API Key](https://developers.google.com/maps/documentation/javascript/get-api-key)

## How to install:
- Grab a copy of the [most recent release](https://github.com/digitalconfection/OpenReporter/releases/) and copy it to a location on your server.
- Configure the domain or subdomain so that the webroot points to `openreporter/web`.
- Create a database in MYSQL
- Navigate to your domain and follow the onscreen installation process.

## After installation:
- Login with the admin user and navigate to `admin/config/system/geofield_map_settings` and add your Google API Key.
- Go to `admin/config/system/site-information` to change any site configurations name and email.
- Go to `admin/config/people/accounts` to change any welcome message text.

## Updating and saving configuration:
When new updates are released to ensure that your site configuration is not overwritten, go to `admin/config/development/configuration` and export your site config as tar.gz. After updating, re-import your config. Revert the items that you have changed and accept the incoming imports. 

# Support
Running into an issue? Open an [Issue](https://github.com/digitalconfection/openreporter/issues) or reach out to us on [Twitter](https://twitter.com/openreporter).
