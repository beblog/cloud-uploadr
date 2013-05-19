<?php

require 'aws-sdk/aws-autoloader.php';

use Aws\S3\S3Client;
use Aws\S3\Enum\CannedAcl;
use Aws\Common\Enum\Region;

define("S3_BUCKET_NAME", get_site_option('cloud_uploadr_s3_bucket'));
define("S3_ACCESS_KEY", get_site_option('cloud_uploadr_s3_access_key'));
define("S3_SECRET", get_site_option('cloud_uploadr_s3_secret'));

if(get_site_option('cloud_uploadr_s3_virtualhost') == 1)
    define("S3_URL", "http://" . S3_BUCKET_NAME);

switch(get_site_option('cloud_uploadr_s3_region'))
{
    case "eu-west-1":
        define("S3_REGION", Region::EU_WEST_1);
        if( !array_key_exists('S3_URL', $GLOBALS) )
            define("S3_URL", "http://s3-eu-west-1.amazonaws.com/" . S3_BUCKET_NAME);
        break;

    default:
        define("S3_REGION", Region::US_EAST_1);
}

//
$s3 = S3Client::factory(array(
        'key'    => S3_ACCESS_KEY,
        'secret' => S3_SECRET,
        'region' => S3_REGION
    ));

// Based on WP wp_unique_filename
function unique_filename($dir, $name, $ext) {
    global $s3;

    $filename = $name.$ext;
    $number = '';

    // Check if bucket exists
    if(!$s3->doesBucketExist(S3_BUCKET_NAME)) {
        throw new \Exception('Cannot find bucket');
    }

    // change '.ext' to lower case
    if ( $ext && strtolower($ext) != $ext ) {
        $ext2 = strtolower($ext);
        $filename2 = preg_replace( '|' . preg_quote($ext) . '$|', $ext2, $filename );

        // check for both lower and upper case extension or image sub-sizes may be overwritten
        while ( $s3->doesObjectExist(S3_BUCKET_NAME, $dir . "/".$filename) || $s3->doesObjectExist(S3_BUCKET_NAME, $dir . "/".$filename2) ) {
            $new_number = $number + 1;
            $filename = str_replace( "$number$ext", "$new_number$ext", $filename );
            $filename2 = str_replace( "$number$ext2", "$new_number$ext2", $filename2 );
            $number = $new_number;
        }
        return $filename2;
    }

    while ( $s3->doesObjectExist(S3_BUCKET_NAME,  $dir . "/".$filename ) ) {
        if ( '' == "$number$ext" )
            $filename = $filename . ++$number . $ext;
        else
            $filename = str_replace( "$number$ext", ++$number . $ext, $filename );
    }

    return $filename;
}

function s3_handle_file_upload($file) {
    global $s3;

    error_log("s3_handle_file_upload fired");
    error_log("file " . print_r($file, true));


    $wp_upload_path = get_upload_path();

    // You may define your own function and pass the name in $overrides['unique_filename_callback']
    $unique_filename_callback = "unique_filename";

    // Generate an unique filename
    error_log("check file exists starts");
    $filename = wp_unique_filename( $wp_upload_path, basename($file['file']), $unique_filename_callback );
    $s3Path = $wp_upload_path . "/" . $filename;
    error_log("check file exists ends");

    // Check if bucket exists
    if(!$s3->doesBucketExist(S3_BUCKET_NAME)) {
        throw new \Exception('Cannot find bucket');
    }

    // Upload an object by streaming the contents of a file
    // SourceFile should be absolute path to a file on disk
    try {
        $result = $s3->putObject(array(
                'Bucket'     => S3_BUCKET_NAME,
                'Key'        => $s3Path,
                'SourceFile' => $file['file'],
                'ContentType' => $file['type'],
                'ACL'        => CannedAcl::PUBLIC_READ
            ));
    } catch(\Exception $e) {

    }

    if($result) {
        $file['url'] = S3_URL . "/" . $s3Path;

        try {
            // TODO correct this
            //unlink($file['file']);
            error_log("Original NOT removed");
        } catch (\Exception $e) {
            error_log("Unable to remove file {$file['tmp_name']} after moving to Amazon S3.  This is likely okay and just a permissions issue, but the directory should be cleaned.");
        }
    }

    error_log("file ". print_r($file, true));
    return $file;
}

?>