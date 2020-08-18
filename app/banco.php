<?php

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



	
?>