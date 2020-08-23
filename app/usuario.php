<?php
    include 'banco.php';

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
        $conect = getConexao();
        $conect->beginTransaction();
        $consulta = $conect->prepare("SELECT count(cpf_pessoa) as qtt FROM pessoa WHERE cpf_pessoa = ?");
        $consulta->bindParam( 1, $cpf, PDO::PARAM_STR);
        $consulta->execute();

        echo ("foi");

        $result 		= $data->fetch( PDO::FETCH_ASSOC );
        $qtt            =$result['qtt'];

        if(intval($qtt) !== intval("0")){
            $conect->rollBack();
            echo '[{"result":"false","mensagem":"Pessoa já cadastrada no sevirço.","encod":"false"}]';
            return;
        }

        $data = $conect->prepare("INSERT INTO pessoa(nome_pessoa, email_pessoa, hash_pessoa, cpf_pessoa)VALUES (?, ?, ?, ?)");
        $data->bindParam( 1, $nome, PDO::PARAM_STR);
        $data->bindParam( 2, $email, PDO::PARAM_STR);
        $data->bindParam( 3, $hash, PDO::PARAM_STR);
        $data->bindParam( 4, $cpf, PDO::PARAM_STR);
        $data->execute();

        $data->commit();

        echo '[{"result":"True","mensagem":"Pessoa já cadastrada com sucesso.","encod":"false"}]';
        return;

    } catch ( Exception $e ) {
        $conect->rollBack();
        return '[{"result":"false","p1":"' . base64_encode( $e->getMessage() ) . '","encod":"true"}]';
    }
    






?>