<?php
/*
Plugin Name: Cloud Uploadr
Plugin URI: http://beblog.fr/plugins/cloud-uploadr
Description: Uploads attachements to Cloud Providers (AWS, ...)
Version: 0.1
Author: Remi Philippe
Author URI: http://aiku.fr/team/remi
License: GPL2
*/

/*
 * Options
 * - Provider
 * - s3Bucket
 * - s3AccessKey
 * - s3Secret
 * - s3URL
 * - s3Region
 */
$options = get_option('cloud_uploadr_setting');

switch($options['cloud_uploadr_provider']) {
    case "aws-s3":
        require('providers/aws-s3.php');
        $prefix = "s3";
        break;
}

if( !function_exists('add_filter') ) {
    function add_filter() {}
}

function get_upload_path($time = null) {
    $upload_path = trim( get_option( 'upload_path' ) );

    if ( empty( $upload_path ) || 'wp-content/uploads' == $upload_path ) {
        $dir = 'uploads';
    } else {
        $dir = $upload_path;
    }

    if ( is_multisite() && ! ( is_main_site() && defined( 'MULTISITE' ) ) ) {
        if ( defined( 'MULTISITE' ) )
            $ms_dir = '/sites/' . get_current_blog_id();
        else
            $ms_dir = '/' . get_current_blog_id();

        $dir .= $ms_dir;
    }

    $subdir = '';
    if ( get_option( 'uploads_use_yearmonth_folders' ) ) {
        // Generate the yearly and monthly dirs
        if ( !$time )
            $time = current_time( 'mysql' );
        $y = substr( $time, 0, 4 );
        $m = substr( $time, 5, 2 );
        $subdir = "/$y/$m";
    }

    $dir .= $subdir;

    return $dir;
}

// Admin Menu
if ( is_multisite() ) {
    add_action( 'network_admin_menu', 'cloud_uploadr_menu' );
} else {
    add_action( 'admin_menu', 'cloud_uploadr_menu' );
}

function cloud_uploadr_menu() {
    add_submenu_page( 'settings.php', 'Cloud Uploadr Options', 'Cloud Uploadr', 'manage_options', 'cloud-uploadr', 'cloud_uploadr_options' );
}

function cloud_uploadr_options() {
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }

    $field_names = array(
        'cloud_uploadr_provider',
        'cloud_uploadr_s3_bucket',
        'cloud_uploadr_s3_access_key',
        'cloud_uploadr_s3_secret',
        'cloud_uploadr_s3_url',
        'cloud_uploadr_s3_region'
    );

    // Read in existing option value from database
    $fields = array();
    foreach($field_names as $name)
        $fields[$name] = get_option( $name );

    $hidden_field_name = 'cloud_uploadr_submit_hidden';


    // See if the user has posted us some information
    // If they did, this hidden field will be set to 'Y'
    if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {

        // Save the posted value in the database
        foreach($fields as $key => $value)
            update_option( $key, $value );

        // Put an settings updated message on the screen
        ?>
        <div class="updated"><p><strong><?php _e('settings saved.', 'menu-test' ); ?></strong></p></div>
        <?php

    }
    // Now display the settings editing screen
    echo '<div class="wrap">';
    // header
    echo "<h2>" . __( 'Menu Test Plugin Settings', 'menu-test' ) . "</h2>";

    // settings form
    ?>

    <form name="form1" method="post" action="">
        <input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

        <p><?php _e("Favorite Color:", 'menu-test' ); ?>
            <input type="text" name="<?php echo $data_field_name; ?>" value="<?php echo $opt_val; ?>" size="20">
        </p><hr />

        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
        </p>

    </form>
    </div>

<?php
}

// Will be handled at the provider level
add_filter( 'wp_handle_upload', $prefix.'_handle_file_upload' );


add_filter('wp_generate_attachment_metadata','cloud_uploadr_generate_attachment_metadata');
add_filter('wp_update_attachment_metadata','cloud_uploadr_generate_attachment_metadata');
function cloud_uploadr_generate_attachment_metadata($metadata) {

}

add_filter('wp_get_attachment_url', 'cloud_uploadr_get_attachment_url');
function cloud_uploadr_get_attachment_url($url) {
    $http = site_url(FALSE, 'http');
    $https = site_url(FALSE, 'https');
    return ( $_SERVER['HTTPS'] == 'on' ) ? str_replace($http, $https, $url) : $url;
}