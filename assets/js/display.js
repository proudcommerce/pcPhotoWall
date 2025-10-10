// Display-specific JavaScript (separate from app.js for better organization)
class PicturewallDisplay {
    constructor() {
        this.config = window.displayConfig || {};
        this.photos = [];
        this.currentIndex = 0;
        this.isPlaying = true;
        this.intervalId = null;
        this.currentTransition = 'fade';
        this.autoHideControls = true;
        this.controlsTimeout = null;
        this.refreshInterval = null;
        this.lastPhotoCount = 0;
        
        this.init();
    }
    
    async init() {
        console.log('PicturewallDisplay init started');
        console.log('Config:', this.config);
        await this.loadPhotos();
        this.setupEventListeners();
        this.setupAutoHideControls();
        this.startSlideshow();
        this.startPhotoRefresh();
        console.log('PicturewallDisplay init completed');
    }
    
    async loadPhotos() {
        try {
            console.log('Loading photos for event:', this.config.eventSlug);
            
            // Build API URL with all display parameters
            const params = new URLSearchParams({
                event_slug: this.config.eventSlug,
                display_mode: this.config.displayMode,
                display_count: this.config.displayCount,
                display_interval: this.config.displayInterval
            });
            
            // Add show_logo parameter if it exists in config
            if (this.config.showLogo !== undefined) {
                params.append('show_logo', this.config.showLogo ? '1' : '0');
            }
            
            console.log('API URL:', `${this.config.photosUrl}?${params.toString()}`);
            const response = await fetch(`${this.config.photosUrl}?${params.toString()}`);
            console.log('Response status:', response.status);
            
            const data = await response.json();
            console.log('Photos API response:', data);
            
            if (data.success && data.photos && data.photos.length > 0) {
                this.photos = data.photos;
                this.eventConfig = data.event;
                this.lastPhotoCount = data.photos.length;
                console.log('Photos loaded:', this.photos.length);
                this.renderPhotos();
                this.updatePhotoCount();
                this.updateDisplayMode();
            } else {
                console.log('No photos found or API error');
                this.showNoPhotos();
            }
        } catch (error) {
            console.error('Error loading photos:', error);
            this.showNoPhotos();
        }
    }
    
    getEffectiveDisplayCount() {
        const originalCount = this.config.displayCount || 1;
        
        // On mobile devices (screen width <= 768px), limit to 2 photos maximum
        if (window.innerWidth <= 768) {
            return Math.min(originalCount, 2);
        }
        
        return originalCount;
    }
    
