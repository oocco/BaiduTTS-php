<?php

header('Content-Type:application/json');

class AzureTTS{
    private $access_token = "";
    private $subscription_key = "****************"; //your subscription_key

    private $key = "";

    public $local_name = "westus";

    public $ttsArray = array(
        'gender' => 'Female',
        'name' => 'zh-CN-XiaoxiaoNeural',
        'rate' => '1',
        'volume' => '100',
        'stylelist' => 'newscast',
        'locale' => 'zh-CN'
    );

    private $ch;
    public $status = array('status'=> '', 'path'=>'');

    private $token_filename = "audio/azure_token_cpkwwbauyjscqdvfhpllf.php";
    private $save_file_dir = "audio/";

    private function getToken()
    {   
        $this->delOldFile('audio', 3600);

        $this->ch = curl_init();
        $fetch_token_url = 'https://'. $this->local_name .'.api.cognitive.microsoft.com/sts/v1.0/issueToken';

        curl_setopt($this->ch, CURLOPT_URL, $fetch_token_url);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);

        $headers = array(
            "Ocp-Apim-Subscription-Key: ".$this->subscription_key,
            "Host: " . $this->local_name . ".api.cognitive.microsoft.com",
            "Content-type: application/x-www-form-urlencoded",
            "Content-Length: 0",
            "Connection: Keep-Alive"
        );

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

        $data = curl_exec($this->ch);
        
        if(curl_errno($this->ch)){
            $this->status["status"] = curl_error($this->ch);
            curl_close($this->ch);
            return false;
        }

