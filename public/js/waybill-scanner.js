// "Scan QR" pop-up (partials/waybill-scanner.blade.php), on the sorting center Shipment Board.
// Reads the QR code on the waybill with the camera, from a photo, or takes a typed number; asks the server
// (data-lookup) which parcel it is; on a match the pop-up closes and that parcel's page opens. The waybill's
// Code 128 barcode still reads too, so labels printed before the QR code keep working.
// Reading: the browser's BarcodeDetector when it can read QR codes, otherwise the bundled ZXing library.
(function () {
    var ZXING_SRC = (document.currentScript && document.currentScript.dataset.zxing) || '/js/vendor/zxing.min.js';
    var CAMERA_KEY = 'alvy-scan-camera';   // the camera picked last time
    var CODE = /^[A-Z0-9-]{6,50}$/;        // same rule as the server (App\Support\WaybillScan)
    var INVALID = 'Invalid waybill QR code. Please scan again.';
    var zxingLoading = null;

    // ── Barcode reading engine ────────────────────────────────────

    // The barcode library (~330 KB) loads once, on first use; a failed load is tried again next time.
    function loadZxing() {
        if (window.ZXing) return Promise.resolve(window.ZXing);
        if (!zxingLoading) {
            zxingLoading = new Promise(function (resolve, reject) {
                var s = document.createElement('script');
                var timeout = setTimeout(function () { fail(new Error('ZXing load timed out')); }, 15000);
                function fail(e) { clearTimeout(timeout); zxingLoading = null; s.remove(); reject(e); }
                s.src = ZXING_SRC;
                s.onload = function () { clearTimeout(timeout); window.ZXing ? resolve(window.ZXing) : fail(new Error('ZXing missing')); };
                s.onerror = function () { fail(new Error('ZXing failed to load')); };
                document.head.appendChild(s);
            });
        }
        return zxingLoading;
    }

    function zxingReader(ZXing) {
        var hints = new Map();
        hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.QR_CODE, ZXing.BarcodeFormat.CODE_128]);
        hints.set(ZXing.DecodeHintType.TRY_HARDER, true);
        return new ZXing.BrowserMultiFormatReader(hints, 200);
    }

    function nativeDetector() {
        if (!('BarcodeDetector' in window)) return Promise.resolve(null);
        return window.BarcodeDetector.getSupportedFormats()
            .then(function (formats) {
                if (formats.indexOf('qr_code') === -1) return null;
                var want = ['qr_code', 'code_128'].filter(function (f) { return formats.indexOf(f) !== -1; });
                return new window.BarcodeDetector({ formats: want });
            })
            .catch(function () { return null; });
    }

    // Each live frame is read a different way, in turn: the square in the middle (where the QR code is aimed)
    // enlarged, the whole picture, two black-and-white conversions, and a wide strip for an old barcode label.
    // QR codes read at any angle; small or far ones still read once enlarged.
    var VARIANTS = [
        { box: 'square', turn: 0, bin: 'HybridBinarizer' },
        { box: false,    turn: 0, bin: 'HybridBinarizer' },
        { box: 'square', turn: 0, bin: 'GlobalHistogramBinarizer' },
        { box: false,    turn: 0, bin: 'GlobalHistogramBinarizer' },
        { box: 'strip',  turn: 0, bin: 'HybridBinarizer' }
    ];
    function drawVariant(video, canvas, ctx, v) {
        var vw = video.videoWidth, vh = video.videoHeight;
        var sx = 0, sy = 0, sw = vw, sh = vh, zoom;
        if (v.box === 'square') {
            // The drawn frame, a little larger than shown to forgive aiming.
            sw = sh = Math.min(vw, vh) * 0.85; sx = (vw - sw) / 2; sy = (vh - sh) / 2;
            zoom = Math.min(2, 1000 / sw);
        } else if (v.box === 'strip') {
            sx = vw * 0.05; sw = vw * 0.9; sy = vh * 0.3; sh = vh * 0.4;
            zoom = Math.min(2, 1400 / sw);
        } else {
            zoom = Math.min(1, 1280 / Math.max(vw, vh));
        }
        var dw = Math.round(sw * zoom), dh = Math.round(sh * zoom);
        canvas.width = v.turn ? dh : dw;
        canvas.height = v.turn ? dw : dh;
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        if (v.turn) { ctx.translate(canvas.width, 0); ctx.rotate(Math.PI / 2); }
        ctx.drawImage(video, sx, sy, sw, sh, 0, 0, dw, dh);
    }

    // A photo, the right way up (phones keep the turn in EXIF).
    function openPhoto(file) {
        if (window.createImageBitmap) {
            return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return createImageBitmap(file); });
        }
        return new Promise(function (resolve, reject) {
            var img = new Image(), url = URL.createObjectURL(file);
            img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('bad image')); };
            img.src = url;
        });
    }

    // Phone photos are big, tilted and unevenly lit: a few sizes, both turns and two conversions; first read wins.
    function readPhoto(ZXing, image) {
        var reader = zxingReader(ZXing);
        var w = image.width, h = image.height, long = Math.max(w, h);
        var canvas = document.createElement('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });
        var attempts = [], sizes = [Math.min(long, 1600), 1000];
        if (long < 1600) sizes.push(Math.min(long * 2, 2200));
        sizes.forEach(function (size) {
            [0, 90].forEach(function (turn) {
                ['HybridBinarizer', 'GlobalHistogramBinarizer'].forEach(function (bin) { attempts.push([size, turn, bin]); });
            });
        });
        return new Promise(function (resolve) {
            (function next(i) {
                if (i >= attempts.length) { resolve(null); return; }
                var a = attempts[i], scale = a[0] / long;
                var sw = Math.round(w * scale), sh = Math.round(h * scale);
                canvas.width = a[1] ? sh : sw;
                canvas.height = a[1] ? sw : sh;
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                if (a[1]) { ctx.translate(canvas.width, 0); ctx.rotate(Math.PI / 2); }
                ctx.drawImage(image, 0, 0, sw, sh);
                try {
                    resolve(reader.decodeBitmap(new ZXing.BinaryBitmap(new ZXing[a[2]](new ZXing.HTMLCanvasElementLuminanceSource(canvas)))).getText());
                    return;
                } catch (e) { /* not this way */ }
                setTimeout(function () { next(i + 1); }, 0);
            })(0);
        });
    }

    // A short beep and buzz, so staff know it read without looking at the screen.
    function beep() {
        try { if (navigator.vibrate) navigator.vibrate(80); } catch (e) {}
        try {
            var A = window.AudioContext || window.webkitAudioContext, ac = new A(), o = ac.createOscillator(), g = ac.createGain();
            o.frequency.value = 1100; g.gain.value = 0.08; o.connect(g); g.connect(ac.destination);
            o.start(); o.stop(ac.currentTime + 0.12); setTimeout(function () { ac.close(); }, 300);
        } catch (e) {}
    }

    function store(key, value) { try { value == null ? localStorage.removeItem(key) : localStorage.setItem(key, value); } catch (e) {} }
    function read(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }

    // ── One pop-up ────────────────────────────────────────────────

    function setup(dialog) {
        var lookup  = dialog.dataset.lookup;
        var view    = dialog.querySelector('[data-wbs-view]');
        var video   = dialog.querySelector('video');
        var status  = dialog.querySelector('[data-wbs-status]');
        var startB  = dialog.querySelector('[data-wbs-start]');
        var stopB   = dialog.querySelector('[data-wbs-stop]');
        var picker  = dialog.querySelector('[data-wbs-camera]');
        var torchB  = dialog.querySelector('[data-wbs-torch]');
        var photo   = dialog.querySelector('[data-wbs-photo]');
        var form    = dialog.querySelector('[data-wbs-manual]');
        var input   = form.querySelector('[name="tracking_number"]');
        var token   = (form.querySelector('[name="_token"]') || {}).value || (document.querySelector('meta[name="csrf-token"]') || {}).content;
        var idleText = dialog.querySelector('[data-wbs-idle-text]');
        var idleTitle = dialog.querySelector('[data-wbs-idle-title]');
        function idle(title, text) { idleTitle.textContent = title; idleText.textContent = text; }

        var stream = null, timer = null, run = 0, live = false;
        var busy = false;                       // a lookup is running: frames are not read meanwhile
        var last = { code: null, at: 0 };      // the last code read, so a QR code left in view isn't re-sent
        var torchOn = false;

        function say(text, kind) { status.textContent = text || ''; status.className = 'wbs-status ' + (text ? 'is-' + (kind || 'info') : ''); }
        function cover(name) {
            dialog.querySelectorAll('[data-wbs-cover]').forEach(function (c) { c.classList.toggle('is-on', c.dataset.wbsCover === name); });
            view.classList.toggle('is-busy', name === 'busy' || name === 'ok');
        }
        function buttons() { startB.hidden = live; stopB.hidden = !live; }

        // Release the camera completely (also when the pop-up closes or the page is left).
        function stop(keepCover) {
            run++;
            live = false;
            if (timer) { clearInterval(timer); clearTimeout(timer); timer = null; }
            if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
            video.srcObject = null;
            view.classList.remove('is-live');
            torchB.hidden = true;
            buttons();
            if (!keepCover) { idle('Camera is off', 'Press Start Scanner to use the camera, or type the number below.'); cover('idle'); }
        }

        function cameraError(e) {
            var msg = e && /ZXing/.test(e.message || '')
                ? 'The scanner could not load (slow connection?). Press Start Scanner to try again, or type the number below.'
                : e && (e.name === 'NotAllowedError' || e.name === 'SecurityError')
                ? 'Camera access is blocked. Click the camera or lock icon in the address bar, allow the camera for this site, then press Start Scanner. You can also type the number below.'
                : e && (e.name === 'NotFoundError' || e.name === 'OverconstrainedError')
                ? 'No camera was found on this device. Type the waybill number below, or use Scan from a photo.'
                : e && e.name === 'NotReadableError'
                ? 'The camera is being used by another app or tab. Close it, then press Start Scanner.'
                : 'The camera could not start. Type the waybill number below, or use Scan from a photo.';
            idle('Camera unavailable', 'Type the number below instead.');
            say(msg, 'error');
            input.focus();
        }

        function constraints() {
            var v = { width: { ideal: 1920 }, height: { ideal: 1080 } };
            var chosen = picker.value || read(CAMERA_KEY);
            if (chosen) v.deviceId = { exact: chosen }; else v.facingMode = { ideal: 'environment' };
            return { video: v, audio: false };
        }

        function listCameras() {
            if (!navigator.mediaDevices.enumerateDevices) return;
            navigator.mediaDevices.enumerateDevices().then(function (devices) {
                var cams = devices.filter(function (d) { return d.kind === 'videoinput'; });
                var current = stream && stream.getVideoTracks()[0] && stream.getVideoTracks()[0].getSettings().deviceId;
                picker.innerHTML = '';
                cams.forEach(function (c, i) {
                    var o = document.createElement('option');
                    o.value = c.deviceId;
                    o.textContent = c.label || ('Camera ' + (i + 1));
                    o.selected = c.deviceId === (current || read(CAMERA_KEY));
                    picker.appendChild(o);
                });
                picker.hidden = cams.length < 2;
            }).catch(function () {});
        }

        // Focus continuously (close-up labels) and offer the phone's flashlight when it has one.
        function tune(track) {
            if (!track || !track.getCapabilities) return;
            var caps = track.getCapabilities();
            if (caps.focusMode && caps.focusMode.indexOf('continuous') !== -1) track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(function () {});
            torchB.hidden = !caps.torch;
            torchOn = false;
            torchB.textContent = 'Flashlight on';
        }

        function start() {
            if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                cameraError({ name: 'NotFoundError' });
                say('The live camera needs a secure (https) page. Type the waybill number below, or use Scan from a photo.', 'error');
                return;
            }
            stop(true);
            var mine = run;
            live = true; buttons();
            cover('idle');
            idle('Starting the camera…', 'Allow camera access if your browser asks.');
            say('');
            var library = loadZxing();
            library.catch(function () {});

            navigator.mediaDevices.getUserMedia(constraints())
                .catch(function (e) {
                    // The remembered camera is gone: any camera instead.
                    if (e && (e.name === 'OverconstrainedError' || e.name === 'NotFoundError') && read(CAMERA_KEY)) {
                        store(CAMERA_KEY, null); picker.value = '';
                        return navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false });
                    }
                    throw e;
                })
                .then(function (s) {
                    if (mine !== run) { s.getTracks().forEach(function (t) { t.stop(); }); return; }
                    stream = s;
                    video.srcObject = s;
                    tune(s.getVideoTracks()[0]);
                    listCameras();
                    return video.play().then(nativeDetector).then(function (detector) {
                        if (mine !== run) return;
                        cover(null);
                        view.classList.add('is-live');
                        say('Scanning… hold the QR code steady inside the frame.');
                        if (detector) {
                            timer = setInterval(function () {
                                if (busy || video.readyState < 2) return;
                                detector.detect(video).then(function (codes) { if (codes.length) found(codes[0].rawValue, true); }).catch(function () {});
                            }, 180);
                            return;
                        }
                        return library.then(function (ZXing) {
                            if (mine !== run) return;
                            var reader = zxingReader(ZXing);
                            var canvas = document.createElement('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });
                            var turn = 0;
                            (function tick() {
                                if (mine !== run) return;
                                if (!busy && video.readyState >= 2 && video.videoWidth) {
                                    var v = VARIANTS[turn++ % VARIANTS.length];
                                    drawVariant(video, canvas, ctx, v);
                                    try {
                                        found(reader.decodeBitmap(new ZXing.BinaryBitmap(new ZXing[v.bin](new ZXing.HTMLCanvasElementLuminanceSource(canvas)))).getText(), true);
                                    } catch (e) { /* no barcode in this frame yet */ }
                                }
                                timer = setTimeout(tick, 80);   // the next frame only after this one: slow phones never freeze
                            })();
                        });
                    });
                })
                .catch(function (e) {
                    if (mine !== run) return;
                    stop();
                    cameraError(e);
                });
        }

        // A code from the camera, a photo or the keyboard.
        function found(text, fromCamera) {
            var code = String(text || '').trim().toUpperCase();
            if (busy || !code) return;
            // The same code still in view (after a find, or a "not found") is not sent again for a while.
            if (fromCamera && code === last.code && Date.now() - last.at < 8000) return;
            last = { code: code, at: Date.now() };
            if (!CODE.test(code)) { say(INVALID, 'error'); return; }
            if (fromCamera) beep();
            ask(code);
        }

        // Ask the server which parcel this is; it decides what this person may open.
        function ask(code) {
            busy = true;
            cover('busy');
            dialog.querySelector('[data-wbs-busy-code]').textContent = code;
            say('');
            var ctrl = window.AbortController ? new AbortController() : null;
            var limit = setTimeout(function () { if (ctrl) ctrl.abort(); }, 20000);

            fetch(lookup, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ tracking_number: code }),
                signal: ctrl ? ctrl.signal : undefined
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) { return { status: res.status, data: data }; });
            }).then(function (r) {
                clearTimeout(limit);
                if (r.data && r.data.ok && r.data.url) {
                    cover('ok');
                    say(r.data.message || 'Parcel found. Opening its details…', 'ok');
                    stop(true);
                    setTimeout(function () { closeDialog(true); window.location.assign(r.data.url); }, 450);
                    return;
                }
                var msg = (r.data && r.data.message) || (
                    r.status === 419 ? 'Your session expired. Reload the page, then scan again.'
                    : r.status === 429 ? 'Too many scans in a short time. Wait a moment, then try again.'
                    : r.status === 403 ? 'Your account is not allowed to look up parcels.'
                    : r.status === 401 ? 'Please sign in again.'
                    : 'Something went wrong on the server. Please try again.');
                retry(msg);
            }).catch(function () {
                clearTimeout(limit);
                retry('Could not reach the server. Check your connection and try again.');
            });
        }

        // Back to scanning after a miss: the camera keeps running, the message stays visible.
        function retry(msg) {
            busy = false;
            cover(live ? null : 'idle');
            say(msg, 'error');
            if (!live) input.select();
        }

        function fromPhoto(file) {
            if (!file) return;
            say('Reading the photo…');
            Promise.all([loadZxing(), openPhoto(file)]).then(function (r) { return readPhoto(r[0], r[1]); })
                .then(function (text) {
                    if (text) { found(text, false); return; }
                    say('No QR code found in that photo. Take it straight on, close enough that the QR code is clear, with good light.', 'error');
                })
                .catch(function () { say('That photo could not be read. Try another photo, or type the number.', 'error'); })
                .then(function () { photo.value = ''; });
        }

        function openDialog() {
            busy = false; last = { code: null, at: 0 };
            input.value = '';
            idle('Camera is off', 'Press Start Scanner to use the camera, or type the number below.');
            say('');
            cover('idle');
            dialog.classList.remove('is-closing');
            if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
            start();   // the camera starts right away; Stop Scanner turns it off
        }

        function closeDialog(now) {
            stop();
            busy = false;
            if (!dialog.open) return;
            if (now) { dialog.close(); return; }
            dialog.classList.add('is-closing');
            setTimeout(function () { dialog.classList.remove('is-closing'); if (dialog.open) dialog.close(); }, 130);
        }

        document.querySelectorAll('[data-waybill-scanner-open="' + dialog.id + '"]').forEach(function (b) {
            b.addEventListener('click', openDialog);
        });
        dialog.querySelectorAll('[data-wbs-close]').forEach(function (b) { b.addEventListener('click', function () { closeDialog(); }); });
        dialog.addEventListener('cancel', function (e) { e.preventDefault(); closeDialog(); });           // Esc
        dialog.addEventListener('close', function () { stop(); });                                          // however it closes
        dialog.addEventListener('click', function (e) { if (e.target === dialog) closeDialog(); });         // the dimmed backdrop
        startB.addEventListener('click', start);
        stopB.addEventListener('click', function () { stop(); say('Scanner stopped. Press Start Scanner to scan again.'); });
        picker.addEventListener('change', function () { store(CAMERA_KEY, picker.value); start(); });
        torchB.addEventListener('click', function () {
            var track = stream && stream.getVideoTracks()[0];
            if (!track) return;
            torchOn = !torchOn;
            track.applyConstraints({ advanced: [{ torch: torchOn }] }).catch(function () { torchOn = false; });
            torchB.textContent = torchOn ? 'Flashlight off' : 'Flashlight on';
        });
        photo.addEventListener('change', function () { fromPhoto(photo.files[0]); });
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!input.value.trim()) { say('Type a waybill or tracking number.', 'error'); input.focus(); return; }
            last = { code: null, at: 0 };
            found(input.value, false);
        });
        window.addEventListener('pagehide', function () { stop(); });
    }

    function init() { document.querySelectorAll('dialog[data-waybill-scanner]').forEach(setup); }
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
