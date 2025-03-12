<template>
  <component 
    :is="autocompleteComponent" 
    :id="id"
    :classname="classname"
    :placeholder="placeholder"
    :aria-describedby="ariaDescribedby"
    :types="types"
    :lang="lang"
    @placechanged="onPlaceChanged"
    @change="onChange"
    ref="autocomplete"
  />
</template>

<script>
import OpenStreetMapAutocomplete from './OpenStreetMapAutocomplete.vue';
import MapboxAutocomplete from './MapboxAutocomplete.vue';

export default {
  props: {
    id: {
      type: String,
      required: true
    },
    classname: {
      type: String,
      default: 'form-control'
    },
    placeholder: {
      type: String,
      default: ''
    },
    ariaDescribedby: {
      type: String,
      default: ''
    },
    types: {
      type: String,
      default: ''
    },
    lang: {
      type: String,
      default: 'en'
    }
  },
  data() {
    return {
      mapService: process.env.MIX_MAP_SERVICE || 'openstreetmap'
    }
  },
  computed: {
    autocompleteComponent() {
      // Dynamically choose the component based on the configured map service
      switch (this.mapService.toLowerCase()) {
        case 'mapbox':
          return MapboxAutocomplete;
        case 'openstreetmap':
        default:
          return OpenStreetMapAutocomplete;
      }
    }
  },
  methods: {
    update(value) {
      if (this.$refs.autocomplete) {
        this.$refs.autocomplete.update(value);
      }
    },
    onPlaceChanged(addressData, placeResultData) {
      // Forward the event to the parent component
      this.$emit('placechanged', addressData, placeResultData);
    },
    onChange() {
      // Forward the event to the parent component
      this.$emit('change');
    }
  }
}
</script> 