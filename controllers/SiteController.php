<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\db\Query;
use yii\filters\Cors;
use app\models\LoginForm;
use app\models\ContactForm;

const ROOT_NODE = 'root';
const PARENT_NODE = 'parent';
const LEAF_NODE = 'leaf';

// JSON Error codes and messages 
const JSON_RESP_OK = 0;
const JSON_RESP_MSG_OK = 'OK';
const JSON_RESP_INVALID_NODE = 1;
const JSON_RESP_MSG_INVALID_NODE = 'Invalid Node Id';
const JSON_RESP_NOTHING_TO_DELETE = 2;
const JSON_RESP_MSG_NOTHING_TO_DELETE = 'Nothing To Delete';
const JSON_RESP_INVALID_UPDATE_DATA = 3;
const JSON_RESP_MSG_INVALID_UPDATE_DATA = 'Invalid Update Data';
const JSON_RESP_UPDATE_COUNT_INCONSISTENT = 4;
const JSON_RESP_MSG_UPDATE_COUNT_INCONSISTENT = 'Update Count Inconsistent, should be 1, but it\'s more. Internal Error';
const JSON_RESP_INVALID_ADD_DATA = 5;
const JSON_RESP_MSG_INVALID_ADD_DATA = 'Invalid Add Data';
const JSON_RESP_INVALID_MOVE_DATA = 6;
const JSON_RESP_MSG_INVALID_MOVE_DATA = 'Invalid Move Data';


// here is the format for the response. This is what the 'other side' will
// see when they get the data back. The status and messages are as above,
// Data will be empty array otherwise some data that might be useful to the 
// client. Note any PHP structs to JSON structs conversion issues will apply 
// when the object is shipped and internally converted by json_encode()
//
// ['status' => JSON_RESP_OK, 'msg'=>JSON_RESP_MSG_OK, 'data' => []];

