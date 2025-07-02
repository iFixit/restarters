@component('mail::layout')
{{-- Header --}}
@slot('header')
@php
  $instance = config('app.instance', 'base');
  $logoConfig = config("mail.logos.{$instance}", config('mail.logos.base'));
@endphp
@component('mail::header', ['url' => config('app.url')])
<img src="{{ asset("/images/{$logoConfig['file']}") }}" width="{{ $logoConfig['width'] }}" height="{{ $logoConfig['height'] }}" alt="{{ $logoConfig['alt'] }}">
@endcomponent
@endslot

{{-- Body --}}
{{ $slot }}

{{-- Subcopy --}}
@isset($subcopy)
@slot('subcopy')
@component('mail::subcopy')
{{ $subcopy }}
@endcomponent
@endslot
@endisset

{{-- Footer --}}
@slot('footer')
@component('mail::footer')
&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
@endcomponent
@endslot
@endcomponent
