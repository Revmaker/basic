<?php
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\web\view;
use yii\web\JsExpression;
use yii\helpers\VarDumper;

use yii\helpers\Url;

/* @var $this yii\web\View */
$this->title = 'Recipe Editor';
$this->params['breadcrumbs'][] = $this->title;
?>

 <style> 
	.recipe_select_panel, .recipe_add_panel{
		font-size:16px; 
		border-style: solid;
		border-radius : 10px;
		padding :10px;
		margin-bottom: 5px;
		margin-right : 10px;
	}

	.recipe_add_panel {
		margin-right : 0px;
		margin-left  : 0px;
	}
	
	#recipe_list {
		width : 80%;
	}
	
	.tree-panel { 
		font-size:14px; 
		border-style: solid;
		border-radius : 10px 0 0 10px;
		padding :10px;
		height:600px;
		margin-right : 10px;
		overflow-y : auto;
	}

	.edit-panel {
		font-size:14px; 
		border-style: solid;
		border-radius : 0 10px 10px  0;
		padding :10px;
		height:600px;
		/*margin-left : 10px;*/
	}

	.row {
		display: flex; /* equal height of the children */
	}

	
	/* removes the top dots on the root node that look bad */
	
	.jstreeview .jstree-container-ul > .jstree-open > .jstree-ocl { background-position: -32px 0px;}
	.jstreeview .jstree-container-ul > .jstree-closed> .jstree-ocl { background-position: 0px 0px; }
	.jstreeview .jstree-container-ul > .jstree-leaf> .jstree-ocl { background:transparent; }

</style>
   

<div class="site-about">
	<h1><?= Html::encode($this->title) ?></h1>

<?php /* echo \yii2mod\yii2-sweet-alert\Alert::widget([
          'useSessionFlash' => false,
          'options' => [
               'title' => 'Success message',
               'type' => 'Success',
               'text' => "You will not be able to recover this imaginary file!",
               'confirmButtonText'  => "Yes, delete it!",   
               'cancelButtonText' =>  "No, cancel plx!"
          ]
]);*/
?>
	<div class="row">
		<div class="recipe_select_panel col-sm-4">
			<?php
				$specs = $this->context->getRecipes();

				echo Html::dropDownList('recipe_list', '', ArrayHelper::map($specs, 'id', 'name'), 
								['id'=>'recipe_list',
								'prompt' => '--Select Recipe--',
								]); 
			?>

			<button id="new-recipe" class="btn btn-primary">New</button>
		</div>
		<div class="recipe_add_panel col-sm-8">
			<div class="form-inline">
			  <div class="form-group">
				<label for="recipe_name">Recipe Name</label>
				<input type="text" class="form-control" id="recipe_name" placeholder="Some Name Here">
			  </div>
			  <div class="form-group">
				<label for="recipe_author">Author</label>
				<input type="text" class="form-control" id="recipe_author" placeholder="Haystack Calhoon">
			  </div>
			  <button id="save-recipe" class="btn btn-primary">Save</button>
			  <button id="cancel-recipe" class="btn btn-warning">Cancel</button>
			</div>				
		</div>
	</div>
