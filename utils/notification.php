<?php

/**
 * Our custom notification helper function. Use this to add messages to the 
 * notifications heap during execution of your theme/function code
 */
function custom_notification_helper($message, $type)
{

  if (!is_admin()) {
    return false;
  }

  // todo: check these are valid
  if (!in_array($type, array('error', 'info', 'success', 'warning'))) {
    return false;
  }

  // Store/retrieve a transient associated with the current logged in user
  $transientName = 'admin_custom_notification_' . get_current_user_id();

  // Check if this transient already exists. We can use this to add
  // multiple notifications during a single pass through our code
  $notifications = get_transient($transientName);

  if (!$notifications) {
    $notifications = array(); // initialise as a blank array
  }

  $notifications[] = array(
    'message' => $message,
    'type' => $type
  );

  set_transient($transientName, $notifications);  // no need to provide an expiration, will
  // be removed immediately

}

/**
 * The handler to output our admin notification messages
 */
function custom_admin_notice_handler()
{

  if (!is_admin()) {
    // Only process this when in admin context
    return;
  }

  $transientName = 'admin_custom_notification_' . get_current_user_id();

  // Check if there are any notices stored
  $notifications = get_transient($transientName);

  if ($notifications) :
    foreach ($notifications as $notification) :
      echo <<<HTML

              <div class="notice notice-custom notice-{$notification['type']} is-dismissible">
                  <p>{$notification['message']}</p>
              </div>

HTML;
    endforeach;
  endif;

  // Clear away our transient data, it's not needed any more
  delete_transient($transientName);
}

add_action('admin_notices', 'custom_admin_notice_handler');
