@include('layouts.header_plain')

@yield('content')

<style>
    .landing-page {
        padding-top: 31px;
    }

    .landing-page .row-expanded {
        width: 100%;
    }

    .landing-section {
        display: flex;
        background-color: #fff;
        border: 1px solid black;
        -webkit-box-shadow: 6px 6px 0 0 black;
        box-shadow: 6px 6px 0 0 black;
    }

    .landing-section img {
        max-height: 250px;
        object-fit: cover;
    }

    .landing-section > div {
        padding: 20px;
    }

    .landing-section .landing-icon {
        max-height: 30px;
        width: 30px !important;
        margin-right: 5px;
    }

    .landing-page .has-background-gold {background-color: #FFBE5F;}
    .landing-page .has-background-teal {background-color: #4AAEBC;}
    .landing-page .has-background-pink {background-color: #F49292;}
    .landing-page .has-background-purple {background-color: #80a4e0; }

    .landing-page h1, h2 {
        font-weight: bold;
    }

    .landing-page .landing-hr {
        border-top: 3px dashed black;
    }

    .landing-page .network-left {
        width: 45%;
        padding-right: 1rem;
    }

    .landing-page .network-right {
        width: 55%;
        padding-left: 1rem;
    }

    @media only screen and (max-width: 767px) {
        .landing-page .network-left {
            width: 100%;
            padding-right: 0;
        }
        .landing-page .network-right {
            width: 100%;
            padding-left: 0rem;
        }
    }

    .landing-section p:not(.noindent) {
        padding-left: 39px;
        text-indent: -39px;
    }

    .textlarge {
        font-size: 20px;
    }

    .hero-section {
        text-align: center;

        @media (max-width: 767px) {
          margin-top: 40px;
        }
    }

    .hero-title {
        margin-bottom: 25px;
    }

    .hero-description {
        max-width: 800px;
        margin: 0 auto 30px;
    }

    .hero-buttons {
        margin-top: 30px;
        display: flex;
        justify-content: center;
        gap: 20px;
    }

    .footer-wrapper {
        margin-top: 1rem;
    }
</style>
<section class="landing-page">
  <div class="container">
    <div class="d-flex justify-content-around justify-content-md-start">
      @include('includes.info')
    </div>

    <div class="hero-section">
      <h1 class="hero-title">{{ __('landing.title') }}</h1>
      <div class="hero-description textlarge">
        {!! __('landing.intro') !!}
      </div>
      <div class="hero-buttons">
        <a href="/user/register" class="btn btn-primary">{{ __('landing.join') }}</a>
        <a href="/login" class="btn btn-primary">{{ __('landing.login') }}</a>
      </div>
    </div>

    <div class="row mt-5">
      <div class="col-12 col-md-8 offset-md-2">
        <div class="landing-section has-background-gold">
          <img src="{{ asset('/images/landing/landing1.jpg') }}" alt="Repair Skills (credit Mark Phillips)" class="d-none d-md-block" />
          <div>
            <h2>{{ __('landing.learn') }}</h2>
            <p>
              <img class="landing-icon" src="{{ asset('/images/landing/icon-book.svg') }}" /> {{ __('landing.repair_skills') }}
            </p>
            <p>
              <img class="landing-icon" src="{{ asset('/images/landing/icon-chat-bubble.svg') }}" /> {{ __('landing.repair_advice') }}
            </p>
            <p>
              <img class="landing-icon" src="{{ asset('/images/landing/icon-group.svg') }}" /> {{ __('landing.repair_group') }}
            </p>
            <div class="d-flex justify-content-around justify-content-md-start">
              <a href="/user/register" class="btn btn-primary">{{ __('landing.repair_start') }}</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row mt-4">
      <div class="col-12 col-md-8 offset-md-2">
        <div class="landing-section has-background-teal justify-content-between">
          <div>
            <h2>{{ __('landing.organise') }}</h2>
            <p>
              <img class="landing-icon" src="{{ asset('/images/landing/icon-chat-bubble.svg') }}" /> {{ __('landing.organise_advice') }}
            </p>
            <p>
              <img class="landing-icon" src="{{ asset('/images/landing/icon-group.svg') }}" /> {{ __('landing.organise_manage') }}
            </p>
            <p>
              <img class="landing-icon" src="{{ asset('/images/landing/icon-drill.svg') }}" /> {{ __('landing.organise_publicise') }}
            </p>
            <div class="d-flex justify-content-around justify-content-md-start">
              <a href="/user/register" class="btn btn-primary">{{ __('landing.organise_start') }}</a>
            </div>
          </div>
          <img src="{{ asset('/images/landing/landing2.jpg') }}" alt="Restart Party (credit Mark Phillips)" class="d-none d-md-block" />
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-12 col-md-8 offset-md-2 mt-4">
        <div class="landing-section has-background-pink">
          <img src="{{ asset('/images/landing/landing3.jpg') }}" alt="Restart Crowd (credit Mark Phillips)" class="d-none d-md-block" />
          <div>
            <h2>{{ __('landing.campaign') }}</h2>
            <p>
              <img class="landing-icon" src="{{ asset('/images/landing/icon-group.svg') }}" /> {{ __('landing.campaign_join') }}
            </p>
            <p>
              <img class="landing-icon" src="{{ asset('/images/landing/icon-book.svg') }}" /> {{ __('landing.campaign_barriers') }}
            </p>
            <p>
              <img class="landing-icon" src="{{ asset('/images/landing/icon-microscope.svg') }}" /> {{ __('landing.campaign_data') }}
            </p>
            <div class="d-flex justify-content-around justify-content-md-start">
              <a href="/user/register" class="btn btn-primary">{{ __('landing.campaign_start') }}</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-12 col-md-8 offset-md-2 mt-4">
        <hr class="landing-hr" />
      </div>
    </div>

    <div class="row">
      @if(env('APP_INSTANCE') != 'fixitclinic')
        <div class="col-12 col-md-8 offset-md-2 mt-4">
          <h1>
            {{ __('landing.need_more') }}
          </h1>
          <div class="landing-section has-background-purple mt-4 mb-4">
            <div class="d-flex justify-content-between flex-wrap">
              <div class="network-left">
                <div class="d-flex flex-column justify-content-between h-100">
                  <div class="flex-grow-1">
                    <h2>{{ __('landing.network') }}</h2>
                    <p class="noindent">{{ __('landing.network_blurb') }}</p>
                  </div>
                  <div class="d-none d-md-block">
                    <a href="{{ __('landing.network_start_url') }}" class="btn btn-primary">{{ __('landing.network_start') }}</a>
                  </div>
                </div>
              </div>
              <div class="network-right">
                <p>
                  <img class="landing-icon" src="{{ asset('/images/landing/icon-chat-bubble.svg') }}" /> {{ __('landing.network_tools') }}
                </p>
                <p>
                  <img class="landing-icon" src="{{ asset('/images/landing/icon-cal.svg') }}" /> {{ __('landing.network_events') }}
                </p>
                <p>
                  <img class="landing-icon" src="{{ asset('/images/landing/icon-drill.svg') }}" /> {{ __('landing.network_record') }}
                </p>
                <p>
                  <img class="landing-icon" src="{{ asset('/images/landing/icon-microscope.svg') }}" /> {{ __('landing.network_impact') }}
                </p>
                <p>
                  <img class="landing-icon" src="{{ asset('/images/landing/icon-group.svg') }}" /> {{ __('landing.network_brand') }}
                </p>
                <p class="mb-0">
                  <img class="landing-icon" src="{{ asset('/images/landing/icon-book.svg') }}" /> {{ __('landing.network_power') }}
                </p>
                <div class="d-flex d-md-none mt-2 justify-content-around">
                  <a href="https://therestartproject.org/contact/" class="btn btn-primary">{{ __('landing.network_start') }}</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      @endif
    </div>
  </div>
  
  @include('layouts.footer')
</section>
</body>
</html>

