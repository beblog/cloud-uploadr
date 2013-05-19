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

if( !function_exists('add_filter') ) {
    function add_filter() {}
}

if( !array_key_exists('WP_UPLOAD_BASEDIR', $GLOBALS) ) {
    // Get system temp directory
    $ini_val = ini_get('upload_tmp_dir');
    $tempdir = $ini_val ? $ini_val : sys_get_temp_dir();
    if (substr($tempdir, -1) != '/') $tempdir .= '/';

    error_log("basedir not set");
    $basedir = $tempdir . 'cloud_uploadr'. time() . rand(0, 999999);
    while (is_dir($basedir))
        $basedir = $tempdir . 'cloud_uploadr' . time() . rand(0, 999999);

    define('WP_UPLOAD_BASEDIR', $basedir);
}



// TODO site options for single WP install

switch(get_site_option('cloud_uploadr_provider')) {
    case "aws-s3":
        require('providers/aws-s3.php');
        define("CLOUD_UPLOADR_PREFIX", 's3');
        break;
}



function get_upload_path($withSubdir = true, $time = null) {
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
    if ( get_option( 'uploads_use_yearmonth_folders' ) &&  $withSubdir == true) {
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
    // Register settings
    add_action( 'admin_init', 'register_cloud_uploadr' );
}

function register_cloud_uploadr() {

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
        'cloud_uploadr_s3_region',
        'cloud_uploadr_s3_virtualhost'
    );

    // Read in existing option value from database
    $fields = array();
    foreach($field_names as $name)
        $fields[$name] = get_site_option( $name );

    $hidden_field_name = 'cloud_uploadr_submit_hidden';


    // See if the user has posted us some information
    // If they did, this hidden field will be set to 'Y'
    if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {

        // Save the posted value in the database
        foreach($field_names as $name)
        {
            update_site_option( $name, $_POST[ $name ] );
            $fields[$name] = $_POST[ $name ];
        }

        // Put an settings updated message on the screen
        ?>
        <div class="updated"><p><strong><?php _e('settings saved.', 'cloud_uploadr' ); ?></strong></p></div>
        <?php

    }
    // Now display the settings editing screen
    echo '<div class="wrap">';
    // header
    echo "<h2>" . __( 'Menu Test Plugin Settings', 'cloud_uploadr' ) . "</h2>";

    // settings form
    ?>

    <form name="cloud_uploadr_options" method="post" action="">
        <input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

        <p><?php _e("Provider:", 'cloud_uploadr_provider' ); ?>
            <select name="cloud_uploadr_provider">
                <option></option>
                <option <? if($fields['cloud_uploadr_provider'] == 'aws-s3') echo 'selected'; ?> value="aws-s3">AWS S3</option>
            </select>
        </p><hr />

        <h3>AWS S3 Configuration</h3>

        <p><?php _e("Bucket:", 'cloud_uploadr_s3_bucket' ); ?>
            <input type="text" name="cloud_uploadr_s3_bucket" value="<?php echo $fields['cloud_uploadr_s3_bucket']; ?>" size="20"><br />

            <?php _e("Virtual Host:", 'cloud_uploadr_s3_virtualhost' ); ?>
            <input type="hidden" name="cloud_uploadr_s3_virtualhost" value="0" />
            <input <?php if($fields['cloud_uploadr_s3_virtualhost']) echo "checked"; ?> type="checkbox" name="cloud_uploadr_s3_virtualhost" value="1"><br />

            <?php _e("Access Key:", 'cloud_uploadr_s3_access_key' ); ?>
            <input type="text" name="cloud_uploadr_s3_access_key" value="<?php echo $fields['cloud_uploadr_s3_access_key']; ?>" size="20"><br/>

            <?php _e("Secret:", 'cloud_uploadr_s3_secret' ); ?>
            <input type="text" name="cloud_uploadr_s3_secret" value="<?php echo $fields['cloud_uploadr_s3_secret']; ?>" size="20"><br />

            <?php _e("Region:", 'cloud_uploadr_s3_region' ); ?>
            <select name="cloud_uploadr_s3_region">
                <option></option>
                <option <? if($fields['cloud_uploadr_s3_region'] == 'eu-west-1') echo 'selected'; ?> value="eu-west-1">eu-west-1</option>
                <option <? if($fields['cloud_uploadr_s3_region'] == 'us-west-1') echo 'selected'; ?> value="us-west-1">us-west-1</option>
            </select>
        </p><hr />


        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
        </p>

    </form>
    </div>

<?php
}

// Will be handled at the provider level
add_filter( 'wp_handle_upload', CLOUD_UPLOADR_PREFIX.'_handle_file_upload' );


add_filter('wp_generate_attachment_metadata','cloud_uploadr_generate_attachment_metadata');
add_filter('wp_update_attachment_metadata','cloud_uploadr_generate_attachment_metadata');
function cloud_uploadr_generate_attachment_metadata($data) {
    error_log("cloud_uploadr_generate_attachment_metadata fired");
    error_log("metadata" . print_r($data, true));

    $upload_dir = wp_upload_dir();
    $filepath = $upload_dir['basedir'] . '/' . preg_replace('/^(.+)\/[^\/]+$/', '\\1', $data['file']);

    foreach ($data['sizes'] as $size => $sizedata) {
        $file['file'] = $filepath . '/' . $sizedata['file'];
        $file['url'] = $upload_dir['baseurl'] . substr($file['file'], strlen($upload_dir['basedir']));

        $file['type'] = 'application/octet-stream';
        switch(substr($file['file'], -4)) {
            case '.gif':
                $file['type'] = 'image/gif';
                break;
            case '.jpg':
                $file['type'] = 'image/jpeg';
                break;
            case '.png':
                $file['type'] = 'image/png';
                break;
        }

        s3_handle_file_upload($file);
    }

    return $data;


}

//add_filter('wp_get_attachment_url', 'cloud_uploadr_get_attachment_url');
function cloud_uploadr_get_attachment_url($url) {
    error_log("cloud_uploadr_get_attachment_url fired");
    $upload_dir = get_upload_path();



    $http = site_url(FALSE, 'http');
    $https = site_url(FALSE, 'https');
    return ( $_SERVER['HTTPS'] == 'on' ) ? str_replace($http, $https, $url) : $url;
}

add_filter('upload_dir', 'cloud_uploadr_upload_dir');
function cloud_uploadr_upload_dir($data) {
    error_log("cloud_uploadr_upload_dir fired");

    $data['basedir'] = WP_UPLOAD_BASEDIR;
    $data['baseurl'] = S3_URL . "/" . get_upload_path(false);
    $data['url'] = S3_URL . "/" . get_upload_path();
    $data['path'] = WP_UPLOAD_BASEDIR . $data['subdir'];

    error_log("data " . print_r($data, true));

    return $data;

    /*
    [19-May-2013 08:20:27] upload_dir
    Array (
        [path] => /var/www/vhosts/beblog.fr/httpdocs/wp-content/uploads/sites/2/2013/05
        [url] => http://remi.beblog.fr/wp-content/uploads/sites/2/2013/05
        [subdir] => /2013/05
        [basedir] => /var/www/vhosts/beblog.fr/httpdocs/wp-content/uploads/sites/2
        [baseurl] => http://remi.beblog.fr/wp-content/uploads/sites/2
        [error] =>
    )
    */
}

//add_filter('shutdown', 'cloud_uploadr_shutdown');
function cloud_uploadr_shutdown() {

}