<template>
  <div v-if="imageUploadEnabled">
    <div class="device-photo-layout">
      <label>
        {{ __('devices.images') }}
      </label>
      <div class="d-flex flex-wrap device-photos dropzone-previews">
        <FileUploader :url="uploadURL"
          v-if="(edit || add) && !disabled && (images.length + pendingFiles.length) < maxFiles"
          previews-container=".device-photos" @files-changed="handleFilesChanged" @upload-error="handleUploadError"
          :max-files="maxFiles - images.length" :key="'uploader-' + (id || 'new')" ref="fileUploader" />
        <DeviceImage v-for="image in images" :key="'img-' + image.path" :image="image" @remove="$emit('remove', image)"
          :disabled="disabled" />
        <div v-for="(file, index) in pendingFiles" :key="'pending-' + index" class="pending-image-preview">
          <img :src="getFilePreviewUrl(file)" class="pending-image" />
          <button type="button" @click="removePendingFile(file)" class="remove-pending-btn"
            :disabled="disabled">×</button>
        </div>
      </div>
      <div v-if="pendingFiles.length > 0" class="pending-files-info">
        <small class="text-muted">
          {{ pendingFiles.length }} {{ pendingFiles.length === 1 ? 'file' : 'files' }} ready to upload
        </small>
      </div>
    </div>
  </div>
</template>
<script>
import FileUploader from './FileUploader'
import DeviceImage from './DeviceImage'
import axios from 'axios';

