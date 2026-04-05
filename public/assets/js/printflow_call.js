/**
 * PrintFlow Voice & Video Call System (WebRTC + Socket.io)
 * Final, Production-Quality Implementation
 */

class PrintFlowCall {
    constructor(config) {
        this.config = {
            socketServer: config.socketServer || 'http://localhost:3000',
            userId: config.userId,
            role: config.role,
            userName: config.userName,
            userAvatar: config.userAvatar,
            iceServers: {
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' }
                ]
            }
        };

        this.socket = null;
        this.pc = null;
        this.localStream = null;
        this.remoteStream = null;
        this.callState = 'idle';
        this.currentCallData = null;
        this.callTimer = null;
        this.callDuration = 0;
        
        // Ringtone with fallback
        this.ringtone = new Audio('/printflow/public/assets/audio/ringtone.mp3');
        this.ringtone.loop = true;
        this.ringtone.onerror = () => {
            console.warn("Ringtone file not found or failed to load. Will use electronic beep fallback.");
            this.ringtone = null;
        };

        this.init();
    }

    init() {
        console.log("Call system initializing...");
        this.initSocket();
        this.initUI();
    }

    initSocket() {
        if (typeof io === 'undefined') {
            console.error("Socket.io client not loaded!");
            return;
        }

        this.socket = io(this.config.socketServer);

        this.socket.on('connect', () => {
            console.log("Connected to signaling server");
            this.socket.emit('join', {
                userId: this.config.userId,
                role: this.config.role
            });
        });

        this.socket.on('connect_error', (error) => {
            console.error("Signaling connection error:", error.message);
            // Don't alert on repeat errors, just log
        });

        this.socket.on('incoming-call', async (data) => {
            if (this.callState !== 'idle') {
                this.socket.emit('decline-call', { toUserKey: data.fromUserKey, reason: 'busy' });
                return;
            }
            this.handleIncomingCall(data);
        });

        this.socket.on('call-accepted', async (data) => {
            console.log("Call accepted by remote");
            this.handleCallAccepted(data);
        });

        this.socket.on('call-declined', (data) => {
            console.log("Call declined: " + data.reason);
            this.handleCallDeclined(data.reason);
        });

        this.socket.on('call-ended', () => {
            console.log("Call ended by remote");
            this.endCall(false);
        });

        this.socket.on('ice-candidate', (data) => {
            if (this.pc) {
                this.pc.addIceCandidate(new RTCIceCandidate(data.candidate));
            }
        });

        this.socket.on('call-failed', (data) => {
            alert(data.message);
            this.endCall();
        });
    }

    initUI() {
        // Create Call Overlay if it doesn't exist
        if (!document.getElementById('call-overlay')) {
            const overlay = document.createElement('div');
            overlay.id = 'call-overlay';
            overlay.innerHTML = `
                <div id="call-incoming">
                    <div class="call-profile-pic" id="incoming-avatar"></div>
                    <div class="call-name" id="incoming-name">User Name</div>
                    <div class="call-status" id="incoming-type">Incoming Call...</div>
                    <div class="call-actions-row">
                        <button class="call-btn btn-accept" id="btn-accept-call">
                            <i class="bi bi-telephone-fill"></i>
                        </button>
                        <button class="call-btn btn-decline" id="btn-decline-call">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div id="call-outgoing">
                    <div class="call-profile-pic" id="outgoing-avatar"></div>
                    <div class="call-name" id="outgoing-name">User Name</div>
                    <div class="call-status">Calling...</div>
                    <div class="call-actions-row">
                        <button class="call-btn btn-end" id="btn-cancel-outgoing">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div id="call-active">
                    <div class="call-timer" id="call-timer">00:00</div>
                    <div id="remote-video-container">
                        <video id="remote-video" autoplay playsinline></video>
                    </div>
                    <div id="local-video-container">
                        <video id="local-video" autoplay playsinline muted></video>
                    </div>
                    <div class="voice-wave-visualizer">
                        <div></div><div></div><div></div><div></div><div></div>
                    </div>
                    <div class="call-controls-overlay">
                        <button class="call-btn btn-mute" id="btn-mute">
                            <i class="bi bi-mic-fill"></i>
                        </button>
                        <button class="call-btn btn-video-toggle" id="btn-video-toggle">
                            <i class="bi bi-camera-video-fill"></i>
                        </button>
                        <button class="call-btn btn-end" id="btn-active-end">
                            <i class="bi bi-telephone-x-fill"></i>
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
        }

        // Event Listeners
        document.getElementById('btn-accept-call').addEventListener('click', () => this.acceptCall());
        document.getElementById('btn-decline-call').addEventListener('click', () => this.declineCall());
        document.getElementById('btn-cancel-outgoing').addEventListener('click', () => this.endCall());
        document.getElementById('btn-active-end').addEventListener('click', () => this.endCall());
        document.getElementById('btn-mute').addEventListener('click', (e) => this.toggleMute(e));
        document.getElementById('btn-video-toggle').addEventListener('click', (e) => this.toggleVideo(e));
    }

    async startCall(toUserId, toRole, type, orderId, name, avatar) {
        console.log(`Starting ${type} call to ${toRole}_${toUserId}`);
        this.callState = 'outgoing';
        this.currentCallData = { toUserId, toRole, type, orderId };

        this.updateUI('outgoing', name, avatar, type);

        try {
            this.localStream = await navigator.mediaDevices.getUserMedia({
                video: type === 'video',
                audio: true
            });
            document.getElementById('local-video').srcObject = this.localStream;

            this.pc = this.createPeerConnection(`${toRole}_${toUserId}`);
            this.localStream.getTracks().forEach(track => this.pc.addTrack(track, this.localStream));

            const offer = await this.pc.createOffer();
            await this.pc.setLocalDescription(offer);

            this.socket.emit('call-user', {
                toUserId,
                toRole,
                signal: offer,
                fromName: this.config.userName,
                fromAvatar: this.config.userAvatar,
                type,
                orderId
            });

            // Send "Started a call" indicator to chat
            this.sendCallStatusMessage(`📞 Started a ${type} call`);

            // Auto-timeout after 30 seconds
            this.outgoingTimeout = setTimeout(() => {
                if (this.callState === 'outgoing') {
                    this.socket.emit('end-call', { toUserKey: `${toRole}_${toUserId}` });
                    this.endCall();
                }
            }, 30000);

        } catch (error) {
            console.error("Call Error:", error);
            alert("Could not access camera/microphone.");
            this.endCall();
        }
    }

    handleIncomingCall(data) {
        console.log("Receiving call from " + data.fromName);
        this.callState = 'incoming';
        this.currentCallData = data;
        this.updateUI('incoming', data.fromName, data.fromAvatar, data.type);
        
        if (this.ringtone) {
            this.ringtone.play().catch(e => {
                console.warn("Audio play blocked or failed:", e);
                this.playBeepFallback();
            });
        } else {
            this.playBeepFallback();
        }
        
        // Auto-end if not answered in 30s
        this.incomingTimeout = setTimeout(() => {
            if (this.callState === 'incoming') {
                this.declineCall();
            }
        }, 30000);
    }

    async acceptCall() {
        console.log("Call accepted");
        clearTimeout(this.incomingTimeout);
        this.stopBeepFallback();
        if (this.ringtone) {
            this.ringtone.pause();
            this.ringtone.currentTime = 0;
        }
        this.callState = 'active';

        try {
            this.localStream = await navigator.mediaDevices.getUserMedia({
                video: this.currentCallData.type === 'video',
                audio: true
            });
            document.getElementById('local-video').srcObject = this.localStream;

            this.pc = this.createPeerConnection(this.currentCallData.fromUserKey);
            this.localStream.getTracks().forEach(track => this.pc.addTrack(track, this.localStream));

            await this.pc.setRemoteDescription(new RTCSessionDescription(this.currentCallData.signal));
            const answer = await this.pc.createAnswer();
            await this.pc.setLocalDescription(answer);

            this.socket.emit('accept-call', {
                toUserKey: this.currentCallData.fromUserKey,
                signal: answer
            });

            this.updateUI('active', this.currentCallData.fromName, this.currentCallData.fromAvatar, this.currentCallData.type);
            this.startTimer();

        } catch (error) {
            console.error("Accept Call Error:", error);
            this.endCall();
        }
    }

    async handleCallAccepted(data) {
        clearTimeout(this.outgoingTimeout);
        this.callState = 'active';
        await this.pc.setRemoteDescription(new RTCSessionDescription(data.signal));
        
        this.updateUI('active');
        this.startTimer();
    }

    declineCall() {
        this.socket.emit('decline-call', { toUserKey: this.currentCallData.fromUserKey, reason: 'declined' });
        this.endCall();
    }

    handleCallDeclined(reason) {
        alert(reason === 'busy' ? "User is busy." : "Call was declined.");
        this.endCall();
    }

    endCall(emit = true) {
        console.log("Ending call...");
        if (emit && this.socket && this.currentCallData) {
            const toKey = this.currentCallData.fromUserKey || `${this.currentCallData.toRole}_${this.currentCallData.toUserId}`;
            this.socket.emit('end-call', { toUserKey: toKey });

            // Send "Call ended" indicator to chat with duration
            if (this.callDuration > 0) {
                const mins = Math.floor(this.callDuration / 60).toString().padStart(2, '0');
                const secs = (this.callDuration % 60).toString().padStart(2, '0');
                const type = this.currentCallData.type || 'voice';
                this.sendCallStatusMessage(`📞 ${type.charAt(0).toUpperCase() + type.slice(1)} call ended • ${mins}:${secs}`);
            }
        }

        this.stopBeepFallback();
        if (this.ringtone) {
            this.ringtone.pause();
            this.ringtone.currentTime = 0;
        }
        this.callState = 'idle';
        this.stopTimer();

        if (this.pc) {
            this.pc.close();
            this.pc = null;
        }
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
            this.localStream = null;
        }

        this.updateUI('idle');
        this.currentCallData = null;
    }

    createPeerConnection(toUserKey) {
        const pc = new RTCPeerConnection(this.config.iceServers);

        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this.socket.emit('ice-candidate', { toUserKey, candidate: event.candidate });
            }
        };

        pc.ontrack = (event) => {
            console.log("Received remote track");
            const remoteVideo = document.getElementById('remote-video');
            if (!remoteVideo.srcObject) {
                remoteVideo.srcObject = event.streams[0];
            }
        };

        return pc;
    }

    updateUI(state, name, avatar, type) {
        const overlay = document.getElementById('call-overlay');
        overlay.setAttribute('data-state', state);
        overlay.setAttribute('data-type', type || 'voice');

        if (state === 'idle') {
            overlay.classList.remove('active');
            return;
        }

        overlay.classList.add('active');

        if (name) {
            if (state === 'incoming') {
                document.getElementById('incoming-name').textContent = name;
                document.getElementById('incoming-type').textContent = `Incoming ${type} Call...`;
                const av = document.getElementById('incoming-avatar');
                av.innerHTML = avatar ? `<img src="${avatar}">` : name[0];
            } else if (state === 'outgoing') {
                document.getElementById('outgoing-name').textContent = name;
                const av = document.getElementById('outgoing-avatar');
                av.innerHTML = avatar ? `<img src="${avatar}">` : name[0];
            }
        }
        
        // Active controls reset
        if (state === 'active') {
            document.getElementById('btn-mute').classList.remove('muted');
            document.getElementById('btn-mute').innerHTML = '<i class="bi bi-mic-fill"></i>';
            document.getElementById('btn-video-toggle').classList.remove('off');
            document.getElementById('btn-video-toggle').innerHTML = '<i class="bi bi-camera-video-fill"></i>';
            
            if (this.currentCallData && this.currentCallData.type === 'voice') {
                document.getElementById('btn-video-toggle').style.display = 'none';
            } else {
                document.getElementById('btn-video-toggle').style.display = 'flex';
            }
        }
    }

    startTimer() {
        this.callDuration = 0;
        const timerEl = document.getElementById('call-timer');
        this.callTimer = setInterval(() => {
            this.callDuration++;
            const mins = Math.floor(this.callDuration / 60).toString().padStart(2, '0');
            const secs = (this.callDuration % 60).toString().padStart(2, '0');
            timerEl.textContent = `${mins}:${secs}`;
        }, 1000);
    }

    stopTimer() {
        if (this.callTimer) clearInterval(this.callTimer);
    }

    toggleMute(e) {
        const audioTrack = this.localStream.getAudioTracks()[0];
        audioTrack.enabled = !audioTrack.enabled;
        const btn = document.getElementById('btn-mute');
        btn.classList.toggle('muted', !audioTrack.enabled);
        btn.innerHTML = audioTrack.enabled ? '<i class="bi bi-mic-fill"></i>' : '<i class="bi bi-mic-mute-fill"></i>';
    }

    toggleVideo(e) {
        const videoTrack = this.localStream.getVideoTracks()[0];
        if (!videoTrack) return;
        videoTrack.enabled = !videoTrack.enabled;
        const btn = document.getElementById('btn-video-toggle');
        btn.classList.toggle('off', !videoTrack.enabled);
        btn.innerHTML = videoTrack.enabled ? '<i class="bi bi-camera-video-fill"></i>' : '<i class="bi bi-camera-video-off-fill"></i>';
    }

    // Beep Fallback specifically for ringtone.mp3 404
    playBeepFallback() {
        this.beepInterval = setInterval(() => {
            if (!window.AudioContext && !window.webkitAudioContext) return;
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            gain.gain.setValueAtTime(0.1, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.5);
        }, 1500);
    }

    stopBeepFallback() {
        if (this.beepInterval) clearInterval(this.beepInterval);
    }

    sendCallStatusMessage(text) {
        if (!this.currentCallData || !this.currentCallData.orderId) return;
        const fd = new FormData();
        fd.append('order_id', this.currentCallData.orderId);
        fd.append('message', text);
        
        fetch('/printflow/public/api/chat/send_message.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && typeof window.loadMessages === 'function') {
                window.loadMessages();
            } else if (data.success && typeof window.loadMsgs === 'function') {
                window.loadMsgs(); // for staff side sometimes it's loadMsgs
            }
        })
        .catch(e => console.error("Failed to send call indicator:", e));
    }
}
