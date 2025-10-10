class GalleryManager {
    constructor() {
        this.photos = window.galleryConfig?.photos || [];
        this.filteredPhotos = [...this.photos];
        this.currentSort = 'newest';
        this.currentSearch = '';
        this.currentPhotoIndex = 0;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.renderPhotos();
    }
    
    bindEvents() {
        // Sort functionality
        const sortSelect = document.getElementById('sortBy');
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                this.currentSort = e.target.value;
                this.sortAndFilter();
            });
        }
        
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.currentSearch = e.target.value.toLowerCase();
                this.sortAndFilter();
            });
        }
        
        // Keyboard navigation for lightbox
        document.addEventListener('keydown', (e) => {
            if (document.getElementById('lightbox').classList.contains('active')) {
                if (e.key === 'Escape') {
                    this.closeLightbox();
                } else if (e.key === 'ArrowLeft') {
                    this.previousPhoto();
                } else if (e.key === 'ArrowRight') {
                    this.nextPhoto();
                }
            }
        });
    }
    
    sortAndFilter() {
        // Filter photos based on search
        this.filteredPhotos = this.photos.filter(photo => {
            const username = (photo.username || '').toLowerCase();
            const originalName = (photo.original_name || '').toLowerCase();
            const searchTerm = this.currentSearch;
            
            if (!searchTerm) return true;
            
            return username.includes(searchTerm) || originalName.includes(searchTerm);
        });
        
        // Sort photos
        this.filteredPhotos.sort((a, b) => {
            switch (this.currentSort) {
                case 'newest':
                    return new Date(b.uploaded_at) - new Date(a.uploaded_at);
                case 'oldest':
                    return new Date(a.uploaded_at) - new Date(b.uploaded_at);
                case 'username':
                    const usernameA = (a.username || '').toLowerCase();
                    const usernameB = (b.username || '').toLowerCase();
                    return usernameA.localeCompare(usernameB);
                case 'random':
                    return Math.random() - 0.5;
                default:
                    return 0;
            }
        });
        
        this.renderPhotos();
        this.updatePhotoCount();
    }
    
    renderPhotos() {
        const galleryGrid = document.getElementById('galleryGrid');
        if (!galleryGrid) return;
        
        // Clear existing photos
        galleryGrid.innerHTML = '';
        
        // Add photos to grid
        this.filteredPhotos.forEach(photo => {
            const photoElement = this.createPhotoElement(photo);
            galleryGrid.appendChild(photoElement);
        });
        
        // Add fade-in animation
        setTimeout(() => {
            const items = galleryGrid.querySelectorAll('.gallery-item');
            items.forEach((item, index) => {
                setTimeout(() => {
                    item.classList.add('fade-in');
                }, index * 50);
            });
        }, 100);
    }
    
    createPhotoElement(photo) {
        const item = document.createElement('div');
        item.className = 'gallery-item';
        item.setAttribute('data-username', photo.username || '');
        item.setAttribute('data-date', photo.uploaded_at);
        item.setAttribute('data-original-name', photo.original_name || '');
        
        const imageContainer = document.createElement('div');
        imageContainer.className = 'gallery-item-image';
        
        const img = document.createElement('img');
        img.src = photo.thumbnail_url || photo.url;
        img.alt = photo.original_name || 'Foto';
        img.loading = 'lazy';
        img.onclick = () => this.openLightbox(photo);
        
        const overlay = document.createElement('div');
        overlay.className = 'gallery-item-overlay';
        
        const info = document.createElement('div');
        info.className = 'gallery-item-info';
        
        if (window.galleryConfig?.showUsername && photo.username) {
            const usernameSpan = document.createElement('span');
            usernameSpan.className = 'username';
            usernameSpan.textContent = photo.username;
            info.appendChild(usernameSpan);
        }
        
        if (window.galleryConfig?.showDate) {
            const dateSpan = document.createElement('span');
            dateSpan.className = 'date';
            dateSpan.textContent = photo.uploaded_at_formatted;
            info.appendChild(dateSpan);
        }
        
        overlay.appendChild(info);
        imageContainer.appendChild(img);
        imageContainer.appendChild(overlay);
        item.appendChild(imageContainer);
        
        return item;
    }
    
    openLightbox(photo) {
        const lightbox = document.getElementById('lightbox');
        const lightboxImage = document.getElementById('lightboxImage');
        
        if (!lightbox || !lightboxImage) return;
        
        // Find photo index in filtered photos
        this.currentPhotoIndex = this.filteredPhotos.findIndex(p => p.id === photo.id);
        if (this.currentPhotoIndex === -1) {
            this.currentPhotoIndex = 0;
        }
        
        // Set image
        lightboxImage.src = photo.url;
        lightboxImage.alt = photo.original_name || 'Foto';
        
        // Show lightbox
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Preload image
        const img = new Image();
        img.onload = () => {
            lightboxImage.style.opacity = '1';
        };
        img.src = photo.url;
    }
    
    closeLightbox() {
        const lightbox = document.getElementById('lightbox');
        if (lightbox) {
            lightbox.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    nextPhoto() {
        if (this.filteredPhotos.length === 0) return;
        
        this.currentPhotoIndex = (this.currentPhotoIndex + 1) % this.filteredPhotos.length;
        this.showCurrentPhoto();
    }
    
    previousPhoto() {
        if (this.filteredPhotos.length === 0) return;
        
        this.currentPhotoIndex = (this.currentPhotoIndex - 1 + this.filteredPhotos.length) % this.filteredPhotos.length;
        this.showCurrentPhoto();
    }
    
    showCurrentPhoto() {
        const lightboxImage = document.getElementById('lightboxImage');
        if (!lightboxImage || this.filteredPhotos.length === 0) return;
        
        const photo = this.filteredPhotos[this.currentPhotoIndex];
        if (!photo) return;
        
        // Fade out current image
        lightboxImage.style.opacity = '0';
        
        // Load new image
        const img = new Image();
        img.onload = () => {
            lightboxImage.src = photo.url;
            lightboxImage.alt = photo.original_name || 'Foto';
            lightboxImage.style.opacity = '1';
        };
        img.src = photo.url;
    }
    
    updatePhotoCount() {
        const photoCountElement = document.querySelector('.photo-count');
        if (photoCountElement) {
            const total = this.photos.length;
            const filtered = this.filteredPhotos.length;
            
            if (total === filtered) {
                photoCountElement.textContent = `${total} Fotos`;
            } else {
                photoCountElement.textContent = `${filtered} von ${total} Fotos`;
            }
        }
    }
}

// Global functions for HTML onclick handlers
function openLightbox(imageUrl, title, username, date) {
    const photo = {
        url: imageUrl,
        original_name: title,
        username: username,
        uploaded_at_formatted: date
    };
    
    if (window.galleryManager) {
        window.galleryManager.openLightbox(photo);
    }
}

function closeLightbox() {
    if (window.galleryManager) {
        window.galleryManager.closeLightbox();
    }
}

function nextPhoto() {
    if (window.galleryManager) {
        window.galleryManager.nextPhoto();
    }
}

function previousPhoto() {
    if (window.galleryManager) {
        window.galleryManager.previousPhoto();
    }
}

// Initialize gallery when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.galleryManager = new GalleryManager();
});

// Handle window resize for responsive adjustments
window.addEventListener('resize', function() {
    // Debounce resize events
    clearTimeout(window.resizeTimeout);
    window.resizeTimeout = setTimeout(() => {
        // Any resize-specific logic can go here
    }, 250);
});

// Handle image loading errors
document.addEventListener('error', function(e) {
    if (e.target.tagName === 'IMG' && e.target.closest('.gallery-item')) {
        e.target.style.display = 'none';
        const item = e.target.closest('.gallery-item');
        if (item) {
            item.classList.add('error');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'gallery-item-error';
            errorDiv.innerHTML = '⚠️ Bild konnte nicht geladen werden';
            errorDiv.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                color: #dc3545;
                font-size: 0.9rem;
                text-align: center;
                padding: 1rem;
            `;
            item.querySelector('.gallery-item-image').appendChild(errorDiv);
        }
    }
}, true);

// Lazy loading enhancement
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    observer.unobserve(img);
                }
            }
        });
    });
    
    // Observe all gallery images
    document.addEventListener('DOMContentLoaded', function() {
        const images = document.querySelectorAll('.gallery-item img[data-src]');
        images.forEach(img => imageObserver.observe(img));
    });
}
