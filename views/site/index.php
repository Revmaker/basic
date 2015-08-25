<?php
/* @var $this yii\web\View */
use yii\helpers\Url;

$this->title = 'Car-Labs';
?>
<div class="site-index">

    <div class="jumbotron">
		<img src="<?=URL::to("@web/images/tools.png");?>" />
        <h1>Car-Labs Tools</h1>

        <p class="lead">Welcome to Car-Labs Tools Page</p>
    </div>

    <div class="body-content">

        <div class="row">
            <div class="col-lg-4">
                <h2>Recipe Editor</h2>

                <p>The Recipe Editor is to Edit Recipe's</p>

                <p><a class="btn btn-default" href="<?= URL::toRoute("/site/tree-edit");?>">Recipe Editor &raquo;</a></p>
            </div>
            <div class="col-lg-4">
                <h2>Other Tools</h2>

                <p>This is where you might find the other tools</p>

                <p><a class="btn btn-default" href="<?= URL::toRoute("/site/index");?>">Other Tool &raquo;</a></p>
            </div>
        </div>

    </div>
</div>
