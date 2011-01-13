<?php

/**
 * db.pg.class.php
 *
 * @author Stephan Saalfeld <saalfeld@phenomene.de>
 * @copyright Copyright (c) 2006, Stephan Saalfeld
 * @version 0.1
 */
/**
 */
include_once( 'setup.inc.php' );

/**
 * factory method to get the single instance of the object
 *
 * @param string $mode 'read'|'write'
 *
 * @return DB singleton instance
 *
 * @todo every PHP script can get write access to the database calling getDB( 'write' ), should it be restricted?
 *  possible 'solution':  different include paths for HTTPS and HTTP, HTTP-includes only contain SQL-read login...
 */
function &getDB( $mode = 'read' )
{
	static $singleton_database;
	if ( !isset( $singleton_database ) )
	{
		$singleton_database = new DB( $mode );
	}
	return $singleton_database;
}

/**
 * DB
 *
 * provides access to the database and some specific methods
 *
 * @todo every PHP script can get write access to the database calling DB( 'write' )
 * @see getDB()
 */
class DB
{
	/**#@+
	 * @var string
	 * @access private
	 */
	var $host;
	var $user;
	var $pw;
	var $db;
	var $handle;

	/**
	 * Constructor
	 */  
	function DB( $mode = 'read' )
	{
		global $db_host, $db_user, $db_pw, $db_db;
		$this->host = $db_host[ $mode ];
		$this->user = $db_user[ $mode ];
		$this->pw = $db_pw[ $mode ];
		$this->db = $db_db[ $mode ];
		
		$this->handle = pg_connect( 'host='.$this->host.' port=5432 dbname='.$this->db.' user='.$this->user.' password= '.$this->pw ) or die( pg_last_error() );
	}
	/**#@-*/
	
	/**
	 * get the last error if there was one
	 *
	 * @return string
	 */
	function getError()
	{
		return pg_last_error();
	}
	
	/**
	 * get the results of an SQL query
	 *
	 * @param string $query SQL query
	 *
	 * @return false|array associative index=>name=>value
	 */
	function getResult( $query )
	{
		$result = array();
		if ( $temp = pg_query( $this->handle, $query ) )
		{
			while ( $result[] = @pg_fetch_assoc( $temp ) ) {}
			array_pop( $result );
		}
		else
			$result = false;
		return $result;
	}
	
	/**
	 * get the results of an SQL query keyed by id
	 *
	 * @param string $query SQL query, $id for what name for key
	 *
	 * @return false|array associative index=>name=>value
	 */
	function getResultKeyedById( $query, $id )
	{
		$result = array();
		if ( $temp = pg_query( $this->handle, $query ) )
		{
			while ( $result[] = @pg_fetch_assoc( $temp ) ) {}
			array_pop( $result );
		}
		else
		{
			return false;
		}

		$nresult = array();
		
		if( $result ) {
			foreach( $result as $value ) {
				$nresult[$value[$id]] = $value; 
			}
		}
		return $nresult;
	}
	
	/**
	 * count the entries of a table that match an optional condition
	 *
	 * @param string $table
	 * @param string $cond condition
	 *
	 * @return int
	 */
	function countEntries( $table, $cond = '1' )
	{
		$entries = $this->getResult( 'SELECT count( * ) AS "count" FROM "'.$table.'" WHERE '.$cond );
		//echo( "SELECT count(*) AS 'count' FROM `".$table."` WHERE ".$cond );
		return ( $entries[ 0 ][ 'count' ] );
	}

	/**
	 * close the connection
	 *
	 * @return void
	 */
	function close()
	{
		pg_close( $this->handle );
		return;
	}
	
	/**
	 * insert an entry into a table
	 *
	 * @param string $table
	 * @param array $values associative name=>value
	 *
	 * @return void
	 */
	function insertInto( $table, $values )
	{
		$queryStr = 'INSERT INTO "'.$table.'" (';
		$keys = array_keys( $values );
		$max = sizeof( $keys ) - 1;
		for ( $i = 0; $i < $max; ++$i )
		{
			$queryStr .= '"'.$keys[ $i ].'", ';
		}
		$queryStr .= '"'.$keys[ $max ].'") VALUES (';
		for ( $i = 0; $i <= $max; ++$i )
		{
			if ( is_numeric( $values[ $keys[ $i ] ] ) ) $queryStr .= $values[ $keys[ $i ] ];
			elseif ( is_string( $values[ $keys[ $i ] ] ) ) $queryStr .= "'".pg_escape_string( $values[ $keys[ $i ] ] )."'";
			elseif ( is_bool( $values[ $keys[ $i ] ] ) ) $queryStr .= ( $values[ $keys[ $i ] ] ? 'TRUE' : 'FALSE' );
			if ( $i != $max ) $queryStr .= ', ';
		}
		$queryStr .= ')';
		//echo $queryStr, "<br />\n";
		pg_query( $this->handle, $queryStr );
		return;
	}
	
