<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use app\models\LoginForm;
use app\models\ContactForm;

// node types
const ROOT_NODE = 'root';
const PARENT_NODE = 'parent';
const LEAF_NODE = 'leaf';

// copy recipe prefix
const COPY_PREFIX = '**COPY-';
const MAX_LEN_COPY_NAME = 32;

// renumbering 
const NODE_RENUMBER_START = 10;
const NODE_RENUMBER_BUMP = 10;

// JSON Error codes and messages (DO NOT CHANGE NUMBERS!!)
const JSON_RESP_OK = 0;
const JSON_RESP_INVALID_NODE = 1;
const JSON_RESP_NOTHING_TO_DELETE = 2;
const JSON_RESP_INVALID_UPDATE_DATA = 3;
const JSON_RESP_UPDATE_COUNT_INCONSISTENT = 4;
const JSON_RESP_INVALID_ADD_DATA = 5;
const JSON_RESP_INVALID_MOVE_DATA = 6;
const JSON_RESP_SQL_ERROR = 7;
const JSON_RESP_INVALID_RECIPE_ID = 8;
const JSON_RESP_INVALID_LEAF_TO_LEAF = 9;
const JSON_RESP_INVALID_SOURCE_NODE = 10;
const JSON_RESP_INVALID_TARGET_NODE = 11;
const JSON_RESP_DUPE_SPEC_IN_LEVEL = 12;
const JSON_RESP_DELETE_ROOT_NOT_ALLOWED = 13;
const JSON_RESP_RENUMBER_ERROR = 14;
const JSON_RESP_EMPTY_PARENT = 15;
const JSON_RESP_INVALID_POSITION = 16;
const JSON_RESP_INVALID_COPY_DATA = 17;
const JSON_RESP_INVALID_DEACTIVATE_DATA = 18;

const JSON_RESP_INVALID_ERROR = 99999;

// get the error string for a particular JSON status response
function getJSONStatus($status_id)
{
	$JSON_RESP_MSG = [
		JSON_RESP_OK => 'OK',
		JSON_RESP_INVALID_NODE => 'Invalid Node Id',
		JSON_RESP_NOTHING_TO_DELETE => 'Nothing To Delete',
		JSON_RESP_INVALID_UPDATE_DATA => 'Invalid Update Data',
		JSON_RESP_UPDATE_COUNT_INCONSISTENT => 'Update Count Inconsistent, should be 1, but it\'s more. Internal Error',
		JSON_RESP_INVALID_ADD_DATA => 'Invalid Add Data',
		JSON_RESP_INVALID_MOVE_DATA => 'Invalid Move Data',
		JSON_RESP_SQL_ERROR => 'Internal Error, SQL Execution Failed',
		JSON_RESP_INVALID_RECIPE_ID => 'Invalid Recipe Id',
		JSON_RESP_INVALID_LEAF_TO_LEAF => 'Invalid Add, Can\'t Add Leaf to a Leaf',
		JSON_RESP_INVALID_SOURCE_NODE => 'Source Node does not exist',
		JSON_RESP_INVALID_TARGET_NODE => 'Target Node does not exist',
		JSON_RESP_DUPE_SPEC_IN_LEVEL => 'Spec Already Exists in Level',
		JSON_RESP_DELETE_ROOT_NOT_ALLOWED => 'Delete Root Node of Recipe is Not Allowed',
		JSON_RESP_RENUMBER_ERROR => 'Node Renumber Failed',
		JSON_RESP_EMPTY_PARENT => 'Node has no children',
		JSON_RESP_INVALID_POSITION => 'Invalid Move Position',
		JSON_RESP_INVALID_COPY_DATA => 'Invalid Copy Data',
		JSON_RESP_INVALID_DEACTIVATE_DATA => 'Invalid Deactivate Data',
		
		JSON_RESP_INVALID_ERROR => 'Error of unknown type',
	];	

	if(isset($JSON_RESP_MSG[$status_id]))
		return $JSON_RESP_MSG[$status_id];
		
	return $JSON_RESP_MSG[JSON_RESP_INVALID_ERROR];
}

// here is the format for the response. This is what the 'other side' will
// see when they get the data back. The status and messages are as above,
// Data will be empty array otherwise some data that might be useful to the 
// client. Note any PHP structs to JSON structs conversion issues will apply 
// when the object is shipped and internally converted by json_encode()
//
// ['status' => JSON_RESP_OK, 'msg'=>JSON_RESP_MSG_OK, 'data' => []];

// simple helper to format the json response data
// $data is an array, others are scalar values
function formatJSONResponse($status, $data)
{
	$resp = [];
	
	$resp['status'] = $status;
	$resp['msg'] = getJSONStatus($status);	// get message
	$resp['data'] = $data;
	
	return $resp;
}

/// sjg note -
/// MOST OF THIS SHOULD BE OFFLOADED TO A TREEEDIT CONTROLLER KEEPING
/// SITE RELATED CODE HERE AND SPECIFIC TREE (OR OTHER) CODE IN IT'S 
/// OWN CONTROLLER

class SiteController extends Controller
{
	
