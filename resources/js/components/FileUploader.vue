<template>
  <div>
    <vue-dropzone ref="dropzone" id="dropzone" :options="dropzoneOptions" @vdropzone-sending="sendingEvent"
      @vdropzone-success-multiple="successMultiple" @vdropzone-success="successSingle" @vdropzone-error="errorEvent"
      class="ourdropzone" useCustomSlot>
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
  components: {
    vueDropzone: vue2Dropzone
  },
  computed: {
    dropzoneOptions() {
      return {
        url: this.url,
        paramName: 'file',
        uploadMultiple: true,
        createImageThumbnails: true,
        parallelUploads: 1, // Reduced from 100 to avoid overwhelming the server
        addRemoveLinks: false,
        thumbnailWidth: 120,
        thumbnailHeight: 120,
        maxFiles: this.maxFiles,
        resizeWidth: 800,
        resizeHeight: 800,
        thumbnailMethod: 'contain',
        previewsContainer: this.previewsContainer,
        dictRemoveFile: null,
        acceptedFiles: ".jpeg,.jpg,.png,.gif",
        timeout: 30000, // 30 second timeout
        previewTemplate:
          '<div>' +
          ' <div class="dz-preview dz-file-preview">' +
          '   <div class="dz-image"><img data-dz-thumbnail /></div>' +
          '   <div class="dz-progress">' +
          '     <span data-dz-uploadprogress="" class="dz-upload"></span>' +
          '   </div> ' +
          '   <div class="dz-error-message">' +
          '   <span data-dz-errormessage=""></span>' +
          ' </div> ' +
          '</div>'
      }
    }
  },
  methods: {
    sendingEvent(file, xhr, formData) {
      // Add the CSRF token
      formData.append('_token', this.$store.getters['auth/CSRF']);
      console.log('Sending file:', file.name, 'to URL:', this.url);
    },

    successMultiple(files, response) {
      console.log("Multiple upload success:", files, response);
      this.handleSuccess(files, response);
    },

    successSingle(file, response) {
      console.log("Single upload success:", file, response);
      this.handleSuccess([file], response);
    },

    handleSuccess(files, response) {
      try {
        // Parse response if it's a string
        let parsedResponse = response;
        if (typeof response === 'string') {
          try {
            parsedResponse = JSON.parse(response);
          } catch (e) {
            console.error('Failed to parse response as JSON:', response);
            // If response is just a success message, assume upload worked
            if (response.includes('success')) {
              parsedResponse = { success: true, images: [] };
            } else {
              throw new Error('Invalid response format');
            }
          }
        }

        console.log("Parsed response:", parsedResponse);

        // Handle different response structures
        let images = [];
        if (parsedResponse.images && Array.isArray(parsedResponse.images)) {
          images = parsedResponse.images;
        } else if (parsedResponse.success && parsedResponse.images) {
          images = parsedResponse.images;
        } else {
          console.warn('No images found in response, assuming upload succeeded');
          images = [];
        }

        console.log("Extracted images:", images);

        // Emit the uploaded event with the images
        this.$emit('uploaded', images);

        // Remove the preview files - the parent component will handle displaying them
        files.forEach(file => {
          if (this.$refs.dropzone && this.$refs.dropzone.removeFile) {
            this.$refs.dropzone.removeFile(file);
          }
        });

      } catch (error) {
        console.error('Error handling upload success:', error);
        this.handleError(files, error.message);
      }
    },

    errorEvent(file, errorMessage, xhr) {
      console.error('Upload error:', file, errorMessage, xhr);
      this.handleError([file], errorMessage);
    },

    handleError(files, errorMessage) {
      // Show error message to user
      console.error('Upload failed:', errorMessage);

      // You could emit an error event here if needed
      this.$emit('upload-error', {
        files: files,
        error: errorMessage
      });

      // Remove failed files from dropzone
      files.forEach(file => {
        if (this.$refs.dropzone && this.$refs.dropzone.removeFile) {
          this.$refs.dropzone.removeFile(file);
        }
      });
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