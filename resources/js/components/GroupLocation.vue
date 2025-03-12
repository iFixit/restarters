<template>
  <div>
    <b-form-group>
      <label :for="$id('address-autocomplete')">{{ $translate('groups.location') }}:</label>
      <location-autocomplete
          :id="$id('address-autocomplete')"
          name="location"
          classname="form-control group-location"
          :placeholder="$translate('groups.groups_location_placeholder')"
          @placechanged="placeChanged"
          @change="resetValues"
          :aria-describedby="'locationHelpBlock'"
          ref="autocomplete"
          :class="{ hasError: hasError, 'm-0': true }"
      />
      <small id="locationHelpBlock">
      <span class="form-text text-danger" v-if="hasError">
        {{ $translate('groups.geocode_failed') }}
      </span>
        <span v-else>
      {{ $translate('groups.groups_location_small') }}
      </span>
      </small>
    </b-form-group>
    <b-form-group>
      <label for="group_postcode">{{ $translate('groups.postcode') }}:</label>
      <b-input id="group_postcode" name="postcode" v-model="currentPostcode" :class="{ hasError: hasError }" :readonly="!canEditPostcode" />
      <small>{{ $translate('groups.groups_postcode_small') }}</small>
    </b-form-group>
  </div>
</template>
<script>
import Vue from 'vue'
import LocationAutocomplete from './LocationAutocomplete'
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
    hasError: {
      type: Boolean,
      required: false,
      default: false
    },
    allGroups: {
      type: Array,
      required: false,
      default: null
    },
    canEditPostcode: {
      type: Boolean,
      required: false,
      default: false
    }
  },
  components: {
    LocationAutocomplete
  },
  data () {
    return {
      currentValue: null,
      currentPostcode: null,
    }
  },
  mounted() {
    console.log('GroupLocation component mounted');
    console.log('Initial value:', this.value);
    console.log('Initial lat:', this.lat);
    console.log('Initial lng:', this.lng);
    
    // Initialize values
    this.currentValue = this.value;
    this.currentPostcode = this.postcode;
    
    // Need to wait for the autocomplete component to be mounted
    this.$nextTick(() => {
      if (this.$refs.autocomplete) {
        this.$refs.autocomplete.update(this.currentValue);
      }
    });
  },
  watch: {
    value: {
      handler: function (val) {
        console.log('Value changed:', val);
        this.currentValue = val;
        if (this.$refs.autocomplete) {
          this.$refs.autocomplete.update(this.currentValue);
        }
      },
      immediate: true
    },
    postcode: {
      handler: function (val) {
        console.log('Postcode changed:', val);
        this.currentPostcode = val;
      },
      immediate: true
    },
    currentPostcode: {
      handler: function (val) {
        this.$emit('update:postcode', val);
      }
    }
  },
  methods: {
    async placeChanged(addressData, placeResultData) {
      console.log('Place changed:', addressData, placeResultData);
      
      // nextTick which means the change event will get processed before we emit our new values.
      await this.$nextTick();
      
      // Use the geocodable address for the API if available, otherwise use the formatted address
      this.currentValue = addressData.geocodableAddress || placeResultData.formatted_address;
      
      console.log('Using address for API:', this.currentValue);
      
      this.$emit('update:value', this.currentValue);
      this.$emit('update:lat', addressData.latitude);
      this.$emit('update:lng', addressData.longitude);
      
      // Check if the location is valid for the API
      if (!addressData.validLocation) {
        console.warn('Selected location may not be valid for the API. It needs at least a street and city.');
        // We'll still allow it, but log a warning
      }
      
      // If we have a postcode in the address data, use it
      if (addressData.postcode) {
        this.currentPostcode = addressData.postcode;
        this.$emit('update:postcode', addressData.postcode);
      } else if (addressData.city && 
                (addressData.country === 'United States of America' || 
                 addressData.country === 'USA')) {
        // For US addresses without a postcode, we could potentially fetch it
        // but for now we'll just leave it blank for the user to fill in
        console.log('US address without postcode, user will need to provide it');
      }
      
      // Log the type of location selected to help with debugging
      if (addressData.type) {
        console.log('Selected location type:', addressData.type);
      }
      
      // Ensure we have the minimum required data for the API
      // The backend validation requires at least a street and city
      if (!addressData.street || !addressData.city) {
        console.error('Missing required location data for API validation');
        // We'll still allow it, but log an error
      }
    },
    resetValues() {
      console.log('Reset values called');
      
      // This means that if the input changes, we will assume it's invalid unless we subsequently (because of
      // the nextTick above) get a valid placeChanged event.
      this.$emit('update:value', null);
      this.$emit('update:lat', null);
      this.$emit('update:lng', null);
    }
  }
}
</script>