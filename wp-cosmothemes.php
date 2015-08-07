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

    // Create / Update
    add_action( 'wplr_create_folder', array( $this, 'create_folder' ), 10, 3 );
    add_action( 'wplr_update_folder', array( $this, 'update_folder' ), 10, 2 );
    add_action( 'wplr_create_collection', array( $this, 'create_collection' ), 10, 3 );
    add_action( 'wplr_update_collection', array( $this, 'update_collection' ), 10, 2 );
    add_action( "wplr_move_folder", array( $this, 'move_folder' ), 10, 3 );
    add_action( "wplr_move_collection", array( $this, 'move_collection' ), 10, 3 );

    // Delete
    add_action( "wplr_remove_collection", array( $this, 'remove_collection' ), 10, 1 );
    add_action( "wplr_remove_folder", array( $this, 'remove_folder' ), 10, 1 );

    // Media
    add_action( "wplr_add_media_to_collection", array( $this, 'add_media_to_collection' ), 10, 2 );
    add_action( "wplr_remove_media_from_collection", array( $this, 'remove_media_from_collection' ), 10, 2 );

    // Extra
    //add_action( 'wplr_reset', array( $this, 'reset' ), 10, 0 );
    //add_action( "wplr_clean", array( $this, 'clean' ), 10, 1 );
    //add_action( "wplr_remove_media", array( $this, 'remove_media' ), 10, 1 );
  }

  function extensions( $extensions ) {
    array_push( $extensions, 'Cosmo Themes' );
    return $extensions;
  }

  function create_collection( $collectionId, $inFolderId, $collection, $isFolder = false ) {
    global $wplr;

    // If exists already, avoid re-creating
    $hasMeta = $wplr->get_meta( "cosmothemes_gallery_id", $collectionId );
    if ( !empty( $hasMeta ) )
      return;

    // Get the ID of the parent collection (if any) - check the end of this function for more explanation.
    $post_parent = null;
    if ( !empty( $inFolderId ) )
      $post_parent = $wplr->get_meta( "cosmothemes_term_id", $inFolderId );

    // Create the collection.
    $post = array(
      'post_title'    => wp_strip_all_tags( $collection['name'] ),
      'post_status'   => 'publish',
      'post_type'     => 'gallery',
      'post_parent'   => $post_parent
    );
    $id = wp_insert_post( $post );

    // Add a meta to retrieve easily the LR ID for that collection from a WP Post ID
    $wplr->set_meta( 'cosmothemes_gallery_id', $collectionId, $id );

    // Associate this portfolio to a category
    $parentTermId = $wplr->get_meta( "cosmothemes_term_id", $inFolderId );
    if ( $parentTermId ) {
      $term = get_term_by( 'term_id', $parentTermId, 'gallery-category' );
      if ( !empty( $term ) )
        wp_set_post_terms( $id, $term->term_id, 'gallery-category' );
    }
  }

  function create_folder( $folderId, $inFolderId, $folder ) {
    global $wplr;
    $parentTermId = $wplr->get_meta( "cosmothemes_term_id", $inFolderId );
    $result = wp_insert_term( $folder['name'], 'gallery-category', array( 'parent' => $parentTermId ) );
    if ( is_wp_error( $result ) ) {
      error_log( "Issue while creating the folder " . $folder['name'] . "." );
      error_log( $result->get_error_message() );
      return;
    }
    $wplr->set_meta( 'cosmothemes_term_id', $folderId, $result['term_id'] );
  }

  // Updated the collection with new information.
  // Currently, that would be only its name.
  function update_collection( $collectionId, $collection ) {
    global $wplr;
    $id = $wplr->get_meta( "cosmothemes_gallery_id", $collectionId );
    $post = array( 'ID' => $id, 'post_title' => wp_strip_all_tags( $collection['name'] ) );
    wp_update_post( $post );
  }

  // Updated the folder with new information.
  // Currently, that would be only its name.
  function update_folder( $folderId, $folder ) {
    global $wplr;
    $termId = $wplr->get_meta( "cosmothemes_term_id", $folderId );
    wp_update_term( $termId, 'gallery-category', array( 'name' => $folder['name'] ) );
  }

  // Moved the collection under another folder.
  // If the folder is empty, then it is the root.
  function move_collection( $collectionId, $folderId, $previousFolderId ) {
    global $wplr;
    $galleryId = $wplr->get_meta( "cosmothemes_gallery_id", $collectionId );
    $parentTermId = empty( $folderId ) ? null : $wplr->get_meta( "cosmothemes_term_id", $folderId );
    $previousTermId = empty( $previousFolderId ) ? null : $wplr->get_meta( "cosmothemes_term_id", $previousFolderId );

    // Remove the previous term (category) and add the new one
    wp_remove_object_terms( $galleryId, $previousTermId, 'gallery-category' );
    wp_set_post_terms( $galleryId, $parentTermId, 'gallery-category' );
  }

  // Added meta to a collection.
  // The $mediaId is actually the WordPress Post/Attachment ID.
  function add_media_to_collection( $mediaId, $collectionId, $isRemove = false ) {
    global $wplr;
    $id = $wplr->get_meta( "cosmothemes_gallery_id", $collectionId );
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
    global $wplr;
    $this->add_media_to_collection( $mediaId, $collectionId, true );

    // Need to delete the featured image if it was this media
    $postId = $wplr->get_meta( "cosmothemes_gallery_id", $collectionId );
    $thumbnailId = get_post_meta( $postId, '_thumbnail_id', -1 );
    if ( $thumbnailId == $mediaId )
      delete_post_meta( $postId, '_thumbnail_id' );
  }

  // The collection was deleted.
  function remove_collection( $collectionId ) {
    global $wplr;
    $id = $wplr->get_meta( "cosmothemes_gallery_id", $collectionId );
    wp_delete_post( $id, true );
    $wplr->delete_meta( 'cosmothemes_gallery_id', $collectionId );
  }

  // Delete the folder.
  function remove_folder( $folderId ) {
    global $wplr;
    $id = $wplr->get_meta( "cosmothemes_term_id", $folderId );
    wp_delete_term( $id, 'gallery-category' );
    $wplr->delete_meta( 'cosmothemes_term_id', $folderId );
  }
}

new WPLR_Extension_CosmoThemes;

?>
