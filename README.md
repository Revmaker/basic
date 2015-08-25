Yii 2 Basic Project Template
============================

This is the start of the Recipe Editor. It's based on the Yii 2 basic template

DIRECTORY STRUCTURE
-------------------

      assets/             contains assets definition
      commands/           contains console commands (controllers)
      config/             contains application configurations
      controllers/        contains Web controller classes
      mail/               contains view files for e-mails
      models/             contains model classes
      runtime/            contains files generated during runtime (MUST BE CHMOD 777'ed)
      tests/              contains various tests for the basic application
      vendor/             contains dependent 3rd-party packages
      views/              contains view files for the Web application
      web/                contains the entry script and Web resources
      web/assets          contains the Web assets created by Yii (MUST BE CHMOD 777'ed)


REQUIREMENTS
------------

The minimum requirement by this project template that your Web server supports PHP 5.4.0.
You may need to install composer and bower 

INSTALLATION
------------

### Install from here

Change owner of all files to something like ec2-users:www (for AWS) or for
Ubuntu/Mint (username):www-data where username is your usename if that's not clear
Do a clone into a director. Chmod the the web/assets, and runtime directory to 777
Run Composer in the projects working directory by something like this `composer update`

### Composer

If you do not have [Composer](http://getcomposer.org/), you may install it by following the instructions
at [getcomposer.org](http://getcomposer.org/doc/00-intro.md#installation-nix).

Now you should be able to access the application through the following URL, assuming `basic` is the directory
directly under the Web root.

~~~
http://localhost/basic/web/ or http://yourdomain/basic/web/
~~~


CONFIGURATION
-------------

### Database

Edit the file `config/db.php` with real data, for example:

```php
return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=localhost;dbname=edmunds-jato',	// currently this is the db, but subject to change
    'username' => 'revmaker',	// these must match your credential
    'password' => 'revmaker',
    'charset' => 'utf8',
];
```

**NOTE:** Yii won't create the database for you, this has to be done manually before you can access it.

Also check and edit the other files in the `config/` directory to customize your application.
