<?php
/*
Plugin Name: WP Handbrake
Description: This is custom plugin which converts all video and audio file into readable format using handbrake.js.
Author: Kunal Malviya
*/

if(!defined('ABSPATH')) {
  die('You are not allowed to call this page directly.');
}

/**
 * Let's get started!
 */
class wp_handbrake {

	public function __construct() {
		// echo "kkkkkkkkkkkkkkkkkkk";
		// if( is_user_login() ) {
		// 	add_action("add_attachment", array($this, 'convertAttachment'), 10, 1);
		// } 
		// else {
			// add_filter( 'wp_generate_attachment_metadata', array($this, 'filter_wp_generate_attachment_metadata'), 10, 2 );
			// add_filter( 'wp_handle_upload', 'wpse_256351_upload', 10, 2 );
			// add_filter( 'wp_insert_attachment_data', 'filter_wp_generate_attachment_metadata', 10, 2 );
			add_filter( 'wp_update_attachment_metadata', array($this, 'filter_wp_generate_attachment_metadata'), 10, 2 );
		// }
		// add_action('wp_enqueue_scripts', array($this, 'my_load_scripts'));
	}

	public function convertAttachment( $attachmentId ) {
		global $wpdb;
		$nodeServerUrl = "http://13.210.133.31:3000";
		$postInfo 	   = get_post( $attachmentId );
		
		$fullsizepath  = get_attached_file( $postInfo->ID );
		$attach_data   = wp_generate_attachment_metadata( $attachmentId, $fullsizepath );

		$mimetype = null;
		if ( !empty($postInfo->post_mime_type) ) {
			$mimetype = $postInfo->post_mime_type;
		}

		/**
		* Calling API to convert uploaded file into desired format
		**/
		$nodeServerUrlWithParams = "$nodeServerUrl?fullsizepath=".$fullsizepath."&mimetype=".$mimetype;
		$response = wp_remote_get( $nodeServerUrlWithParams );		
		
		// If response from API(Node Server) has body
		if ( !is_wp_error($response) && $response['body'] && $response['body'] ) {
			$uploadResponse = json_decode($response['body'], true);		

			//If the status of body is true and has modified_file parameter
			if ( !empty($uploadResponse['status']) && !empty($uploadResponse['data']['modified_file']) ) {
				$modifiedFile = null;
				// Taking original post guid so that we just have to replace the file name not whole path we cannot do this by using absolute path because guid is url
				if ( !empty($postInfo->guid) ) {
					$newUrlArray = array();
					$pathArray = explode("/", $postInfo->guid);
					for ($i=0; $i < count($pathArray) -1; $i++) { 
						$newUrlArray[] = $pathArray[$i];
					}

					$newUrlArray[] = $uploadResponse['data']['modified_file'];
					$modifiedFile  = implode("/", $newUrlArray);
				}

				if ( !is_null($modifiedFile) ) {
					$updateStatus = $wpdb->update($wpdb->posts, ['guid' => $modifiedFile], ['ID' => $postInfo->ID]);
					update_attached_file( $attachmentId, $uploadResponse['data']['modified_filepath'] );
				}				
			}
			else {
// 				echo '<pre>';
// 				print_r($uploadResponse);
// 				echo '</pre>';
			}
		} else {			
// 				echo '<pre>';
// 				print_r($uploadResponse);
// 				echo '</pre>';
		}
	}

	public function filter_wp_generate_attachment_metadata( $metadata, $attachment_id ) {
		$this->convertAttachment( $attachment_id );
		return $metadata;
	}

	// public function my_load_scripts() {
	// 	wp_enqueue_script( 'handbreakserverjs', plugins_url( 'js/index.js', __FILE__ ), array('jquery'), time() );
	// }
}

/**
 * Initialize
 */
new wp_handbrake();
