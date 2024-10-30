<?php
/**
 * Plugin Name: LH Cache Remote Images
 * Plugin URI: https://lhero.org/plugins/lh-cache-remote-images/
 * Description: This plugin caches all remote images in post_content asynchronously
 * Version: 1.04
 * Author: Peter Shaw
 * Author URI: https://shawfactor.com

License:
Released under the GPL license
http://www.gnu.org/copyleft/gpl.html

Copyright 2017  Peter Shaw  (email : pete@localhero.biz)

*/

class LH_cache_remote_images_plugin {

var $queued_images_field_name = "lh_cache_remote_images-queued_image";
var $namespace = "lh_cache_remote_images";

private function rel2abs( $rel, $base ){
    /* return if already absolute URL */
    if( parse_url($rel, PHP_URL_SCHEME) != '' )
        return( $rel );

    /* queries and anchors */
    if( $rel[0]=='#' || $rel[0]=='?' )
        return( $base.$rel );

    /* parse base URL and convert to local variables:
       $scheme, $host, $path */
    extract( parse_url($base) );

    /* remove non-directory element from path */
    $path = preg_replace( '#/[^/]*$#', '', $path );

    /* destroy path if relative url points to root */
    if( $rel[0] == '/' )
        $path = '';

    /* dirty absolute URL */
    $abs = '';

    /* do we have a user in our URL? */
    if( isset($user) )
    {
        $abs.= $user;

        /* password too? */
        if( isset($pass) )
            $abs.= ':'.$pass;

        $abs.= '@';
    }

    $abs.= $host;

    /* did somebody sneak in a port? */
    if( isset($port) )
        $abs.= ':'.$port;

    $abs.=$path.'/'.$rel;

    /* replace '//' or '/./' or '/foo/../' with '/' */
    $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
    for( $n=1; $n>0; $abs=preg_replace( $re, '/', $abs, -1, $n ) ) {}

    /* absolute URL is ready! */
    return( $scheme.'://'.$abs );
}



 /**
     * Checks the URL is on the local domain
     * @param type $url
     */

private  function is_local_image($url){

        $is_local = strpos($url, home_url());
        
        return (bool)($is_local !== false);
        
        if ($is_local !== false) {
            return true;
        }
        

    }

