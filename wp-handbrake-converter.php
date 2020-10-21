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
		
// 			add_filter( 'wp_generate_attachment_metadata', array($this, 'filter_wp_generate_attachment_metadata'), 9999, 2 );

			// add_filter( 'wp_handle_upload', 'wpse_256351_upload', 10, 2 );
		
// 			add_filter( 'wp_insert_attachment_data', array($this, 'filter_wp_generate_attachment_metadata'), 10, 2 );

			add_filter( 'wp_update_attachment_metadata', array($this, 'filter_wp_generate_attachment_metadata'), 900, 2 );
			add_filter( 'wp_insert_post_data' , array($this, 'replace_extensions') , 99, 2 );
		// }
		// add_action('wp_enqueue_scripts', array($this, 'my_load_scripts'));

	}

	public function convertAttachment( $attachmentId, $initialMetaData ) {
		global $wpdb;
		$nodeServerUrl = site_url().":3000";
		$postInfo 	   = get_post( $attachmentId );		
		$fullsizepath  = get_attached_file( $postInfo->ID );		

		$mimetype = null;
		if ( !empty($postInfo->post_mime_type) ) {
			$mimetype = $postInfo->post_mime_type;
		}

		/**
		* Calling API to convert uploaded file into desired format
		* Sending two parameter one is server file path and another is uploaded file mimetype
		**/
		$nodeServerUrlWithParams = "$nodeServerUrl?fullsizepath=".$fullsizepath."&mimetype=".$mimetype;
		$response = wp_remote_get( $nodeServerUrlWithParams );		
		
		// If response from API(Node Server) has body
		if ( !is_wp_error($response) && $response['body'] && $response['body'] ) {
			$uploadResponse = json_decode($response['body'], true);		

			//If the status of body is true and has modified_file parameter
			if ( !empty($uploadResponse['status']) && !empty($uploadResponse['data']['modified_file']) ) {
				$modifiedFile = null;

				// Taking original post guid so that we just have to replace the file name not whole path we cannot do this by using absolute path because guid is url and it contains url in MM/DD format
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
					
					/**
					* STEP1: Updating the post
					**/
					$updateStatus = $wpdb->update(
						$wpdb->posts, 
						['guid' => $modifiedFile], 
						['ID' => $postInfo->ID]
					);

					/**
					* STEP 2: Used to update the file path of the attachment, which uses post meta name _wp_attached_file to store the path of the attachment.
					*/
					update_attached_file( $attachmentId, $uploadResponse['data']['modified_filepath'] );

					/**
					* STEP 3: Getting the mimetype of uploded file on server and getting its meta data so that we can save it in file metadata in db
					**/
					if ( !empty($uploadResponse['mimetype']) ) {
						$newAttachedfile = get_attached_file( $attachmentId );
						
						// File is required to get meta data
						require_once( ABSPATH . 'wp-admin/includes/media.php' );

						$modifiedFileMetaData = null;
						if ( $uploadResponse['mimetype'] == 'video/mp4' ) {
							$modifiedFileMetaData = wp_read_video_metadata( $newAttachedfile );
						}
						else {
							$modifiedFileMetaData = wp_read_audio_metadata( $newAttachedfile );
						}						
					}

					/**
					* STEP 4: If new meta data is not null then update
					**/
					if ( !is_null($modifiedFileMetaData) ) {

						// Updating post mime type
						$updateStatus = $wpdb->update(
							$wpdb->posts, 
							['post_mime_type' => $modifiedFileMetaData['mime_type']], 
							['ID' => $postInfo->ID]
						);
						
						// echo '<pre>';
						// print_r($attachmentId);
						// print_r($modifiedFileMetaData);
						// echo '</pre>';
						// wp_update_attachment_metadata( $attachmentId,  $modifiedFileMetaData );
						// die;
						return $modifiedFileMetaData;
					} 
					else {
						return $initialMetaData;
					}
				}				
			}
			else {
			// echo '<pre>';
			// print_r($uploadResponse);
			// echo '</pre>';
				return $initialMetaData;
			}
		} else {			
			// echo '<pre>';
			// print_r($uploadResponse);
			// echo '</pre>';
			return $initialMetaData;
		}
	}

	public function filter_wp_generate_attachment_metadata( $metadata, $attachment_id ) {
		$this->convertAttachment( $attachment_id, $metadata );
		return $metadata;
	}
	
	public function replace_extensions( $data , $postarr ) {
		$istr = $data['post_content'];

		// Replace all audio formats to mp4
		$newAudioPhrase = preg_replace(
			array('~\b3gp\b~', '~\bogg\b~', '~\boga\b~', '~\bmogg\b~', '~\bwav\b~', '~\bwebm\b~'), 
			"mp3", 
			$istr
		);

		// Replace all video formats to mp4		
		$newPhrase = preg_replace(
			array('~\bmp4\b~', '~\bwebm\b~', '~\bmkv\b~', '~\bflv\b~', '~\bvob\b~', '~\bavi\b~', '~\bmov\b~', '~\bwmv\b~', '~\bgifv\b~'), 
			"mp4", 
			$newAudioPhrase
		);

		$data['post_content'] = $newPhrase;
		return $data;
	}
	
	// public function my_load_scripts() {
	// 	wp_enqueue_script( 'handbreakserverjs', plugins_url( 'js/index.js', __FILE__ ), array('jquery'), time() );
	// }
}

/**
 * Initialize
 */
new wp_handbrake();
