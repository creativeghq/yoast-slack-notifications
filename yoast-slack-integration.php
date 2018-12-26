<?php
/*
Plugin Name: Yoast Slack Notification for Metafield Changes
Plugin URI: https://creativeg.gr
Description: When ever someone does a change on any post or page, get notified to Slack for the change with a Before and After preview!
Version: 1.0.1
Author: Basilis Kanonidis
Author URI: https://creativeg.gr
Requires at least: 3.9.1
Tested up to: 4.1
Text Domain:
 */
include_once plugin_dir_path(__FILE__) . '/framework/init.php';
class Yoast_SlackNotification
{
    public function __construct()
    {
        add_action('init', array($this, 'get_post_detail'));
        add_action('save_post', array($this, 'save_post'));
        add_action('init', array($this, 'admin_options'));
    }

    public function get_post_detail()
    {
        if (is_admin()) {
            if (!$_POST && $_GET['post']) {
                $postId         = $_GET['post'];
                $title          = stripslashes(get_post_meta($postId, '_yoast_wpseo_title', true));
                
                $meta           = stripslashes(get_post_meta($postId, '_yoast_wpseo_metadesc', true));
                $robot_noindex  = stripslashes(get_post_meta($postId, '_yoast_wpseo_meta-robots-noindex', true));
                $robot_adv      = stripslashes(get_post_meta($postId, '_yoast_wpseo_meta-robots-adv', true));
                $robot_nofollow = stripslashes(get_post_meta($postId, '_yoast_wpseo_meta-robots-nofollow', true));
                $canonical      = stripslashes(get_post_meta($postId, '_yoast_wpseo_canonical', true));
                $_SESSION['yoast_slack_title_'.$postId] = $title;
                $_SESSION['yoast_slack_meta_'.$postId] = $meta;
                $_SESSION['yoast_slack_robot_noindex_'.$postId] = $robot_noindex;
                $_SESSION['yoast_slack_robot_adv_'.$postId] = $robot_adv;
                $_SESSION['yoast_slack_robot_nofollow_'.$postId] = $robot_nofollow;
                $_SESSION['yoast_slack_canonical_'.$postId] = $canonical;

                // update_option('yoast_slack_title', array('title' => $title, 'pid' => $postId));
                // update_option('yoast_slack_meta', array('meta' => $meta, 'pid' => $postId));
                // update_option('yoast_slack_robot_noindex', array('robot_noindex' => $robot_noindex, 'pid' => $postId));
                
                // update_option('yoast_slack_robot_adv', array('robot_adv'=>$robot_adv, 'pid'=>$postId));
                // update_option('yoast_slack_robot_nofollow', array('robot_nofollow'=>$robot_nofollow, 'pid'=>$postId));
                // update_option('yoast_slack_canonical', array('canonical'=>$canonical, 'pid'=>$postId));
            }
        }

    }

