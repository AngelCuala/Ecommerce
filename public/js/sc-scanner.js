// Sorting center waybill scanner: reads the waybill barcode (Code 128) or a QR code with the camera,
// puts the number in the scan box and submits it. Typing the number still works when there is no camera.
// Uses the browser's BarcodeDetector when it can read Code 128, otherwise the bundled ZXing library.
(function () {
    var ZXING_SRC = (document.currentScript && document.currentScript.dataset.zxing) || '/js/vendor/zxing.min.js';
    var zxingLoading = null;

    function loadZxing() {
        if (window.ZXing) return Promise.resolve(window.ZXing);
        if (!zxingLoading) {
            zxingLoading = new Promise(function (resolve, reject) {
                var s = document.createElement('script');
                s.src = ZXING_SRC;
                s.onload = function () { window.ZXing ? resolve(window.ZXing) : reject(new Error('ZXing missing')); };
                s.onerror = function () { reject(new Error('ZXing failed to load')); };
                document.head.appendChild(s);
            });
        }
        return zxingLoading;
    }

    function nativeDetector() {
        if (!('BarcodeDetector' in window)) return Promise.resolve(null);
        return window.BarcodeDetector.getSupportedFormats()
            .then(function (formats) {
                return formats.indexOf('code_128') === -1 ? null : new window.BarcodeDetector({ formats: ['code_128', 'qr_code'] });
            })
            .catch(function () { return null; });
    }

    function setup(box) {
        var form   = box.closest('form') ? box.closest('form') : document.getElementById(box.dataset.form);
        var input  = form.querySelector('[name="tracking_number"]');
        var openB  = box.querySelector('[data-camera-open]');
        var panel  = box.querySelector('[data-camera-panel]');
        var video  = box.querySelector('video');
        var status = box.querySelector('[data-camera-status]');
        var stream = null, timer = null, reader = null, done = false;

        function say(text, bad) { status.textContent = text; status.style.color = bad ? '#B91C1C' : ''; }

        function stop() {
            if (timer) { clearInterval(timer); timer = null; }
            if (reader) { try { reader.reset(); } catch (e) {} reader = null; }
            if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
            video.srcObject = null;
        }

        function close() { stop(); panel.hidden = true; openB.hidden = false; }

        function found(text) {
            if (done) return;
            var code = String(text || '').trim();
            if (!code) return;
            done = true;
            stop();
            say('Read ' + code + '. Looking it up…');
            input.value = code;
            form.submit();   // the server checks the code, the parcel and this center
        }

        function start() {
            done = false;
            if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                say('The camera can only be used on a secure (https) page with a camera. Type the number instead.', true);
                return;
            }
            panel.hidden = false;
            openB.hidden = true;
            say('Starting the camera…');

            nativeDetector().then(function (detector) {
                if (detector) {
                    return navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false }).then(function (s) {
                        stream = s;
                        video.srcObject = s;
                        return video.play().then(function () {
                            say('Point the camera at the waybill barcode.');
                            timer = setInterval(function () {
                                if (video.readyState < 2) return;
                                detector.detect(video).then(function (codes) { if (codes.length) found(codes[0].rawValue); }).catch(function () {});
                            }, 250);
                        });
                    });
                }
                return loadZxing().then(function (ZXing) {
                    var hints = new Map();
                    hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.CODE_128, ZXing.BarcodeFormat.QR_CODE]);
                    reader = new ZXing.BrowserMultiFormatReader(hints, 250);
                    say('Point the camera at the waybill barcode.');
                    return reader.decodeFromConstraints({ video: { facingMode: 'environment' }, audio: false }, video, function (result) {
                        if (result) found(result.getText());
                    });
                });
            }).catch(function (e) {
                stop();
                say(e && e.name === 'NotAllowedError'
                    ? 'Camera permission was denied. Allow the camera, or type the number instead.'
                    : 'The camera could not start. Type the number instead.', true);
            });
        }

        openB.addEventListener('click', start);
        box.querySelector('[data-camera-close]').addEventListener('click', close);
        window.addEventListener('pagehide', stop);
    }

    document.querySelectorAll('[data-camera-scan]').forEach(setup);
})();
