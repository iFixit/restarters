<template>
  <b-modal ref="modal" :title="__('devices.image_photo')" size="lg" hide-footer>
    <div class="d-flex justify-content-center">
      <b-img-lazy class="w-100" :src="imageUrl" @error.native="brokenImage" />
    </div>
  </b-modal>
</template>
<script>
export default {
	props: {
		image: {
			type: Object,
			required: true,
		},
	},
	computed: {
		imageUrl() {
			if (this.image) {
				if (this.image instanceof File) {
					return URL.createObjectURL(this.image);
				}
				// Check if we have a full URL (S3) or just a path (local)
				if (this.image.startsWith("http")) {
					return this.image;
				}

				return `/uploads/${this.image}`;
			}
			return "/images/upload_ico_grey.svg";
		},
	},
	methods: {
		show() {
			this.$refs.modal.show();
		},
		brokenImage(e) {
			e.target.src = "/images/placeholder.png";
		},
	},
};
</script>
