# Webmention.io integration for Drupal

## About Webmention.io

Webmention.io is a hosted service created to easily handle webmentions (and legacy pingbacks) on any web page. This Drupal 8 modules exposes an endpoint (/webmention/notify) to receive pingbacks and webmentions via this service. Pingbacks are also validated to make sure that the source URL has a valid link to the target.

You need an account at https://webmention.io. As soon as one webmention is recorded, you can set the the webhook to http://your_domain/webmention/notify.

## Installation

- Go to admin/modules and toggle 'Webmention IO'
- Add the webmention header tags to html.html.twig (or use hooks to only add these head tags on certain pages).

 ```
  <link rel="pingback" href="https://webmention.io/webmention?forward=http://your_domain/webmention/notify" />
  <link rel="webmention" href="https://webmention.io/your_domain/webmention" />
  ```

- Pingbacks and webmentions are stored in a content type callback 'Backlinks' as user 1.
  An overview of collected links is available at admin/content/backlinks. Configure admin/content to exclude them from
  that view (if wanted).

## Configuration

Two settings can be configured by adding lines to settings.php

  - Logging the payload in watchdog:

  ```
  $settings['webmention_io_log_payload'] = TRUE;
  ```

  - Assigning a different user id for the backlink:

  ```
  $settings['webmention_io_uid'] = 321;
  ```