export default {
  components: { DeviceImage, FileUploader },
  props: {
    id: {
      type: Number,
      required: false,
      default: null
    },
    add: {
      type: Boolean,
      required: false,
      default: false
    },
    edit: {
      type: Boolean,
      required: false,
      default: false
    },
    disabled: {
      type: Boolean,
      required: false,
      default: false
    }
  },
  data() {
    return {
      maxFiles: 5,
      imagesDuringCreation: null,
      pendingFiles: [],
      filePreviewUrls: {}, // Cache for file preview URLs
      isUploading: false, // Flag to prevent clearing pending files during upload
    }
  },
  computed: {
    images() {
      // TODO LATER The images are currently added/removed/deleted immediately, and so we get them from the store.
      // This should be deferred until the save.
      if (this.id > 0) {
        return this.$store.getters['devices/imagesByDevice'](this.id)
      } else {
        return this.imagesDuringCreation || []
      }
    },
    uploadURL() {
      return '/device/image-upload/' + (this.id ? this.id : 0)
    },
    imageUploadEnabled() {
      return window.Laravel && window.Laravel.imageUploadEnabled;
    }
  },
  watch: {
    // Watch for changes in images prop but preserve pending files during upload
    images: {
      handler(newImages, oldImages) {
        // Don't clear pending files if we're uploading
        if (this.isUploading) {
          console.log('Upload in progress, preserving pending files during images update');
          return;
        }

        // Handle normal images update when not uploading
        if (newImages !== oldImages) {
          console.log('Images updated:', newImages);
        }
      },
      deep: true
    }
  },
  methods: {
    handleFilesChanged(files) {
      console.log('Files changed:', files);
      this.pendingFiles = files;

      // Create preview URLs for new files
      files.forEach(file => {
        if (!this.filePreviewUrls[file.name]) {
          this.filePreviewUrls[file.name] = URL.createObjectURL(file);
        }
      });

      // Emit event to parent component
      this.$emit('pending-files-changed', files);
    },

    handleUploadError(error) {
      console.error('Upload error:', error);
      this.$emit('upload-error', error);
    },

    getFilePreviewUrl(file) {
      return this.filePreviewUrls[file.name] || URL.createObjectURL(file);
    },

    // Method to upload all pending files (called by parent component)
    async uploadPendingFiles() {
      if (this.pendingFiles.length === 0) {
        return { success: true, images: [] };
      }

      console.log('Uploading pending files sequentially:', this.pendingFiles);

      // Set uploading flag to prevent clearing pending files during process
      this.isUploading = true;

      try {
        const allUploadedImages = [];
        let failedUploads = [];

        // Create a copy of pending files to avoid modifying array while iterating
        const filesToUpload = [...this.pendingFiles];

        // Upload files one by one sequentially to avoid server conflicts
        for (let i = 0; i < filesToUpload.length; i++) {
          const file = filesToUpload[i];
          console.log(`Uploading file ${i + 1}/${filesToUpload.length}:`, file.name);

          try {
            const result = await this.uploadSingleFile(file);

            if (result.success && result.images) {
              // Collect all uploaded images
              allUploadedImages.push(...result.images);
              console.log('Successfully uploaded:', file.name, result.images);

              // Remove only this specific file from pending files
              // but keep the others for continued preview
              this.removePendingFile(file);
            } else {
              console.error('Upload failed for file:', file.name, result);
              failedUploads.push({ file: file.name, error: result.error || 'Unknown error' });
            }
          } catch (error) {
            console.error('Upload error for file:', file.name, error);
            failedUploads.push({ file: file.name, error: error.message || 'Upload failed' });

            // Don't break the entire upload process for one failed file
            // Continue with remaining files
          }

          // Add a small delay between uploads to prevent server overload
          if (i < filesToUpload.length - 1) {
            await new Promise(resolve => setTimeout(resolve, 500));
          }
        }

        // Clear any remaining pending files after ALL uploads are complete
        this.clearPendingFiles();

        // Report results
        if (failedUploads.length > 0) {
          console.warn('Some uploads failed:', failedUploads);
          const errorMessage = `Failed to upload ${failedUploads.length} file(s): ${failedUploads.map(f => f.file).join(', ')}`;
          return {
            success: allUploadedImages.length > 0,
            images: allUploadedImages,
            error: errorMessage,
            partialSuccess: allUploadedImages.length > 0
          };
        }

        return { success: true, images: allUploadedImages };

      } catch (error) {
        console.error('Error uploading files:', error);
        return { success: false, error: error.message };
      } finally {
        // Clear uploading flag
        this.isUploading = false;
      }
    },

    async uploadSingleFile(file) {
      console.log('Starting upload for file:', file.name, 'to device:', this.id);

      const formData = new FormData();
      formData.append('file', file);

      try {
        const response = await axios.post(`/device/image-upload/${this.id}`, formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
          timeout: 30000, // 30 second timeout per file
        });

        console.log('Upload response for', file.name, ':', response.data);

        if (response.data.success) {
          return {
            success: true,
            images: response.data.images || []
          };
        } else {
          throw new Error(response.data.error || 'Upload failed');
        }
      } catch (error) {
        console.error('Upload error for', file.name, ':', error);

        // Handle different types of errors
        if (error.code === 'ECONNABORTED') {
          throw new Error('Upload timeout - file may be too large');
        } else if (error.response && error.response.status === 504) {
          throw new Error('Server timeout - please try uploading fewer files at once');
        } else if (error.response && error.response.status >= 500) {
          throw new Error('Server error - please try again later');
        } else {
          throw error;
        }
      }
    },

    clearPendingFiles() {
      // Don't clear pending files if we're in the middle of uploading
      if (this.isUploading) {
        console.log('Upload in progress, not clearing pending files');
        return;
      }

      console.log('Clearing pending files');

      // Clean up preview URLs
      Object.values(this.filePreviewUrls).forEach(url => {
        URL.revokeObjectURL(url);
      });
      this.filePreviewUrls = {};

      // Clear pending files
      this.pendingFiles = [];

      // Clear FileUploader if it exists
      if (this.$refs.fileUploader) {
        this.$refs.fileUploader.clearFiles();
      }
    },

    // Method to get pending files (for parent component)
    getPendingFiles() {
      return this.pendingFiles;
    },

    // Remove a specific file from pending files without clearing all
    removePendingFile(fileToRemove) {
      const index = this.pendingFiles.findIndex(file => file.name === fileToRemove.name && file.size === fileToRemove.size);
      if (index > -1) {
        this.pendingFiles.splice(index, 1);

        // Clean up preview URL for this specific file
        if (this.filePreviewUrls[fileToRemove.name]) {
          URL.revokeObjectURL(this.filePreviewUrls[fileToRemove.name]);
          delete this.filePreviewUrls[fileToRemove.name];
        }

        // Remove file from FileUploader component
        if (this.$refs.fileUploader) {
          this.$refs.fileUploader.removeFile(fileToRemove);
        }
      }
    }
  },

  beforeDestroy() {
    // Clean up any remaining preview URLs
    Object.values(this.filePreviewUrls).forEach(url => {
      URL.revokeObjectURL(url);
    });
  }
}
</script>
<style scoped lang="scss">
.device-photo-layout {
  margin-bottom: 1rem;
}

.device-photos {
  min-height: 120px;
  border: 2px dashed #ddd;
  border-radius: 8px;
  padding: 10px;
  background-color: #f9f9f9;
}

.pending-image-preview {
  position: relative;
  margin-right: 10px;
  margin-bottom: 10px;
}

.pending-image {
  width: 120px;
  height: 120px;
  object-fit: cover;
  border-radius: 4px;
  border: 2px solid #007bff;
}

.remove-pending-btn {
  position: absolute;
  top: -5px;
  right: -5px;
  background: #dc3545;
  color: white;
  border: none;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  font-size: 12px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;

  &:hover {
    background: #c82333;
  }

  &:disabled {
    background: #6c757d;
    cursor: not-allowed;
  }
}

.pending-files-info {
  margin-top: 0.5rem;
  text-align: center;
}
</style>
