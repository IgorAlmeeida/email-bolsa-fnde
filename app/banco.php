<?php

<<<<<<< HEAD
	function getConexao(){
		try{
			$pdo = new PDO("pgsql:host=database-1.c88nbyo6r5kw.us-east-1.rds.amazonaws.com dbname=petbolsa user=postgres password=superpetuag");
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			return $pdo;
	
		}catch(PDOException $ex){
			echo $ex->getMessage();
	
		}
	}


	getConexao();
=======
function get_conexao(){
	$dsn = 'mysql:host=pet-bolsa-email.c88nbyo6r5kw.us-east-1.rds.amazonaws.com;dbname=bolsa_email_pet';
	$user = 'criador';
	$pass = 'superpetuag123';

	try{
		$pdo = new PDO($dsn, $user, $pass);
		return $pdo;
	} catch (PDOException $e){
		echo ("Error: ".$e->getMessage());
	}
}


>>>>>>> 490d6b965dd76d107b1487d8ed335331c9dbe1ba

	
?>