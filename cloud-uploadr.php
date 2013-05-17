<?php
/*
Plugin Name: Cloud Uploadr
Plugin URI: http://beblog.fr/plugins/cloud-uploadr
Description: Uploads attachements to Cloud Providers (AWS, ...)
Version: 0.1
Author: Remi Philippe
Author URI: http://aiku.fr/us/remi
License: GPL2
*/

require 'aws-sdk/aws-autoloader.php';

use Aws\Common\Aws;
use Aws\S3\Enum\CannedAcl;
use Aws\S3\Exception\S3Exception;

$options = get_option('cloud_uploadr_setting');

define("S3_BUCKET_NAME", $options['bucket']);
define("S3_PATH", $options['path']);
define("S3_ACCESS_KEY", $options['key']);
define("S3_SECRET", $options['secret']);

// Instantiate an S3 client
//$s3 = Aws::factory('/path/to/config.php')->get('s3');

// Upload a publicly accessible file. The file size, file type, and MD5 hash are automatically calculated by the SDK
//try {
//    $s3->putObject(array(
//            'Bucket' => 'my-bucket',
//            'Key'    => 'my-object',
//            'Body'   => fopen('/path/to/file', 'r'),
//            'ACL'    => CannedAcl::PUBLIC_READ
//        ));
//} catch (S3Exception $e) {
//    echo "There was an error uploading the file.\n";
//}

if( !function_exists('add_filter') ) {
    function add_filter() {}
}
