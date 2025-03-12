<template>
  <div class="osm-autocomplete">
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
    <div class="osm-autocomplete-results" v-show="showResults && results.length > 0">
      <div 
        v-for="(result, index) in results" 
        :key="index" 
        class="osm-autocomplete-result"
        @click="selectResult(result)"
        :class="{ 'active': index === activeIndex }"
      >
        {{ formatDisplayAddress(result.properties) }}
      </div>
    </div>
    <!-- Debug info (remove in production) -->
    <div class="debug-info" style="display: none;">
      <p>Query: {{ query }}</p>
      <p>Results: {{ results.length }}</p>
      <p>Show Results: {{ showResults }}</p>
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
      userLocation: null
    }
  },
  mounted() {
    // Add event listener to document to close dropdown when clicking outside
    document.addEventListener('click', this.handleClickOutside);
    
    // Get user's location for better results
    this.getUserLocation();
    
    // Log to help with debugging
    console.log('OpenStreetMapAutocomplete component mounted');
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
    formatDisplayAddress(properties) {
      let displayName = '';
      console.log('Formatting address for properties:', properties);
      
      // Handle different types of results from Photon
      if (properties.type === 'house' || properties.osm_value === 'house') {
        // For house type, we want to prioritize the full address
        if (properties.housenumber && properties.street) {
          displayName = `${properties.housenumber} ${properties.street}`;
        } else if (properties.name && properties.street) {
          // Sometimes the name contains the house number
          displayName = `${properties.name}, ${properties.street}`;
        } else if (properties.name) {
          displayName = properties.name;
        } else if (properties.street) {
          displayName = properties.street;
        }
      } else {
        // For street type or other types
        if (properties.housenumber && properties.street) {
          displayName = `${properties.housenumber} ${properties.street}`;
        } else if (properties.street) {
          displayName = properties.street;
        } else if (properties.name) {
          displayName = properties.name;
        }
      }
      
      // If we have a name that's different from what we've already built, add it
      if (properties.name && 
          displayName && 
          !displayName.includes(properties.name) && 
          properties.name !== properties.street) {
        displayName = `${properties.name}, ${displayName}`;
      }
      
      // Add city/town/village - this is critical for geocoding
      let hasCity = false;
      if (properties.city) {
        displayName += displayName ? `, ${properties.city}` : properties.city;
        hasCity = true;
      } else if (properties.town) {
        displayName += displayName ? `, ${properties.town}` : properties.town;
        hasCity = true;
      } else if (properties.village) {
        displayName += displayName ? `, ${properties.village}` : properties.village;
        hasCity = true;
      }
      
      // Add state/province
      if (properties.state) {
        displayName += displayName ? `, ${properties.state}` : properties.state;
      }
      
      // Add country - this is critical for geocoding
      if (properties.country) {
        displayName += displayName ? `, ${properties.country}` : properties.country;
      }
      
      // Add postcode
      if (properties.postcode) {
        // For US addresses, put postcode after state
        if (properties.country === 'United States of America' || properties.country === 'USA') {
          displayName += displayName ? ` ${properties.postcode}` : properties.postcode;
        } else {
          // For other countries, put postcode before country
          const parts = displayName.split(', ');
          if (parts.length > 1 && properties.country && parts[parts.length - 1] === properties.country) {
            // Insert postcode before country
            parts.splice(parts.length - 1, 0, properties.postcode);
            displayName = parts.join(', ');
          } else {
            displayName += displayName ? `, ${properties.postcode}` : properties.postcode;
          }
        }
      }
      
      // Ensure we have at least a city and country for geocoding
      if (!hasCity && properties.country) {
        console.warn('Address is missing city/town/village, may be difficult to geocode');
      }
      
      // Format for Nominatim geocoding compatibility
      // Nominatim prefers: "street, city, state, country"
      if (!displayName) {
        displayName = 'Location';
      }
      
      return displayName;
    },
    async fetchResults() {
      if (!this.query || this.query.length < 3) {
        this.results = [];
        this.showResults = false;
        console.log('Query too short or empty, not fetching results');
        return;
      }

      try {
        console.log('Fetching results for:', this.query);
        
        // Build URL with location bias if available
        let url = `https://photon.komoot.io/api/?q=${encodeURIComponent(this.query)}&limit=20&lang=${this.lang}`;
        
        // Add location bias if we have user location
        if (this.userLocation) {
          url += `&lat=${this.userLocation.lat}&lon=${this.userLocation.lon}`;
        }
        
        console.log('Fetching from URL:', url);
        
        const response = await fetch(url);
        const data = await response.json();
        
        console.log('Photon API response:', data);
        
        let results = data.features || [];
        
        // Check if the query looks like a specific address with a number
        const hasNumberInQuery = /\d+/.test(this.query);
        
        // Sort results to prioritize locations with more complete information
        results = results.sort((a, b) => {
          const aProps = a.properties;
          const bProps = b.properties;
          
          // If query has a number, prioritize results with house numbers
          if (hasNumberInQuery) {
            const aHasHouseNumber = !!aProps.housenumber;
            const bHasHouseNumber = !!bProps.housenumber;
            if (aHasHouseNumber !== bHasHouseNumber) {
              return bHasHouseNumber - aHasHouseNumber;
            }
            
            // If both have house numbers, prioritize exact matches
            if (aHasHouseNumber && bHasHouseNumber) {
              const queryNumber = this.query.match(/\d+/)[0];
              const aExactMatch = aProps.housenumber === queryNumber;
              const bExactMatch = bProps.housenumber === queryNumber;
              if (aExactMatch !== bExactMatch) {
                return bExactMatch - aExactMatch;
              }
            }
          }
          
          // Prioritize house type over street type
          const aIsHouse = aProps.type === 'house' || aProps.osm_value === 'house';
          const bIsHouse = bProps.type === 'house' || bProps.osm_value === 'house';
          if (aIsHouse !== bIsHouse) {
            return bIsHouse - aIsHouse;
          }
          
          // Prioritize US locations if the query looks like a US address
          const isUSQuery = this.query.match(/\b(USA|US|United States)\b/i) || 
            this.query.match(/\b(AL|AK|AZ|AR|CA|CO|CT|DE|FL|GA|HI|ID|IL|IN|IA|KS|KY|LA|ME|MD|MA|MI|MN|MS|MO|MT|NE|NV|NH|NJ|NM|NY|NC|ND|OH|OK|OR|PA|RI|SC|SD|TN|TX|UT|VT|VA|WA|WV|WI|WY)\b/);
          
          if (isUSQuery) {
            const aIsUS = (aProps.country === 'United States of America' || aProps.country === 'USA');
            const bIsUS = (bProps.country === 'United States of America' || bProps.country === 'USA');
            if (aIsUS !== bIsUS) {
              return bIsUS - aIsUS;
            }
          }
          
          // Prioritize results with more complete information
          const aScore = this.getCompletionScore(aProps);
          const bScore = this.getCompletionScore(bProps);
          return bScore - aScore;
        });
        
        this.results = results;
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
    
    // Helper method to score how complete an address is
    getCompletionScore(properties) {
      let score = 0;
      
      // House type gets a high score
      if (properties.type === 'house' || properties.osm_value === 'house') score += 10;
      
      // Address components
      if (properties.housenumber) score += 8;
      if (properties.street) score += 6;
      if (properties.name) score += 4;
      
      // Location components
      if (properties.city) score += 3;
      else if (properties.town) score += 3;
      else if (properties.village) score += 3;
      
      if (properties.state) score += 2;
      if (properties.postcode) score += 2;
      if (properties.country) score += 1;
      
      return score;
    },
    selectResult(result) {
      const properties = result.properties;
      const coordinates = result.geometry.coordinates;
      
      console.log('Selected result properties:', properties);
      
      // Format display name from properties using our helper method
      const displayName = this.formatDisplayAddress(properties);
      
      this.query = displayName;
      this.showResults = false;
      
      // Ensure we have a valid location string that will pass validation
      // The backend expects at least a street and city
      let validLocation = false;
      
      // Check if we have the minimum required data for geocoding
      const hasStreet = !!(properties.housenumber || properties.street);
      const hasCity = !!(properties.city || properties.town || properties.village);
      const hasCountry = !!properties.country;
      
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
        
        if (properties.street) parts.push(properties.street);
        if (properties.city) parts.push(properties.city);
        else if (properties.town) parts.push(properties.town);
        else if (properties.village) parts.push(properties.village);
        
        if (properties.state) parts.push(properties.state);
        if (properties.country) parts.push(properties.country);
        
        if (parts.length >= 2) {
          geocodableAddress = parts.join(', ');
          console.log('Constructed geocodable address:', geocodableAddress);
          validLocation = true;
        }
      }
      
      const addressData = {
        latitude: coordinates[1],  // Photon returns [lon, lat]
        longitude: coordinates[0],
        formattedAddress: displayName,
        geocodableAddress: geocodableAddress, // Address formatted for geocoding
        postcode: properties.postcode,
        housenumber: properties.housenumber,
        street: properties.street,
        city: properties.city || properties.town || properties.village,
        state: properties.state,
        country: properties.country,
        name: properties.name,
        type: properties.type || properties.osm_value,
        osm_type: properties.osm_type,
        osm_value: properties.osm_value,
        validLocation: validLocation
      };
      
      this.selectedPlace = {
        formatted_address: displayName,
        geocodable_address: geocodableAddress,
        properties: properties,
        validLocation: validLocation
      };
      
      // Emit the placechanged event with the selected place data
      this.$emit('placechanged', addressData, this.selectedPlace);
    }
  }
}
</script>

<style scoped>
.osm-autocomplete {
  position: relative;
  width: 100%;
}

.osm-autocomplete-results {
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

.osm-autocomplete-result {
  padding: 10px 12px;
  cursor: pointer;
  border-bottom: 1px solid #eee;
  white-space: normal;
  line-height: 1.4;
  font-size: 14px;
}

.osm-autocomplete-result:last-child {
  border-bottom: none;
}

.osm-autocomplete-result:hover,
.osm-autocomplete-result.active {
  background-color: #f5f5f5;
}

/* Debug styles */
.debug-info {
  margin-top: 10px;
  padding: 10px;
  background: #f8f9fa;
  border: 1px solid #ddd;
  border-radius: 4px;
}
</style> 