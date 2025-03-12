<template>
  <div class="mapbox-autocomplete">
    <input
      :id="id"
      type="text"
      :class="classname"
      :placeholder="placeholder"
      v-model="query"
      @input="onInput"
      @focus="onFocus"
      @blur="onBlur"
      @keydown.down="onKeyDown"
      @keydown.up="onKeyUp"
      @keydown.enter="onKeyEnter"
      :aria-describedby="ariaDescribedby"
      ref="autocompleteInput"
    />
    <div class="mapbox-autocomplete-results" v-show="showResults && results.length > 0">
      <div 
        v-for="(result, index) in results" 
        :key="index" 
        class="mapbox-autocomplete-result"
        @click="selectResult(result)"
        :class="{ 'active': index === activeIndex }"
      >
        {{ result.place_name }}
      </div>
    </div>
  </div>
</template>

<script>
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
      query: '',
      results: [],
      showResults: false,
      activeIndex: -1,
      debounceTimeout: null,
      selectedPlace: null,
      userLocation: null,
      accessToken: process.env.MIX_MAPBOX_ACCESS_TOKEN || ''
    }
  },
  mounted() {
    // Add event listener to document to close dropdown when clicking outside
    document.addEventListener('click', this.handleClickOutside);
    
    // Get user's location for better results
    this.getUserLocation();
    
    // Log to help with debugging
    console.log('MapboxAutocomplete component mounted');
    
    if (!this.accessToken) {
      console.error('Mapbox access token is not configured. Please set MIX_MAPBOX_ACCESS_TOKEN in your .env file.');
    }
  },
  beforeDestroy() {
    // Remove event listener when component is destroyed
    document.removeEventListener('click', this.handleClickOutside);
    
    // Clear any pending timeouts
    if (this.debounceTimeout) {
      clearTimeout(this.debounceTimeout);
    }
  },
  methods: {
    getUserLocation() {
      // Try to get user's location for better results
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          (position) => {
            this.userLocation = {
              lat: position.coords.latitude,
              lon: position.coords.longitude
            };
            console.log('User location obtained:', this.userLocation);
          },
          (error) => {
            console.log('Geolocation error:', error);
            // Fallback to IP-based geolocation
            this.getIPBasedLocation();
          }
        );
      } else {
        // Fallback to IP-based geolocation
        this.getIPBasedLocation();
      }
    },
    getIPBasedLocation() {
      // Use a free IP geolocation service
      fetch('https://ipapi.co/json/')
        .then(response => response.json())
        .then(data => {
          if (data.latitude && data.longitude) {
            this.userLocation = {
              lat: data.latitude,
              lon: data.longitude
            };
            console.log('IP-based location obtained:', this.userLocation);
          }
        })
        .catch(error => {
          console.error('IP geolocation error:', error);
        });
    },
    update(value) {
      this.query = value || '';
    },
    onInput() {
      clearTimeout(this.debounceTimeout);
      this.debounceTimeout = setTimeout(() => {
        this.fetchResults();
      }, 300);
      
      // Emit change event to notify parent component
      this.$emit('change');
    },
    onFocus() {
      // If we have a query and no results, fetch results
      if (this.query && this.query.length >= 3 && this.results.length === 0) {
        this.fetchResults();
      } else if (this.results.length > 0) {
        this.showResults = true;
      }
    },
    onBlur() {
      // Delay hiding results to allow click events to register
      setTimeout(() => {
        this.showResults = false;
      }, 200);
    },
    onKeyDown() {
      if (this.results.length > 0) {
        this.activeIndex = Math.min(this.activeIndex + 1, this.results.length - 1);
        this.showResults = true;
      }
    },
    onKeyUp() {
      if (this.results.length > 0) {
        this.activeIndex = Math.max(this.activeIndex - 1, -1);
        this.showResults = true;
      }
    },
    onKeyEnter() {
      if (this.activeIndex >= 0 && this.results[this.activeIndex]) {
        this.selectResult(this.results[this.activeIndex]);
      }
    },
    handleClickOutside(event) {
      // Close dropdown when clicking outside the component
      if (this.$el && !this.$el.contains(event.target)) {
        this.showResults = false;
      }
    },
    async fetchResults() {
      if (!this.query || this.query.length < 3) {
        this.results = [];
        this.showResults = false;
        console.log('Query too short or empty, not fetching results');
        return;
      }

      if (!this.accessToken) {
        console.error('Cannot fetch results: Mapbox access token is not configured');
        return;
      }

      try {
        console.log('Fetching results for:', this.query);
        
        // Build URL with location bias if available
        let url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(this.query)}.json?access_token=${this.accessToken}&limit=10&language=${this.lang}`;
        
        // Add location bias if we have user location
        if (this.userLocation) {
          url += `&proximity=${this.userLocation.lon},${this.userLocation.lat}`;
        }
        
        // Add types filter if specified
        if (this.types) {
          url += `&types=${this.types}`;
        }
        
        console.log('Fetching from URL:', url.replace(this.accessToken, 'REDACTED'));
        
        const response = await fetch(url);
        const data = await response.json();
        
        console.log('Mapbox API response:', data);
        
        this.results = data.features || [];
        this.showResults = this.results.length > 0;
        this.activeIndex = -1;
        
        console.log('Results count:', this.results.length);
        console.log('showResults:', this.showResults);
        
        // Force update to ensure the dropdown is shown
        this.$forceUpdate();
      } catch (error) {
        console.error('Error fetching address suggestions:', error);
        this.results = [];
        this.showResults = false;
      }
    },
    selectResult(result) {
      console.log('Selected result:', result);
      
      // Format display name from the place_name property
      const displayName = result.place_name;
      
      this.query = displayName;
      this.showResults = false;
      
      // Extract address components from the result
      const addressComponents = this.extractAddressComponents(result);
      
      // Ensure we have a valid location string that will pass validation
      let validLocation = false;
      
      // Check if we have the minimum required data for geocoding
      const hasStreet = !!addressComponents.street;
      const hasCity = !!addressComponents.city;
      const hasCountry = !!addressComponents.country;
      
      // For geocoding to work, we need at least a city and country
      if (hasCity && hasCountry) {
        validLocation = true;
      }
      
      // For API validation, we need at least a street and city
      if (hasStreet && hasCity) {
        validLocation = true;
      }
      
      console.log('Location is valid for API:', validLocation);
      console.log('Has street:', hasStreet, 'Has city:', hasCity, 'Has country:', hasCountry);
      
      // Create a geocodable address string
      let geocodableAddress = displayName;
      
      // If the address doesn't have enough information, try to construct a better one
      if (!validLocation) {
        console.warn('Selected location may not be valid for geocoding, attempting to improve it');
        
        // Try to construct a better address for geocoding
        const parts = [];
        
        if (addressComponents.street) parts.push(addressComponents.street);
        if (addressComponents.city) parts.push(addressComponents.city);
        if (addressComponents.state) parts.push(addressComponents.state);
        if (addressComponents.country) parts.push(addressComponents.country);
        
        if (parts.length >= 2) {
          geocodableAddress = parts.join(', ');
          console.log('Constructed geocodable address:', geocodableAddress);
          validLocation = true;
        }
      }
      
      const addressData = {
        latitude: result.center[1],  // Mapbox returns [longitude, latitude]
        longitude: result.center[0],
        formattedAddress: displayName,
        geocodableAddress: geocodableAddress,
        postcode: addressComponents.postcode,
        housenumber: addressComponents.housenumber,
        street: addressComponents.street,
        city: addressComponents.city,
        state: addressComponents.state,
        country: addressComponents.country,
        name: result.text,
        type: result.place_type[0],
        validLocation: validLocation
      };
      
      this.selectedPlace = {
        formatted_address: displayName,
        geocodable_address: geocodableAddress,
        properties: addressComponents,
        validLocation: validLocation
      };
      
      // Emit the placechanged event with the selected place data
      this.$emit('placechanged', addressData, this.selectedPlace);
    },
    extractAddressComponents(result) {
      const components = {
        housenumber: '',
        street: '',
        city: '',
        state: '',
        country: '',
        postcode: ''
      };
      
      // Extract the main text as the street or POI name
      components.street = result.text;
      
      // Extract components from context
      if (result.context) {
        result.context.forEach(context => {
          const id = context.id.split('.')[0];
          
          switch (id) {
            case 'postcode':
              components.postcode = context.text;
              break;
            case 'place':
              components.city = context.text;
              break;
            case 'region':
              components.state = context.text;
              break;
            case 'country':
              components.country = context.text;
              break;
          }
        });
      }
      
      // Try to extract house number from the address
      if (result.address) {
        components.housenumber = result.address;
      } else {
        // Try to extract house number from the beginning of the street name
        const match = components.street.match(/^(\d+)\s+(.+)/);
        if (match) {
          components.housenumber = match[1];
          components.street = match[2];
        }
      }
      
      return components;
    }
  }
}
</script>

<style scoped>
.mapbox-autocomplete {
  position: relative;
  width: 100%;
}

.mapbox-autocomplete-results {
  position: absolute;
  width: 100%;
  max-height: 300px;
  overflow-y: auto;
  background: white;
  border: 1px solid #ccc;
  border-top: none;
  z-index: 1000;
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
  margin-top: -1px; /* Connect to input field */
}

.mapbox-autocomplete-result {
  padding: 10px 12px;
  cursor: pointer;
  border-bottom: 1px solid #eee;
  white-space: normal;
  line-height: 1.4;
  font-size: 14px;
}

.mapbox-autocomplete-result:last-child {
  border-bottom: none;
}

.mapbox-autocomplete-result:hover,
.mapbox-autocomplete-result.active {
  background-color: #f5f5f5;
}
</style> 