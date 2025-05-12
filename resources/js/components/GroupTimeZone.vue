<template>
  <div>
    <label for="postcode">{{ __('groups.timezone') }}:</label>
    <vue-typeahead-bootstrap
        v-model="currentValue"
        :maxMatches="3"
        :data="timezones"
        :minMatchingChars="1"
        inputClass="form-control field timezone"
        :class="{
'invalid': !valid
        }"
    />
    <b-form-checkbox id="group_override_timezone" name="override_timezone" v-model="overrideTimezone" type="checkbox">
      {{ __('groups.override_timezone') }}
    </b-form-checkbox>
    <small class="form-text text-muted">
      {{ __('groups.timezone_placeholder') }}
    </small>
    <input type="hidden" name="timezone" :value="currentValue" />
  </div>
</template>
<script>
import VueTypeaheadBootstrap from 'vue-typeahead-bootstrap';
import axios from 'axios'

export default {
  props: {
    value: {
      type: String,
      required: false,
      default: null
    },
    overrideTimezone: {
      type: Boolean,
      required: false,
      default: false
    },
  },
  components: { VueTypeaheadBootstrap },
  data () {
    return {
      currentValue: null,
      timezones: [],
      overrideTimezoneLocal: this.overrideTimezone,
    }
  },
  computed: {
    valid() {
      return !this.currentValue || !this.timezones.length || this.timezones.includes(this.currentValue)
    }
  },
  watch: {
    value(newValue) {
      this.currentValue = newValue;
    },
    overrideTimezoneLocal(newValue) {
      this.$emit('update:overrideTimezone', newValue)
    },
    overrideTimezone(newValue) {
      this.overrideTimezoneLocal = newValue;
    },
    valid(newValue) {
      this.$emit('update:valid', newValue)
    },
    currentValue(newValue) {
      this.$emit('update:value', newValue)
    }
  },
  async mounted() {
    this.currentValue = this.value
    this.overrideTimezoneLocal = this.overrideTimezone

    const ret = await axios.get('/api/timezones')

    if (ret.status && ret.status === 200 && ret.data) {
      this.timezones = ret.data.map(t => t.name)
    }
  }
}
</script>
<style scoped lang="scss">
@import 'resources/global/css/_variables';

::v-deep .invalid input {
  border: 2px solid $brand-danger;
}
</style>