<div class="row">
	<div class="tree-panel col-sm-7">

	<?= \yiidreamteam\jstree\JsTree::widget([
		'containerOptions' => [
			'class' => 'jstreeview',	// sets the the div's CLASS
			'id' => 'treeview',		// sets the div's ID
		],
		'jsOptions' => [
			'core' => [
				'check_callback' => true,	// needs to be true for DND
				'multiple' => false,	// allow multiple selections, set to false to allow only single select

				'themes' => [
					'variant' => 'large',	// makes tree bigger
					 'stripes' => true,		// green bar effect like old computer paper
				],
			],
			'types' =>[
				'default' => ['icon' => 'glyphicon glyphicon-flash'],
				'parent' => ['icon' => 'glyphicon glyphicon-eye-open'],
				'leaf' => ['icon' => 'glyphicon glyphicon-leaf'],
				'root' => ['icon' => 'glyphicon glyphicon-folder-open']
			],
			'plugins' => ['dnd', 'types', 'contextmenu'],
			'dnd' => ['check_while_dragging' => true],
			
			// for context menu you need to put into the component non quoted JS, so
			// you must use the JsExpression, it does the rest.
			'contextmenu' => [
				'items' => new JsExpression('function ($node) {
                return {
                    "Expand": {
                        "label": "Expand Tree",
                        "action": function (obj) {
                            this.ExpandTree(obj);
                        }
                    },
                    "Contract": {
                        "label": "Contract Tree",
                        "action": function (obj) {
                            this.ContractTree(obj);
                        }
                    },
                };
            }'),	// JsExpression
				
			],
		]
	])
	?>
		

	<br />
	<div>
	</div>
	</div> <!-- tree-panel-->
	
	<div class="edit-panel col-sm-5">
	<form class="form-horizontal"> 
	<fieldset>
	<!-- Form Name -->
	<legend id="edit-state">Edit Data Here</legend>

	<div class="control-group">
	  <label class="control-label" for="textinput">Name</label>
	  <div class="controls">
		<input name="name" placeholder="" class="input-xlarge" type="text">
	  </div>
	</div>

	<div class="control-group">
	  <label class="control-label" for="textinput">Weight</label>
	  <div class="controls">
		  
		<?php
			$weights = $this->context->getWeights();
					?>  
		<?= Html::dropDownList('weight_list', '0', ArrayHelper::map($weights, 'id', 'weight'), 
								['id'=>'weight_list', 
								]); 
		?>
	  </div>

	<div class="control-group no_disp_parent">
	  <label class="control-label" for="textinput">Specification</label>
	  <div class="controls">
		  
		<?php
			$specs = $this->context->getSpecs();
					?>  
		<?= Html::dropDownList('spec_list', '', ArrayHelper::map($specs, 'id', 'spec_name'), 
								['id'=>'spec_list',
								]); 
		?>
	  </div>
	</div>
	
	<div class="control-group">
	  <label class="control-label" for="textinput">Order</label>
	  <div class="controls">
		<input name="order" placeholder="" class="input-xlarge" type="text">
	  </div>
	</div>

	<div class="control-group no_disp_parent">
	  <label class="control-label" for="textinput">Min</label>
	  <div class="controls">
		<input name="min" placeholder="" class="input-xlarge" type="text">
	  </div>
	</div>
	
	<div class="control-group no_disp_parent">
	  <label class="control-label" for="textinput">Max</label>
	  <div class="controls">
		<input name="max" placeholder="" class="input-xlarge" type="text">
	  </div>
	</div>
	<br />

	<button id="new-leaf" class="btn btn-primary">New Leaf</button>
	<button id="new-parent" class="btn btn-primary">New Parent</button>
	<button id="edit" class="btn btn-primary">Edit</button>
	<button id="save" class="btn btn-success">Save</button> 
	<button id="remove" class="btn btn-danger">Remove</button> 
	<button id="cancel" class="btn btn-warning">Cancel</button> 
	
	</fieldset>
	</form>
	</div> <!-- edit-panel-->
</div> <!-- row-->

<?php
// this block will be how you put in JS into YII
// check out <<< and other heredoc in 'string' options for stuffing data into a php var
// NOTE that this should be placed at POS_END as most of the JQuery and
// other javascript by default are loaded at the end in YII. Inline
// <script> tags that reference JQuery may not work since JQuery is loaded
// last.

// Generated URL's for each action's Ajax Call
$ajax_url['node'] = Url::to(['site/get-node']);
$ajax_url['add'] = Url::to(['site/add-node']);
$ajax_url['remove'] = Url::to(['site/remove-node']);
$ajax_url['update'] = Url::to(['site/update-node']);
$ajax_url['move'] = Url::to(['site/move-node']);
$ajax_url['tree'] = Url::to(['site/tree']); // this is not a post, append the tree ID to the end of this URL '$recipe_id=1234'

$script = <<< JS

var gEditState = 'inactive';	// edit state new, update, browse
var gEditType = 'inactive';		// current edit type, leaf or other 
var gCurrParent = -1; 			// invalid for start
var gCurrNode = -1; 			// invalid for start
var gCurrType = '';				// invalid for start

