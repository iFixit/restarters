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
          :max-files="maxFiles - images.length" ref="fileUploader" />
        <DeviceImage v-for="image in images" :key="'img-' + image.path" :image="image" @remove="$emit('remove', image)"
          :disabled="disabled" />
        <div v-for="(file, index) in pendingFiles" :key="'pending-' + index" class="pending-image-preview">
          <img :src="getFilePreviewUrl(file)" class="pending-image" />
          <button type="button" @click="removePendingFile(index)" class="remove-pending-btn"
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
      filePreviewUrls: {} // Cache for file preview URLs
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

    removePendingFile(index) {
      const file = this.pendingFiles[index];

      // Clean up preview URL
      if (this.filePreviewUrls[file.name]) {
        URL.revokeObjectURL(this.filePreviewUrls[file.name]);
        delete this.filePreviewUrls[file.name];
      }

      // Remove from FileUploader
      if (this.$refs.fileUploader) {
        this.$refs.fileUploader.removeFile(file);
      }
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

      try {
        const allUploadedImages = [];

        // Create a copy of pending files to avoid modifying array while iterating
        const filesToUpload = [...this.pendingFiles];

        // Upload files one by one sequentially to avoid server conflicts
        for (const file of filesToUpload) {
          console.log('Uploading file:', file.name);

          const result = await this.uploadSingleFile(file);

          if (result.success && result.images) {
            // Collect all uploaded images
            allUploadedImages.push(...result.images);
            console.log('Successfully uploaded:', file.name, result.images);
          }
        }

        // Clear all pending files after successful upload
        this.clearPendingFiles();

        return { success: true, images: allUploadedImages };

      } catch (error) {
        console.error('Error uploading files:', error);
        return { success: false, error: error.message };
      }
    },

    async uploadSingleFile(file) {
      console.log('Starting upload for file:', file.name, 'to device:', this.id);

      const formData = new FormData();
      formData.append('file', file);

      try {
        const response = await axios.post(`/party/device/${this.id}/image-upload`, formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
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
        throw error;
      }
    },

    clearPendingFiles() {
      // Clean up all preview URLs
      Object.values(this.filePreviewUrls).forEach(url => {
        URL.revokeObjectURL(url);
      });
      this.filePreviewUrls = {};

      // Clear pending files
      this.pendingFiles = [];

      // Clear FileUploader
      if (this.$refs.fileUploader) {
        this.$refs.fileUploader.clearFiles();
      }
    },

    // Method to get pending files (for parent component)
    getPendingFiles() {
      return this.pendingFiles;
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
