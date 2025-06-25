<template>
  <b-modal ref="modal" :title="__('events.image_photo')" size="lg" hide-footer>
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
			// Check if we have a full URL (S3) or just a path (local)
			if (this.image.path.startsWith("http")) {
				return this.image.path;
			}
			return `/uploads/${this.image.path}`;
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
