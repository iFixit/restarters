<template>
  <div>
    <div class="d-flex justify-content-between">
      <div class="d-flex">
        <b-img-lazy :src="groupImage" class="profile mr-2" @error.native="brokenProfileImage" />
        <div class="d-flex flex-column justify-content-center">
          <a :href="'/group/view/' + group.idgroups">{{ group.name }}</a>
          <GroupArchivedBadge :idgroups="group.idgroups" />
        </div>
      </div>
      <div>
        <b-btn variant="primary" :href="'/group/join/' + group.idgroups" v-if="!group.ingroup">
          {{ __('groups.join_group_button') }}
        </b-btn>
      </div>
    </div>
    <hr />
  </div>
</template>
<script>
import { DEFAULT_PROFILE } from '../constants'
import GroupArchivedBadge from "./GroupArchivedBadge.vue";

export default {
  components: { GroupArchivedBadge },
  props: {
    group: {
      type: Object,
      required: true
    }
  },
  computed: {
    defaultProfile() {
      return DEFAULT_PROFILE
    },
    groupImage() {
      if (this.group && this.group.group_image && this.group.group_image.image) {
        const imagePath = this.group.group_image.image.path;

        // Use the global helper function to get the correct URL
        if (window.getUploadUrl) {
          return window.getUploadUrl(imagePath, 'mid');
        }

        // Fallback for older code - should not be needed
        return `/uploads/mid_${imagePath}`;
      }

      return DEFAULT_PROFILE;
    },
  },
  methods: {
    brokenProfileImage(event) {
      event.target.src = DEFAULT_PROFILE
    },
  }
}
</script>
<style scoped lang="scss">
@import 'resources/global/css/_variables';
@import '~bootstrap/scss/functions';
@import '~bootstrap/scss/variables';
@import '~bootstrap/scss/mixins/_breakpoints';

.profile {
  border: 1px solid black;
  width: 48px;
  height: 48px;
}

a {
  text-decoration: underline;
  color: #222;
}

hr {
  border-top: 1px solid black;
}
</style>