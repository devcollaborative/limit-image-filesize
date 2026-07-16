<?php
/**
 * Plugin Name: Limit Image Filesize
 * Plugin URI: 
 * Description: Prevents uploading images larger than 5MB
 * Version: 1.0
 * Requires at least: 6.7.1
 * Requires PHP: 8
 * Author: DevCollaborative
 * Author URI: https://devcollaborative.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) or exit;

define( 'LIMIT_IMAGE_FILESIZE_VERSION', '1.0' );

//this keeps coming through as 0 some I'm not using it below but ideally it would be set here
//define( 'MAX_IMAGE_FILESIZE', 5 ); //start with 5 MB

/**
 * Run plugin update process on activation.
 */
function limit_image_filesize_activate() {
	limit_image_filesize_update_check();
}
register_activation_hook( __FILE__, 'limit_image_filesize_activate' );

/**
 * Checks the current plugins version, and runs the update process if versions don't match.
 */
function limit_image_filesize_update_check() {
	if ( LIMIT_IMAGE_FILESIZE_VERSION !== get_option( 'limit_image_filesize_version' ) ) {

		// Update with new plugin version.
		update_option( 'limit_image_filesize_version', LIMIT_IMAGE_FILESIZE_VERSION );

		// Flush permalinks so the new page template is recognized.
		flush_rewrite_rules();
	}
}
add_action( 'plugins_loaded', 'limit_image_filesize_update_check' );

/**
 * Detect and log (or act on) the file type of a file as it is being
 * uploaded to the WordPress media library.
 *
 * Hooked into 'wp_handle_upload_prefilter', which receives the $_FILES
 * entry for the upload before WordPress moves or validates it.
 *
 * @param array $file The $_FILES array entry for the uploading file. {
 *     @type string $name     Original filename from the client.
 *     @type string $type     MIME type reported by the browser (untrusted).
 *     @type string $tmp_name Absolute path to the temporary file on disk.
 *     @type int    $size     File size in bytes.
 *     @type int    $error    PHP upload error code (0 = no error).
 * }
 * @return array The (possibly modified) $file array. Must always be returned.
 * 
 * This code was generated with Claude, then modified by a human.
 */

function detect_filetype_on_upload( array $file ): array {
    // Skip files that already have a PHP upload error.
    if ( ! empty( $file['error'] ) ) {
        return $file;
    }

    $file_name = $file['name'];      // Original name, e.g. 'photo.jpg'
    $tmp_path  = $file['tmp_name']; // Temp path on server, e.g. '/tmp/php7Hx1Qz'

    // -------------------------------------------------------------------------
    // 1. Check the extension + MIME type via WordPress's allowed-types list.
    //    This is the same check WP itself runs later, so it respects any
    //    custom MIME types registered via the 'upload_mimes' filter.
    // -------------------------------------------------------------------------
    $filetype  = wp_check_filetype( $file_name );
    $extension = $filetype['ext'];   // e.g. 'jpg'   — false if not allowed
    $mime_type = $filetype['type'];  // e.g. 'image/jpeg' — false if not allowed

    // -------------------------------------------------------------------------
    // 2. Cross-check with the actual file content on disk (more trustworthy
    //    than the browser-supplied $file['type']).
    //    wp_check_filetype_and_ext() inspects file headers where possible.
    // -------------------------------------------------------------------------
    $real      = wp_check_filetype_and_ext( $tmp_path, $file_name );
    $real_ext  = $real['ext'];       // Extension inferred from file content
    $real_mime = $real['type'];      // MIME type inferred from file content

    // -------------------------------------------------------------------------
    // 3. Split MIME into general type and subtype for easy branching.
    // -------------------------------------------------------------------------
    $parts   = $real_mime ? explode( '/', $real_mime ) : [ '', '' ];
    $type    = $parts[0] ?? '';      // e.g. 'image', 'video', 'application'
    $subtype = $parts[1] ?? '';      // e.g. 'jpeg', 'pdf', 'mp4'

    // -- For image, check filesize  ------------------------------------
         if ( $type === 'image' ) {
            $max_size_bytes = 5 * 1024 * 1024; // 

            if ( $file['size'] > $max_size_bytes ) {
                $file['error'] = sprintf(
                    /* translators: 1: uploaded file size, 2: maximum allowed size */
                    __( 'Image rejected: file size is %1$s, which exceeds the %2$s limit.', 'lightship' ),
                    size_format( $file['size'] ),
                    size_format( $max_size_bytes )
                );
                return $file;
            }
        }

    // Always return the $file array — returning nothing breaks the upload.
    return $file;
}
add_filter( 'wp_handle_upload_prefilter', 'detect_filetype_on_upload' );

/**
 * Append or modify the maximum upload file size message in the Media Library.
 */
add_filter('gettext', function($translated_text, $text, $domain) {
    // Check if we are in the admin area and looking for the specific string
    if (is_admin() && 'Maximum upload file size: %s.' === $text) {

        // Append your custom text here
        $translated_text = sprintf('%s Image uploads restricted to 5 MB.', $text );
    }
    return $translated_text;
}, 20, 3);
