<?php
/**
 * Plugin Name:         Ultimate Member - Profile Content Moderation
 * Description:         Extension to Ultimate Member for Profile Content Moderation.
 * Version:             3.6.0
 * Requires PHP:        7.4
 * Author:              Miss Veronica
 * License:             GPL v3 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:          https://github.com/MissVeronica
 * Plugin URI:          https://github.com/MissVeronica/um-profile-content-moderation
 * Update URI:          https://github.com/MissVeronica/um-profile-content-moderation
 * Text Domain:         content-moderation
 * Domain Path:         /languages
 * UM version:          2.8.6
 * Source computeDiff:  https://stackoverflow.com/questions/321294/highlight-the-difference-between-two-strings-in-php
 */

if ( ! defined( 'ABSPATH' ) ) exit; 
if ( ! class_exists( 'UM' ) ) return;

use um\core\Options;

class UM_Profile_Content_Moderation {

    public $profile_forms        = array();
    public $slugs                = array();
    public $update_field_types   = array();
    public $not_update_user_keys = array( 'role', 'pass', 'password' );

    public $send_email           = true;
    public $moderation_count     = '';
    public $transient_name       = 'content_moderation_';
    public $transient_life       = 5 * DAY_IN_SECONDS;
    public $seconds_in_week      = 7 * DAY_IN_SECONDS;
    public $half_day_seconds     = 12 * 3600;
    public $new_plugin_version   = '';
    
    public $cached_meta_keys     = array( 'um_content_moderation', 'um_denial_profile_updates', 'um_rollback_profile_updates' );
    public $cached_meta_values   = array();
    public $time_since_colors    = array();
    public $opacity              = array( '1.0', '0.9', '0.8', '0.7', '0.6', '0.5', '0.4' );

    public $templates            = array(   'pending_user'  => 'pending_user',
                                            'accept_user'   => 'accept_user',
                                            'denial_user'   => 'denial_user',
                                            'rollback_user' => 'rollback_user',
                                            'pending_admin' => 'pending_admin',
                                        );

    public $html_allowed         = array(
                                            'a'     => array(
                                                            'href'   => array(),
                                                            'target' => true,
                                                            'title'  => true,
                                                            'style'  => true,
                                                            ),
                                            'style' => array(),
                                            'br'    => array(),
                                            'hr'    => array(),
                                            'span'  => array(
                                                            'style' => true,
                                                            'title' => true,
                                                            ),
                                            'table' => array(),
                                            'td'    => array(
                                                            'style'   => true,
                                                            'colspan' => true,
                                                            ),
                                            'tr'    => array(
                                                            'style'   => true,
                                                            ),
                                        );

