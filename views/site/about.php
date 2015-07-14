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


	.recipe_select_panel {
		font-size:16px; 
		border-style: solid;
		border-radius : 10px;
		padding :10px;
		margin-bottom: 5px;
	}
	
	#recipe_list {
		width : 350px;
	}
	
	.tree-panel { 
		font-size:14px; 
		border-style: solid;
		border-radius : 10px 0 0 10px;
		padding :10px;
		height:600px;
		margin-right : 10px;
		overflow-y : scroll;
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

	<div class="row">
		<div class="recipe_select_panel col-sm-4">
			<?php
				$specs = $this->context->getRecipes();

				echo Html::dropDownList('recipe_list', '', ArrayHelper::map($specs, 'id', 'name'), 
								['id'=>'recipe_list',
								'prompt' => '--Select Recipe--',
								]); 
			?>

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
<!--		<button id="expand" class="btn btn-primary">Expand</button>
		<button id="contract" class="btn btn-primary">Contract</button>	-->
	</div>
	</div> <!-- tree-panel-->
	
	<div class="edit-panel col-sm-5">
	<form class="form-horizontal"> 
	<fieldset>
	<!-- Form Name -->
	<legend>Edit Data Here</legend>

	<div class="control-group">
	  <label class="control-label" for="textinput">Name</label>
	  <div class="controls">
		<input name="name" placeholder="" class="input-xlarge" type="text">
	  </div>
	</div>

<!--
	<div class="control-group">
	  <label class="control-label" for="textinput">Weight</label>
	  <div class="controls">
		<input name="weight" placeholder="" class="input-xlarge" type="text">
	  </div>
	</div>

	<div class="control-group">
	  <label class="control-label" for="textinput">Spec Id</label>
	  <div class="controls">
		<input name="spec_id" placeholder="" class="input-xlarge" type="text">
	  </div>
	</div>
-->	

	<div class="control-group">
	  <label class="control-label" for="textinput">Weight</label>
	  <div class="controls">
		  
		<?php
			$weights = $this->context->getWeights();
					?>  
		<?= Html::dropDownList('weight_list', '9999', ArrayHelper::map($weights, 'id', 'weight'), 
								['id'=>'weight_list',
								]); 
		?>
	  </div>

	<div class="control-group no_disp_parent">
	  <label class="control-label" for="textinput">Spec Id</label>
	  <div class="controls">
		  
		<?php
			$specs = $this->context->getSpecs();
					?>  
		<?= Html::dropDownList('spec_list', '9999', ArrayHelper::map($specs, 'id', 'spec_name'), 
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

	<button id="clear" class="btn btn-success">clear</button> 
	<button id="save" class="btn btn-success">Save</button> 
	<button id="cancel" class="btn btn-success">Cancel</button> 
	<button id="add" class="btn btn-primary">Add</button>
	<button id="new" class="btn btn-primary">New</button>
	<button id="update" class="btn btn-primary">Update</button>
	<button id="remove" class="btn btn-danger">Remove</button> 
	
	</fieldset>
	</form>
	<!-- debug fields -->
	<div id="ajax_node_type"></div>
	<div id="ajax_json_type"></div>
	<div id="ajax_node_id"></div>
	<div id="ajax_parent_id"></div>
	<div id="ajax_status_msg"></div>
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

$(document).ready(function() {
	
	// do some set up to get initial state of buttons, etc
	
	$("#remove").prop("disabled",true);
	$("#update").prop("disabled",true);
	$("#add").prop("disabled",true);
	$("#new").prop("disabled",true);
	$("#save").prop("disabled",true);
	$("#recipe_list").val(""); // set to the prompt
	
	setReadOnly(true); // inital state is read only
	clearEdits();
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
}

function setReadOnly(state)
{
state = false ; // for testing
	//input fields
	$("input[name=name]").prop('readonly', state);
	$("input[name=order]").prop('readonly', state);
	$("input[name=min]").prop('readonly', state);
	$("input[name=max]").prop('readonly', state);
	
	// drop down lists
	$("#spec_list").attr("disabled", state); 
	$("#weight_list").attr("disabled", state); 
}

$("input[name=name]").on('input', function(event)
{
	
});

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


$('#clear').on('click',function(event)
{
	event.preventDefault(); 
	clearEdits();
});

function ExpandTree(obj)
{
	$('#treeview').jstree('open_all');
}

function ContractTree(obj)
{
	$('#treeview').jstree('close_all');
	$('#treeview').jstree('deselect_all');
	
	$("#remove").prop("disabled",true);
	$("#update").prop("disabled",true);
}

$('#expand').on('click',function(event)
{
	event.preventDefault(); 
	ExpandTree(null);
});

$('#contract').on('click',function(event)
{
	event.preventDefault(); 
	ContractTree(null);
});

$('#new').on('click',function(event)
{
	event.preventDefault(); 

	// should only be here IF on a parent node (spec_id == 9999)
	clearEdits();
	setReadOnly(false);
	$("#save").prop("disabled",false);
});

$('#save').on('click',function(event)
{
	event.preventDefault(); 
	
	// can be here if add or update save...
});


// Add new node to the tree. Calls remote functions, tree refreshed
// after add so no changes made to display unless successful
$('#add').on('click',function(event)
{
	event.preventDefault(); 

	selected = $('#treeview').jstree('get_selected');	// implies single selection mode in tree
	
	if(selected.length == 0)
	{
		alert('Nothing Selected, can not add!');
		return;
	}
	
	alert('Adding Node to Parent ' + selected[0]);

	name = $("input[name=name]").val();
	//weight = $("input[name=weight]").val();
	weight_id = $("#spec_list").val();
	//spec_id = $("input[name=spec_id]").val();
	spec_id = $("#spec_list").val();
		
	order = $("input[name=order]").val();
	min = $("input[name=min]").val();
	max = $("input[name=max]").val();
	
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

	if(parseFloat(min) > parseFloat(max))
	{
		alert('Invalid min/max range');
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

			switch(data.status)
			{
				case 0:  break;	// no error
				case 5:  alert('Application Error ' + data.msg + ', Node Id : ' + node.node_id); return;
				default: alert('Unknown Application Error ' + data.msg); return;
			}
		
			$('#treeview').jstree('refresh');	// once added get the new data
		},
		
		error:	function(data) {
			alert('Http Response : ' + data.responseText + ' Operation Failed');
			// turn tree red, this is where communication failed or invalid
			// data to the ajax call was sent.
		},
		
	});		
});

$('#update').on('click',function(event)
{
	event.preventDefault(); 

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
	//weight = $("input[name=weight]").val();
	weight_id = $("#weight_list").val();
	
	// spec_id = $("input[name=spec_id]").val();
	
	spec_id = $("#spec_list").val();
		
	order = $("input[name=order]").val();
	min = $("input[name=min]").val();
	max = $("input[name=max]").val();

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

	if(parseFloat(min) > parseFloat(max))
	{
		alert('Invalid min/max range');
		return;
	}

	
	$.ajax({
		url: '{$ajax_url['update']}',	// must match URL format for Yii, will be different if 'friendlyURL' is enabled
//		async: false,	// make non async call as the tree gets odd if we dont
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

			switch(data.status)
			{
				case 0:  break;	// no error
				case 3:
				case 4:  alert('Application Error ' + data.msg + ', Node Id : ' + node.node_id); return;
				default: alert('Unknown Application Error ' + data.msg); return;
			}
		
			$('#treeview').jstree('refresh');
		},
		
		error:	function(data) {
			alert('Http Response : ' + data.responseText + ' Operation Failed');
			// turn tree red, this is where communication failed or invalid
			// data to the ajax call was sent.
		},

	});		
});

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
//		async: false,	// make non async call as the tree gets odd if we dont
		type: 'post',
		data: {
			node_id : selected[0]
		},
		
		success: function (data) {
						
			node = data.data;

			switch(data.status)
			{
				case 0:  break;	// no error
				case 1:
				case 2:  alert('Application Error ' + data.msg + ',  Node Id : ' + node.node_id); return;
				default: alert('Unknown Application Error ' + data.msg); return;
			}
			
			alert('Deleted ' + node.node_cnt + ' Nodes from the tree');
			
			$('#treeview').jstree('refresh');
			clearEdits();
		},
		
		error:	function(data) {
			alert('Http Response : ' + data.responseText + ' Operation Failed');
			// turn tree red, this is where communication failed or invalid
			// data to the ajax call was sent.
		},
		
	});		
});

