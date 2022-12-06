var ttsApp = new Vue({
    el: '#ttsApp',

    data: {
      type: 0,
      password: '',
      ttsText: '',
      //百度TTS数据
      ttsData : {
        per: '0',
        spd: '5',
        pit: '5',
        vol: '5',
        aue: '3',
        cuid: 'webapi'
      },
      //讯飞TTS数据
      ttsXunfeiData: {
        common: {app_id: ''},
        business: {
          ent: 'intp65',
          aue: 'raw',
          auf: 'audio/L16;rate=16000',
          vcn: 'xiaoyan',
          speed: 50,
          volume: 50,
          pitch: 50,
          bgs: 0,
          tte: 'UTF8',
          reg: '2',
          ram: '0',
          rdn: '0'
        },
        data: {
          status: 2,
          text: ''
        }
      },
      xunfeiWebsocketUrl: '',
      xunfeiGetUrlUrl: 'xunfeitts-api.php',
      xunfeiWebsocket: '',

      xunfeiAudioData: [],
      //显示高级设置参数
      advancedConfig: false,

      //处理参数
      audioUrl: '',
      enableDownload: false,
      enableSend: true,
      //提示消息
      status: '',
      classStatus: 'alert-primary',
      //提交url
      serverUrl: 'tts-api.php'
    },

    watch: {
      //允许发送按钮当数据改变时
      ttsData: {
        handler: function(){
        this.enableSend = true;
        },
        deep: true
      },
      ttsXunfeiData: {
        handler: function(){
        this.enableSend = true;
        },
        deep: true
      },
      ttsText: function(){
        this.ttsXunfeiData.data.text = Base64.encode(this.ttsText);
      },
      type: function(){
        this.advancedConfig = false;
        setTimeout(() => {
          (function () {$('[data-toggle="tooltip"]').tooltip();})();
        }, 1000);
      }
    },
    

    created: function(){
      //载入前读取保存数据
      this.initializeLcoalStorge();
    },

    methods: {
      //获取保存的设置
      initializeLcoalStorge: function(){
        (this.getLocalStorage("baiduConfig") != null) && (this.ttsData = JSON.parse(this.getLocalStorage("baiduConfig")));
        (this.getLocalStorage("xunfeiConfig") != null) && (this.ttsXunfeiData = JSON.parse(this.getLocalStorage("xunfeiConfig")));

        (this.getLocalStorage("ttspassword") != null) && (this.password = this.getLocalStorage("ttspassword"));
        (this.getLocalStorage("ttstype") != null) && (this.type = this.getLocalStorage("ttstype"));
      },

      //储存与清除设置
      setLocalStorage: function(name_in, value_in){
          localStorage.setItem(name_in, value_in);
      },
      getLocalStorage: function(name_in){
          return localStorage.getItem(name_in);
      },

      savettsData: function(){
        this.setLocalStorage('baiduConfig', JSON.stringify(this.ttsData));
        this.setLocalStorage('xunfeiConfig', JSON.stringify(this.ttsXunfeiData));
        this.setLocalStorage('ttspassword', this.password);
        this.setLocalStorage('ttstype', this.type);
        this.classStatus = "alert-success";
        this.status = "Save Success";
      },

      resetttsData: function(){
        if (confirm('Clean Config?')){
          this.ttsData = {tex: '',per: '0',spd: '5',pit: '5',vol: '5',aue: '3',cuid: 'webapi',key: ''};
          this.ttsXunfeiData = {common: {app_id: ''},business: {ent: 'intp65',aue: 'raw',auf: 'audio/L16;rate=16000',vcn: 'xiaoyan',speed: '50',volume: '50',pitch: '50',bgs: '0',tte: 'UTF8',reg: '2',ram: '0',rdn: '0'},data: {status: 2,text: ''}};
          localStorage.removeItem('baiduConfig');
          localStorage.removeItem('xunfeiConfig');
          localStorage.removeItem('ttspassword');
          localStorage.removeItem('ttstype');
        }
      },

      //重新载入音频
      reloadAudio: function(){
        let audio = document.getElementById('audioSrc');
        audio.load();
        audio.play();
      },

      //send ajax request
      ajaxRequest: function(uri, n){
        let _this = this;
        axios.post(uri, n)
        .then(function(response){
          if('path' in response.data && response.data.path != ''){

            if(_this.type == 0){
              let ext = response.data.path.substr(-3, 3);
              if(ext == 'mp3' || ext == 'wav' || ext == 'pcm'){
                _this.audioUrl = response.data.path;
                _this.reloadAudio();

                _this.classStatus = "alert-success";
                _this.status = response.data.status;
                _this.enableSend = false;
                _this.enableDownload = true;

              }else if(ext == 'txt'){
                _this.classStatus = "alert-warning";
                _this.status = "Error, Check Log";
                _this.enableSend = true;
                _this.enableDownload = false;
              }
            }else if(_this.type == 1){
              _this.xunfeiWebsocketUrl = response.data.path;
              _this.ttsXunfeiData.common.app_id = response.data.appid;
              _this.initXunfeiWebsocket();
              //console.log(_this.xunfeiWebsocketUrl);
              _this.classStatus = "alert-success";
              _this.status = response.data.status;
            }
            
          }else{
            _this.classStatus = "alert-warning";
            _this.status = response.data.status;
            _this.enableSend = true;
            _this.enableDownload = false;
          }
        })
        .catch(function(error){
            console.log(error);
            _this.classStatus = "alert-danger";
            _this.status = "Error, Check Network or Server";
            _this.enableSend = true;
            _this.enableDownload = false;
        })
      },

      //从php获取加密后的websockets链接
      getWebsocketUrl: function(){
        this.enableSend = false;
        let n = new FormData;
        n.append("key", this.password);
        this.ajaxRequest(this.xunfeiGetUrlUrl, n);
      },
      //开启websocket连接
      initXunfeiWebsocket: function(){
        if(typeof(WebSocket) === "undefined"){
            alert("您的浏览器不支持socket")
        }else{
            this.xunfeiWebsocket = new WebSocket(this.xunfeiWebsocketUrl);
            _this = this;
            this.xunfeiWebsocket.onopen = function(){
              //_this.classStatus = "alert-success";
              //_this.status = "Websocket Connect Success";
              _this.xunfeiWebsocket.send(JSON.stringify(_this.ttsXunfeiData));
            };
            
            this.xunfeiWebsocket.onerror = function(){
              _this.classStatus = "alert-danger";
              _this.status = "Websocket Connect Error";
              _this.enableSend = true;
            };

            this.xunfeiWebsocket.onmessage = function(event){
              //console.log(event.data);
              let jsonData = JSON.parse(event.data)
              // 合成失败
              if (jsonData.code !== 0) {
                _this.status = `Error: ${jsonData.code}:${jsonData.message}`;
                //self.resetAudio();
                _this.classStatus = "alert-danger";
                _this.xunfeiWebsocket.close();
                _this.enableSend = true;
                return;
              }

              /* 运行官方transform脚本，将音频转换为Float32Array
                  let transData = {data: jsonData.data.audio};
                  if(_this.ttsXunfeiData.business.auf.indexOf('8000')==-1){
                    transData.from = 8000;
                  }
                  _this.handleXunfeiAudio(transData);
                  */
              
              //保存语音数据
              _this.xunfeiAudioData = jsonData.data.audio;
              _this.enableDownload = true;

              if (jsonData.code === 0 && jsonData.data.status === 2) {
                _this.classStatus = "alert-success";
                _this.status = "File Saved, Please Open It !";
                _this.xunfeiWebsocket.close();
              }
            };

            this.xunfeiWebsocket.onclose = function(){
              //_this.classStatus = "alert-primary";
              //_this.status = "Websocket Disconnect";
            };
        }
      },

      //发送TTS数据到服务器
      sendTts: function(){
        if(this.ttsText == ''){
          this.classStatus = "alert-danger";
          this.status = "Must Input Text";
          return;
        }
        if(this.type == 0){
            this.status = "";
          if(this.enableSend){
            this.enableSend = false;
            let n = new FormData;
            n.append("tex", this.ttsText);
            n.append("per", this.ttsData.per);
            n.append("spd", this.ttsData.spd);
            n.append("pit", this.ttsData.pit);
            n.append("vol", this.ttsData.vol);
            n.append("aue", this.ttsData.aue);
            n.append("cuid", this.ttsData.cuid);
            n.append("key", this.password);
            this.ajaxRequest(this.serverUrl, n);

          }
        }else if(this.type == 1){
          this.getWebsocketUrl();
        }
        
      },

      //处理音频文件下载

      /* 官方transform脚本，将音频转换为Float32Array
          handleXunfeiAudio: function(e){
            let newAudioData = this.xunfeiTransData(atob(e.data), e.data.from || 16000);
          },
          xunfeiTransData: function (audioDataStr, sampleRate) {
            let newAudioData;
            let minSampleRate = 22050
            let audioData = this.xunfeiToFloat32(this.xunfeiStrToUint8Array(audioDataStr));
            if (sampleRate >= minSampleRate) {
              newAudioData = audioData;
            } else {
              newAudioData = this.xunfeiChangeSampleRate(audioData, sampleRate, minSampleRate);
            }
            this.xunfeiAudioData = newAudioData;
          },
          xunfeiChangeSampleRate: function (buffer, from) {
            var data = new Float32Array(buffer)
            let minSampleRate = 22050
            var fitCount = Math.round(data.length * (minSampleRate / from))
            var newData = new Float32Array(fitCount)
            var springFactor = (data.length - 1) / (fitCount - 1)
            newData[0] = data[0]
            for (var i = 1; i < fitCount - 1; i++) {
              var tmp = i * springFactor
              var before = Math.floor(tmp).toFixed()
              var after = Math.ceil(tmp).toFixed()
              var atPoint = tmp - before
              newData[i] = data[before] + (data[after] - data[before]) * atPoint
            }
            newData[fitCount - 1] = data[data.length - 1]
            return newData
          },
          xunfeiToFloat32: function (input) {
            var tmp = new Int16Array(new DataView(input.buffer).buffer)
            var tmpData = []
            for (let i = 0; i < tmp.length; i++) {
              var d = tmp[i] < 0 ? tmp[i] / 0x8000 : tmp[i] / 0x7FFF
              tmpData.push(d)
            }
            return new Float32Array(tmpData)
          },
          xunfeiStrToUint8Array: function (rawData) {
            const outputArray = new Uint8Array(rawData.length)
            for (let i = 0; i < rawData.length; ++i) {
              outputArray[i] = rawData.charCodeAt(i)
            }
            return outputArray
          },
          */

      //获取音频文件后缀
      getAudioExt: function(){
        if(this.type == 0){
          return _this.audioUrl.substr(-3, 3);
        }else if(this.type == 1){
          switch (this.ttsXunfeiData.business.aue) {
            case 'raw':
              return 'pcm';
              break;
            default:
              return 'spx';
              break;
          }
        }
      },

      //转换pcm音频为wav文件
      encodeWav: function(blobData){

      },

      dataURLtoBlob: function (dataurl) {

        //mime = arr[0].match(/:(.*?);/)[1],
        //mime = {type: 'audio/pcm'};
        let mime = 'audio/'+_this.getAudioExt();

        bstr = atob(dataurl), 
        n = bstr.length, 
        u8arr = new Uint8Array(n);

        while (n--) {
          u8arr[n] = bstr.charCodeAt(n);
        }
        return new Blob([u8arr], { type: mime });
      },

      getBlob: function(url, cb){
        let xhr = new XMLHttpRequest()
        xhr.open('GET', url, true);
        xhr.responseType = 'blob';
        xhr.onload = function() {
          if (xhr.status === 200) {
            cb(xhr.response);
          }
        };
        xhr.send();
      },
      
      saveAs: function(blob, filename){
        if (window.navigator.msSaveOrOpenBlob) {
            navigator.msSaveBlob(blob, filename);
        }else{
          let link = document.createElement('a');
          let body = document.querySelector('body');
          link.href = window.URL.createObjectURL(blob);
          link.download = filename;
          // fix Firefox
          link.style.display = 'none';
          body.appendChild(link);
          link.click();
          body.removeChild(link);
          window.URL.revokeObjectURL(link.href);
        };
      },

      download: function(url, filename) {
        let _this = this;
        this.getBlob(url, function(blob) {
          _this.saveAs(blob, filename);
        });
      },

      //下载音频函数
      downloadAudio: function(){
        if(this.enableDownload){
          let _this = this;
          let fname = '';
          if(this.ttsText.length > 40){
            fname = _this.ttsText.substr(0, 30) + '...' + _this.ttsText.substr(-10, 10);
          }else{
            fname = _this.ttsText.substr(0, 40);
          }
          fname = fname + '.' + _this.getAudioExt();

          //从服务器保存文件
          if(this.type == 0){
            this.download(this.audioUrl , fname);

          //从对象保存文件
          }else if(this.type == 1){
            _this.saveAs(this.dataURLtoBlob(_this.xunfeiAudioData), fname);
          }

        }
      }
    }

  })

  //获取客户端IP地址
  ttsApp.ttsData.cuid = returnCitySN["cip"];
  (function () {$('[data-toggle="tooltip"]').tooltip();})();
  
  //绑定input值
  //$('#spd').bind('input propertychange', function() {
  //    $('.spdval').html($(this).val());
  //});
  
