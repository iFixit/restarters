<template>
  <div class="badge-group">
    <button id="notifications-badge" :class="{
      'badge': true,
      'badge-pill': true,
      'badge-info': true,
      'badge-right': true,
      'd-flex': true
       }" data-toggle="collapse" data-target="#notifications" aria-expanded="false" aria-controls="notifications">
      <svg width="22" height="20" viewBox="0 0 11 15" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd"
           clip-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="1.414">
        <g fill="#fff">
          <ellipse cx="5.25" cy="4.868" rx="3.908" ry="3.94"/>
          <path
              d="M4.158 13.601h2.184v.246h-.001A1.097 1.097 0 0 1 5.25 15a1.097 1.097 0 0 1-1.092-1.101l.001-.052h-.001v-.246z"/>
          <ellipse cx=".671" cy="12.337" rx=".671" ry=".677"/>
          <path d="M.671 11.66h9.158v1.353H.671z"/>
          <ellipse cx="5.25" cy=".927" rx=".92" ry=".927"/>
          <ellipse cx="9.829" cy="12.337" rx=".671" ry=".677"/>
          <path d="M1.342 4.439h7.815v8.574H1.342z"/>
          <path d="M0 12.337h10.5v.677H0z"/>
        </g>
      </svg>
      <div class="chat-count">
        <!-- eslint-disable-next-line-->
        <span v-html="padCount(restartersNotifications)" />
      </div>
    </button>
  </div>
</template>
<script>
const axios = require('axios')

export default {
  props: {
    userId: {
      type: Number,
      required: true
    },
  },
  data () {
    return {
      restartersNotifications: null
    }
  },
  computed: {
    url () {
      return '/notifications'
    },
  },
  mounted() {
    setTimeout(async() => {
      const ret = await axios.get('/api/users/' + this.userId + '/notifications/')

      if (ret.data.success) {
        this.restartersNotifications = ret.data.restarters
      }
    }, 5000)
  },
  methods: {
    padCount(val) {
      if (val === null) {
        val = '--'
      } else if (val > 99) {
        val = 99
      }

      return val
    },
    goto() {
      window.location = this.url
    }
  }
}
</script>
<style scoped lang="scss">
.chat-count {
  width: 27px;
  font-size: 16px;
  top: 2px;
}
</style>