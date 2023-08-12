<?php
/**
 * Plugin Name:         Ultimate Member - Profile Content Moderation
 * Description:         Extension to Ultimate Member for Profile Content Moderation.
 * Version:             3.2.0
 * Requires PHP:        7.4
 * Author:              Miss Veronica
 * License:             GPL v3 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:          https://github.com/MissVeronica
 * Text Domain:         ultimate-member
 * Domain Path:         /languages
 * UM version:          2.6.9
 * Source computeDiff:  https://stackoverflow.com/questions/321294/highlight-the-difference-between-two-strings-in-php
 */

if ( ! defined( 'ABSPATH' ) ) exit; 
if ( ! class_exists( 'UM' ) ) return;

class UM_Profile_Content_Moderation {

    public $profile_forms        = array();
    public $notification_emails  = array();
    public $slug                 = array();
    public $update_field_types   = array();
    public $not_update_user_keys = array( 'role', 'pass', 'password' );
    public $send_email = false;

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
            add_filter( 'manage_users_sortable_columns', array( $this, 'register_sortable_columns_custom' ), 10, 1 );
            add_action( 'pre_get_users',                 array( $this, 'pre_get_users_sort_columns_custom' ));

            add_action( "um_admin_custom_hook_um_deny_profile_update", array( $this, 'um_deny_profile_update_content_moderation' ), 10, 1 );
            add_filter( 'um_disable_email_notification_sending',       array( $this, 'um_disable_email_notification_content_moderation' ), 10, 4 );
            add_filter( 'um_admin_bulk_user_actions_hook',             array( $this, 'um_admin_bulk_user_actions_content_moderation' ), 10, 1 );
            add_filter( 'um_admin_user_row_actions',                   array( $this, 'um_admin_user_row_actions_content_moderation' ), 10, 2 );
            add_action( 'load-toplevel_page_ultimatemember',           array( $this, 'load_toplevel_page_content_moderation' ) );

            add_action( 'um_admin_ajax_modal_content__hook_content_moderation_review_update', array( $this, 'content_moderation_review_update_ajax_modal' ));
            add_action( "um_admin_custom_hook_um_rollback_profile_update",                    array( $this, 'um_rollback_profile_update_content_moderation' ), 10, 1 );

            if ( isset( $_REQUEST['content_moderation'] ) && sanitize_key( $_REQUEST['content_moderation'] ) === 'awaiting_profile_review' ) {
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

        define( 'Content_Moderation_Path', plugin_dir_path( __FILE__ ) );
        define( 'CM_UNMODIFIED',  0 );
        define( 'CM_DELETED',    -1 );
        define( 'CM_INSERTED',    1 );

        add_action( 'um_user_pre_updating_profile',   array( $this, 'um_user_pre_updating_profile_save_before_after' ), 10, 2 );
        add_action( 'um_user_after_updating_profile', array( $this, 'um_user_after_updating_profile_set_pending' ), 10, 3 );
        add_action( 'um_user_edit_profile',           array( $this, 'um_user_edit_profile_content_moderation' ), 10, 1 );

    }

    public function load_toplevel_page_content_moderation() {

        add_meta_box( 'um-metaboxes-sidebox-20', __( 'Content Moderation', 'ultimate-member' ), array( $this, 'toplevel_page_content_moderation' ), 'toplevel_page_ultimatemember', 'side', 'core' );
    }

    public function count_content_values( $meta_key ) {

        global $wpdb;

        return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '{$meta_key}' AND meta_value > '0' " );
    }

    public function format_users( $counter ) {

        if ( $counter == 0 ) {
            $count_users = __( 'No users', 'ultimate-member' );
        } else {
            $count_users = ( $counter > 1 ) ? sprintf( __( '%d users', 'ultimate-member' ), $counter ) : __( 'One user', 'ultimate-member' );
        }

        return $count_users;
    }

