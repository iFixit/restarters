<template>
  <b-form-group>
    <label for="group_website">{{ __('groups.groups_website') }}:</label>
    <b-input-group>
      <b-input-group-prepend is-text>https://</b-input-group-prepend>
      <b-input type="text" id="group_website" name="website" v-model="websiteInput" :class="{ hasError: hasError }"/>
    </b-input-group>
    <small>{{ __('groups.groups_website_small') }}</small>
  </b-form-group>
</template>
<script>
export default {
  props: {
    website: {
      type: String,
      required: false,
      default: null
    },
    hasError: {
      type: Boolean,
      required: false,
      default: false
    }
  },
  data () {
    return {
      websiteInput: ''
    }
  },
  mounted () {
    // Remove protocol for display
    if (this.website && this.website.match(/^https?:\/\//)) {
      this.websiteInput = this.website.replace(/^https?:\/\//, '');
    } else {
      this.websiteInput = this.website || '';
    }
  },
  watch: {
    websiteInput(newVal) {
      let url = newVal.trim();
      if (url && !/^https?:\/\//.test(url)) {
        url = 'https://' + url;
      }
      this.$emit('update:website', url)
    },
    website(newVal) {
      if (newVal && newVal.match(/^https?:\/\//)) {
        this.websiteInput = newVal.replace(/^https?:\/\//, '');
      } else {
        this.websiteInput = newVal || '';
      }
    }
  }
}
</script>