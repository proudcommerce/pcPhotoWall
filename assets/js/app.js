// Picturewall Upload Interface
class PicturewallUpload {
    constructor() {
        this.config = window.appConfig || {};
        this.selectedFile = null;
        this.maxUploadSize = null;
        
        // DOM Elements
        this.fileInput = document.getElementById('fileInput');
        this.selectFileBtn = document.getElementById('selectFileBtn');
        this.uploadZone = document.getElementById('uploadZone');
        this.fileSelectionArea = document.getElementById('fileSelectionArea');
        this.filePreviewArea = document.getElementById('filePreviewArea');
        this.fileName = document.getElementById('fileName');
        this.cancelBtn = document.getElementById('cancelBtn');
        this.usernameInput = document.getElementById('username');
        
        this.init();
    }
    
    init() {
        if (!this.config.eventSlug) {
            console.warn('No event slug configured');
            return;
        }
        
        this.setupEventListeners();
        this.setupDragAndDrop();
        this.loadEventConfig();
        this.focusUsername();
    }
    
    setupEventListeners() {
        // File selection button
        if (this.selectFileBtn) {
            this.selectFileBtn.addEventListener('click', () => {
                this.fileInput.click();
            });
        }
        
        // File input change
        if (this.fileInput) {
        this.fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                this.handleFileSelect(e.target.files[0]);
            }
        });
        }
        
        // Upload button removed - upload starts automatically
        
        // Cancel button
        if (this.cancelBtn) {
            this.cancelBtn.addEventListener('click', () => {
                this.cancelUpload();
            });
        }
        
        // Username input
        if (this.usernameInput) {
            this.usernameInput.addEventListener('input', (e) => {
                this.saveUsername(e.target.value);
            });
        }
    }
    
    setupDragAndDrop() {
        if (!this.uploadZone) return;
        
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.uploadZone.addEventListener(eventName, this.preventDefaults, false);
            document.body.addEventListener(eventName, this.preventDefaults, false);
        });
        
        // Highlight drop area when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            this.uploadZone.addEventListener(eventName, () => {
                this.uploadZone.classList.add('drag-over');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            this.uploadZone.addEventListener(eventName, () => {
                this.uploadZone.classList.remove('drag-over');
            }, false);
        });
        
        // Handle dropped files
        this.uploadZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                this.handleFileSelect(files[0]);
            }
        }, false);
    }
    
    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    handleFileSelect(file) {
        if (!this.validateFile(file)) {
            return;
        }
        
        this.selectedFile = file;
        this.showFilePreview(file);
        
        // Automatically start upload after file selection
        this.startUpload();
    }
    
    async loadEventConfig() {
        try {
            const response = await fetch(`/api/event-config.php?event_slug=${this.config.eventSlug}`);
            const data = await response.json();
            
            if (data.success && data.event) {
                this.maxUploadSize = data.event.max_upload_size || 10485760; // Default to 10MB
                this.updateUploadInfo();
            }
        } catch (error) {
            console.warn('Could not load event config:', error);
            this.maxUploadSize = 10485760; // Default to 10MB
        }
    }
    
    updateUploadInfo() {
        const uploadInfo = document.querySelector('.upload-info ul');
        if (uploadInfo && this.maxUploadSize) {
            const maxSizeMB = Math.round(this.maxUploadSize / 1024 / 1024);
            const sizeInfo = document.createElement('li');
            sizeInfo.textContent = `Maximale Dateigröße: ${maxSizeMB}MB`;
            uploadInfo.appendChild(sizeInfo);
        }
    }
    
    validateFile(file) {
        // Check file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif'];
        
        // Also check file extension for HEIC/HEIF files (MIME detection can be unreliable)
        const fileExtension = file.name.split('.').pop().toLowerCase();
        const isHeicFile = ['heic', 'heif'].includes(fileExtension);
        
        if (!allowedTypes.includes(file.type) && !isHeicFile) {
            this.showError('Dateityp nicht erlaubt. Erlaubt: JPG, PNG, GIF, WebP, HEIC, HEIF');
            return false;
        }
        
        // Check file size if maxUploadSize is set
        if (this.maxUploadSize && file.size > this.maxUploadSize) {
            const maxSizeMB = Math.round(this.maxUploadSize / 1024 / 1024);
            const fileSizeMB = Math.round(file.size / 1024 / 1024);
            this.showError(`Datei zu groß: ${fileSizeMB}MB (Maximum: ${maxSizeMB}MB)`);
            return false;
        }
        
        return true;
    }
    
    showFilePreview(file) {
        // Update file info
        if (this.fileName) {
            this.fileName.textContent = file.name;
        }
        
        
        // Show preview area, hide selection area
        if (this.fileSelectionArea) {
            this.fileSelectionArea.style.display = 'none';
        }
        
        if (this.filePreviewArea) {
            this.filePreviewArea.style.display = 'block';
        }
    }
    
    
    async startUpload() {
        if (!this.selectedFile) {
            this.showError('Keine Datei ausgewählt');
            return;
        }
        
        // Show loading state
        this.setUploadState('uploading');
        
        try {
        const formData = new FormData();
            formData.append('photo', this.selectedFile);
        formData.append('event_slug', this.config.eventSlug);
        formData.append('csrf_token', this.config.csrfToken);
        
        if (this.usernameInput && this.usernameInput.value.trim()) {
            formData.append('username', this.usernameInput.value.trim());
        }
        
            const response = await fetch(this.config.uploadUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                const message = result.message || 'Foto erfolgreich hochgeladen!';
                this.showSuccess(message);
                // Don't reset immediately, let user see the success message
                setTimeout(() => {
                    this.resetUpload();
                }, 3000); // Longer timeout for moderation message
            } else {
                this.showError(result.error || 'Upload fehlgeschlagen');
                // Reset interface after error
                setTimeout(() => {
                    this.resetUpload();
                }, 3000);
            }
            
        } catch (error) {
            console.error('Upload error:', error);
            this.showError('Upload fehlgeschlagen: ' + error.message);
            // Reset interface after error
            setTimeout(() => {
                this.resetUpload();
            }, 3000);
        }
        
        this.setUploadState('idle');
    }
    
    cancelUpload() {
        this.resetUpload();
    }
    
    resetUpload() {
        this.selectedFile = null;
        
        // Clear file input
        if (this.fileInput) {
            this.fileInput.value = '';
        }
        
        // Show selection area, hide preview area
        if (this.fileSelectionArea) {
            this.fileSelectionArea.style.display = 'block';
        }
        
        if (this.filePreviewArea) {
            this.filePreviewArea.style.display = 'none';
        }
        
        // Clear any error/success messages
        this.clearMessages();
    }
    
    setUploadState(state) {
        // Show/hide and configure cancel button
        if (this.cancelBtn) {
            switch (state) {
                case 'uploading':
                    this.cancelBtn.style.display = 'inline-block';
                    this.cancelBtn.disabled = true;
                    this.cancelBtn.textContent = '⏳ Upload läuft...';
                    break;
                case 'idle':
                default:
                    this.cancelBtn.style.display = 'none';
                    this.cancelBtn.disabled = false;
                    this.cancelBtn.textContent = '❌ Abbrechen';
                    break;
            }
        }
    }
    
    showError(message) {
        this.showMessage(message, 'error');
    }
    
    showSuccess(message) {
        this.showMessage(message, 'success');
    }
    
    showMessage(message, type) {
        // Remove existing messages
        this.clearMessages();
        
        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.className = `upload-message upload-message-${type}`;
        messageDiv.textContent = message;
        
        // Insert in the preview area or upload section
        const filePreviewArea = document.getElementById('filePreviewArea');
        
        if (filePreviewArea && filePreviewArea.style.display !== 'none') {
            // If preview area is visible, add message there
            filePreviewArea.appendChild(messageDiv);
        } else {
            // Fallback: add to upload section
            const uploadSection = document.querySelector('.upload-section');
            if (uploadSection) {
                uploadSection.appendChild(messageDiv);
            }
        }
        
        // Auto-hide after 8 seconds for success messages, 5 seconds for errors
        const hideDelay = type === 'success' ? 8000 : 5000;
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.parentNode.removeChild(messageDiv);
            }
        }, hideDelay);
    }
    
    clearMessages() {
        const messages = document.querySelectorAll('.upload-message');
        messages.forEach(msg => msg.remove());
    }
    
    saveUsername(username) {
        if (username.trim()) {
            localStorage.setItem('picturewall_username', username.trim());
        }
    }
    
    focusUsername() {
        if (this.usernameInput) {
            this.usernameInput.focus();
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
        new PicturewallUpload();
});