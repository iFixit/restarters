<template>
  <div>
    <b-img-lazy :src="thumbnailUrl" thumbnail class="mr-2 mb-2 size d-inline clickme" @error.native="brokenImage" @click.native="zoom" />
    <EventImageModal :image="image" ref="modal" />
  </div>
</template>
<script>
import CollapsibleSection from "./CollapsibleSection";
import { PLACEHOLDER } from "../constants";
import EventImageModal from "./EventImageModal";

export default {
	components: { EventImageModal },
	props: {
		image: {
			type: Object,
			required: true,
		},
	},
	computed: {
		thumbnailUrl() {
			// Check if we have a full URL (S3) or just a path (local)
			if (this.image.path.startsWith("http")) {
				// For S3, we might have a direct URL to the thumbnail
				return this.image.thumbnail_path || this.image.path;
			}
			return `/uploads/thumbnail_${this.image.path}`;
		},
	},
	methods: {
		brokenImage(e) {
			e.target.src = "/images/placeholder.png";
		},
		zoom() {
			this.$refs.modal.show();
		},
	},
};
</script>
<style scoped lang="scss">
.size {
  width: 80px;
  height: 80px;
}

.clickme {
  cursor: pointer;
}
</style>