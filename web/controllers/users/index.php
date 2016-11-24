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


require_once __DIR__.'/../../../vendor/autoload.php';
require_once __DIR__.'/../../../src/app.php';

use Symfony\Component\Validator\Constraints as Assert;

$app->match('/users/list', function (Symfony\Component\HttpFoundation\Request $request) use ($app) {  
    $start = 0;
    $vars = $request->query->all();
    $qsStart = (int)$vars["start"];
    $search = $vars["search"];
    $order = $vars["order"];
    $columns = $vars["columns"];
    $qsLength = (int)$vars["length"];    
    
    if($qsStart) {
        $start = $qsStart;
    }    
	
    $index = $start;   
    $rowsPerPage = $qsLength;
       
    $rows = array();
    
    $searchValue = $search['value'];
    $orderValue = $order[0];
    
    $orderClause = "";
    if($orderValue) {
        $orderClause = " ORDER BY ". $columns[(int)$orderValue['column']]['data'] . " " . $orderValue['dir'];
    }
    
    $table_columns = array(
		'id', 
		'user_id', 
		'user_email', 
		'user_name', 
		'user_fullname', 
		'user_type', 
		'created_at', 
		'user_lastloggedin', 

    );
    
    $table_columns_type = array(
		'int(11) unsigned', 
		'varchar(100)', 
		'varchar(100)', 
		'varchar(100)', 
		'varchar(255)', 
		'varchar(50)', 
		'timestamp', 
		'int(11)', 

    );    
    
    $whereClause = "";
    
    $i = 0;
    foreach($table_columns as $col){
        
        if ($i == 0) {
           $whereClause = " WHERE";
        }
        
        if ($i > 0) {
            $whereClause =  $whereClause . " OR"; 
        }
        
        $whereClause =  $whereClause . " " . $col . " LIKE '%". $searchValue ."%'";
        
        $i = $i + 1;
    }
    
    $recordsTotal = $app['db']->executeQuery("SELECT * FROM `users`" . $whereClause . $orderClause)->rowCount();
    
    $find_sql = "SELECT * FROM `users`". $whereClause . $orderClause . " LIMIT ". $index . "," . $rowsPerPage;
    $rows_sql = $app['db']->fetchAll($find_sql, array());

    foreach($rows_sql as $row_key => $row_sql){
        for($i = 0; $i < count($table_columns); $i++){

		if( $table_columns[$i] == "user_fullname") {
			$rows[$row_key][$table_columns[$i]] = "<strong>".$row_sql[$table_columns[$i]]."</strong><br />".$row_sql['user_id']."<br />".$row_sql['user_email'];
		}
		else {
			$rows[$row_key][$table_columns[$i]] = $row_sql[$table_columns[$i]];
		}

        }
    }    
    
    $queryData = new queryData();
    $queryData->start = $start;
    $queryData->recordsTotal = $recordsTotal;
    $queryData->recordsFiltered = $recordsTotal;
    $queryData->data = $rows;
    
    return new Symfony\Component\HttpFoundation\Response(json_encode($queryData), 200);
});

$app->match('/users', function () use ($app) {
    
	$table_columns = array(
		'id', 
		'user_fullname', 
		'user_name', 
		'user_type',
		'user_lastloggedin'
    );
    
    $table_headers = array(
	    'ID',
	    'Fullname',
	    'Username',
	    'Type',
	    'Last logged in'
    );

    $primary_key = "id";	

    return $app['twig']->render('users/list.html.twig', array(
    	"table_columns" => $table_columns,
        "primary_key" => $primary_key,
        "table_headers" => $table_headers
    ));
        
})
->bind('users_list');



