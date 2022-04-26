<!DOCTYPE html>
<html dir="ltr" lang="{{ config('twill.locale', 'en') }}">
    <head>
        @include('twill::partials.head')
        @livewireStyles
    </head>
    <body class="env env--{{ app()->environment() }} @yield('appTypeClass')">
        @include('twill::partials.icons.svg-sprite')
        @if(config('twill.enabled.search', false))
            @partialView(($moduleName ?? null), 'navigation._overlay_navigation', ['search' => true])
        @else
            @partialView(($moduleName ?? null), 'navigation._overlay_navigation')
        @endif
        <div class="a17">
            <header class="header">
                <div class="container">
                    @partialView(($moduleName ?? null), 'navigation._title')
                    @partialView(($moduleName ?? null), 'navigation._global_navigation')
                    <div class="header__user" id="headerUser" v-cloak>
                        @partialView(($moduleName ?? null), 'navigation._user')
                    </div>
                    @if(config('twill.enabled.search', false) && !($isDashboard ?? false))
                      <div class="headerSearch" id="searchApp">
                        <a href="#" class="headerSearch__toggle" @click.prevent="toggleSearch">
                          <span v-svg symbol="search" v-show="!open"></span>
                          <span v-svg symbol="close_modal" v-show="open"></span>
                        </a>
                        <transition name="fade_search-overlay" @after-enter="afterAnimate">
                          <div class="headerSearch__wrapper" :style="positionStyle" v-show="open" v-cloak>
                            <div class="headerSearch__overlay" :style="positionStyle" @click="toggleSearch"></div>
                            <a17-search endpoint="{{ route(config('twill.dashboard.search_endpoint')) }}" :open="open" :opened="opened"></a17-search>
                          </div>
                        </transition>
                      </div>
                    @endif
                </div>
            </header>
            @hasSection('primaryNavigation')
                @yield('primaryNavigation')
            @else
                @partialView(($moduleName ?? null), 'navigation._primary_navigation')
                @partialView(($moduleName ?? null), 'navigation._secondary_navigation')
                @partialView(($moduleName ?? null), 'navigation._breadcrumb')
            @endif
            <section class="main">
                <div class="app" id="app">
                    @yield('content')
                    @if (config('twill.enabled.media-library') || config('twill.enabled.file-library'))
                        <a17-medialibrary ref="mediaLibrary"
                                          :authorized="{{ json_encode(auth('twill_users')->user()->can('edit-media-library')) }}"
                                          :extra-metadatas="{{ json_encode(array_values(config('twill.media_library.extra_metadatas_fields', []))) }}"
                                          :translatable-metadatas="{{ json_encode(array_values(config('twill.media_library.translatable_metadatas_fields', []))) }}"
                        ></a17-medialibrary>
                        <a17-dialog ref="deleteWarningMediaLibrary" modal-title="{{ twillTrans("twill::lang.media-library.dialogs.delete.delete-media-title") }}" confirm-label="{{ twillTrans("twill::lang.media-library.dialogs.delete.delete-media-confirm") }}">
                            <p class="modal--tiny-title"><strong>{{ twillTrans("twill::lang.media-library.dialogs.delete.delete-media-title") }}</strong></p>
                            <p>{!! twillTrans("twill::lang.media-library.dialogs.delete.delete-media-desc") !!}</p>
                        </a17-dialog>
                        <a17-dialog ref="replaceWarningMediaLibrary" modal-title="{{ twillTrans("twill::lang.media-library.dialogs.replace.replace-media-title") }}" confirm-label="{{ twillTrans("twill::lang.media-library.dialogs.replace.replace-media-confirm") }}">
                            <p class="modal--tiny-title"><strong>{{ twillTrans("twill::lang.media-library.dialogs.replace.replace-media-title") }}</strong></p>
                            <p>{!! twillTrans("twill::lang.media-library.dialogs.replace.replace-media-desc") !!}</p>
                        </a17-dialog>
                    @endif
                    <a17-notif variant="success"></a17-notif>
                    <a17-notif variant="error"></a17-notif>
                    <a17-notif variant="info" :auto-hide="false" :important="false"></a17-notif>
                    <a17-notif variant="warning" :auto-hide="false" :important="false"></a17-notif>
                </div>
                <div class="appLoader">
                    <span>
                        <span class="loader"><span></span></span>
                    </span>
                </div>
                @include('twill::partials.footer')
            </section>
        </div>

        <form style="display: none" method="POST" action="{{ route('twill.logout') }}" data-logout-form>
            @csrf
        </form>

        {{-- LIVEWIRE --}}
        @livewireScripts
        {{-- Toast: https://github.com/usernotnull/tall-toasts --}}
        @toastScripts
        {{-- Sortable --}}
        <script src="https://unpkg.com/@nextapps-be/livewire-sortablejs@0.1.1/dist/livewire-sortable.js" defer></script>
        {{-- AlpineJs --}}
        <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
        <livewire:toasts />
        {{-- Safelist classes --}}
        {{-- modalwidth comment for tailwind purge, used widths: sm:max-w-sm sm:max-w-md sm:max-w-lg sm:max-w-xl sm:max-w-2xl sm:max-w-3xl sm:max-w-4xl sm:max-w-5xl sm:max-w-6xl sm:max-w-7xl --}}
        @livewire('livewire-ui-modal')
    </body>
</html>