$(document).ready(function() {
	
	// do some set up to get initial state of buttons, etc

	$("#recipe_list").val(""); // set to the prompt

	clearEdits();
	setEditState('inactive');
});

// expand the tree by default on inital page open

$('#treeview').on('loaded.jstree', function (event, data) {
	$(this).jstree("open_all");
});	

// expand the tree on any refresh due to a reload by url change
$('#treeview').bind('refresh.jstree', function(e, data) {
    // invoked after jstree has loaded
    $(this).jstree("open_all");
});

// check for integer only number
function isIntNum(str)
{
	var intregx = /(^([-]?[0-9]+))$/;  
	return intregx.test(str);
}

// see if a valid float
function isFloatNum(str)   
{   
	var decimal = /(^([-]?\.?[0-9]+)|^([-]?[0-9]+\.[0-9]+)|^([-]?[0-9]+))$/;  
	return decimal.test(str);
}  

function clearEdits()
{
	$("input[name=name]").val('');					
	$("input[name=order]").val('');			
	$("input[name=min]").val('');			
	$("input[name=max]").val('');		
	
	$("#spec_list").val('9999');
	$("#weight_list").val('');	
}

// show either full edits for leaf or subset for parent/root
function setEditFields(type)
{
	if(type == 'leaf')
	{
		$(".no_disp_parent").show();			
	}
	else
	{
		// root or parent nodes here
		$(".no_disp_parent").hide();			
	}
	
	gEditType = type;

}

function setReadOnly(state)
{
	//input fields
	$("input[name=name]").prop('readonly', state);
	$("input[name=order]").prop('readonly', state);
	$("input[name=min]").prop('readonly', state);
	$("input[name=max]").prop('readonly', state);
	
	// drop down lists
	$("#spec_list").attr("disabled", state); 
	$("#weight_list").attr("disabled", state); 
}

// used to set state of the interface
function setEditState(new_state)
{
	if(new_state == 'new')
	{
		gEditState = 'new';
	}
	else
		if(new_state == 'update')
		{
			gEditState = 'update';
		}
		else
			if(new_state == 'browse')
			{
				gEditState = 'browse';	// all other cases to browse
			}
			else
				gEditState = 'inactive';	// new load or unknown state
			
		
		setButtonState(gEditState);	// update button to new state
}

function setButtonState(state)
{
	if(state == 'update' || state == 'new')
	{
		$("#edit-state").html((state == 'new')? "Add Mode" : "Edit Mode");
		
		setReadOnly(false);

		$("#remove").prop("disabled",true);
		$("#remove").hide();
		
		$("#new-leaf").prop("disabled", true);
		$("#new-leaf").hide();
		$("#new-parent").prop("disabled", true);
		$("#new-parent").hide();

		$("#edit").prop("disabled", true);
		$("#edit").hide();

		$("#save").prop("disabled",false);
		$("#save").show();
		$("#cancel").prop("disabled",false);
		$("#cancel").show();
		
	}
	else 
		if(state == 'browse') // browse mode
		{
			setReadOnly(true);
			$("#edit-state").html("Browse Mode");

			$("#remove").prop("disabled",false);
			$("#remove").show();

			$("#new-leaf").prop("disabled", false);
			$("#new-leaf").show();
			$("#new-parent").prop("disabled", false);
			$("#new-parent").show();

			$("#edit").prop("disabled", false);
			$("#edit").show();

			$("#save").prop("disabled",true);
			$("#save").hide();
			$("#cancel").prop("disabled",true);
			$("#cancel").hide();
		}
		else
		{
			setReadOnly(true);
			$("#edit-state").html("Load Recipe");

			$("#remove").hide();
			$("#new-leaf").hide();
			$("#new-parent").hide();
			$("#edit").hide();
			$("#save").hide();
			$("#cancel").hide();
		}
}

$('#recipe_list').on('change', function(event)
{
	id = $("#recipe_list").val();
	
	if(id == "")
	{
		$('#treeview').jstree(true).settings.core.data = ''; 	
		$('#treeview').jstree(true).refresh();
		return;
	}
	
	url = '{$ajax_url['tree']}' + '&recipe_id=' + id;
	
	$('#treeview').jstree(true).settings.core.data = {'url' : url}; 	
	$('#treeview').jstree(true).refresh();
});