// capture things in the tree when a click happens
// mainly the single selected (by config options) node

$('#treeview').on('changed.jstree', function (e, data) 	{

	// if we have nothing selected we can't do much, maybe some house keeping
	if(data.selected.length == 0)
	{
		// Nothing selected, clear fields, form display, etc

		$("#remove").prop("disabled",true);	// can remove if something selected
		$("#update").prop("disabled",true);
		$("#add").prop("disabled",true);
		$("#new").prop("disabled",true);
		$("#save").prop("disabled",true);

		return;
	}
	
	// console.log(data.node.type); // show intern 'type' of node as set by JSON on load
	
	var json_node_type = data.node.type; // the type coming in from inital load

	// something selected now, enable some buttons
	
	$("#remove").prop("disabled",false);
	$("#update").prop("disabled",false);
		
	// call the ajax function that gets a node.
	
	$.ajax({
		url:	'{$ajax_url['node']}',	// match URL format for Yii, will be different if 'friendlyURL' is enabled
		type: 	'post',

		data: {		// data sent in post params
					node_id : data.selected[0]	// the node id selected
		},
	   
		error:	function(data) {
			alert('Http Response : ' + data.responseText + ' Operation Failed');
			// turn tree red, this is where communication failed or invalid
			// data to the ajax call was sent.
		},
		   
		success: function (data) {

			// NON ZERO is an error, display and get out

			node = data.data;		

			if(data.status)
			{
				// specific data for this error, ie, node_id may not exist for other status
				alert('Application Error ' + data.msg + ' Node Id : ' + node.node_id);
				return;
			}
		
			// debug display
			$('#ajax_node_type').html('Node Type   : ' + node.node_type);
			$('#ajax_json_type').html('JSON Type   : ' + json_node_type);
			$('#ajax_node_id').html('Node Id   : ' + node.id);
			$('#ajax_parent_id').html('Parent Id : ' + node.parent_id);
			$('#ajax_status').html('Status : ' + node.status);
			$('#ajax_status_msg').html('Status Message : ' + node.msg);
			  
			// these should both be 0, null is not good for the UI

			if(node.min === null)
				node.min = '0';

			if(node['max'] === null)
				node.max = '0';

			// try stuffing some data to input fields

			$("input[name=name]").val(node.name);			
			//$("input[name=weight]").val(node.weight);			
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

			if(node['spec_id'] == 9999)
			{
				$("#add").prop("disabled",false);
				$("#new").prop("disabled",false);
				setReadOnly(false);
			}
			else
			{
				$("#add").prop("disabled",true);
				$("#new").prop("disabled",true);
				
				$("#save").prop("disabled",true);
				setReadOnly(true);
			}
			
		}
	});		
});

function getNodeById(id)
{
	return $('#treeview').jstree(true).get_node(id);
}

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
//		async: false,	// make non async call as the tree gets odd if we dont
		data: {
			source_id : source_id,
			target_id : target_id			
		},
		
		success: function (data) {
						
			node = data.data;

			switch(data.status)
			{
				case 0:  break;	// no error
				case 6:  alert('Application Error ' + data.msg + ',  Source Id : ' + node.source_id + ' Target Id : ' + node.target_id); return;
				default: alert('Unknown Application Error ' + data.msg); return;
			}
	
		},
		
		error:	function(data) {
			alert('Http Response : ' + data.responseText + ' Operation Failed');
			// turn tree red, this is where communication failed or invalid
			// data to the ajax call was sent.
		},
		
	});
	
	$('#treeview').jstree('refresh');
	$('#treeview').jstree('open_all');
	clearEdits();
});

JS;
$this->registerJs($script, view::POS_END);
?>
