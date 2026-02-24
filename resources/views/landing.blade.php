@include('layouts.header_plain')

@yield('content')

@php
  $instance = env('APP_INSTANCE') === 'base' ? 'restarters' : (env('APP_INSTANCE') ?: 'restarters');
@endphp

<section class="landing-page">

  {{-- Fix-O-Meter (sticks to top, morphs to compact bar on scroll) --}}
  <section class="fixometer" id="fixometer-hero">
    <div class="container">
      {{-- Header lives inside the fixometer so it's part of the same
           sticky/fixed element — no separate flow element to cause
           layout jumps on scroll-up --}}
      <div class="fixometer__header">
        @include('includes.info')
      </div>
      <h2 class="fixometer__title">{{ __('landing.fixometer_title') }}</h2>
      <div class="fixometer__grid">

        <div class="fixometer__card fixometer__card--gold">
          <img class="fixometer__icon" src="{{ asset('/images/trash.svg') }}" alt="" aria-hidden="true" />
          <div class="fixometer__value">{{ number_format($wasteTotalLbs, 0, '.', ',') }}</div>
          <div class="fixometer__unit">lbs</div>
          <div class="fixometer__label">{{ __('landing.fixometer_waste') }}</div>
        </div>

        <div class="fixometer__card fixometer__card--teal">
          <img class="fixometer__icon" src="{{ asset('/images/cloud_empty.svg') }}" alt="" aria-hidden="true" />
          <div class="fixometer__value">{{ number_format($co2Total, 0, '.', ',') }}</div>
          <div class="fixometer__unit">lbs</div>
          <div class="fixometer__label">{{ __('landing.fixometer_co2') }}</div>
        </div>

        <div class="fixometer__card fixometer__card--pink">
          <img class="fixometer__icon" src="{{ asset('/images/fixed.svg') }}" alt="" aria-hidden="true" />
          <div class="fixometer__value">{{ number_format($deviceCount, 0, '.', ',') }}</div>
          <div class="fixometer__label">{{ __('landing.fixometer_devices') }}</div>
        </div>

        <div class="fixometer__card fixometer__card--purple">
          <img class="fixometer__icon" src="{{ asset('/images/participants.svg') }}" alt="" aria-hidden="true" />
          <div class="fixometer__value">{{ number_format($volunteerCount, 0, '.', ',') }}</div>
          <div class="fixometer__label">{{ __('landing.fixometer_volunteers') }}</div>
        </div>

        <div class="fixometer__card fixometer__card--light">
          <img class="fixometer__icon" src="{{ asset('/images/parties.svg') }}" alt="" aria-hidden="true" />
          <div class="fixometer__value">{{ number_format($partiesCount, 0, '.', ',') }}</div>
          <div class="fixometer__label">{{ __('landing.fixometer_events') }}</div>
        </div>

      </div>
      <p class="fixometer__disclaimer">{{ __('landing.fixometer_disclaimer') }}</p>
    </div>
  </section>

  {{-- Spacer absorbs the height difference when fixometer compacts, preventing layout jump --}}
  <div id="fixometer-spacer"></div>

  <div class="container">

    {{-- Sentinel: when this scrolls out of view, hero has reached the top --}}
    <div id="fixometer-sentinel"></div>

    {{-- Hero — Split layout: text left, image right --}}
    <div class="hero hero--split">
      <div class="hero__content">
        <h1>{{ __('landing.title') }}</h1>
        <p class="hero__subtitle">{{ __('landing.subtitle') }}</p>
        <div class="hero__description">
          {!! __('landing.intro') !!}
        </div>
        <div class="hero__actions">
          <a href="/user/register" class="btn btn-primary btn-lg">{{ __('landing.cta_primary') }}</a>
          <a href="/login" class="btn btn-primary btn-lg">{{ __('landing.cta_secondary') }}</a>
        </div>
      </div>
      <div class="hero__image d-none d-md-block">
        <img src="{{ asset('/images/landing/'. $instance .'/landing1.jpg') }}" alt="{{ __('landing.landing_1_alt') }}" />
      </div>
    </div>

    {{-- How It Works — 3-step journey with photos --}}
    <section class="how-it-works">
      <h2 class="how-it-works__title">{{ __('landing.how_title') }}</h2>
      <div class="how-it-works__steps">

        <div class="how-it-works__step how-it-works__step--gold">
          <img class="how-it-works__photo" src="{{ asset('/images/landing/'. $instance .'/landing1.jpg') }}" alt="{{ __('landing.landing_1_alt') }}" />
          <div class="how-it-works__body">
            <div class="how-it-works__number">1</div>
            <h3>{{ __('landing.how_step1_title') }}</h3>
            <p>{{ __('landing.how_step1_desc') }}</p>
          </div>
        </div>

        <div class="how-it-works__step how-it-works__step--teal">
          <img class="how-it-works__photo" src="{{ asset('/images/landing/'. $instance .'/landing2.jpg') }}" alt="{{ __('landing.landing_2_alt') }}" />
          <div class="how-it-works__body">
            <div class="how-it-works__number">2</div>
            <h3>{{ __('landing.how_step2_title') }}</h3>
            <p>{{ __('landing.how_step2_desc') }}</p>
          </div>
        </div>

        <div class="how-it-works__step how-it-works__step--pink">
          <img class="how-it-works__photo" src="{{ asset('/images/landing/'. $instance .'/landing3.jpg') }}" alt="{{ __('landing.landing_3_alt') }}" />
          <div class="how-it-works__body">
            <div class="how-it-works__number">3</div>
            <h3>{{ __('landing.how_step3_title') }}</h3>
            <p>{{ __('landing.how_step3_desc') }}</p>
          </div>
        </div>

      </div>
    </section>
  </div>

  {{-- Final CTA Banner --}}
  <section class="cta-banner">
    <div class="container">
      <h2>{{ __('landing.cta_banner_title') }}</h2>
      <p>{{ __('landing.cta_banner_desc') }}</p>
      <div class="cta-banner__actions">
        <a href="/user/register" class="btn btn-light btn-lg">{{ __('landing.cta_primary') }}</a>
      </div>
    </div>
  </section>

  @include('layouts.footer')
</section>
</body>
</html>
