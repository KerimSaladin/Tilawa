/**
 * recorder.js — تسجيل صوت وفيديو التلاوة
 * يعالج: التسجيل المباشر، المعاينة، وإرسال النموذج
 */

let mediaRecorder = null;
let audioChunks = [];
let videoChunks = [];
let isRecording = false;
let recordingTimer = 0;
let timerInterval = null;
let visualizerInterval = null;
let recordedAudioBlob = null;
let recordedVideoBlob = null;

// عناصر الصوت
const recordBtn       = document.getElementById('recordBtn');
const audioPreview    = document.getElementById('audioPreview');
const audioPlayback   = document.getElementById('audioPlayback');
const recordedAudioInput = document.getElementById('recorded_audio');
const recordingTimerEl   = document.getElementById('recordingTimer');
const recordingStatus    = document.getElementById('recordingStatus');
const recordingSpinner   = document.getElementById('recordingSpinner');
const micIcon            = document.getElementById('micIcon');
const visualizerBars     = document.getElementById('visualizerBars');

// عناصر الفيديو
const videoRecordBtn  = document.getElementById('videoRecordBtn');
const videoStopBtn    = document.getElementById('videoStopBtn');
const videoPreview    = document.getElementById('videoPreview');
const videoPlayback   = document.getElementById('videoPlayback');
const recordedVideoInput = document.getElementById('recorded_video');

// ── تهيئة شريط التصور ─────────────────────────────────────────────────────
function initVisualizer() {
    if (!visualizerBars || visualizerBars.children.length > 0) return;
    for (let i = 0; i < 48; i++) {
        const bar = document.createElement('div');
        bar.className = 'visualizer-bar';
        bar.style.animationDelay = `${i * 0.05}s`;
        visualizerBars.appendChild(bar);
    }
}

function startVisualizer() {
    if (!visualizerBars) return;
    const bars = visualizerBars.querySelectorAll('.visualizer-bar');
    visualizerInterval = setInterval(() => {
        bars.forEach(bar => {
            const h = 10 + Math.random() * 90;
            bar.style.height = h + '%';
            bar.classList.add('active');
        });
    }, 150);
}

function stopVisualizer() {
    clearInterval(visualizerInterval);
    if (!visualizerBars) return;
    visualizerBars.querySelectorAll('.visualizer-bar').forEach(bar => {
        bar.style.height = '4px';
        bar.classList.remove('active');
    });
}

function formatTime(s) {
    return `${String(Math.floor(s/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}`;
}

// ── تسجيل الصوت ───────────────────────────────────────────────────────────
if (recordBtn) {
    recordBtn.addEventListener('click', async () => {
        if (!isRecording) {
            // بدء التسجيل
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream, { mimeType: getSupportedAudioMime() });
                audioChunks = [];

                mediaRecorder.ondataavailable = e => { if (e.data.size > 0) audioChunks.push(e.data); };

                mediaRecorder.onstop = () => {
                    recordedAudioBlob = new Blob(audioChunks, { type: getSupportedAudioMime() });
                    const url = URL.createObjectURL(recordedAudioBlob);
                    audioPlayback.src = url;
                    audioPreview.style.display = 'block';
                    audioPreview.classList.add('active');

                    // تخزين base64 في الحقل المخفي
                    const reader = new FileReader();
                    reader.onload = () => { if (recordedAudioInput) recordedAudioInput.value = reader.result; };
                    reader.readAsDataURL(recordedAudioBlob);

                    stream.getTracks().forEach(t => t.stop());
                    stopVisualizer();
                    clearInterval(timerInterval);
                    if (recordingStatus) recordingStatus.textContent = 'تم التسجيل — يمكنك الاستماع أو إعادة التسجيل';
                };

                mediaRecorder.start(100);
                isRecording = true;
                recordingTimer = 0;
                timerInterval = setInterval(() => { recordingTimer++; if (recordingTimerEl) recordingTimerEl.textContent = formatTime(recordingTimer); }, 1000);
                startVisualizer();

                if (micIcon)         micIcon.style.opacity = '0';
                if (recordingSpinner) recordingSpinner.style.display = 'block';
                if (recordingStatus)  recordingStatus.textContent = 'جارٍ التسجيل... انقر للإيقاف';

            } catch (err) {
                alert('يرجى السماح للمتصفح بالوصول إلى الميكروفون');
            }
        } else {
            // إيقاف التسجيل
            if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
            isRecording = false;
            if (micIcon)         micIcon.style.opacity = '1';
            if (recordingSpinner) recordingSpinner.style.display = 'none';
        }
    });
}