function ExpandTree(obj)
{
	$('#treeview').jstree('open_all');
}

function ContractTree(obj)
{
	$('#treeview').jstree('close_all');
	$('#treeview').jstree('deselect_all');
}

// given a tree id, get the json object for it
function getNodeById(id)
{
	return $('#treeview').jstree(true).get_node(id);
}

// event handlers for buttons

$('#new-recipe').on('click',function(event)
{
	alert('Nice Try');
});

$('#save-recipe').on('click',function(event)
{
	alert('Wishful Thinking');
});

$('#cancel-recipe').on('click',function(event)
{
	alert('Hold Your Horses...');
});

$('#new-leaf').on('click',function(event)
{
	event.preventDefault(); 

	if(gCurrType == 'leaf')
	{
		alert('Please Select a Parent Node as a target');
		return;
	}
	
	if(gCurrParent == -1)
	{
		alert('no parent selected selected, please select a node in the tree')
		return;
	}

	setEditFields('leaf');
	clearEdits();
	setEditState('new');
	
	// set some defaults
	
	$("#weight_list").val('0');
	$("input[name=order]").val('10');
	$("input[name=min]").val('0');
	$("input[name=max]").val('0');

});

$('#new-parent').on('click',function(event)
{
	event.preventDefault(); 

	if(gCurrType == 'leaf')
	{
		alert('Please Select a Parent Node as a target');
		return;
	}

	if(gCurrParent == -1)
	{
		alert('no parent selected selected, please select a node in the tree')
		return;
	}

	setEditFields('parent');

	clearEdits();
	setEditState('new');
	$("#weight_list").val('0');
	$("input[name=order]").val('10');
});


$('#edit').on('click',function(event)
{
	event.preventDefault(); 

	if(gCurrParent == -1)
	{
		alert('no parent selected selected, please select a node in the tree to edit')
		return;
	}

	setEditState('update');
});

$('#save').on('click',function(event)
{
	event.preventDefault(); 
	
	// can be here if add or update save...
	
	if(gEditState == 'new')
	{
		alert('Save the Record');
		addNode();
	}
	else
		if(gEditState == 'update')
		{
			updateNode();
		}
		else
		{
			alert('Unknown state. Can\'t save or update');
		}

		setEditState('browse');
});

$('#cancel').on('click',function(event)
{
	event.preventDefault(); 
	alert('Changes Discarded');
	setEditState('browse');
	
	getNode(gCurrNode);
});


// capture things in the tree when a click happens
// mainly the single selected (by config options) node

function getNode(node_id)
{

	if(node_id == -1)
	{
		alert('getNode() : Invalid node Id');
		return;
	}
	
	// call the ajax function that gets a node.
	
	$.ajax({
		url:	'{$ajax_url['node']}',	// match URL format for Yii, will be different if 'friendlyURL' is enabled
		type: 	'post',

		data: {		// data sent in post params
					node_id : node_id	// the node id of interest
		},
	   
		error:	function(data) {
			alert('Http Response : ' + data.responseText + ' Operation Failed');
			// turn tree red, this is where communication failed or invalid
			// data to the ajax call was sent.
			gCurrNode = -1;
			gCurrType = '';
		},
		   
		success: function (data) {

			// NON ZERO is an error, display and get out

			node = data.data;		

			gCurrNode = node.id; 
			
			if(data.status)
			{
				// specific data for this error, ie, node_id may not exist for other status
				alert('Application Error : ' + data.msg + ' Node Id : ' + node.node_id);
				return;
			}
			  
			// these should both be 0, null is not good for the UI

			if(node.min === null)
				node.min = '0';

			if(node['max'] === null)
				node.max = '0';

			// try stuffing some data to input fields

			$("input[name=name]").val(node.name);			
			$("#weight_list").val(node.weight);
			
			//$("input[name=spec_id]").val(node.spec_id);
			
			// set spec list box
			$("#spec_list").val(node.spec_id);
						
			$("input[name=order]").val(node.order);			
			$("input[name=min]").val(node.min);			
			$("input[name=max]").val(node.max);			

			// mess with some button states based on node type
			// also if the node is 9999 might want to disable 
			// some fields or other UI indicators
				
			setEditFields(node.node_type); // set field displable or not
		}
	});			
}