    public function behaviors()
    {
        return [	
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    // returns a list of the specs. Will return only active
    // format is as an array of records. don't bring back the 9999 spec id, it's not
    // selectable by the user
    public function getSpecs()
    {
		$specs = (new Query())->select('id, spec_name')->
									from('{{%specs}}')->
									where('id != :id', ['id'=>9999])->
									orderBy('spec_name')->
									all();
		return $specs;
	}
	

	// function to return a proper formatted array for the Select2 component
	// this builds a list of spec categories and then an array of the specs
    public function getSpecsByCat()
    {
		$rows = (new Query())->select('specs.id as id, specs.spec_name as text, cats.category_name')->
									from('{{%specs}} as specs, {{%categories}} as cats')->
									where('specs.id != :id', ['id'=>9999])->
									andWhere('specs.category_id = cats.id')->
									orderBy('category_name,spec_name')->
									all();

		$specs = [];
		$cat_specs = [];
		$heading = $rows[0]['category_name'];

		foreach($rows as $row)
		{
			if($heading != $row['category_name'])
			{
				$specs[] = ['text' => $heading, 'children' => ArrayHelper::map($cat_specs, 'id', 'text')];
				$heading = $row['category_name'];
				$cat_specs = [];
			}
			
			$cat_specs[] = ['id' => $row['id'], 'text' => $row['text']];
		}
								
		return ArrayHelper::map($specs, 'text', 'children');
	}


	// similar to above, but returns list of valid weights, keep 
	// results like database just in case we need to pull from db later
	// 0-10 are valid weights
	public function getWeights()
	{
		return [
				['id'=> 0, 'weight'=>'Ignore'],
				['id'=> 1, 'weight'=>'1'],
				['id'=> 2, 'weight'=>'2'],
				['id'=> 3, 'weight'=>'3'],
				['id'=> 4, 'weight'=>'4'],
				['id'=> 5, 'weight'=>'5'],
				['id'=> 6, 'weight'=>'6'],
				['id'=> 7, 'weight'=>'7'],
				['id'=> 8, 'weight'=>'8'],
				['id'=> 9, 'weight'=>'9'],
				['id'=> 10, 'weight'=>'10'],
			];
	}
	

	// return one recipie, must be active, returns false on miss
	public function getRecipe($recipe_id)
	{
		$recipe = (new Query())->select('id, name, description, author')->
									from('{{%recipes}}')->
									orderBy('name')->
									where(['id' => $recipe_id, 'active'=>'1'])->
									limit(1)->
									one();
		return $recipe;
	}

	// return the list of recipies, must be active 
	public function getRecipes()
	{
		$recipes = (new Query())->select('id, name')->
									from('{{%recipes}}')->
									orderBy('name')->
									where(['active'=>'1'])->
									all();
		return ArrayHelper::map($recipes, 'id', 'name');
	}
	
	// given a parent node, return a list of all children. This 
	// has no assumed order of nodes in the list
	//
	// $child_list must be initialized empty array
	// $parent_id is the parent node you are looking for it' children, and parent_id is not in the result set
	
	public function getChildList(&$child_list, $parent_id) 
	{
		// get the list of all nodes with this parent id
		$treedata = (new Query())->select('id, spec_id, parent_id')->
									from('{{%attributes}}')->
									where(['parent_id' => $parent_id])-> // was , 'active' => '1'
									all();
	
		// add to the list, and call again with any potentials (recursive)
		// if no data recursion stops
		
		foreach($treedata as $row) 
		{
			$child_list[] = $row['id'];
			
			// make the call if the spec_id == 9999 as that is not a terminal node and need exploration
			
			if($row['spec_id'] == 9999)
				$this->getChildList($child_list, $row['id']);
		}
		
		return;
	}
	
	// finds all the nodes directly decending from the parent. No traversal is done
	// returns a list of the node_id's
	public function getImmediateChildList($parent_id)
	{
		// get the list of all nodes with this parent id
		$treedata = (new Query())->select('id')->
									from('{{%attributes}}')->
									where(['parent_id' => $parent_id])->
									all();
		// return a nice list
			
		$children_list = [];	
		foreach($treedata as $row) 
			$children_list[] = $row['id'];
		
		return $children_list; // list of children with same parent_id (immediate)
	}	

	// given a parent node, return a list of all childrens spec_ids. This 
	// has no assumed order of nodes in the list
	//
	// $child_list must be an initialized
	// $parent_id is the parent node you are looking for it' children, and parent_id is not in the result set
	// this is similar to the above except it is used to check for the same
	// spec_id in any level at or below
	// ignore ID will be an Id to skip. This is useful for UPDATE operations
	public function getChildSpecList(&$spec_list, $node_id, $ignore_id) 
	{
		// get the list of all nodes with this parent id
		$treedata = (new Query())->select('id, parent_id, spec_id')->
									from('{{%attributes}}')->
									where(['parent_id' => $node_id])->
									all();
	
		// add to the list, and call again with any potentials (recursive)
		// if no data recursion stops
		
		foreach($treedata as $row) 
		{
			// skip storing the ignore id if a match, we should still travers it's children in any case
			
			if($ignore_id != $row['id'])
				$spec_list[] = $row['spec_id']; // save the spec_id in the flattend list
			
			// skip any terminal/leaf nodes, save the call as we know they can't have any children
			
			if($row['spec_id'] == 9999)
				$this->getChildSpecList($spec_list, $row['id'], $ignore_id);
		}
		
		return;
	}

	// finds all the nodes directly decending from the parent. No traversal is done
	// returns the spec_ids at that level
	
	public function getImmediateChildSpecList($parent_id, $ignore_id)
	{
		// get the list of all nodes with this parent id
		$treedata = (new Query())->select('id, spec_id')->
									from('{{%attributes}}')->
									where(['parent_id' => $parent_id])->
									all();
		// return a nice list
			
		$children_list = [];	
		foreach($treedata as $row) 
			if($ignore_id != $row['id'])
				$children_list[] = $row['spec_id'];
		
		return $children_list; // list of children with same parent_id (immediate)
	}

	// helper to get immediate child nodes with specific fields for order sorting
	// comes sorted by order
	public function getChildNodesOrder($parent_id)
	{
		$children = (new Query())->select('id, spec_id, order')->
									from('{{%attributes}}')->
									where(['parent_id' => $parent_id])->
									orderBy('order')->
									all();
		if(count($children) == 0)
			return false;
		return $children;
	}

	// helper to sort a list of records by order, will be needed at some point after list is messed with
	public function sortNodeListOrder(&$node_list)
	{
		// sort in ascending order yes, closure used here - idea stolen from stack overflow
		usort($node_list, function($a, $b) {
				return $a['order'] > $b['order'];
			});
	}
	
	// helper to renumber a list. Will make nice even numbers
	public function renumberNodeList(&$node_list)
	{
		// renumber starting with some value, bump by another
		$bumper = NODE_RENUMBER_START;
		foreach($node_list as &$br)
		{
			$br['order'] = $bumper;
			$bumper += NODE_RENUMBER_BUMP;
		}		
	}

	// write a list of nodes to the db updating only the order.
	// must pass in an array of rows with each row having the
	// field of 'id' and 'order' set
	public function updateNodeListOrder($node_list)
	{
		foreach($node_list as $rec)
		{
			// let the db pummel begin
			
			try
			{
				Yii::$app->db->createCommand()->update('{{%attributes}}', 
							[	// upate fields
								'order' => $rec['order'],
							],
							['id' => $rec['id']]	// where part
				)->execute();
			}
			catch(Exception $e)
			{
				return false; 
			}
		}
		return true;
	}
	
	// given a node (hopefully a parent) it will renumber the nodes acording to the
	// current order. What? Well if nodes are ordered 1, 45, 55,999 the nodes would
	// be cleanly reorderd 10, 20, 30, 40. This does NOT change the order value
	// of the passed in parent. It does not matter if the node is a leaf or a
	// child it will get renumbers. Again note the lexical position of the node
	// will not change just the numeric value. Rememeber Basic's renumber, same thing.
	// saves to the db!
	public function renumberLeafs($parent_id)
	{
		// get a list of this parents children

		if(($children = $this->getChildNodesOrder($parent_id)) === false)
			return JSON_RESP_EMPTY_PARENT;
	
		$this->sortNodeListOrder($children);
		$this->renumberNodeList($children);

		// OK, now update it! Count is tricky here since if the data is the exact same
		// it will NOT return an count of 1, but rather a count of 0 since nothing was changed
		// note this can only update the order. Again possible place for a transaction 

		if(!$this->updateNodeListOrder($children))
			return JSON_RESP_RENUMBER_ERROR;
		else
			return JSON_RESP_OK;
	}

	// this will check for a spec on an existing level or below of the tree.
	// return true if spec exist in the parent and below, false if not
	// The $node_id should be a parent node, or this will return false 	
	// note : $ignore_id will ignore the passed in node id so update of 
	// a record can happen
	public function isExistingSpec($node_id, $spec_id, $ignore_id = -1)
	{			
		$spec_list = [];
		
		$this->getChildSpecList($spec_list, $node_id, $ignore_id);
		 
		return in_array($spec_id, $spec_list);
	}
	
	// same as above except for immediate children of the specifed node
	public function isExistingImmediateSpec($node_id, $spec_id, $ignore_id = -1)
	{					
		$spec_list = $this->getImmediateChildSpecList($node_id, $ignore_id);
		 
		return in_array($spec_id, $spec_list);
	}

	// adds a new recipe, creates the initial attribute root node. 
	// false on failure, otherwise the new recipe id
	public function addRecipe($name, $description, $author, &$status)
	{
		// check some params
		
		if(empty($name) || empty($description) || empty($author))
		{
			$status = JSON_RESP_INVALID_ADD_DATA;
			return false;
		}
		
		// create the new record with active status
		
		// this whole mess should be in a transaction so if the recipe
		// succedes and the attribute failes the entire mess should be
		// rolled back.
		try
		{
			$count = Yii::$app->db->createCommand()->insert('{{%recipes}}', 
					[	// insert fields
						'name'=>$name, 
						'description' => $description,
						'author' => $author,
						'active' => 1,
					]
			)->execute();
		}
		catch(Exception $e)
		{
			$status = JSON_RESP_SQL_ERROR;
			return false;
		}
		
		// this is the tricky part. Essentially gets the last autoinc id from an insert on 
		// the current connection, which hopefully is what we just did!
		
		$recipe_id = Yii::$app->db->getSchema()->getLastInsertID();
		
		// now we have the record need to add the attribute with proper data
		// OK, now add it!
 
		try
		{
			$count = Yii::$app->db->createCommand()->insert('{{%attributes}}', 
					[	// insert fields
						'name'=> $name, // use the recipe name for the root node
						'weight' => '',
						'order' => 0,
						'parent_id' => 0,
						'spec_id' => 9999,
						'recipe_id' => $recipe_id,
						'min'=>0,
						'max'=>0,
					]
			)->execute();
		}
		catch(Exception $e)
		{
			$status = JSON_RESP_SQL_ERROR;
			return false;
		}
		
		$status = JSON_RESP_OK;
		return $recipe_id;
	}

	// update data in the recipe
	public function updateRecipe($recipe_id, $name, $description, $author, &$status)
	{
		if(!is_numeric($recipe_id) || empty($name) || empty($description) || empty($author))
		{
			$status = JSON_RESP_INVALID_ADD_DATA;
			return false;
		}

		try
		{
			$count = Yii::$app->db->createCommand()->update('{{%recipes}}', 
						[	// upate fields
							'name'=>$name, 
							'description' => $description,
							'author' => $author,
						],
						['id' => $recipe_id]	// where part
			)->execute();
		}
		catch(Exception $e)
		{
			$status = JSON_RESP_SQL_ERROR;
			return false;
		}
	
		$status = JSON_RESP_OK;
		return ($count == 1)? true : false; // anything other then one is a problem
	}

	// inserts a full new row in to the attributes table
	// this is a simple helper. 
	// returns new record ID if insert OK, false if fail
	
	public function insertTreeRec($rec)
	{
		if(empty($rec))
			return false;
		
		try {

			$count = Yii::$app->db->createCommand()->insert('{{%attributes}}', 
					[	// insert fields
						'name'=> $rec['name'], 
						'weight' => $rec['weight'],
						'order' => $rec['order'],
						'parent_id' => $rec['parent_id'], 
						'spec_id' => $rec['spec_id'], 
						'recipe_id' => $rec['recipe_id'],  
						'min'=> $rec['min'], 
						'max'=> $rec['max'], 
						'active' => $rec['active'],
					]
			)->execute();
		}
		catch(Exception $e)
		{
			return false;
		}
	
		$rec_id = Yii::$app->db->getSchema()->getLastInsertID();

		return ($count == 1)? $rec_id : false;
	}
	
	public function dupeTree($parent_id, $new_parent_id, $new_recipe_id) 
	{
		// get the list of all nodes with this parent id 
		$treedata = (new Query())->select('id, parent_id, name, order, weight, spec_id, min, max, recipe_id, active')->
									from('{{%attributes}}')->
									where(['parent_id' => $parent_id])->	// was, 'active' => '1'
									all();
	
		// add to the list, and call again with any potentials (recursive)
		// if no data recursion stops
		
		foreach($treedata as $row) 
		{
			$row['parent_id'] = $new_parent_id;
			$row['recipe_id'] = $new_recipe_id;
			
			if(($new_id = $this->insertTreeRec($row)) === false)
			{
				continue; // quiet error, will cause loss of nodes (and possible childred) for sure
			}
			
			// make the call if the spec_id == 9999 as that is not a terminal node and need exploration
			
			if($row['spec_id'] == 9999)
			{
				$this->dupeTree($row['id'], $new_id, $new_recipe_id);
			}
		}
		
		return;
	}

	// copy an existing recipe and all data
	public function copyRecipe($recipe_id, &$status)
	{
		if(!is_numeric($recipe_id))
		{
			$status = JSON_RESP_INVALID_COPY_DATA;
			return false;
		}

		if(($recipe = $this->getRecipe($recipe_id)) === false)
		{
			$status = JSON_RESP_INVALID_COPY_DATA;
			return false;
		}

		// now we have the id of the original recipe we want to copy.
		// first create the recipe record in the recipies table, this is
		// a flat one line with name, description and author. All the same
		// except for the name which will be prefixed with 'COPY' or some
		// such thing to differentiate it. Note names don't have to be unique
		// and are currently 32 chars in the db so chop if needed

		$name = $recipe['name'];
		$description = $recipe['description'];
		$author = $recipe['author'];

		// create new name so it's recognizable by the user, make sure it doesnt
		// overflow the field size. May be fine with PDO but better off being nice
		
		if((strlen($name) + strlen(COPY_PREFIX)) > MAX_LEN_COPY_NAME)
			$name = COPY_PREFIX . substr($name, 0, MAX_LEN_COPY_NAME - strlen(COPY_PREFIX)); 
		else
			$name = COPY_PREFIX . $name;
		
		try
		{
			$count = Yii::$app->db->createCommand()->insert('{{%recipes}}', 
						[	// fields
							'name'=>$name, 
							'description' => $description,
							'author' => $author,
						]
			)->execute();
		}
		catch(Exception $e)
		{
			$status = JSON_RESP_SQL_ERROR;
			return false;
		}

		// this magic gets the last inserted record's id (autoinc field)
		$new_recipe_id = Yii::$app->db->getSchema()->getLastInsertID();

		// get the root node of the recipe to be copied
		
		$root_node = $this->getRecipeRootNode($recipe_id, $status);
		if($status != JSON_RESP_OK)
		{
			$status = JSON_RESP_SQL_ERROR;
			return false;
		}

		// save new node with updated recipe id and get it's new record ID 
		$root_node['recipe_id'] = $new_recipe_id; 
		if(($new_parent_id = $this->insertTreeRec($root_node)) === false)
		{
			$status = JSON_RESP_SQL_ERROR;
			return false;
		}
		
		// dupe the tree AFTER the root node
		// TODO: Better error check
		$this->dupeTree($root_node['id'], $new_parent_id, $new_recipe_id);
		
		$status = JSON_RESP_OK;
		return $new_recipe_id;
	}

	// decativte a recipe and that's about all
	// $state = true for active, false is DE-active
	
	public function activateRecipe($recipe_id, $state, &$status)
	{
		if(!is_numeric($recipe_id))
		{
			$status = JSON_RESP_INVALID_DEACTIVATE_DATA;
			return false;
		}
		
		try
		{
			$count = Yii::$app->db->createCommand()->update('{{%recipes}}', 
						[	// upate fields
							'active'=>($state)? 1 : 0, 
						],
						['id' => $recipe_id]	// where part
			)->execute();
		}
		catch(Exception $e)
		{
			$status = JSON_RESP_SQL_ERROR;
			return false;
		}
	
		$status = JSON_RESP_OK;
		return ($count == 1)? true : false; // anything other then one is a problem
	}
	
	// remove a recipe and all it's children. Can cause a lot of damage
	// if used improperly. Likely should not be allowed for most users 
	// returns FALSE on fail, a count (may be 0) on success
	public function removeRecipeAndNodes($recipe_id, &$status)
	{
		// this does not need any fancy recursion since we have all nodes of a specific
		// recipe tagged already with it. Delete the attribute nodes, then the recipe.
		// this would be a good candidate for a transaction (commit/rollback on fail)
		
		try
		{
			$total_count = Yii::$app->db->createCommand()->delete('{{%attributes}}', ['recipe_id' => $recipe_id])->execute();
			$total_count += Yii::$app->db->createCommand()->delete('{{%recipes}}', ['id'=> $recipe_id])->execute();
		}
		catch(Exception $e)
		{
			$status = JSON_RESP_SQL_ERROR;
			return false; // might be a problem
		}
			
		$status = JSON_RESP_SQL_ERROR;
		return $total_count;
	}

    // helper function give recipe_id it returns the root record for the tree
    // Returns a row or false if no match.
    // currently returns databank fields (id, parent_id, name, weight, spec_id, min, max, recipe_id)
    public function getRecipeRootNode($recipe_id, &$status)
    {
		// this query is tricky for one reason in that while the one() option 
		// specifies one record (row) it still will query and pull back internally
		// all matching rows. The limit(1) ensures efficiency. 
		
		$node_data = (new Query())->select('id, parent_id, name, order, weight, spec_id, min, max, recipe_id, active')->
							from('{{%attributes}}')->
							where(['recipe_id' => $recipe_id, 'parent_id' => '0'])->
							limit(1)->
							one();
									
		if($node_data === false)
		{
			$status = JSON_RESP_SQL_ERROR;
			return false;
		}
			
		$status = JSON_RESP_OK;
		return $node_data;
	}
	
    // helper function give unique node_id it returns the record. Not sure
    // if any reason to also require the recipe_id since node_id's are 
    // unique.
    // Returns a row or false if no match.
    // currently returns databank fields (id, parent_id, name, weight, spec_id, min, max, recipe_id)
    // AND a synthesised field to indicate the node type (node_type)
    // node_type - [parent | terminal] (change as needed and use a const)

    public function getRecipeNode($node_id, &$status)
    {
		// this query is tricky for one reason in that while the one() option 
		// specifies one record (row) it still will query and pull back internally
		// all matching rows. The limit(1) ensures efficiency. 
		
		$node_data = (new Query())->select('id, parent_id, name, order, weight, spec_id, min, max, recipe_id')->
							from('{{%attributes}}')->
							where(['id' => $node_id])->
							limit(1)->
							one();
									
		if($node_data === false)
		{
			$status = JSON_RESP_SQL_ERROR;
			return false;
		}
			
		if($node_data['spec_id'] == 9999)	// 9999 is a magic number 
		{
			if($node_data['parent_id'] == 0)
				$node_data['node_type'] = ROOT_NODE;
			else
				$node_data['node_type'] = PARENT_NODE;
		}
		else 
			$node_data['node_type'] = LEAF_NODE;
			
		$status = JSON_RESP_OK;
		return $node_data;
	}
	
	// removes node and all it's children. danger can cause a lot of deletions
	// needs error return codes, might be best to set this as a 
	// transaction so all or nothing
	// returns false if error, otherwise count of deleted nodes
	// NOTE : this should not allow root node to be deleted
	
	public function removeRecipeNode($node_id, &$status)
	{
		$children = [];
		
		$this->getChildList($children, $node_id);
		
		
		if(($del_candidate = $this->getRecipeNode($node_id, $status)) === false)
		{
			$status = JSON_RESP_NOTHING_TO_DELETE;
			return false;
		}
		
		// can never remove the root node of the tree, this can only
		// be done by the remove recipe function
		
		if($del_candidate['parent_id'] == 0)
		{
			$status = JSON_RESP_DELETE_ROOT_NOT_ALLOWED;
			return false;
		}
		
		$children[] = $node_id;	// tricky, now add the parent node at the end of the list
		
		// now delete the nodes in the list
		// their are a couple of strategies, one is to just create an IN() clause
		// the other is to do individual deletes. 
		// Let's try the in() clause first
		
		try
		{
			$count = Yii::$app->db->createCommand()->delete('{{%attributes}}', ['in', 'id', $children])->execute();
		}
		catch(Exception $e)
		{
			$status = JSON_RESP_SQL_ERROR;
			return false;
		}
		
		$status = JSON_RESP_SQL_ERROR;
		return $count;
	}

	// add a new node. Must have all the required data or will return false
	// data is an array of fields that typically match the databank table.
	// Will NOT add a node to a leaf node, must be a parent capable node or error.
	// 
	// returns - 
	// false if can't insert a new rec or
	// the node id of the newly created node (NEED TO FIGURE THIS OUT...)

	public function addRecipeNode($target_id, $spec_id, $name, $weight, $order, $min, $max, &$status)
	{
		$name = trim($name);
	
		if(!is_numeric($weight) || !is_numeric($order) || !is_numeric($target_id)  
				|| !is_numeric($spec_id)  || !is_numeric($min) || !is_numeric($max) || empty($name))
		{
			$status = JSON_RESP_INVALID_ADD_DATA;
			return false;
		}
		// get the parent nodes recipe, it better exist!
		
		if(($parent = $this->getRecipeNode($target_id, $status)) === false)
		{
			$status = JSON_RESP_INVALID_TARGET_NODE;
			return false;
		}	

		$recipe_id = $parent['recipe_id'];	// must be the same recipe as the parent... Think about it
		
		// Make sure we are adding a node to the parent that can accept it
		// I think this is minimally defined as a node with spec_id as '9999'
		
		if($parent['spec_id'] != 9999)		// MAGIC NUMBER MAKE CONSTANT!!! 
		{
			$status = JSON_RESP_INVALID_LEAF_TO_LEAF;
			return false;					// this indicated you are trying to add a node to a leaf
		}
		
		// double check that the actual recipe exists
		
		if($this->getRecipeName($recipe_id) === false)
		{
			$status = JSON_RESP_INVALID_RECIPE_ID;
			return false;
		}
		
		// now triple check that the new node's spec_id does not exist in 
		// the current level or child branches. This has one exception, allow
		// spec_id of 9999 since you can have many nested 9999 in a branch
		
		if($spec_id != 9999)
			if($this->isExistingImmediateSpec($target_id, $spec_id))
			{
				$status = JSON_RESP_DUPE_SPEC_IN_LEVEL;
				return false;
			}
		
		// OK, now add it!
 
		try
		{
			$count = Yii::$app->db->createCommand()->insert('{{%attributes}}', 
					[	// insert fields
						'name'=>$name, 
						'weight' => $weight,
						'order' => $order,
						'parent_id' => $target_id,
						'spec_id' => $spec_id,
						'recipe_id' => $recipe_id,
						'min'=>$min,
						'max'=>$max,
					]
			)->execute();
		}
		catch(Exception $e)
		{
			$status = JSON_RESP_SQL_ERROR;
			return false;
		}

		$status = JSON_RESP_OK;
		return $count;
	}

	// only updates data elements, no linkages
	public function updateRecipeNode($node_id, $spec_id, $name, $weight, $order, $min, $max, &$status)
	{
		$name = trim($name);
	
		if(!is_numeric($weight) || !is_numeric($order) || !is_numeric($node_id)  
				|| !is_numeric($spec_id)  || !is_numeric($min) || !is_numeric($max) || empty($name))
		{
			$status = JSON_RESP_MSG_INVALID_UPDATE_DATA;
			return false;
		}
		
		// get the node. updates get tricky as you CANT change a spec_id if
		// already set to 9999 (MAGIC). This could mess the tree up. So for now
		// spec_id is IGNORED IF CURRENTLY SET TO 9999. Other fields may have implications
		// if 9999, like min/max must be zero, etc...
		
		if(($node = $this->getRecipeNode($node_id, $status)) === false)
		{
			$status = JSON_RESP_INVALID_TARGET_NODE;
			return false;
		}	

		// might bounce if the incoming spec_id != 9999 as a mean thing to do if
		// this is a parent node. Right now just override it and force to keep it's
		// parent state
		
		if($node['spec_id'] == 9999)	// MAGIC NUMBER!!!
		{
			$spec_id = 9999; 	// force it 
								// this is a questions as in the DB it's currently NULL which blows.
								// possibly if min == max it can also be ignored (in martin/sam code)
		}
		
		if($spec_id == 9999)
		{
			// reset anything her that should be on a Parent Node
			$min = $max = 0;
		}
		
		// now triple check that the new node's spec_id does not exist in 
		// the current level. Do not check for parent nodes
		// that is any nodes with spec_id 9999 as their can be many in branches.
		
		if($spec_id != 9999)
			if($this->isExistingImmediateSpec($node['parent_id'], $spec_id, $node_id))
			{
				$status = JSON_RESP_DUPE_SPEC_IN_LEVEL;
				return false;
			}
		
		// OK, now update it! Count is tricky here since if the data is the exact same
		// it will NOT return an count of 1, but rather a count of 0 since nothing was changed
 
		try
		{
			$count = Yii::$app->db->createCommand()->update('{{%attributes}}', 
						[	// upate fields
							'name'=>$name, 
							'weight' => $weight,
							'order' => $order,
							'spec_id' => $spec_id,
							'min'=>$min,
							'max'=>$max,
						],
						['id' => $node_id]	// where part
			)->execute();
		}
		catch(Exception $e)
		{
			$status = JSON_RESP_SQL_ERROR;
			return false;
		}
	
		$status = JSON_RESP_OK;
		return $count; // need to return the inserted record id at some point
	}

	// helper for the move function. This will just update the
	// nodes parent linkage, that's all. False on any error
	// this has no checking, so be sure you call it with correct
	// values
	public function updateRecipeParent($node_id, $new_parent_id)
	{
		try
		{
			Yii::$app->db->createCommand()->update('{{%attributes}}', 
						[	// upate fields
							'parent_id'=>$new_parent_id, 
						],
						['id' => $node_id]	// where part
			)->execute();
		}
		catch(Exception $e)
		{
			return false;
		}

		return true;
	}

	// move a node ($source_id) to another parent ($target_id)
	// initially will only move a $souce node to a target
	// that is a parent type of node. Source nodes that are leafs
	// can't be moved to other leafs. This might change as it may
	// be OK as it might infer a reorder operation. But for now, 
	// lets keep it simple

	public function moveRecipeNode($source_id, $target_id, $position, &$status)
	{
		if(($source_node = $this->getRecipeNode($source_id, $status)) === false)
		{			
			$status = JSON_RESP_INVALID_SOURCE_NODE;
			return false;
		}
					
		if(($target_node = $this->getRecipeNode($target_id, $status)) === false)
		{
			$status = JSON_RESP_INVALID_TARGET_NODE;
			return false;
		}
			
		// OK both nodes exist now some validation
		
		// if node is not 9999 then it's not a potential candidate
		if($target_node['spec_id'] != 9999) // MAGIC NUMBER FOR PARENT NODE TYPE
		{
			$status = JSON_RESP_INVALID_LEAF_TO_LEAF;
			return false;
		}
			
		// can't move the recipe root node 
		if($source_node['parent_id'] == 0)
		{
			$status = JSON_RESP_INVALID_SOURCE_NODE;
			return false;
		}
/*		
		// can't move to the same parent that is already set 
		if($source_node['parent_id'] == $target_id)
		{
			$status = JSON_RESP_INVALID_TARGET_NODE;
			return false;
		}
*/
		// better safe then sorry
		if($source_id == $target_id)
		{
			$status = JSON_RESP_INVALID_TARGET_NODE;
			return false;
		}
	
		// make sure same recipe, can't move out of a recipe
		if($source_node['recipe_id'] != $target_node['recipe_id'])
		{
			$status = JSON_RESP_INVALID_RECIPE_ID;
			return false;
		}
			
		// check to make sure we are not moving a node with the same
		// spec ID to a branch with a pre existing spec that matches. 
		// this is not checked for parent nodes which can nest anywhere
		// except under a child. Also skip the check if node alreay in
		// the list, which would be just a re-order
		
		if($source_node['parent_id'] != $target_id)	// skip check if moving node in same parent 
			if($source_node['spec_id'] != 9999)
				if($this->isExistingImmediateSpec($target_id, $source_node['spec_id']))
				{
					$status = JSON_RESP_DUPE_SPEC_IN_LEVEL;
					return false;
				}
			
		// you can never copy a source to a target
		// where the target is a child of the source! This is a more costly
		
		$child_list = [];
		if($this->getChildList($child_list, $source_id) === false)
		{
			$status = JSON_RESP_INVALID_SOURCE_NODE;
			return false;
		}
			
		// oops, target is an ancestor of the source, no go	
		if(in_array($target_id, $child_list))
		{
			$status = JSON_RESP_INVALID_TARGET_NODE;
			return false;
		}

		
/*
 	$parent		= $this->get_node($parent, array('with_children'=> true, 'with_path' => true));
		$id			= $this->get_node($id, array('with_children'=> true, 'deep_children' => true, 'with_path' => true));

		if(!$parent['children']) {
			$position = 0;
		}
		if($id[$this->options['structure']['parent_id']] == $parent[$this->options['structure']['id']] && $position > $id[$this->options['structure']['position']]) {
			$position ++;
		}
		if($parent['children'] && $position >= count($parent['children'])) {
			$position = count($parent['children']);
		}
		
		if no children in the parent node then set position to 0
		if current node's parent == it's parent AND it's target position > current position then bump insert position++
		if parent has children AND target position >= number of child nodes $position = number of children
 * 
 */
 		
		
		// get list of child nodes (may be a mix of leafs and parents)
		if(($children = $this->getChildNodesOrder($target_id)) === false)
		{
			$status = JSON_RESP_EMPTY_PARENT;
			return false;
		}
		
		// renumber to ensure what ever order was their is set to something nice to work with, ie 10, 20, 30
		$this->renumberNodeList($children);

		// if at the end of a list and node was dropped from other parent
		// JSTree sends the position value as if the node was in the list.
		// so a parent with 5 children when dragging a node from another
		// parent will show a position of 6, however we only have 5 since
		// we have not updated yet. So fix here by just making it the end
		// of the list.


		// Need to scan the list to find the OLD position
		$last_pos = 0;
		foreach($children as $rec)
		{
			if($rec['id'] == $source_id)
				break;
			$last_pos++;
		}

		// If the current position is > the old position JSTree has 
		// already included the node to the count will be off by one
		
		if($position > $last_pos)
			$position++;

		
		// now depending on where the node was dropped fix up the order value
		// this will cause the renumber to correcly position the node
		
		if(!isset($children[$position]))
		{
			// this case is a node that is past the end of the list. 
			// jstree when added an node from another parent will add to 
			// the list and as such the count will be > then the number 
			// of children we know about.
			
			$position = count($children) - 1;
			$new_source_order = $children[$position]['order'] + 1;	// so if node is ordered is 10, will be now 9
		}
		else
		{
			$new_source_order = $children[$position]['order'] - 1;	// so if node is ordered is 10, will be now 9
		}
		
		// now need to add to the list or update if already in the list in the case of
		// a move inside the same parent
		
		$found = false;
		foreach($children as &$rec)
		{
			if($rec['id'] == $source_id)
			{
				$rec['order'] = $new_source_order;
				$found = true;
				break;
			}
		}

		// if not found, append to the list so it gets updated. The means node was from another parent
		if(!$found)
			$children[] = ['id'=>$source_id, 'order' => $new_source_order];
				
		// not super efficient but works fine for small lists
		
		$this->sortNodeListOrder($children); // sort again
		$this->renumberNodeList($children);	// renumber again
		
		// write the set of nodes and new order back to the db
		
		if(!$this->updateNodeListOrder($children))
		{
			$status = JSON_RESP_RENUMBER_ERROR;
			return false;
		}
		
		// Ok, after all that need to update the linkages
		
		if($this->updateRecipeParent($source_id, $target_id) === false)
		{
			$status = JSON_RESP_SQL_ERROR;
			return false;
		}
		// success!
		
		$status = JSON_RESP_OK;
		return true;
	}

	// gets the name of a recipe given it's ID
	// returns the string or false if not found
	// NOTE that the {{%TableName}} sets the table name prefix
	// this setting is in the config/db.php file and just add a line -
	//     'tablePrefix' => 'usen_', or what ever your prefix is ('brpt_')
	// Also this only looks at ACTIVE recipes
	public function getRecipeName($recipe_id)
	{
        $recipe_rec = (new Query())->select('name')->
									 from('{{%recipes}}')->
									 where(['id' => $recipe_id, 'active' => '1'])->
									 limit(1)->
									 one();
									 
		if($recipe_rec === false)
			return false;
			
		return $recipe_rec['name'];
	}

	
	// pulls all nodes for a given recipe. This only pulls
	// nodes with the active flag set to 1. The active flag
	// should NOT be used in this type of data typically 
	
    public function getRecipeTree($recipe_id)
    {
		$jstreedata = (new Query())->select('id, parent_id, name, spec_id')->
									from('{{%attributes}}')->
									where(['recipe_id' => $recipe_id])-> // was 'active' => '1'
									orderBy('order')->
									all();
		if(count($jstreedata) == 0)
			return false;
		
		return $jstreedata;
	}
	
	// send the entire tree in JSTree secondary JSON format
	// this function does not use the same status as the other
	// ajax calls as I'm yet unable to make that work, so for now
	// ship data exacly as JSTree needs
	
	// TBD 
	// add fix mode which will create a NEW NODE. This node will
	// hang off the root and be called '***INVALID-SPECS***'. All invalid
	// nodes (nodes without valid parent) get attached to this. Then
	// can be easily deleted by the recipe editor.
	public function ValidateTree($recipe_id, $quiet = true)
    {

		$jstree = []; 		// empty array for JSON
		$node_list = [];	// keep list of all valid node id's
		$tree_data = [];
		
		$msg = '<strong>Recipe Id : ' . $recipe_id . '</strong><br />';
		
		$a_index    = 0;
		
		if(($recipe_name = $this->getRecipeName($recipe_id)) === false)
			return '<br />Invalid Recipe Id : ' . $recipe_id . '<br />' ;

		if(($jstreedata = $this->getRecipeTree($recipe_id)) !== false)
		{
			foreach ($jstreedata as $row)
			{
				$id = $node_list[] = $jstree[$a_index]['id'] = $row['id'];	// save to the tree and to the list
						
				// the new 'type' sets a NODE type that can be used for icon display.
				// may later be useful for Drag N' Drop					
				
				if ($row['parent_id'] == 0)
				{
					// if the root node then get the recipe name to the root as well
					$jstree[$a_index]['parent'] = '#';	// jstree root is '#'
					$jstree[$a_index]['text'] = $recipe_name;	// just show the recipe name for the root node
					$jstree[$a_index]['type'] = 'root';
					
					$tree_data[$id]['parent'] = '#';
					$tree_data[$id]['text'] = $recipe_name;
					$tree_data[$id]['type'] = 'root';
					
				}
				else
				{
					$jstree[$a_index]['parent'] = $row['parent_id'];
					$jstree[$a_index]['text'] = $row['name'];

					$tree_data[$id]['parent'] = $row['parent_id'];
					$tree_data[$id]['text'] = $row['name'];
	
					// hack to determin node type, set icon type bootstrap glyphicon (see bootstrap)
					
					if($row['spec_id'] != 9999)	// 9999 is a magic number make a constant 
					{
						$jstree[$a_index]['type'] = 'leaf';
						$tree_data[$id]['type'] = 'leaf';
					}
					else
					{
						$jstree[$a_index]['type'] = 'parent';
						$tree_data[$id]['type'] = 'parent';
					}
				}            
			   
				$a_index ++;
			}
			
		}// valid tree
		else
		{
			return '<br />No Tree Data for : ' . $recipe_id . '<br />' ;
		}		

		$found_root = false;
		$empty_name = 0;
		$empty_parent = 0;
		$invalid_parent = 0;
		$missing_parent = 0;
		$invalid_parent_relationship = 0;

		$msg = '<strong>Recipe Name : ' . $recipe_name . '</strong><br />';
		
		foreach($jstree as $node)
		{
			$curr_msg = '';
			
			// check for some obvious errors
			
			if($node['parent'] == '#')
			{
				$found_root = true;
				if(!$quiet)
					$curr_msg .= '<strong>I am grrROOT</strong>, ';
			}
			else
			{
				if(empty($node['parent']))
				{
					$empty_parent++;
					$curr_msg .= '<strong>Empty Parent</strong>, ';
				}

				if(!is_numeric($node['parent']))
				{
					$invalid_parent++;
					$curr_msg .= '<strong>Invalid Parent Data : ' . $node['parent'] . '</strong>, ';
				}

				// don't check root for parent
				
				$parent = $node['parent'];
				
				if(!in_array($parent, $node_list))
				{
					$missing_parent++;
					$curr_msg .= '<strong>Missing Parent Id : ' . $parent . '</strong>, ';
				}
			}
			
			if(trim($node['text']) == '')
			{
				$empty_name++;
				$curr_msg .= '<strong>Empty Name</strong>, ';
			}
			
			// if not the root node, all nodes parent MUST be a parent (spec_id of 9999)
			// must check to see if parent exists before doing this
			
			if($node['type'] != 'root' && isset($tree_data[$node['parent']]))
			{
				// check parent to see if root or parent type
				
				if(!($tree_data[$node['parent']]['type'] == 'parent' || $tree_data[$node['parent']]['type'] == 'root'))
				{
					$invalid_parent_relationship++;
					
					$curr_msg .= '<strong>Parent Node Id : ' . $node['parent'] . ' is not ROOT or a valid Parent</strong>, ';
				}
			}
			
			if(!$quiet || $curr_msg != '')
			{
				$msg .= 'Node Id : ' . $node['id'] . ' ' . $curr_msg;
				$msg .= '<br />';
			}
		
		}
         
		$msg .= '<br />';
		if($found_root == false)
			$msg .= 'ERROR ROOT NODE NOT FOUND<br />';
		
		$msg .= 'Summary -<br />';	
		$msg .= 'Empty Name          : ' . $empty_name . '<br />';
		$msg .= 'Empty Parent        : ' . $empty_parent . '<br />';
		$msg .= 'Invalid Parent Data : ' . $invalid_parent . '<br />';
		$msg .= 'Missing Parents     : ' . $missing_parent . '<br />';
		$msg .= 'Invalid Parent Type : ' . $invalid_parent_relationship . '<br />';
		
		$msg .= '<br />';
        return $msg;
    }
	
	////////////////////////////////////////////////////////////////////
	// ACTIONS BELOW
	////////////////////////////////////////////////////////////////////
	
    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionLogin()
    {
        if (!\Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        } else {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        } else {
            return $this->render('contact', [
                'model' => $model,
            ]);
        }
    }

    public function actionAbout()
    {
        return $this->render('about');
    }
    
    public function actionTreeEdit()
    {
        return $this->render('tree-edit');
    }

    public function actionTree()
    {
		// gets the parameter recipe_id to load that particular recipe
		// these might be better as post, but will have to change the
		// ajax call to send post parameters so the YII to() html helper 
		// may not work for stuffing the tree with data.
		
		if(!isset($_GET['recipe_id']) || !is_numeric($_GET['recipe_id']))
			throw new \yii\web\BadRequestHttpException;

		$recipe_id = $_GET['recipe_id'];

		$jstreejson = []; // empty array for JSON, hopefully this is OK or other error format need to be used
		
		if(($recipe_name = $this->getRecipeName($recipe_id)) !== false)
		{
			if(($jstreedata = $this->getRecipeTree($recipe_id)) !== false)
			{
				$a_index    = 0;
				
				foreach ($jstreedata as $row)
				{
					$jstreejson[$a_index]['id'] = $row['id'];
					
					// the new 'type' sets a NODE type that can be used for icon display.
					// may later be useful for Drag N' Drop					
					
					if ($row['parent_id'] == 0)
					{
						// if the root node then get the recipe name to the root as well
						$jstreejson[$a_index]['parent'] = '#';	// jstree root is '#'
						$jstreejson[$a_index]['text'] = $recipe_name;	// just show the recipe name for the root node
						$jstreejson[$a_index]['type'] = 'root';
					}
					else
					{
						$jstreejson[$a_index]['parent'] = $row['parent_id'];
						$jstreejson[$a_index]['text'] = $row['name'];
		
						// this magic will set the node type in the JSON to be something from BOOTSTRAP!! NICE

						// hack to determin node type, set icon type bootstrap glyphicon (see bootstrap)
						
						if($row['spec_id'] != 9999)	// 9999 is a magic number make a constant or someting more universal like NULL
						{
							//$jstreejson[$a_index]['icon'] = 'glyphicon glyphicon-leaf';	// leaf node
							$jstreejson[$a_index]['type'] = 'leaf';
						}
						else
						{		
							$jstreejson[$a_index]['type'] = 'parent';
							//$jstreejson[$a_index]['icon'] = 'glyphicon glyphicon-eye-open';
						}
					}            
				   
					$a_index ++;
				}
				
			}// valid tree
		}// valid name	
		
        // check here for this magic!
        // http://www.yiiframework.com/doc-2.0/guide-runtime-responses.html
        // Yii will format the data correctly as JSON and ship it with the 
        // correct HTML response type (JSON). 
         
        return \Yii::createObject([
        'class' => 'yii\web\Response',
        'format' => \yii\web\Response::FORMAT_JSON,
        'data' => $jstreejson, 
        ]);
    }
    
	// return a nodes worth of data
	public function actionGetNode()
	{
		if (!Yii::$app->request->isAjax)
			throw new \yii\web\MethodNotAllowedHttpException;
		
		// better exist and be a number
		
		if(!isset($_POST['node_id']) || !is_numeric($_POST['node_id']))
			throw new \yii\web\BadRequestHttpException;

		$node_id = $_POST['node_id'];
		
		if(($node_data = $this->getRecipeNode($node_id, $status)) === false)
			$json_response = formatJSONResponse(JSON_RESP_INVALID_NODE, ['node_id' => $node_id]);
		else
			$json_response = formatJSONResponse(JSON_RESP_OK, $node_data);

	    return \Yii::createObject([
        'class' => 'yii\web\Response',
        'format' => \yii\web\Response::FORMAT_JSON,
        'data' =>  $json_response,
		]);
	}
	
	public function actionRemoveNode()
	{
		if (!Yii::$app->request->isAjax)
			throw new \yii\web\MethodNotAllowedHttpException;
		
		// better exist and be a number
		
		if(!isset($_POST['node_id']) || !is_numeric($_POST['node_id']))
			throw new \yii\web\BadRequestHttpException;

		$node_id = $_POST['node_id'];

		// error data will be consistant with node_id and node_cnt for all cases
		// 3 cases of status, OK, invaid node id, and nothing to delete are returned
		
		if(($count = $this->removeRecipeNode($node_id, $status)) === false)
			$json_response = formatJSONResponse($status, ['node_id' => $node_id, 'node_cnt' => $count]);
		else
		{
			// we had deleted datat it was a success, BUT if we tried to delete a node and it didn't delete then
			// that is an error. Let the client decide what to do on it
			
			if($count > 0)
				$json_response = formatJSONResponse(JSON_RESP_OK, ['node_id' => $node_id, 'node_cnt' => $count]);
			else
				$json_response = formatJSONResponse(JSON_RESP_NOTHING_TO_DELETE, ['node_id' => $node_id, 'node_cnt' => $count]);
		}
		
	    return \Yii::createObject([
        'class' => 'yii\web\Response',
        'format' => \yii\web\Response::FORMAT_JSON,
        'data' => $json_response,
		]);
	}

	// adds a new node given a valid parent ID
	public function actionAddNode()
	{
		if (!Yii::$app->request->isAjax)
			throw new \yii\web\MethodNotAllowedHttpException;
		
		// do some checking
			
		if(!isset($_POST['parent_id']) || !is_numeric($_POST['parent_id']))
			throw new \yii\web\BadRequestHttpException;
		
		if(!isset($_POST['name']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['weight']) || !is_numeric($_POST['weight']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['order']) || !is_numeric($_POST['order']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['spec_id']) || !is_numeric($_POST['spec_id']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['min']) || !is_numeric($_POST['min']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['max']) || !is_numeric($_POST['max']))
			throw new \yii\web\BadRequestHttpException;

		$parent_id = $_POST['parent_id'];

		$count = $this->addRecipeNode($parent_id, $_POST['spec_id'], $_POST['name'], 
			$_POST['weight'], $_POST['order'], $_POST['min'], $_POST['max'], $status);

		if($count === false || $count != 1)
			$json_response = formatJSONResponse($status, ['node_id' => $parent_id, 'node_cnt' => $count]);
		else
			$json_response = formatJSONResponse(JSON_RESP_OK, ['node_id' => $parent_id]);
						
	    return \Yii::createObject([
        'class' => 'yii\web\Response',
        'format' => \yii\web\Response::FORMAT_JSON,
        'data' => $json_response,
		]);
	}
	
	// update a node, needs all the db fields in question, will
	// watch out for updating a node if the spec_id is 9999
		
	public function actionUpdateNode()
	{
		if (!Yii::$app->request->isAjax)
			throw new \yii\web\MethodNotAllowedHttpException;
		
		// do some checking
			
		if(!isset($_POST['node_id']) || !is_numeric($_POST['node_id']))
			throw new \yii\web\BadRequestHttpException;
		
		if(!isset($_POST['name']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['weight']) || !is_numeric($_POST['weight']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['order']) || !is_numeric($_POST['order']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['spec_id']) || !is_numeric($_POST['spec_id']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['min']) || !is_numeric($_POST['min']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['max']) || !is_numeric($_POST['max']))
			throw new \yii\web\BadRequestHttpException;

		$node_id = $_POST['node_id'];
		
		$count = $this->updateRecipeNode($node_id, $_POST['spec_id'], $_POST['name'], 
				$_POST['weight'], $_POST['order'], $_POST['min'], $_POST['max'], $status);
						
		if($count === false)
			$json_response = formatJSONResponse($status, ['node_id' => $node_id, 'node_cnt' => $count]);
		else
		{
			if($count == 0 || $count == 1)	// allow for the count of 0 or 1 both are OK. A count of 0 likely indicates NO data was changed.
				$json_response = formatJSONResponse(JSON_RESP_OK, ['node_id' => $node_id, 'node_cnt' => $count]);
			else
				$json_response = formatJSONResponse(JSON_RESP_UPDATE_COUNT_INCONSISTENT, ['node_id' => $node_id, 'node_cnt' => $count]);
		}

	    return \Yii::createObject([
        'class' => 'yii\web\Response',
        'format' => \yii\web\Response::FORMAT_JSON,
        'data' => $json_response,
		]); 
	}

	// move nodes
	
	public function actionMoveNode()
	{
		if (!Yii::$app->request->isAjax)
			throw new \yii\web\MethodNotAllowedHttpException;
		
		// do some checking
			
		if(!isset($_POST['source_id']) || !is_numeric($_POST['source_id']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['target_id']) || !is_numeric($_POST['target_id']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['position']) || !is_numeric($_POST['position']))
			throw new \yii\web\BadRequestHttpException;

		$source_id = $_POST['source_id'];
		$target_id = $_POST['target_id'];
		$position = $_POST['position'];
		
		if($this->moveRecipeNode($source_id, $target_id, $position, $status) === false)
			$json_response = formatJSONResponse($status, ['source_id' => $source_id, 'target_id' => $target_id, 'position'=>$position]);
		else
			$json_response = formatJSONResponse(JSON_RESP_OK, ['source_id' => $source_id, 'target_id' => $target_id, 'position'=>$position]);

	    return \Yii::createObject([
        'class' => 'yii\web\Response',
        'format' => \yii\web\Response::FORMAT_JSON,
        'data' => $json_response,
		]);
	}

	// returns a list of recipies (HTML list format not JSON)
	public function actionGetRecipes()
	{
		if (!Yii::$app->request->isAjax)
			throw new \yii\web\MethodNotAllowedHttpException;
			
		$recipes = $this->getRecipes();
		
		if(count($recipes) == 0)
			return '<option value=0" selected>No Recipies Available</option>';
		else
			$list = '<option value="">--Select Recipe--</option>';	// the prompt
		
		// format the list as options for the drop down list box
		foreach ($recipes as $key=>$value) 
		{
			$list .= Html::tag('option', Html::encode($value), ['value' => $key]);
		}

		return $list;
	}

	// returns a SINGLE recipe (JSON)
	public function actionGetRecipe()
	{
		if (!Yii::$app->request->isAjax)
			throw new \yii\web\MethodNotAllowedHttpException;
			
		if(!isset($_POST['recipe_id']) || !is_numeric($_POST['recipe_id']))
			throw new \yii\web\BadRequestHttpException;

		$recipe_id = $_POST['recipe_id'];
	
		if(($recipe_data = $this->getRecipe($recipe_id)) === false)
			$json_response = formatJSONResponse(JSON_RESP_INVALID_RECIPE_ID, ['recipe_id' => $recipe_id]);
		else
			$json_response = formatJSONResponse(JSON_RESP_OK, $recipe_data);

	    return \Yii::createObject([
        'class' => 'yii\web\Response',
        'format' => \yii\web\Response::FORMAT_JSON,
        'data' =>  $json_response,
		]);
	}

	// adds a new recipe and all that it entails
	public function actionAddRecipe()
	{
		if (!Yii::$app->request->isAjax)
			throw new \yii\web\MethodNotAllowedHttpException;
		
		// do some checking
			
		if(!isset($_POST['name']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['description']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['author']))
			throw new \yii\web\BadRequestHttpException;

		if(($recipe_id = $this->addRecipe(trim($_POST['name']), trim($_POST['description']), trim($_POST['author']), $status)) === false)
			$json_response = formatJSONResponse($status, []);
		else
			$json_response = formatJSONResponse(JSON_RESP_OK, ['recipe_id' => $recipe_id]);

	    return \Yii::createObject([
        'class' => 'yii\web\Response',
        'format' => \yii\web\Response::FORMAT_JSON,
        'data' => $json_response,
		]); 
	}
	
	// updates a recipe and all that it entails
	public function actionUpdateRecipe()
	{
		if (!Yii::$app->request->isAjax)
			throw new \yii\web\MethodNotAllowedHttpException;
		
		// do some checking

		if(!isset($_POST['recipe_id']) || !is_numeric($_POST['recipe_id']))
			throw new \yii\web\BadRequestHttpException;
			
		if(!isset($_POST['name']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['description']))
			throw new \yii\web\BadRequestHttpException;

		if(!isset($_POST['author']))
			throw new \yii\web\BadRequestHttpException;

		$recipe_id = $_POST['recipe_id'];
		
		if($this->updateRecipe($recipe_id, trim($_POST['name']), trim($_POST['description']), trim($_POST['author']), $status) === false)
			$json_response = formatJSONResponse($status, ['recipe_id' => $recipe_id]);
		else
			$json_response = formatJSONResponse(JSON_RESP_OK, ['recipe_id' => $recipe_id]);

	    return \Yii::createObject([
        'class' => 'yii\web\Response',
        'format' => \yii\web\Response::FORMAT_JSON,
        'data' => $json_response,
		]); 
	}	
	
	// Copy a recipe and all that it entails
	public function actionCopyRecipe()
	{
		if (!Yii::$app->request->isAjax)
			throw new \yii\web\MethodNotAllowedHttpException;
		
		// do some checking

		if(!isset($_POST['recipe_id']) || !is_numeric($_POST['recipe_id'])) // recipe id to copy
			throw new \yii\web\BadRequestHttpException;
			
		$recipe_id = $_POST['recipe_id'];

		// shold pass back the NEW recipie if successful otherwise error so the front end doesn't do anything.
		
		if(($new_recipe_id = $this->copyRecipe($recipe_id, $status)) === false)
			$json_response = formatJSONResponse($status, ['recipe_id' => $recipe_id]);
		else
			$json_response = formatJSONResponse(JSON_RESP_OK, ['recipe_id' => $new_recipe_id]);	// send the new recipe back for loading
		
	    return \Yii::createObject([
        'class' => 'yii\web\Response',
        'format' => \yii\web\Response::FORMAT_JSON,
        'data' => $json_response,
		]); 
	}	

	// Deactivates a recipe, to the user it maps to the delete action since
	// we don't want the user to actually delete it.
	public function actionDeleteRecipe()
	{
		if (!Yii::$app->request->isAjax)
			throw new \yii\web\MethodNotAllowedHttpException;
		
		// do some checking

		if(!isset($_POST['recipe_id']) || !is_numeric($_POST['recipe_id'])) // recipe id to copy
			throw new \yii\web\BadRequestHttpException;
			
		$recipe_id = $_POST['recipe_id'];

		// Deactivate the recipe
		
		if($this->activateRecipe($recipe_id, false, $status) === false)
			$json_response = formatJSONResponse($status, ['recipe_id' => $recipe_id]);
		else
			$json_response = formatJSONResponse(JSON_RESP_OK, ['recipe_id' => $recipe_id]);
		
	    return \Yii::createObject([
        'class' => 'yii\web\Response',
        'format' => \yii\web\Response::FORMAT_JSON,
        'data' => $json_response,
		]); 
	}	
}
