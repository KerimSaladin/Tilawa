class CallService {
    constructor(opts) {
        this.api = 'api/calls.php';
        this.type = opts.type === 'video' ? 'video' : 'audio';
        this.calleeId = opts.calleeId || null;
        this.callId = opts.callId || null;
        this.localEl = opts.localEl;
        this.remoteEl = opts.remoteEl;
        this.stateEl = opts.stateEl;
        this.endBtn = opts.endBtn;
        this.acceptBtn = opts.acceptBtn;
        this.declineBtn = opts.declineBtn;
        this.pc = null;
        this.localStream = null;
        this.lastCandId = 0;
        this.status = 'ringing';
        this.pollTimer = null;
        this.bindControls();
    }
    bindControls() {
        if (this.endBtn) this.endBtn.addEventListener('click', () => this.end());
        if (this.acceptBtn) this.acceptBtn.addEventListener('click', () => this.accept());
        if (this.declineBtn) this.declineBtn.addEventListener('click', () => this.decline());
    }
    async init() {
        if (this.callId) {
            const r = await fetch(`${this.api}?action=get_call&call_id=${this.callId}`);
            const j = await r.json();
            if (j.success && j.call) {
                this.type = j.call.type;
                this.status = j.call.status;
            }
        } else {
            const fd = new FormData();
            fd.append('action', 'create');
            fd.append('callee_id', this.calleeId);
            fd.append('type', this.type);
            const r = await fetch(this.api, { method: 'POST', body: fd, credentials: 'include' });
            const j = await r.json();
            if (!j.success) throw new Error('create failed');
            this.callId = j.call_id;
            this.status = 'ringing';
        }
        await this.setupPeer();
        if (this.type === 'video') {
            this.localEl.srcObject = this.localStream;
            this.localEl.muted = true;
            this.localEl.autoplay = true;
            this.localEl.playsInline = true;
        }
        this.pc.addEventListener('icecandidate', async e => {
            if (e.candidate) {
                const fd = new FormData();
                fd.append('action', 'add_candidate');
                fd.append('call_id', this.callId);
                fd.append('candidate', JSON.stringify(e.candidate));
                await fetch(this.api, { method: 'POST', body: fd, credentials: 'include' });
            }
        });
        this.pc.addEventListener('track', e => {
            const stream = e.streams[0];
            if (this.type === 'video') {
                this.remoteEl.srcObject = stream;
                this.remoteEl.autoplay = true;
                this.remoteEl.playsInline = true;
            } else {
                this.remoteEl.srcObject = stream;
            }
        });
        if (!this.callId) return;
        if (this.acceptBtn) {
            if (this.status === 'ringing') {
                this.setState('جاري الاتصال');
            }
        }
        this.startPolling();
        if (!this.acceptBtn) {
            await this.makeOffer();
        }
    }
    async setupPeer() {
        const cfg = { iceServers: [{ urls: ['stun:stun.l.google.com:19302'] }] };
        this.pc = new RTCPeerConnection(cfg);
        const constraints = this.type === 'video' ? { audio: true, video: { facingMode: 'user' } } : { audio: true, video: false };
        this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
        this.localStream.getTracks().forEach(t => this.pc.addTrack(t, this.localStream));
    }
    async makeOffer() {
        const offer = await this.pc.createOffer();
        await this.pc.setLocalDescription(offer);
        const fd = new FormData();
        fd.append('action', 'set_offer');
        fd.append('call_id', this.callId);
        fd.append('sdp', JSON.stringify(offer));
        await fetch(this.api, { method: 'POST', body: fd, credentials: 'include' });
    }
    async accept() {
        const fd = new FormData();
        fd.append('action', 'accept');
        fd.append('call_id', this.callId);
        await fetch(this.api, { method: 'POST', body: fd, credentials: 'include' });
        this.status = 'accepted';
        this.setState('متصل');
        await this.answerIfNeeded();
    }
    async decline() {
        const fd = new FormData();
        fd.append('action', 'decline');
        fd.append('call_id', this.callId);
        await fetch(this.api, { method: 'POST', body: fd, credentials: 'include' });
        this.status = 'declined';
        this.setState('تم الرفض');
        this.end(true);
    }
    async end(silent=false) {
        try {
            const fd = new FormData();
            fd.append('action', 'end');
            fd.append('call_id', this.callId);
            await fetch(this.api, { method: 'POST', body: fd, credentials: 'include' });
        } catch {}
        this.status = 'ended';
        this.setState('منتهي');
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
        if (this.localStream) this.localStream.getTracks().forEach(t => t.stop());
        if (this.pc) this.pc.close();
        if (!silent) window.location.href = 'messages.php';
    }
    setState(txt) {
        if (this.stateEl) this.stateEl.textContent = txt;
    }
    startPolling() {
        this.pollTimer = setInterval(async () => {
            const r = await fetch(`${this.api}?action=get_call&call_id=${this.callId}`);
            const j = await r.json();
            if (!j.success || !j.call) return;
            const c = j.call;
            if (c.status !== this.status) {
                this.status = c.status;
                if (this.status === 'accepted') this.setState('متصل');
                if (this.status === 'declined') { this.setState('تم الرفض'); this.end(true); }
                if (this.status === 'ended') { this.setState('منتهي'); this.end(true); }
            }
            await this.applyRemoteSdpIfAny(c);
            await this.pullCandidates();
        }, 1000);
    }
    async applyRemoteSdpIfAny(c) {
        if (this.acceptBtn) {
            if (c.offer_sdp && !this.pc.currentRemoteDescription) {
                const offer = JSON.parse(c.offer_sdp);
                await this.pc.setRemoteDescription(offer);
            }
        } else {
            if (c.answer_sdp && (!this.pc.currentRemoteDescription || this.pc.remoteDescription?.type !== 'answer')) {
                const ans = JSON.parse(c.answer_sdp);
                await this.pc.setRemoteDescription(ans);
            }
        }
        await this.answerIfNeeded();
    }
    async answerIfNeeded() {
        const r = await fetch(`${this.api}?action=get_call&call_id=${this.callId}`);
        const j = await r.json();
        if (!j.success || !j.call) return;
        const c = j.call;
        if (this.acceptBtn && c.offer_sdp && !c.answer_sdp) {
            const offer = JSON.parse(c.offer_sdp);
            if (!this.pc.currentRemoteDescription) await this.pc.setRemoteDescription(offer);
            const answer = await this.pc.createAnswer();
            await this.pc.setLocalDescription(answer);
            const fd = new FormData();
            fd.append('action', 'set_answer');
            fd.append('call_id', this.callId);
            fd.append('sdp', JSON.stringify(answer));
            await fetch(this.api, { method: 'POST', body: fd, credentials: 'include' });
        }
    }
    async pullCandidates() {
        const r = await fetch(`${this.api}?action=get_candidates&call_id=${this.callId}&last_id=${this.lastCandId}`);
        const j = await r.json();
        if (!j.success) return;
        for (const row of j.candidates) {
            this.lastCandId = row.id;
            try {
                const cand = JSON.parse(row.candidate);
                await this.pc.addIceCandidate(cand);
            } catch {}
        }
    }
}
window.CallService = CallService;
