<?php
/**
 * Plugin Name:     Ultimate Member - Profile Content Moderation
 * Description:     Extension to Ultimate Member for Profile Content Moderation.
 * Version:         1.1.0
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v3 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; 
if ( ! class_exists( 'UM' ) ) return;

class UM_Profile_Content_Moderation {

    public $profile_forms = array();
    public $notification_emails = array();

    function __construct() {

        if ( is_admin()) {

            add_filter( 'um_settings_structure', array( $this, 'um_settings_structure_content_moderation' ), 10, 1 );
            add_action(	'um_extend_admin_menu',  array( $this, 'um_extend_admin_menu_content_moderation' ), 10 );
            add_filter( 'pre_user_query',        array( $this, 'filter_users_content_moderation' ), 99 );

            add_action( "um_admin_custom_hook_um_deny_profile_update", array( $this, 'um_deny_profile_update_content_moderation' ), 10, 1 );
            add_filter( 'um_disable_email_notification_sending',       array( $this, 'um_disable_email_notification_content_moderation' ), 10, 4 );
            add_filter( 'um_admin_bulk_user_actions_hook',             array( $this, 'um_admin_bulk_user_actions_content_moderation' ), 10, 1 );

            $um_profile_forms = get_posts( array( 	'meta_key'    => '_um_mode',
                                                    'meta_value'  => 'profile',
                                                    'numberposts' => -1,
                                                    'post_type'   => 'um_form',
                                                    'post_status' => 'publish'
                                                ));

            foreach( $um_profile_forms as $um_form ) {
                $this->profile_forms[$um_form->ID] = $um_form->post_title;
            }

            $emails = UM()->config()->email_notifications;
            foreach( $emails as $key => $email ) {
                $this->notification_emails[$key] = $email['title'];
            }
        }

        add_action( 'um_user_after_updating_profile', array( $this, 'um_user_after_updating_profile_set_pending' ), 10, 3 );
    }

    public function um_admin_bulk_user_actions_content_moderation( $actions ) {

        if( isset( $_REQUEST['content_moderation'] ) && $_REQUEST['content_moderation'] == 'awaiting_profile_review' ) {

            $actions = array();

            $actions['um_approve_membership']  = array( 'label' => __( 'Approve Profile Update', 'ultimate-member' ));				
            $actions['um_deny_profile_update'] = array( 'label' => __( 'Deny Profile Update', 'ultimate-member' ));				
            $actions['um_deactivate']          = array( 'label' => __( 'Deactivate', 'ultimate-member' ));				
        }

        return $actions;
    } 

    public function um_deny_profile_update_content_moderation( $uid ) {

        um_fetch_user( $uid );
        $this->send( um_user( 'user_email' ), UM()->options()->get( 'um_content_moderation_denial_user_email' ) );
    }

    public function um_disable_email_notification_content_moderation( $false, $email, $template, $args ) {

        if ( $template == 'approved_email' && email_exists( $email )) {

            $user = get_user_by( 'email', $email );
            if ( isset( $user ) && is_a( $user, '\WP_User' ) ) {

                um_fetch_user( $user->ID );
                $um_content_moderation = um_user( 'um_content_moderation' );

                if ( isset( $um_content_moderation ) && (int)$um_content_moderation > 1000 ) {

                    update_user_meta( $user->ID, 'um_content_moderation', 0 );
                    UM()->user()->remove_cache( $user->ID );
                    um_fetch_user( $user->ID );

                    $this->send( $email, UM()->options()->get( 'um_content_moderation_accept_user_email' ) );
                    return true;
                }
            }
        }

        return $false;
    }

    public function send( $email, $template, $args = array() ) {

        if ( empty( UM()->options()->get( $template . '_on' ) ) ) {
            return;
        }

        $attachments = array();
        $headers     = 'From: ' . stripslashes( UM()->options()->get( 'mail_from' ) ) . ' <' . UM()->options()->get( 'mail_from_addr' ) . '>' . "\r\n";

        add_filter( 'um_template_tags_patterns_hook', array( UM()->mail(), 'add_placeholder' ), 10, 1 );
        add_filter( 'um_template_tags_replaces_hook', array( UM()->mail(), 'add_replace_placeholder' ), 10, 1 );

        $subject = wp_unslash( um_convert_tags( UM()->options()->get( $template . '_sub' ), $args ) );
        $subject = html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' );

        $message = UM()->mail()->prepare_template( $template, $args );

        if ( UM()->options()->get( 'email_html' ) ) {
            $headers .= "Content-Type: text/html\r\n";
        } else {
            $headers .= "Content-Type: text/plain\r\n";
        }

        wp_mail( $email, $subject, $message, $headers, $attachments );
    }

    public function filter_users_content_moderation( $filter_query ) {

        global $wpdb;
        global $pagenow;

        if ( is_admin() && $pagenow == 'users.php' && ! empty( $_REQUEST['content_moderation'] ) ) {

            if ( sanitize_key( $_REQUEST['content_moderation'] ) == 'awaiting_profile_review' ) {

                $filter_query->query_where = str_replace( 'WHERE 1=1', "WHERE 1=1 AND {$wpdb->users}.ID IN (
                                                                        SELECT {$wpdb->usermeta}.user_id FROM $wpdb->usermeta
                                                                        WHERE {$wpdb->usermeta}.meta_key = 'um_content_moderation'
                                                                        AND {$wpdb->usermeta}.meta_value > '1000')",
                                                                      $filter_query->query_where );
            }
        }

        return $filter_query;
    }

    public function um_extend_admin_menu_content_moderation() {

        $url = esc_url( admin_url( 'users.php' ) . '?content_moderation=awaiting_profile_review' );

        add_submenu_page( 'ultimatemember', __( 'Content Moderation', 'ultimate-member' ), 
                                            __( 'Content Moderation', 'ultimate-member' ), 
                                                'manage_options', $url , '' );			
    }

    public function um_user_after_updating_profile_set_pending( $to_update, $user_id, $args ) {

        $um_content_moderation_forms = UM()->options()->get( 'um_content_moderation_forms' );

        if ( in_array( $args['form_id'], $um_content_moderation_forms )) {

            if ( in_array( UM()->user()->get_role(), UM()->options()->get( 'um_content_moderation_roles' ))) {

                UM()->user()->set_status( 'awaiting_admin_review' );
                UM()->mail()->send( um_user( 'user_email' ), UM()->options()->get( 'um_content_moderation_pending_user_email' ) );
                UM()->mail()->send( get_bloginfo( 'admin_email' ), UM()->options()->get( 'um_content_moderation_admin_email' ), array( 'admin' => true ) );

                update_user_meta( $user_id, 'um_content_moderation', time() );
                UM()->user()->remove_cache( $user_id );
                um_fetch_user( $user_id );
            }
        }
    }

    public function um_settings_structure_content_moderation( $settings_structure ) {

        $settings_structure['']['sections']['users']['fields'][] = array(
            'id'            => 'um_content_moderation_forms',
            'type'          => 'select',
            'multi'         => true,
            'size'          => 'medium',
            'options'       => $this->profile_forms,
            'label'         => __( 'Content Moderation - Profile Forms', 'ultimate-member' ),
            'tooltip'       => __( 'Select single or multiple Profile Forms for Content Moderation.', 'ultimate-member' ),
            );

        $settings_structure['']['sections']['users']['fields'][] = array(
            'id'            => 'um_content_moderation_roles',
            'type'          => 'select',
            'multi'         => true,
            'label'         => __( 'Content Moderation - User Roles', 'ultimate-member' ),
            'tooltip'       => __( 'Select the User Role(s) to be included in Content Moderation.', 'ultimate-member' ),
            'options'       => UM()->roles()->get_roles(),
            'size'          => 'medium',
            );

        $settings_structure['']['sections']['users']['fields'][] = array(
            'id'            => 'um_content_moderation_pending_user_email',
            'type'          => 'select',
            'label'         => __( 'Content Moderation - User Pending Notification', 'ultimate-member' ),
            'tooltip'       => __( 'Select the User Pending Notification Email template.', 'ultimate-member' ),
            'options'       => $this->notification_emails,
            'size'          => 'medium',
            );

        $settings_structure['']['sections']['users']['fields'][] = array(
            'id'            => 'um_content_moderation_accept_user_email',
            'type'          => 'select',
            'label'         => __( 'Content Moderation - User Accept Notification', 'ultimate-member' ),
            'tooltip'       => __( 'Select the User Accept Notification Email template.', 'ultimate-member' ),
            'options'       => $this->notification_emails,
            'size'          => 'medium',
            );

        $settings_structure['']['sections']['users']['fields'][] = array(
            'id'            => 'um_content_moderation_denial_user_email',
            'type'          => 'select',
            'label'         => __( 'Content Moderation - User Denial Notification', 'ultimate-member' ),
            'tooltip'       => __( 'Select the User Denial Notification Email template.', 'ultimate-member' ),
            'options'       => $this->notification_emails,
            'size'          => 'medium',
            );

        $settings_structure['']['sections']['users']['fields'][] = array(
            'id'            => 'um_content_moderation_admin_email',
            'type'          => 'select',
            'label'         => __( 'Content Moderation - Admin Notification', 'ultimate-member' ),
            'tooltip'       => __( 'Select the Admin Notification Email template.', 'ultimate-member' ),
            'options'       => $this->notification_emails,
            'size'          => 'medium',
            );

        return $settings_structure;
    }

}

new UM_Profile_Content_Moderation();
