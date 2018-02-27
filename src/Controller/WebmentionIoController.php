<?php

namespace Drupal\webmention_io\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\JsonResponse;

class WebmentionIoController extends ControllerBase {

  /**
   * Routing callback: receive webmentions and pingbacks from Webmention.io.
   */
  public function endpoint() {
    $valid = FALSE;

    // Default response code and message.
    $response_code = 400;
    $response_message = 'Bad request';

    // Check if there's any input from the webhook.
    // TODO I need to actually receive a webhook request to validate all this
    // code, this was done by testing with a REST client.
    $input = file('php://input');
    $input = is_array($input) ? array_shift($input) : '';
    $mention = json_decode($input, TRUE);

    // Check if this is a forward pingback, which is a POST request.
    if (empty($mention) && (!empty($_POST['source']) && !empty($_POST['target']))) {
      if ($this->validateSource($_POST['source'], $_POST['target'])) {
        $valid = TRUE;
        $mention = [];
        $mention['secret'] = '';
        $mention['source'] = $_POST['source'];
        $mention['post'] = [];
        $mention['post']['type'] = 'pingback';
        $mention['post']['wm-property'] = 'pingback';
        $mention['target'] = $_POST['target'];
      }
    }
    else {
      $valid = TRUE;
    }

    // Debug.
    if (Settings::get('webmention_io_log_payload', FALSE)) {
      $this->getLogger('webmention_io')->notice('input: @input', ['@input' => print_r($input, 1)]);
    }

    // We have a valid mention.
    if (!empty($mention) && $valid) {

      // Debug.
      if (Settings::get('webmention_io_log_payload', FALSE)) {
        $this->getLogger('webmention_io')->notice('object: @object', ['@object' => print_r($mention, 1)]);
      }

      $response_code = 202;
      $response_message = 'Webmention was successful';

      $values = [
        'title' => 'Backlink: ' . $mention['source'],
        'type' => 'backlinks',
        'uid' => Settings::get('webmention_io_uid', 1),
        // Remove the base url.
        'field_webmention_target' => ['value' => str_replace(\Drupal::request()->getSchemeAndHttpHost(), '', $mention['target'])],
        'field_webmention_source' => ['value' => $mention['source']],
        'field_webmention_type' => ['value' => $mention['post']['type']],
        'field_webmention_property' => ['value' => $mention['post']['wm-property']]
      ];

      // Secret field.
      if (!empty($mention['secret'])) {
        $values['field_webmention_secret'] = ['value' => $mention['secret']];
      }

      // Author info.
      foreach (['name', 'photo', 'url'] as $key) {
        if (!empty($mention['post']['author'][$key])) {
          $values['field_webmention_author_' . $key] = ['value' => $mention['post']['author'][$key]];
        }
      }

      // Save the node.
      $node = $this->entityTypeManager()->getStorage('node')->create($values);
      $node->save();
    }

    $response = ['result' => $response_message];
    return new JsonResponse($response, $response_code);
  }

  /**
   * Validates that target is linked on source.
   *
   * @param $source
   * @param $target
   *
   * @return bool
   */
  protected function validateSource($source, $target) {
    $valid = FALSE;

    $content = file_get_contents($source, $target);
    if ($content && strpos($content, $target) !== FALSE) {
      $valid = TRUE;
    }

    return $valid;
  }

}
