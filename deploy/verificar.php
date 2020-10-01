<?php

require_once('src/PHPMailer.php');
require_once('src/SMTP.php');
require_once('src/Exception.php');
 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$agora = date('d/m/Y H:i');
echo $agora;

echo "\nConectando ao Banco de dados\n";
//sleep (180);

$agora = date('d/m/Y H:i');
echo $agora;

$pdo = new PDO('mysql:host=database-2.c88nbyo6r5kw.us-east-1.rds.amazonaws.com;dbname=petbolsa', "site", "superpetuag1");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 

echo "\nConectado ao Banco de dados\n";

echo "Buscando pessoas\n";
$array_pessoas = pegar_hash_banco();

echo "Realizando rotinas de busca e atualização no banco de dados\n";
for ($i = 0; $i < count($array_pessoas); $i++){
    $json_file = file_get_contents("https://www.fnde.gov.br/digef/rs/spba/publica/pagamento/".$array_pessoas[$i]['hash_pessoa']);

    $obj = json_decode($json_file);
    $programas = $obj->programas;

    $pagamentos = pegar_pagamentos($programas);

    echo "Verificando dados de ".$array_pessoas[$i]['nome_pessoa']."\n";

    if (verificar_pagamentos_banco($array_pessoas[$i]['id_pessoa'])){
        inserir_pagamento($array_pessoas[$i]['id_pessoa'], $pagamentos[count($pagamentos)-1], true);
        //echo(json_encode($pagamentos[count($pagamentos)-2]));
    }
    else{
        inserir_pagamento($array_pessoas[$i]['id_pessoa'], $pagamentos[count($pagamentos)-1], false);
       // echo(json_encode($pagamentos[count($pagamentos)-1]));
    }
}

echo "Verificando email pendentes\n";

$linha = verificar_email_pendentes();

foreach ($linha as $result){
    $is_enviado = enviar_email($result['email_pessoa'], $result['nome_pessoa'], $result['cpf_pessoa'], $result['hash_pessoa'], $result['bolsa'], $result['valor_bolsa']);

    if ($is_enviado){
        atualizar_bolsa($result['id_bolsa']);
    }

}

echo "Rotinas concluídas.\n";
exit;

function atualizar_bolsa($id_bolsa){

    try {

        $conect = $GLOBALS["pdo"];
        $conect->beginTransaction();
    
        $consulta = $conect->prepare("UPDATE bolsa SET is_enviado = TRUE WHERE id_bolsa = ?");
        $consulta->bindParam(1, $id_bolsa, PDO::PARAM_INT);
        $consulta->execute();
    
        $conect->commit();
    } catch (Exception $e ) {
        $conect->rollBack();
        echo $e->getMessage();
    }
}

function veriricar_ultimo_pagamento($id_pessoa){
    try{

        $conect = $GLOBALS["pdo"];
        //$conect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 

        $conect->beginTransaction();
        $consulta = $conect->prepare("SELECT * FROM bolsa WHERE id_pessoa = :id_pessoa ORDER BY id_bolsa DESC" );
        $consulta->bindParam(':id_pessoa', $id_pessoa, PDO::PARAM_INT);
        $consulta->execute();

        $result =  $consulta->fetch(PDO::FETCH_ASSOC);
        $last_bolsa = $result["bolsa"];

        $conect->commit();
        return $last_bolsa;

    } catch (Exception $e ) {
        $conect->rollBack();
        return $e->getMessage();
    }
}

