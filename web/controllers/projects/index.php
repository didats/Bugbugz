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

$app->match('/projects/list', function (Symfony\Component\HttpFoundation\Request $request) use ($app) {  
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
		'project_id', 
		'project_name', 
		'project_description', 
		'created_at', 
		'updated_at', 

    );
    
    $table_columns_type = array(
		'int(11) unsigned', 
		'varchar(100)', 
		'int(11)', 
		'int(11)', 
		'timestamp', 
		'timestamp', 

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
    
    $recordsTotal = $app['db']->executeQuery("SELECT * FROM `projects`" . $whereClause . $orderClause)->rowCount();
    
    $find_sql = "SELECT * FROM `projects`". $whereClause . $orderClause . " LIMIT ". $index . "," . $rowsPerPage;
    $rows_sql = $app['db']->fetchAll($find_sql, array());

    foreach($rows_sql as $row_key => $row_sql){
        for($i = 0; $i < count($table_columns); $i++){

		if( $table_columns_type[$i] != "blob") {
				$rows[$row_key][$table_columns[$i]] = $row_sql[$table_columns[$i]];
		} else {				if( !$row_sql[$table_columns[$i]] ) {
						$rows[$row_key][$table_columns[$i]] = "0 Kb.";
				} else {
						$rows[$row_key][$table_columns[$i]] = " <a target='__blank' href='menu/download?id=" . $row_sql[$table_columns[0]];
						$rows[$row_key][$table_columns[$i]] .= "&fldname=" . $table_columns[$i];
						$rows[$row_key][$table_columns[$i]] .= "&idfld=" . $table_columns[0];
						$rows[$row_key][$table_columns[$i]] .= "'>";
						$rows[$row_key][$table_columns[$i]] .= number_format(strlen($row_sql[$table_columns[$i]]) / 1024, 2) . " Kb.";
						$rows[$row_key][$table_columns[$i]] .= "</a>";
				}
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




/* Download blob img */
$app->match('/projects/download', function (Symfony\Component\HttpFoundation\Request $request) use ($app) { 
    
    // menu
    $rowid = $request->get('id');
    $idfldname = $request->get('idfld');
    $fieldname = $request->get('fldname');
    
    if( !$rowid || !$fieldname ) die("Invalid data");
    
    $find_sql = "SELECT " . $fieldname . " FROM " . projects . " WHERE ".$idfldname." = ?";
    $row_sql = $app['db']->fetchAssoc($find_sql, array($rowid));

    if(!$row_sql){
        $app['session']->getFlashBag()->add(
            'danger',
            array(
                'message' => 'Row not found!',
            )
        );        
        return $app->redirect($app['url_generator']->generate('menu_list'));
    }

    header('Content-Description: File Transfer');
    header('Content-Type: image/jpeg');
    header("Content-length: ".strlen( $row_sql[$fieldname] ));
    header('Expires: 0');
    header('Cache-Control: public');
    header('Pragma: public');
    ob_clean();    
    echo $row_sql[$fieldname];
    exit();
   
    
});



$app->match('/projects', function () use ($app) {
    
	$table_columns = array(
		'id', 
		'project_id', 
		'project_name', 
		'project_description'
    );
    
    $table_headers = array(
	    'ID',
	    'Serial',
	    'Name',
	    'Description'
    );
    
    getMyProjects();

    $primary_key = "id";	

    return $app['twig']->render('projects/list.html.twig', array(
    	"table_columns" => $table_columns,
        "primary_key" => $primary_key,
        "table_headers" => $table_headers
    ));
        
})
->bind('projects_list');



$app->match('/projects/create', function () use ($app) {
    
    $initial_data = array(
		'project_id' => createID(), 
		'project_name' => '', 
		'project_description' => '', 
		'created_at' => date("Y-m-d H:i:s"), 
		'updated_at' => date("Y-m-d H:i:s"), 

    );
    
    getMyProjects();

    $form = $app['form.factory']->createBuilder('form', $initial_data);



	$form = $form->add('project_id', 'text', array('required' => false));
	$form = $form->add('project_name', 'text', array('required' => false));
	$form = $form->add('project_description', 'textarea', array('required' => false));
	$form = $form->add('created_at', 'text', array('required' => true));
	$form = $form->add('updated_at', 'text', array('required' => false));


    $form = $form->getForm();

    if("POST" == $app['request']->getMethod()){

        $form->handleRequest($app["request"]);

        if ($form->isValid()) {
            $data = $form->getData();

            $update_query = "INSERT INTO `projects` (`project_id`, `project_name`, `project_description`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?)";
            $app['db']->executeUpdate($update_query, array($data['project_id'], $data['project_name'], $data['project_description'], $data['created_at'], $data['updated_at']));            


            $app['session']->getFlashBag()->add(
                'success',
                array(
                    'message' => 'projects created!',
                )
            );
            return $app->redirect($app['url_generator']->generate('projects_list'));

        }
    }

    return $app['twig']->render('projects/create.html.twig', array(
        "form" => $form->createView()
    ));
        
})
->bind('projects_create');



$app->match('/projects/edit/{id}', function ($id) use ($app) {
	
	getMyProjects();
	
    $find_sql = "SELECT * FROM `projects` WHERE `id` = ?";
    $row_sql = $app['db']->fetchAssoc($find_sql, array($id));

    if(!$row_sql){
        $app['session']->getFlashBag()->add(
            'danger',
            array(
                'message' => 'Row not found!',
            )
        );        
        return $app->redirect($app['url_generator']->generate('projects_list'));
    }

    
    $initial_data = array(
		'project_id' => $row_sql['project_id'], 
		'project_name' => $row_sql['project_name'], 
		'project_description' => $row_sql['project_description'], 
		'created_at' => $row_sql['created_at'], 
		'updated_at' => $row_sql['updated_at'], 

    );


    $form = $app['form.factory']->createBuilder('form', $initial_data);


	$form = $form->add('project_id', 'text', array('required' => false));
	$form = $form->add('project_name', 'text', array('required' => false));
	$form = $form->add('project_description', 'textarea', array('required' => false));
	$form = $form->add('created_at', 'text', array('required' => true));
	$form = $form->add('updated_at', 'text', array('required' => false));


    $form = $form->getForm();

    if("POST" == $app['request']->getMethod()){

        $form->handleRequest($app["request"]);

        if ($form->isValid()) {
            $data = $form->getData();

            $update_query = "UPDATE `projects` SET `project_id` = ?, `project_name` = ?, `project_description` = ?, `created_at` = ?, `updated_at` = ? WHERE `id` = ?";
            $app['db']->executeUpdate($update_query, array($data['project_id'], $data['project_name'], $data['project_description'], $data['created_at'], $data['updated_at'], $id));            


            $app['session']->getFlashBag()->add(
                'success',
                array(
                    'message' => 'projects edited!',
                )
            );
            return $app->redirect($app['url_generator']->generate('projects_edit', array("id" => $id)));

        }
    }

    return $app['twig']->render('projects/edit.html.twig', array(
        "form" => $form->createView(),
        "id" => $id
    ));
        
})
->bind('projects_edit');



$app->match('/projects/delete/{id}', function ($id) use ($app) {

    $find_sql = "SELECT * FROM `projects` WHERE `id` = ?";
    $row_sql = $app['db']->fetchAssoc($find_sql, array($id));

    if($row_sql){
        $delete_query = "DELETE FROM `projects` WHERE `id` = ?";
        $app['db']->executeUpdate($delete_query, array($id));

        $app['session']->getFlashBag()->add(
            'success',
            array(
                'message' => 'projects deleted!',
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

    return $app->redirect($app['url_generator']->generate('projects_list'));

})
->bind('projects_delete');






