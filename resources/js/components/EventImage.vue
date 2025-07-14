<template>
	<div>
		<b-img-lazy :src="thumbnailUrl" thumbnail class="mr-2 mb-2 size d-inline clickme" @error.native="brokenImage"
			@click.native="zoom" />
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
			if (this.image?.path) {
				const imagePath = this.image.path;

				if (window.getUploadUrl) {
					return window.getUploadUrl(imagePath, 'thumbnail');
				}

				return `/uploads/thumbnail_${imagePath}`;
			}


			return "/images/thumbnail_placeholder.png";
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