    function __construct() {

        if ( is_admin()) {

            remove_filter( 'manage_users_custom_column', array( &UM()->classes['admin_columns'], 'manage_users_custom_column' ), 10, 3 );
            add_filter( 'manage_users_custom_column',    array( $this, 'manage_users_custom_column_content_moderation' ), 10, 3 );

            add_filter( 'manage_users_columns',          array( $this, 'manage_users_columns_content_moderation' ) );
            add_filter( 'um_admin_views_users',          array( $this, 'um_admin_views_users_content_moderation' ), 10, 1 );

            add_filter( 'um_settings_structure',         array( $this, 'um_settings_structure_content_moderation' ), 10, 1 );
            add_action(	'um_extend_admin_menu',          array( $this, 'um_extend_admin_menu_content_moderation' ), 10 );
            add_filter( 'pre_user_query',                array( $this, 'filter_users_content_moderation' ), 99 );
            
            add_filter( 'manage_users_sortable_columns', array( $this, 'register_sortable_columns_custom' ), 10, 1 );
            add_action( 'pre_get_users',                 array( $this, 'pre_get_users_sort_columns_custom' ));

            add_action( "um_admin_custom_hook_um_deny_profile_update",   array( $this, 'um_deny_profile_update_content_moderation' ), 10, 1 );
            add_action( "um_admin_custom_hook_um_accept_profile_update", array( $this, 'um_accept_profile_update_content_moderation' ), 10, 1 );
            add_filter( 'um_disable_email_notification_sending',         array( $this, 'um_disable_email_notification_content_moderation' ), 10, 4 );
            add_filter( 'um_admin_bulk_user_actions_hook',               array( $this, 'um_admin_bulk_user_actions_content_moderation' ), 10, 1 );
            add_filter( 'um_admin_user_row_actions',                     array( $this, 'um_admin_user_row_actions_content_moderation' ), 10, 2 );
            add_action( 'um_admin_do_action__content_moderation',        array( $this, 'content_moderation_reset' ) );
            add_filter( 'um_adm_action_custom_update_notice',            array( $this, 'content_moderation_reset_notice' ), 10, 2 );

            add_action( 'um_admin_ajax_modal_content__hook_content_moderation_review_update', array( $this, 'content_moderation_review_update_ajax_modal' ));
            add_action( "um_admin_custom_hook_um_rollback_profile_update",                    array( $this, 'um_rollback_profile_update_content_moderation' ), 10, 1 );

            if ( isset( $_REQUEST['content_moderation'] ) && sanitize_key( $_REQUEST['content_moderation'] ) === 'awaiting_profile_review' ) {
                add_action( 'admin_init', array( $this, 'replace_standard_action_content_moderation' ), 10 );
            }

            if ( UM()->options()->get( 'um_content_moderation_modal_list' ) == 1 ) {
                add_action( 'load-toplevel_page_ultimatemember', array( $this, 'load_toplevel_page_content_moderation' ) );
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
        }

        define( 'CM_UNMODIFIED',  0 );
        define( 'CM_DELETED',    -1 );
        define( 'CM_INSERTED',    1 );

        define( 'Plugin_Basename_CM', plugin_basename(__FILE__));

        add_action( 'um_user_pre_updating_profile',              array( $this, 'um_user_pre_updating_profile_save_before_after' ), 10, 2 );
        add_action( 'um_user_after_updating_profile',            array( $this, 'um_user_after_updating_profile_set_pending' ), 10, 3 );
        add_action( 'um_user_edit_profile',                      array( $this, 'um_user_edit_profile_content_moderation' ), 10, 1 );
        add_filter( 'um_myprofile_edit_menu_items',              array( $this, 'um_myprofile_edit_menu_items_content_moderation' ), 10, 1 );
        add_filter( 'um_user_pre_updating_profile_array',        array( $this, 'um_user_pre_updating_profile_array_delay_update' ), 10, 3 );
        add_action( 'plugins_loaded',                            array( $this, 'um_content_moderation_plugin_loaded' ), 0 );
        add_action( 'um_after_email_confirmation',               array( $this, 'um_after_email_confirmation_admin_approval' ), 10, 1 );
        add_action( 'um_after_profile_name_inline',              array( $this, 'um_after_profile_name_show_user_updated' ), 10, 1 );
        add_filter( 'um_email_notifications',                    array( $this, 'um_email_notification_profile_content_moderation' ), 99 );
        add_filter( 'plugin_action_links_' . Plugin_Basename_CM, array( $this, 'content_moderation_settings_link' ), 10 );

        if ( UM()->options()->get( 'um_content_moderation_delay_update' ) != 1 ) {
            if ( UM()->options()->get( 'um_content_moderation_disable_logincheck' ) == 1 ) {

                add_filter( 'authenticate',                          array( $this, 'um_wp_form_errors_hook_logincheck_content_moderation' ), 40, 3 );
                add_action( 'um_submit_form_errors_hook_logincheck', array( $this, 'um_submit_form_errors_hook_logincheck_content_moderation' ), 999, 2 );
            }
        }

        define( 'Plugin_Path_CM', plugin_dir_path( __FILE__ ) );
        define( 'Plugin_Textdomain_CM', 'content-moderation' );
        define( 'Plugin_File_CM', __FILE__ );

        $this->cached_meta_values = array_fill_keys( array_map( 'sanitize_text_field', $this->cached_meta_keys ), false );
        $this->time_since_colors  = array_fill( 0, 6, 'white' );
    }

    public function um_content_moderation_plugin_loaded() {

        $locale = ( get_locale() != '' ) ? get_locale() : 'en_US';
        load_textdomain( Plugin_Textdomain_CM, WP_LANG_DIR . '/plugins/' . Plugin_Textdomain_CM . '-' . $locale . '.mo' );
        load_plugin_textdomain( Plugin_Textdomain_CM, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    function content_moderation_settings_link( $links ) {

        $url = get_admin_url() . 'admin.php?page=um_options&tab=extensions&section=content-moderation';
        $links[] = '<a href="' . esc_url( $url ) . '">' . __( 'Settings' ) . '</a>';
    
        return $links;
    }

    public function um_after_profile_name_show_user_updated( $args ) {

        if ( UM()->options()->get( 'um_content_moderation_update_status' ) == 1 ) {

            $last_update = um_user( 'um_content_moderation_update' );

            if ( ! empty( $last_update )) {
                $last_update = strtotime( $last_update );

                if ( $last_update > ( current_time( 'timestamp' ) - $this->seconds_in_week )) {

                    $number_days = intval(( current_time( 'timestamp' ) - $last_update ) / DAY_IN_SECONDS );
                    $time_since_colors = UM()->options()->get( 'um_content_moderation_update_colors' );

                    if ( ! empty( $time_since_colors )) {
                        $this->time_since_colors = array_map( 'trim', array_map( 'sanitize_text_field', explode( ',', $time_since_colors )));
                    }

                    if ( isset( $this->time_since_colors[$number_days] ) && ! empty( $this->time_since_colors[$number_days] )) {

                        switch ( $number_days ) {

                            case 0:     $title = __( 'The latest update of the User Profile: today',     'content-moderation' ); break;
                            case 1:     $title = __( 'The latest update of the User Profile: yesterday', 'content-moderation' ); break;
                            default:    $title = sprintf( __( 'Time since the latest update of the User Profile: %d days ago %s', 'content-moderation' ), $number_days, $last_update );
                        }

                        $opacity = '1.0';
                        if ( UM()->options()->get( 'um_content_moderation_update_opacity' ) == 1 && isset( $this->opacity[$number_days] )) {
                            $opacity = $this->opacity[$number_days];
                        }

                        $pixels = 24;
                        $px = trim( UM()->options()->get( 'um_content_moderation_update_size' ));
                        if ( ! empty( $px )) {
                            $pixels = absint( str_replace( 'px', '', strtolower( sanitize_text_field( $px ))));
                        }
?>
                        <span class="um-field-label-icon"
                              style="font-size: <?php echo esc_attr( $pixels ); ?>px;
                                     opacity: <?php echo esc_attr( $opacity ); ?>;
                                     color: <?php echo esc_attr( $this->time_since_colors[$number_days]); ?>;"
                              title="<?php echo esc_attr( $title ); ?>">
                            <i class="fas fa-circle-user"></i>
                        </span>
<?php
                    }
                }
            }
        }
    }

    public function last_midnight_today() {

        $today = date_i18n( 'Y/m/d', current_time( 'timestamp' ));
        return $today;
    }

    public function um_user_pre_updating_profile_array_delay_update( $to_update, $user_id, $form_data ) {

        if ( UM()->options()->get( 'um_content_moderation_delay_update' ) == 1 ) {

            $to_update = array();
        }

        return $to_update;
    }

    public function um_myprofile_edit_menu_items_content_moderation( $items ) {

        if ( UM()->options()->get( 'um_content_moderation_delay_update' ) == 1 ) {

            if ( (int)um_user( 'um_content_moderation' ) > 1000 ) {

                $url = sanitize_url( UM()->options()->get( 'um_content_moderation_delay_url' ));
                
                if ( ! empty( $url )) {

                    $url_text = sanitize_text_field( UM()->options()->get( 'um_content_moderation_delay_url_text' ));

                    $items['editprofile'] = '<a href="' . esc_url( $url ) . '" class="real_url" target="_blank">';

                    if ( empty( $url_text )) {
                        $items['editprofile'] .=  __( 'Why Content Moderation', 'content-moderation' );

                    } else {
                        $items['editprofile'] .=  esc_attr( $url_text );
                    }

                    $items['editprofile'] .= '</a>'; 

                } else {
                    unset( $items['editprofile'] );
                }
            }
        }
 
        return $items;
    }

    public function um_after_email_confirmation_admin_approval( $user_id ) {

        if ( UM()->options()->get( 'um_content_moderation_double_optin' ) == 1 ) {

            um_fetch_user( $user_id );
            UM()->user()->pending();
        }
    }

    public function um_submit_form_errors_hook_logincheck_content_moderation( $submitted_data, $form_data  ) {

        if ( ! is_user_logged_in() ) {

            $user_id = ( isset( UM()->login()->auth_id ) ) ? UM()->login()->auth_id : '';
            um_fetch_user( $user_id );

            $status = um_user( 'account_status' );
            if ( $status == 'awaiting_admin_review' ) {

                $um_content_moderation = um_user( 'um_content_moderation' );
                if ( ! empty( $um_content_moderation ) && (int)$um_content_moderation > 1000 ) {

                    remove_action( 'um_submit_form_errors_hook_logincheck', 'um_submit_form_errors_hook_logincheck', 9999, 2 );

                    if ( isset( $form_data['form_id'] ) && absint( $form_data['form_id'] ) === absint( UM()->shortcodes()->core_login_form() )
                                                        && UM()->form()->errors && ! isset( $_POST[ UM()->honeypot ] ) ) {

                        wp_safe_redirect( um_get_core_page( 'login' ) );
                        exit;
                    }
                }
            }
        }
    }

    public function um_wp_form_errors_hook_logincheck_content_moderation( $user, $username, $password ) {

        if ( isset( $user->ID ) ) {

            um_fetch_user( $user->ID );
            $status = um_user( 'account_status' );

            if ( $status == 'awaiting_admin_review' ) {
                $um_content_moderation = um_user( 'um_content_moderation' );

                if ( ! empty( $um_content_moderation ) && (int)$um_content_moderation > 1000 ) {
                    remove_filter( 'authenticate', 'um_wp_form_errors_hook_logincheck', 50, 3 );
                }
            }
        }
        return $user;
    }

    public function load_toplevel_page_content_moderation() {

        $this->moderation_count = $this->format_users( $this->count_content_values( 'um_content_moderation' ));

        add_meta_box( 'um-metaboxes-sidebox-content-moderation',
                        sprintf( __( 'Content Moderation - %s waiting', 'content-moderation' ), $this->moderation_count ),
                        array( $this, 'toplevel_page_content_moderation' ),
                        'toplevel_page_ultimatemember',
                        'side', 'core'
                    );
    }

    public function count_content_values( $meta_key, $delta = false ) {

        global $wpdb;

        if ( isset( $this->cached_meta_values[$meta_key] ) && $this->cached_meta_values[$meta_key] !== false ) {
            $counter = $this->cached_meta_values[$meta_key];

        } else {
            $counter = get_transient( $this->transient_name . $meta_key );
        }

        if ( $counter === false ) {

            $counter = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '{$meta_key}' AND meta_value > '0' " );
            if ( $counter !== null ) {
                set_transient( $this->transient_name . $meta_key, $counter, $this->transient_life );

            } else {
                $counter = 0;
            }

        } else {

            if ( $delta !== false ) {
                $counter = $counter + $delta;
                set_transient( $this->transient_name . $meta_key, $counter );
            }
        }

        $this->cached_meta_values[$meta_key] = $counter;

        return $counter;
    }

    public function format_users( $counter ) {

        if ( $counter == 0 ) {
            $count_users = __( 'No users', 'content-moderation' );

        } else {

            $count_users = ( $counter > 1 ) ? sprintf( __( '%d users', 'content-moderation' ), $counter ) : __( 'One user', 'content-moderation' );
        }

        return $count_users;
    }

    function toplevel_page_content_moderation() {

        $denied_count = $this->format_users( $this->count_content_values( 'um_denial_profile_updates' ) );

        if ( UM()->options()->get( 'um_content_moderation_delay_update' ) != 1 ) {
            $rollback_count   = $this->format_users( $this->count_content_values( 'um_rollback_profile_updates' ) );
        }

        $roles = UM()->options()->get( 'um_content_moderation_roles' );
        if ( ! empty( $roles )) {

            $roles = array_map( 'sanitize_text_field', $roles );
            $roles_list = UM()->roles()->get_roles();
            $moderated_roles = array();

            foreach( $roles as $role ) {
                $moderated_roles[] = $roles_list[$role];
            }

        } else {

            $moderated_roles[] = __( 'None', 'content-moderation' );
        }

        $forms = UM()->options()->get( 'um_content_moderation_forms' );
        if ( ! empty( $forms )) {

            $forms = array_map( 'sanitize_text_field', $forms );
            $moderated_forms = array();

            foreach( $forms as $form ) {
                $moderated_forms[] = $this->profile_forms[$form];
            }

        } else {

            $moderated_forms[] = __( 'None', 'content-moderation' );
        }
?>
        <div>
            <div><?php echo sprintf( __( '%s waiting for profile update approval.', 'content-moderation' ), $this->moderation_count ); ?></div>
            <div><?php echo sprintf( __( '%s being denied their profile update.', 'content-moderation' ), $denied_count ); ?></div>
            <?php
            if ( UM()->options()->get( 'um_content_moderation_delay_update' ) != 1 ) {?>
                <div><?php echo sprintf( __( '%s with rollbacks.', 'content-moderation' ), $rollback_count ); ?></div>
            <?php
            }

            $url = get_admin_url() . 'admin.php?page=um_options&tab=extensions&section=content-moderation';
            $settings_link = '<a href="' . esc_url( $url ) . '">' . __( 'Plugin settings', 'content-moderation' ) . '</a>';

            echo '<hr>'; ?>
            <div><?php echo $settings_link; ?></div>
            <div><?php echo sprintf( __( 'Moderated Roles: %s', 'content-moderation' ), implode( ', ', $moderated_roles ) ); ?></div>
            <div><?php echo sprintf( __( 'Moderated Forms: %s', 'content-moderation' ), implode( ', ', $moderated_forms ) ); ?></div>
<?php
            clearstatcache();
            echo '<hr>';

            //$UM_class = new Options();
            //$reflectionProperty = new \ReflectionProperty( Options::class, 'options' );
            //$reflectionProperty->setAccessible( true );

            if ( UM()->options()->get( 'um_content_moderation_delay_update' ) == 1 ) {
                unset( $this->templates['rollback_user'] );
            }

            foreach( $this->templates as $template ) {
                //$subject = $reflectionProperty->getValue( $UM_class )[ "content_moderation_{$template}_email" . '_sub' ];
                //$status  = $reflectionProperty->getValue( $UM_class )[ "content_moderation_{$template}_email" . '_on' ];
                $subject = UM()->options()->get( "content_moderation_{$template}_email" . '_sub' );
                $status  = UM()->options()->get( "content_moderation_{$template}_email" . '_on' );

                $url_email  = get_site_url() . "/wp-admin/admin.php?page=um_options&tab=email&email=content_moderation_{$template}_email";
                $status  = empty( $status ) ? __( 'Email not active', 'content-moderation' ) : __( 'Email active', 'content-moderation' );
                $status  = sprintf( '<a href="%s">%s</a> ', esc_url( $url_email ), $status );

                $located = wp_normalize_path( STYLESHEETPATH . '/ultimate-member/email/' . "content_moderation_{$template}_email" . '.php' );
                $exists  = file_exists( $located ) ? '' : __( 'Template not found', 'content-moderation' )?>

                <div><?php echo sprintf( __( '%s: %s %s', 'content-moderation' ), $subject, $status, $exists ); ?></div>
<?php       }?>

        </div>
<?php
        $this->content_moderation_reset_button();
    }

    public function content_moderation_reset_button() {

        $url_content_moderation = add_query_arg(
            array(
                'um_adm_action' => 'content_moderation',
                '_wpnonce'      => wp_create_nonce( 'content_moderation' ),
            )
        );

        $button_text = ( UM()->options()->get( 'um_content_moderation_delay_update' ) == 1 ) ?
                            __( 'Reset Moderation cache counters', 'content-moderation' ) :
                            __( 'Reset any left User Profile update values and Moderation cache counters', 'content-moderation' );
?>
        <hr>
        <p>
            <a href="<?php echo esc_url( $url_content_moderation ); ?>" class="button">
                <?php esc_attr_e( $button_text ); ?>
            </a>
        </p>
<?php
    }

    public function content_moderation_reset() {

        global $wpdb;

        $count = 0;
        if ( UM()->options()->get( 'um_content_moderation_delay_update' ) != 1 ) {

            $action_users = $wpdb->get_results( "SELECT * FROM {$wpdb->usermeta} WHERE meta_key = 'um_content_moderation' AND meta_value != '0' " );

            if ( ! empty( $action_users )) {

                foreach( $action_users as $action_user ) {

                    um_fetch_user( $action_user->user_id );
                    if ( um_user( 'account_status' ) == 'approved' ) {

                        $count++;
                        update_user_meta( $action_user->user_id, 'um_content_moderation', 0 );
                        update_user_meta( $action_user->user_id, 'um_diff_updates', null );
                        UM()->user()->remove_cache( $action_user->user_id );
                    }
                }
            }
        }

        foreach( $this->cached_meta_keys as $meta_key ) {

            if ( get_transient( $this->transient_name . $meta_key ) !== false ) {
                delete_transient( $this->transient_name . $meta_key );
            }
        }

        $url = add_query_arg(
            array(
                'page'   => 'ultimatemember',
                'update' => 'content_moderation_reset',
                'result' =>  $count,
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }

    public function content_moderation_reset_notice( $message, $update ) {

        if ( $update == 'content_moderation_reset' ) {

            if ( UM()->options()->get( 'um_content_moderation_delay_update' ) != 1 ) {

                $message[0]['content'] = sprintf( __( 'Content Moderation removed %s left User Profile update values and all Moderation cache counters.', 'content-moderation' ),
                                                        sanitize_text_field( $_REQUEST['result'] ));
            } else {

                $message[0]['content'] = __( 'Content Moderation removed all Moderation cache counters.', 'content-moderation' );
            }
        }

        return $message;
    }

    public function um_user_edit_profile_content_moderation( $args ) {

        if ( isset( $args['custom_fields'] )) {

            $custom_fields = maybe_unserialize( $args['custom_fields'] );

            foreach( $custom_fields as $meta_key => $value ) {
                if ( is_array( $value ) && isset( $value['type'] )) {
                    $this->update_field_types[$meta_key] = $value['type'];
                }
            }

            $this->update_field_types['description'] = 'textarea';
        }
    }

    public function redirect_to_content_moderation( $user_id = false ) {

        if ( ! empty( $user_id )) {

            UM()->user()->remove_cache( $user_id );
            um_fetch_user( $user_id );
        }

        $uri = add_query_arg( 'content_moderation', 'awaiting_profile_review', admin_url( 'users.php' ) );

        wp_safe_redirect( $uri );
        exit;
    }

    public function um_admin_views_users_content_moderation( $views ) {

        $moderation_count = $this->count_content_values( 'um_content_moderation' );

        if ( isset( $_REQUEST['content_moderation'] ) && sanitize_key( $_REQUEST['content_moderation'] ) === 'awaiting_profile_review' ) {

            $current = 'class="current"';
            $views['all'] = str_replace( 'class="current"', '', $views['all'] );

        } else {
            $current = '';
        }

        $views['moderation'] = '<a ' . $current . 'href="' . esc_url( admin_url( 'users.php' ) . '?content_moderation=awaiting_profile_review' ) . '">' .
                                __( 'Content Moderation', 'content-moderation' ) . ' <span class="count">(' . $moderation_count . ')</span></a>';

        return $views;
    }

    public function manage_users_columns_content_moderation( $columns ) {

        if ( isset( $_REQUEST['content_moderation'] ) && sanitize_key( $_REQUEST['content_moderation'] ) === 'awaiting_profile_review' ) {

            $columns['content_moderation'] = __( 'Update/Denial', 'content-moderation' );
        }

        return $columns;
    }

    public function register_sortable_columns_custom( $columns ) {

        $columns['content_moderation'] = 'content_moderation';

        return $columns;
    }

    public function pre_get_users_sort_columns_custom( $query ) {

        if ( $query->get( 'orderby' ) == 'content_moderation' ) {
             $query->set( 'orderby',  'meta_value' );
             $query->set( 'meta_key', 'um_content_moderation' );
        }
    }

    public function manage_users_custom_column_content_moderation( $value, $column_name, $user_id ) {

        if ( $column_name == 'account_status' ) {

            um_fetch_user( $user_id );
            $value = um_user( 'account_status_name' );

            if ( (int)um_user( 'um_content_moderation' ) > 1000 ) {
                $value = __( 'Content Moderation', 'content-moderation' );
            }

            um_reset_user();
        }

        if ( $column_name == 'content_moderation' ) {

            um_fetch_user( $user_id );

            $um_content_moderation = um_user( 'um_content_moderation' );
            if ( ! empty( $um_content_moderation ) && (int)$um_content_moderation > 1000 ) {
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

        foreach( $this->slugs as $slug ) {

            $located = UM()->mail()->locate_template( $slug );

            if ( ! is_file( $located ) || filesize( $located ) == 0 ) {
                $located = wp_normalize_path( STYLESHEETPATH . '/ultimate-member/email/' . $slug . '.php' );
            }

            clearstatcache();
            if ( ! file_exists( $located ) || filesize( $located ) == 0 ) {

                wp_mkdir_p( dirname( $located ) );

                $email_source = file_get_contents( Plugin_Path_CM . $slug . '.php' );
                file_put_contents( $located, $email_source );

                if ( ! file_exists( $located ) ) {
                    file_put_contents( um_path . 'templates/email/' . $slug . '.php', $email_source );
                }
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
            echo $this->create_profile_difference_message();
            um_reset_user();

        } else {

            echo '<p><label>' . __( 'No access', 'content-moderation' ) . '</label></p>';
        }

        echo '</div>';
    }

    public function create_profile_difference_message() {

        ob_start();

        echo '<p><label>' . sprintf( __( 'Profile Update submitted %s by User %s', 'content-moderation' ),
                                    date( 'Y-m-d H:i:s', um_user( 'um_content_moderation' )), um_user( 'user_login' )) . '</label></p>';

        $um_denial_profile_updates = um_user( 'um_denial_profile_updates' );
        if ( ! empty( $um_denial_profile_updates ) && (int)$um_denial_profile_updates > 0 ) {
            echo '<p><label>' . sprintf( __( 'Profile Update Denial sent %s', 'content-moderation' ),
                                        date( 'Y-m-d H:i:s', $um_denial_profile_updates )) . '</label></p>';
        }

        if ( UM()->options()->get( 'um_content_moderation_delay_update' ) != 1 ) {

            $um_rollback_profile_updates = um_user( 'um_rollback_profile_updates' );
            if ( ! empty( $um_rollback_profile_updates ) && (int)$um_rollback_profile_updates > 0 ) {
                echo '<p><label>' . sprintf( __( 'Last Profile Rollback of updates %s', 'content-moderation' ),
                                            date( 'Y-m-d H:i:s', $um_rollback_profile_updates )) . '</label></p>';
            }
        }

        $diff_updates = maybe_unserialize( um_user( 'um_diff_updates' ));

        $old = __( 'Old:', 'content-moderation' );
        $new = __( 'New:', 'content-moderation' );

        $output = array();

        foreach( $diff_updates as $meta_key => $meta_value ) {

            $meta_value = $this->meta_value_any_difference( $meta_value );

            if ( is_array( $meta_value )) {

                $field = UM()->builtin()->get_a_field( $meta_key );
                $title = isset( $field['title'] ) ? esc_attr( $field['title'] ) : __( 'No text', 'content-moderation' );

                if ( in_array( $meta_key, $this->not_update_user_keys )) {
                    $title .= '<span title="' . sprintf( __( 'No rollback possible for the meta_key %s', 'content-moderation' ), $meta_key ) . '" style="color: red;"> *</span>';
                }

                if ( empty( $meta_value['old'] ) || empty( $meta_value['new'] )) {

                    if ( empty( $meta_value['old'] )) {
                        $text_old = __( '(empty)', 'content-moderation' );
                    } else {
                        $text_old = $meta_value['old'];
                    }

                    if ( empty( $meta_value['new'] )) {
                        $text_new = __( '(empty)', 'content-moderation' );
                    } else {
                        $text_new = $meta_value['new'];
                    }

                } else {

                    if ( $meta_value['type'] == 'textarea' ) {

                        $meta_value['old'] = str_replace( array( "\n", "\r", "\t" ), ' ', $meta_value['old'] );
                        $meta_value['new'] = str_replace( array( "\n", "\r", "\t" ), ' ', $meta_value['new'] );
                    }

                    $array_old = array_map( 'sanitize_text_field', array_map( 'trim', explode( ' ', $meta_value['old'] )));
                    $array_new = array_map( 'sanitize_text_field', array_map( 'trim', explode( ' ', $meta_value['new'] )));

                    if ( count( $array_old ) == 1 && count( $array_new ) == 1 ) {

                        $text_old = $meta_value['old'];
                        $text_new = $meta_value['new'];

                    } else {

                        $diffs = $this->compute_Diff( $array_old, $array_new );

                        $text_new = '';
                        $text_old = '';
                        $old_code = false;
                        $new_code = false;
                        $code = '<strong style="font-weight:900">';

                        foreach( $diffs['mask'] as $key => $diff ) {
                            switch( $diff ) {
                                case CM_UNMODIFIED: if ( $old_code ) {
                                                        $text_old .= '</strong>';
                                                        $old_code = false;
                                                    }
                                                    if ( $new_code ) {
                                                        $text_new .= '</strong>';
                                                        $new_code = false;
                                                    }
                                                    $text_old .= $diffs['values'][$key] . ' ';
                                                    $text_new .= $diffs['values'][$key] . ' ';
                                                    break;

                                case CM_DELETED:    if ( $old_code ) {
                                                        $text_old .= $diffs['values'][$key] . ' ';
                                                    } else {
                                                        $text_old .= $code . $diffs['values'][$key] . ' ';
                                                        $old_code = true;
                                                    }
                                                    break;

                                case CM_INSERTED:   if ( $new_code ) {
                                                        $text_new .= $diffs['values'][$key] . ' ';
                                                    } else {
                                                        $text_new .= $code . $diffs['values'][$key] . ' ';
                                                        $new_code = true;
                                                    }
                                                    break;

                                default:            break;
                            }
                        }

                        if ( $old_code ) {
                            $text_old = rtrim( $text_old );
                            $text_old .= '</strong>';
                        }

                        if ( $new_code ) {
                            $text_new = rtrim( $text_new );
                            $text_new .= '</strong>';
                        }

                        if ( $text_old == $text_new ) {
                            $text_new = __( 'Format changes only', 'content-moderation' );
                        }

                    }
                }

                $output[] = "<p><label>{$title} - {$meta_key}</label><br />
                             <span class=\"diff-updates\"><label>{$old}</label>{$text_old}<br />
                             <label>{$new}</label>{$text_new}</span></p>";
            }
        }

        if ( count( $output ) > 0 ) {
            sort( $output );
            echo implode( '', $output );

        } else {

            echo '<p><label>' . __( 'No updates found', 'content-moderation' ) . '</label></p>';
            echo '<p><label>' . __( 'Image/File updates are not logged at the moment.', 'content-moderation' ) . '</label></p>';
        }

        return ob_get_clean();
    }

    public function load_modal_content_moderation() {

        ?><div id="UM_preview_profile_update" style="display:none">
            <div class="um-admin-modal-head">
                <h3><?php _e( "Review Profile Content Moderation", "content-moderation" ); ?></h3>
            </div>
            <div class="um-admin-modal-body"></div>
            <div class="um-admin-modal-foot"></div>
        </div>

        <div id="UM_preview_registration" style="display:none">
            <div class="um-admin-modal-head">
                <h3><?php _e( 'Review Registration Details', 'content-moderation' ); ?></h3>
            </div>
            <div class="um-admin-modal-body"></div>
            <div class="um-admin-modal-foot"></div>
        </div><?php
    }

    public function um_admin_user_row_actions_content_moderation( $actions, $user_id ) {

        if ( isset( $_REQUEST['content_moderation'] ) && sanitize_key( $_REQUEST['content_moderation'] ) === 'awaiting_profile_review' ) {

            $actions['view_info_update'] = '<a href="javascript:void(0);" data-modal="UM_preview_profile_update"
                                            data-modal-size="smaller" data-dynamic-content="content_moderation_review_update"
                                            data-arg1="' . esc_attr( $user_id ) . '" data-arg2="profile_updates">' .
                                            __( 'Moderation', 'content-moderation' ) .  '</a>';
        }

        return $actions;
    }

    public function um_admin_bulk_user_actions_content_moderation( $actions ) {

        if ( isset( $_REQUEST['content_moderation'] ) && sanitize_key( $_REQUEST['content_moderation'] ) === 'awaiting_profile_review' ) {

            $output = ob_get_clean();
            $output = str_replace( __( 'UM Action', 'content-moderation' ), __( 'UM Content Moderation', 'content-moderation' ), $output );
            ob_start();
            echo $output;

            $actions = array();

            if ( UM()->options()->get( 'um_content_moderation_delay_update' ) == 1 ) {
                $actions['um_accept_profile_update'] = array( 'label' => __( 'Approve Profile Update', 'content-moderation' ));

            } else {

                $actions['um_approve_membership']      = array( 'label' => __( 'Approve Profile Update', 'content-moderation' ));
                $actions['um_rollback_profile_update'] = array( 'label' => __( 'Rollback Profile Update', 'content-moderation' ));
            }

            $actions['um_deny_profile_update'] = array( 'label' => __( 'Deny Profile Update', 'content-moderation' ));
            $actions['um_deactivate']          = array( 'label' => __( 'Deactivate', 'content-moderation' ));
        }

        return $actions;
    }

    public function content_moderation_action( $user_id = false ) {

        if ( current_user_can( 'administrator' ) && UM()->options()->get( 'um_content_moderation_admin_disable' ) == 1 ) {
            return false;
        }

        $um_content_moderation_forms = UM()->options()->get( 'um_content_moderation_forms' );

        if ( ! empty( $um_content_moderation_forms )) {
            $um_content_moderation_forms = array_map( 'sanitize_text_field', $um_content_moderation_forms );

            $form_id = sanitize_text_field( $_POST['form_id'] );
            if ( in_array( $form_id, $um_content_moderation_forms )) {

                $um_content_moderation_roles = UM()->options()->get( 'um_content_moderation_roles' );

                if ( ! empty( $um_content_moderation_roles )) {
                    if ( ! empty( $user_id ) && in_array( UM()->roles()->get_priority_user_role( $user_id ), array_map( 'sanitize_text_field', $um_content_moderation_roles ))) {

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function um_deny_profile_update_content_moderation( $user_id = false ) {

        if ( ! empty( $user_id )) {

            um_fetch_user( $user_id );
            $this->send( um_user( 'user_email' ), UM()->options()->get( 'um_content_moderation_denial_user_email' ) );

            update_user_meta( $user_id, 'um_denial_profile_updates', current_time( 'timestamp' ) );
            $this->count_content_values( 'um_denial_profile_updates', 1 );

            $delay_update = um_user( 'um_delay_profile_updates' );
            if ( UM()->options()->get( 'um_content_moderation_delay_update' ) == 1 || ! empty( $delay_update )) {

                update_user_meta( $user_id, 'um_delay_profile_updates', null );
                update_user_meta( $user_id, 'um_diff_updates', null );
                update_user_meta( $user_id, 'um_content_moderation', 0 );
                $this->count_content_values( 'um_content_moderation', -1 );
            }
        }

        $this->redirect_to_content_moderation( $user_id );
    }

    public function um_accept_profile_update_content_moderation( $user_id ) {

        $user = get_userdata( $user_id );
        if ( ! empty( $user ) && ! is_wp_error( $user ) ) {

            um_fetch_user( $user_id );

            $delay_profile_updates = maybe_unserialize( um_user( 'um_delay_profile_updates' ));
            $whitelist_saved = false;
            $to_update = false;

            if ( is_array( $delay_profile_updates )) {

                if ( isset( $delay_profile_updates['to_update'] ) && ! empty( $delay_profile_updates['to_update'] )) {
                    $to_update = $delay_profile_updates['to_update'];

                    if ( isset( $delay_profile_updates['whitelist'] ) && ! empty( $delay_profile_updates['whitelist'] )) {

                        $whitelist_saved = UM()->form()->usermeta_whitelist;
                        UM()->form()->usermeta_whitelist = $delay_profile_updates['whitelist'];

                    } else {
                        $to_update = false;
                    }
                }
            }

            if ( ! empty( $to_update )) {

                UM()->user()->update_profile( $to_update );
                do_action( 'um_update_profile_full_name', $user_id, $to_update );

                update_user_meta( $user_id, 'um_diff_updates', null );
                update_user_meta( $user_id, 'um_delay_profile_updates', null );
                update_user_meta( $user_id, 'um_content_moderation', 0 );
                update_user_meta( $user_id, 'um_content_moderation_update', $this->last_midnight_today() );
                $this->count_content_values( 'um_content_moderation', -1 );

                $this->send( um_user( 'user_email' ), UM()->options()->get( 'content_moderation_accept_user_email' ) );

                if ( ! empty( $whitelist_saved )) {
                    UM()->form()->usermeta_whitelist = $whitelist_saved;
                }
            }

            $this->redirect_to_content_moderation( $user_id );
        }

        $this->redirect_to_content_moderation();
    }

    public function meta_value_any_difference( $meta_value ) {

        $meta_value['old'] = maybe_unserialize( $meta_value['old'] );
        $meta_value['new'] = maybe_unserialize( $meta_value['new'] );

        if ( is_array( $meta_value['old'] )) {
            $meta_value['old'] = implode( ',', $meta_value['old'] );
        }
        if ( is_array( $meta_value['new'] )) {
            $meta_value['new'] = implode( ',', $meta_value['new'] );
        }

        if ( empty( $meta_value['old'] ) && empty( $meta_value['new'] )) {
            return false;
        }

        if ( mb_strtolower( $meta_value['old']) != mb_strtolower( $meta_value['new'])) {

            return $meta_value;

        } else {

            return false;
        }
    }

    public function reset_user_after_update( $user_id = false ) {

        if ( ! empty( $user_id )) {

            update_user_meta( $user_id, 'um_content_moderation', 0 );
            update_user_meta( $user_id, 'um_content_moderation_update', $this->last_midnight_today() );
            update_user_meta( $user_id, 'um_diff_updates', null );
            $this->count_content_values( 'um_content_moderation', -1 );

            if ( ! empty( um_user( 'um_denial_profile_updates' )) && (int)um_user( 'um_denial_profile_updates' ) > 0 ) {
                update_user_meta( $user_id, 'um_denial_profile_updates', 0 );
                $this->count_content_values( 'um_denial_profile_updates', -1 );
            }

            update_user_meta( $user_id, 'account_status', 'approved' );
        }

        $this->redirect_to_content_moderation( $user_id );
    }

    public function um_rollback_profile_update_content_moderation( $user_id ) {

        if ( current_user_can( 'administrator' ) && um_can_view_profile( $user_id )) {

            um_fetch_user( $user_id );

            $diff_updates = maybe_unserialize( um_user( 'um_diff_updates' ));
            $submitted    = maybe_unserialize( um_user( 'submitted' ));

            $update_user_keys = UM()->user()->update_user_keys;

            $changes = array();

            foreach( $diff_updates as $meta_key => $meta_value ) {

                if ( in_array( $meta_key, UM()->user()->banned_keys ) ) {
                    continue;
                }

                $meta_value = $this->meta_value_any_difference( $meta_value );

                if ( is_array( $meta_value )) {

                    if ( ! in_array( $meta_key, $update_user_keys ) ) {

                        if ( in_array( $meta_key, array( 'first_name', 'last_name' ))) {
                            $changes[$meta_key] = $meta_value['old'];
                        }

                        switch( $diff_updates[$meta_key]['type'] ) {

                            case 'radio':
                            case 'checkbox':
                            case 'multiselect':     $meta_value['old'] = explode( ',', $meta_value['old'] );
                            case 'url':
                            case 'tel':
                            case 'number':
                            case 'rating':
                            case 'date':
                            case 'select':
                            case 'text':
                            case 'textarea':        if ( $meta_value['old'] === 0 ) {
                                                        update_user_meta( $user_id, $meta_key, '0' );
                                                        $submitted[$meta_key] = '0';
                                                    } else {
                                                        update_user_meta( $user_id, $meta_key, $meta_value['old'] );
                                                        $submitted[$meta_key] = $meta_value['old'];
                                                    }
                                                    break;

                            default:                break;
                        }

                    } else {

                        if ( ! in_array( $meta_key, $this->not_update_user_keys )) {
                            $args = array();
                            $args['ID'] = $user_id;
                            $args[$meta_key] = $meta_value['old'];

                            wp_update_user( $args );
                        }
                    }
                }
            }

            if ( count( $changes ) > 0 ) {
                do_action( 'um_update_profile_full_name', $user_id, $changes );
            }

            $this->send( um_user( 'user_email' ), UM()->options()->get( 'um_content_moderation_rollback_user_email' ) );

            update_user_meta( $user_id, 'submitted', $submitted );
            update_user_meta( $user_id, 'um_rollback_profile_updates', current_time( 'timestamp' ) );
            $this->count_content_values( 'um_rollback_profile_updates', 1 );

            $this->reset_user_after_update( $user_id );
        }
    }

    public function um_disable_email_notification_content_moderation( $false, $email, $template, $args ) {

        if ( $template == 'approved_email' && email_exists( $email )) {

            $user = get_user_by( 'email', $email );
            if ( isset( $user ) && is_a( $user, '\WP_User' ) ) {

                um_fetch_user( $user->ID );
                $um_content_moderation = um_user( 'um_content_moderation' );

                if ( ! empty( $um_content_moderation ) && (int)$um_content_moderation > 0 ) {

                    $this->send( $email, UM()->options()->get( 'um_content_moderation_accept_user_email' ) );

                    $this->reset_user_after_update( $user->ID );
                }
            }
        }

        return $false;
    }

    public function send( $email, $template, $args = array() ) {

        if ( empty( $email ) || empty( $template ) || empty( UM()->options()->get( $template . '_on' ) ) ) {
            return;
        }

        add_filter( 'um_template_tags_patterns_hook', array( UM()->mail(), 'add_placeholder' ), 10, 1 );
        add_filter( 'um_template_tags_replaces_hook', array( UM()->mail(), 'add_replace_placeholder' ), 10, 1 );

        $subject = wp_unslash( um_convert_tags( UM()->options()->get( $template . '_sub' ), $args ) );
        $subject = html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' );

        $message = UM()->mail()->prepare_template( $template, $args );

        $attachments = array();
        $headers     = 'From: ' . stripslashes( UM()->options()->get( 'mail_from' ) ) . ' <' . esc_attr( UM()->options()->get( 'mail_from_addr' )) . '>' . "\r\n";

        if ( UM()->options()->get( 'email_html' ) == 1 ) {
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

            if ( sanitize_key( $_REQUEST['content_moderation'] ) === 'awaiting_profile_review' ) {

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

        add_submenu_page( 'ultimatemember', __( 'Content Moderation', 'content-moderation' ),
                                            __( 'Content Moderation', 'content-moderation' ) . sprintf( ' (%d)', intval( $this->count_content_values( 'um_content_moderation' ) )),
                                                'manage_options', $url , '' );

        //$this->copy_email_notifications_content_moderation();
    }

    public function um_user_pre_updating_profile_save_before_after( $to_update, $user_id ) {

        if ( $this->content_moderation_action( $user_id ) ) {

            $um_content_moderation = um_user( 'um_content_moderation' );

            if ( UM()->options()->get( 'um_content_moderation_delay_update' ) == 1 ) {

                update_user_meta( $user_id, 'um_delay_profile_updates', array( 'to_update' => $to_update, 'whitelist' => UM()->form()->usermeta_whitelist ));
                $diff_updates = array();
                $um_content_moderation = 0;

            } else {

                $diff_updates = maybe_unserialize( um_user( 'um_diff_updates' ));

                if ( empty( $diff_updates )) {
                    $diff_updates = array();
                }
            }

            foreach( $to_update as $meta_key => $meta_value ) {

                if ( empty( $diff_updates[$meta_key]['old'] )) {
                    $diff_updates[$meta_key]['old'] = um_user( $meta_key );
                }

                $diff_updates[$meta_key]['new'] = $meta_value;

                if ( isset( $this->update_field_types[$meta_key] )) {
                    $diff_updates[$meta_key]['type'] = $this->update_field_types[$meta_key];

                } else {

                    $diff_updates[$meta_key]['type'] = 'text';
                }
            }

            update_user_meta( $user_id, 'um_diff_updates', $diff_updates );

            if ( empty( $um_content_moderation ) || (int)$um_content_moderation == 0 ) {

                update_user_meta( $user_id, 'um_content_moderation', current_time( 'timestamp' ) );
                $this->count_content_values( 'um_content_moderation', 1 );

            } else {
                $this->send_email = false;
            }
        }
    }

    public function um_user_after_updating_profile_set_pending( $to_update, $user_id, $args ) {

        if ( $this->send_email ) {
            if ( ! empty( $user_id )) {

                um_fetch_user( $user_id );

                add_filter( 'um_template_tags_patterns_hook', array( $this, 'content_moderation_template_tags_patterns' ), 10, 1 );
                add_filter( 'um_template_tags_replaces_hook', array( $this, 'content_moderation_template_tags_replaces' ), 10, 1 );

                if ( UM()->options()->get( 'um_content_moderation_delay_update' ) != 1 ) {
                    UM()->user()->set_status( 'awaiting_admin_review' );
                }

                UM()->mail()->send( um_user( 'user_email' ), UM()->options()->get( 'um_content_moderation_pending_user_email' ) );
                UM()->mail()->send( get_bloginfo( 'admin_email' ), UM()->options()->get( 'um_content_moderation_admin_email' ), array( 'admin' => true ) );
            }
        }
    }

    public function content_moderation_template_tags_patterns( $search ) {

        $search[] = '{content_moderation}';
        return $search;
    }

    public function content_moderation_template_tags_replaces( $replace ) {

        $replace[] = $this->create_profile_difference_message();
        return $replace;
    }
//*********
    public function get_possible_plugin_update( $plugin ) {

        $update = __( 'Plugin version update failure', 'content-moderation' );
        $transient = get_transient( $plugin );

        if ( is_array( $transient ) && isset( $transient['status'] )) {
            $update = $transient['status'];
        }

        if ( defined( 'Plugin_File_CM' )) {

            $plugin_data = get_plugin_data( Plugin_File_CM );
            if ( ! empty( $plugin_data )) {

                if ( empty( $transient ) || $this->new_version_test_required( $transient, $plugin_data )) {

                    if ( extension_loaded( 'curl' )) {

                        $github_user = 'MissVeronica';
                        $url = "https://api.github.com/repos/{$github_user}/{$plugin}/contents/README.md";

                        $curl = curl_init();
                        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
                        curl_setopt( $curl, CURLOPT_BINARYTRANSFER, 1 );
                        curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, 1 );
                        curl_setopt( $curl, CURLOPT_URL, $url );
                        curl_setopt( $curl, CURLOPT_USERAGENT, $github_user );

                        $content = json_decode( curl_exec( $curl ), true );
                        $error = curl_error( $curl );
                        curl_close( $curl );

                        if ( ! $error ) {

                            switch( $this->validate_new_plugin_version( $plugin_data, $content ) ) {

                                case 0:     $update = __( 'Plugin version update verification failed', 'content-moderation' );
                                            break;
                                case 1:     $update = '<a href="' . esc_url( $plugin_data['UpdateURI'] ) . '" target="_blank">';
                                            $update = sprintf( __( 'Update to %s plugin version %s%s is now available for download.', 'content-moderation' ), $update, esc_attr( $this->new_plugin_version ), '</a>' );
                                            break;
                                case 2:     $update = sprintf( __( 'Plugin is updated to the latest version %s.', 'content-moderation' ), esc_attr( $plugin_data['Version'] ));
                                            break;
                                case 3:     $update = __( 'Unknown encoding format returned from GitHub', 'content-moderation' );
                                            break;
                                case 4:     $update = __( 'Version number not found', 'content-moderation' );
                                            break;
                                case 5:     $update = sprintf( __( 'Update to plugin version %s is now available for download from GitHub.', 'content-moderation' ), esc_attr( $this->new_plugin_version ));
                                            break;
                                default:    $update = __( 'Plugin version update validation failure', 'content-moderation' );
                                            break;
                            }

                            if ( isset( $plugin_data['PluginURI'] ) && ! empty( $plugin_data['PluginURI'] )) {

                                $update .= sprintf( ' <a href="%s" target="_blank" title="%s">%s</a>',
                                                            esc_url( $plugin_data['PluginURI'] ),
                                                            __( 'GitHub plugin documentation and download', 'content-moderation' ),
                                                            __( 'Plugin documentation', 'content-moderation' ));
                            }

                            $today = date_i18n( 'Y/m/d H:i:s', current_time( 'timestamp' ));
                            $update .= '<br />' . sprintf( __( 'Github plugin version status is checked each 24 hours last at %s.', 'content-moderation' ), esc_attr( $today ));

                            set_transient( $plugin,
                                            array( 'status'       => $update,
                                                  'last_version' => $plugin_data['Version'] ),
                                            24 * HOUR_IN_SECONDS
                                        );

                        } else {
                            $update = sprintf( __( 'GitHub remote connection cURL error: %s', 'content-moderation' ), $error );
                        }

                    } else {
                        $update = __( 'cURL extension not loaded by PHP', 'content-moderation' );
                    }
                }
            }
        }

        return wp_kses( $update, $this->html_allowed );
    }

    public function new_version_test_required( $transient, $plugin_data ) {

        $bool = false;
        if ( isset( $transient['last_version'] ) && $plugin_data['Version'] != $transient['last_version'] ) {
            $bool = true;
        }

        return $bool;
    }

    public function validate_new_plugin_version( $plugin_data, $content ) {

        $validation = 0;
        if ( is_array( $content ) && isset( $content['content'] )) {

            $validation = 3;
            if ( $content['encoding'] == 'base64' ) {

                $readme  = base64_decode( $content['content'] );
                $version = strrpos( $readme, 'Version' );

                $validation = 4;
                if ( $version !== false ) {

                    $version = array_map( 'trim', array_map( 'sanitize_text_field', explode( "\n", substr( $readme, $version, 40 ))));

                    if ( isset( $plugin_data['Version'] ) && ! empty( $plugin_data['Version'] )) {

                        $version = explode( ' ', $version[0] );
                        $index = 1;
                        if ( isset( $version[$index] ) && ! empty( $version[$index] )) {

                            $validation = 2;
                            if ( sanitize_text_field( $plugin_data['Version'] ) != $version[$index] ) {

                                $validation = 5;
                                if ( isset( $plugin_data['UpdateURI'] ) && ! empty( $plugin_data['UpdateURI'] )) {

                                    $this->new_plugin_version = $version[$index];
                                    $validation = 1;
                                }
                            } 
                        }
                    }
                }
            }
        }

        return $validation;
    }

    public function um_settings_structure_content_moderation( $settings ) {

        if ( isset( $_REQUEST['page'] ) &&  $_REQUEST['page'] == 'um_options' ) {
            if ( isset( $_REQUEST['tab'] ) &&  $_REQUEST['tab'] == 'extensions' ) {

                $settings['extensions']['sections']['content-moderation']['title'] = __( 'Profile Content Moderation', 'content-moderation' );

                if ( isset( $_REQUEST['section'] ) && $_REQUEST['section'] == 'content-moderation' ) {

                    $settings['extensions']['sections']['content-moderation']['description'] = $this->get_possible_plugin_update( 'um-content-moderation' );
                    $settings['extensions']['sections']['content-moderation']['fields']      = $this->create_plugin_settings_fields();
                }
            }
        }

        return $settings;
    }

    public function create_plugin_settings_fields() {

        $plugin_data = get_plugin_data( __FILE__ );

        $link = sprintf( '<a href="%s" target="_blank" title="%s">%s</a>',
                                        esc_url( $plugin_data['PluginURI'] ),
                                    __( 'GitHub plugin documentation and download', 'content-moderation' ),
                                    __( 'Plugin', 'content-moderation' ));

        $notification_emails = array();
        $emails = UM()->config()->email_notifications;
        foreach( $emails as $key => $email ) {
            $notification_emails[$key] = $email['title'];
        }

        $prefix = '&nbsp; * &nbsp;';
        $section_fields = array();

        $section_fields[] = array(
                'id'             => 'content_moderation_header',
                'type'           => 'header',
                'label'          => __( 'Moderation Forms & Roles', 'content-moderation' ),
        );

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_forms',
                    'type'           => 'select',
                    'multi'          => true,
                    'size'           => 'medium',
                    'options'        => $this->profile_forms,
                    'label'          => $prefix . __( 'Profile Forms to Moderate', 'content-moderation' ),
                    'description'    => __( 'Select single or multiple Profile Forms for Content Moderation.', 'content-moderation' ),
                );

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_roles',
                    'type'           => 'select',
                    'multi'          => true,
                    'label'          => $prefix . __( 'User Roles to Moderate', 'content-moderation' ),
                    'description'    => __( 'Select the User Role(s) to be included in Content Moderation.', 'content-moderation' ),
                    'options'        => UM()->roles()->get_roles(),
                    'size'           => 'medium',
                );

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_modal_list',
                    'type'           => 'checkbox',
                    'label'          => $prefix . __( 'UM Dashboard Modal', 'content-moderation' ),
                    'checkbox_label' => __( 'Click to enable the UM Dashboard modal for Content Moderation.', 'content-moderation' ),
                );

        $section_fields[] = array(
            'id'             => 'content_moderation_header',
            'type'           => 'header',
            'label'          => __( 'User Info', 'content-moderation' ),
        );

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_update_status',
                    'type'           => 'checkbox',
                    'label'          => $prefix . __( 'Enable User Update Status', 'content-moderation' ),
                    'checkbox_label' => __( 'Click to enable a "days since update" colored Profile circle after the Profile page User name.', 'content-moderation' ),
                );

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_update_colors',
                    'type'           => 'text',
                    'label'          => $prefix . __( 'Enter colors for the Profile circle', 'content-moderation' ),
                    'description'    => __( 'Enter colors either by color name or HEX code comma separated for each day\'s display of the "days since update" Profile circle.', 'content-moderation' ) . '<br />' .
                                        __( 'Default color is "white" and is displayed for 7 days.', 'content-moderation' ) . ' <a href="https://www.w3schools.com/colors/colors_groups.asp" target="_blank">W3Schools HTML Color Groups</a>',
                    'conditional'    => array( 'um_content_moderation_update_status', '=', 1 ),
                );

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_update_opacity',
                    'type'           => 'checkbox',
                    'label'          => $prefix . __( 'Enable transparency increase for the Profile circle', 'content-moderation' ),
                    'checkbox_label' => __( 'Click to enable increased transparency of the "days since update" Profile circle for each day after approved update.', 'content-moderation' ),
                    'conditional'    => array( 'um_content_moderation_update_status', '=', 1 ),
                );

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_update_size',
                    'type'           => 'text',
                    'label'          => $prefix . __( 'Enter size in pixels for the Profile circle', 'content-moderation' ),
                    'size'           => 'small',
                    'description'    => __( 'Enter size in pixels for the "days since update" Profile circle. Default value is 24 pixels.', 'content-moderation' ),
                    'conditional'    => array( 'um_content_moderation_update_status', '=', 1 ),
                );

        $section_fields[] = array(
                'id'             => 'content_moderation_header',
                'type'           => 'header',
                'label'          => __( 'Moderation Process', 'content-moderation' ),
                'description'    => __( 'Default: User set to Admin Review status with Profile updated.', 'content-moderation' ) . '<br />' .
                                    __( 'Option from version 3.6.0: Delayed Profile update until approved by a site Moderator.', 'content-moderation' ) . '<br />',
            );

            if ( intval( $this->count_content_values( 'um_content_moderation' )) == 0 ) {

                $section_fields[] = array(
                        'id'             => 'um_content_moderation_delay_update',
                        'type'           => 'checkbox',
                        'label'          => $prefix . __( 'Delay User Profile update during Moderation', 'content-moderation' ),
                        'checkbox_label' => __( 'Click to enable the delay of the User Profile update until approved by a site Moderator.', 'content-moderation' ),
                    );

            } else {

                $description = ( UM()->options()->get( 'um_content_moderation_delay_update' ) == 1 ) ?
                                                        __( 'Option is enabled', 'content-moderation' ) :
                                                        __( 'Option is disabled', 'content-moderation' );

                $section_fields[] = array(
                    'id'             => 'content_moderation_header',
                    'type'           => 'header',
                    'label'          => $prefix . __( 'Delay User Profile update during Moderation', 'content-moderation' ),
                    'description'    => $description . '<br />' .
                                        __( 'Settings are only displayed for changes when the queue of users waiting for approval is empty.', 'content-moderation' ),
                );
            }

            if ( UM()->options()->get( 'um_content_moderation_delay_update' ) == 1 ) {

                $section_fields[] = array(
                        'id'             => 'um_content_moderation_delay_url',
                        'type'           => 'text',
                        'label'          => $prefix . __( 'Delay User Profile update URL at the Cog wheel menu', 'content-moderation' ),
                        'description'    => __( 'Enter an URL to a page where you explain the Content Moderation with delayed update procedure at your site.', 'content-moderation' ) . '<br />' .
                                            __( 'Link replaces "Edit Profile" when user is awaiting Content Moderation.', 'content-moderation' ) . '<br />' .
                                            __( 'Blank URL disables link and "Edit Profile" text.', 'content-moderation' ),
                        'size'           => 'medium',
                        'conditional'    => array( 'um_content_moderation_delay_update', '=', 1 ),
                    );

                $section_fields[] = array(
                        'id'             => 'um_content_moderation_delay_url_text',
                        'type'           => 'text',
                        'label'          => $prefix . __( 'Delay User Profile update text at the Cog wheel menu', 'content-moderation' ),
                        'description'    => __( 'Enter a short URL text message. Default text is "Why Content Moderation".', 'content-moderation' ),
                        'size'           => 'medium',
                        'conditional'    => array( 'um_content_moderation_delay_url', '!=', '' ),
                    );
            }

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_disable_logincheck',
                    'type'           => 'checkbox',
                    'label'          => $prefix . __( 'Allow Users Login', 'content-moderation' ),
                    'checkbox_label' => __( 'Click to disable UM status logincheck of Users not approved yet in Content Moderation.', 'content-moderation' ),
                    'conditional'    => array( 'um_content_moderation_delay_update', '!=', 1 ),
                );

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_admin_disable',
                    'type'           => 'checkbox',
                    'label'          => $prefix . __( 'Disable Admin updates Moderation', 'content-moderation' ),
                    'checkbox_label' => __( 'Click to disable Admin updates of Users from Content Moderation.', 'content-moderation' ),
                );

        $section_fields[] = array(
                'id'             => 'content_moderation_header',
                'type'           => 'header',
                'label'          => __( 'Registration Approval', 'content-moderation' ),
            );

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_double_optin',
                    'type'           => 'checkbox',
                    'label'          => $prefix . __( 'Enable Email Activation plus Admin Review', 'content-moderation' ),
                    'checkbox_label' => __( 'Click to enable Admin Review after successful Email Activation by the User.', 'content-moderation' ),
                    'description'    => __( 'UM Setting Registration email Activation must be set in advance.', 'content-moderation' ),
                );

        $section_fields[] = array(
                'id'             => 'content_moderation_header',
                'type'           => 'header',
                'label'          => __( 'Email Templates', 'content-moderation' ),
            );

            $url_email  = get_site_url() . "/wp-admin/admin.php?page=um_options&tab=email&email=" . UM()->options()->get( 'um_content_moderation_pending_user_email' );
            $settings = sprintf( ' <a href="%s">%s</a>', esc_url( $url_email ), __( 'Email settings', 'content-moderation' ));

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_pending_user_email',
                    'type'           => 'select',
                    'label'          => $prefix . __( 'User Pending Notification', 'content-moderation' ),
                    'description'    => __( 'Select the User Pending Notification Email template.', 'content-moderation' ) . $settings,
                    'options'        => $notification_emails,
                    'size'           => 'medium',
                );

            $url_email  = get_site_url() . "/wp-admin/admin.php?page=um_options&tab=email&email=" . UM()->options()->get( 'um_content_moderation_accept_user_email' );
            $settings = sprintf( ' <a href="%s">%s</a>', esc_url( $url_email ), __( 'Email settings', 'content-moderation' ));

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_accept_user_email',
                    'type'           => 'select',
                    'label'          => $prefix . __( 'User Accept Notification', 'content-moderation' ),
                    'description'    => __( 'Select the User Accept Notification Email template.', 'content-moderation' ) . $settings,
                    'options'        => $notification_emails,
                    'size'           => 'medium',
                );

            $url_email  = get_site_url() . "/wp-admin/admin.php?page=um_options&tab=email&email=" . UM()->options()->get( 'um_content_moderation_denial_user_email' );
            $settings = sprintf( ' <a href="%s">%s</a>', esc_url( $url_email ), __( 'Email settings', 'content-moderation' ));

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_denial_user_email',
                    'type'           => 'select',
                    'label'          => $prefix . __( 'User Denial Notification', 'content-moderation' ),
                    'description'    => __( 'Select the User Denial Notification Email template.', 'content-moderation' ) . $settings,
                    'options'        => $notification_emails,
                    'size'           => 'medium',
                );

            $url_email  = get_site_url() . "/wp-admin/admin.php?page=um_options&tab=email&email=" . UM()->options()->get( 'um_content_moderation_rollback_user_email' );
            $settings = sprintf( ' <a href="%s">%s</a>', esc_url( $url_email ), __( 'Email settings', 'content-moderation' ));

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_rollback_user_email',
                    'type'           => 'select',
                    'label'          => $prefix . __( 'User Rollback Notification', 'content-moderation' ),
                    'description'    => __( 'Select the User Rollback Notification Email template.', 'content-moderation' ) . $settings,
                    'options'        => $notification_emails,
                    'size'           => 'medium',
                    'conditional'    => array( 'um_content_moderation_delay_update', '!=', 1 ),
                );

            $url_email  = get_site_url() . "/wp-admin/admin.php?page=um_options&tab=email&email=" . UM()->options()->get( 'um_content_moderation_admin_email' );
            $settings = sprintf( ' <a href="%s">%s</a>', esc_url( $url_email ), __( 'Email settings', 'content-moderation' ));

            $section_fields[] = array(
                    'id'             => 'um_content_moderation_admin_email',
                    'type'           => 'select',
                    'label'          => $prefix . __( 'Admin Notification', 'content-moderation' ),
                    'description'    => __( 'Select the Admin Notification Email template.', 'content-moderation' ) . $settings,
                    'options'        => $notification_emails,
                    'size'           => 'medium',
                );

        return $section_fields;
    }

