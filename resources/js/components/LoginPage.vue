<template>
  <div>
    <div class="row row-expanded pb-3">
      <div class="col-lg-6 d-flex">
        <div class="card card__login col-12 panel">
          <legend>{{ translatedLoginTitle }}</legend>

          <div class="text-center">
            <p class="mb-4 text-muted">{{ translatedLoginDescription }}</p>

            <b-button variant="primary" @click="loginWithIFixit" class="btn-lg btn-block" size="lg">
              {{ translatedLoginWithIFixit }}
            </b-button>

            <p class="mt-3 text-muted small">
              {{ translatedLoginHelp }}
            </p>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card card__content col-12 panel panel__orange">
          <h3 style="font-weight:700">{{ translatedWhatIs }}</h3>
          <!-- eslint-disable-next-line -->
          <div v-html="translatedWhatIsContent" />
          <a href="/about" class="card__link">{{ translatedMore }}</a>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
export default {
  components: {},
  props: {
    iFixitEnabled: {
      type: Boolean,
      required: false,
      default: true
    }
  },
  computed: {
    translatedLoginTitle() {
      return this.$lang.get('login.login_title')
    },
    translatedLoginDescription() {
      return this.$lang.get('auth.ifixit_login_description') || 'Sign in with your iFixit account to access the Restarters platform.'
    },
    translatedLoginWithIFixit() {
      return this.$lang.get('auth.login_with_ifixit')
    },
    translatedLoginHelp() {
      return this.$lang.get('auth.ifixit_login_help') || 'Don\'t have an iFixit account? You can create one for free on iFixit.com'
    },
    translatedWhatIs() {
      return this.$lang.get('login.whatis')
    },
    translatedWhatIsContent() {
      return this.$lang.get('login.whatis_content')
    },
    translatedMore() {
      return this.$lang.get('login.more')
    }
  },
  methods: {
    loginWithIFixit() {
      // Get the current URL for redirect after login
      const redirectUrl = new URLSearchParams(window.location.search).get('redirect') || '/dashboard'

      // Construct the iFixit login URL with redirect
      const iFixitLoginUrl = `/auth/ifixit/login?redirect=${encodeURIComponent(redirectUrl)}`

      // Redirect to iFixit login
      window.location.href = iFixitLoginUrl
    }
  },
  mounted() {
    // If iFixit auth is disabled, show error
    if (!this.iFixitEnabled) {
      console.error('iFixit authentication is disabled')
    }
  }
}
</script>