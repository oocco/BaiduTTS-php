/**
 * Created by lycheng on 2019/8/9.
 */

(function () {
  var self = this
  let minSampleRate = 22050
  this.onmessage = function (e) {
    switch (e.data.command) {
      case 'transData': {
        let newAudioData = transform.transData(atob(e.data.data), e.data.from || 16000)
        self.postMessage({
          command: 'newAudioData',
          data: newAudioData
        })
        break
      }
    }
  }
  var transform = {
    transData: function (audioDataStr, sampleRate) {
      let newAudioData
      let audioData = this.toFloat32(this.strToUint8Array(audioDataStr))
      if (sampleRate >= minSampleRate) {
        newAudioData = audioData
      } else {
        newAudioData = this.changeSampleRate(audioData, sampleRate, minSampleRate)
      }
      return newAudioData
    },
    changeSampleRate: function (buffer, from) {
      var data = new Float32Array(buffer)
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
    toFloat32: function (input) {
      var tmp = new Int16Array(new DataView(input.buffer).buffer)
      var tmpData = []
      for (let i = 0; i < tmp.length; i++) {
        var d = tmp[i] < 0 ? tmp[i] / 0x8000 : tmp[i] / 0x7FFF
        tmpData.push(d)
      }
      return new Float32Array(tmpData)
    },
    strToUint8Array: function (rawData) {
      const outputArray = new Uint8Array(rawData.length)
      for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i)
      }
      return outputArray
    }
  }
})()