	/**
	 * insert an entry into a table
	 * return the automatically set id (sequence)
	 *
	 * @param string $table
	 * @param array $values associative name=>value
	 *
	 * @return int id
	 */
	function insertIntoId( $table, $values )
	{
		$this->insertInto( $table, $values );
		$id = $this->getResult( 'SELECT lastval() AS "id"' );
		return $id[ 0 ][ 'id' ];
	}
	
	/**
	 * update an entry of a table
	 *
	 * @param string $table
	 * @param array $values associative name=>value
	 * @param string $cond condition
	 *
	 * @return int affected rows
	 */
	function update( $table, $values, $cond = '0' )
	{
		$query	= 'UPDATE "'.$table.'" SET ';
		$keys	= array_keys( $values );
		$max	= sizeof( $values );
		for ( $i = 0; $i < $max; ++$i )
		{
			$query .= '"'.$keys[ $i ].'" = ';
			if ( is_numeric( $values[ $keys[ $i ] ] ) ) $query .= $values[ $keys[ $i ] ];
			elseif ( is_string( $values[ $keys[ $i ] ] ) ) $query .= "'".pg_escape_string( $values[ $keys[ $i ] ] )."'";
			elseif ( is_bool( $values[ $keys[ $i ] ] ) ) $query .= ( $values[ $keys[ $i ] ] ? 'TRUE' : 'FALSE' );
			if ( $i != $max - 1 ) $query .= ', ';
		}
		$query .= ' WHERE '.$cond;
		//echo $query;
		$r = pg_query( $this->handle, $query );
		return pg_affected_rows( $r );
	}
	
	/**
	 * delete an entry from a table
	 *
	 * @param string $table
	 * @param string $cond condition
	 *
	 * @return int affected rows
	 */
	function deleteFrom( $table, $cond = '0' )
	{
		//print("DELETE FROM `".$table."` WHERE ".$cond.";<br />\n");
		$r = pg_query( $this->handle, 'DELETE FROM "'.$table.'" WHERE '.$cond );
		return pg_affected_rows( $r );
	}
	
	/**
	 * get a branch of a recursive tree
	 *
	 * a recursive tree contains a key and a reference to the parent nodes key
	 * get a branch through climbing up the tree from the given node to the root
	 *
	 * @param string $table
	 * @param string $id id of the "youngest" node
	 * @param string $idName name of the id collumn
	 * @param string $pidName name of the parent id reference collumn
	 * @param string $cond condition
	 *
	 * @return array
	 *
	 * @todo sloppy fixed for postgres, never checked
	 */
	function getTreeBranch( $table, $id, $idName = 'id',$pidName = 'parent_id', $cond = '1' )
	{
		$ret = array();	
		do
		{
			$cur = $this->getResult( 'SELECT * FROM "'.$table.'" WHERE "'.$idName.'" = \''.$id.'\' AND '.$cond.' LIMIT 1' );
			$ret[] = $cur[ 0 ];
			$id = $cur[ 0 ][ $pidName ];
		}
		while ( sizeof( $cur ) > 0 && $cur[ 0 ][ $pidName ] );
		return $ret;
	}
	
	/**
	 * get a nodes children, childrens children and so on of parent node in a recursive tree
	 *
	 * a recursive tree contains a key and a reference to the parent nodes key
	 * children all have got the same parent node, every child can have children
	 *
	 * @uses DB::getTreeChildren() recursively
	 *
	 * @param string $table
	 * @param string $pid id of the parent node
	 * @param string $idName name of the id collumn
	 * @param string $pidName name of the parent id reference collumn
	 * @param string $cond condition
	 *
	 * @return array|0
	 *
	 * @todo sloppy fixed for postgres, never checked
	 */
	function getTreeChildren( $table, $pid, $idName = 'id', $pidName = 'parent_id', $cond = '1' )
	{
		$cur = $this->getResult( 'SELECT * FROM "'.$table.'" WHERE "'.$pidName.'" = \''.$pid.'\' AND '.$cond );
		$anz = sizeof( $cur );
		if ( $anz > 0 )
		{
			for ( $i = 0; $i < $anz; $i++ )
			{
				$ret[$i] = array(
						'node' => $cur[ $i ],
						'children' => $this->getTreeChildren(
							$table,
							$cur[ $i ][ $idName ],
							$idName,
							$pidName,
							$cond ) );
			}
		}
		else $ret = 0;
		return $ret;
	}
	
