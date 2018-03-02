<?php

namespace Drupal\webmention_io\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\webmention_io\Entity\WebmentionEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class WebmentionIoController extends ControllerBase {

  /**
   * Routing callback: receive webmentions and pingbacks from Webmention.io.
   */
  public function endpoint(Request $request) : JsonResponse {
    $valid = FALSE;

    // Default response code and message.
    $response_code = 400;
    $response_message = 'Bad request';

    $mention = $this->generateMentionArrayFromRequest($request);
    if (!empty($mention)) {
      $valid = TRUE;
    }

    // We have a valid mention.
    if ($valid) {

      // Debug.
      if (Settings::get('webmention_io_log_payload', FALSE)) {
        $this->getLogger('webmention_io')->notice('object: @object', ['@object' => print_r($mention, 1)]);
      }

      $response_code = 202;
      $response_message = 'Webmention was successful';

      $mention['user_id'] = Settings::get('webmention_io_uid', 1);
      $mention['target'] = ['value' => str_replace(\Drupal::request()->getSchemeAndHttpHost(), '', $mention['target'])];

      // Save the entity.
      try {
        $webmention = WebmentionEntity::createFromArray($mention);
        $webmention->save();
      }
      catch (\Exception $exception) {
        return new JsonResponse($exception->getMessage(), '400');
      }
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

  /**
   * Extracts an array valid for a mention from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array
   */
  protected function generateMentionArrayFromRequest(Request $request) : array {
    // Check if there's any input from the webhook.
    $input = $request->getContent();
    $input = is_array($input) ? array_shift($input) : '';
    $mention = json_decode($input, TRUE);

    // Check if this is a forward pingback, which is a POST request.
    if (empty($mention) && ($request->request->get('source') && $request->request->get('target'))) {
      if ($this->validateSource($_POST['source'], $_POST['target'])) {
        $mention = [];
        $mention['source'] = $_POST['source'];
        $mention['post'] = [];
        $mention['post']['type'] = 'pingback';
        $mention['post']['wm-property'] = 'pingback';
        $mention['target'] = $_POST['target'];
      }
    }

    // Debug.
    if (Settings::get('webmention_io_log_payload', FALSE)) {
      $this->getLogger('webmention_io')
        ->notice('input: @input', ['@input' => print_r($input, 1)]);
    }

    return $mention;
  }

}
