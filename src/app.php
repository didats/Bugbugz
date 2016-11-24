<?php
/*
 * This file is part of the CRUD Admin Generator project.
 *
 * Author: Jon Segador <jonseg@gmail.com>
 * Web: http://crud-admin-generator.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class queryData {
	public $start;
	public $recordsTotal;
	public $recordsFiltered;
	public $data;
	function queryData() {
	}
}
use Silex\Application;
$app = new Application();
$app->register(new Silex\Provider\TwigServiceProvider(), array(
	'twig.path' => __DIR__.'/../web/views',
));
$app->register(new Silex\Provider\FormServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
	'translator.messages' => array(),
));
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
		'dbs.options' => array(
			'db' => array(
				'driver'   => 'pdo_mysql',
				'dbname'   => 'YOUR DB',
				'host'     => '127.0.0.1',
				'user'     => 'YOUR USERDB',
				'password' => 'YOUR PASSWORD',
				'charset'  => 'utf8',
			),
		)
));
$app['twig']->addGlobal('sessions', $app['session']->get("user"));
$app['asset_path'] = 'http://trackr.local/resources';
$app['upload_path'] = 'http://trackr.local/uploads';
$app['debug'] = true;
	// array of REGEX column name to display for foreigner key insted of ID
	// default used :'name','title','e?mail','username'
$app['usr_search_names_foreigner_key'] = array();
return $app;