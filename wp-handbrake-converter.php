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
		add_action("add_attachment", array($this, 'action_add_attachment'), 10, 1);
	}

	public function action_add_attachment( $attachmentId ) {
		global $wpdb;
		$postInfo 	   = get_post( $attachmentId );
		$fullsizepath  = get_attached_file( $postInfo->ID );
		$attach_data   = wp_generate_attachment_metadata( $attachmentId, $fullsizepath );

		/**
		* Calling API to convert uploaded file into desired format
		**/
		$response = wp_remote_get( "http://localhost:3000?fullsizepath=".$fullsizepath );

		// If response from API(Node Server) has body
		if ( $response['body'] && $response['body'] ) {
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

					// echo '<pre>';
					// echo $postInfo->guid;
					// print_r($uploadResponse);
					// echo $modifiedFile;
					// echo '</pre>';
				}

				if ( !is_null($modifiedFile) ) {
					$updateStatus = $wpdb->update($wpdb->posts, ['guid' => $modifiedFile], ['ID' => $postInfo->ID]);
					update_attached_file( $attachmentId, $uploadResponse['data']['modified_filepath'] );
					// echo '<pre>';
					// print_r($updateStatus);
					// print_r(get_post( $postInfo->ID ));
					// echo '</pre>';
					// die;
				}				
			}
		}
	}
}

/**
 * Initialize
 */
new wp_handbrake();
