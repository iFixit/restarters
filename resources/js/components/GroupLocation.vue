<template>
  <div>
    <b-form-group>
      <label :for="$id('address-autocomplete')">{{ __('groups.location') }}:</label>
      <vue-google-autocomplete
          :id="$id('address-autocomplete')"
          name="location"
          classname="form-control group-location"
          :placeholder="__('groups.groups_location_placeholder')"
          @placechanged="placeChanged"
          @change="resetValues"
          aria-describedby="locationHelpBlock"
          types="geocode"
          ref="autocomplete"
          :class="{ hasError: hasError, 'm-0': true }"
      />
      <small id="locationHelpBlock">
      <span class="form-text text-danger" v-if="hasError">
        {{ __('groups.geocode_failed') }}
      </span>
        <span v-else>
      {{ __('groups.groups_location_small') }}
      </span>
      </small>
    </b-form-group>
    <b-form-group>
      <label for="group_postcode">{{ __('groups.postcode') }}:</label>
      <b-input id="group_postcode" name="postcode" v-model="currentPostcode" :class="{ hasError: hasError }" :readonly="!overridePostcode" />
      <b-form-checkbox id="group_override_postcode" name="override_postcode" v-model="overridePostcode" type="checkbox">
        {{ __('groups.override_postcode') }}
      </b-form-checkbox>
      <small>{{ __('groups.groups_postcode_small') }}</small>
    </b-form-group>
  </div>
</template>
<script>
import Vue from 'vue'
import VueGoogleAutocomplete from 'vue-google-autocomplete'
import UniqueId from 'vue-unique-id';

Vue.use(UniqueId);

export default {
  props: {
    value: {
      type: String,
      required: false,
      default: null
    },
    lat: {
      type: Number,
      required: false,
      default: null
    },
    lng: {
      type: Number,
      required: false,
      default: null
    },
    postcode: {
      type: String,
      required: false,
      default: null
    },
    area: {
      type: String,
      required: false,
      default: null
    },
    hasError: {
      type: Boolean,
      required: false,
      default: false
    },
    selectedGroup: {
      type: Number,
      required: false,
      default: null
    },
    online: {
      type: Boolean,
      required: false,
      default: false
    },
    overridePostcode: {
      type: Boolean,
      required: false,
      default: false
    },
  },
  components: {
    VueGoogleAutocomplete
  },
  data () {
    return {
      currentValue: null,
      location: null,
      currentPostcode: null,
      timer: null,
    }
  },
  mounted() {
    this.currentValue = this.value
    this.currentPostcode = this.postcode
    this.$refs.autocomplete.update(this.currentValue)
  },
  watch: {
    currentPostcode(newVal) {
      this.$emit('update:postcode', newVal)
    },
    overridePostcode(newVal) {
      this.$emit('update:overridePostcode', newVal)
    },
  },
  methods: {
    async placeChanged(addressData, placeResultData) {
      // nextTick which means the change event will get processed before we emit our new values.
      await this.$nextTick()
      this.currentValue = placeResultData.formatted_address
      this.$emit('update:value', this.currentValue)
      this.$emit('update:lat', addressData.latitude)
      this.$emit('update:lng', addressData.longitude)
      // Set postcode from autocompletion only if overridePostcode is false
      if (!this.overridePostcode && addressData.postal_code) {
        this.currentPostcode = addressData.postal_code
      }
      // Emit location-changed for timezone lookup
      this.$emit('location-changed', { lat: addressData.latitude, lng: addressData.longitude })
    },
    resetValues() {
      // This means that if the input changes, we will assume it's invalid unless we subsequently (because of
      // the nextTick above) get a valid placeChanged event.
      this.$emit('update:value', null)
      this.$emit('update:lat', null)
      this.$emit('update:lng', null)
    }
  }
}
</script>