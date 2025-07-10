<template>
	<div class="position-relative">
		<b-img-lazy :src="imageUrl" class="align-self-start clickme" @click.native="zoom" />
		<b-btn variant="none" class="remove align-content-center" @click="confirm" v-if="!disabled">
			╳
		</b-btn>
		<ConfirmModal @confirm="remove" ref="confirm" />
		<DeviceImageModal :image="image" ref="modal" />
	</div>
</template>
<script>
import ConfirmModal from "./ConfirmModal";
import DeviceImageModal from "./DeviceImageModal";
export default {
	components: { DeviceImageModal, ConfirmModal },
	props: {
		image: {
			type: Object,
			required: true,
		},
		disabled: {
			type: Boolean,
			required: false,
			default: false,
		},
	},
	computed: {
		imageUrl() {
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

			// Default fallback
			return "/images/placeholder.png";
		},
	},
	methods: {
		remove() {
			this.$emit("remove");
		},
		confirm() {
			this.$refs.confirm.show();
		},
		zoom() {
			this.$refs.modal.show();
		},
	},
};
</script>
<style scoped lang="scss">
.remove {
	position: absolute;
	right: 6px;
	top: 1px;
	border-radius: 50%;
	background-color: white;
	font-size: 16px !important;
	min-width: unset !important;
	padding: 5px;
	font-weight: bolder;
	border: 2px solid grey;
	width: 30px;
	height: 30px;
}
</style>