	/**
	 * get a nodes children, childrens children and so on of a parent node and the parent node as root in a recursive tree
	 *
	 * a recursive tree contains a key and a reference to the parent nodes key
	 * children all have got the same parent node, every child can have children
	 *
	 * @uses DB::getTreeChildren()
	 *
	 * @param string $table
	 * @param string $id id of the node
	 * @param string $idName name of the id collumn
	 * @param string $pidName name of the parent id reference collumn
	 * @param string $cond condition
	 *
	 * @return array|0
	 *
	 * @todo sloppy fixed for postgres, never checked
	 */
	function getTree( $table, $id, $idName = 'id', $pidName = 'parent_id', $cond = '1' )
	{
		$cur = $this->getResult( 'SELECT * FROM "'.$table.'" WHERE "'.$idName.'" = \''.$id.'\' AND '.$cond.' LIMIT 1' );
		if ( sizeof($cur) > 0 )
		{
			$ret = array( 'node' => $cur[0], 'children' => $this->getTreeChildren( $table, $id, $idName, $pidName, $cond ) );
		}
		else
		{
			$ret = 0;
		}
		return $ret;
	}
	
	/*
	 * Retrieve relation id for a relation class in a given project
	 */
	function getRelationId( $pid, $relationname )
	{
		$res = $this->getResult(
		'SELECT "relation"."id" FROM "relation"
		WHERE "relation"."project_id" = '.$pid.' AND
		"relation"."relation_name" = \''.$relationname.'\'');
		$resid = !empty($res) ? $res[0]['id'] : 0;
		return $resid;
	}

	/*
	 * Retrieve class id for a class name in a given project
	 */
	function getClassId( $pid, $classname )
	{
		$res = $this->getResult(
		'SELECT "class"."id" FROM "class"
		WHERE "class"."project_id" = '.$pid.' AND
		"class"."class_name" = \''.$classname.'\'');
		$resid = !empty($res) ? $res[0]['id'] : 0;
		return $resid;
	}
  