        $http_code = curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);
        if (!$http_code == "200") {
            $this->status["status"] = "Error Code: " . $http_code;
            curl_close($this->ch);
            return false;
        }

        $data = str_replace(" ","",$data);

        $save_token_array = array(
            "time" => time(),
            "token" => $data
        );

        if(false!==fopen($this->token_filename,'w+')){
            file_put_contents($this->token_filename, serialize($save_token_array));
            $this->status["status"] = "Save Token Success!";
        }

        $this->access_token = $data;
        curl_close($this->ch);

        return true;
    }

    private function checkToken()
    {
        $read_file = fopen($this->token_filename,'r');
        $cacheArray = unserialize(fread($read_file,filesize($this->token_filename))); 

        if (empty($cacheArray['time'])){
            return $this->getToken();
        }

        $time_now = number_format(time(), 0, "", "");
        $cacheArray['time'] = number_format($cacheArray['time'], 0, "", "");


        if ($time_now > $cacheArray['time'] && ($time_now - $cacheArray['time']) > 540) {
            return $this->getToken();
        }

        $this->access_token = $cacheArray["token"];
        return true;
    }

    public function getList()
    {   
        $this->ch = curl_init();
        $voices_list_url = 'https://'. $this->local_name .'.tts.speech.microsoft.com/cognitiveservices/voices/list';
        curl_setopt($this->ch, CURLOPT_URL, $voices_list_url);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 10);

        $headers = array(
            "Ocp-Apim-Subscription-Key: ".$this->subscription_key,
            "Host: ". $this->local_name .".tts.speech.microsoft.com",
        );
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

        $data = curl_exec($this->ch);
        if(curl_errno($this->ch)){
            $this->status["status"] = curl_error($this->ch);
            curl_close($this->ch);
            return $this->returnStatus();
        }

        $http_code = curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);
        if (!$http_code == "200") {
            $this->status["status"] = "Error Code: " . $http_code;
            curl_close($this->ch);
            return $this->returnStatus();
        }
        
        curl_close($this->ch);

        $this->status["status"] = json_decode($data);
        $this->status["path"] = "getlist";
        return $this->returnStatus();
    }

    public function textToVoice($text, $key)
    {
        if(!$this->verificationKey($key)){
            return $this->returnStatus();
        }

        if (empty($text)) {
            $this->status["status"] =  'TTS Text is Empty';
            return $this->returnStatus();
        }

        if (!$this->checkToken()) {
            return $this->returnStatus();
        }
        $this->ch = curl_init();

        $constructed_url = 'https://'. 'eastus' .'.tts.speech.microsoft.com/cognitiveservices/v1';
        curl_setopt($this->ch, CURLOPT_URL, $constructed_url);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 10);
        
        $body = "
        <speak version='1.0' 
        xml:lang='" . $this->ttsArray["locale"] . "'><voice 
        xml:lang='" . $this->ttsArray["locale"] . "' 
        xml:gender='" . $this->ttsArray["gender"] . "' 
        name='" . $this->ttsArray["name"] . "' 
        stylelist='" . $this->ttsArray["stylelist"] . "'>
        <prosody 
        rate='" . $this->ttsArray["rate"] . "' 
        volume='" . $this->ttsArray["volume"] . "'>"
         . $text . 
         "</prosody>
         </voice>
         </speak>";

        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $body);

        $headers = array(
            "Authorization: Bearer " . $this->access_token,
            "Content-type: application/ssml+xml",
            "X-Microsoft-OutputFormat: audio-48khz-192kbitrate-mono-mp3",
            "Host: " . 'eastus' . ".tts.speech.microsoft.com",
            "Content-Length: " . strlen($body),
            'User-Agent: Azure-TTS',
            "Connection: Keep-Alive"
        );

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

        $data = curl_exec($this->ch);

        if(curl_errno($this->ch)){
            $this->status["status"] = curl_error($this->ch);
            curl_close($this->ch);
            return $this->returnStatus();
        }

        $http_code = curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);

        if (!$http_code == 200) {
            $this->status["status"] = "textToVoice " . "Error Code: " . $http_code;
            curl_close($this->ch);
            return $this->returnStatus();
        }
        curl_close($this->ch);
        
        $file = "result-". date('Ymd-His') . '.mp3';
        if(file_put_contents($this->save_file_dir.$file, $data)){
            $this->status["status"] = "File Saved, Please Open It !";
            $this->status["path"] = $this->save_file_dir.$file;
        }else{
            $this->status["status"] = "Save File Error, Please Check Permission";
        }
        
        return $this->returnStatus();
    }

    public function setttingTTS($ttsValue)
    {
        isset($ttsValue["gender"]) && $this->ttsArray["gender"] = $ttsValue["gender"];
        isset($ttsValue["name"]) && $this->ttsArray["name"] = $ttsValue["name"];
        isset($ttsValue["rate"]) && $this->ttsArray["rate"] = $ttsValue["rate"];
        isset($ttsValue["volume"]) && $this->ttsArray["volume"] = $ttsValue["volume"];
        isset($ttsValue["stylelist"]) && $this->ttsArray["stylelist"] = $ttsValue["stylelist"];
        isset($ttsValue["locale"]) && $this->ttsArray["locale"] = $ttsValue["locale"];
    }

    private function returnStatus()
    {
        return json_encode($this->status,JSON_UNESCAPED_UNICODE);
    }

    private function delOldFile($dir, $del_time)
    {
        $files = scandir($dir);
        foreach($files as $filename){
            $thisfile = $dir.'/'.$filename;
            
            if($thisfile != '.' && $thisfile != '..' && (time()-filemtime($thisfile)) > $del_time) {
                unlink($thisfile);
            }
        }
    }

    public function verificationKey($key)
    {
        if($this->key == ""){
            return true;
        }
        if((string)$key == $this->key){
            return true;
        }else{
            $this->status["status"] = "Please Input Right Password";
            return false;
        }
    }
}

$ttsObj = new AzureTTS();

$tex = "";
$key = "";
$ttsValue = array();
$result = "";

if (is_array($_POST)) { 
    if(isset($_POST["getlist"]) || isset($_GET["getlist"])){
        $result = $ttsObj->getList();
    }else{
        isset($_POST["tex"]) && $tex = $_POST["tex"];
        isset($_POST["key"]) && $key = $_POST["key"];

        isset($_GET["tex"]) && $tex = $_GET["tex"];
        isset($_GET["key"]) && $key = $_GET["key"];

        isset($_POST["gender"]) && $ttsValue["gender"] = $_POST["gender"]; 
        isset($_POST["name"]) && $ttsValue["name"] = $_POST["name"]; 
        isset($_POST["rate"]) && $ttsValue["rate"] = $_POST["rate"]; 
        isset($_POST["volume"]) && $ttsValue["volume"] = $_POST["volume"]; 
        isset($_POST["locale"]) && $ttsValue["locale"] = $_POST["locale"];
        isset($_POST["stylelist"]) && $ttsValue["stylelist"] = $_POST["stylelist"];

        $ttsObj->setttingTTS($ttsValue);
        $result = $ttsObj->textToVoice($tex, $key);
    }
    echo $result;
}

?>