<?php

use function PHPSTORM_META\type;

include_once "banco.php";

    $nome = $_POST["nome"];
    $cpf = $_POST["cpf"];
    $email = $_POST["email"];

    $json_file = [];
    $json_file = file_get_contents("https://www.fnde.gov.br/digef/rs/spba/publica/pessoa/1/10/".$cpf);
    
    echo json_decode($json_file,true);



?>