    renderPhotos() {
        console.log('renderPhotos called with', this.photos.length, 'total photos');
        
        const container = document.getElementById('photosGrid');
        const loading = document.getElementById('loading');
        const noPhotos = document.getElementById('noPhotos');
        const logoElement = document.querySelector('.display-logo');
        
        console.log('Container found:', !!container);
        console.log('Loading found:', !!loading);
        console.log('NoPhotos found:', !!noPhotos);
        console.log('Logo element found:', !!logoElement);
        
        if (!container) {
            console.error('photosGrid container not found!');
            return;
        }
        
        // Hide loading and no photos
        if (loading) loading.style.display = 'none';
        if (noPhotos) noPhotos.style.display = 'none';
        
        // Ensure logo is always visible if it exists
        if (logoElement) {
            logoElement.style.display = 'block';
            logoElement.style.visibility = 'visible';
            logoElement.style.opacity = '1';
        }
        
        // Clear container
        container.innerHTML = '';
        container.style.display = 'grid';
        
        // Set layout based on actual photos to display
        const displayCount = this.getEffectiveDisplayCount();
        const remainingPhotos = this.photos.length - this.currentIndex;
        const photoCount = Math.min(displayCount, remainingPhotos);
        container.className = 'photos-grid';
        
        console.log('Rendering photos - currentIndex:', this.currentIndex, 'remainingPhotos:', remainingPhotos, 'photoCount:', photoCount);
        
        if (photoCount === 1) {
            // Single photo - fullscreen
            container.classList.add('single', 'fullscreen');
            container.style.display = 'flex';
            container.style.gridTemplateColumns = '1fr';
            container.style.gridTemplateRows = '1fr';
        } else if (photoCount === 2) {
            // 2 photos - check if mobile for layout
            if (window.innerWidth <= 768) {
                // Mobile: 2 photos stacked vertically
                container.classList.add('grid', 'two-photos-mobile');
                container.style.display = 'grid';
                container.style.gridTemplateColumns = '1fr';
                container.style.gridTemplateRows = '1fr 1fr';
            } else {
                // Desktop: 2 photos side by side
                container.classList.add('grid', 'two-photos');
                container.style.display = 'grid';
                container.style.gridTemplateColumns = '1fr 1fr';
                container.style.gridTemplateRows = '1fr';
            }
        } else if (photoCount === 3) {
            // 3 photos - side by side, full height
            container.classList.add('grid', 'three-photos');
            container.style.display = 'grid';
            container.style.gridTemplateColumns = '1fr 1fr 1fr';
            container.style.gridTemplateRows = '1fr';
        } else if (photoCount === 4) {
            // 4 photos - check if mobile for layout
            if (window.innerWidth <= 768) {
                // Mobile: 2 photos stacked vertically (limit to 2 on mobile)
                container.classList.add('grid', 'four-photos-mobile');
                container.style.display = 'grid';
                container.style.gridTemplateColumns = '1fr';
                container.style.gridTemplateRows = '1fr 1fr';
            } else {
                // Desktop: 4 photos in 2x2 grid
                container.classList.add('grid', 'four-photos');
                container.style.display = 'grid';
                container.style.gridTemplateColumns = '1fr 1fr';
                container.style.gridTemplateRows = '1fr 1fr';
            }
        } else if (photoCount === 6) {
            // 6 photos - check if mobile for layout
            if (window.innerWidth <= 768) {
                // Mobile: 2 photos stacked vertically
                container.classList.add('grid', 'six-photos-mobile');
                container.style.display = 'grid';
                container.style.gridTemplateColumns = '1fr';
                container.style.gridTemplateRows = '1fr 1fr';
            } else {
                // Desktop: 6 photos in 3x2 grid
                container.classList.add('grid', 'six-photos');
                container.style.display = 'grid';
                container.style.gridTemplateColumns = '1fr 1fr 1fr';
                container.style.gridTemplateRows = '1fr 1fr';
            }
        } else if (photoCount === 8) {
            // 8 photos - check if mobile for layout
            if (window.innerWidth <= 768) {
                // Mobile: 2 photos stacked vertically
                container.classList.add('grid', 'eight-photos-mobile');
                container.style.display = 'grid';
                container.style.gridTemplateColumns = '1fr';
                container.style.gridTemplateRows = '1fr 1fr';
            } else {
                // Desktop: 8 photos in 4x2 grid
                container.classList.add('grid', 'eight-photos');
                container.style.display = 'grid';
                container.style.gridTemplateColumns = '1fr 1fr 1fr 1fr';
                container.style.gridTemplateRows = '1fr 1fr';
            }
        } else if (photoCount === 10) {
            // 10 photos - check if mobile for layout
            if (window.innerWidth <= 768) {
                // Mobile: 2 photos stacked vertically
                container.classList.add('grid', 'ten-photos-mobile');
                container.style.display = 'grid';
                container.style.gridTemplateColumns = '1fr';
                container.style.gridTemplateRows = '1fr 1fr';
            } else {
                // Desktop: 10 photos in 5x2 grid
                container.classList.add('grid', 'ten-photos');
                container.style.display = 'grid';
                container.style.gridTemplateColumns = '1fr 1fr 1fr 1fr 1fr';
                container.style.gridTemplateRows = '1fr 1fr';
            }
        } else {
            // Fallback - single column
            container.classList.add('single');
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
        }
        
        console.log('Layout set:', container.className);
        console.log('Photo count:', photoCount);
        
        // Add only the photos that should be displayed (based on current slideshow position)
        const startIndex = this.currentIndex;
        const endIndex = startIndex + photoCount;
        
        // Add photos with staggered delay for smooth appearance
        for (let i = startIndex; i < endIndex; i++) {
            const photo = this.photos[i];
            const photoElement = this.createPhotoElement(photo, i);
            photoElement.style.opacity = '0'; // Start invisible
            container.appendChild(photoElement);
            
            // Show photo with delay
            const delay = (i - startIndex) * 200; // 200ms delay between each photo
            setTimeout(() => {
                photoElement.style.transition = 'opacity 0.5s ease-in-out';
                photoElement.style.opacity = '1';
            }, delay);
        }
        
        // On mobile, ensure we only show 2 photos maximum
        if (window.innerWidth <= 768 && photoCount > 2) {
            const mobilePhotos = container.querySelectorAll('.photo-item');
            for (let i = 2; i < mobilePhotos.length; i++) {
                mobilePhotos[i].style.display = 'none';
            }
        }
        
        console.log('Added', endIndex - startIndex, 'photo elements to container');
        
        // Update progress after a short delay to ensure DOM is updated
        setTimeout(() => {
            this.updateProgress();
        }, 100);
    }
    
