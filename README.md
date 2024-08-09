# Helsingborg API Event Manager

This plugin is an LTS version of the Helsingborg API Event Manager plugin. It is
a WordPress plugin that turns your WordPress site into an event manager. It
allows you to create and manage events on your site and then import them on
other sites.

## Installation

1. Add the following to your `composer.json` file:
   ```json
   {
     "repositories": [
       {
         "type": "vcs",
         "url": "https://github.com/municipio-lts/wp-plugin-hbg-event-manager-2024.git",
         "only": [
           "municipio-lts/wp-plugin-hbg-event-manager-2024"
         ],
         "no-api": true
       },
     ]
   }
   ```
2. Install the package and its dependencies:
   ```bash
   composer require municipio-lts/wp-plugin-hbg-event-manager-2024:dev-lts/v1.4.3 giggsey/libphonenumber-for-php:^7.4
   ```
3. Activate the plugin in WordPress.
