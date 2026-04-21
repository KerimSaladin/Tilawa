/**
 * Custom Video Player Component
 * Vanilla JavaScript implementation of a modern video player
 */

class CustomVideoPlayer {
    constructor(container, options = {}) {
        this.container = typeof container === 'string' ? document.querySelector(container) : container;
        this.videoSrc = options.src || '';
        this.videoElement = null;
        this.isPlaying = false;
        this.volume = 1;
        this.progress = 0;
        this.isMuted = false;
        this.playbackSpeed = 1;
        this.showControls = false;
        this.currentTime = 0;
        this.duration = 0;
        this.controlsTimeout = null;

        this.init();
    }

    init() {
        this.createPlayer();
        this.attachEventListeners();
    }

    createPlayer() {
        // Create player wrapper
        const playerWrapper = document.createElement('div');
        playerWrapper.className = 'custom-video-player';

        // Create video element
        this.videoElement = document.createElement('video');
        this.videoElement.className = 'custom-video-player__video';
        this.videoElement.playsInline = true;

        // Set src if provided, otherwise will be set from source elements
        if (this.videoSrc) {
            this.videoElement.src = this.videoSrc;
        }

        // Create controls container
        const controlsContainer = document.createElement('div');
        controlsContainer.className = 'custom-video-player__controls';

        // Progress bar container
        const progressContainer = document.createElement('div');
        progressContainer.className = 'custom-video-player__progress-container';

        const currentTimeSpan = document.createElement('span');
        currentTimeSpan.className = 'custom-video-player__time';
        currentTimeSpan.textContent = '0:00';
        this.currentTimeElement = currentTimeSpan;

        const progressSlider = document.createElement('div');
        progressSlider.className = 'custom-video-player__progress-slider';
        this.progressSlider = progressSlider;

        const durationSpan = document.createElement('span');
        durationSpan.className = 'custom-video-player__time';
        durationSpan.textContent = '0:00';
        this.durationElement = durationSpan;

        progressContainer.appendChild(currentTimeSpan);
        progressContainer.appendChild(progressSlider);
        progressContainer.appendChild(durationSpan);

        // Controls row
        const controlsRow = document.createElement('div');
        controlsRow.className = 'custom-video-player__controls-row';

        // Left controls (play/pause, volume)
        const leftControls = document.createElement('div');
        leftControls.className = 'custom-video-player__controls-left';

        // Play/Pause button
        const playPauseBtn = document.createElement('button');
        playPauseBtn.className = 'custom-video-player__btn';
        playPauseBtn.innerHTML = this.getPlayIcon();
        this.playPauseBtn = playPauseBtn;

        // Volume controls
        const volumeContainer = document.createElement('div');
        volumeContainer.className = 'custom-video-player__volume-container';

        const volumeBtn = document.createElement('button');
        volumeBtn.className = 'custom-video-player__btn';
        volumeBtn.innerHTML = this.getVolumeIcon();
        this.volumeBtn = volumeBtn;

        const volumeSlider = document.createElement('div');
        volumeSlider.className = 'custom-video-player__volume-slider';
        this.volumeSlider = volumeSlider;

        volumeContainer.appendChild(volumeBtn);
        volumeContainer.appendChild(volumeSlider);

        leftControls.appendChild(playPauseBtn);
        leftControls.appendChild(volumeContainer);

        // Right controls (playback speed)
        const rightControls = document.createElement('div');
        rightControls.className = 'custom-video-player__controls-right';

        const speeds = [0.5, 1, 1.5, 2];
        speeds.forEach(speed => {
            const speedBtn = document.createElement('button');
            speedBtn.className = 'custom-video-player__btn custom-video-player__speed-btn';
            if (speed === 1) {
                speedBtn.classList.add('active');
            }
            speedBtn.textContent = `${speed}x`;
            speedBtn.dataset.speed = speed;
            rightControls.appendChild(speedBtn);
        });

        controlsRow.appendChild(leftControls);
        controlsRow.appendChild(rightControls);

        controlsContainer.appendChild(progressContainer);
        controlsContainer.appendChild(controlsRow);

        playerWrapper.appendChild(this.videoElement);
        playerWrapper.appendChild(controlsContainer);

        // Replace container content or append
        if (this.container.tagName === 'VIDEO') {
            // Preserve source elements
            const sources = Array.from(this.container.querySelectorAll('source'));
            sources.forEach(source => {
                const newSource = document.createElement('source');
                newSource.src = source.src;
                newSource.type = source.type;
                this.videoElement.appendChild(newSource);
            });

            // Copy other attributes
            if (this.container.hasAttribute('preload')) {
                this.videoElement.preload = this.container.preload;
            }
            if (this.container.hasAttribute('playsinline')) {
                this.videoElement.setAttribute('playsinline', '');
            }

            this.container.parentNode.replaceChild(playerWrapper, this.container);
        } else {
            this.container.innerHTML = '';
            this.container.appendChild(playerWrapper);
        }

        this.playerWrapper = playerWrapper;
        this.controlsContainer = controlsContainer;
    }