    createPhotoElement(photo, index) {
        const div = document.createElement('div');
        div.className = 'photo-item';
        div.dataset.index = index;
        
        const img = document.createElement('img');
        img.src = photo.url;
        img.alt = photo.original_name || 'Foto';
        img.loading = 'lazy';
        
        // Add error handling for images
        img.onerror = () => {
            console.warn(`Failed to load image: ${photo.url}`);
            div.style.display = 'none';
        };
        
        div.appendChild(img);
        
        // Add overlay only if configured to show username or date
        if (this.config.eventConfig && (this.config.eventConfig.showUsername || this.config.eventConfig.showDate)) {
            const overlay = document.createElement('div');
            overlay.className = 'photo-overlay';
            overlay.style.opacity = this.config.eventConfig.overlayOpacity || 0.8;
            
            const info = document.createElement('div');
            info.className = 'photo-info';
            
            if (this.config.eventConfig.showUsername) {
                const username = document.createElement('div');
                username.className = 'photo-username';
                username.textContent = photo.username || '';
                info.appendChild(username);
            }
            
            if (this.config.eventConfig.showDate) {
                const time = document.createElement('div');
                time.className = 'photo-time';
                time.textContent = photo.uploaded_at_formatted;
                info.appendChild(time);
            }
            
            overlay.appendChild(info);
            div.appendChild(overlay);
        }
        
        return div;
    }
    
    showPhoto(index) {
        const photos = document.querySelectorAll('.photo-item');
        
        if (photos.length === 0) return;
        
        // Hide all photos with transition
        photos.forEach(photo => {
            photo.classList.remove('active');
        });
        
        // Show current photo with transition
        if (photos[index]) {
            photos[index].classList.add('active');
        }
        
        this.currentIndex = index;
        this.updateProgress();
    }
    
    nextPhoto() {
        if (this.photos.length === 0) return;
        
        const displayCount = this.getEffectiveDisplayCount();
        
        // Calculate how many photos we can show from current position
        const remainingPhotos = this.photos.length - this.currentIndex;
        
        if (remainingPhotos <= displayCount) {
            // Not enough photos left, reset to beginning
            this.currentIndex = 0;
        } else {
            // Move by display count
            this.currentIndex += displayCount;
        }
        
        console.log('Next photo - currentIndex:', this.currentIndex, 'remaining:', this.photos.length - this.currentIndex);
        
        // Fade out current photos before showing new ones
        this.fadeOutCurrentPhotos(() => {
            this.renderPhotos(); // Re-render with new photos
            // Ensure logo is visible after photo change
            const logoElement = document.querySelector('.display-logo');
            if (logoElement) {
                logoElement.style.display = 'block';
                logoElement.style.visibility = 'visible';
                logoElement.style.opacity = '1';
            }
        });
    }
    