    public function save_post($postId)
    {
        if (is_admin()) {
        	
            $hook_url        = wp_get_page_field_value('yoast-serp-slack-settings', 'yoast_slack_webhook_url');
            $slack_channel   = wp_get_page_field_value('yoast-serp-slack-settings', 'yoast_slack_channel');
            $slack_username  = wp_get_page_field_value('yoast-serp-slack-settings', 'yoast_slack_username');

            // $prev_post_title = get_option('yoast_slack_title');
            $prev_post_title = $_SESSION['yoast_slack_title_'.$postId];
            $current_user    = wp_get_current_user();
            $permalink       = get_permalink($postId);
            if (($prev_post_title || $_POST['yoast_wpseo_title']) && $prev_post_title != $_POST['yoast_wpseo_title']) {
                $text = "Yoast title changed by user " . $current_user->user_firstname . ' ' . $current_user->user_lastname . " at " . $permalink . "\n";
                $text .= "*Previous Title:* \n";
                $text .= $prev_post_title . "\n";
                $text .= "*New Title:* \n";
                $text .= $_POST['yoast_wpseo_title'] . "\n";

                $this->postToSlack($hook_url, $text, $slack_channel, $slack_username);
            }

            // $prev_post_desc = get_option('yoast_slack_meta');
            $prev_post_desc = $_SESSION['yoast_slack_meta_'.$postId];
            if (($prev_post_desc || $_POST['yoast_wpseo_metadesc']) && $prev_post_desc != $_POST['yoast_wpseo_metadesc']) {
                $text_desc = "Yoast meta description changed by user " . $current_user->user_firstname . ' ' . $current_user->user_lastname . " at " . $permalink . "\n";
                $text_desc .= "*Previous Meta Description:* \n";
                $text_desc .= $prev_post_desc . "\n";
                $text_desc .= "*New Meta Description:* \n";
                $text_desc .= $_POST['yoast_wpseo_metadesc'] . "\n";

                $this->postToSlack($hook_url, $text_desc, $slack_channel, $slack_username);
            }

            // $prev_post_canonical = get_option('yoast_slack_canonical');
            $prev_post_canonical = $_SESSION['yoast_slack_canonical_'.$postId];
            if (($prev_post_canonical || $_POST['yoast_wpseo_canonical']) && $prev_post_canonical != $_POST['yoast_wpseo_canonical']) {
                $text_desc = "Yoast canonical changed by user " . $current_user->user_firstname . ' ' . $current_user->user_lastname . " at " . $permalink . "\n";
                $text_desc .= "*Previous:* \n";
                $text_desc .= $prev_post_canonical . "\n";
                $text_desc .= "*New:* \n";
                $text_desc .= $_POST['yoast_wpseo_canonical'] . "\n";

                $this->postToSlack($hook_url, $text_desc, $slack_channel, $slack_username);
            }

            // $prev_post_adv = get_option('yoast_slack_robot_adv');
            $prev_post_adv = $_SESSION['yoast_slack_robot_adv_'.$postId];
            if (($prev_post_adv || $_POST['yoast_wpseo_meta-robots-adv']) && $prev_post_adv != implode(',', $_POST['yoast_wpseo_meta-robots-adv'])) {
                $text_desc = "Yoast robots advanced changed by user " . $current_user->user_firstname . ' ' . $current_user->user_lastname . " at " . $permalink . "\n";
                $text_desc .= "*Previous:* \n";
                $text_desc .= $prev_post_adv . "\n";
                $text_desc .= "*New:* \n";
                $text_desc .= implode(',', $_POST['yoast_wpseo_meta-robots-adv']) . "\n";

                $this->postToSlack($hook_url, $text_desc, $slack_channel, $slack_username);
            }

            // $prev_post_nofollow = get_option('yoast_slack_robot_nofollow');
            $prev_post_nofollow = $_SESSION['yoast_slack_robot_nofollow_'.$postId];

            if (($_POST['yoast_wpseo_meta-robots-nofollow'] || $prev_post_nofollow) && $prev_post_nofollow != $_POST['yoast_wpseo_meta-robots-nofollow']) {

                if ($prev_post_nofollow == 1) {
                    $prev_post_nofollow = 'Yes';
                } else {
                    $prev_post_nofollow = 'No';
                }

                if ($_POST['yoast_wpseo_meta-robots-nofollow'] == 1) {
                    $_POST['yoast_wpseo_meta-robots-nofollow'] = 'Yes';
                } else {
                    $_POST['yoast_wpseo_meta-robots-nofollow'] = 'No';
                }

                $text_desc = "Yoast robots no follow changed by user " . $current_user->user_firstname . ' ' . $current_user->user_lastname . " at " . $permalink . "\n";
                $text_desc .= "*Previous:* \n";
                $text_desc .= $prev_post_nofollow . "\n";
                $text_desc .= "*New:* \n";
                $text_desc .= $_POST['yoast_wpseo_meta-robots-nofollow'] . "\n";

                $this->postToSlack($hook_url, $text_desc, $slack_channel, $slack_username);
            }


            // $prev_post_no_index = get_option('yoast_slack_robot_noindex');
            $prev_post_no_index = $_SESSION['yoast_slack_robot_noindex_'.$postId];
            if ($prev_post_no_index != $_POST['yoast_wpseo_meta-robots-noindex']) {

                $prev_robot_value = 'Default';
                if ($prev_post_no_index == 2) {
                    $prev_robot_value = 'Index';
                } else if ($prev_post_no_index == 1) {
                    $prev_robot_value = 'No Index';
                }

                $post_robot_value = 'Default';
                if ($_POST['yoast_wpseo_meta-robots-noindex'] == 2) {
                    $post_robot_value = 'Index';
                } else if ($_POST['yoast_wpseo_meta-robots-noindex'] == 1) {
                    $post_robot_value = 'No Index';
                }

                $text_desc = "Yoast robots no index value changed by user " . $current_user->user_firstname . ' ' . $current_user->user_lastname . " at " . $permalink . "\n";
                $text_desc .= "*Previous Value:* \n";
                $text_desc .= $prev_robot_value . "\n";
                $text_desc .= "*New value:* \n";
                $text_desc .= $post_robot_value . "\n";

                $this->postToSlack($hook_url, $text_desc, $slack_channel, $slack_username);
            }
        }
    }

    public function admin_options()
    {
        $page_with_tabs = wp_create_admin_page([
            'menu_name' => 'Yoast Serp Slack Settings',
            'id'        => 'yoast-serp-slack-settings',
            'prefix'    => 'sss_',
            'icon'      => 'dashicons-share-alt',
        ]);
        $page_with_tabs->set_tab([
            'id'   => 'default',
            'name' => 'Settings',
        ]);

        // creates a text field

        // creates a text field
        $page_with_tabs->add_field([
            'type'  => 'text',
            'id'    => 'yoast_slack_webhook_url',
            'label' => 'Slack Webhook url',
            'desc'  => 'You must first <a href="https://my.slack.com/services/new/incoming-webhook/" target="_blank">set up an incoming webhook integration in your Slack account</a>. <br>Once you select a channel (which you can override below),<br> click the button to add the integration, copy the provided webhook URL, and paste the URL in the box above.',
            'props' => [
                // optional tag properties
                'placeholder' => '',
            ],
            //'default' => 'hello world',
        ]);
        $page_with_tabs->add_field([
            'type'    => 'text',
            'id'      => 'yoast_slack_channel',
            'label'   => 'Slack channel',
            'desc'    => 'Incoming webhooks have a default channel but you can use this setting as an override. Use a "#" before the name to specify a channel and a "@" to specify a direct message. <br>For example, type "#wordpress" for your Slack channel about WordPress or type "@bamadesigner" to send your notifications to me as a direct message,<br> at least you could if I was a member of your Slack account. Send to multiple channels or messages by separating the names with commas.',
            'props'   => [
                // optional tag properties
                'placeholder' => '',
            ],
            'default' => '',
        ]);

        $page_with_tabs->add_field([

            'type'  => 'text',
            'id'    => 'yoast_slack_username',
            'label' => 'Slack Username',
            'desc'  => 'Incoming webhooks have a default username but you can use this setting as an override',
            'props' => [
                // optional tag properties
                'placeholder' => '',
            ],
        ]);

    }

    public function postToSlack($hook_url, $message, $channel, $username = '')
    {
        $array = array("text" => $message, 'channel' => $channel, 'username' => $username);

        $data = wp_remote_post($hook_url, array(
            'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
            'body'    => json_encode($array),
            'method'  => 'POST',
        ));
    }

}
new Yoast_SlackNotification;