function getSupportedAudioMime() {
    const types = ['audio/webm;codecs=opus','audio/webm','audio/ogg;codecs=opus','audio/mp4'];
    for (const t of types) { if (MediaRecorder.isTypeSupported(t)) return t; }
    return '';
}

function getSupportedVideoMime() {
    const types = ['video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm','video/mp4'];
    for (const t of types) { if (MediaRecorder.isTypeSupported(t)) return t; }
    return '';
}

// ── تسجيل الفيديو ─────────────────────────────────────────────────────────
let videoStream = null;

if (videoRecordBtn) {
    videoRecordBtn.addEventListener('click', async () => {
        try {
            videoStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
            const mime = getSupportedVideoMime();
            mediaRecorder = new MediaRecorder(videoStream, mime ? { mimeType: mime } : {});
            videoChunks = [];

            // عرض مباشر
            videoPlayback.srcObject = videoStream;
            videoPlayback.style.display = 'block';
            videoPlayback.muted = true;
            videoPlayback.play();
            videoPreview.classList.add('active');

            mediaRecorder.ondataavailable = e => { if (e.data.size > 0) videoChunks.push(e.data); };
            mediaRecorder.onstop = () => {
                recordedVideoBlob = new Blob(videoChunks, { type: mime || 'video/webm' });
                const url = URL.createObjectURL(recordedVideoBlob);
                videoPlayback.srcObject = null;
                videoPlayback.src = url;
                videoPlayback.muted = false;

                const reader = new FileReader();
                reader.onload = () => { if (recordedVideoInput) recordedVideoInput.value = reader.result; };
                reader.readAsDataURL(recordedVideoBlob);

                videoStream.getTracks().forEach(t => t.stop());
            };

            mediaRecorder.start(100);
            videoRecordBtn.style.display = 'none';
            videoStopBtn.style.display = 'inline-flex';

        } catch (err) {
            alert('يرجى السماح للمتصفح بالوصول إلى الكاميرا والميكروفون');
        }
    });
}

if (videoStopBtn) {
    videoStopBtn.addEventListener('click', () => {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
        videoRecordBtn.style.display = 'inline-flex';
        videoStopBtn.style.display = 'none';
    });
}

// ── إرسال النموذج بـ fetch لعرض الرد ─────────────────────────────────────
const uploadForm = document.getElementById('uploadForm');
if (uploadForm) {
    uploadForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = this.querySelector('[type=submit]');
        const originalText = submitBtn ? submitBtn.textContent : '';
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = '...جارٍ الرفع'; }

        const formData = new FormData(this);

        // إذا كان هناك تسجيل صوتي محفوظ كـ base64، أضفه كـ Blob لتجنب مشكلة الحجم
        if (recordedAudioBlob && (!formData.get('audio_file') || !formData.get('audio_file').size)) {
            formData.set('audio_file', recordedAudioBlob, 'recitation_audio.webm');
            formData.delete('recorded_audio');
        }
        if (recordedVideoBlob && (!formData.get('video_file') || !formData.get('video_file').size)) {
            formData.set('video_file', recordedVideoBlob, 'recitation_video.webm');
            formData.delete('recorded_video');
        }

        try {
            const resp = await fetch(this.action, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
            const data = await resp.json();

            if (data.success) {
                showUploadSuccess();
                this.reset();
                recordedAudioBlob = null;
                recordedVideoBlob = null;
                if (audioPreview) audioPreview.style.display = 'none';
                if (videoPreview) videoPreview.classList.remove('active');
                setTimeout(() => location.reload(), 1500);
            } else {
                showUploadError(data.message || 'حدث خطأ أثناء الرفع');
            }
        } catch (err) {
            showUploadError('فشل الاتصال بالخادم');
        } finally {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalText; }
        }
    });
}

function showUploadSuccess() {
    const el = document.getElementById('uploadResult') || createResultEl();
    el.className = 'alert alert-success';
    el.textContent = '✅ تم رفع التلاوة بنجاح! يتم إعادة التحميل...';
    el.style.display = 'block';
}

function showUploadError(msg) {
    const el = document.getElementById('uploadResult') || createResultEl();
    el.className = 'alert alert-error';
    el.textContent = '❌ ' + msg;
    el.style.display = 'block';
}

function createResultEl() {
    const el = document.createElement('div');
    el.id = 'uploadResult';
    el.style.marginTop = '1rem';
    const form = document.getElementById('uploadForm');
    if (form) form.parentNode.insertBefore(el, form.nextSibling);
    return el;
}

// تهيئة
document.addEventListener('DOMContentLoaded', initVisualizer);
