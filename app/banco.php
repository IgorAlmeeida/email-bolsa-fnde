<?php

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

	
?>