  /*
   * return all treenode ids for a skeleton starting with root node as a flat list
   * using the element_of relationship of the treenodes
   */
   function getTreenodeIdsForSkeleton( $pid, $skelid )
   {
    // element of id
    $ele_id = $this->getRelationId( $pid, 'element_of' );
    if(!$ele_id) { echo makeJSON( array( '"error"' => 'Can not find "element_of" relation for this project' ) ); return; }

    $res = $this->getResult(
    'SELECT "tci"."id" FROM "treenode_class_instance" AS "tci", "treenode"
    WHERE "tci"."project_id" = '.$pid.' AND
    "tci"."relation_id" = '.$ele_id.' AND
    "tci"."class_instance_id" = '.$skelid.' AND
    "treenode"."id" = "tci"."treenode_id"
    ORDER BY "treenode"."parent_id" DESC');
    
    return $res;
   }
   
   /*
    * return class_instance id of a treenode for a given relation in a project
    */
   function getClassInstanceForTreenode( $pid, $tnid, $relation )
   {
    // element of id
    $rel_id = $this->getRelationId( $pid, $relation );
    if(!$rel_id) { echo makeJSON( array( '"error"' => 'Can not find "'.$relation.'" relation for this project' ) ); return; }

    $res = $this->getResult(
    'SELECT "tci"."class_instance_id" FROM "treenode_class_instance" AS "tci"
    WHERE "tci"."project_id" = '.$pid.' AND
    "tci"."relation_id" = '.$rel_id.' AND
    "tci"."treenode_id" = '.$tnid);
    
    return $res;
   }

  /*
   * get class_instance parent from a class_instance given its relation
   */
   function getCIFromCI( $pid, $cid, $relation )
   {
    // element of id
    $rel_id = $this->getRelationId( $pid, $relation );
    if(!$rel_id) { echo makeJSON( array( '"error"' => 'Can not find "'.$relation.'" relation for this project' ) ); return; }

    $res = $this->getResult(
    'SELECT "tci"."class_instance_b" as "id" FROM "class_instance_class_instance" AS "cici"
    WHERE "cici"."project_id" = '.$pid.' AND
    "cici"."relation_id" = '.$rel_id.' AND
    "cici"."class_instance_a" = '.$cid);
    
    return $res;
   }
   
   /*
    * get children of a treenode in a flat list
    */
   function getTreenodeChildren( $pid, $tnid )
   {
    $res = $this->getResult(
    'SELECT "treenode"."id" AS "tnid" FROM "treenode" WHERE 
    "treenode"."project_id" = '.$pid.' AND
    "treenode"."parent_id" = '.$tnid);
    
    return $res;
   }

  /*
   * get all downstream treenodes for a given treenode
   */
   function getAllTreenodeChildrenRecursively( $pid, $tnid )
   {
    $res = $this->getResult(
    "SELECT * FROM connectby('treenode', 'id', 'parent_id', 'parent_id', '".$tnid."', 0)
    AS t(id int, parent_id int, level int, branch int);");
    
    return $res;
   }
  
  /*
   * split a skeleton given a treenode
   */
  function splitSkeleton( $pid, $tnid )
  {
 
    $modof = 'model_of';
    $eleof = 'element_of';
    
    // relation ids
    $modof_id = $this->getRelationId( $pid, $modof );
    if(!$modof_id) { echo makeJSON( array( '"error"' => 'Can not find "'.$modof.'" relation for this project' ) ); return; }

    $eleof_id = $this->getRelationId( $pid, $eleof );
    if(!$eleof_id) { echo makeJSON( array( '"error"' => 'Can not find "'.$eleof.'" relation for this project' ) ); return; }

    $skid = $this->getClassId( $pid, "skeleton" );
    if(!$skid) { echo makeJSON( array( '"error"' => 'Can not find "skeleton" class for this project' ) ); return; }
 
    // retrieve class_instances for the treenode, should only be one id
    $ci_id = getClassInstanceForTreenode( $pid, $tnid, 'model_of');
    // delete the model of, assume only one
    $ids = $this->deleteFrom("class_instance", ' "class_instance"."id" = '.$ci_id[0]['class_instance_id']);
    
    // retrieve skeleton id
    $sk = getClassInstanceForTreenode( $pid, $tnid, 'element_of');
    $sk_id = $sk[0]['class_instance_id'];
    
    // retrieve neuron id of the skeleton
    $neu = getCIFromCI( $pid, $sk_id, 'model_of' );
    $neu_id = $neu[0]['id'];
    
    $childrentreenodes = $this->getTreenodeChildren( $pid, $tnid );
    // for each children, set to root and create a new skeleton
    foreach($treenodes as $key => $tn) {
      // set to root
      $ids = $this->getResult('UPDATE "treenode" SET "parent_id" = NULL WHERE "treenode"."id" = '.$tn['tnid']);
      // create new skeleton
      $data = array(
        'user_id' => $uid,
        'project_id' => $pid,
        'class_id' => $skid,
        'name' => 'skeleton'
        );
      $skelid = $this->insertIntoId('class_instance', $data );
      // update skeleton name by adding its id to the end
      $up = array('name' => 'skeleton '.$skelid);
      $upw = 'id = '.$skelid;
      $this->update( "class_instance", $up, $upw);
      
      // attach skeleton to neuron
      $data = array(
          'user_id' => $uid,
          'project_id' => $pid,
          'relation_id' => 'model_of',
          'class_instance_a' => $skelid,
          'class_instance_b' => $neu_id 
        );
      $this->insertInto('class_instance_class_instance', $data );
      
      // update element_of of sub-skeleton
      // retrieve all treenode ids by traversing the subtree
      $allchi = $this->getAllTreenodeChildrenRecursively( $pid, $tn['tnid'] );
      foreach($treenodes as $key => $chitn) {
        // update the element_of to the newly created skeleton
        // and the new root treenode
        $ids = $this->getResult('UPDATE "treenode_class_instance" SET "class_instance_id" = '.$skelid.' WHERE
        "treenode_class_instance"."treenode_id" = '.$tn['tnid'].' AND
        "treenode_class_instance"."relation_id" = '.$eleof_id);
      };
    };
  }

}

?>