    prevPhoto() {
        if (this.photos.length === 0) return;
        
        const displayCount = this.getEffectiveDisplayCount();
        
        if (this.currentIndex <= 0) {
            // Go to the last valid position
            const remainingPhotos = this.photos.length % displayCount;
            if (remainingPhotos === 0) {
                this.currentIndex = this.photos.length - displayCount;
            } else {
                this.currentIndex = this.photos.length - remainingPhotos;
            }
        } else {
            this.currentIndex -= displayCount; // Move back by display count
        }
        
        // Fade out current photos before showing new ones
        this.fadeOutCurrentPhotos(() => {
            this.renderPhotos(); // Re-render with new photos
            // Ensure logo is visible after photo change
            const logoElement = document.querySelector('.display-logo');
            if (logoElement) {
                logoElement.style.display = 'block';
                logoElement.style.visibility = 'visible';
                logoElement.style.opacity = '1';
            }
        });
    }
    
    startSlideshow() {
        const displayCount = this.getEffectiveDisplayCount();
        
        if (this.photos.length <= displayCount) {
            // Not enough photos for slideshow
            this.isPlaying = false;
            return;
        }
        
        this.stopSlideshow();
        this.intervalId = setInterval(() => {
            if (this.isPlaying) {
                this.nextPhoto();
            }
        }, this.config.displayInterval * 1000);
    }
    
