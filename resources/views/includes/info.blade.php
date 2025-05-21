<style>
    .header-row {
        display: flex;
        width: 100%;
        align-items: center;
        margin: 10px 0 40px;

        @media (max-width: 767px) {
            margin: 10px 0;
        }
    }

    .logo-container-link {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 25%;

        @media (max-width: 767px) {
            width: 100%;
        }
    }

    .stats-container {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        width: 75%;

        @media (max-width: 767px) {
            display: none;
        }
    }
    
    .stats-row {
        display: flex;
        justify-content: space-around;
        text-align: center;
        width: 75%;
        align-items: center;
        height: 100%;
    }

    .stats__stat {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 0 10px;
    }

    .stat-header {
        font-family: 'Open Sans';
        margin: 10px 0 0;
        color: $brand;
        font-size: 14px;
        line-height: 1;
    }
    .stat-figure {
        font-family: 'Asap';
        font-weight: 700;
        font-size: 34px;
        line-height: 1;
        margin: 0;
    }
</style>

<div class="header-row" id="logostats-header">
    <div class="logo-container-link">
          <a href="/">
            @include('partials.logo-container')
          </a>
    </div>
    <div class="stats-container">
        <div class="stats-row">
            <div class="stats__stat">
                <div class="stat-figure">{{ number_format($deviceCount, 0, '.', ',') }}</div>
                <div class="stat-header">@lang('login.stat_1')</div>
            </div>
            <div class="stats__stat">
                <div class="stat-figure">{{ number_format(round($co2Total), 0, '.', ',') }} kg</div>
                <div class="stat-header">@lang('login.stat_2')</div>
            </div>
            <div class="stats__stat">
                <div class="stat-figure">{{ number_format(round($wasteTotal), 0, '.', ',') }} kg</div>
                <div class="stat-header">@lang('login.stat_3')</div>
            </div>
            <div class="stats__stat">
                <div class="stat-figure">{{ number_format($partiesCount, 0, '.', ',') }}</div>
                <div class="stat-header">@lang('login.stat_4')</div>
            </div>
        </div>
    </div>
</div>