    function toplevel_page_content_moderation() {

        $moderation_count = $this->format_users( $this->count_content_values( 'um_content_moderation' ) );
        $denied_count     = $this->format_users( $this->count_content_values( 'um_denial_profile_updates' ) );
        $rollback_count   = $this->format_users( $this->count_content_values( 'um_rollback_profile_updates' ) );
?>
        <div>
            <p><?php echo sprintf( __( '%s waiting for profile update approval.', 'ultimate-member' ), $moderation_count ); ?></p>
            <p><?php echo sprintf( __( '%s being denied their profile update.', 'ultimate-member' ), $denied_count ); ?></p>
            <p><?php echo sprintf( __( '%s with rollbacks.', 'ultimate-member' ), $rollback_count ); ?></p>
        </div>
<?php
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

    public function redirect_to_content_moderation( $user_id ) {

        UM()->user()->remove_cache( $user_id );
        um_fetch_user( $user_id );

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
                                __( 'Content Moderation', 'ultimate-member' ) . ' <span class="count">(' . $moderation_count . ')</span></a>';

        return $views;
    }

    public function manage_users_columns_content_moderation( $columns ) {

        if ( isset( $_REQUEST['content_moderation'] ) && sanitize_key( $_REQUEST['content_moderation'] ) === 'awaiting_profile_review' ) { 

            $columns['content_moderation'] = __( 'Update/Denial', 'ultimate-member' );
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

        foreach( $this->slug as $slug ) {

            $located = UM()->mail()->locate_template( $slug );
            if ( ! is_file( $located ) || filesize( $located ) == 0 ) {
                $located = wp_normalize_path( STYLESHEETPATH . '/ultimate-member/email/' . $slug . '.php' );
            }

            clearstatcache();
            if ( ! file_exists( $located ) || filesize( $located ) == 0 ) {

                wp_mkdir_p( dirname( $located ) );

                $email_source = file_get_contents( Content_Moderation_Path . $slug . '.php' );
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

            echo '<p><label>' . __( 'No access', 'ultimate-member' ) . '</label></p>';
        }

        echo '</div>';
    }

    public function create_profile_difference_message() {

        ob_start();

        echo '<p><label>' . sprintf( __( 'Profile Update submitted %s by User %s', 'ultimate-member' ), date( 'Y-m-d H:i:s', um_user( 'um_content_moderation' )), um_user( 'user_login' )) . '</label></p>';

        $um_denial_profile_updates = um_user( 'um_denial_profile_updates' );
        if ( ! empty( $um_denial_profile_updates ) && (int)$um_denial_profile_updates > 0 ) {
            echo '<p><label>' . sprintf( __( 'Profile Update Denial sent %s', 'ultimate-member' ), date( 'Y-m-d H:i:s', $um_denial_profile_updates )) . '</label></p>';
        }

        $um_rollback_profile_updates = um_user( 'um_rollback_profile_updates' );
        if ( ! empty( $um_rollback_profile_updates ) && (int)$um_rollback_profile_updates > 0 ) {
            echo '<p><label>' . sprintf( __( 'Last Profile Rollback of updates %s', 'ultimate-member' ), date( 'Y-m-d H:i:s', $um_rollback_profile_updates )) . '</label></p>';
        }

        $diff_updates = maybe_unserialize( um_user( 'um_diff_updates' ));

        $old = __( 'Old:', 'ultimate-member' );
        $new = __( 'New:', 'ultimate-member' );

        $output = array();

        foreach( $diff_updates as $meta_key => $meta_value ) {

            $meta_value = $this->meta_value_any_difference( $meta_value );

            if ( is_array( $meta_value )) {

                $field = UM()->builtin()->get_a_field( $meta_key );
                $title = isset( $field['title'] ) ? esc_attr( $field['title'] ) : __( 'No text', 'ultimate-member' );

                if ( in_array( $meta_key, $this->not_update_user_keys )) {
                    $title .= '<span title="' . sprintf( __( 'No rollback possible for the meta_key %s', 'ultimate-member' ), $meta_key ) . '" style="color: red;"> *</span>';
                }

                if ( empty( $meta_value['old'] ) || empty( $meta_value['new'] )) {

                    if ( empty( $meta_value['old'] )) {
                        $text_old = __( '(empty)', 'ultimate-member' );
                    } else {
                        $text_old = $meta_value['old'];
                    }

                    if ( empty( $meta_value['new'] )) {
                        $text_new = __( '(empty)', 'ultimate-member' );
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
                            $text_new = __( 'Format changes only', 'ultimate-member' );
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

            echo '<p><label>' . __( 'No updates found', 'ultimate-member' ) . '</label></p>';
            echo '<p><label>' . __( 'Image/File updates are not logged at the moment.', 'ultimate-member' ) . '</label></p>';
        }

        return ob_get_clean();
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

        if ( isset( $_REQUEST['content_moderation'] ) && sanitize_key( $_REQUEST['content_moderation'] ) === 'awaiting_profile_review' ) { 

            $actions['view_info_update'] = '<a href="javascript:void(0);" data-modal="UM_preview_profile_update" 
                                            data-modal-size="smaller" data-dynamic-content="content_moderation_review_update" 
                                            data-arg1="' . esc_attr( $user_id ) . '" data-arg2="profile_updates">' . 
                                            __( 'Moderation', 'ultimate-member' ) .  '</a>';
        }

        return $actions;
    }

    public function um_admin_bulk_user_actions_content_moderation( $actions ) {

        if ( isset( $_REQUEST['content_moderation'] ) && sanitize_key( $_REQUEST['content_moderation'] ) === 'awaiting_profile_review' ) {

            $output = ob_get_clean();
            $output = str_replace( __( 'UM Action', 'ultimate-member' ), __( 'UM Content Moderation', 'ultimate-member' ), $output );
            ob_start();
            echo $output;

            $actions = array();

            $actions['um_approve_membership']      = array( 'label' => __( 'Approve Profile Update', 'ultimate-member' ));				
            $actions['um_deny_profile_update']     = array( 'label' => __( 'Deny Profile Update', 'ultimate-member' ));
            $actions['um_rollback_profile_update'] = array( 'label' => __( 'Rollback Profile Update', 'ultimate-member' ));				
            $actions['um_deactivate']              = array( 'label' => __( 'Deactivate', 'ultimate-member' ));				
        }

        return $actions;
    }

    public function content_moderation_action() {

        if ( current_user_can( 'administrator' ) && UM()->options()->get( 'um_content_moderation_admin_disable' ) == 1 ) {
            return false;
        }

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

        $this->redirect_to_content_moderation( $user_id );
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

    public function reset_user_after_update( $user_id ) {

        update_user_meta( $user_id, 'um_content_moderation', 0 );
        update_user_meta( $user_id, 'um_diff_updates', null );

        if ( ! empty( um_user( 'um_denial_profile_updates' )) && (int)um_user( 'um_denial_profile_updates' ) > 0 ) {
            update_user_meta( $user_id, 'um_denial_profile_updates', 0 );
        }

        update_user_meta( $user_id, 'account_status', 'approved' );        

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

            $this->reset_user_after_update( $user_id );
        }
    }

    public function um_disable_email_notification_content_moderation( $false, $email, $template, $args ) {

        if ( $template == 'approved_email' && email_exists( $email )) {

            $user = get_user_by( 'email', $email );
            if ( isset( $user ) && is_a( $user, '\WP_User' ) ) {

                um_fetch_user( $user->ID );
                $um_content_moderation = um_user( 'um_content_moderation' );

                if ( isset( $um_content_moderation ) && (int)$um_content_moderation > 0 ) {

                    $this->send( $email, UM()->options()->get( 'um_content_moderation_accept_user_email' ) );

                    $this->reset_user_after_update( $user->ID );
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

                if ( isset( $this->update_field_types[$meta_key] )) {
                    $diff_updates[$meta_key]['type'] = $this->update_field_types[$meta_key];
                } else {
                    $diff_updates[$meta_key]['type'] = 'text';
                }
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

            um_fetch_user( $user_id );

            add_filter( 'um_template_tags_patterns_hook', array( $this, 'content_moderation_template_tags_patterns' ), 10, 1 );
            add_filter( 'um_template_tags_replaces_hook', array( $this, 'content_moderation_template_tags_replaces' ), 10, 1 );

            UM()->user()->set_status( 'awaiting_admin_review' );
            UM()->mail()->send( um_user( 'user_email' ), UM()->options()->get( 'um_content_moderation_pending_user_email' ) );
            UM()->mail()->send( get_bloginfo( 'admin_email' ), UM()->options()->get( 'um_content_moderation_admin_email' ), array( 'admin' => true ) );
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
            'id'            => 'um_content_moderation_admin_disable',
            'type'          => 'checkbox',
            'label'         => __( 'Content Moderation - Admin Disable', 'ultimate-member' ),
            'tooltip'       => __( 'Disable Admin updates of Users from Content Moderation.', 'ultimate-member' ),
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
            'id'            => 'um_content_moderation_rollback_user_email',
            'type'          => 'select',
            'label'         => __( 'Content Moderation - User Rollback Notification', 'ultimate-member' ),
            'tooltip'       => __( 'Select the User Rollback Notification Email template.', 'ultimate-member' ),
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

                                'content_moderation_rollback_user_email' => array(
                                        'key'            => 'content_moderation_rollback_user_email',
                                        'title'          => __( 'Content Moderation - User Rollback Notification', 'ultimate-member' ),
                                        'description'    => __( 'User Rollback Notification Email template', 'ultimate-member' ),
                                        'recipient'      => 'user',
                                        'default_active' => true,
                                        'subject'        => '[{site_name}] Profile rollback',
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