$app->match('/users/create', function () use ($app) {
    
    $initial_data = array(
		'user_id' => createID(), 
		'user_email' => '', 
		'user_name' => '', 
		'user_fullname' => '', 
		'user_type' => '', 
		'created_at' => date("Y-m-d H:i:s"), 
		'user_lastloggedin' => date("Y-m-d H:i:s"), 

    );

    $form = $app['form.factory']->createBuilder('form', $initial_data);



	$form = $form->add('user_id', 'text', array('required' => false));
	$form = $form->add('user_email', 'text', array('required' => false));
	$form = $form->add('user_name', 'text', array('required' => false));
	$form = $form->add('user_fullname', 'text', array('required' => false));
	$form = $form->add('user_password', 'password', array('required' => true));
	$form = $form->add('user_type', 'choice', array('required' => true, 'choices' => array("DEVELOPER" => "Developer", "TESTER" => "Tester", 'ADMIN' => "Admin")));
	$form = $form->add('created_at', 'text', array('required' => true));
	$form = $form->add('user_lastloggedin', 'text', array('required' => false));


    $form = $form->getForm();

    if("POST" == $app['request']->getMethod()){

        $form->handleRequest($app["request"]);

        if ($form->isValid()) {
            $data = $form->getData();
            
            $password = passwordShield($data['user_password']);

            $update_query = "INSERT INTO `users` (`user_id`, `user_email`, `user_name`, `user_fullname`, `user_type`, `created_at`, `user_lastloggedin`, `user_password`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $app['db']->executeUpdate($update_query, array($data['user_id'], $data['user_email'], $data['user_name'], $data['user_fullname'], $data['user_type'], $data['created_at'], $data['user_lastloggedin'], $password));            


            $app['session']->getFlashBag()->add(
                'success',
                array(
                    'message' => 'users created!',
                )
            );
            return $app->redirect($app['url_generator']->generate('users_list'));

        }
    }

    return $app['twig']->render('users/create.html.twig', array(
        "form" => $form->createView()
    ));
        
})
->bind('users_create');



$app->match('/users/edit/{id}', function ($id) use ($app) {

    $find_sql = "SELECT * FROM `users` WHERE `id` = ?";
    $row_sql = $app['db']->fetchAssoc($find_sql, array($id));

    if(!$row_sql){
        $app['session']->getFlashBag()->add(
            'danger',
            array(
                'message' => 'Row not found!',
            )
        );        
        return $app->redirect($app['url_generator']->generate('users_list'));
    }

    
    $initial_data = array(
		'user_id' => $row_sql['user_id'], 
		'user_email' => $row_sql['user_email'], 
		'user_name' => $row_sql['user_name'], 
		'user_fullname' => $row_sql['user_fullname'], 
		'user_type' => $row_sql['user_type'], 
		'created_at' => $row_sql['created_at'], 
		'user_lastloggedin' => $row_sql['user_lastloggedin'], 

    );


    $form = $app['form.factory']->createBuilder('form', $initial_data);


	$form = $form->add('user_id', 'text', array('required' => false));
	$form = $form->add('user_email', 'text', array('required' => false));
	$form = $form->add('user_name', 'text', array('required' => false));
	$form = $form->add('user_password', 'password', array('required' => true));
	$form = $form->add('user_fullname', 'text', array('required' => false));
	$form = $form->add('user_type', 'choice', array('required' => true, 'choices' => array("DEVELOPER" => "Developer", "TESTER" => "Tester", 'ADMIN' => "Admin")));
	$form = $form->add('created_at', 'text', array('required' => true));
	$form = $form->add('user_lastloggedin', 'text', array('required' => false));


    $form = $form->getForm();

    if("POST" == $app['request']->getMethod()){

        $form->handleRequest($app["request"]);

        if ($form->isValid()) {
            $data = $form->getData();
			
			$password_edit = "";
			if($data['user_password'] != "") {
				$password_edit = "`user_password` = '".passwordShield($data['user_password'])."',";
			}
			
            $update_query = "UPDATE `users` SET `user_id` = ?, `user_email` = ?, `user_name` = ?, `user_fullname` = ?, `user_type` = ?, `created_at` = ?,$password_edit `user_lastloggedin` = ? WHERE `id` = ?";
            
            
            
            $app['db']->executeUpdate($update_query, array($data['user_id'], $data['user_email'], $data['user_name'], $data['user_fullname'], $data['user_type'], $data['created_at'], $data['user_lastloggedin'], $id));            


            $app['session']->getFlashBag()->add(
                'success',
                array(
                    'message' => 'users edited!',
                )
            );
            return $app->redirect($app['url_generator']->generate('users_edit', array("id" => $id)));

        }
    }

    return $app['twig']->render('users/edit.html.twig', array(
        "form" => $form->createView(),
        "id" => $id
    ));
        
})
->bind('users_edit');

