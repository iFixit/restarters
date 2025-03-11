<template>
  <div>
    <div class="row row-expanded pb-3">
      <div class="col-lg-6 d-flex">
        <form id="login-form" action="/login" method="post" class="card card__login col-12 panel">

          <input type="hidden" name="_token" :value="CSRF" />

          <div id="my_name_wrap" style="display:none;">
            <input name="my_name" type="text" value="" id="my_name">
            <input name="my_time" type="text" :value="time">
          </div>

          <legend>{{ $translate('login.login_title') }}</legend>

          <div class="form-group">
            <label for="fp_email">{{ $translate('auth.email_address') }}:</label>
            <b-form-input type="email" name="email" id="fp_email" :value="email" required autofocus />
          </div>

          <div class="form-group">
            <label for="password">{{ $translate('auth.password') }}:</label>
            <b-form-input type="password" name="password" id="password" required />
          </div>

          <div v-if="error">
            <div class="alert alert-danger" role="alert">
              {{ $translate('auth.failed') }}
            </div>
          </div>
          <div class="row entry-panel__actions">
            <div class="col-6 col-md-8 align-content-center flex-column d-flex">
              <div class="row">
                <div class="col-12">
                  <a class="entry-panel__link" href="/user/recover">{{ $translate('auth.forgot_password') }}</a>
                </div>
                <div class="col-12">
                  <a class="entry-panel__link" href="/user/register">{{ $translate('auth.create_account') }}</a>
                </div>
              </div>
            </div>
            <div class="col-6 col-md-4 align-content-center flex-column justify-content-end d-flex">
              <b-button id="login-form-submit" type="submit" variant="primary" @click="login" :disabled="disabled">{{ $translate('auth.login') }}</b-button>
            </div>
          </div>
        </form>
      </div>
      <div class="col-lg-6">
        <div class="card card__content col-12 panel panel__orange">
          <h3 style="font-weight:700">{{ $translate('login.whatis') }}</h3>
          <!-- eslint-disable-next-line -->
          <div v-html="$translate('login.whatis_content')" />
          <a href="/about" class="card__link">{{ $translate('login.more') }}</a>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import auth from '../mixins/auth'

export default {
  components: {},
  mixins: [ auth ],
  props: {
    error: {
      type: Boolean,
      required: true
    },
    time: {
      type: String,
      required: true
    },
    email: {
      type: String,
      required: true
    }
  },
  data () {
    return {
      disabled: false,
    }
  },
  computed: {
    CSRF() {
      return this.$store.getters['auth/CSRF']
    }
  },
  methods: {
    login() {
      // We've seen double submits of the login form, leading to 419 errors.  Prevent the user submitting twice by
      // double-clicking.
      //
      // The default event handler will proceed to validate the form (because of the required attributes) and
      // submit or show a native error.
      this.submitDisabled = true
    }
  }
}
</script>