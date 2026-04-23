class CallService {
    constructor(opts) {
        this.api        = (opts.siteUrl || '') + '/api/calls.php';
        this.type       = opts.type === 'video' ? 'video' : 'audio';
        this.calleeId   = opts.calleeId  || null;
        this.callId     = opts.callId    || null;
        this.localEl    = opts.localEl;
        this.remoteEl   = opts.remoteEl;
        this.stateEl    = opts.stateEl;
        this.endBtn     = opts.endBtn;
        this.acceptBtn  = opts.acceptBtn;
        this.declineBtn = opts.declineBtn;
        this.onConnected   = opts.onConnected   || null;
        this.onRinging     = opts.onRinging     || null;
        this.onStateChange = opts.onStateChange || null;

        this.pc          = null;
        this.localStream = null;
        this.lastCandId  = 0;
        this.status      = 'ringing';
        this.pollTimer   = null;
        this._answered   = false;
        this._offerMade  = false;
        this._ended      = false;
        this.isCallee    = !!this.callId;
        this._bindControls();
    }

    _bindControls() {
        if (this.endBtn)     this.endBtn    .addEventListener('click', () => this.end());
        if (this.acceptBtn)  this.acceptBtn .addEventListener('click', () => this.accept());
        if (this.declineBtn) this.declineBtn.addEventListener('click', () => this.decline());
    }

    async init() {
        if (this.isCallee) {
            const r = await fetch(`${this.api}?action=get_call&call_id=${this.callId}`, {credentials:'include'});
            const j = await r.json();
            if (!j.success || !j.call) throw new Error('call not found');
            this.type   = j.call.type;
            this.status = j.call.status;
        } else {
            const fd = new FormData();
            fd.append('action',    'create');
            fd.append('callee_id', this.calleeId);
            fd.append('type',      this.type);
            const r = await fetch(this.api, { method:'POST', body:fd, credentials:'include' });
            const j = await r.json();
            if (!j.success) throw new Error('failed to create call');
            this.callId = j.call_id;
            this.status = 'ringing';
        }
        await this._setupPeer();
        this._startPolling();
        if (!this.isCallee) {
            this.setState('جاري الاتصال\u2026');
            if (this.onRinging) this.onRinging();
            await this._makeOffer();
        } else {
            this.setState('مكالمة واردة\u2026');
        }
    }

    async _setupPeer() {
        const cfg = { iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' }
        ]};
        this.pc = new RTCPeerConnection(cfg);
        const constraints = this.type === 'video'
            ? { audio:true, video:{ facingMode:'user', width:{ideal:1280}, height:{ideal:720} } }
            : { audio:true, video:false };
        this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
        this.localStream.getTracks().forEach(t => this.pc.addTrack(t, this.localStream));
        if (this.localEl && this.type === 'video') {
            this.localEl.srcObject = this.localStream;
            this.localEl.muted = true;
            this.localEl.autoplay = true;
            this.localEl.playsInline = true;
        }
        this.pc.addEventListener('icecandidate', async e => {
            if (!e.candidate || !this.callId) return;
            const fd = new FormData();
            fd.append('action',    'add_candidate');
            fd.append('call_id',   this.callId);
            fd.append('candidate', JSON.stringify(e.candidate));
            await fetch(this.api, { method:'POST', body:fd, credentials:'include' }).catch(()=>{});
        });
        this.pc.addEventListener('track', e => {
            const stream = e.streams[0];
            if (this.remoteEl) {
                this.remoteEl.srcObject = stream;
                this.remoteEl.autoplay = true;
                this.remoteEl.playsInline = true;
                this.remoteEl.play().catch(()=>{});
            }
        });
        this.pc.addEventListener('connectionstatechange', () => {
            const s = this.pc.connectionState;
            if (s === 'connected') { this.setState('متصل'); if (this.onConnected) this.onConnected(); }
            if (s === 'disconnected' || s === 'failed') this.setState('انقطع الاتصال');
        });
    }

    async _makeOffer() {
        if (this._offerMade) return;
        this._offerMade = true;
        const offer = await this.pc.createOffer();
        await this.pc.setLocalDescription(offer);
        const fd = new FormData();
        fd.append('action',  'set_offer');
        fd.append('call_id', this.callId);
        fd.append('sdp',     JSON.stringify(offer));
        await fetch(this.api, { method:'POST', body:fd, credentials:'include' });
    }

    async _makeAnswer(offerSdp) {
        if (this._answered) return;
        this._answered = true;
        if (!this.pc.currentRemoteDescription) {
            await this.pc.setRemoteDescription(JSON.parse(offerSdp));
        }
        const answer = await this.pc.createAnswer();
        await this.pc.setLocalDescription(answer);
        const fd = new FormData();
        fd.append('action',  'set_answer');
        fd.append('call_id', this.callId);
        fd.append('sdp',     JSON.stringify(answer));
        await fetch(this.api, { method:'POST', body:fd, credentials:'include' });
    }

    async accept() {
        const fd = new FormData();
        fd.append('action',  'accept');
        fd.append('call_id', this.callId);
        await fetch(this.api, { method:'POST', body:fd, credentials:'include' });
        this.status = 'accepted';
        this.setState('جاري الاتصال\u2026');
        if (this.onStateChange) this.onStateChange('accepted');
    }

    async decline() {
        const fd = new FormData();
        fd.append('action',  'decline');
        fd.append('call_id', this.callId);
        await fetch(this.api, { method:'POST', body:fd, credentials:'include' }).catch(()=>{});
        await this.end(true);
    }

    async end(silent = false) {
        if (this._ended) return;
        this._ended = true;
        clearInterval(this.pollTimer);
        try {
            const fd = new FormData();
            fd.append('action',  'end');
            fd.append('call_id', this.callId);
            await fetch(this.api, { method:'POST', body:fd, credentials:'include' });
        } catch {}
        if (this.localStream) this.localStream.getTracks().forEach(t => t.stop());
        if (this.pc) this.pc.close();
        this.setState('انتهت المكالمة');
        if (!silent) setTimeout(() => window.location.href = 'messages.php', 800);
    }

    setState(txt) { if (this.stateEl) this.stateEl.textContent = txt; }

    _startPolling() { this.pollTimer = setInterval(() => this._poll(), 1500); }

    async _poll() {
        if (this._ended) return;
        try {
            const r = await fetch(`${this.api}?action=get_call&call_id=${this.callId}`, {credentials:'include'});
            const j = await r.json();
            if (!j.success || !j.call) return;
            const c = j.call;
            if (c.status !== this.status) {
                this.status = c.status;
                if (this.onStateChange) this.onStateChange(c.status);
                if (c.status === 'accepted') this.setState('جاري الاتصال\u2026');
                if (c.status === 'declined') { this.setState('رُفضت المكالمة'); await this.end(true); return; }
                if (c.status === 'ended')    { this.setState('انتهت المكالمة'); await this.end(true); return; }
            }
            if (this.isCallee && c.status === 'accepted' && c.offer_sdp && !this._answered) {
                await this._makeAnswer(c.offer_sdp);
            }
            if (!this.isCallee && c.answer_sdp && !this.pc.currentRemoteDescription) {
                await this.pc.setRemoteDescription(JSON.parse(c.answer_sdp));
            }
            await this._pullCandidates();
        } catch {}
    }

    async _pullCandidates() {
        const r = await fetch(`${this.api}?action=get_candidates&call_id=${this.callId}&last_id=${this.lastCandId}`, {credentials:'include'});
        const j = await r.json();
        if (!j.success) return;
        for (const row of j.candidates) {
            this.lastCandId = row.id;
            try { await this.pc.addIceCandidate(JSON.parse(row.candidate)); } catch {}
        }
    }
}
window.CallService = CallService;
