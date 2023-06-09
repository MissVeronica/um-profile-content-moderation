<?php
/**
 * Plugin Name:     Ultimate Member - Profile Content Moderation
 * Description:     Extension to Ultimate Member for Profile Content Moderation.
 * Version:         2.2.2
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v3 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.6.3
 */

if ( ! defined( 'ABSPATH' ) ) exit; 
if ( ! class_exists( 'UM' ) ) return;

class UM_Profile_Content_Moderation {

    public $profile_forms = array();
    public $notification_emails = array();
    public $send_email = false;
    public $slug = array();

    function __construct() {

        if ( is_admin()) {

            remove_filter( 'manage_users_custom_column', array( &UM()->classes['admin_columns'], 'manage_users_custom_column' ), 10, 3 );
            add_filter( 'manage_users_custom_column',    array( $this, 'manage_users_custom_column_content_moderation' ), 10, 3 );

            add_filter( 'manage_users_columns',          array( $this, 'manage_users_columns_content_moderation' ) );
            add_filter( 'um_admin_views_users',          array( $this, 'um_admin_views_users_content_moderation' ), 10, 1 );

            add_filter( 'um_settings_structure',         array( $this, 'um_settings_structure_content_moderation' ), 10, 1 );
            add_action(	'um_extend_admin_menu',          array( $this, 'um_extend_admin_menu_content_moderation' ), 10 );
            add_filter( 'pre_user_query',                array( $this, 'filter_users_content_moderation' ), 99 );
            add_filter( 'um_email_notifications',        array( $this, 'um_email_notification_profile_content_moderation' ), 99 );

            add_action( "um_admin_custom_hook_um_deny_profile_update", array( $this, 'um_deny_profile_update_content_moderation' ), 10, 1 );
            add_filter( 'um_disable_email_notification_sending',       array( $this, 'um_disable_email_notification_content_moderation' ), 10, 4 );
            add_filter( 'um_admin_bulk_user_actions_hook',             array( $this, 'um_admin_bulk_user_actions_content_moderation' ), 10, 1 );
            add_filter( 'um_admin_user_row_actions',                   array( $this, 'um_admin_user_row_actions_content_moderation' ), 10, 2 );

            add_action( 'um_admin_ajax_modal_content__hook_content_moderation_review_update', array( $this, 'content_moderation_review_update_ajax_modal' ));

            if ( isset( $_REQUEST['content_moderation'] ) && $_REQUEST['content_moderation'] == 'awaiting_profile_review' ) {
                add_action( 'admin_init', array( $this, 'replace_standard_action_content_moderation' ), 10 ); 
            }

            $um_profile_forms = get_posts( array(   'meta_key'    => '_um_mode',
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

        add_action( 'um_user_pre_updating_profile',   array( $this, 'um_user_pre_updating_profile_save_before_after' ), 10, 2 );
        add_action( 'um_user_after_updating_profile', array( $this, 'um_user_after_updating_profile_set_pending' ), 10, 3 );
    }

    public function um_admin_views_users_content_moderation( $views ) {

        global $wpdb;

        $moderation_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'um_content_moderation' AND meta_value > '0' " );
        $views['moderation'] = '<a href="' . esc_url( admin_url( 'users.php' ) . '?content_moderation=awaiting_profile_review' ) . '">' . 
                                __( 'Content Moderation', 'ultimate-member' ) . ' <span class="count">(' . $moderation_count . ')</span></a>';

        return $views;
    }

    public function manage_users_columns_content_moderation( $columns ) {

        if ( isset( $_REQUEST['content_moderation'] ) && $_REQUEST['content_moderation'] == 'awaiting_profile_review' ) { 

            $columns['content_moderation'] = __( 'Update/Denial', 'ultimate-member' );
        }

        return $columns;
    }

    public function manage_users_custom_column_content_moderation( $value, $column_name, $user_id ) {

        if ( $column_name == 'account_status' ) {

            um_fetch_user( $user_id );
            $value = um_user( 'account_status_name' );

            if ( (int)um_user( 'um_content_moderation' ) > 1000 ) {
                $value = __( 'Content Moderation', 'ultimate-member' );
            }

            um_reset_user();
        }

        if ( $column_name == 'content_moderation' ) {

            um_fetch_user( $user_id );

            $um_content_moderation = um_user( 'um_content_moderation' );
            if ( (int)$um_content_moderation > 1000 ) {
                $value .= date( 'Y-m-d H:i:s', $um_content_moderation );
            }

            $um_denial_profile_updates = um_user( 'um_denial_profile_updates' );
            if ( ! empty( $um_denial_profile_updates ) && (int)$um_denial_profile_updates > 0 ) {
                $value .= '<br />' . date( 'Y-m-d H:i:s', $um_denial_profile_updates );
            }

            um_reset_user();
        }

        return $value;
    }

    public function copy_email_notifications_content_moderation() {

        define( 'Content_Moderation_Path', plugin_dir_path( __FILE__ ) );

        foreach( $this->slug as $slug ) {

            $located = UM()->mail()->locate_template( $slug );
            if ( empty( $located )) {
                $located = wp_normalize_path( STYLESHEETPATH . '/ultimate-member/email/' . $slug . '.php' );
            }

            clearstatcache();
            if ( ! file_exists( $located ) ) {

                wp_mkdir_p( dirname( $located ) );

                $email_source = file_get_contents( Content_Moderation_Path . $slug . '.php' );
                file_put_contents( $located, $email_source );
            }
        }
    }

    public function replace_standard_action_content_moderation() {

        remove_action( 'admin_footer', array( UM()->classes['admin_metabox'], 'load_modal_content' ), 9 );
        add_action(    'admin_footer', array( $this, 'load_modal_content_moderation' ), 9 );

    }

    public function content_moderation_review_update_ajax_modal() {

        $user_id = sanitize_text_field( $_POST['arg1'] );

        echo '<div class="um-admin-infobox">';

        if ( current_user_can( 'administrator' ) && um_can_view_profile( $user_id )) {

            um_fetch_user( $user_id );

            echo '<p><label>' . sprintf( __( 'Profile Update submitted %s by User %s', 'ultimate-member' ), date( 'Y-m-d H:i:s', um_user( 'um_content_moderation' )), um_user( 'user_login' )) . '</label></p>';

            $um_denial_profile_updates = um_user( 'um_denial_profile_updates' );
            if ( ! empty( $um_denial_profile_updates ) && (int)$um_denial_profile_updates > 0 ) {
                echo '<p><label>' . sprintf( __( 'Profile Update Denial sent %s', 'ultimate-member' ), date( 'Y-m-d H:i:s', $um_denial_profile_updates )) . '</label></p>';
            }

            $diff_updates = maybe_unserialize( um_user( 'um_diff_updates' ));

            $old = __( 'Old:',  'ultimate-member' );
            $new = __( 'New:',  'ultimate-member' );

            $output = array();

            foreach( $diff_updates as $meta_key => $meta_value ) {

                $meta_value['old'] = maybe_unserialize( $meta_value['old'] );
                $meta_value['new'] = maybe_unserialize( $meta_value['new'] );

                if ( is_array( $meta_value['old'] )) {
                    $meta_value['old'] = implode( ',', $meta_value['old'] );
                }
                if ( is_array( $meta_value['new'] )) {
                    $meta_value['new'] = implode( ',', $meta_value['new'] );
                }

                if ( empty( $meta_value['old'] )) $meta_value['old'] = __( '(empty)', 'ultimate-member' );
                if ( empty( $meta_value['new'] )) $meta_value['new'] = __( '(empty)', 'ultimate-member' );

                if ( mb_strtolower( $meta_value['old']) != mb_strtolower( $meta_value['new'])) {

                    $field = UM()->builtin()->get_a_field( $meta_key );
                    $title = isset( $field['title'] ) ? esc_attr( $field['title']) : esc_attr( $field['label']);

                    $output[] = "<p><label>{$title}</label>
                                    <span><label>{$old}</label>" . esc_attr( $meta_value['old']) . "<br />
                                    <label>{$new}</label>" . esc_attr( $meta_value['new']) .
                                    "</span></p>";
                }
            }

            if ( count( $output ) > 0 ) {
                sort( $output );
                echo implode( '', $output );

            } else {

                echo '<p><label>' . __( 'No updates found', 'ultimate-member' ) . '</label></p>';
                echo '<p><label>' . __( 'Image/File updates are not logged at the moment.', 'ultimate-member' ) . '</label></p>';
            }

            um_reset_user();

        } else {

            echo '<p><label>' . __( 'No access', 'ultimate-member' ) . '</label></p>';
        }

        echo '</div>';
    }

    public function load_modal_content_moderation() {

        ?><div id="UM_preview_profile_update" style="display:none">
            <div class="um-admin-modal-head">
                <h3><?php _e( "Review Profile Content Moderation", "ultimate-member" ); ?></h3>
            </div>
            <div class="um-admin-modal-body"></div>
            <div class="um-admin-modal-foot"></div>
        </div>

        <div id="UM_preview_registration" style="display:none">
            <div class="um-admin-modal-head">
                <h3><?php _e( 'Review Registration Details', 'ultimate-member' ); ?></h3>
            </div>
            <div class="um-admin-modal-body"></div>
            <div class="um-admin-modal-foot"></div>
        </div><?php
    }

    public function um_admin_user_row_actions_content_moderation( $actions, $user_id ) {

        if ( isset( $_REQUEST['content_moderation'] ) && $_REQUEST['content_moderation'] == 'awaiting_profile_review' ) { 

            $actions['view_info_update'] = '<a href="javascript:void(0);" data-modal="UM_preview_profile_update" 
                                            data-modal-size="smaller" data-dynamic-content="content_moderation_review_update" 
                                            data-arg1="' . esc_attr( $user_id ) . '" data-arg2="profile_updates">' . 
                                            __( 'Moderation', 'ultimate-member' ) .  '</a>';
        }

        return $actions;
    }

    public function um_admin_bulk_user_actions_content_moderation( $actions ) {

        if ( isset( $_REQUEST['content_moderation'] ) && $_REQUEST['content_moderation'] == 'awaiting_profile_review' ) {

            $actions = array();

            $actions['um_approve_membership']  = array( 'label' => __( 'Approve Profile Update', 'ultimate-member' ));				
            $actions['um_deny_profile_update'] = array( 'label' => __( 'Deny Profile Update', 'ultimate-member' ));				
            $actions['um_deactivate']          = array( 'label' => __( 'Deactivate', 'ultimate-member' ));				
        }

        return $actions;
    }

    public function content_moderation_action() {

        $um_content_moderation_forms = array_map( 'sanitize_text_field', UM()->options()->get( 'um_content_moderation_forms' ));
        $form_id = sanitize_text_field( $_POST['form_id'] );

        if ( in_array( $form_id, $um_content_moderation_forms )) {

            if ( in_array( UM()->user()->get_role(), array_map( 'sanitize_text_field', UM()->options()->get( 'um_content_moderation_roles' )))) {
                return true;
            }
        }

        return false;
    }

    public function um_deny_profile_update_content_moderation( $user_id ) {

        um_fetch_user( $user_id );
        $this->send( um_user( 'user_email' ), UM()->options()->get( 'um_content_moderation_denial_user_email' ) );

        update_user_meta( $user_id, 'um_denial_profile_updates', current_time( 'timestamp' ) );
        UM()->user()->remove_cache( $user_id );
        um_fetch_user( $user_id );

        $uri = add_query_arg( 'content_moderation', 'awaiting_profile_review', admin_url( 'users.php' ) );
        wp_redirect( $uri );
        exit;
    }

    public function um_disable_email_notification_content_moderation( $false, $email, $template, $args ) {

        if ( $template == 'approved_email' && email_exists( $email )) {

            $user = get_user_by( 'email', $email );
            if ( isset( $user ) && is_a( $user, '\WP_User' ) ) {

                um_fetch_user( $user->ID );
                $um_content_moderation = um_user( 'um_content_moderation' );

                if ( isset( $um_content_moderation ) && (int)$um_content_moderation > 0 ) {

                    update_user_meta( $user->ID, 'um_content_moderation', 0 );
                    update_user_meta( $user->ID, 'um_diff_updates', null );

                    if ( ! empty( um_user( 'um_denial_profile_updates' )) && (int)um_user( 'um_denial_profile_updates' ) > 0 ) {
                        update_user_meta( $user->ID, 'um_denial_profile_updates', 0 );
                    }

                    update_user_meta( $user->ID, 'account_status', 'approved' );
                    UM()->user()->remove_cache( $user->ID );
                    um_fetch_user( $user->ID );

                    $this->send( $email, UM()->options()->get( 'um_content_moderation_accept_user_email' ) );                    
                    
                    $uri = add_query_arg( 'content_moderation', 'awaiting_profile_review', admin_url( 'users.php' ) );
                    wp_redirect( $uri );
                    exit;
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
                                                
        $this->copy_email_notifications_content_moderation();
    }

    public function um_user_pre_updating_profile_save_before_after( $to_update, $user_id ) {

        if ( $this->content_moderation_action() ) {

            $diff_updates = maybe_unserialize( um_user( 'um_diff_updates' ));
            if ( empty( $diff_updates )) {
                $diff_updates = array();
            }

            foreach( $to_update as $meta_key => $meta_value ) {

                if ( empty( $diff_updates[$meta_key]['old'] )) {
                    $diff_updates[$meta_key]['old'] = um_user( $meta_key );
                }
                $diff_updates[$meta_key]['new'] = $meta_value;
            }

            update_user_meta( $user_id, 'um_diff_updates', $diff_updates );

            $um_content_moderation = um_user( 'um_content_moderation' );
            if ( empty( $um_content_moderation ) || (int)$um_content_moderation == 0 ) {
                $this->send_email = true;
                update_user_meta( $user_id, 'um_content_moderation', current_time( 'timestamp' ) );
            }
        }
    }

    public function um_user_after_updating_profile_set_pending( $to_update, $user_id, $args ) {

        if ( $this->send_email ) {

            UM()->user()->set_status( 'awaiting_admin_review' );
            UM()->mail()->send( um_user( 'user_email' ), UM()->options()->get( 'um_content_moderation_pending_user_email' ) );
            UM()->mail()->send( get_bloginfo( 'admin_email' ), UM()->options()->get( 'um_content_moderation_admin_email' ), array( 'admin' => true ) );
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

    public function um_email_notification_profile_content_moderation( $emails ) {

        $custom_emails = array(	'content_moderation_pending_user_email' => array(
                                        'key'            => 'content_moderation_pending_user_email',
                                        'title'          => __( 'Content Moderation - User Pending Notification', 'ultimate-member' ),
                                        'description'    => __( 'User Pending Notification Email template', 'ultimate-member' ),
                                        'recipient'      => 'user',
                                        'default_active' => true,
                                        'subject'        => '[{site_name}] Profile review pending',
                                        'body'           => '',
                                ),

                                'content_moderation_accept_user_email' => array(
                                        'key'            => 'content_moderation_accept_user_email',
                                        'title'          => __( 'Content Moderation - User Accept Notification', 'ultimate-member' ),
                                        'description'    => __( 'User Accepted Notification Email template', 'ultimate-member' ),
                                        'recipient'      => 'user',
                                        'default_active' => true,
                                        'subject'        => '[{site_name}] Profile is accepted',
                                        'body'           => '',
                                ),

                                'content_moderation_denial_user_email' => array(
                                        'key'            => 'content_moderation_denial_user_email',
                                        'title'          => __( 'Content Moderation - User Denial Notification', 'ultimate-member' ),
                                        'description'    => __( 'User Denial Notification Email template', 'ultimate-member' ),
                                        'recipient'      => 'user',
                                        'default_active' => true,
                                        'subject'        => '[{site_name}] Profile is denied',
                                        'body'           => '',
                                ),

                                'content_moderation_pending_admin_email' => array(
                                        'key'            => 'content_moderation_pending_admin_email',
                                        'title'          => __( 'Content Moderation - Admin Pending Notification', 'ultimate-member' ),
                                        'description'    => __( 'Admin Pending Notification Email template', 'ultimate-member' ),
                                        'recipient'      => 'admin',
                                        'default_active' => true,
                                        'subject'        => '[{site_name}] Profile is updated',
                                        'body'           => '',
                                ),
                            );

        foreach ( $custom_emails as $slug => $custom_email ) {

            if ( ! array_key_exists( $slug . '_on', UM()->options()->options ) ) {

                UM()->options()->options[ $slug . '_on' ]  = empty( $custom_email['default_active'] ) ? 0 : 1;
                UM()->options()->options[ $slug . '_sub' ] = $custom_email['subject'];
            }

            $this->slug[] = $slug;

            $emails[ $slug ] = $custom_email;
        }

        return $emails;
    }

}

new UM_Profile_Content_Moderation();

