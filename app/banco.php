<?php

	// Conexao ao banco de dados

	define( 'MYSQL_HOST', 'pet-bolsa-email.c88nbyo6r5kw.us-east-1.rds.amazonaws.com' );
	define( 'MYSQL_USER', 'admin' );
	define( 'MYSQL_PASSWORD', 'igor12345678' );
	define( 'MYSQL_DB_NAME', 'bolsa_email_pet' );

	new PDO( 'mysql:host=' . MYSQL_HOST . ' port=3306 dbname=' . MYSQL_DB_NAME, MYSQL_USER, MYSQL_PASSWORD);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	
?>