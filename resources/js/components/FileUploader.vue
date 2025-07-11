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
        createImageThumbnails: false, // Disable thumbnails
        resizeWidth: 800,
        resizeHeight: 800,
        thumbnailMethod: 'contain',
        previewsContainer: false, // Disable previews entirely
        dictRemoveFile: null,
        acceptedFiles: ".jpeg,.jpg,.png,.gif",
        manuallyAddFile: true,
        autoProcessQueue: false, // Don't auto-upload
        previewTemplate: '<div style="display:none;"></div>' // Hide any preview
      }
    }
  },
  methods: {
    fileAdded(file) {
      console.log('File added:', file.name, file.size, file.type);

      // Add to our pending files list
      this.pendingFiles.push(file);

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

        // Clear files from dropzone properly
        this.pendingFiles.forEach(file => {
          if (this.$refs.dropzone && this.$refs.dropzone.removeFile) {
            this.$refs.dropzone.removeFile(file);
          }
        });

        // Reset dropzone state
        this.resetDropzone();

        this.pendingFiles = [];
        this.$emit('files-changed', this.pendingFiles);
      }
    },

    // Method to reset dropzone to clean state
    resetDropzone() {
      if (this.$refs.dropzone && this.$refs.dropzone.dropzone) {
        const dropzone = this.$refs.dropzone.dropzone;

        // Clear any queued files
        dropzone.removeAllFiles(true);

        // Reset internal state
        dropzone.files = [];
        dropzone.filesProcessing = [];
        dropzone.filesQueue = [];

        console.log('Dropzone reset to clean state');
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
</style>