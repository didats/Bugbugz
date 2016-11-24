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

$app->match('/issues/list', function (Symfony\Component\HttpFoundation\Request $request) use ($app) {  
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
		'issue_id', 
		'project_id', 
		'user_id', 
		'issue_title', 
		'issue_desc', 
		'issue_reporter', 
		'issue_type', 
		'issue_priority', 
		'issue_status', 
		'created_at', 
		'updated_at', 

    );
    
    $table_columns_type = array(
		'int(11) unsigned', 
		'varchar(100)', 
		'varchar(100)', 
		'varchar(100)', 
		'varchar(255)', 
		'text', 
		'varchar(100)', 
		'varchar(50)', 
		'varchar(50)', 
		'varchar(50)', 
		'timestamp', 
		'timestamp', 

    );    
    
    $whereClause = "WHERE ";
    
    $i = 0;
    foreach($table_columns as $col){
        
        if ($i == 0) {
           $whereClause .= "(";
        }
        
        if ($i > 0) {
            $whereClause =  $whereClause . " OR"; 
        }
        
        $whereClause =  $whereClause . " " . $col . " LIKE '%". $searchValue ."%'";
        
        $i = $i + 1;
    }
    
    $user_type = $app['session']->get("user")["type"];
    $user_id = $app['session']->get("user")["id"];
    $project = $_GET['projectid'];
    
    if(strlen($whereClause) > 0) {
	    if ($user_type == "DEVELOPER") {
	    	$whereClause .= ") AND (issue_status = 'OPEN' AND user_id = '$user_id')";
	    }
	    else if($user_type == "TESTER") {
			$whereClause .= ") AND (project_id = '$project' AND issue_reporter = '$user_id')";
		}
		else {
			$whereClause .= "1";
		}
    }
    else if(strlen($whereClause) == 0) {
	    if ($user_type == "DEVELOPER") {
	    	$whereClause .= " AND (issue_status = 'OPEN' AND user_id = '$user_id')";
	    }
	    else if($user_type == "TESTER") {
			$whereClause .= " AND (project_id = '$project' AND issue_reporter = '$user_id')";
		}
		else {
			$whereClause .= "1";
		}
    }
    
    
    $recordsTotal = $app['db']->executeQuery("SELECT * FROM `issues`" . $whereClause . $orderClause)->rowCount();
    
    $find_sql = "SELECT * FROM `issues`". $whereClause . $orderClause . " LIMIT ". $index . "," . $rowsPerPage;
    
    $rows_sql = $app['db']->fetchAll($find_sql, array());

    foreach($rows_sql as $row_key => $row_sql){
        for($i = 0; $i < count($table_columns); $i++){

			if( $table_columns[$i] == "issue_title") {
				$issue_id = $row_sql['issue_id'];
				$rows[$row_key][$table_columns[$i]] = "<a href='/issues/detail/$issue_id' class='issue-detail'><i class='fa fa-bug'></i> ".$row_sql[$table_columns[$i]]."</a>";
			}
			elseif( $table_columns[$i] == "issue_desc") {
				$rows[$row_key][$table_columns[$i]] = doDescription($row_sql[$table_columns[$i]]);
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

$app->get("/issues/status/{status}/{id}", function($status, $id) use($app) {
	
	// check if the issue under this guy
	$strSQL = "SELECT * FROM issues WHERE issue_id = ? AND user_id = ?";
	$row = $app['db']->fetchAssoc($strSQL, array($id, $app['session']->get("user")['id']));
	
	if(count($row) > 0) {
		if($app['session']->get("user")["type"] == "DEVELOPER") {
			if($status == "done") {
				$status = "REVIEW";
			}
		}
		
		
		// update
		$strSQL = "UPDATE issues SET issue_status = ? WHERE id = ?";
		$rst = $app['db']->executeUpdate($strSQL, array(strtoupper($status), $id));
	}
	
	return $app->redirect($app['url_generator']->generate('issues_list')."?project=".$_GET['project']);
	
	return false;
});

$app->match("/issues/detail/{id}", function($id) use($app) {
	getMyProjects();
	
	$strSQL = "SELECT A.*, B.* FROM issues A, projects B WHERE A.project_id = B.project_id AND A.issue_id = ?";
	$row = $app['db']->fetchAssoc($strSQL, array($id));
	
	$attachments = $app['db']->fetchAll("SELECT * FROM attachments WHERE issue_id = ?", array($row['issue_id']));
	
	$comments = $app['db']->fetchAll("SELECT * FROM comments WHERE issue_id = ?", array($id));
	$commentData = array();
	foreach($comments as $comment) {
		array_push($commentData, array(
			'user' => getFullname($comment['user_id']),
			'text' => nl2br($comment['comment_text'])
		));
	}
	
	if(isset($_POST['comment'])) {
		if (strlen($_POST['comment']) > 0) {
			$app['db']->executeUpdate("INSERT INTO comments SET comment_id = ?, user_id = ?, issue_id = ?, comment_text = ?, created_at = NOW()", array(createID(), $row['user_id'], $id, $_POST['comment']));
			
			
			return $app->redirect($app['url_generator']->generate('issues_detail', array('id' => $id)));
		}
		
	}
	
	return $app['twig']->render('issues/detail.html.twig', array(
    	'data' => $row,
    	'reporter' => getFullname($row['issue_reporter']),
    	'assigned' => getFullname($row['user_id']),
    	'attachments' => $attachments,
    	'comments' => $commentData,
    	'comments_length' => count($commentData)
    ));
	
})->bind('issues_detail');

$app->match('/issues', function () use ($app) {
    
    $table_headers = array(
	    'ID',
	    'Title',
	    'Description',
	    'Priority',
	    'Status'
    );
    $table_columns = array(
	    'id',
		'issue_title',
		'issue_desc',
		'issue_priority',
		'issue_status'
    );

    $primary_key = "id";
    
    getMyProjects();
    
    // project detail
    $project = $app['db']->fetchAssoc("SELECT * FROM projects WHERE project_id = ?", array($_GET['project']));
    
    return $app['twig']->render('issues/list.html.twig', array(
    	"table_columns" => $table_columns,
    	"table_headers" => $table_headers,
        "primary_key" => $primary_key,
        "project" => $project,
        "project_id" => $_GET['project']
    ));
        
})
->bind('issues_list');



$app->match('/issues/create', function () use ($app) {
    
    getMyProjects();
    
    $rows = $app['db']->fetchAll("SELECT user_id, user_fullname FROM users WHERE user_type = 'DEVELOPER'");
    $users = array();
    foreach($rows as $row) {
	    $users[$row['user_id']] = $row['user_fullname'];
    }
    
    $initial_data = array(
		'issue_id' => createID(), 
		'project_id' => $_GET['project'], 
		'user_id' => "", 
		'issue_title' => '', 
		'issue_desc' => '', 
		'issue_reporter' => $app['session']->get("user")['id'], 
		'issue_type' => '', 
		'issue_priority' => '', 
		'issue_status' => 'OPEN', 
		'created_at' => date("Y-m-d H:i:s"), 
		'updated_at' => date("Y-m-d H:i:s"), 

    );

    $form = $app['form.factory']->createBuilder('form', $initial_data);

	// BUG, NEW FEATURE, IMPROVEMENT
	// MINOR, MAJOR, BLOCKER, CRITICAL

	$form = $form->add('issue_id', 'text', array('required' => true));
	$form = $form->add('project_id', 'text', array('required' => true));
	$form = $form->add('issue_type', 'choice', array('required' => true, 'choices' => array('BUG' => "Bug", 'NEW FEATURE' => "New Feature", 'IMPROVEMENT' => "Improvement")));
	$form = $form->add('issue_priority', 'choice', array('required' => true, 'choices' => array('MINOR' => 'Minor', 'MAJOR' => 'Major', 'BLOCKER' => 'Blocker', 'CRITICAL' => 'Critical')));
	$form = $form->add('issue_status', 'text', array('required' => true));	
	$form = $form->add('issue_title', 'text', array('required' => true));
	$form = $form->add('issue_desc', 'textarea', array('required' => true));
	$form = $form->add('issue_reporter', 'text', array('required' => true));

	$form = $form->add('user_id', 'choice', array('required' => true, 'label' => "Assigned to", 'choices' => $users));
	$form = $form->add('created_at', 'text', array('required' => true));
	$form = $form->add('updated_at', 'text', array('required' => false));
	
	$form = $form->add('upload_file1', 'file', array('required' => false));
	$form = $form->add('upload_file2', 'file', array('required' => false));
	$form = $form->add('upload_file3', 'file', array('required' => false));

    $form = $form->getForm();

    if("POST" == $app['request']->getMethod()){

        $form->handleRequest($app["request"]);

        if ($form->isValid()) {
            $data = $form->getData();
            
            $count = 3;
            for ($i = 0; $i<$count; $i++) {
	            $file = $_FILES['form']['name']['upload_file'.($i + 1)];
	            
	            if(!empty($file)) {
		            $filetype = strrchr($file, '.');
		            
		            $destination = md5(uniqid(rand(), true)).$filetype;
		            
					if(move_uploaded_file($_FILES['form']['tmp_name']['upload_file'.($i + 1)], getcwd()."/uploads/".$destination)) {
						$query = "INSERT INTO attachments SET attachment_file = ?, attachment_name = ?, attachment_id = ?, issue_id = ?, user_id = ?";
						$app['db']->executeUpdate($query, array(
							$destination,
							$destination, 
							createID(),
							$initial_data['issue_id'],
							$app['session']->get("user")['id']
						));
					}
		        }
            }

            $update_query = "INSERT INTO `issues` (`issue_id`, `project_id`, `user_id`, `issue_title`, `issue_desc`, `issue_reporter`, `issue_type`, `issue_priority`, `issue_status`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $app['db']->executeUpdate($update_query, array($data['issue_id'], $data['project_id'], $data['user_id'], $data['issue_title'], $data['issue_desc'], $data['issue_reporter'], $data['issue_type'], $data['issue_priority'], $data['issue_status'], $data['created_at'], $data['updated_at']));            


            $app['session']->getFlashBag()->add(
                'success',
                array(
                    'message' => 'issues created!',
                )
            );
            return $app->redirect($app['url_generator']->generate('issues_list')."?project=".$data['project_id']);

        }
    }
    
    $send = array(
	    "form" => $form->createView(),
        "project_id" => $_GET['project']
    );
    
    

    return $app['twig']->render('issues/create.html.twig', $send);
        
})
->bind('issues_create');



$app->match('/issues/edit/{id}', function ($id) use ($app) {

	$rows = $app['db']->fetchAll("SELECT user_id, user_fullname FROM users WHERE user_type = 'DEVELOPER'");
    $users = array();
    foreach($rows as $row) {
	    $users[$row['user_id']] = $row['user_fullname'];
    }

    $find_sql = "SELECT * FROM `issues` WHERE `id` = ?";
    $row_sql = $app['db']->fetchAssoc($find_sql, array($id));
	
	getMyProjects();
	
	
	
    if(!$row_sql){
        $app['session']->getFlashBag()->add(
            'danger',
            array(
                'message' => 'Row not found!',
            )
        );        
        return $app->redirect($app['url_generator']->generate('issues_list'));
    }

    
    $initial_data = array(
		'issue_id' => $row_sql['issue_id'], 
		'project_id' => $row_sql['project_id'], 
		'user_id' => $row_sql['user_id'], 
		'issue_title' => $row_sql['issue_title'], 
		'issue_desc' => $row_sql['issue_desc'], 
		'issue_reporter' => $row_sql['issue_reporter'], 
		'issue_type' => $row_sql['issue_type'], 
		'issue_priority' => $row_sql['issue_priority'], 
		'issue_status' => $row_sql['issue_status'], 
		'created_at' => $row_sql['created_at'], 
		'updated_at' => $row_sql['updated_at'], 

    );


    $form = $app['form.factory']->createBuilder('form', $initial_data);


	$form = $form->add('issue_id', 'text', array('required' => false));
	$form = $form->add('project_id', 'text', array('required' => false));
	$form = $form->add('user_id', 'choice', array('required' => true, 'label' => "Assigned to", 'choices' => $users));
	$form = $form->add('issue_title', 'text', array('required' => false));
	$form = $form->add('issue_desc', 'textarea', array('required' => false));
	$form = $form->add('issue_reporter', 'text', array('required' => false));
	$form = $form->add('created_at', 'text', array('required' => true));
	$form = $form->add('updated_at', 'text', array('required' => false));
	
	$form = $form->add('issue_type', 'choice', array('required' => true, 'choices' => array('BUG' => "Bug", 'NEW FEATURE' => "New Feature", 'IMPROVEMENT' => "Improvement")));
	$form = $form->add('issue_priority', 'choice', array('required' => true, 'choices' => array('MINOR' => 'Minor', 'MAJOR' => 'Major', 'BLOCKER' => 'Blocker', 'CRITICAL' => 'Critical')));
	$form = $form->add('issue_status', 'text', array('required' => true));	


    $form = $form->getForm();

    if("POST" == $app['request']->getMethod()){

        $form->handleRequest($app["request"]);

        if ($form->isValid()) {
            $data = $form->getData();

            $update_query = "UPDATE `issues` SET `issue_id` = ?, `project_id` = ?, `user_id` = ?, `issue_title` = ?, `issue_desc` = ?, `issue_reporter` = ?, `issue_type` = ?, `issue_priority` = ?, `issue_status` = ?, `created_at` = ?, `updated_at` = ? WHERE `id` = ?";
            $app['db']->executeUpdate($update_query, array($data['issue_id'], $data['project_id'], $data['user_id'], $data['issue_title'], $data['issue_desc'], $data['issue_reporter'], $data['issue_type'], $data['issue_priority'], $data['issue_status'], $data['created_at'], $data['updated_at'], $id));            


            $app['session']->getFlashBag()->add(
                'success',
                array(
                    'message' => 'issues edited!',
                )
            );
            return $app->redirect($app['url_generator']->generate('issues_list', array("project" => $data['project_id'])));

        }
    }

    return $app['twig']->render('issues/edit.html.twig', array(
        "form" => $form->createView(),
        "id" => $id
    ));
        
})
->bind('issues_edit');



$app->match('/issues/delete/{id}', function ($id) use ($app) {

    $find_sql = "SELECT * FROM `issues` WHERE `id` = ?";
    $row_sql = $app['db']->fetchAssoc($find_sql, array($id));

    if($row_sql){
        $delete_query = "DELETE FROM `issues` WHERE `id` = ?";
        $app['db']->executeUpdate($delete_query, array($id));

        $app['session']->getFlashBag()->add(
            'success',
            array(
                'message' => 'issues deleted!',
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

    return $app->redirect($app['url_generator']->generate('issues_list'));

})
->bind('issues_delete');