// simple helper to format the json response data
// $data is an array, others are scalar values
function formatJSONResponse($status, $msg, $data)
{
	$resp = [];
	
	$resp['status'] = $status;
	$resp['msg'] = $msg;
	$resp['data'] = $data;
	
	return $resp;
}

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
    
    // returns a list of the specs. Will return only active
    // format is as an array of records
    public function getSpecs()
    {
		$specs = (new Query())->select('id, spec_name')->
									from('{{%specs}}')->
									orderBy('spec_name')->
									all();
		return $specs;
	}

	// given a parent node, return a list of all children. This 
	// has no assumed order of nodes in the list
	//
	// $child_list must be an initialized
	// $parent_id is the parent node you are looking for it' children, and parent_id is not in the result set
	
	public function getChildList(&$child_list, $parent_id) 
	{
		// get the list of all nodes with this parent id
		$treedata = (new Query())->select('id, spec_id, parent_id')->
									from('{{%attributes}}')->
									where(['parent_id' => $parent_id])->
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

	// remove a recipe and all it's children. Can cause a lot of damage
	// if used improperly 
	// returns FALSE on fail, a count (may be 0) on success
	public function removeRecipeAndNodes($recipe_id)
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
			return false; // might be a problem
		}

		return $total_count;
	}
	
	// removes node and all it's children. danger can cause a lot of deletions
	// needs error return codes, might be best to set this as a 
	// transaction so all or nothing
	// returns false if error, otherwise count of deleted nodes
	
	public function removeRecipeNode($node_id)
	{
		$children = [];
		
		$this->getChildList($children, $node_id);
		
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
			return false;
		}
		
		return $count;
	}

	// add a new node. Must have all the required data or will return false
	// data is an array of fields that typically match the databank table.
	// Will NOT add a node to a leaf node, must be a parent capable node or error.
	// 
	// returns - 
	// false if can't insert a new rec or
	// the node id of the newly created node (NEED TO FIGURE THIS OUT...)

	public function addRecipeNode($target_id, $spec_id, $name, $weight, $order, $min, $max)
	{
		$name = trim($name);
	
		if(!is_numeric($weight) || !is_numeric($order) || !is_numeric($target_id)  
				|| !is_numeric($spec_id)  || !is_numeric($min) || !is_numeric($max))
			return false;
	
		// get the parent nodes recipe, it better exist!
		
		if(($parent = $this->getRecipeNode($target_id)) === false)
			return false;

		$recipe_id = $parent['recipe_id'];	// must be the same recipe as the parent... Think about it
		
		// Make sure we are adding a node to the parent that can accept it
		// I think this is minimally defined as a node with spec_id as '9999'
		
		if($parent['spec_id'] != 9999)		// MAGIC NUMBER MAKE CONSTANT!!! 
			return false;					// this indicated you are trying to add a node to a leaf

		// double check that the recipe exists
		
		if($this->getRecipeName($recipe_id) === false)
			return false;
		
		// now triple check that the new node's spec_id does not exist in 
		// the current level or child branches. This has one exception, allow
		// spec_id of 9999 since you can have many nested 9999 in a branch
		
		if($spec_id != 9999)
			if($this->isExistingImmediateSpec($target_id, $spec_id))
				return false;	
		
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
			return false;
		}

		return $count;
	}

	// only updates data elements, no linkages
	public function updateRecipeNode($node_id, $spec_id, $name, $weight, $order, $min, $max)
	{
		$name = trim($name);
	
		if(!is_numeric($weight) || !is_numeric($order) || !is_numeric($node_id)  
				|| !is_numeric($spec_id)  || !is_numeric($min) || !is_numeric($max))
			return false;
	
		// get the node. updates get tricky as you CANT change a spec_id if
		// already set to 9999 (MAGIC). This could mess the tree up. So for now
		// spec_id is IGNORED IF CURRENTLY SET TO 9999. Other fields may have implications
		// if 9999, like min/max must be zero, etc...
		
		if(($node = $this->getRecipeNode($node_id)) === false)
			return false;

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
				return false;	
		
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
			return false;
		}
	
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
			$count = Yii::$app->db->createCommand()->update('{{%attributes}}', 
						[	// upate fields
							'parent_id'=>$new_parent_id, 
						],
						['id' => $node_id]	// where part
			)->execute();
			
			// better update one record. It's also an error IF the record is not updated
			// which can happen if the new_parent_id is already the same.
			
			if($count != 1)
				return false;
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
	public function moveRecipeNode($source_id, $target_id)
	{
		if(($source_node = $this->getRecipeNode($source_id)) === false)
			return false;

		if(($target_node = $this->getRecipeNode($target_id)) === false)
			return false;
			
		// OK both nodes exist now some validation
		
		// if node is not 9999 then it's not a potential candidate
		if($target_node['spec_id'] != 9999) // MAGIC NUMBER FOR PARENT NODE TYPE
			return false;
			
		// can't move the recipe root node 
		if($source_node['parent_id'] == 0)
			return false;
			
		// can't move to the same parent that is already set 
		if($source_node['parent_id'] == $target_id)
			return false;

		// better safe then sorry
		if($source_id == $target_id)
			return false;

		// make sure same recipe, can't move out of a recipe
		if($source_node['recipe_id'] != $target_node['recipe_id'])
			return false;
			
///////
//////////////
///////// working here
//////////////
//////////////
//////////////
//////////////
//////////////
///////			
// need to check for current level node existing, if 9999 skip, if not do the check			
			
		// This is the last check, you can never copy a source to a target
		// where the target is a child of the source! This is a more costly
		// check...
		
		$child_list = [];
		if($this->getChildList($child_list, $source_id) === false)
			return false;
			
		// oops, target is an ancestor of the source, no go	
		if(in_array($target_id, $child_list))
			return false;
			
		// Ok, now just change the paret of the source to the target
		
		if($this->updateRecipeParent($source_id, $target_id) === false)
			return false;
			
		// success!
		
		return true;
	}

	// gets the name of a recipe given it's ID
	// returns the string or false if not found
	// NOTE that the {{%TableName}} sets the table name prefix
	// this setting is in the config/db.php file and just add a line -
	//     'tablePrefix' => 'usen_', or what ever your prefix is ('brpt_')
	public function getRecipeName($recipe_id)
	{
        $recipe_rec = (new Query())->select('id, name')->
									 from('{{%recipes}}')->
									 where(['id' => $recipe_id])->
									 limit(1)->
									 one();
									 
		if($recipe_rec === false)
			return false;
			
		return $recipe_rec['name'];
	}


	// need spec_id as it hints at node type (leaf/parent)
	
    public function getRecipeTree($recipe_id)
    {
		$jstreedata = (new Query())->select('id, parent_id, name, spec_id')->
									from('{{%attributes}}')->
									where(['recipe_id' => $recipe_id])->
									orderBy('spec_id')->
									all();
		if(count($jstreedata) == 0)
			return false;
		
		return $jstreedata;
	}
	
	// send the entire tree in JSTree secondary JSON format
	// this function does not use the same status as the other
	// ajax calls as I'm yet unable to make that work, so for now
	// ship data exacly as JSTree needs
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

			// ---------------------------------------------------------------------
			// -- Get tree data from databank table brpt_attributes
			// -- order by tree_id
			// ---------------------------------------------------------------------
			
			if(($jstreedata = $this->getRecipeTree($recipe_id)) !== false)
			{
				// ---------------------------------------------------------------------
				// -- Transform data to JSON format, replacing parent_id = 0 (top nodes)
				// -- by "#"
				// ---------------------------------------------------------------------
				
				$jstreejson = [];
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
    
    
    // helper function give unique node_id it returns the record. Not sure
    // if any reason to also  require the recipe_id since node_id's are 
    // unique.
    // Returns a row or false if no match.
    // currently returns databank fields (id, parent_id, name, weight, spec_id, min, max, recipe_id)
    // AND a synthesised field to indicate the node type (node_type)
    // node_type - [parent | terminal] (change as needed and use a const)

    public function getRecipeNode($node_id)
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
			return false;
			
		if($node_data['spec_id'] == 9999)	// 9999 is a magic number make a constant or someting more universal like NULL
			$node_data['node_type'] = PARENT_NODE;
		else 
			$node_data['node_type'] = LEAF_NODE;
			
		return $node_data;
	}
	
	// return a nodes worth of data
	public function actionNode()
	{
		if (!Yii::$app->request->isAjax)
			throw new \yii\web\MethodNotAllowedHttpException;
		
		// better exist and be a number
		
		if(!isset($_POST['node_id']) || !is_numeric($_POST['node_id']))
			throw new \yii\web\BadRequestHttpException;

		$node_id = $_POST['node_id'];
		
		if(($node_data = $this->getRecipeNode($node_id)) === false)
			$json_response = formatJSONResponse(JSON_RESP_INVALID_NODE, JSON_RESP_MSG_INVALID_NODE, ['node_id' => $node_id]);
		else
			$json_response = formatJSONResponse(JSON_RESP_OK, JSON_RESP_MSG_OK, $node_data);

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
		
		if(($count = $this->removeRecipeNode($node_id)) === false)
			$json_response = formatJSONResponse(JSON_RESP_INVALID_NODE, JSON_RESP_MSG_INVALID_NODE, ['node_id' => $node_id, 'node_cnt' => $count]);
		else
		{
			// we had deleted datat it was a success, BUT if we tried to delete a node and it didn't delete then
			// that is an error. Let the client decide what to do on it
			
			if($count > 0)
				$json_response = formatJSONResponse(JSON_RESP_OK, JSON_RESP_MSG_OK, ['node_id' => $node_id, 'node_cnt' => $count]);
			else
				$json_response = formatJSONResponse(JSON_RESP_NOTHING_TO_DELETE, JSON_RESP_MSG_NOTHING_TO_DELETE, ['node_id' => $node_id, 'node_cnt' => $count]);
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

		$count = $this->addRecipeNode($parent_id, $_POST['spec_id'], 
						$_POST['name'], $_POST['weight'], $_POST['order'], $_POST['min'], $_POST['max']);

		if($count === false || $count != 1)
			$json_response = formatJSONResponse(JSON_RESP_INVALID_ADD_DATA, JSON_RESP_MSG_INVALID_ADD_DATA, ['node_id' => $parent_id, 'node_cnt' => $count]);
		else
		{
			$json_response = formatJSONResponse(JSON_RESP_OK, JSON_RESP_MSG_OK, ['node_id' => $parent_id, 'node_cnt' => $count]);
		}
						
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
		
		$count = $this->updateRecipeNode($node_id, $_POST['spec_id'], 
						$_POST['name'], $_POST['weight'], $_POST['order'], $_POST['min'], $_POST['max']);
						
		if($count === false)
			$json_response = formatJSONResponse(JSON_RESP_INVALID_UPDATE_DATA, JSON_RESP_MSG_INVALID_UPDATE_DATA, ['node_id' => $node_id, 'node_cnt' => $count]);
		else
		{
			// we had deleted datat it was a success, BUT if we tried to delete a node and it didn't delete then
			// that is an error. Let the client decide what to do on it, could should always be one here
			
			if($count == 0 || $count == 1)	// allow for the count of 0 or 1 both are OK. A count of 0 likely indicates NO data was changed.
				$json_response = formatJSONResponse(JSON_RESP_OK, JSON_RESP_MSG_OK, ['node_id' => $node_id, 'node_cnt' => $count]);
			else
				$json_response = formatJSONResponse(JSON_RESP_UPDATE_COUNT_INCONSISTENT, JSON_RESP_MSG_UPDATE_COUNT_INCONSISTENT, ['node_id' => $node_id, 'node_cnt' => $count]);
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

		$source_id = $_POST['source_id'];
		$target_id = $_POST['target_id'];
		
		if($this->moveRecipeNode($source_id, $target_id) === false)
			$json_response = formatJSONResponse(JSON_RESP_INVALID_MOVE_DATA, JSON_RESP_MSG_INVALID_MOVE_DATA, ['source_id' => $source_id, 'target_id' => $target_id]);
		else
			$json_response = formatJSONResponse(JSON_RESP_OK, JSON_RESP_MSG_OK, ['source_id' => $source_id, 'target_id' => $target_id]);

	    return \Yii::createObject([
        'class' => 'yii\web\Response',
        'format' => \yii\web\Response::FORMAT_JSON,
        'data' => $json_response,
		]);
	}
	
}