$app->match('/users/myaccount', function () use ($app) {
	getMyProjects();
	$id = $app['session']->get("user")['user_id'];
	
    $find_sql = "SELECT * FROM `users` WHERE `id` = ?";
    $row_sql = $app['db']->fetchAssoc($find_sql, array($id));

    if(!$row_sql){
        $app['session']->getFlashBag()->add(
            'danger',
            array(
                'message' => 'Row not found!',
            )
        );        
        return $app->redirect($app['url_generator']->generate('users_list'));
    }

    
    $initial_data = array(
		'user_id' => $row_sql['user_id'], 
		'user_email' => $row_sql['user_email'], 
		'user_name' => $row_sql['user_name'], 
		'user_fullname' => $row_sql['user_fullname'], 
		'user_type' => $row_sql['user_type'], 
		'created_at' => $row_sql['created_at'], 
		'user_lastloggedin' => $row_sql['user_lastloggedin'], 

    );


    $form = $app['form.factory']->createBuilder('form', $initial_data);


	$form = $form->add('user_id', 'text', array('required' => false));
	$form = $form->add('user_email', 'text', array('required' => false));
	$form = $form->add('user_name', 'text', array('required' => false));
	$form = $form->add('user_password', 'password', array('required' => true));
	$form = $form->add('user_fullname', 'text', array('required' => false));
	$form = $form->add('user_type', 'choice', array('required' => true, 'choices' => array("DEVELOPER" => "Developer", "TESTER" => "Tester", 'ADMIN' => "Admin")));
	$form = $form->add('created_at', 'text', array('required' => true));
	$form = $form->add('user_lastloggedin', 'text', array('required' => false));


    $form = $form->getForm();

    if("POST" == $app['request']->getMethod()){

        $form->handleRequest($app["request"]);

        if ($form->isValid()) {
            $data = $form->getData();
			
			$password_edit = "";
			if($data['user_password'] != "") {
				$password_edit = "`user_password` = '".passwordShield($data['user_password'])."'";
			}
			
            $update_query = "UPDATE `users` SET `user_id` = ?, `user_email` = ?, `user_name` = ?, `user_fullname` = ?, `user_type` = ?, `created_at` = ?,$password_edit `user_lastloggedin` = ? WHERE `id` = ?";
            
            
            
            $app['db']->executeUpdate($update_query, array($data['user_id'], $data['user_email'], $data['user_name'], $data['user_fullname'], $data['user_type'], $data['created_at'], $data['user_lastloggedin'], $id));            


            $app['session']->getFlashBag()->add(
                'success',
                array(
                    'message' => 'users edited!',
                )
            );
            return $app->redirect($app['url_generator']->generate('users_myaccount'));

        }
    }

    return $app['twig']->render('users/myaccount.html.twig', array(
        "form" => $form->createView(),
        "id" => $id,
        "menu" => "account"
    ));
        
})
->bind('users_myaccount');

$app->match('/users/delete/{id}', function ($id) use ($app) {

    $find_sql = "SELECT * FROM `users` WHERE `id` = ?";
    $row_sql = $app['db']->fetchAssoc($find_sql, array($id));

    if($row_sql){
        $delete_query = "DELETE FROM `users` WHERE `id` = ?";
        $app['db']->executeUpdate($delete_query, array($id));

        $app['session']->getFlashBag()->add(
            'success',
            array(
                'message' => 'users deleted!',
            )
        );
    }
    else{
        $app['session']->getFlashBag()->add(
            'danger',
            array(
                'message' => 'Row not found!',
            )
        );  
    }

    return $app->redirect($app['url_generator']->generate('users_list'));

})
->bind('users_delete');