    attachEventListeners() {
        // Video events
        this.videoElement.addEventListener('timeupdate', () => this.handleTimeUpdate());
        this.videoElement.addEventListener('loadedmetadata', () => this.handleLoadedMetadata());
        this.videoElement.addEventListener('play', () => {
            this.isPlaying = true;
            this.updatePlayPauseIcon();
        });
        this.videoElement.addEventListener('pause', () => {
            this.isPlaying = false;
            this.updatePlayPauseIcon();
        });
        this.videoElement.addEventListener('click', () => this.togglePlay());

        // Play/Pause button
        this.playPauseBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.togglePlay();
        });

        // Volume button
        this.volumeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleMute();
        });

        // Progress slider
        this.progressSlider.addEventListener('click', (e) => {
            e.stopPropagation();
            this.handleSeek(e);
        });

        // Volume slider
        this.volumeSlider.addEventListener('click', (e) => {
            e.stopPropagation();
            this.handleVolumeChange(e);
        });

        // Speed buttons
        this.playerWrapper.querySelectorAll('.custom-video-player__speed-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const speed = parseFloat(btn.dataset.speed);
                this.setSpeed(speed);
            });
        });

        // Show/hide controls on hover
        this.playerWrapper.addEventListener('mouseenter', () => {
            this.showControls = true;
            this.updateControlsVisibility();
            this.clearControlsTimeout();
        });

        this.playerWrapper.addEventListener('mouseleave', () => {
            this.hideControlsDelayed();
        });

        // Hide controls when video is playing
        this.videoElement.addEventListener('play', () => {
            this.hideControlsDelayed();
        });
    }

    togglePlay() {
        if (this.isPlaying) {
            this.videoElement.pause();
        } else {
            this.videoElement.play();
        }
    }

    handleVolumeChange(e) {
        const rect = this.volumeSlider.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const percentage = Math.min(Math.max((x / rect.width) * 100, 0), 100);
        const newVolume = percentage / 100;

        this.videoElement.volume = newVolume;
        this.volume = newVolume;
        this.isMuted = newVolume === 0;
        this.updateVolumeIcon();
        this.updateVolumeSlider();
    }

    handleTimeUpdate() {
        if (this.videoElement.duration) {
            const progress = (this.videoElement.currentTime / this.videoElement.duration) * 100;
            this.progress = isFinite(progress) ? progress : 0;
            this.currentTime = this.videoElement.currentTime;
            this.duration = this.videoElement.duration;

            this.updateProgress();
            this.updateTimeDisplay();
        }
    }

    handleLoadedMetadata() {
        this.duration = this.videoElement.duration;
        this.updateTimeDisplay();
    }

    handleSeek(e) {
        if (!this.videoElement.duration) return;

        const rect = this.progressSlider.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const percentage = Math.min(Math.max((x / rect.width) * 100, 0), 100);
        const time = (percentage / 100) * this.videoElement.duration;

        if (isFinite(time)) {
            this.videoElement.currentTime = time;
            this.progress = percentage;
            this.updateProgress();
        }
    }

    toggleMute() {
        this.isMuted = !this.isMuted;
        this.videoElement.muted = this.isMuted;

        if (!this.isMuted && this.volume === 0) {
            this.volume = 1;
            this.videoElement.volume = 1;
        } else if (this.isMuted) {
            this.volume = 0;
        }

        this.updateVolumeIcon();
        this.updateVolumeSlider();
    }

    setSpeed(speed) {
        this.videoElement.playbackRate = speed;
        this.playbackSpeed = speed;

        // Update active state
        this.playerWrapper.querySelectorAll('.custom-video-player__speed-btn').forEach(btn => {
            btn.classList.toggle('active', parseFloat(btn.dataset.speed) === speed);
        });
    }

    updatePlayPauseIcon() {
        this.playPauseBtn.innerHTML = this.isPlaying ? this.getPauseIcon() : this.getPlayIcon();
    }

    updateVolumeIcon() {
        this.volumeBtn.innerHTML = this.getVolumeIcon();
    }

    updateProgress() {
        this.progressSlider.style.setProperty('--progress', `${this.progress}%`);
    }

    updateVolumeSlider() {
        this.volumeSlider.style.setProperty('--volume', `${this.volume * 100}%`);
    }

    updateTimeDisplay() {
        this.currentTimeElement.textContent = this.formatTime(this.currentTime);
        this.durationElement.textContent = this.formatTime(this.duration);
    }

    updateControlsVisibility() {
        if (this.showControls) {
            this.controlsContainer.classList.add('visible');
        } else {
            this.controlsContainer.classList.remove('visible');
        }
    }

    hideControlsDelayed() {
        this.clearControlsTimeout();
        this.controlsTimeout = setTimeout(() => {
            if (this.isPlaying) {
                this.showControls = false;
                this.updateControlsVisibility();
            }
        }, 3000);
    }

    clearControlsTimeout() {
        if (this.controlsTimeout) {
            clearTimeout(this.controlsTimeout);
            this.controlsTimeout = null;
        }
    }

    formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }

    getPlayIcon() {
        return `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>`;
    }

    getPauseIcon() {
        return `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect></svg>`;
    }

    getVolumeIcon() {
        if (this.isMuted || this.volume === 0) {
            return `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>`;
        } else if (this.volume > 0.5) {
            return `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>`;
        } else {
            return `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>`;
        }
    }
}

// Auto-initialize video players on page load
// Auto-initialize video players on page load
document.addEventListener('DOMContentLoaded', function () {
    // Find all video elements with data-custom-player attribute
    document.querySelectorAll('video[data-custom-player]').forEach(video => {
        const options = {};

        // Only use src if it's directly on the video element
        if (video.src && video.hasAttribute('src')) {
            options.src = video.src;
        }

        new CustomVideoPlayer(video, options);
    });
});

