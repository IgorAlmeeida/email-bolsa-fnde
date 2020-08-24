<?php
    include ("email.php");

    $nome = $_POST["nome"];
    $cpf = $_POST["cpf"];
    $email = $_POST["email"];

    $json_file = [];
    $json_file = file_get_contents("https://www.fnde.gov.br/digef/rs/spba/publica/pessoa/1/10/".$cpf);

    $obj = json_decode( $json_file);

    if($obj->totalHits == 0){
        echo '[{"result":"false","mensagem":"Pessoa não encontrara no banco de dados do FNDE.","encod":"false"}]';
        return;
    }
    
    $array = $obj->pessoas;
    $hash = $array[0]->hash;

    try{

        $conect = new PDO('mysql:host=database-2.c88nbyo6r5kw.us-east-1.rds.amazonaws.com;dbname=petbolsa', "site", "superpetuag1");    

        $conect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
        $conect->beginTransaction();
        $consulta = $conect->prepare("SELECT count(cpf_pessoa) as qtt FROM pessoa WHERE cpf_pessoa = :cpf", array(PDO::CURSOR_SCROLL));
        $consulta->bindParam(':cpf', $cpf, PDO::PARAM_STR);
        $consulta->execute();


        $result 		= $consulta->fetch( PDO::FETCH_ASSOC );
        $qtt            =$result['qtt'];

        if(intval($qtt) !== intval("0")){
            $conect->rollBack();
            echo '[{"result":"false","mensagem":"Pessoa já cadastrada no sevirço.","encod":"false"}]';
            return;
        }

        $consulta = $conect->prepare("INSERT INTO pessoa(nome_pessoa, email_pessoa, hash_pessoa, cpf_pessoa)VALUES (?, ?, ?, ?)");
        $consulta->bindParam( 1, $nome, PDO::PARAM_STR);
        $consulta->bindParam( 2, $email, PDO::PARAM_STR);
        $consulta->bindParam( 3, $hash, PDO::PARAM_STR);
        $consulta->bindParam( 4, $cpf, PDO::PARAM_STR);
        $consulta->execute();

        $conect->commit();
        echo (enviar_email_cadastro($nome, $email, $hash, $cpf));

        echo '[{"result":"True","mensagem":"Pessoa cadastrada com sucesso.","encod":"false"}]';
        
        return;

    } catch ( Exception $e ) {
        $conect->rollBack();
        return '[{"result":"false","mensagem":"' . base64_encode( $e->getMessage() ) . '","encod":"true"}]';
    }
    
    





?>