function inserir_pagamento($id_pessoa, $pagamento, $is_enviado){
    if($is_enviado){
        try{

            $conect = $GLOBALS["pdo"];
            $conect->beginTransaction();       
            $consulta = $conect->prepare("INSERT INTO bolsa (id_pessoa, bolsa, ordem_bancaria, valor_bolsa, is_enviado)
            VALUES(?,?,?,?,?)");

            if(strpos($pagamento->valor, "." )){
            	$valor = str_replace('.', '', $pagamento->valor);
            	$valor = strval($valor);
            }
            else {
            	$valor = strval($pagamento->valor);
            }

            $consulta->bindParam( 1, $id_pessoa,PDO::PARAM_INT);
            $consulta->bindParam( 2, $pagamento->referencia, PDO::PARAM_STR);
            $consulta->bindParam( 3, $pagamento->ordermBancaria, PDO::PARAM_STR);
            $consulta->bindParam( 4, $valor, PDO::PARAM_STR);
            $consulta->bindParam( 5, $is_enviado, PDO::PARAM_BOOL);
            $consulta->execute();
    
            $conect->commit();                
            
        } catch ( Exception $e ) {
            $conect->rollBack();
            $e->getMessage();
        }
    }
    else{
        $last_bolsa = veriricar_ultimo_pagamento($id_pessoa);
        if ($last_bolsa == $pagamento->referencia){
            return;
        }
        try{
            $conect = $GLOBALS["pdo"];  
            $conect->beginTransaction();     
            $consulta = $conect->prepare("INSERT INTO bolsa (id_pessoa, bolsa, ordem_bancaria, valor_bolsa, is_enviado)
            VALUES(?,?,?,?,?)");
            $valor = str_replace('.', '', $pagamento->valor);
            $consulta->bindParam( 1, $id_pessoa, PDO::PARAM_INT);
            $consulta->bindParam( 2, $pagamento->referencia, PDO::PARAM_STR);
            $consulta->bindParam( 3, $pagamento->ordermBancaria, PDO::PARAM_STR);
            $consulta->bindParam( 4, $valor, PDO::PARAM_STR);
            $consulta->bindParam( 5, $is_enviado, PDO::PARAM_BOOL);
            $consulta->execute();
    
            $conect->commit();                
            
        } catch ( Exception $e ) {
            $conect->rollBack();
            $e->getMessage();
        }

    }
} 

function verificar_pagamentos_banco($id_pessoa){
    try{

        $conect = $GLOBALS["pdo"];
        $conect->beginTransaction();
        $consulta = $conect->prepare("SELECT count(id_bolsa) as qtt FROM bolsa WHERE id_pessoa = :id_pessoa");
        $consulta->bindParam(':id_pessoa', $id_pessoa, PDO::PARAM_INT);
        $consulta->execute();

        $result =  $consulta->fetch(PDO::FETCH_ASSOC);
        $qtt = $result["qtt"];

        $conect->commit();

        if (intval($qtt) === intval("0")){
            return true;
        }

        return false;

    } catch (Exception $e ) {
        $conect->rollBack();
        return $e->getMessage();
    }
}

function pegar_hash_banco(){
    
    try{

        $conect = $GLOBALS["pdo"];    
        $conect->beginTransaction();
        $consulta = $conect->prepare("SELECT * FROM pessoa");
        $consulta->execute();

        $result = [];

        while($linha = $consulta->fetch(PDO::FETCH_ASSOC)){
            $result[] = $linha;
        }   
        
        $conect->commit();

        return $result;

    } catch (Exception $e ) {
        $conect->rollBack();
        return $e->getMessage();
    }
}

function pegar_pagamentos($programas){
    for ($i = 0; $i < count($programas); $i++){
        if($programas[$i]->nome == "PET-ALUNO" || $programas[$i]->nome == "PET-TUTOR"){ 

            $entirades = $programas[$i]->entidades; //pegando as entidades

            $new_json = json_decode(tratar_json_mal_formado(json_encode($entirades, JSON_UNESCAPED_SLASHES))); //alterando as entidades para acessar o campo vaiável

            $entidade = $new_json->pppppppppppppp; //entrando na entidade

            $funcao = $entidade->funcoes; //entrando na funcao

            $funcao_formatada = json_decode(tratar_json_mal_formado_funcao(json_encode($funcao, JSON_UNESCAPED_SLASHES)));

            $pagamentos = $funcao_formatada->pp->pagamentos;

            return $pagamentos;

        }

    }
}

function tratar_json_mal_formado_funcao($string){
    for($i = 0 ; $i < strlen($string); $i++){
        if($string[$i] === chr(34)){
            for ($j = 1 ; $j < 3; $j++){
                if ($string[$i+$j] === chr(34)){
                    break;
                }
                $string[$i+$j] = "p";
            }
        break;
        }
    }
    return $string;    
}


function tratar_json_mal_formado($string){
    for($i = 0 ; $i < strlen($string); $i++){
        if($string[$i] === chr(34)){
            for ($j = 1 ; $j < 15; $j++){
                if ($string[$i+$j] === chr(34)){
                break;
                }
                $string[$i+$j] = "p";
            }
        break;
        }
    }
    return $string;

}

function verificar_email_pendentes(){
    try {
        $conect = $GLOBALS["pdo"];
        $conect->beginTransaction();       
        $consulta = $conect->prepare("SELECT * 
        FROM  bolsa as b 
        JOIN pessoa as p ON b.id_pessoa = p.id_pessoa 
        where b.is_enviado = FALSE");
        $consulta->execute();
    
        $result = [];
    
        while($linha = $consulta->fetch(PDO::FETCH_ASSOC)){
            $result[] = $linha;
        }

        $conect->commit();
        
        return $result;
    

    } catch ( Exception $e ) {
        $conect->rollBack();
        $e->getMessage();
    }

}

function enviar_email($email, $nome, $cpf, $hash, $mes, $valor){
 
    try {
        $mail = new PHPMailer(true);
        //$mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'bolsapet.criativacao@gmail.com';
        $mail->Password = 'pzdovlkmdriratkw';
        $mail->Port = 587;
 
        $mail->setFrom('bolsapet.criativacao@gmail.com');
        $mail->addAddress($email);
    
        $mail->isHTML(true);
        $mail->Subject = 'Bolsa FNDE - PET';
        $mail->Body = "<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>
        <html xmlns='http://www.w3.org/1999/xhtml' xmlns:o='urn:schemas-microsoft-com:office:office' style='width:100%;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;padding:0;Margin:0'>
        <head> 
        <meta charset='UTF-8'> 
        <meta content='width=device-width, initial-scale=1' name='viewport'> 
        <meta name='x-apple-disable-message-reformatting'> 
        <meta http-equiv='X-UA-Compatible' content='IE=edge'> 
        <meta content='telephone=no' name='format-detection'> 
        <title>Novo modelo de e-mail 2020-08-16</title> 
        <!--[if (mso 16)]>
            <style type='text/css'>
            a {text-decoration: none;}
            </style>
            <![endif]--> 
        <!--[if gte mso 9]><style>sup { font-size: 100% !important; }</style><![endif]--> 
        <!--[if gte mso 9]>
        <xml>
            <o:OfficeDocumentSettings>
            <o:AllowPNG></o:AllowPNG>
            <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
        <![endif]--> 
        <!--[if !mso]><!-- --> 
        <link href='https://fonts.googleapis.com/css?family=Open+Sans:400,400i,700,700i' rel='stylesheet'> 
        <!--<![endif]--> 
        <style type='text/css'>
        @media only screen and (max-width:600px) {p, ul li, ol li, a { font-size:16px!important; line-height:150%!important } h1 { font-size:32px!important; text-align:center; line-height:120%!important } h2 { font-size:26px!important; text-align:center; line-height:120%!important } h3 { font-size:20px!important; text-align:center; line-height:120%!important } h1 a { font-size:32px!important } h2 a { font-size:26px!important } h3 a { font-size:20px!important } .es-menu td a { font-size:16px!important } .es-header-body p, .es-header-body ul li, .es-header-body ol li, .es-header-body a { font-size:16px!important } .es-footer-body p, .es-footer-body ul li, .es-footer-body ol li, .es-footer-body a { font-size:16px!important } .es-infoblock p, .es-infoblock ul li, .es-infoblock ol li, .es-infoblock a { font-size:12px!important } *[class='gmail-fix'] { display:none!important } .es-m-txt-c, .es-m-txt-c h1, .es-m-txt-c h2, .es-m-txt-c h3 { text-align:center!important } .es-m-txt-r, .es-m-txt-r h1, .es-m-txt-r h2, .es-m-txt-r h3 { text-align:right!important } .es-m-txt-l, .es-m-txt-l h1, .es-m-txt-l h2, .es-m-txt-l h3 { text-align:left!important } .es-m-txt-r img, .es-m-txt-c img, .es-m-txt-l img { display:inline!important } .es-button-border { display:inline-block!important } a.es-button { font-size:16px!important; display:inline-block!important; border-width:15px 30px 15px 30px!important } .es-btn-fw { border-width:10px 0px!important; text-align:center!important } .es-adaptive table, .es-btn-fw, .es-btn-fw-brdr, .es-left, .es-right { width:100%!important } .es-content table, .es-header table, .es-footer table, .es-content, .es-footer, .es-header { width:100%!important; max-width:600px!important } .es-adapt-td { display:block!important; width:100%!important } .adapt-img { width:100%!important; height:auto!important } .es-m-p0 { padding:0px!important } .es-m-p0r { padding-right:0px!important } .es-m-p0l { padding-left:0px!important } .es-m-p0t { padding-top:0px!important } .es-m-p0b { padding-bottom:0!important } .es-m-p20b { padding-bottom:20px!important } .es-mobile-hidden, .es-hidden { display:none!important } tr.es-desk-hidden, td.es-desk-hidden, table.es-desk-hidden { display:table-row!important; width:auto!important; overflow:visible!important; float:none!important; max-height:inherit!important; line-height:inherit!important } .es-desk-menu-hidden { display:table-cell!important } table.es-table-not-adapt, .esd-block-html table { width:auto!important } table.es-social { display:inline-block!important } table.es-social td { display:inline-block!important } }
        #outlook a {
            padding:0;
        }
        .ExternalClass {
            width:100%;
        }
        .ExternalClass,
        .ExternalClass p,
        .ExternalClass span,
        .ExternalClass font,
        .ExternalClass td,
        .ExternalClass div {
            line-height:100%;
        }
        .es-button {
            mso-style-priority:100!important;
            text-decoration:none!important;
        }
        a[x-apple-data-detectors] {
            color:inherit!important;
            text-decoration:none!important;
            font-size:inherit!important;
            font-family:inherit!important;
            font-weight:inherit!important;
            line-height:inherit!important;
        }
        .es-desk-hidden {
            display:none;
            float:left;
            overflow:hidden;
            width:0;
            max-height:0;
            line-height:0;
            mso-hide:all;
        }
        </style> 
        </head> 
        <body style='width:100%;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;padding:0;Margin:0'> 
        <div class='es-wrapper-color' style='background-color:#EEEEEE'> 
        <!--[if gte mso 9]>
                    <v:background xmlns:v='urn:schemas-microsoft-com:vml' fill='t'>
                        <v:fill type='tile' color='#eeeeee'></v:fill>
                    </v:background>
                <![endif]--> 
        <table class='es-wrapper' width='100%' cellspacing='0' cellpadding='0' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;padding:0;Margin:0;width:100%;height:100%;background-repeat:repeat;background-position:center top'> 
            <tr style='border-collapse:collapse'> 
            <td valign='top' style='padding:0;Margin:0'> 
            <table cellpadding='0' cellspacing='0' class='es-header' align='center' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%;background-color:transparent;background-repeat:repeat;background-position:center top'> 
                <tr style='border-collapse:collapse'> 
                <td align='center' style='padding:0;Margin:0'> 
                <table class='es-header-body' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#044767;width:600px' cellspacing='0' cellpadding='0' bgcolor='#044767' align='center'> 
                    <tr style='border-collapse:collapse'> 
                    <td align='left' style='padding:35px;Margin:0'> 
                    <table width='100%' cellspacing='0' cellpadding='0' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px'> 
                        <tr style='border-collapse:collapse'> 
                        <td valign='top' align='center' style='padding:0;Margin:0;width:530px'> 
                        <table width='100%' cellspacing='0' cellpadding='0' role='presentation' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px'> 
                            <tr style='border-collapse:collapse'> 
                            <td class='es-m-txt-c' align='center' style='padding:0;Margin:0'><h1 style='Margin:0;line-height:36px;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;font-size:36px;font-style:normal;font-weight:bold;color:#FFFFFF'>Bolsa FNDE</h1></td> 
                            </tr> 
                        </table></td> 
                        </tr> 
                    </table></td> 
                    </tr> 
                </table></td> 
                </tr> 
            </table> 
            <table class='es-content' cellspacing='0' cellpadding='0' align='center' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%'> 
                <tr style='border-collapse:collapse'> 
                <td align='center' style='padding:0;Margin:0'> 
                <table class='es-content-body' cellspacing='0' cellpadding='0' bgcolor='#ffffff' align='center' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#FFFFFF;width:600px'> 
                    <tr style='border-collapse:collapse'> 
                    <td align='left' style='padding:0;Margin:0;padding-left:35px;padding-right:35px;padding-top:40px'> 
                    <table width='100%' cellspacing='0' cellpadding='0' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px'> 
                        <tr style='border-collapse:collapse'> 
                        <td valign='top' align='center' style='padding:0;Margin:0;width:530px'> 
                        <table width='100%' cellspacing='0' cellpadding='0' role='presentation' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px'> 
                            <tr style='border-collapse:collapse'> 
                            <td align='center' style='Margin:0;padding-top:25px;padding-bottom:25px;padding-left:35px;padding-right:35px;font-size:0px'><a target='_blank' href='https://viewstripo.email/' style='-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;font-size:16px;text-decoration:none;color:#ED8E20'><img src='https://ijmyfh.stripocdn.email/content/guids/c0c9c2b3-644a-4f60-8040-f3a76b2ee3cb/images/47461597594729465.png' alt style='display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic' width='120'></a></td> 
                            </tr> 
                            <tr style='border-collapse:collapse'> 
                            <td align='center' style='padding:0;Margin:0;padding-bottom:10px'><h2 style='Margin:0;line-height:36px;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;font-size:30px;font-style:normal;font-weight:bold;color:#333333'>Deposito realizado</h2></td> 
                            </tr> 
                            <tr style='border-collapse:collapse'> 
                            <td align='center' style='padding:0;Margin:0;padding-top:15px;padding-bottom:20px'><p style='Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-size:16px;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#777777'>A sua bolsa do FNDE esta disponível para saque.</p></td> 
                            </tr> 
                        </table></td> 
                        </tr> 
                    </table></td> 
                    </tr> 
                </table></td> 
                </tr> 
            </table> 
            <table class='es-content' cellspacing='0' cellpadding='0' align='center' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%'> 
                <tr style='border-collapse:collapse'> 
                <td align='center' style='padding:0;Margin:0'> 
                <table class='es-content-body' cellspacing='0' cellpadding='0' bgcolor='#ffffff' align='center' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#FFFFFF;width:600px'> 
                    <tr style='border-collapse:collapse'> 
                    <td align='left' style='padding:0;Margin:0;padding-top:20px;padding-left:35px;padding-right:35px'> 
                    <table width='100%' cellspacing='0' cellpadding='0' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px'> 
                        <tr style='border-collapse:collapse'> 
                        <td valign='top' align='center' style='padding:0;Margin:0;width:530px'> 
                        <table width='100%' cellspacing='0' cellpadding='0' role='presentation' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px'> 
                            <tr style='border-collapse:collapse'> 
                            <td bgcolor='#eeeeee' align='left' style='Margin:0;padding-top:10px;padding-bottom:10px;padding-left:10px;padding-right:10px'> 
                            <table style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;width:500px' class='cke_show_border' cellspacing='1' cellpadding='1' border='0' align='left' role='presentation'> 
                                <tr style='border-collapse:collapse'> 
                                <td width='80%' style='padding:0;Margin:0'><h4 style='Margin:0;line-height:120%;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif'>Dados</h4></td> 
                                <td width='20%' style='padding:0;Margin:0'><h4 style='Margin:0;line-height:120%;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif'><br></h4></td> 
                                </tr> 
                            </table></td> 
                            </tr> 
                        </table></td> 
                        </tr> 
                    </table></td> 
                    </tr> 
                    <tr style='border-collapse:collapse'> 
                    <td align='left' style='padding:0;Margin:0;padding-left:35px;padding-right:35px'> 
                    <table width='100%' cellspacing='0' cellpadding='0' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px'> 
                        <tr style='border-collapse:collapse'> 
                        <td valign='top' align='center' style='padding:0;Margin:0;width:530px'> 
                        <table width='100%' cellspacing='0' cellpadding='0' role='presentation' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px'> 
                            <tr style='border-collapse:collapse'> 
                            <td align='left' style='Margin:0;padding-top:10px;padding-bottom:10px;padding-left:10px;padding-right:10px'> 
                            <table style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;width:500px' class='cke_show_border' cellspacing='1' cellpadding='1' border='0' align='left' role='presentation'> 
                                <tr style='border-collapse:collapse'> 
                                <td style='padding:5px 10px 5px 0;Margin:0' width='50%' align='left'>Nome</td> 
                                <td style='padding:5px 0;Margin:0' width='50%' align='left'><p style='Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-size:16px;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#333333'>".$nome."</p></td> 
                                </tr> 
                                <tr style='border-collapse:collapse'> 
                                <td style='padding:5px 10px 5px 0;Margin:0' width='50%' align='left'><p style='Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-size:16px;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#333333'>CPF</p></td> 
                                <td style='padding:5px 0;Margin:0' width='50%' align='left'><p style='Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-size:16px;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#333333'>".$cpf."</p></td> 
                                </tr> 
                                <tr style='border-collapse:collapse'> 
                                <td style='padding:5px 10px 5px 0;Margin:0' width='50%' align='left'><p style='Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-size:16px;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#333333'>Hash</p></td> 
                                <td style='padding:5px 0;Margin:0' width='50%' align='left'><p style='Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-size:16px;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#333333'>".$hash."</p></td> 
                                </tr> 
                            </table></td> 
                            </tr> 
                        </table></td> 
                        </tr> 
                    </table></td> 
                    </tr> 
                    <tr style='border-collapse:collapse'> 
                    <td align='left' style='padding:0;Margin:0;padding-top:10px;padding-left:35px;padding-right:35px'> 
                    <table width='100%' cellspacing='0' cellpadding='0' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px'> 
                        <tr style='border-collapse:collapse'> 
                        <td valign='top' align='center' style='padding:0;Margin:0;width:530px'> 
                        <table style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;border-top:3px solid #EEEEEE;border-bottom:3px solid #EEEEEE' width='100%' cellspacing='0' cellpadding='0' role='presentation'> 
                            <tr style='border-collapse:collapse'> 
                            <td align='left' style='Margin:0;padding-left:10px;padding-right:10px;padding-top:15px;padding-bottom:15px'> 
                            <table style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;width:500px' class='cke_show_border' cellspacing='1' cellpadding='1' border='0' align='left' role='presentation'> 
                                <tr style='border-collapse:collapse'> 
                                <td width='80%' style='padding:0;Margin:0'><h4 style='Margin:0;line-height:120%;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif'>MÊS</h4></td> 
                                <td width='20%' style='padding:0;Margin:0'><h4 style='Margin:0;line-height:120%;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif'>".$mes."</h4></td> 
                                </tr> 
                            </table></td> 
                            </tr> 
                            <tr style='border-collapse:collapse'> 
                            <td align='left' style='Margin:0;padding-left:10px;padding-right:10px;padding-top:15px;padding-bottom:15px'> 
                            <table style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;width:500px' class='cke_show_border' cellspacing='1' cellpadding='1' border='0' align='left' role='presentation'> 
                                <tr style='border-collapse:collapse'> 
                                <td width='80%' style='padding:0;Margin:0'><h4 style='Margin:0;line-height:120%;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif'>VALOR</h4></td> 
                                <td width='20%' style='padding:0;Margin:0'><h4 style='Margin:0;line-height:120%;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif'>".$valor."</h4></td> 
                                </tr> 
                            </table></td> 
                            </tr> 
                        </table></td> 
                        </tr> 
                    </table></td> 
                    </tr> 
                </table></td> 
                </tr> 
            </table> 
            <table cellpadding='0' cellspacing='0' class='es-content' align='center' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%'> 
                <tr style='border-collapse:collapse'> 
                <td align='center' style='padding:0;Margin:0'> 
                <table class='es-content-body' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#1B9BA3;width:600px' cellspacing='0' cellpadding='0' bgcolor='#1b9ba3' align='center'> 
                    <tr style='border-collapse:collapse'> 
                    <td align='left' style='Margin:0;padding-top:35px;padding-bottom:35px;padding-left:35px;padding-right:35px'> 
                    <table width='100%' cellspacing='0' cellpadding='0' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px'> 
                        <tr style='border-collapse:collapse'> 
                        <td valign='top' align='center' style='padding:0;Margin:0;width:530px'> 
                        <table width='100%' cellspacing='0' cellpadding='0' role='presentation' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px'> 
                            <tr style='border-collapse:collapse'> 
                            <td align='center' style='padding:0;Margin:0;padding-top:25px'><h2 style='Margin:0;line-height:24px;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;font-size:24px;font-style:normal;font-weight:bold;color:#FFFFFF'>PET CRIATIVAÇÃO - UFAPE</h2></td> 
                            </tr> 
                        </table></td> 
                        </tr> 
                    </table></td> 
                    </tr> 
                </table></td> 
                </tr> 
            </table> 
            <table class='es-content' cellspacing='0' cellpadding='0' align='center' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%'> 
                <tr style='border-collapse:collapse'> 
                <td align='center' style='padding:0;Margin:0'> 
                <table class='es-content-body' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:transparent;width:600px' cellspacing='0' cellpadding='0' align='center'> 
                    <tr style='border-collapse:collapse'> 
                    <td align='left' style='Margin:0;padding-left:20px;padding-right:20px;padding-top:30px;padding-bottom:30px'> 
                    <table width='100%' cellspacing='0' cellpadding='0' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px'> 
                        <tr style='border-collapse:collapse'> 
                        <td valign='top' align='center' style='padding:0;Margin:0;width:560px'> 
                        <table width='100%' cellspacing='0' cellpadding='0' style='mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px'> 
                            <tr style='border-collapse:collapse'> 
                            <td align='center' style='padding:0;Margin:0;display:none'></td> 
                            </tr> 
                        </table></td> 
                        </tr> 
                    </table></td> 
                    </tr> 
                </table></td> 
                </tr> 
            </table></td> 
            </tr> 
        </table> 
        </div>  
        </body>
        </html>";
        //$mail->AltBody = 'Chegou o email teste do Canal TI';
 
        if($mail->send()) {
            return true;
        } else {
            return false;
        }
    } catch (Exception $e) {
        echo "Erro ao enviar mensagem: {$mail->ErrorInfo}";
    }
}


?>