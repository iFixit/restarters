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

				// Use the url field if available (from server response)
				if (this.image.url) {
					return this.image.url;
				}

				// Fallback: construct URL from path
				if (this.image.path) {
					// Check if we have a full URL (S3) or just a path (local)
					if (this.image.path.startsWith("http")) {
						return this.image.path;
					}
					return `/uploads/${this.image.path}`;
				}

				// Use string value directly if image is just a string
				if (typeof this.image === 'string') {
					if (this.image.startsWith("http")) {
						return this.image;
					}
					return `/uploads/${this.image}`;
				}
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
