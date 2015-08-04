<?php

/*
Plugin Name: Cosmo Themes for Lightroom
Description: Cosmo Themes Extension for Lightroom through the WP/LR Sync plugin.
Version: 0.1.0
Author: Jordy Meow
Author URI: http://www.meow.fr
*/

class WPLR_Extension_CosmoThemes {

  public function __construct() {

    // Init
    add_filter( 'wplr_extensions', array( $this, 'extensions' ), 10, 1 );

    // Reset
    add_action( 'wplr_reset', array( $this, 'reset' ), 10, 0 );

    // Create / Update
    add_action( 'wplr_create_folder', array( $this, 'create_folder' ), 10, 3 );
    add_action( 'wplr_update_folder', array( $this, 'update_folder' ), 10, 2 );
    add_action( 'wplr_create_collection', array( $this, 'create_collection' ), 10, 3 );
    add_action( 'wplr_update_collection', array( $this, 'update_collection' ), 10, 2 );

    // Move
    add_action( "wplr_move_folder", array( $this, 'move_collection' ), 10, 3 );
    add_action( "wplr_move_collection", array( $this, 'move_collection' ), 10, 3 );

    // Media
    add_action( "wplr_add_media_to_collection", array( $this, 'add_media_to_collection' ), 10, 2 );
    add_action( "wplr_remove_media_from_collection", array( $this, 'remove_media_from_collection' ), 10, 2 );
    add_action( "wplr_remove_media", array( $this, 'remove_media' ), 10, 1 );
    add_action( "wplr_remove_collection", array( $this, 'remove_collection' ), 10, 1 );
  }

  function extensions( $extensions ) {
    array_push( $extensions, 'Cosmo Themes' );
    return $extensions;
  }

  function reset() {
    global $wpdb;
  	$wpdb->query( "DELETE p FROM $wpdb->posts p INNER JOIN $wpdb->postmeta m ON p.ID = m.post_id WHERE m.meta_key = \"wplr_to_cosmo\"" );
  	$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key = \"wplr_to_cosmo\"" );
  }

  // Get the Post ID for the LR Collection $lrid (from meta)
  function get_id_for_collection( $lrid ) {
    global $wpdb;
    $id = $wpdb->get_var( $wpdb->prepare( "SELECT p.post_id FROM $wpdb->postmeta p WHERE p.meta_key = %s AND p.meta_value = %d", 'wplr_to_cosmo', $lrid ) );
    return $id;
  }

  function create_collection( $collectionId, $inFolderId, $collection, $isFolder = false ) {

    // If exists already, avoid re-creating
    $hasMeta = $this->get_id_for_collection( $collectionId );
    if ( !empty( $hasMeta ) )
      return;

    // Get the ID of the parent collection (if any) - check the end of this function for more explanation.
    $post_parent = null;
    if ( !empty( $inFolderId ) )
      $post_parent = $this->get_id_for_collection( $inFolderId );

    // Create the collection.
    $post = array(
      'post_title'    => wp_strip_all_tags( $collection['name'] ),
      'post_status'   => 'publish',
      'post_type'     => 'gallery',
      'post_parent'   => $post_parent
    );
    $id = wp_insert_post( $post );
    update_post_meta( $id, 'wplr_to_cosmo', $collectionId, true );
    update_post_meta( $id, '_post_image_gallery', '' );
  }

  function create_folder( $folderId, $inFolderId, $folder ) {
    // Well, we can say that a folder is a collection (we could have use a taxonomy for that too)
    // Let's keep it simple and re-use the create_collection with an additional parameter to avoid having content.
    $this->create_collection( $folderId, $inFolderId, $folder, true );
  }

  // Updated the collection with new information.
  // Currently, that would be only its name.
  function update_collection( $collectionId, $collection ) {
    $id = $this->get_id_for_collection( $collectionId );
    $post = array( 'ID' => $id, 'post_title' => wp_strip_all_tags( $collection['name'] ) );
    wp_update_post( $post );
  }

  // Updated the folder with new information.
  // Currently, that would be only its name.
  function update_folder( $folderId, $folder ) {
    $this->update_collection( $folderId, $folder );
  }

  // Moved the collection under another folder.
  // If the folder is empty, then it is the root.
  function move_collection( $collectionId, $folderId, $previousFolderId ) {
    $post_parent = null;
    if ( !empty( $folderId ) )
      $post_parent = $this->get_id_for_collection( $folderId );
    $id = $this->get_id_for_collection( $collectionId );
    $post = array( 'ID' => $id, 'post_parent' => $post_parent );
    wp_update_post( $post );
  }

  // Added meta to a collection.
  // The $mediaId is actually the WordPress Post/Attachment ID.
  function add_media_to_collection( $mediaId, $collectionId, $isRemove = false ) {
    $id = $this->get_id_for_collection( $collectionId );
    $str = get_post_meta( $id, '_post_image_gallery', TRUE );
    $ids = !empty( $str ) ? explode( ',', $str ) : array();
    $index = array_search( $mediaId, $ids, false );
    if ( $isRemove ) {
      if ( $index !== FALSE )
        unset( $ids[$index] );
    }
    else {
      // If mediaId already there then exit.
      if ( $index !== FALSE )
        return;
      array_push( $ids, $mediaId );
    }
    // Update _post_image_gallery
    update_post_meta( $id, '_post_image_gallery', implode( ',', $ids ) );

    // Add a default featured image if none
    add_post_meta( $id, '_thumbnail_id', $mediaId, true );
  }

  // Remove media from the collection.
  function remove_media_from_collection( $mediaId, $collectionId ) {
    $this->add_media_to_collection( $mediaId, $collectionId, true );
  }

  // The media was physically deleted.
  function remove_media( $mediaId ) {
    // No need to do anything.
  }

  // The collection was deleted.
  function remove_collection( $collectionId ) {
    $id = $this->get_id_for_collection( $collectionId );
    wp_delete_post( $id, true );
    delete_post_meta( $id, 'wplr_to_cosmo' );
  }
}

new WPLR_Extension_CosmoThemes;

?>