    public function um_email_notification_profile_content_moderation( $um_emails ) {

        $url = get_admin_url() . 'admin.php?page=um_options&tab=extensions&section=content-moderation';
        $settings_link = ' <a href="' . esc_url( $url ) . '">' . __( 'Plugin settings', 'content-moderation' ) . '</a>';

        $custom_emails = array(	'content_moderation_pending_user_email' => array(
                                        'key'            => 'content_moderation_pending_user_email',
                                        'title'          => __( 'Content Moderation - User Pending Notification', 'content-moderation' ),
                                        'description'    => __( 'User Pending Notification Email template', 'content-moderation' ) . $settings_link,
                                        'recipient'      => 'user',
                                        'default_active' => true,
                                        'subject'        => '[{site_name}] Profile review pending',
                                        'body'           => '',
                                ),

                                'content_moderation_accept_user_email' => array(
                                        'key'            => 'content_moderation_accept_user_email',
                                        'title'          => __( 'Content Moderation - User Accept Notification', 'content-moderation' ),
                                        'description'    => __( 'User Accepted Notification Email template', 'content-moderation' ) . $settings_link,
                                        'recipient'      => 'user',
                                        'default_active' => true,
                                        'subject'        => '[{site_name}] Profile is accepted',
                                        'body'           => '',
                                ),

                                'content_moderation_denial_user_email' => array(
                                        'key'            => 'content_moderation_denial_user_email',
                                        'title'          => __( 'Content Moderation - User Denial Notification', 'content-moderation' ),
                                        'description'    => __( 'User Denial Notification Email template', 'content-moderation' ) . $settings_link,
                                        'recipient'      => 'user',
                                        'default_active' => true,
                                        'subject'        => '[{site_name}] Profile is denied',
                                        'body'           => '',
                                ),

                                'content_moderation_rollback_user_email' => array(
                                        'key'            => 'content_moderation_rollback_user_email',
                                        'title'          => __( 'Content Moderation - User Rollback Notification', 'content-moderation' ),
                                        'description'    => __( 'User Rollback Notification Email template', 'content-moderation' ) . $settings_link,
                                        'recipient'      => 'user',
                                        'default_active' => true,
                                        'subject'        => '[{site_name}] Profile rollback',
                                        'body'           => '',
                                ),

                                'content_moderation_pending_admin_email' => array(
                                        'key'            => 'content_moderation_pending_admin_email',
                                        'title'          => __( 'Content Moderation - Admin Pending Notification', 'content-moderation' ),
                                        'description'    => __( 'Admin Pending Notification Email template', 'content-moderation' ) . $settings_link,
                                        'recipient'      => 'admin',
                                        'default_active' => true,
                                        'subject'        => '[{site_name}] Profile is updated',
                                        'body'           => '',
                                ),
                            );

        if ( UM()->options()->get( 'um_content_moderation_delay_update' ) == 1 ) {
            unset( $custom_emails['content_moderation_rollback_user_email'] );
        }

        //$UM_class = new Options();
        //$reflectionProperty = new \ReflectionProperty( Options::class, 'options' );
        //$reflectionProperty->setAccessible( true );

        foreach ( $custom_emails as $slug => $custom_email ) {

            if ( UM()->options()->get( $slug . '_on' ) === '' ) {

                $email_on = empty( $custom_email['default_active'] ) ? 0 : 1;
                UM()->options()->update( $this->slug . '_on', $email_on );
            }
    
            if ( UM()->options()->get( $slug . '_sub' ) === '' ) {
    
                UM()->options()->update( $slug . '_sub', $custom_email['subject'] );
            }

            //if ( ! array_key_exists( $slug . '_on', $reflectionProperty->getValue( $UM_class ) ) ) {

            //    $UM_options = $reflectionProperty->getValue( $UM_class );
            //    $UM_options[ $slug . '_on' ]  = empty( $custom_email['default_active'] ) ? 0 : 1;
            //    $UM_options[ $slug . '_sub' ] = $custom_email['subject'];
            //    $reflectionProperty->setValue( $UM_class, $UM_options );
            //}

            $this->slugs[] = $slug;
        }

        $this->copy_email_notifications_content_moderation();

        return array_merge( $um_emails, $custom_emails );
    }

