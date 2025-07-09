@include('layouts.header_plain')
@yield('content')
<section class="login-page">
    <div class="container">

        @include('includes.info')

        @if (\Session::has('success'))
            {{-- This is used by password reset. --}}
            <div class="alert alert-success">
                {!! \Session::get('success') !!}
            </div>
        @endif

        <div class="vue">
            <LoginPage
                :i-fixit-enabled="{{ config('external_auth.enabled', true) ? 'true' : 'false' }}"
            />
        </div>

    </div>
</section>
@include('layouts.footer')
