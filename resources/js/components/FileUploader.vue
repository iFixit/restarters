<template>
  <div>
    <vue-dropzone ref="dropzone" id="dropzone" :options="dropzoneOptions" @vdropzone-file-added="fileAdded"
      @vdropzone-error="errorEvent" class="ourdropzone" useCustomSlot>
      <b-img src="/images/upload_ico_grey.svg" />
      <div class="dz-message d-none" />
    </vue-dropzone>
  </div>
</template>
<script>
import vue2Dropzone from 'vue2-dropzone'

export default {
  props: {
    url: {
      type: String,
      required: true
    },
    previewsContainer: {
      type: String,
      required: true
    },
    maxFiles: {
      type: Number,
      required: false,
      default: 1
    },
  },
  data() {
    return {
      pendingFiles: []
    }
  },
  components: {
    vueDropzone: vue2Dropzone
  },
  computed: {
    dropzoneOptions() {
      return {
        url: "thisisrequired", // Required by dropzone but not used since we're not auto-uploading
        paramName: 'file',
        uploadMultiple: false,
        maxFiles: this.maxFiles,
        createImageThumbnails: true,
        resizeWidth: 800,
        resizeHeight: 800,
        thumbnailMethod: 'contain',
        previewsContainer: this.previewsContainer,
        dictRemoveFile: null,
        acceptedFiles: ".jpeg,.jpg,.png,.gif",
        manuallyAddFile: true,
        autoProcessQueue: false, // Don't auto-upload
        previewTemplate:
          '<div class="dz-preview dz-file-preview">' +
          '  <div class="dz-image"><img data-dz-thumbnail /></div>' +
          '  <div class="dz-progress" style="display: none;">' +
          '    <span data-dz-uploadprogress="" class="dz-upload"></span>' +
          '  </div>' +
          '  <div class="dz-error-message">' +
          '    <span data-dz-errormessage=""></span>' +
          '  </div>' +
          '  <div class="dz-remove" data-dz-remove></div>' +
          '</div>'
      }
    }
  },
  methods: {
    fileAdded(file) {
      console.log('File added:', file.name, file.size, file.type);

      // Add to our pending files list
      this.pendingFiles.push(file);

      // Create data URL for preview
      const reader = new FileReader();
      reader.onload = (e) => {
        // Update the thumbnail with the data URL
        const thumbnailImg = file.previewElement.querySelector('[data-dz-thumbnail]');
        if (thumbnailImg) {
          thumbnailImg.src = e.target.result;
        }
      };
      reader.readAsDataURL(file);

      // Add remove functionality
      const removeBtn = file.previewElement.querySelector('[data-dz-remove]');
      if (removeBtn) {
        removeBtn.addEventListener('click', () => {
          this.removeFile(file);
        });
      }

      // Emit the files to parent component
      this.$emit('files-changed', this.pendingFiles);
    },

    removeFile(file) {
      console.log('Removing file:', file.name);

      // Remove from pending files
      this.pendingFiles = this.pendingFiles.filter(f => f !== file);

      // Remove from dropzone
      if (this.$refs.dropzone && this.$refs.dropzone.removeFile) {
        this.$refs.dropzone.removeFile(file);
      }

      // Emit updated files list
      this.$emit('files-changed', this.pendingFiles);
    },

    errorEvent(file, errorMessage) {
      console.error('Dropzone error:', file, errorMessage);

      // Remove the file that caused the error
      this.removeFile(file);

      // Emit error event
      this.$emit('upload-error', {
        file: file,
        error: errorMessage
      });
    },

    // Method to get current files (for parent component)
    getPendingFiles() {
      return this.pendingFiles;
    },

    // Method to clear all files
    clearFiles() {
      // Only clear if not in the middle of processing
      if (this.pendingFiles.length > 0) {
        console.log('Clearing files from FileUploader');
        this.pendingFiles.forEach(file => {
          if (this.$refs.dropzone && this.$refs.dropzone.removeFile) {
            this.$refs.dropzone.removeFile(file);
          }
        });
        this.pendingFiles = [];
        this.$emit('files-changed', this.pendingFiles);
      }
    },

    // Method to prevent clearing files during external updates
    preserveFiles() {
      console.log('Preserving files in FileUploader:', this.pendingFiles.length);
      return this.pendingFiles.length;
    }
  }
}
</script>
<style lang="scss">
// Note that this style is explicitly not scoped so that it can override dropzone styles.
@import 'resources/global/css/_variables';

.ourdropzone {
  padding: 0;
  align-content: start;
  background-color: transparent !important;
  border: 0 !important;
  padding-left: 2px;
  min-height: unset;

  .dz-message {
    margin: 0 !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
  }

  img {
    width: 100px !important;
  }
}

.dz-preview {
  position: relative;
  margin-right: 0.5rem;
}

.dz-remove {
  position: absolute;
  top: -10px;
  right: -8px;
  font-size: 13px;
  z-index: 2;
  font-weight: 600;
  color: $brand;
  text-decoration: underline;
  cursor: pointer;

  &:hover {
    text-decoration: none;
  }

  &:before {
    content: "╳";
    position: relative;
    background-color: white;
    border-radius: 50%;
    padding: 3px;
  }
}
</style>