    stopSlideshow() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }
    
    startPhotoRefresh() {
        // Check for new photos every 10 seconds
        this.refreshInterval = setInterval(async () => {
            await this.checkForNewPhotos();
        }, 10000);
    }
    
    stopPhotoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
    
    async checkForNewPhotos() {
        try {
            console.log('Checking for new photos...');
            
            // Build API URL with all display parameters
            const params = new URLSearchParams({
                event_slug: this.config.eventSlug,
                display_mode: this.config.displayMode,
                display_count: this.config.displayCount,
                display_interval: this.config.displayInterval
            });
            
            // Add show_logo parameter if it exists in config
            if (this.config.showLogo !== undefined) {
                params.append('show_logo', this.config.showLogo ? '1' : '0');
            }
            
            const response = await fetch(`${this.config.photosUrl}?${params.toString()}`);
            const data = await response.json();
            
            if (data.success && data.photos) {
                const newPhotoCount = data.photos.length;
                console.log('Current photos:', this.photos.length, 'New photos:', newPhotoCount, 'Last count:', this.lastPhotoCount);
                
                if (newPhotoCount > this.photos.length) {
                    console.log('New photos detected! Reloading...');
                    this.photos = data.photos;
                    this.lastPhotoCount = newPhotoCount;
                    
                    // Reset to beginning if we're past the end
                    const displayCount = this.getEffectiveDisplayCount();
                    if (this.currentIndex >= this.photos.length - displayCount) {
                        this.currentIndex = 0;
                    }
                    
                    // Fade out current photos before showing new ones
                    this.fadeOutCurrentPhotos(() => {
                        this.renderPhotos();
                        this.startSlideshow(); // Restart slideshow with new photos
                        // Ensure logo is visible after refresh
                        const logoElement = document.querySelector('.display-logo');
                        if (logoElement) {
                            logoElement.style.display = 'block';
                            logoElement.style.visibility = 'visible';
                            logoElement.style.opacity = '1';
                        }
                    });
                } else {
                    // Update lastPhotoCount even if no new photos
                    this.lastPhotoCount = newPhotoCount;
                }
            }
        } catch (error) {
            console.error('Error checking for new photos:', error);
        }
    }
    
    togglePlayPause() {
        this.isPlaying = !this.isPlaying;
    }
    
    updateProgress() {
        // Progress bar removed
    }
    
    setupEventListeners() {
        // Keyboard controls only
        document.addEventListener('keydown', (e) => {
            switch(e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    this.prevPhoto();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.nextPhoto();
                    break;
                case ' ':
                    e.preventDefault();
                    this.togglePlayPause();
                    break;
                case 'f':
                case 'F':
                    e.preventDefault();
                    this.toggleFullscreen();
                    break;
                case 'r':
                case 'R':
                    e.preventDefault();
                    this.refreshPhotos();
                    break;
            }
        });
    }
    
    setupSettings() {
        // Settings removed
    }
    
    toggleSettings() {
        // Settings removed
    }
    
    async applySettings() {
        // Settings removed
    }
    
    setupAutoHideControls() {
        // Controls removed
    }
    
    resetControlsTimeout() {
        // Controls removed
    }
    
    toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.log('Error attempting to enable fullscreen:', err);
            });
        } else {
            document.exitFullscreen();
        }
    }
    
    updatePhotoCount() {
        // Photo count display removed
    }
    
    updateDisplayMode() {
        // Display mode display removed
    }
    
    showNoPhotos() {
        const container = document.getElementById('photoContainer');
        const loading = document.getElementById('loading');
        const noPhotos = document.getElementById('noPhotos');
        const photosGrid = document.getElementById('photosGrid');
        const logoElement = document.querySelector('.display-logo');
        
        if (loading) loading.style.display = 'none';
        if (photosGrid) photosGrid.style.display = 'none';
        if (noPhotos) noPhotos.style.display = 'block';
        
        // Ensure logo is always visible even when no photos
        if (logoElement) {
            logoElement.style.display = 'block';
            logoElement.style.visibility = 'visible';
            logoElement.style.opacity = '1';
        }
        
        this.stopSlideshow();
    }
    
    // Public methods for external control
    play() {
        this.isPlaying = true;
        this.startSlideshow();
    }
    
    pause() {
        this.isPlaying = false;
        this.stopSlideshow();
    }
    
    setInterval(seconds) {
        this.config.displayInterval = seconds;
        if (this.isPlaying) {
            this.startSlideshow();
        }
    }
    
    fadeOutCurrentPhotos(callback) {
        const currentPhotos = document.querySelectorAll('.photo-item');
        const logoElement = document.querySelector('.display-logo');
        
        // Ensure logo stays visible during transitions
        if (logoElement) {
            logoElement.style.display = 'block';
            logoElement.style.visibility = 'visible';
            logoElement.style.opacity = '1';
            logoElement.style.transition = 'none'; // Disable transitions on logo
        }
        
        if (currentPhotos.length === 0) {
            callback();
            return;
        }
        
        // Fade out all current photos with staggered timing
        currentPhotos.forEach((photo, index) => {
            const delay = index * 100; // 100ms delay between each fade out
            setTimeout(() => {
                photo.style.transition = 'opacity 0.3s ease-in-out';
                photo.style.opacity = '0';
            }, delay);
        });
        
        // Wait for all photos to fade out, then call callback
        const totalDelay = (currentPhotos.length - 1) * 100 + 300; // Last photo delay + fade duration
        setTimeout(() => {
            // Ensure logo is still visible after transition
            if (logoElement) {
                logoElement.style.display = 'block';
                logoElement.style.visibility = 'visible';
                logoElement.style.opacity = '1';
            }
            callback();
        }, totalDelay);
    }
    
    // Manual refresh function for testing
    async refreshPhotos() {
        console.log('Manual photo refresh triggered');
        await this.checkForNewPhotos();
    }
    
    destroy() {
        this.stopSlideshow();
        this.stopPhotoRefresh();
    }
}

// Initialize display when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, checking for display-mode class');
    console.log('Body classes:', document.body.className);
    
    if (document.body.classList.contains('display-mode')) {
        console.log('Initializing PicturewallDisplay...');
        window.picturewallDisplay = new PicturewallDisplay();
    } else {
        console.log('Not a display page, skipping initialization');
    }
});

// Handle visibility change (pause when tab is not visible)
document.addEventListener('visibilitychange', () => {
    if (window.picturewallDisplay) {
        if (document.hidden) {
            window.picturewallDisplay.pause();
        } else {
            window.picturewallDisplay.play();
        }
    }
});

// Handle window resize
window.addEventListener('resize', () => {
    if (window.picturewallDisplay) {
        // Re-render photos on resize to adapt to new screen size
        window.picturewallDisplay.renderPhotos();
    }
});

// This is handled by the first DOMContentLoaded listener above
