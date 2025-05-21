{{-- 
  Logo Container Partial
  This partial is used to wrap logo SVGs with a responsive container.
  Parameters:
  - $instance: The app instance name (defaults to the value from env('APP_INSTANCE'))
--}}

@php
  $instance = $instance ?? (env('APP_INSTANCE') === 'base' ? 'restarters' : (env('APP_INSTANCE') ?: 'restarters')); 
@endphp

<style>
  .logo-container {
    width: 100%;
    height: auto;
    display: flex;
    align-items: center;
    justify-content: center;

    @media (max-width: 768px) {
      width: 100%;
    }
  }

  .logo-container svg {
    width: 100%;
    height: auto;
    max-width: 260px;
  }
</style>

<div class="logo-container">
  @include("includes/logo-{$instance}")
</div>

