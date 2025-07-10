<!doctype html>
<html class="body-plain" lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @yield('extra-meta')
        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="shortcut icon" href="/favicon-{{ (env('APP_INSTANCE') === 'base' ? 'restarters' : (env('APP_INSTANCE') ?: 'restarters')) }}.ico" type="image/x-icon" />

        <title>
            @hasSection('title')
            @yield('title')
            @else
            {{ config('app.name', 'Laravel') }}
            @endif
        </title>

        @yield('extra-css')

        <!-- Styles -->
        @if( isset($iframe) )
          <link href="{{ asset('css/app.css') }}" rel="stylesheet">
          <link href="{{ asset('css/iframe.css') }}" rel="stylesheet">
        @else
          <link href="{{ asset('css/app.css') }}" rel="stylesheet">
        @endif

        <!-- Meta tags for social previews. -->
        <meta data-hid="og:type" property="og:type" content="website">
        <meta data-hid="description" name="description" content="{{ __('landing.intro') }}">
        <meta data-hid="og:image" property="og:image" content="{{ url('/images/landing/'. (env('APP_INSTANCE') === 'base' ? 'restarters' : (env('APP_INSTANCE') ?: 'restarters')) .'/landing1.jpg') }}">
        <meta data-hid="og:locale" property="og:locale" content="{{ app()->getLocale() }}">
        <meta data-hid="og:title" property="og:title" content="{{ config('app.name', 'Laravel') }}">
        <meta data-hid="og:site_name" property="og:site_name" content="{{ config('app.name', 'Laravel') }}">
        <meta data-hid="og:url" property="og:url" content="{{ url()->current() }}">
        <meta data-hid="og:description" property="og:description" content="{{ __('landing.intro') }}">
        <meta data-hid="twitter:title" name="twitter:title" content="{{ config('app.name', 'Laravel') }}">
        <meta data-hid="twitter:description" name="twitter:description" content="{{ __('landing.intro') }}">
        <meta data-hid="twitter:image" name="twitter:image" content="{{ url('/images/landing/'. (env('APP_INSTANCE') === 'base' ? 'restarters' : (env('APP_INSTANCE') ?: 'restarters')) .'/landing1.jpg') }}">
        <meta data-hid="twitter:image:alt" name="twitter:image:alt" content="{{ env('META_TWITTER_IMAGE_ALT') }}">
        <meta data-hid="twitter:card" name="twitter:card" content="summary_large_image">
        <meta data-hid="twitter:site" name="twitter:site" content="{{ env('META_TWITTER_SITE') }}">


        <script>
            window.appInstance = "{{ env('APP_INSTANCE', 'base') }}";
            window.appDebug = "{{ env('APP_DEBUG', '0') }}";
            window.Laravel = {
                imageUploadEnabled: @json(config('restarters.features.image_upload_enabled', false)),
                uploadsUsingS3: @json(config('filesystems.disks.uploads.driver', 'local') === 's3'),
                aws_url: @json(env('AWS_URL', '')),
            };
            
            // Global helper for upload URLs
            window.getUploadUrl = function(filename, type = 'original') {
                if (!filename) return null;
                
                // Check if it's already a full URL (S3)
                if (filename.startsWith('http')) {
                    return filename;
                }
                
                // Determine the prefix based on type
                let prefix = '';
                if (type === 'thumbnail') {
                    prefix = 'thumbnail_';
                } else if (type === 'mid') {
                    prefix = 'mid_';
                }
                
                // Use CloudFront URL if available and using S3
                if (window.Laravel.uploadsUsingS3 && window.Laravel.aws_url) {
                    return `${window.Laravel.aws_url}uploads/${prefix}${filename}`;
                }
                
                // Fallback to local storage URL
                return `/uploads/${prefix}${filename}`;
            };
        </script>

        <!-- Cookie banner with fine-grained opt-in -->
        <script src="{{ asset('js/gdpr-cookie-notice.js') }}"></script>
        <!-- Check to see if visitor has opted in to analytics cookies -->
        <script>
         window.restarters = {};
         restarters.cookie_domain = '{{ env('SESSION_DOMAIN') }}';
         var gdprCookiesCheck = Cookies;
         var gdprCurrentCookiesSelection = gdprCookiesCheck.getJSON('gdprcookienotice');
         restarters.analyticsCookieEnabled = (typeof gdprCurrentCookiesSelection !== 'undefined' && gdprCurrentCookiesSelection['analytics']);
        </script>

        @if( config('restarters.features.matomo_integration') )
        <!-- Matomo -->
        <script>
            var _paq = window._paq = window._paq || [];
            /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
            _paq.push(['trackPageView']);
            _paq.push(['enableLinkTracking']);
            (function() {
                var u="https://restartproject.matomo.cloud/";
                _paq.push(['setTrackerUrl', u+'matomo.php']);
                _paq.push(['setSiteId', '1']);
                var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
                g.async=true; g.src='//cdn.matomo.cloud/restartproject.matomo.cloud/matomo.js'; s.parentNode.insertBefore(g,s);
            })();
        </script>
        <!-- End Matomo Code -->
        @endif
    </head>
    @if( Request::is('login') || Request::is('user/register') )
      <body class="fixed-layout">
    @elseif ( isset($onboarding) && $onboarding )
      <body class="onboarding">
    @else
      <body>
    @endif
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ env('GOOGLE_TAG_MANAGER_ID') }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->

        @if (isset($show_login_join_to_anons) && $show_login_join_to_anons)
            <div class="container container-nav">
                <nav class="navbar navbar-expand-md navbar-light">
                    <div class="d-none d-sm-block navbar-brand">
                        @include('partials.logo-container')
                    </div>
                    <div class="d-block d-sm-none">
                        @include('partials.logo-container',['instance' => 'plain'])
                    </div>

                <div id="navbarSupportedContent" class="collapse navbar-collapse">
                    <ul class="navbar-nav ml-auto">
                        <li><a class="nav-link" href="/login">@lang('login.login_title')</a></li>
                        <li><a class="nav-link" href="/user/register">@lang('login.join_title')</a></li>
                    </ul>
                </div>

                </nav>
            </div>
        @endif
