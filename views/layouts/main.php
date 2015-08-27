<?php
use yii\helpers\Html;
use yii\bootstrap\Nav;
use yii\bootstrap\NavBar;
use yii\widgets\Breadcrumbs;
use app\assets\AppAsset;
use yii\helpers\Url;

use webvimark\modules\UserManagement\components\GhostNav;
use webvimark\modules\UserManagement\UserManagementModule;

/* @var $this \yii\web\View */
/* @var $content string */

AppAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
    <link rel="shortcut icon" href="<?php echo Yii::$app->request->baseUrl; ?>/favicon.ico" type="image/x-icon" />
</head>
<body>

<?php $this->beginBody() ?>
    <div class="wrap">
        <?php
            NavBar::begin([
                'brandLabel' => 'Car-Labs',
                'brandUrl' => Yii::$app->homeUrl,
                'options' => [
                    'class' => 'navbar-inverse navbar-fixed-top',
                ],
            ]);
            echo GhostNav::widget([
                'options' => ['class' => 'navbar-nav navbar-right'],
				'encodeLabels'=>false, // don't encode stuff in the label, needed for UserManagementModule::menuItems()
                'items' => [

                    ['label' => 'Home', 'url' => ['/site/index']],
                    ['label' => 'Recipe Editor', 'url' => ['/site/tree-edit']],
                    ['label' => 'About', 'url' => ['/site/about']],
					[
						'label' => 'User Admin',
						'items'=> UserManagementModule::menuItems(),
					],
                    Yii::$app->user->isGuest ?
                        ['label' => 'Login', 'url' => ['/user-management/auth/login']] : 
                        ['label' => 'Logout (' . Yii::$app->user->identity->username . ')', 'url' => ['/user-management/auth/logout']],
      				[
					 'label' => 'Admin',
					 'items'=> 	array_merge(UserManagementModule::menuItems(), [ ['label'=>'Change Password', 'url'=>['/user-management/auth/change-own-password']]]),
      				],

                ],
            ]);
            NavBar::end();
        ?>

        <div class="container">
            <?= Breadcrumbs::widget([
                'links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],
            ]) ?>
            <?= $content ?>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p class="pull-left">&copy; Car-Labs or oTToMate or cars360 <?= date('Y') ?></p>
            <p class="pull-right"><?= 'Powered By Car-Labs' ?></p>
        </div>
    </footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
