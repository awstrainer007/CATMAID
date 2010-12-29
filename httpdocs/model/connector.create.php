<?php

include_once( 'errors.inc.php' );
include_once( 'db.pg.class.php' );
include_once( 'session.class.php' );
include_once( 'tools.inc.php' );
include_once( 'json.inc.php' );

$db =& getDB();
$ses =& getSession();

$pid = isset( $_REQUEST[ 'pid' ] ) ? intval( $_REQUEST[ 'pid' ] ) : 0;
$uid = $ses->isSessionValid() ? $ses->getId() : 0;

$input_id = isset( $_REQUEST[ 'input_id' ] ) ? intval( $_REQUEST[ 'input_id' ] ) : 0;
$input_relation = isset( $_REQUEST[ 'input_relation' ] ) ? $_REQUEST[ 'input_relation' ] : 'none';
$input_type = isset( $_REQUEST[ 'input_type' ] ) ? $_REQUEST[ 'input_type' ] : 'none';
$input_location_relation = isset( $_REQUEST[ 'input_location_relation' ] ) ? $_REQUEST[ 'input_location_relation' ] : 'none';
$x = isset( $_REQUEST[ 'x' ] ) ? floatval( $_REQUEST[ 'x' ] ) : 0;
$y = isset( $_REQUEST[ 'y' ] ) ? floatval( $_REQUEST[ 'y' ] ) : 0;
$z = isset( $_REQUEST[ 'z' ] ) ? floatval( $_REQUEST[ 'z' ] ) : 0;
$location_type = isset( $_REQUEST[ 'location_type' ] ) ? $_REQUEST[ 'location_type' ] : 'none';
$location_relation = isset( $_REQUEST[ 'location_relation' ] ) ? $_REQUEST[ 'location_relation' ] : 'none';

if ( $pid )
{
  if ( $uid )
  {
    // retrieve class ids
    $it_id = $db->getClassId( $pid, $input_type );
    if(!$it_id) { echo makeJSON( array( '"error"' => 'Can not find "'.$input_type.'" class for this project' ) ); return; }
    
    $lt_id = $db->getClassId( $pid, $location_type );
    if(!$lt_id) { echo makeJSON( array( '"error"' => 'Can not find "'.$location_type.'" class for this project' ) ); return; }
    
    // relation ids
    $ir_id = $db->getRelationId( $pid, $input_relation );
    if(!$ir_id) { echo makeJSON( array( '"error"' => 'Can not find "'.$input_relation.'" relation for this project' ) ); return; }

    $ilr_id = $db->getRelationId( $pid, $input_location_relation );
    if(!$ilr_id) { echo makeJSON( array( '"error"' => 'Can not find "'.$input_location_relation.'" relation for this project' ) ); return; }

    $lr_id = $db->getRelationId( $pid, $location_relation );
    if(!$lr_id) { echo makeJSON( array( '"error"' => 'Can not find "'.$location_relation.'" relation for this project' ) ); return; }
        
    // class_instance
      // * synapseX <synapse>
      // * presynaptic terminal X <presynaptic terminal>
    $data = array(
      'user_id' => $uid,
      'project_id' => $pid,
      'class_id' => $lt_id,
      'name' => $location_type
      );
    $location_type_instance_id = $db->insertIntoId('class_instance', $data );
    // rename location_type instance
    $up = array('name' => $location_type.' '.$location_type_instance_id);
    $db->update( "class_instance", $up, 'id = '.$location_type_instance_id); 
      
    $data = array(
      'user_id' => $uid,
      'project_id' => $pid,
      'class_id' => $it_id,
      'name' => $input_type
      );
    $input_type_instance_id = $db->insertIntoId('class_instance', $data );
    // rename location_type instance
    $up = array('name' => $input_type.' '.$input_type_instance_id);
    $db->update( "class_instance", $up, 'id = '.$input_type_instance_id); 
    
    //location
      //* new location L
    $data = array(
      'user_id' => $uid,
      'project_id' => $pid,
      'location' => '('.$x.','.$y.','.$z.')'
      );
    $location_instance_id = $db->insertIntoId('location', $data );
     
    //treenode_class_instance
      //* treenode model_of presynaptic terminal X
    $data = array(
      'user_id' => $uid,
      'project_id' => $pid,
      'relation_id' => $ir_id,
      'treenode_id' => $input_id,
      'class_instance_id' => $input_type_instance_id
      );
    $db->insertInto('treenode_class_instance', $data );
      
    //location_class_instance
      //* L model_of synapseX
    $data = array(
      'user_id' => $uid,
      'project_id' => $pid,
      'relation_id' => $lr_id,
      'location_id' => $location_instance_id,
      'class_instance_id' => $location_type_instance_id
      );
    $db->insertInto('location_class_instance', $data );
    
    // class_instance_class_instance
      //* presynaptic terminal X presynaptic_to synapseX
    $data = array(
      'user_id' => $uid,
      'project_id' => $pid,
      'relation_id' => $ilr_id,
      'class_instance_a' => $input_type_instance_id,
      'class_instance_b' => $location_type_instance_id
      );
    $db->insertInto('class_instance_class_instance', $data );
    
    echo makeJSON( array( 'success' => 'Added location and corresponding annotation updates.' ) );
    
  }
  else
    echo makeJSON( array( 'error' => 'You are not logged in currently.  Please log in to be able to create locations.' ) );
}
else
  echo makeJSON( array( 'error' => 'Project closed. Can not apply operation.' ) );
  
?>
    