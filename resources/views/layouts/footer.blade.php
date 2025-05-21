    @include('partials.onboarding')

    <footer class="site-footer">
      <div class="container position-relative">
        <div class="row">
          <div class="col-12 text-center py-3">
              <p>
                Powered by <a href="https://therestartproject.org">Restarters.net</a>, an open source software for organizing community repair.
              </p>
          </div>
        </div>
        
        <div class="language-selector">
          @include('partials.languages')
        </div>
      </div>
    </footer>

    <style>
      .site-footer {
        background-color: #f8f9fa;
        border-top: 1px solid #e9ecef;
        margin-top: 0.5rem;
        position: relative;
        width: 100%;
      }
      
      .site-footer p {
        margin-bottom: 0;
      }
      
      .footer-link {
        color: #4a4a4a;
        transition: color 0.2s ease;
        text-decoration: none;
      }
      
      .footer-link:hover {
        color: #000;
        text-decoration: underline;
      }
      
      .language-selector {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
      }
      
      @media (max-width: 767px) {
        .language-selector {
          position: static;
          margin-top: 0.5rem;
          text-align: center;
          transform: none;
        }
      }
    </style>

    <script src="{{ asset('js/app.js') }}"></script>

    @yield('scripts')
  </body>
</html>
