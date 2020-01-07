<?php 
    header('Content-Type:application/json');
    define('DEMO_CURL_VERBOSE', false);

    //授权KEY，自行填写
    $apiKey = "***";
    $secretKey = "***";

    //转语音参数
    // 下载的文件格式, 3：mp3(default) 4： pcm-16k 5： pcm-8k 6. wav
    $ttsArray = array(
        'tex' => '', //为避免+等特殊字符没有编码，此处需要2次urlencode。
        'per' => '0', //发音人选择, 基础音库：0为度小美，1为度小宇，3为度逍遥，4为度丫丫；精品音库：5为度小娇，103为度米朵，106为度博文，110为度小童，111为度小萌
        'spd' => '5', //语速，取值0-15，默认为5中语速
        'pit' => '5', //音调，取值0-15，默认为5中语调
        'vol' => '5', //音量，取值0-9，默认为5中音量
        'aue' => '3', //3为mp3格式(默认)； 4为pcm-16k；5为pcm-8k；6为wav（内容同pcm-16k）
        'cuid' => 'webapi', //用户唯一标识，用来计算UV值。建议填写能区分用户的机器 MAC 地址或 IMEI 码，长度为60字符以内
        'tok' => '', //开放平台获取到的开发者access_token
        'lan' => 'zh', //固定参数
        'ctp' => 1, //固定参数
        'key' => '' //设置密码，空为不使用
    );

    //判断是否需要密码并验证
    if( $ttsArray["key"] != '' && isset($_POST["key"]) && $ttsArray["key"] != $_POST["key"] ){        
        $tts_result = array('status'=> 'Please Input Right Password', 'path'=>'');
        echo json_encode($tts_result,JSON_UNESCAPED_UNICODE);
        exit();
    }


    //获取POST参数
    if (is_array($_POST)) {
        isset($_POST["tex"]) && $ttsArray["tex"] = urlencode($_POST["tex"]);
        isset($_POST["spd"]) && $ttsArray["spd"] = $_POST["spd"];
        isset($_POST["pit"]) && $ttsArray["pit"] = $_POST["pit"];
        isset($_POST["vol"]) && $ttsArray["vol"] = $_POST["vol"];
        isset($_POST["per"]) && $ttsArray["per"] = $_POST["per"];
        isset($_POST["cuid"]) && $ttsArray["cuid"] = $_POST["cuid"];
        isset($_POST["aue"]) && $ttsArray["aue"] = $_POST["aue"];
    }
    
    $g_has_error = true;

    function getToken($valueArray, $key, $secret)
    {
        $status = '';

        /** 公共模块获取token开始 */
        $auth_url = "https://openapi.baidu.com/oauth/2.0/token?grant_type=client_credentials&client_id=".$key."&client_secret=".$secret;
        //echo "Token URL auth_url is " . $auth_url . "\n";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $auth_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //信任任何证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // 检查证书中是否设置域名,0不验证
        curl_setopt($ch, CURLOPT_VERBOSE, DEMO_CURL_VERBOSE);
        $res = curl_exec($ch);

        if(curl_errno($ch))
        {
            print curl_error($ch);
        }
        curl_close($ch);

        //echo "Token URL response is " . $res . "\n";
        $response = json_decode($res, true);

        if (!isset($response['access_token'])){
            $status = "Error to Obtain Token\n";
            return array('status'=> $status, 'path'=>'');
        }
        if (!isset($response['scope'])){
            $status = "Error to Obtain Scopes\n";
            return array('status'=> $status, 'path'=>'');
        }

        if (!in_array('audio_tts_post',explode(" ", $response['scope']))){
            $status = "Do Not Have TTS Permission\n";
            //请至网页上应用内开通语音合成权限
            return array('status'=> $status, 'path'=>'');
        }
        $token = $response['access_token'];
        //echo "token = $token ; expireInSeconds: ${response['expires_in']}\n\n";
        /** 公共模块获取token结束 */
        $valueArray["tok"] = $token;


        /** 拼接参数开始 **/
        // tex=$text&lan=zh&ctp=1&cuid=$cuid&tok=$token&per=$per&spd=$spd&pit=$pit&vol=$vol
        $paramsStr =  http_build_query($valueArray);
        $url = 'http://tsn.baidu.com/text2audio';
        $urltest = $url . '?' . $paramsStr;
        //echo $urltest . "\n"; // 反馈请带上此url
        /** 拼接参数结束 **/


        global $g_has_error;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt ($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paramsStr);

        function read_header($ch, $header){
            global $g_has_error;

            $comps = explode(":", $header);
            // 正常返回的头部 Content-Type: audio/*
            // 有错误的如 Content-Type: application/json
            if (count($comps) >= 2){
                if (strcasecmp(trim($comps[0]), "Content-Type") == 0){
                    if (strpos($comps[1], "audio/") > 0 ){
                        //获取语音没有错误
                        $g_has_error = false;
                    }else{
                        $status = $header . " , Has Error !";
                    }
                }
            }
            return strlen($header);
        }

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'read_header');
        $data = curl_exec($ch);
        if(curl_errno($ch))
        {
            $status = curl_error($ch);
            //CURL出现错误返回
            return array('status'=> $status, 'path'=>'');
        }
        curl_close($ch);

        $formats = array(3 => 'mp3', 4 => 'pcm', 5 =>'pcm', 6 => 'wav');
        $format = $formats[$valueArray['aue']];

        //设置保存目录
        $dir_path = 'audio/';

        $file = $g_has_error ? "result.txt" : "result-". date('Ymd-His') . '.' . $format;
        //;
        if(file_put_contents($dir_path.$file, $data)){
            $status = "File Saved, Please Open It !";
            //正常返回音频文件
            return array('status'=> $status, 'path'=>$dir_path.$file);
        }else{
            //保存文件出现错误返回
            return array('status'=> 'Save File Error, Please Check Permission', 'path'=>'');
        }
        
    }

    if (!empty($ttsArray["tex"])) {
        $tts_result = getToken($ttsArray, $apiKey, $secretKey);
    }else{
        $tts_result = array('status'=> 'TTS Text is Empty', 'path'=>'');
    }

    //返回文件url，状态消息
    echo json_encode($tts_result,JSON_UNESCAPED_UNICODE);

    //删除3小时前的文件
    function del_old_file($dir, $del_time){
        $files = scandir($dir);
        foreach($files as $filename){
            $thisfile = $dir.'/'.$filename;
            
            if($thisfile != '.' && $thisfile != '..' && (time()-filemtime($thisfile)) > $del_time) {
                unlink($thisfile);
            }
        }
    }
    //运行删除, 保存时间：3600*3 ，php运行时才会执行删除
    del_old_file('audio', 3600*3);
 ?>