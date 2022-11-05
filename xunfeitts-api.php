<?php
    header('Content-Type:application/json');

    define("XF_APPID", "5e10aedc");  //your APPID
    define("XF_SECTET", "0ba2fb674c51eadae5f5e0cf831b526b");  //your SECTET
    define("XF_KEY", "b2b9891f92ea09b8d9f43ee8be781fb4");  //your KEY

    $ttsArray = array(
        'key' => '' //设置密码，空为不使用
    );

    //判断是否需要密码并验证
    if( 
        ($ttsArray["key"] != '' && isset($_POST["key"]) && $ttsArray["key"] != $_POST["key"]) ||
        ($ttsArray["key"] != '' && !isset($_POST["key"]))
    ){        
        $tts_result = array('status'=> 'Please Input Right Password', 'path'=>'');
        echo json_encode($tts_result,JSON_UNESCAPED_UNICODE);
        exit();
    }


    function ttsJson($tex){
        $common = ['app_id' => XF_APPID];
        $business = [];
        $business['aue'] = "raw";
        $business['auf'] = "audio/L16;rate=16000";
        $business['vcn'] = "xiaoyuan";
        $business['tte'] = "UTF8";

        $data['text'] = base64_encode($tex);
        $data['status'] = 2;

        $send_data = ['common' => $common, 'business' => $business, 'data' => $data];
        return  json_encode($send_data);
    }

    function creatUrl($host = "ws-api.xfyun.cn")
    {
        $url = 'wss://tts-api.xfyun.cn/v2/tts';

        # 生成RFC1123格式的时间戳
        $date = gmdate('D, d M Y H:i:s') . ' GMT';

        # 拼接字符串
        $signature_origin = "host: " . $host . "\n" . "date: " . $date . "\n" . "GET " . "/v2/tts " . "HTTP/1.1";

        # 进行hmac-sha256进行加密
        $signature_sha = hash_hmac('sha256', $signature_origin, XF_SECTET, true);
        $signature_sha = base64_encode($signature_sha);

        $authorization_origin = 'api_key="' . XF_KEY . '",algorithm="hmac-sha256",headers="host date request-line",signature="' . $signature_sha . '"';
        $authorization = base64_encode($authorization_origin);

        $path = $url . '?authorization=' . urlencode($authorization). '&date=' . urlencode($date) . '&host=' . urlencode($host);

        return $path;

    }

    $xunfei_tts_result = array('status'=> 'Get Url Success', 'path'=>creatUrl(), 'appid'=>XF_APPID);
    echo json_encode($xunfei_tts_result,JSON_UNESCAPED_UNICODE);
    

 ?>