$('#treeview').on('changed.jstree', function (e, data) 	{

	// if we have nothing selected we can't do much, maybe some house keeping
	
	setEditState('browse');

	if(data.selected.length == 0)
	{
		gCurrParent = -1;
		gCurrNode = -1;
		gCurrType = '';
		return;
	}
	
	// console.log(data.node.type); // show intern 'type' of node as set by JSON on load
	
	var json_node_type = data.node.type; // the type coming in from inital load
	
	gCurrType = data.node.type;
	if(data.node.type == 'leaf')
	{
		node = getNodeById(data.selected[0])
		gCurrParent = node.parent;
	}
	else // it parent or root
	{
		gCurrParent = data.selected[0]; // it a parent so can add to it if any child selected
	}
	
	// call the ajax function that gets a node.
	
	getNode(data.selected[0]);
});


// Add new node to the tree. Calls remote functions, tree refreshed
// after add so no changes made to display unless successful
function addNode()
{
	selected = $('#treeview').jstree('get_selected');	// implies single selection mode in tree
	
	if(selected.length == 0)
	{
		alert('Nothing Selected, can\'t add!');
		return;
	}
	
	alert('Adding Node to Parent ' + selected[0]);

	// these must be set for all node types
	
	name = $("input[name=name]").val();
	weight_id = $("#weight_list").val();
	order = $("input[name=order]").val();

	// if a leaf then these must also be set from the form
	if(gEditType == 'leaf')
	{
		spec_id = $("#spec_list").val();
		min = $("input[name=min]").val();
		max = $("input[name=max]").val();
	}
	else
	{	// parent/root values
		spec_id = 9999;	// indicate it's a parent type
		min = 0;
		max = 0;
	}
	
	name = name.trim(); // if your browser doesn't have this get a new browswer

	if(name.length == 0)
	{
		alert('Error, name Can\'t be empty');
		return;
	}
	
	// validate numbers
	if(!isIntNum(weight_id))
	{
		alert('Invalid Weight, must be integer');
		return;
	}
	
	if(!isIntNum(spec_id))
	{
		alert('Invalid Spec Id, must be integer');
		return;
	}
	
	if(!isIntNum(order))
	{
		alert('Invalid Order, must be integer');
		return;
	}

	if(!isFloatNum(min))
	{
		alert('Invalid Min, must be numeric');
		return;
	}
	
	if(!isFloatNum(max))
	{
		alert('Invalid Max, must be numeric');
		return;
	}

	// that parent id is enough to get the
	// recipe id, all parms needed
		
	$.ajax({
		url: '{$ajax_url['add']}',	// must match URL format for Yii, will be different if 'friendlyURL' is enabled
		type: 'post',
		data: {
			parent_id : selected[0], 	// this is the parent of the new node. 
			spec_id   : spec_id,
			name      : name,
			weight	  : weight_id,
			order	  : order,
			min		  : min,
			max		  : max
		},

		success: function (data) {
			node = data.data;

			if(data.status != 0)
			{
				alert('Application Error : ' + data.msg + ', Node Id : ' + node.node_id); 
				
				// on error might reset back to edit mode, need to check
				return;
			}
		
			$('#treeview').jstree('refresh');	// once added get the new data
		},
		
		error:	function(data) {
			alert('Http Response : ' + data.responseText + ' Operation Failed');
			// turn tree red, this is where communication failed or invalid
			// data to the ajax call was sent.
		},
	});		
}