 /**
     * Checks the URL is absolute
     * @param type $url
     */
    
private function is_absolute_url($url){
        $parse = parse_url($url);
        if (array_key_exists("host", $parse)) return true;
        return false;
    }

private function delete_queued_url($meta_id, $old_url){

global $wpdb;

$sql = "DELETE FROM ".$wpdb->postmeta." WHERE meta_id = '".$meta_id."' and meta_key = '_lh_cache_remote_images-queued_image' and meta_value = '".$old_url."' LIMIT 1";


$results = $wpdb ->get_results($sql);


}




private function handle_upload($upload_url, $postid){


$desc = 'Download of '.$upload_url;

if (!class_exists('LH_copy_from_url_class')) {

include_once("includes/lh-copy-from-url-class.php");

}

$attachment_id = LH_copy_from_url_class::save_external_file($upload_url, $postid, $desc, TRUE);

return $attachment_id;	

} 

public function content_save_pre( $content) {


global $post;
global $wpdb;

$types = get_post_types( array('public'   => true ), 'names' );

if (isset($post) and in_array($post->post_type, $types)){
       
       
$dom = new DOMDocument();

// load the HTML into the DomDocument object (this would be your source HTML)
$dom->loadHTML("<html><head><meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\"></head><body>".stripslashes($content)."</body></html>");


//Find all images
$images = $dom->getElementsByTagName('img');

//Iterate though images
foreach ($images AS $image) {

if ($src = $image->getAttribute('src')){

if (!$this->is_absolute_url($src)){

$newurl = $this->rel2abs( $src, home_url() );
$image->setAttribute('src', $newurl);

unset($newurl);


} elseif (!$this->is_local_image($src)){

$image->removeAttribute('srcset');


foreach($image->attributes as $att){

if (substr( $att->nodeName, 0, 4 ) === "data"){

$image->removeAttribute($att->nodeName);



}


}

if (isset($post->ID)){

add_post_meta($post->ID, '_lh_cache_remote_images-queued_image', $src, false);


}




}



}

}




$body = $dom->documentElement->lastChild;

//very ugly regex, should do this via dom


preg_match("/<body[^>]*>(.*?)<\/body>/is", $dom->saveHTML($body), $matches);

return $matches[1];

} else {
    
 return $content;   
}



}


private function update_post_content($postid, $content, $new_url, $old_url){

$dom = new DOMDocument();

// load the HTML into the DomDocument object (this would be your source HTML)
$dom->loadHTML("<!DOCTYPE html><html><head><meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\"></head><body>".$content."</body></html>");


//Find all images
$images = $dom->getElementsByTagName('img');

$update = false;

//Iterate though images
foreach ($images AS $image) {


if ($old_url = $image->getAttribute('src')){


//echo "found";

$image->setAttribute('src', $new_url);


$update = true;



}

}


$body = $dom->documentElement->lastChild;

//very ugly regex, should do this via dom


preg_match("/<body[^>]*>(.*?)<\/body>/is", $dom->saveHTML($body), $matches);

$content = $matches[1];

if ($update){

$my_post = array(
      'ID'           => $postid,
      'post_content' => $content,
  );

// Update the post into the database
  wp_update_post( $my_post );


}


return $update;

}

public function run_processes(){

global $wpdb;

$sql = "SELECT 	posts.ID, posts.post_content, meta.meta_id, meta.meta_value FROM ".$wpdb->posts." posts, ".$wpdb->postmeta." meta WHERE posts.ID = meta.post_id and posts.post_status = 'publish' and meta_key = '_lh_cache_remote_images-queued_image' LIMIT 1";

$results = $wpdb ->get_results($sql);

if (isset($results[0]->ID)){

$postid = $results[0]->ID;

//echo "the post id is ".$postid;

$content = stripslashes($results[0]->post_content);

//echo "the content is ".$content;

$meta_id = $results[0]->meta_id;

//echo "the meta id is ".$meta_id;

$old_url = $results[0]->meta_value;

//echo "the old url is ".$old_url;

$return = $this->handle_upload($old_url, $postid);


if (is_numeric($return)){

$new_url = wp_get_attachment_url($return);

//echo "the new url is ".$new_url;


$this->update_post_content($postid, $content, $new_url, $old_url);

$this->delete_queued_url($meta_id, $old_url);



}

}


}


public function on_activate($network_wide) {


    if ( is_multisite() && $network_wide ) { 

        global $wpdb;

        foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
            switch_to_blog($blog_id);

wp_clear_scheduled_hook( 'lh_cache_remote_images_process' );
wp_schedule_event( time(), 'hourly', 'lh_cache_remote_images_process' );

            restore_current_blog();
        } 

    } else {


wp_clear_scheduled_hook( 'lh_cache_remote_images_process' );
wp_schedule_event( time(), 'hourly', 'lh_cache_remote_images_process' );


}


}

public function deactivate_hook() {

wp_clear_scheduled_hook( 'lh_cache_remote_images_process' ); 


}



public function __construct() {


//find remote images in post_content and save them to post_meta
add_action( 'content_save_pre', array($this,"content_save_pre"));

//Hook up a function to the process
add_action( 'lh_cache_remote_images_process', array($this,"run_processes"));


}

}


$lh_cache_remote_images_instance = new LH_cache_remote_images_plugin();
register_activation_hook(__FILE__, array($lh_cache_remote_images_instance, 'on_activate'));
register_deactivation_hook( __FILE__, array($lh_cache_remote_images_instance,'deactivate_hook') );

?>