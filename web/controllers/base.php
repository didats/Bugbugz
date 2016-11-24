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


require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/../../src/app.php';


require_once __DIR__.'/attachments/index.php';
require_once __DIR__.'/issues/index.php';
require_once __DIR__.'/projects/index.php';
require_once __DIR__.'/userdevices/index.php';
require_once __DIR__.'/users/index.php';
require_once __DIR__.'/added/added.php';


$app->match('/', function () use ($app) {
	getMyProjects();
    
    $type = $app['session']->get("user")['type'];
    $user_id = $app['session']->get("user")['id'];
    
    $table_headers = array(
	    'Title',
	    'Description',
	    'Priority',
	    'Status',
	    'Action'
    );
    
    $data = array();
    if ($type == "DEVELOPER") {
	    if (!isset($_GET['show'])) {
	    	$strSQL = "SELECT A.*, B.project_name, B.project_id FROM issues A, projects B WHERE A.user_id = ? AND A.project_id = B.project_id AND A.issue_status = 'OPEN' ORDER BY A.project_id ASC";
	    }
	    else {
		    $strSQL = "SELECT A.*, B.project_name, B.project_id FROM issues A, projects B WHERE A.user_id = ? AND A.project_id = B.project_id ORDER BY A.project_id ASC";
	    }
	}
	else if ($type == "TESTER") {
		
		if (!isset($_GET['show'])) {
			$strSQL = "SELECT A.*, B.project_name, B.project_id FROM issues A, projects B WHERE A.issue_reporter = ? AND A.project_id = B.project_id AND (A.issue_status = 'REVIEW' OR A.issue_status = 'FAILED') ORDER BY A.project_id ASC";
		}
		else {
			$strSQL = "SELECT A.*, B.project_name, B.project_id FROM issues A, projects B WHERE A.issue_reporter = ? AND A.project_id = B.project_id ORDER BY A.project_id ASC";
		}
		
	}
	else {
		$strSQL = "SELECT A.*, B.project_name, B.project_id FROM issues A, projects B WHERE A.project_id = B.project_id ORDER BY A.project_id ASC";
	}
	
	$rows = $app['db']->fetchAll($strSQL, array($user_id));
    $project = 1;
    $project_id = "";
    
    foreach($rows as $row) {
		
		if(strlen($project_id) == 0) {
			$project_id = $row['project_id'];
			$project = 1;
		}
		else if ($project_id != $row['project_id']) {
			$project_id = $row['project_id'];
			$project = 1;
		}
		else {
			$project_id = $row['project_id'];
			$project = 0;
		}
		
		array_push($data, array(
			'id' => $row['id'],
			'issue_id' => $row['issue_id'],
			'project' => $project,
			'project_id' => $row['project_id'],
			'project_name' => $row['project_name'],
			'title' => $row['issue_title'],
			'desc' => doDescription($row['issue_desc']),
			'type' => $row['issue_type'],
			'priority' => $row['issue_priority'],
			'status' => $row['issue_status']
		));
    }
    
    $send = array('rows' => $data, 'table_headers' => $table_headers);
    if(isset($_GET['show'])) {
	    $send['menu'] = "showall";
    }
    else {
	    $send['menu'] = "dashboard";
    }
    
    return $app['twig']->render('dashboard.html.twig', $send);
        
})
->bind('dashboard');

$app->before(function ($request, $app) {
	$request->getSession()->start();
	
	if (!preg_match("/login/", $request->getRequestUri())) {
		if(!checkingAuth()) {
			return $app->redirect($app['url_generator']->generate('login'));
		}
	}
});

$app->match('/login', function () use ($app) {
	$htmlData = array();
	if("POST" == $app['request']->getMethod()){
		
		foreach($_POST['form'] as $key => $value) {
			$$key = strip_tags($value);
		}
		$password = passwordShield($password);
		
		// checking the database
		$row = $app['db']->fetchAssoc("SELECT * FROM users WHERE user_name = ? AND user_password = ?", array($username, $password));
		
		
		if(!$row) {
			// do nothing
			$htmlData['message'] = "Account not found";
		}
		else {
			// do session
			$app['session']->set('user', 
				array('type' => $row['user_type'], 
				'email' => $row['user_email'], 
				'id' => $row['user_id'], 
				'user_id' => $row['id'],
				'username' => $row['user_name'],
				'fullname' => $row['user_fullname'], 
				'last_login' => $row['user_lastloggedin']));
			
			// execute update on last login
			$app['db']->executeUpdate("UPDATE users SET user_lastloggedin = NOW() WHERE user_id = ?", array($row['user_id']));
			
			getMyProjects();
			
			return $app->redirect($app['url_generator']->generate('dashboard'));
		}
		
	}
	
    return $app['twig']->render('login.html.twig', $htmlData);
        
})
->bind('login');

$app->match("/logout", function() use($app) {
	
	$app['session']->clear();
	return $app->redirect($app['url_generator']->generate('login'));
	
})->bind("logout");

function passwordShield($str) {
	return md5($str."1r2i3m4b5u6n7e8s9i0a1");
}

function createID() {
	return implode('-', str_split(substr(strtoupper(md5("1t2r3i4a5d6i7".microtime().rand(1000, 9999))), 0, 20), 5));
}

function checkingAuth() {
	global $app;
	
	$user = $app['session']->get("user");
	
	if(!isset($user['id'])) {
		return false;
	}
	
	return true;
}

function getMyProjects() {
	global $app;
	$user = $app['session']->get("user");
	$type = $user['type'];
	$id = $user['id'];
	
	// get all project which have issue under this user
	$projects = array();
	if ($type == "DEVELOPER") {
		$projects = $app['db']->fetchAll("SELECT DISTINCT A.project_name, A.project_id FROM projects A, issues B WHERE A.project_id = B.project_id AND B.user_id = ?", array($id));
		
	}
	else if($type == "TESTER") {
		$projects = $app['db']->fetchAll("SELECT DISTINCT A.project_name, A.project_id FROM projects A", array());
	}
	
	if(count($projects) > 0) {
		$list_projects = array();
		foreach($projects as $project) {
			array_push($list_projects, array('name' => $project['project_name'], 'id' => $project['project_id']));
		}
		
		$app['twig']->addGlobal('myproject', $list_projects);
	}
}

function doDescription($text) {
	return substr($text, 0, 100)."...";
}

function getFullname($userid) {
	global $app;
	
	$strSQL = "SELECT user_fullname FROM users WHERE user_id = ?";
	$row = $app['db']->fetchAssoc($strSQL, array($userid));
	
	return (isset($row['user_fullname'])) ? $row['user_fullname'] : "None";
}

$app->run();