function updateNode()
{
	selected = $('#treeview').jstree('get_selected');	// implies single selection
	
	if(selected.length == 0)
	{
		alert('Nothing Selected, can not update!');
		return;
	}
	
	alert('Update Node ' + selected[0]);
	
	// that parent id is enough to get the
	// recipe id, all parms needed

	name = $("input[name=name]").val();
	weight_id = $("#weight_list").val();
	spec_id = $("#spec_list").val();
		
	order = $("input[name=order]").val();
	min = $("input[name=min]").val();
	max = $("input[name=max]").val();

	name = name.trim(); // if your browser doesn't have this get a new browswer

	// validate common

	if(name.length == 0)
	{
		alert('Error, name Can\'t be empty');
		return;
	}

	if(!isIntNum(weight_id))
	{
		alert('Invalid Weight, must be integer');
		return;
	}

	if(!isIntNum(order))
	{
		alert('Invalid Order, must be integer');
		return;
	}

	// validate and set defaults for all values if not leaf
	
	if(gCurrType == 'leaf')
	{
		if(!isIntNum(spec_id))
		{
			alert('Invalid Spec Id, must be integer');
			return;
		}
		
		if(!isFloatNum(min))
		{
			alert('Invalid Min, must be numeric');
			return;
		}
		
		if(!isFloatNum(max))
		{
			alert('Invalid Max, must be numeric');
			return;
		}
	}
	else
	{
		spec_id = 9999; // force this
		min = max = 0; 	// all zeros
	}
	

	$.ajax({
		url: '{$ajax_url['update']}',	// must match URL format for Yii, will be different if 'friendlyURL' is enabled
		type: 'post',
		data: {
			node_id   : selected[0], 	// this is the node we want to update
			spec_id   : spec_id,
			name      : name,
			weight	  : weight_id,
			order	  : order,
			min		  : min,
			max		  : max
		},
		success: function (data) {
			node = data.data;

			if(data.status != 0)
			{
				alert('Application Error : ' + data.msg + ', Node Id : ' + node.node_id); 
				return;
			}
		
			$('#treeview').jstree('refresh');
		},
		
		error:	function(data) {
			alert('Http Response : ' + data.responseText + ' Operation Failed');
			// turn tree red, this is where communication failed or invalid
			// data to the ajax call was sent.
		},

	});		
}

$('#remove').on('click',function(event)
{
	event.preventDefault(); 
		
	selected = $('#treeview').jstree('get_selected');	// implies single selection mode
	
	if(selected.length == 0)
	{
		alert('Nothing Selected');
		return;
	}
	
	// alert('Removing Node ID (and children) ' + selected[0]);
	
	$.ajax({
		url: '{$ajax_url['remove']}',	// must match URL format for Yii, will be different if 'friendlyURL' is enabled
		type: 'post',
		data: {
			node_id : selected[0]
		},
		
		success: function (data) {
						
			node = data.data;

			if(data.status != 0)
			{
				alert('Application Error :' + data.msg + ',  Node Id : ' + node.node_id); 
				return;
			}
			
			alert('Deleted ' + node.node_cnt + ' Nodes from the tree');
			
			$('#treeview').jstree('refresh');
			clearEdits();
		},
		
		error:	function(data) {
			alert('Http Response : ' + data.responseText + ' Operation Failed');
		},
		
	});		
});

$('#treeview').on("move_node.jstree", function (e, data) {

	//console.log(data);
    alert('Moving Node Id : ' + data.node.id + ' To Node Id : ' + data.parent);

	// this gets the full json for the node
	target_node = getNodeById(data.parent);

	// this will change the drop target to the parent if dropping on
	// a leaf. This will be a problem if ever doing drag/drop reordering
	// but otherwise allows for better ui
		
    if(target_node.type === 'leaf')
		target_id = target_node.parent;
	else
		target_id = data.parent;
	
	source_id = data.node.id;

	$.ajax({
		url: '{$ajax_url['move']}',	// must match URL format for Yii, will be different if 'friendlyURL' is enabled
		type: 'post',
		data: {
			source_id : source_id,
			target_id : target_id			
		},
		
		success: function (data) {
						
			node = data.data;

			if(data.status != 0)
			{
				alert('Application Error : ' + data.msg + ',  Source Id : ' + node.source_id + ' Target Id : ' + node.target_id);
				return;
			}
		},
		
		error:	function(data) {
			alert('Http Response : ' + data.responseText + ' Operation Failed');
		},
		
	});
	
	$('#treeview').jstree('refresh');
	$('#treeview').jstree('open_all');
	clearEdits();
});

JS;
$this->registerJs($script, view::POS_END);
?>