    public function compute_Diff( $from, $to ) {

        $diffValues = array();
        $diffMask   = array();

        $dm = array();
        $n1 = count( $from );
        $n2 = count( $to );

        for ( $j = -1; $j < $n2; $j++ ) $dm[-1][$j] = CM_UNMODIFIED;
        for ( $i = -1; $i < $n1; $i++ ) $dm[$i][-1] = CM_UNMODIFIED;

        for ( $i = 0; $i < $n1; $i++ ) {
            for ( $j = 0; $j < $n2; $j++ ) {

                if ( $from[$i] == $to[$j] ) {
                    $ad = $dm[$i - 1][$j - 1];
                    $dm[$i][$j] = $ad + 1;

                } else {

                    $a1 = $dm[$i - 1][$j];
                    $a2 = $dm[$i][$j - 1];
                    $dm[$i][$j] = max( $a1, $a2 );
                }
            }
        }

        $i = $n1 - 1;
        $j = $n2 - 1;

        while (( $i > -1 ) || ( $j > -1 )) {

            if ( $j > -1 ) {

                if ( $dm[$i][$j - 1] == $dm[$i][$j] ) {
                    $diffValues[] = $to[$j];
                    $diffMask[] = CM_INSERTED;
                    $j--;
                    continue;
                }
            }

            if ( $i > -1 ) {

                if ( $dm[$i - 1][$j] == $dm[$i][$j] ) {
                    $diffValues[] = $from[$i];
                    $diffMask[] = CM_DELETED;
                    $i--;
                    continue;
                }
            }

            {
                $diffValues[] = $from[$i];
                $diffMask[] = CM_UNMODIFIED;
                $i--;
                $j--;
            }
        }

        $diffValues = array_reverse( $diffValues );
        $diffMask   = array_reverse( $diffMask );

        return array( 'values' => $diffValues, 'mask' => $diffMask );
    }


}

new UM_Profile_Content_Moderation();

